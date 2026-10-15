<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\HttpClient;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * UC-03 / UC-04 / UC-05 end to end: real HTTP requests to the password
 * reset, profile, privacy and change-password pages. The reset test reads
 * the actual email from Mailpit.
 */
final class AccountManagementFlowTest extends TestCase
{
    private const PASSWORD = 'Sup3rSecret!';
    private const NEW_PASSWORD = 'Br4ndNewPass';
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private HttpClient $browser;
    private PDO $pdo;

    /** @var list<string> */
    private array $createdUsernames = [];

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->browser = HttpClient::fromEnvironment();
        $this->pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_NAME')),
            getenv('DB_USER'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        if ($this->createdUsernames === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($this->createdUsernames), '?'));
        $users = $this->pdo->prepare("SELECT user_id, profile_image_url FROM users WHERE username IN ($placeholders)");
        $users->execute($this->createdUsernames);

        foreach ($users->fetchAll() as $user) {
            // Uploaded photos are files in the web container, which is where these tests run.
            if (is_string($user['profile_image_url']) && str_starts_with($user['profile_image_url'], '/uploads/avatars/')) {
                @unlink(__DIR__ . '/../../public' . $user['profile_image_url']);
            }
            $this->pdo->prepare('DELETE FROM seller_profiles WHERE user_id = ?')->execute([$user['user_id']]);
            $this->pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$user['user_id']]);
        }
    }

    // ---------- UC-03 reset password ----------

    public function testForgotPasswordEmailsALinkThatResetsThePassword(): void
    {
        $user = $this->createAccountThenLogOut();

        $response = $this->requestReset($user['email']);
        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString("we've sent it a link", $response['body']);

        $email = $this->latestEmailTo($user['email']);
        $this->assertSame('Reset your OmniBuy password', $email['Subject']);
        $this->assertMatchesRegularExpression(
            '#^http://localhost:8080/pages/reset-password\.php\?token=[0-9a-f]{64}\r?$#m',
            $email['Text'],
            'The email should contain a reset link for this site'
        );
        preg_match('#/pages/reset-password\.php\?token=[0-9a-f]{64}#', $email['Text'], $link);

        $resetPage = $this->browser->get($link[0]);
        $this->assertSame(200, $resetPage['status']);
        $this->assertStringContainsString('Choose a new password', $resetPage['body']);

        $saved = $this->submitNewPassword($resetPage['body'], self::NEW_PASSWORD);
        $this->assertSame(302, $saved['status']);
        $this->assertSame('/pages/login.php?reset=1', $saved['location']);
        $this->assertStringContainsString('Your password has been changed', $this->browser->get($saved['location'])['body']);

        $this->assertSame(302, $this->logIn($user['email'], self::NEW_PASSWORD)['status'], 'New password should work');
        $this->browser->post('/pages/logout.php', []);
        $this->assertSame(200, $this->logIn($user['email'], self::PASSWORD)['status'], 'Old password should not');

        $reused = $this->browser->get($link[0]);
        $this->assertStringContainsString('Link expired', $reused['body'], 'A link must only work once');
    }

    public function testForgotPasswordGivesSameAnswerForUnknownEmail(): void
    {
        // Security: the form must not reveal which emails have accounts.
        $address = 'nobody_' . bin2hex(random_bytes(4)) . '@example.com';

        $response = $this->requestReset($address);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString("we've sent it a link", $response['body']);
        $this->assertNull($this->latestEmailTo($address, 3), 'No email should be sent');
    }

    public function testForgotPasswordRejectsInvalidEmail(): void
    {
        $response = $this->requestReset('not-an-email');

        $this->assertStringContainsString('Enter the email address you signed up with.', $response['body']);
    }

    public function testForgotPasswordRequiresCsrfToken(): void
    {
        $response = $this->browser->post('/pages/forgot-password.php', ['email' => 'someone@example.com']);

        $this->assertStringContainsString('Your session expired', $response['body']);
    }

    #[DataProvider('badTokens')]
    public function testResetPageRejectsBadLinks(string $query): void
    {
        $response = $this->browser->get('/pages/reset-password.php' . $query);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Link expired', $response['body']);
        $this->assertStringNotContainsString('name="password"', $response['body']);
    }

    public static function badTokens(): array
    {
        return [
            'no token' => [''],
            'made-up token' => ['?token=' . str_repeat('a', 64)],
            'wrong format' => ['?token=abc'],
            'injection attempt' => ['?token=' . urlencode("' OR '1'='1")],
        ];
    }

    public function testResetPageDoesNotLeakTokenInReferrer(): void
    {
        $headers = get_headers(rtrim(getenv('APP_URL') ?: 'http://localhost', '/') . '/pages/reset-password.php', true);

        $this->assertSame('no-referrer', $headers['Referrer-Policy'] ?? null);
    }

    // ---------- Access control ----------

    #[DataProvider('protectedPages')]
    public function testAccountPagesRequireLogin(string $page): void
    {
        $response = $this->browser->get($page);

        $this->assertSame(302, $response['status']);
        $this->assertStringStartsWith('/pages/login.php?redirect=', $response['location']);
    }

    public static function protectedPages(): array
    {
        return [
            'account' => ['/pages/account.php'],
            'edit profile' => ['/pages/edit-profile.php'],
            'privacy settings' => ['/pages/privacy-settings.php'],
            'change password' => ['/pages/change-password.php'],
        ];
    }

    // ---------- UC-04 edit profile ----------

    public function testEditProfileUpdatesAccountAndHeader(): void
    {
        $this->signUp();
        $newUsername = 'renamed_' . bin2hex(random_bytes(4));
        $this->createdUsernames[] = $newUsername;

        $response = $this->saveProfile(['username' => $newUsername, 'bio' => 'Selling my old camera gear.']);

        $this->assertSame(302, $response['status']);
        $this->assertSame('/pages/account.php?updated=profile', $response['location']);

        $account = $this->browser->get($response['location']);
        $this->assertStringContainsString('Your profile has been updated.', $account['body']);
        $this->assertStringContainsString('Selling my old camera gear.', $account['body']);
        $this->assertStringContainsString('<span>' . $newUsername . '</span>', $account['body'], 'Header should show the new username');
    }

    public function testEditProfileShowsErrorsAndKeepsValues(): void
    {
        $this->signUp();

        $response = $this->saveProfile(['first_name' => '', 'bio' => 'Kept bio']);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('First name is required.', $response['body']);
        $this->assertStringContainsString('Kept bio</textarea>', $response['body']);
    }

    public function testEditProfileEscapesHtml(): void
    {
        // Security: a bio is shown to other users, so it must not inject markup.
        $this->signUp();

        $this->saveProfile(['bio' => '<script>alert(1)</script>']);
        $account = $this->browser->get('/pages/account.php');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $account['body']);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $account['body']);
    }

    public function testEditProfileRequiresCsrfToken(): void
    {
        $this->signUp();
        $form = $this->profileFormFields($this->browser->get('/pages/edit-profile.php')['body']);
        $form['csrf_token'] = 'wrong';

        $response = $this->browser->post('/pages/edit-profile.php', $form);

        $this->assertStringContainsString('Your session expired', $response['body']);
    }

    public function testPhotoUploadIsSavedAndShown(): void
    {
        $this->signUp();

        $response = $this->saveProfile([], ['photo' => $this->tempFile(base64_decode(self::PNG_1X1))]);
        $this->assertSame(302, $response['status']);

        $account = $this->browser->get('/pages/account.php');
        $this->assertMatchesRegularExpression('#src="/uploads/avatars/[0-9a-f]{32}\.png"#', $account['body']);
        preg_match('#/uploads/avatars/[0-9a-f]{32}\.png#', $account['body'], $photoUrl);

        $photo = $this->browser->get($photoUrl[0]);
        $this->assertSame(200, $photo['status'], 'The uploaded photo should be served');
        $this->assertSame(base64_decode(self::PNG_1X1), $photo['body']);
    }

    public function testUploadingScriptAsPhotoIsRejected(): void
    {
        // Security: a PHP file renamed to .png must not be stored on the server.
        $this->signUp();
        $before = glob(__DIR__ . '/../../public/uploads/avatars/*') ?: [];

        $response = $this->saveProfile([], ['photo' => $this->tempFile('<?php echo "pwned"; ?>')]);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Photo must be a JPEG, PNG or WebP image.', $response['body']);
        $this->assertSame($before, glob(__DIR__ . '/../../public/uploads/avatars/*') ?: []);
    }

    // ---------- UC-05 privacy ----------

    public function testHidingLastNameRemovesItFromSellerSearch(): void
    {
        $user = $this->signUp(['last_name' => 'Zq' . bin2hex(random_bytes(3))]);
        $storeName = 'Privacy Test Store ' . bin2hex(random_bytes(4));
        $this->pdo->prepare('
            INSERT INTO seller_profiles (user_id, store_name, payout_account_reference)
            SELECT user_id, ?, ? FROM users WHERE username = ?
        ')->execute([$storeName, 'payout_' . bin2hex(random_bytes(8)), $user['username']]);
        $searchUrl = '/pages/search.php?search_type=seller_profiles&q=' . urlencode($storeName);

        $this->assertStringContainsString($user['last_name'], $this->browser->get($searchUrl)['body']);

        $page = $this->browser->get('/pages/privacy-settings.php');
        $saved = $this->browser->post('/pages/privacy-settings.php', [
            'csrf_token' => HttpClient::csrfToken($page['body']),
            'hide_last_name' => '1',
        ]);
        $this->assertSame('/pages/account.php?updated=privacy', $saved['location']);

        $results = $this->browser->get($searchUrl)['body'];
        $this->assertStringContainsString($storeName, $results);
        $this->assertStringNotContainsString($user['last_name'], $results);

        $byLastName = $this->browser->get('/pages/search.php?search_type=seller_profiles&q=' . urlencode($user['last_name']));
        $this->assertStringNotContainsString($storeName, $byLastName['body'], 'Hidden last name must not be searchable');
    }

    public function testPrivacySettingCanBeTurnedOff(): void
    {
        $this->signUp();
        $page = $this->browser->get('/pages/privacy-settings.php');
        $this->browser->post('/pages/privacy-settings.php', ['csrf_token' => HttpClient::csrfToken($page['body']), 'hide_last_name' => '1']);

        $page = $this->browser->get('/pages/privacy-settings.php');
        $this->assertStringContainsString(' checked', $page['body']);
        $this->browser->post('/pages/privacy-settings.php', ['csrf_token' => HttpClient::csrfToken($page['body'])]);

        $this->assertStringNotContainsString(' checked', $this->browser->get('/pages/privacy-settings.php')['body']);
    }

    // ---------- Change password ----------

    public function testChangePasswordThenLogInWithNewPassword(): void
    {
        $user = $this->signUp();

        $response = $this->changePassword(self::PASSWORD, self::NEW_PASSWORD);
        $this->assertSame('/pages/account.php?updated=password', $response['location']);

        $this->browser->post('/pages/logout.php', []);
        $this->assertSame(302, $this->logIn($user['username'], self::NEW_PASSWORD)['status']);
    }

    public function testChangePasswordWithWrongCurrentPasswordIsRejected(): void
    {
        $this->signUp();

        $response = $this->changePassword('WrongPassw0rd', self::NEW_PASSWORD);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('Your current password is incorrect.', $response['body']);
    }

    // ---------- Helpers ----------

    /**
     * @return array{username: string, email: string, last_name: string}
     */
    private function signUp(array $overrides = []): array
    {
        $suffix = bin2hex(random_bytes(4));
        $this->createdUsernames[] = "acct_$suffix";
        $page = $this->browser->get('/pages/signup.php');

        $fields = array_merge([
            'csrf_token' => HttpClient::csrfToken($page['body']),
            'first_name' => 'Account',
            'last_name' => 'Flow',
            'username' => "acct_$suffix",
            'email' => "acct_$suffix@example.com",
            'phone_number' => '657-555-0123',
            'birth_date' => '2000-01-01',
            'password' => self::PASSWORD,
            'password_confirm' => self::PASSWORD,
        ], $overrides);

        $response = $this->browser->post('/pages/signup.php', $fields);
        $this->assertSame(302, $response['status'], 'Sign-up should succeed');

        return ['username' => $fields['username'], 'email' => $fields['email'], 'last_name' => $fields['last_name']];
    }

    private function createAccountThenLogOut(): array
    {
        $user = $this->signUp();
        $this->browser->post('/pages/logout.php', []);

        return $user;
    }

    private function logIn(string $identifier, string $password): array
    {
        $page = $this->browser->get('/pages/login.php');

        return $this->browser->post('/pages/login.php', [
            'csrf_token' => HttpClient::csrfToken($page['body']),
            'redirect' => '/',
            'identifier' => $identifier,
            'password' => $password,
        ]);
    }

    private function requestReset(string $email): array
    {
        $page = $this->browser->get('/pages/forgot-password.php');

        return $this->browser->post('/pages/forgot-password.php', [
            'csrf_token' => HttpClient::csrfToken($page['body']),
            'email' => $email,
        ]);
    }

    private function submitNewPassword(string $resetPageHtml, string $password): array
    {
        preg_match('/name="token" value="([0-9a-f]{64})"/', $resetPageHtml, $token);

        return $this->browser->post('/pages/reset-password.php', [
            'csrf_token' => HttpClient::csrfToken($resetPageHtml),
            'token' => $token[1],
            'password' => $password,
            'password_confirm' => $password,
        ]);
    }

    private function changePassword(string $current, string $new): array
    {
        $page = $this->browser->get('/pages/change-password.php');

        return $this->browser->post('/pages/change-password.php', [
            'csrf_token' => HttpClient::csrfToken($page['body']),
            'current_password' => $current,
            'password' => $new,
            'password_confirm' => $new,
        ]);
    }

    /**
     * Submits the edit-profile form with its current values plus the given changes.
     */
    private function saveProfile(array $changes, array $files = []): array
    {
        $fields = array_merge($this->profileFormFields($this->browser->get('/pages/edit-profile.php')['body']), $changes);

        return $this->browser->post('/pages/edit-profile.php', $fields, $files);
    }

    private function profileFormFields(string $html): array
    {
        $fields = ['csrf_token' => HttpClient::csrfToken($html)];

        foreach (['first_name', 'last_name', 'username', 'email', 'phone_number'] as $name) {
            preg_match('/name="' . $name . '"[^>]*value="([^"]*)"/s', $html, $match);
            $fields[$name] = html_entity_decode($match[1] ?? '');
        }
        preg_match('#<textarea[^>]*name="bio"[^>]*>(.*?)</textarea>#s', $html, $bio);
        $fields['bio'] = html_entity_decode($bio[1] ?? '');

        return $fields;
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'photo');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * The newest email Mailpit caught for this address, or null if none
     * arrived. Skips the test when Mailpit isn't running.
     *
     * @return array{Subject: string, Text: string}|null
     */
    private function latestEmailTo(string $address, int $attempts = 20): ?array
    {
        $mailpit = rtrim(getenv('MAILPIT_URL') ?: 'http://mailpit:8025', '/');

        for ($i = 0; $i < $attempts; $i++) {
            $search = $this->getJson($mailpit . '/api/v1/search?query=' . urlencode('to:"' . $address . '"'));
            if ($search === null) {
                $this->markTestSkipped("Mailpit is not reachable at $mailpit (run docker compose up -d)");
            }

            if (!empty($search['messages'])) {
                return $this->getJson($mailpit . '/api/v1/message/' . $search['messages'][0]['ID']);
            }

            usleep(250_000);
        }

        return null;
    }

    private function getJson(string $url): ?array
    {
        $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 5]]));

        return $body === false ? null : json_decode($body, true);
    }
}
