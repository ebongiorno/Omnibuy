<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\HttpClient;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Sell flow end to end: a new account fills in both listing steps and
 * publishes. There is one kind of account, so no seller setup is needed.
 */
final class CreateListingFlowTest extends TestCase
{
    private const PASSWORD = 'Sup3rSecret!';
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private HttpClient $browser;
    private PDO $pdo;
    private int $userId;
    private string $photo;

    protected function setUp(): void
    {
        $this->browser = HttpClient::fromEnvironment();
        $this->pdo = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_NAME')),
            getenv('DB_USER'),
            getenv('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        $suffix = bin2hex(random_bytes(4));
        $this->pdo->prepare('
            INSERT INTO users (first_name, last_name, username, email, password_hash, phone_number, birth_date)
            VALUES (\'New\', \'Seller\', ?, ?, ?, \'555-0111\', \'2000-01-01\')
        ')->execute(["sell_$suffix", "sell_$suffix@example.com", password_hash(self::PASSWORD, PASSWORD_DEFAULT)]);
        $this->userId = (int) $this->pdo->lastInsertId();

        $this->photo = tempnam(sys_get_temp_dir(), 'photo') . '.png';
        file_put_contents($this->photo, base64_decode(self::PNG_1X1));
    }

    protected function tearDown(): void
    {
        $images = $this->pdo->prepare('
            SELECT li.image_url FROM listing_images li
            JOIN listings l ON l.listing_id = li.listing_id
            WHERE l.seller_user_id = ?
        ');
        $images->execute([$this->userId]);
        foreach ($images->fetchAll(PDO::FETCH_COLUMN) as $url) {
            @unlink(__DIR__ . '/../../public' . $url);
        }

        foreach ([
            'DELETE li FROM listing_images li JOIN listings l ON l.listing_id = li.listing_id WHERE l.seller_user_id = ?',
            'DELETE FROM listings WHERE seller_user_id = ?',
            'DELETE FROM seller_profiles WHERE user_id = ?',
            'DELETE FROM users WHERE user_id = ?',
        ] as $sql) {
            $this->pdo->prepare($sql)->execute([$this->userId]);
        }
        @unlink($this->photo);
    }

    public function testNewAccountCanPublishAListing(): void
    {
        $this->logIn();

        $step1 = $this->browser->post('/pages/create-listing.php', [
            'title' => 'Desk lamp',
            'price' => '12.50',
            'description' => 'A working desk lamp in good condition.',
        ], ['photos[0]' => $this->photo]);
        $this->assertSame('create-listing-details.php', $step1['location']);

        $category = (string) $this->pdo->query('SELECT category_id FROM categories ORDER BY category_id LIMIT 1')->fetchColumn();
        $step2 = $this->browser->post('/pages/create-listing-details.php', [
            'category' => $category,
            'condition' => 'good',
            'quantity' => '1',
            'fulfillment_type' => 'shipping',
        ]);

        $this->assertSame('my-listings.php?published=1', $step2['location'], $this->alerts($step2['body']));

        $listing = $this->pdo->prepare('SELECT item_name, price, listing_status FROM listings WHERE seller_user_id = ?');
        $listing->execute([$this->userId]);
        $this->assertSame(
            ['item_name' => 'Desk lamp', 'price' => '12.50', 'listing_status' => 'active'],
            $listing->fetch()
        );
    }

    public function testDetailsStepSeesTheLoggedInUser(): void
    {
        // Output before session_start() (e.g. a blank line before <?php) silently drops the session.
        $this->logIn();

        $page = $this->browser->get('/pages/create-listing-details.php');

        $this->assertStringContainsString($this->username(), $page['body']);
    }

    private function logIn(): void
    {
        $page = $this->browser->get('/pages/login.php');
        $response = $this->browser->post('/pages/login.php', [
            'csrf_token' => HttpClient::csrfToken($page['body']),
            'redirect' => '/',
            'identifier' => $this->username(),
            'password' => self::PASSWORD,
        ]);
        $this->assertSame(302, $response['status'], 'Fixture: login should succeed');
    }

    private function username(): string
    {
        $stmt = $this->pdo->prepare('SELECT username FROM users WHERE user_id = ?');
        $stmt->execute([$this->userId]);

        return (string) $stmt->fetchColumn();
    }

    private function alerts(string $html): string
    {
        preg_match_all('#<p role="alert">\s*(.*?)\s*</p>#s', $html, $matches);

        return 'Page errors: ' . implode(' | ', $matches[1]);
    }
}
