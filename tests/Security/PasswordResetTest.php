<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Security;

use OmniBuy\Tests\Support\HttpClient;

/**
 * CC6.1 SEC-06: reset links stop working once used or expired, and the
 * forgot-password form doesn't reveal which emails have accounts.
 *
 * Reset links are inserted directly with a known token, the same way
 * includes/password-reset.php stores them (SHA-256 of the token).
 */
final class PasswordResetTest extends SecurityTestCase
{
    private const NEW_PASSWORD = 'Br4ndNewPass';
    private const OTHER_PASSWORD = 'Th1rdPassword';

    /** SEC-06 */
    public function testExpiredLinkIsRejectedWithoutChangingThePassword(): void
    {
        $account = $this->createAccount();
        $token = $this->createResetLink($account['user_id'], '- INTERVAL 1 MINUTE');
        $browser = $this->browser();

        self::assertStringContainsString('Link expired', $browser->get($this->resetPath($token))['body']);

        $response = $this->submitReset($browser, $token, self::NEW_PASSWORD);

        self::assertNotSame(302, $response['status']);
        $this->assertPasswordIs($account['user_id'], self::PASSWORD);
    }

    /** SEC-06 */
    public function testUsedLinkCannotBeReplayed(): void
    {
        $account = $this->createAccount();
        $token = $this->createResetLink($account['user_id'], '+ INTERVAL 30 MINUTE');
        $browser = $this->browser();

        $first = $this->submitReset($browser, $token, self::NEW_PASSWORD);
        self::assertSame('/pages/login.php?reset=1', $first['location'], 'Fixture: a fresh link should work once');
        $this->assertPasswordIs($account['user_id'], self::NEW_PASSWORD);

        $replay = $this->submitReset($browser, $token, self::OTHER_PASSWORD);

        self::assertNotSame(302, $replay['status']);
        $this->assertPasswordIs($account['user_id'], self::NEW_PASSWORD);
        self::assertStringContainsString('Link expired', $browser->get($this->resetPath($token))['body']);
    }

    /** SEC-06: a guessed or made-up token is rejected. */
    public function testUnknownTokenIsRejected(): void
    {
        $page = $this->browser()->get($this->resetPath(bin2hex(random_bytes(32))));

        self::assertStringContainsString('Link expired', $page['body']);
    }

    /** SEC-06 / account enumeration: both answers must be identical. */
    public function testForgotPasswordDoesNotRevealWhetherAnAccountExists(): void
    {
        $account = $this->createAccount();

        $known = $this->requestReset($account['email']);
        $unknown = $this->requestReset('nobody_' . bin2hex(random_bytes(4)) . '@example.com');

        self::assertSame($known['status'], $unknown['status']);
        self::assertSame($this->withoutTokens($known['body']), $this->withoutTokens($unknown['body']));
    }

    private function createResetLink(int $userId, string $expiresFromNow): string
    {
        $token = bin2hex(random_bytes(32));
        $this->pdo->prepare("
            INSERT INTO password_resets (user_id, token_hash, expires_at)
            VALUES (?, ?, NOW() $expiresFromNow)
        ")->execute([$userId, hash('sha256', $token)]);

        return $token;
    }

    private function resetPath(string $token): string
    {
        return '/pages/reset-password.php?token=' . $token;
    }

    /**
     * Posts the reset form directly, so it also works for links whose page
     * no longer shows the form.
     */
    private function submitReset(HttpClient $browser, string $token, string $password): array
    {
        $csrfPage = $browser->get('/pages/forgot-password.php');

        return $browser->post('/pages/reset-password.php', [
            'csrf_token' => HttpClient::csrfToken($csrfPage['body']),
            'token' => $token,
            'password' => $password,
            'password_confirm' => $password,
        ]);
    }

    private function requestReset(string $email): array
    {
        $browser = $this->browser();
        $page = $browser->get('/pages/forgot-password.php');

        return $browser->post('/pages/forgot-password.php', [
            'csrf_token' => HttpClient::csrfToken($page['body']),
            'email' => $email,
        ]);
    }

    /** Removes per-session CSRF tokens and the submitted email so two pages can be compared. */
    private function withoutTokens(string $html): string
    {
        $html = preg_replace('/[0-9a-f]{64}/', 'TOKEN', $html);

        return preg_replace('/[a-z0-9_]+@example\.com/', 'EMAIL', $html);
    }

    private function assertPasswordIs(int $userId, string $password): void
    {
        self::assertTrue(password_verify($password, $this->userRow($userId)['password_hash']));
    }
}
