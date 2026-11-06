<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Security;

use OmniBuy\Tests\Support\HttpClient;

/**
 * CC6.1 SEC-01 / SEC-02: passwords are stored as one-way hashes and only
 * the right password, sent from the real login form, starts a session.
 */
final class AuthenticationTest extends SecurityTestCase
{
    /** SEC-01 */
    public function testRegistrationStoresOnlyAOneWayPasswordHash(): void
    {
        $browser = $this->browser();
        $suffix = bin2hex(random_bytes(4));
        $page = $browser->get('/pages/signup.php');

        $response = $browser->post('/pages/signup.php', [
            'csrf_token' => HttpClient::csrfToken($page['body']),
            'first_name' => 'Sec',
            'last_name' => 'Signup',
            'username' => "sec_signup_$suffix",
            'email' => "sec_signup_$suffix@example.com",
            'phone_number' => '555-0199',
            'birth_date' => '2000-01-01',
            'password' => self::PASSWORD,
            'password_confirm' => self::PASSWORD,
        ]);

        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute(["sec_signup_$suffix"]);
        $row = $stmt->fetch();
        self::assertIsArray($row, 'Sign-up should create the account');
        $this->trackUser((int) $row['user_id']);
        self::assertSame(302, $response['status']);

        self::assertNotSame(self::PASSWORD, $row['password_hash']);
        self::assertContains(password_get_info($row['password_hash'])['algoName'], ['bcrypt', 'argon2i', 'argon2id']);
        self::assertTrue(password_verify(self::PASSWORD, $row['password_hash']));
        foreach ($row as $column => $value) {
            self::assertStringNotContainsString(self::PASSWORD, (string) $value, "Plain-text password found in users.$column");
        }
    }

    /** SEC-02 positive control: without it, the denials below could pass on a broken login. */
    public function testCorrectPasswordGrantsAccess(): void
    {
        $account = $this->createAccount();
        $browser = $this->browser();

        $response = $this->logIn($browser, $account['email']);

        self::assertSame(302, $response['status']);
        self::assertSame(200, $browser->get('/pages/account.php')['status']);
    }

    /** SEC-02 */
    public function testWrongPasswordDoesNotStartASession(): void
    {
        $account = $this->createAccount();
        $browser = $this->browser();

        $response = $this->logIn($browser, $account['email'], 'Wr0ngPassword!');

        self::assertSame(200, $response['status']);
        self::assertStringContainsString(self::LOGIN_ERROR, $response['body']);
        $this->assertRedirectsToLogin($browser->get('/pages/account.php'));
    }

    /** SEC-02: the error must not reveal whether the account exists. */
    public function testUnknownAccountGetsTheSameErrorAsWrongPassword(): void
    {
        $response = $this->logIn($this->browser(), 'nobody_' . bin2hex(random_bytes(4)) . '@example.com');

        self::assertSame(200, $response['status']);
        self::assertStringContainsString(self::LOGIN_ERROR, $response['body']);
    }

    /** SEC-02 */
    public function testPasswordIsCaseSensitive(): void
    {
        $account = $this->createAccount();

        $response = $this->logIn($this->browser(), $account['email'], strtoupper(self::PASSWORD));

        self::assertStringContainsString(self::LOGIN_ERROR, $response['body']);
    }

    /** SEC-02: a login posted from another site (no CSRF token) is refused. */
    public function testLoginWithoutCsrfTokenIsRejected(): void
    {
        $account = $this->createAccount();
        $browser = $this->browser();

        $response = $browser->post('/pages/login.php', [
            'identifier' => $account['email'],
            'password' => self::PASSWORD,
        ]);

        self::assertNotSame(302, $response['status']);
        self::assertStringContainsString('Your session expired', $response['body']);
        $this->assertRedirectsToLogin($browser->get('/pages/account.php'));
    }

    /** SEC-02 */
    public function testSuspendedAccountCannotLogIn(): void
    {
        $account = $this->createAccount(['account_status' => 'suspended']);
        $browser = $this->browser();

        $response = $this->logIn($browser, $account['email']);

        self::assertSame(200, $response['status']);
        $this->assertRedirectsToLogin($browser->get('/pages/account.php'));
    }
}
