<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\HttpClient;
use PDO;
use PHPUnit\Framework\TestCase;

final class RenewListingTest extends TestCase
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

        // Connect to the test database.
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

        // Create an HTTP client to simulate browser requests.
        $this->browser = HttpClient::fromEnvironment();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->pdo)) {

                // Delete temporary listings first.
                foreach (array_reverse($this->createdListingIds) as $id) {
                    $this->pdo->prepare(
                        'DELETE FROM listings WHERE listing_id = ?'
                    )->execute([$id]);
                }

                // Delete the temporary category.
                if ($this->createdCategoryId !== null) {
                    $this->pdo->prepare(
                        'DELETE FROM categories WHERE category_id = ?'
                    )->execute([$this->createdCategoryId]);
                }

                // Delete temporary seller profiles and users.
                foreach (array_reverse($this->createdUserIds) as $id) {
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
    // TEST DATA HELPER: CREATE SELLER
    // =========================================

    private function createSeller(): array
    {
        // Generate a unique identifier for the test account.
        $suffix = bin2hex(random_bytes(6));

        $username = "uc15_$suffix";
        $email = "uc15_$suffix@example.test";

        // Create a temporary user account.
        $stmt = $this->pdo->prepare(
            'INSERT INTO users
                (first_name, last_name, username, email,
                 password_hash, phone_number, birth_date,
                 is_email_verified)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            'UC15',
            'Fixture',
            $username,
            $email,
            password_hash(self::PASSWORD, PASSWORD_BCRYPT),
            '555-0000',
            '2000-01-01',
            1
        ]);

        // Store the user ID for cleanup.
        $userId = (int) $this->pdo->lastInsertId();

        $this->createdUserIds[] = $userId;

        // Create the seller profile associated with this user.
        $stmt = $this->pdo->prepare(
            'INSERT INTO seller_profiles
                (user_id, store_name, payout_account_reference)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            $userId,
            "UC15 Store $suffix",
            "UC15_$suffix"
        ]);

        // Return the information needed by our tests.
        return [
            'id' => $userId,
            'email' => $email
        ];
    }

    // =========================================
    // TEST DATA HELPER: CREATE CATEGORY
    // =========================================

    private function createCategory(): int
    {
        // Generate a unique category name.
        $name = 'UC15 Category ' . bin2hex(random_bytes(6));

        // Insert the temporary category into the database.
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (category_name)
             VALUES (?)'
        );

        $stmt->execute([$name]);

        // Retrieve the ID of the newly created category.
        $categoryId = (int) $this->pdo->lastInsertId();

        // Save the ID so tearDown() can delete it later.
        $this->createdCategoryId = $categoryId;

        // Return the category ID for use in our tests.
        return $categoryId;
    }

    // =========================================
    // TEST DATA HELPER: CREATE LISTING
    // =========================================

    private function createListing(
        int $sellerId,
        int $categoryId,
        string $name
    ): int {
        // Create a temporary active listing.
        $stmt = $this->pdo->prepare(
            "INSERT INTO listings
                (seller_user_id,
                 category_id,
                 item_name,
                 item_description,
                 sale_type,
                 fulfillment_type,
                 price,
                 `condition`,
                 quantity,
                 listing_status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $sellerId,
            $categoryId,
            $name,
            'UC-15 temporary test listing',
            'fixed_price',
            'shipping',
            25.00,
            'good',
            1,
            'active'
        ]);

        // Retrieve the ID of the newly created listing.
        $listingId = (int) $this->pdo->lastInsertId();

        // Save the ID so tearDown() can delete it later.
        $this->createdListingIds[] = $listingId;

        // Return the listing ID for use in our tests.
        return $listingId;
    }

    // =========================================
    // TEST HELPER: LOG IN AS SELLER
    // =========================================

    private function logInAs(string $email): string
    {
        // Visit the login page.
        $loginPage = $this->browser->get('/pages/login.php');

        $this->assertSame(200, $loginPage['status']);

        // Retrieve the CSRF token from the login form.
        $csrfToken = HttpClient::csrfToken(
            $loginPage['body']
        );

        // Submit the login request.
        $response = $this->browser->post(
            '/pages/login.php',
            [
                'identifier' => $email,
                'password' => self::PASSWORD,
                'csrf_token' => $csrfToken,
                'redirect' => '/pages/my-listings.php'
            ]
        );

        // Verify successful login redirects to My Listings.
        $this->assertSame(
            302,
            $response['status'],
            $response['body']
        );

        $this->assertSame(
            '/pages/my-listings.php',
            $response['location']
        );

        // Return the token for authenticated POST requests.
        return $csrfToken;
    }

    // =========================================
    // TEST HELPER: RETRIEVE LISTING RECORD
    // =========================================

    private function listingRecord(int $listingId): array
    {
        // Retrieve the listing's information from the database.
        $stmt = $this->pdo->prepare(
            'SELECT
                listing_id,
                seller_user_id,
                item_name,
                listing_status,
                created_at,
                renewed_at
             FROM listings
             WHERE listing_id = ?'
        );

        $stmt->execute([$listingId]);

        // Retrieve the matching database row.
        $listing = $stmt->fetch();

        // Verify that the listing exists.
        $this->assertIsArray(
            $listing,
            'Expected listing record was not found.'
        );

        // Return the listing information.
        return $listing;
    }
}
