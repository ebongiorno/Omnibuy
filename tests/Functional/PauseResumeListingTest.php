<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\HttpClient;
use PDO;
use PHPUnit\Framework\TestCase;

final class PauseResumeListingTest extends TestCase
{
    private const PASSWORD = 'Sup3rSecret!';

    private PDO $pdo;
    private HttpClient $browser;

    private array $createdUserIds = [];
    private array $createdListingIds = [];
    private ?int $createdCategoryId = null;

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
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $this->browser = HttpClient::fromEnvironment();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->pdo)) {
                foreach (array_reverse($this->createdListingIds) as $id) {
                    $this->pdo->prepare(
                        'DELETE FROM listing_views WHERE listing_id = ?'
                    )->execute([$id]);

                    $this->pdo->prepare(
                        'DELETE FROM listings WHERE listing_id = ?'
                    )->execute([$id]);
                }

                if ($this->createdCategoryId !== null) {
                    $this->pdo->prepare(
                        'DELETE FROM categories WHERE category_id = ?'
                    )->execute([$this->createdCategoryId]);
                }

                foreach (array_reverse($this->createdUserIds) as $id) {
                    $this->pdo->prepare(
                        'DELETE FROM search_history WHERE user_id = ?'
                    )->execute([$id]);

                    $this->pdo->prepare(
                        'DELETE FROM listing_views WHERE user_id = ?'
                    )->execute([$id]);

                    $this->pdo->prepare(
                        'DELETE FROM seller_profiles WHERE user_id = ?'
                    )->execute([$id]);

                    $this->pdo->prepare(
                        'DELETE FROM users WHERE user_id = ?'
                    )->execute([$id]);
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    // TC-12-01: SELLER CAN PAUSE AN ACTIVE LISTING

    public function testSellerCanPauseOwnListing(): void
    {
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        $name = 'UC12 Pause ' . bin2hex(random_bytes(5));
        $id = $this->createListing($seller['id'], $categoryId, $name);

        $this->assertSame('active', $this->listingStatus($id));

        $token = $this->logInAs($seller['email']);

        $response = $this->browser->post('/pages/my-listings.php', [
            'action' => 'pause_listing',
            'listing_id' => (string) $id,
            'csrf_token' => $token,
        ]);

        $this->assertSame(303, $response['status']);
        $this->assertSame(
            '/pages/my-listings.php?paused=1',
            $response['location']
        );
        $this->assertSame('hidden', $this->listingStatus($id));

        $page = $this->browser->get('/pages/my-listings.php');

        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString($name, $page['body']);
        $this->assertStringContainsString('Resume Listing', $page['body']);
    }

    // TC-12-02: PAUSED LISTING IS HIDDEN FROM BUYERS

    public function testPausedListingIsHiddenFromMarketplace(): void
    {
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        $name = 'UC12Hidden' . bin2hex(random_bytes(6));
        $id = $this->createListing($seller['id'], $categoryId, $name);

        $token = $this->logInAs($seller['email']);

        $response = $this->browser->post('/pages/my-listings.php', [
            'action' => 'pause_listing',
            'listing_id' => (string) $id,
            'csrf_token' => $token,
        ]);

        $this->assertSame(303, $response['status']);

        $search = $this->browser->get(
            '/pages/search.php?' . http_build_query([
                'q' => $name,
                'search_type' => 'items',
            ])
        );

        $this->assertSame(200, $search['status']);
        $this->assertStringNotContainsString(
            'href="/pages/listing.php?id=' . $id . '"',
            $search['body']
        );

        $this->assertMatchesRegularExpression(
            '/class="search-results__count"[^>]*>\s*0\s+results?\b/',
            $search['body']
        );

        $detail = $this->browser->get(
            '/pages/listing.php?id=' . $id
        );

        $this->assertStringNotContainsString(
            $name,
            $detail['body']
        );
        $this->assertStringContainsString(
            'Listing unavailable',
            $detail['body']
        );
    }

    // TC-12-03: SELLER CAN RESUME A PAUSED LISTING

    public function testSellerCanResumePausedListing(): void
    {
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        $name = 'UC12 Resume ' . bin2hex(random_bytes(5));
        $id = $this->createListing($seller['id'], $categoryId, $name);

        $token = $this->logInAs($seller['email']);

        $pause = $this->browser->post('/pages/my-listings.php', [
            'action' => 'pause_listing',
            'listing_id' => (string) $id,
            'csrf_token' => $token,
        ]);

        $this->assertSame(303, $pause['status']);
        $this->assertSame('hidden', $this->listingStatus($id));

        $resume = $this->browser->post('/pages/my-listings.php', [
            'action' => 'resume_listing',
            'listing_id' => (string) $id,
            'csrf_token' => $token,
        ]);

        $this->assertSame(303, $resume['status']);
        $this->assertSame(
            '/pages/my-listings.php?resumed=1',
            $resume['location']
        );
        $this->assertSame('active', $this->listingStatus($id));

        $page = $this->browser->get('/pages/my-listings.php');

        $this->assertStringContainsString($name, $page['body']);
        $this->assertStringContainsString('Pause Listing', $page['body']);
    }

    // TC-12-04: OTHER SELLERS CANNOT CHANGE LISTING STATUS

    public function testOtherSellerCannotPauseOrResumeListing(): void
    {
        $owner = $this->createSeller();
        $otherSeller = $this->createSeller();
        $categoryId = $this->createCategory();

        $id = $this->createListing(
            $owner['id'],
            $categoryId,
            'UC12 Protected ' . bin2hex(random_bytes(5))
        );

        $token = $this->logInAs($otherSeller['email']);

        $pause = $this->browser->post('/pages/my-listings.php', [
            'action' => 'pause_listing',
            'listing_id' => (string) $id,
            'csrf_token' => $token,
        ]);

        $this->assertSame(404, $pause['status']);
        $this->assertSame('active', $this->listingStatus($id));

        // Set the fixture to hidden so unauthorized resume is tested.
        $this->pdo->prepare(
            "UPDATE listings SET listing_status = 'hidden'
             WHERE listing_id = ?"
        )->execute([$id]);

        $resume = $this->browser->post('/pages/my-listings.php', [
            'action' => 'resume_listing',
            'listing_id' => (string) $id,
            'csrf_token' => $token,
        ]);

        $this->assertSame(404, $resume['status']);
        $this->assertSame('hidden', $this->listingStatus($id));
    }

    // TEST DATA HELPERS

    private function createSeller(): array
    {
        $suffix = bin2hex(random_bytes(6));
        $email = "uc12_$suffix@example.test";

        $this->pdo->prepare('
            INSERT INTO users (
                first_name, last_name, username, email,
                password_hash, phone_number, birth_date,
                is_email_verified
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            'UC12',
            'Fixture',
            "uc12_$suffix",
            $email,
            password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            '555-0000',
            '2000-01-01',
            1,
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        $this->createdUserIds[] = $userId;

        $this->pdo->prepare('
            INSERT INTO seller_profiles (
                user_id, store_name, payout_account_reference
            ) VALUES (?, ?, ?)
        ')->execute([
            $userId,
            "UC12 Store $suffix",
            "UC12_$suffix",
        ]);

        return ['id' => $userId, 'email' => $email];
    }

    private function createCategory(): int
    {
        $name = 'UC12 Category ' . bin2hex(random_bytes(6));

        $this->pdo->prepare(
            'INSERT INTO categories (category_name) VALUES (?)'
        )->execute([$name]);

        $this->createdCategoryId = (int) $this->pdo->lastInsertId();

        return $this->createdCategoryId;
    }

    private function createListing(
        int $sellerId,
        int $categoryId,
        string $name
    ): int {
        $this->pdo->prepare('
            INSERT INTO listings (
                seller_user_id, category_id, item_name,
                item_description, sale_type, fulfillment_type,
                price, `condition`, quantity, listing_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $sellerId,
            $categoryId,
            $name,
            'UC-12 temporary test listing',
            'fixed_price',
            'shipping',
            25.00,
            'good',
            1,
            'active',
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->createdListingIds[] = $id;

        return $id;
    }

    private function logInAs(string $email): string
    {
        $loginPage = $this->browser->get('/pages/login.php');
        $this->assertSame(200, $loginPage['status']);

        $token = HttpClient::csrfToken($loginPage['body']);

        $response = $this->browser->post('/pages/login.php', [
            'identifier' => $email,
            'password' => self::PASSWORD,
            'csrf_token' => $token,
            'redirect' => '/pages/my-listings.php',
        ]);

        $this->assertSame(302, $response['status']);

        return $token;
    }

    private function listingStatus(int $listingId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT listing_status FROM listings WHERE listing_id = ?'
        );

        $stmt->execute([$listingId]);

        return (string) $stmt->fetchColumn();
    }
}
