<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\HttpClient;
use PHPUnit\Framework\TestCase;
use PDO;

final class ListingAnalyticsTest extends TestCase
{
    private const PASSWORD = 'UC11TestPassword!2026';
    private HttpClient $browser;
    private PDO $pdo;
    private array $createdUserIds = [];
    private array $createdListingIds = [];
    private ?int $createdCategoryId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->browser = HttpClient::fromEnvironment();
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
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->pdo)) {
                // Functional HTTP tests require committed fixtures,
                // so remove only the records created by this test.
                // Activity records can reference test listings, even when
                // those listings have been marked sold.
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
                    // Search and view history now reference authenticated
                    // users. Clear only this test's users before deleting them.
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

    // =========================================
    // TC-11-06: ANALYTICS REQUIRES LOGIN
    // =========================================

    public function testAnalyticsPageRequiresLogin(): void
    {
        $response = $this->browser->get(
            '/pages/listing-analytics.php?id=1'
        );

        $this->assertSame(302, $response['status']);

        $this->assertStringStartsWith(
            '/pages/login.php?redirect=',
            $response['location'] ?? ''
        );
    }



    // =========================================
    // TEST DATA HELPERS
    // =========================================

    private function createListingOwner(): array
    {
        $suffix = bin2hex(random_bytes(6));
        $username = "uc11_$suffix";
        $email = "uc11_$suffix@example.test";

        $this->pdo->prepare('
            INSERT INTO users (
                first_name,
                last_name,
                username,
                email,
                password_hash,
                phone_number,
                birth_date,
                is_email_verified
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            'UC11',
            'Fixture',
            $username,
            $email,
            password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            '555-0000',
            '2000-01-01',
            1,
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        $this->createdUserIds[] = $userId;

        // Required by the listings table's foreign key.
        // This is not a separate buyer/seller account type.
        $this->pdo->prepare('
            INSERT INTO seller_profiles (
                user_id,
                store_name,
                payout_account_reference
            ) VALUES (?, ?, ?)
        ')->execute([
            $userId,
            "UC11 Store $suffix",
            "UC11_$suffix",
        ]);

        return [
            'id' => $userId,
            'email' => $email,
        ];
    }

    private function createCategory(): int
    {
        $name = 'UC11 Category '
            . bin2hex(random_bytes(6));

        $this->pdo->prepare(
            'INSERT INTO categories (category_name) VALUES (?)'
        )->execute([$name]);

        $this->createdCategoryId =
            (int) $this->pdo->lastInsertId();

        return $this->createdCategoryId;
    }

    private function createListing(
        int $sellerId,
        int $categoryId,
        string $name
    ): int {
        $this->pdo->prepare('
            INSERT INTO listings (
                seller_user_id,
                category_id,
                item_name,
                item_description,
                sale_type,
                fulfillment_type,
                price,
                `condition`,
                quantity,
                listing_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $sellerId,
            $categoryId,
            $name,
            'UC-11 temporary test listing',
            'fixed_price',
            'shipping',
            25.00,
            'good',
            1,
            'active',
        ]);

        $listingId = (int) $this->pdo->lastInsertId();
        $this->createdListingIds[] = $listingId;

        return $listingId;
    }

    private function logInAs(string $email): string
    {
        $loginPage = $this->browser->get('/pages/login.php');

        $this->assertSame(200, $loginPage['status']);

        $csrfToken = HttpClient::csrfToken(
            $loginPage['body']
        );

        $response = $this->browser->post('/pages/login.php', [
            'identifier' => $email,
            'password' => self::PASSWORD,
            'csrf_token' => $csrfToken,
            'redirect' => '/pages/my-listings.php',
        ]);

        $this->assertSame(
            302,
            $response['status'],
            $response['body']
        );

        $this->assertSame(
            '/pages/my-listings.php',
            $response['location']
        );

        return $csrfToken;
    }

}