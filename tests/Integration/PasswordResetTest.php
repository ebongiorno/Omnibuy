<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../../includes/password-reset.php';

/**
 * UC-03 reset tokens against the real database: creation, expiry,
 * single use, cancellation and the request limit.
 */
final class PasswordResetTest extends DatabaseTestCase
{
    private const NEW_PASSWORD = 'Br4ndNewPass';

    public function testResetStoresOnlyTheTokenHash(): void
    {
        $userId = $this->createUser('reset');

        $reset = createPasswordReset($this->pdo, $this->emailOf($userId));

        $this->assertNotNull($reset);
        $this->assertSame($userId, $reset['user']['user_id']);
        $this->assertTrue(isWellFormedResetToken($reset['token']));

        $row = $this->latestReset($userId);
        $this->assertSame(passwordResetTokenHash($reset['token']), $row['token_hash']);
        $this->assertNotSame($reset['token'], $row['token_hash'], 'The raw token must never be stored');
        $this->assertNull($row['used_at']);
    }

    public function testResetExpiresAfterThirtyMinutes(): void
    {
        $userId = $this->createUser('reset');
        createPasswordReset($this->pdo, $this->emailOf($userId));

        $minutes = (int) $this->pdo->query("
            SELECT TIMESTAMPDIFF(MINUTE, created_at, expires_at) FROM password_resets WHERE user_id = $userId
        ")->fetchColumn();

        $this->assertSame(PASSWORD_RESET_LIFETIME_MINUTES, $minutes);
    }

    public function testEmailMatchIgnoresCaseAndSpaces(): void
    {
        $userId = $this->createUser('reset');

        $this->assertNotNull(createPasswordReset($this->pdo, '  ' . strtoupper($this->emailOf($userId)) . ' '));
    }

    public function testUnknownEmailCreatesNothing(): void
    {
        $this->assertNull(createPasswordReset($this->pdo, 'nobody_' . $this->uniqueSuffix() . '@example.com'));
    }

    public function testSuspendedAccountCannotReset(): void
    {
        $userId = $this->createUser('reset');
        $this->pdo->exec("UPDATE users SET account_status = 'suspended' WHERE user_id = $userId");

        $this->assertNull(createPasswordReset($this->pdo, $this->emailOf($userId)));
    }

    public function testNewRequestCancelsOlderLinks(): void
    {
        $userId = $this->createUser('reset');
        $first = createPasswordReset($this->pdo, $this->emailOf($userId));
        $second = createPasswordReset($this->pdo, $this->emailOf($userId));

        $this->assertNull(findPasswordReset($this->pdo, $first['token']));
        $this->assertNotNull(findPasswordReset($this->pdo, $second['token']));
    }

    public function testRequestsAreLimitedPerAccount(): void
    {
        // Security: the form can't be used to flood someone's inbox.
        $userId = $this->createUser('reset');

        for ($i = 0; $i < PASSWORD_RESET_MAX_REQUESTS; $i++) {
            $this->assertNotNull(createPasswordReset($this->pdo, $this->emailOf($userId)));
        }

        $this->assertNull(createPasswordReset($this->pdo, $this->emailOf($userId)));
    }

    public function testResetChangesPasswordAndUsesUpTheLink(): void
    {
        $userId = $this->createUser('reset');
        $reset = createPasswordReset($this->pdo, $this->emailOf($userId));

        $errors = resetPasswordWithToken($this->pdo, $reset['token'], self::NEW_PASSWORD, self::NEW_PASSWORD);

        $this->assertSame([], $errors);
        $this->assertTrue(password_verify(self::NEW_PASSWORD, $this->passwordHashOf($userId)));
        $this->assertNotNull($this->latestReset($userId)['used_at']);
        $this->assertNotNull(attemptLogin($this->pdo, $this->emailOf($userId), self::NEW_PASSWORD)['user']);
    }

    public function testLinkWorksOnlyOnce(): void
    {
        $userId = $this->createUser('reset');
        $reset = createPasswordReset($this->pdo, $this->emailOf($userId));
        resetPasswordWithToken($this->pdo, $reset['token'], self::NEW_PASSWORD, self::NEW_PASSWORD);

        $errors = resetPasswordWithToken($this->pdo, $reset['token'], 'An0therPass', 'An0therPass');

        $this->assertArrayHasKey('token', $errors);
        $this->assertTrue(password_verify(self::NEW_PASSWORD, $this->passwordHashOf($userId)));
    }

    public function testExpiredLinkIsRejected(): void
    {
        $userId = $this->createUser('reset');
        $reset = createPasswordReset($this->pdo, $this->emailOf($userId));
        $this->pdo->exec("UPDATE password_resets SET expires_at = NOW() - INTERVAL 1 MINUTE WHERE user_id = $userId");
        $oldHash = $this->passwordHashOf($userId);

        $errors = resetPasswordWithToken($this->pdo, $reset['token'], self::NEW_PASSWORD, self::NEW_PASSWORD);

        $this->assertArrayHasKey('token', $errors);
        $this->assertSame($oldHash, $this->passwordHashOf($userId));
    }

    public function testWeakPasswordKeepsLinkUsable(): void
    {
        $userId = $this->createUser('reset');
        $reset = createPasswordReset($this->pdo, $this->emailOf($userId));

        $errors = resetPasswordWithToken($this->pdo, $reset['token'], 'short', 'short');

        $this->assertArrayHasKey('password', $errors);
        $this->assertNotNull(findPasswordReset($this->pdo, $reset['token']), 'A typo must not burn the link');
    }

    public function testMadeUpTokenIsRejected(): void
    {
        $this->assertArrayHasKey('token', resetPasswordWithToken(
            $this->pdo,
            bin2hex(random_bytes(32)),
            self::NEW_PASSWORD,
            self::NEW_PASSWORD
        ));
    }

    public function testDeletingAccountDeletesItsResets(): void
    {
        $userId = $this->createUser('reset');
        createPasswordReset($this->pdo, $this->emailOf($userId));

        $this->pdo->exec("DELETE FROM users WHERE user_id = $userId");

        $this->assertFalse($this->latestReset($userId));
    }

    private function emailOf(int $userId): string
    {
        return (string) $this->pdo->query("SELECT email FROM users WHERE user_id = $userId")->fetchColumn();
    }

    private function passwordHashOf(int $userId): string
    {
        return (string) $this->pdo->query("SELECT password_hash FROM users WHERE user_id = $userId")->fetchColumn();
    }

    private function latestReset(int $userId): array|false
    {
        return $this->pdo->query("
            SELECT * FROM password_resets WHERE user_id = $userId ORDER BY reset_id DESC LIMIT 1
        ")->fetch();
    }
}
