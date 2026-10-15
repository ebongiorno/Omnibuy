<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../../includes/auth.php';

/**
 * UC-01 registerUser() and UC-02 attemptLogin() against the real database.
 */
final class AuthenticationTest extends DatabaseTestCase
{
    public function testRegisterCreatesAccountWithHashedPassword(): void
    {
        $input = $this->registrationInput();

        $result = registerUser($this->pdo, $input);

        $this->assertSame([], $result['errors']);
        $this->assertIsInt($result['user_id']);

        $row = $this->fetchUser($result['user_id']);
        $this->assertSame($input['username'], $row['username']);
        $this->assertNotSame($input['password'], $row['password_hash']);
        $this->assertTrue(password_verify($input['password'], $row['password_hash']));
    }

    public function testRegisterStoresEmailInLowercase(): void
    {
        $input = $this->registrationInput();
        $input['email'] = strtoupper($input['email']);

        $result = registerUser($this->pdo, normalizeRegistrationInput($input));

        $this->assertSame(strtolower($input['email']), $this->fetchUser($result['user_id'])['email']);
    }

    public function testRegisterRejectsInvalidInputWithoutCreatingAccount(): void
    {
        $input = $this->registrationInput();
        $input['password_confirm'] = 'different1';

        $result = registerUser($this->pdo, $input);

        $this->assertNull($result['user_id']);
        $this->assertArrayHasKey('password_confirm', $result['errors']);
        $this->assertSame(0, $this->countUsers($input['username']));
    }

    public function testRegisterRejectsDuplicateEmailIgnoringCase(): void
    {
        $first = $this->registrationInput();
        registerUser($this->pdo, $first);

        $second = $this->registrationInput();
        $second['email'] = strtoupper($first['email']);
        $result = registerUser($this->pdo, normalizeRegistrationInput($second));

        $this->assertNull($result['user_id']);
        $this->assertSame('An account with this email already exists.', $result['errors']['email']);
    }

    public function testRegisterRejectsDuplicateUsername(): void
    {
        $first = $this->registrationInput();
        registerUser($this->pdo, $first);

        $second = $this->registrationInput();
        $second['username'] = $first['username'];
        $result = registerUser($this->pdo, $second);

        $this->assertNull($result['user_id']);
        $this->assertSame('This username is taken.', $result['errors']['username']);
    }

    public function testLoginWithEmail(): void
    {
        $input = $this->registeredUser();

        $result = attemptLogin($this->pdo, $input['email'], $input['password']);

        $this->assertNull($result['error']);
        $this->assertSame($input['username'], $result['user']['username']);
    }

    public function testLoginWithUsername(): void
    {
        $input = $this->registeredUser();

        $result = attemptLogin($this->pdo, $input['username'], $input['password']);

        $this->assertNull($result['error']);
        $this->assertSame($input['email'], $result['user']['email']);
    }

    public function testLoginEmailIsCaseInsensitiveAndTrimmed(): void
    {
        $input = $this->registeredUser();

        $result = attemptLogin($this->pdo, '  ' . strtoupper($input['email']) . ' ', $input['password']);

        $this->assertNull($result['error']);
    }

    public function testLoginNeverReturnsThePasswordHash(): void
    {
        $input = $this->registeredUser();

        $result = attemptLogin($this->pdo, $input['email'], $input['password']);

        $this->assertArrayNotHasKey('password_hash', $result['user']);
    }

    public function testWrongPasswordAndUnknownAccountGiveTheSameError(): void
    {
        // Security: the message must not reveal which accounts exist.
        $input = $this->registeredUser();

        $wrongPassword = attemptLogin($this->pdo, $input['email'], 'WrongPassw0rd');
        $unknownAccount = attemptLogin($this->pdo, 'nobody_' . $this->uniqueSuffix() . '@example.com', 'WrongPassw0rd');

        $this->assertNull($wrongPassword['user']);
        $this->assertNull($unknownAccount['user']);
        $this->assertSame($wrongPassword['error'], $unknownAccount['error']);
    }

    public function testPasswordIsCaseSensitive(): void
    {
        $input = $this->registeredUser();

        $result = attemptLogin($this->pdo, $input['email'], strtoupper($input['password']));

        $this->assertNull($result['user']);
    }

    public function testEmptyCredentialsAreRejected(): void
    {
        $this->assertNull(attemptLogin($this->pdo, '', '')['user']);
        $this->assertNull(attemptLogin($this->pdo, 'someone', '')['user']);
    }

    public function testSuspendedAccountCannotLogIn(): void
    {
        $input = $this->registeredUser();
        $this->pdo->prepare('UPDATE users SET account_status = "suspended" WHERE username = :u')
            ->execute(['u' => $input['username']]);

        $result = attemptLogin($this->pdo, $input['email'], $input['password']);

        $this->assertNull($result['user']);
        $this->assertStringContainsString('suspended', $result['error']);
    }

    public function testSqlInjectionInLoginIsTreatedAsText(): void
    {
        $this->registeredUser();

        $result = attemptLogin($this->pdo, "' OR '1'='1", "' OR '1'='1");

        $this->assertNull($result['user']);
    }

    private function registrationInput(): array
    {
        $suffix = $this->uniqueSuffix();

        return normalizeRegistrationInput([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => "auth_$suffix",
            'email' => "auth_$suffix@example.com",
            'phone_number' => '657-555-0123',
            'birth_date' => '2000-01-01',
            'password' => 'Sup3rSecret!',
            'password_confirm' => 'Sup3rSecret!',
        ]);
    }

    private function registeredUser(): array
    {
        $input = $this->registrationInput();
        $result = registerUser($this->pdo, $input);
        $this->assertSame([], $result['errors']);

        return $input;
    }

    private function fetchUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);

        return $stmt->fetch();
    }

    private function countUsers(string $username): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
        $stmt->execute(['u' => $username]);

        return (int) $stmt->fetchColumn();
    }
}
