<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Security;

use OmniBuy\Tests\Support\HttpClient;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base for the SOC 2 CC6.1 logical access tests (SEC-01 to SEC-08 in
 * docs/security/CC6.1-Test-Plan.md).
 *
 * Tests send real HTTP requests to the running site, so they see exactly
 * what a browser would. Each test uses its own disposable accounts, which
 * are deleted afterwards; real and seeded users are never touched.
 */
abstract class SecurityTestCase extends TestCase
{
    protected const PASSWORD = 'Sup3rSecret!';
    protected const LOGIN_ERROR = 'Incorrect email/username or password.';

    protected PDO $pdo;

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO(
            sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: 'db',
                (int) (getenv('DB_PORT') ?: 3306),
                getenv('DB_NAME') ?: 'omnibuy'
            ),
            getenv('DB_USER') ?: '',
            getenv('DB_PASSWORD') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    protected function tearDown(): void
    {
        // Password resets are removed with the user (ON DELETE CASCADE).
        foreach ($this->createdUserIds as $userId) {
            $this->pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$userId]);
        }
        $this->createdUserIds = [];

        parent::tearDown();
    }

    /**
     * A new browser with its own cookies, i.e. a separate person.
     */
    protected function browser(): HttpClient
    {
        return HttpClient::fromEnvironment();
    }

    /**
     * Creates a disposable account with password self::PASSWORD. The row is
     * committed so the web server can see it, and deleted in tearDown().
     *
     * @param array<string, mixed> $overrides users columns to set
     * @return array<string, mixed> the users row, without password_hash
     */
    protected function createAccount(array $overrides = []): array
    {
        $suffix = bin2hex(random_bytes(4));
        $row = array_merge([
            'first_name' => 'Sec',
            'last_name' => "Tester$suffix",
            'username' => "sec_$suffix",
            'email' => "sec_$suffix@example.com",
            'password_hash' => password_hash(self::PASSWORD, PASSWORD_DEFAULT),
            'phone_number' => '555-' . random_int(1000, 9999),
            'birth_date' => '2000-01-01',
        ], $overrides);

        $columns = array_keys($row);
        $this->pdo->prepare(sprintf(
            'INSERT INTO users (%s) VALUES (:%s)',
            implode(', ', $columns),
            implode(', :', $columns)
        ))->execute($row);

        $this->trackUser((int) $this->pdo->lastInsertId());

        unset($row['password_hash']);

        return ['user_id' => (int) $this->pdo->lastInsertId()] + $row;
    }

    /**
     * Marks an account made some other way (e.g. through the sign-up form) for deletion.
     */
    protected function trackUser(int $userId): void
    {
        $this->createdUserIds[] = $userId;
    }

    /**
     * Submits the real login form, CSRF token included.
     *
     * @return array{status: int, location: ?string, body: string}
     */
    protected function logIn(HttpClient $browser, string $identifier, string $password = self::PASSWORD): array
    {
        $page = $browser->get('/pages/login.php');

        return $browser->post('/pages/login.php', [
            'csrf_token' => HttpClient::csrfToken($page['body']),
            'redirect' => '/',
            'identifier' => $identifier,
            'password' => $password,
        ]);
    }

    /**
     * A browser that is logged in as $account.
     */
    protected function loggedInAs(array $account): HttpClient
    {
        $browser = $this->browser();
        $response = $this->logIn($browser, $account['email']);
        self::assertSame(302, $response['status'], 'Test fixture: login should succeed');

        return $browser;
    }

    /**
     * The response sent the visitor to the login page instead of serving the page.
     */
    protected function assertRedirectsToLogin(array $response, string $message = ''): void
    {
        self::assertSame(302, $response['status'], $message ?: 'Expected a redirect to the login page');
        self::assertStringStartsWith('/pages/login.php', (string) $response['location'], $message);
    }

    /** @return array<string, mixed> */
    protected function userRow(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE user_id = ?');
        $stmt->execute([$userId]);

        return $stmt->fetch();
    }
}
