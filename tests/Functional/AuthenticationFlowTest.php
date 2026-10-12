<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\HttpClient;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * UC-01 / UC-02 end to end: real HTTP requests to the sign-up, login,
 * logout and account pages, with cookies kept between requests.
 */
final class AuthenticationFlowTest extends TestCase
{
    private const PASSWORD = 'Sup3rSecret!';

    private HttpClient $browser;

    /** @var list<string> usernames created by the test, deleted in tearDown */
    private array $createdUsernames = [];

    protected function setUp(): void
    {
        $this->browser = HttpClient::fromEnvironment();
    }

    protected function tearDown(): void
    {
        if ($this->createdUsernames === []) {
            return;
        }

        $pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_NAME')),
            getenv('DB_USER'),
            getenv('DB_PASSWORD')
        );
        $placeholders = implode(',', array_fill(0, count($this->createdUsernames), '?'));
        $pdo->prepare("DELETE FROM users WHERE username IN ($placeholders)")->execute($this->createdUsernames);
    }

    public function testSignupLogsUserInAndShowsThemInHeader(): void
    {
        $user = $this->signUp();

        $home = $this->browser->get('/');
        $this->assertSame(200, $home['status']);
        $this->assertStringContainsString(htmlspecialchars($user['username']), $home['body']);
        $this->assertStringNotContainsString('href="/pages/login.php"', $home['body']);
    }

    public function testLogoutEndsTheSession(): void
    {
        $this->signUp();

        $logout = $this->browser->post('/pages/logout.php', []);
        $this->assertSame(302, $logout['status']);
        $this->assertSame('/pages/login.php?logged_out=1', $logout['location']);

        $loginPage = $this->browser->get($logout['location']);
        $this->assertStringContainsString('You have been logged out.', $loginPage['body']);

        $account = $this->browser->get('/pages/account.php');
        $this->assertSame(302, $account['status'], 'Protected page should be blocked after logout');
    }

    public function testLogoutRequiresPost(): void
    {
        $this->signUp();

        $this->browser->get('/pages/logout.php');

        $this->assertSame(200, $this->browser->get('/pages/account.php')['status'], 'GET must not log the user out');
    }

    public function testLoginWithCorrectPasswordReachesProtectedPage(): void
    {
        $user = $this->createAccountThenLogOut();

        $response = $this->logIn($user['email'], self::PASSWORD);
        $this->assertSame(302, $response['status']);

        $account = $this->browser->get('/pages/account.php');
        $this->assertSame(200, $account['status']);
        $this->assertStringContainsString(htmlspecialchars($user['email']), $account['body']);
    }

    public function testLoginWithWrongPasswordIsRejected(): void
    {
        $user = $this->createAccountThenLogOut();

        $response = $this->logIn($user['email'], 'WrongPassw0rd');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Incorrect email/username or password.', $response['body']);
        $this->assertSame(302, $this->browser->get('/pages/account.php')['status']);
    }

    public function testLoginWithUnknownAccountShowsSameMessage(): void
    {
        $response = $this->logIn('nobody_' . bin2hex(random_bytes(4)) . '@example.com', 'WrongPassw0rd');

        $this->assertStringContainsString('Incorrect email/username or password.', $response['body']);
    }

    public function testProtectedPageRedirectsToLoginAndBackAfterwards(): void
    {
        $user = $this->createAccountThenLogOut();

        $account = $this->browser->get('/pages/account.php');
        $this->assertSame(302, $account['status']);
        $this->assertSame('/pages/login.php?redirect=%2Fpages%2Faccount.php', $account['location']);

        $loginPage = $this->browser->get($account['location']);
        $this->assertStringContainsString('Please log in to continue.', $loginPage['body']);

        $response = $this->logIn($user['username'], self::PASSWORD, $loginPage['body']);
        $this->assertSame('/pages/account.php', $response['location']);
    }

    public function testLoginIgnoresRedirectToAnotherSite(): void
    {
        // Security: open-redirect protection.
        $user = $this->createAccountThenLogOut();

        $loginPage = $this->browser->get('/pages/login.php?redirect=' . urlencode('https://evil.example/'));
        $response = $this->logIn($user['email'], self::PASSWORD, $loginPage['body']);

        $this->assertSame('/', $response['location']);
    }

    public function testSessionIdChangesOnLogin(): void
    {
        // Security: prevents session fixation.
        $user = $this->createAccountThenLogOut();

        $this->browser->get('/pages/login.php');
        $before = $this->browser->cookie('PHPSESSID');

        $this->logIn($user['email'], self::PASSWORD);

        $this->assertNotNull($before);
        $this->assertNotSame($before, $this->browser->cookie('PHPSESSID'));
    }

    public function testSessionCookieIsHttpOnlyAndSameSite(): void
    {
        $this->browser->get('/pages/login.php');

        $cookie = $this->browser->lastSetCookies['PHPSESSID'] ?? '';
        $this->assertStringContainsStringIgnoringCase('HttpOnly', $cookie);
        $this->assertStringContainsStringIgnoringCase('SameSite=Lax', $cookie);
    }

    public function testSignupWithoutCsrfTokenIsRejected(): void
    {
        $this->browser->get('/pages/signup.php');
        $fields = $this->signupFields();
        unset($fields['csrf_token']);

        $response = $this->browser->post('/pages/signup.php', $fields);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Your session expired', $response['body']);
        $this->assertSame(302, $this->browser->get('/pages/account.php')['status']);
    }

    public function testSignupShowsErrorsAndKeepsEnteredValues(): void
    {
        $page = $this->browser->get('/pages/signup.php');
        $fields = $this->signupFields(HttpClient::csrfToken($page['body']));
        $fields['email'] = 'not-an-email';
        $fields['password_confirm'] = 'something else';

        $response = $this->browser->post('/pages/signup.php', $fields);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Enter a valid email address.', $response['body']);
        $this->assertStringContainsString('Passwords do not match.', $response['body']);
        $this->assertStringContainsString('value="' . $fields['username'] . '"', $response['body']);
        $this->assertStringNotContainsString(self::PASSWORD, $response['body'], 'Passwords must not be echoed back');
    }

    public function testSignupWithExistingEmailIsRejected(): void
    {
        $user = $this->createAccountThenLogOut();

        $page = $this->browser->get('/pages/signup.php');
        $fields = $this->signupFields(HttpClient::csrfToken($page['body']));
        $fields['email'] = $user['email'];

        $response = $this->browser->post('/pages/signup.php', $fields);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('An account with this email already exists.', $response['body']);
    }

    public function testSignupEscapesHtmlInEnteredValues(): void
    {
        // Security: values echoed back into the form must not inject markup.
        $page = $this->browser->get('/pages/signup.php');
        $fields = $this->signupFields(HttpClient::csrfToken($page['body']));
        $fields['first_name'] = '"><script>alert(1)</script>';
        $fields['email'] = 'bad';

        $response = $this->browser->post('/pages/signup.php', $fields);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response['body']);
    }

    /**
     * @return array{username: string, email: string}
     */
    private function signUp(): array
    {
        $page = $this->browser->get('/pages/signup.php');
        $this->assertSame(200, $page['status']);

        $fields = $this->signupFields(HttpClient::csrfToken($page['body']));
        $response = $this->browser->post('/pages/signup.php', $fields);

        preg_match_all('/class="auth-(?:field__error|alert[^"]*)"[^>]*>([^<]+)</', $response['body'], $messages);
        $this->assertSame(302, $response['status'], 'Sign-up should redirect. Page said: ' . implode(' | ', $messages[1]));
        $this->assertSame('/?welcome=1', $response['location']);

        return ['username' => $fields['username'], 'email' => $fields['email']];
    }

    private function createAccountThenLogOut(): array
    {
        $user = $this->signUp();
        $this->browser->post('/pages/logout.php', []);

        return $user;
    }

    private function logIn(string $identifier, string $password, ?string $loginPageHtml = null): array
    {
        $loginPageHtml ??= $this->browser->get('/pages/login.php')['body'];
        preg_match('/name="redirect" value="([^"]*)"/', $loginPageHtml, $redirect);

        return $this->browser->post('/pages/login.php', [
            'csrf_token' => HttpClient::csrfToken($loginPageHtml),
            'redirect' => html_entity_decode($redirect[1] ?? '/'),
            'identifier' => $identifier,
            'password' => $password,
        ]);
    }

    private function signupFields(?string $csrfToken = null): array
    {
        $suffix = bin2hex(random_bytes(4));
        $this->createdUsernames[] = "flow_$suffix";

        return [
            'csrf_token' => $csrfToken,
            'first_name' => 'Flow',
            'last_name' => 'Test',
            'username' => "flow_$suffix",
            'email' => "flow_$suffix@example.com",
            'phone_number' => '657-555-0123',
            'birth_date' => '2000-01-01',
            'password' => self::PASSWORD,
            'password_confirm' => self::PASSWORD,
        ];
    }
}
