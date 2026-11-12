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
    // TC-15-01: RENEW AN ELIGIBLE LISTING
    // =========================================

    public function testSellerCanRenewOwnActiveListing(): void
    {
        // Create a temporary seller, category, and listing.
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        $listingName = 'UC15 Renewal Test '
            . bin2hex(random_bytes(6));

        $listingId = $this->createListing(
            $seller['id'],
            $categoryId,
            $listingName
        );

        // -----------------------------------------
        // 1. Verify the original listing information.
        // -----------------------------------------

        $before = $this->listingRecord($listingId);

        $this->assertSame(
            'active',
            $before['listing_status']
        );

        $this->assertNull(
            $before['renewed_at']
        );

        $this->assertSame(
            $seller['id'],
            (int) $before['seller_user_id']
        );

        // -----------------------------------------
        // 2. Authenticate as the listing owner.
        // -----------------------------------------

        $token = $this->logInAs($seller['email']);

        // -----------------------------------------
        // 3. Confirm the Renew Listing button appears.
        // -----------------------------------------

        $page = $this->browser->get(
            '/pages/my-listings.php'
        );

        $this->assertSame(
            200,
            $page['status']
        );

        $this->assertStringContainsString(
            $listingName,
            $page['body']
        );

        $this->assertStringContainsString(
            'Renew Listing',
            $page['body']
        );

        // -----------------------------------------
        // 4. Submit a valid renewal request.
        // -----------------------------------------

        $response = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'renew_listing',
                'listing_id' => (string) $listingId,
                'csrf_token' => $token,
            ]
        );

        // Verify successful renewal redirects the seller.
        $this->assertSame(
            303,
            $response['status'],
            $response['body']
        );

        $this->assertSame(
            '/pages/my-listings.php?renewed=1',
            $response['location']
        );

        // -----------------------------------------
        // 5. Verify the database after renewal.
        // -----------------------------------------

        $after = $this->listingRecord($listingId);

        // The listing should now have a renewal timestamp.
        $this->assertNotNull(
            $after['renewed_at']
        );

        // The listing must still be active.
        $this->assertSame(
            'active',
            $after['listing_status']
        );

        // The original creation date must not change.
        $this->assertSame(
            $before['created_at'],
            $after['created_at']
        );

        // The listing must retain its original owner.
        $this->assertSame(
            $seller['id'],
            (int) $after['seller_user_id']
        );

        // The listing name must remain unchanged.
        $this->assertSame(
            $listingName,
            $after['item_name']
        );

        // -----------------------------------------
        // 6. Verify the renewal confirmation message.
        // -----------------------------------------

        $successPage = $this->browser->get(
            '/pages/my-listings.php?renewed=1'
        );

        $this->assertSame(
            200,
            $successPage['status']
        );

        $this->assertStringContainsString(
            'Your listing was renewed successfully!',
            $successPage['body']
        );

        // The renewed listing should still appear.
        $this->assertStringContainsString(
            $listingName,
            $successPage['body']
        );

        // The page should display the Last Renewed field.
        $this->assertStringContainsString(
            'Last Renewed:',
            $successPage['body']
        );
    }

    // =========================================
    // TC-15-02: VERIFY RENEWAL TIMESTAMP UPDATE
    // =========================================

    public function testRenewalUpdatesTimestampWithoutChangingOtherData(): void
    {
        // Create a temporary seller and category.
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        // Create two active listings for the same seller.
        $targetName = 'UC15 Timestamp Test '
            . bin2hex(random_bytes(6));

        $controlName = 'UC15 Unchanged Control '
            . bin2hex(random_bytes(6));

        $targetId = $this->createListing(
            $seller['id'],
            $categoryId,
            $targetName
        );

        $controlId = $this->createListing(
            $seller['id'],
            $categoryId,
            $controlName
        );

        // -----------------------------------------
        // 1. Give the target listing an older
        //    creation date and renewal timestamp.
        // -----------------------------------------

        $stmt = $this->pdo->prepare(
            "UPDATE listings
             SET created_at = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 DAY),
                 renewed_at = DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 7 DAY)
             WHERE listing_id = ?"
        );

        $stmt->execute([$targetId]);

        // Retrieve both original database records.
        $before = $this->listingRecord($targetId);
        $controlBefore = $this->listingRecord($controlId);

        // Confirm the target has an existing renewal date.
        $this->assertNotNull($before['renewed_at']);

        $this->assertSame(
            'active',
            $before['listing_status']
        );

        // -----------------------------------------
        // 2. Authenticate as the listing owner.
        // -----------------------------------------

        $token = $this->logInAs($seller['email']);

        // Record database time immediately before renewal.
        $requestStartedAt = (string) $this->pdo
            ->query('SELECT CURRENT_TIMESTAMP')
            ->fetchColumn();

        // -----------------------------------------
        // 3. Submit the renewal request.
        // -----------------------------------------

        $response = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'renew_listing',
                'listing_id' => (string) $targetId,
                'csrf_token' => $token,
            ]
        );

        // Record database time immediately after renewal.
        $requestEndedAt = (string) $this->pdo
            ->query('SELECT CURRENT_TIMESTAMP')
            ->fetchColumn();

        $this->assertSame(
            303,
            $response['status'],
            $response['body']
        );

        $this->assertSame(
            '/pages/my-listings.php?renewed=1',
            $response['location']
        );

        // -----------------------------------------
        // 4. Verify the updated renewal timestamp.
        // -----------------------------------------

        $after = $this->listingRecord($targetId);

        $this->assertNotNull($after['renewed_at']);

        // Renewal must replace the older timestamp.
        $this->assertNotSame(
            $before['renewed_at'],
            $after['renewed_at']
        );

        // The new timestamp must be more recent.
        $this->assertGreaterThan(
            strtotime($before['renewed_at']),
            strtotime($after['renewed_at'])
        );

        // The renewal must occur within the database
        // time window surrounding the POST request.
        $this->assertGreaterThanOrEqual(
            $requestStartedAt,
            $after['renewed_at']
        );

        $this->assertLessThanOrEqual(
            $requestEndedAt,
            $after['renewed_at']
        );

        // -----------------------------------------
        // 5. Verify original listing data is preserved.
        // -----------------------------------------

        $this->assertSame(
            $before['listing_id'],
            $after['listing_id']
        );

        $this->assertSame(
            $before['seller_user_id'],
            $after['seller_user_id']
        );

        $this->assertSame(
            $before['item_name'],
            $after['item_name']
        );

        $this->assertSame(
            $before['listing_status'],
            $after['listing_status']
        );

        $this->assertSame(
            $before['created_at'],
            $after['created_at']
        );

        // -----------------------------------------
        // 6. Verify the unrelated listing is unchanged.
        // -----------------------------------------

        $controlAfter = $this->listingRecord($controlId);

        $this->assertSame(
            $controlBefore,
            $controlAfter
        );
    }

    // =========================================
    // TC-15-03: VERIFY RENEWAL AFFECTS LISTING SORTING
    // =========================================

    public function testRenewedListingMovesUpInNewestSearchResults(): void
    {
        // Create a temporary seller and category.
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        // Use a unique search term to isolate these listings.
        $searchTerm = 'UC15Sort' . bin2hex(random_bytes(6));

        $olderName = $searchTerm . ' Older Listing';
        $newerName = $searchTerm . ' Newer Listing';

        // Create two active listings for the same seller.
        $olderId = $this->createListing(
            $seller['id'],
            $categoryId,
            $olderName
        );

        $newerId = $this->createListing(
            $seller['id'],
            $categoryId,
            $newerName
        );

        // -----------------------------------------
        // 1. Give the listings different creation dates.
        // -----------------------------------------

        $stmt = $this->pdo->prepare(
            "UPDATE listings
             SET created_at = DATE_SUB(
                 CURRENT_TIMESTAMP, INTERVAL 30 DAY
             )
             WHERE listing_id = ?"
        );

        $stmt->execute([$olderId]);

        $stmt = $this->pdo->prepare(
            "UPDATE listings
             SET created_at = DATE_SUB(
                 CURRENT_TIMESTAMP, INTERVAL 1 DAY
             )
             WHERE listing_id = ?"
        );

        $stmt->execute([$newerId]);

        // Neither listing has been renewed yet.
        $this->assertNull(
            $this->listingRecord($olderId)['renewed_at']
        );

        $this->assertNull(
            $this->listingRecord($newerId)['renewed_at']
        );

        // -----------------------------------------
        // 2. Search for both listings using Newest sorting.
        // -----------------------------------------

        $searchUrl = '/pages/search.php?' . http_build_query([
            'q' => $searchTerm,
            'search_type' => 'items',
            'sort' => 'recent',
        ]);

        $beforeSearch = $this->browser->get($searchUrl);

        $this->assertSame(
            200,
            $beforeSearch['status']
        );

        // Confirm both listings appear in the results.
        $this->assertStringContainsString(
            $olderName,
            $beforeSearch['body']
        );

        $this->assertStringContainsString(
            $newerName,
            $beforeSearch['body']
        );

        // Find the positions of their listing links.
        $olderLink = 'href="/pages/listing.php?id='
            . $olderId . '"';

        $newerLink = 'href="/pages/listing.php?id='
            . $newerId . '"';

        $beforeOlderPosition = strpos(
            $beforeSearch['body'],
            $olderLink
        );

        $beforeNewerPosition = strpos(
            $beforeSearch['body'],
            $newerLink
        );

        $this->assertNotFalse($beforeOlderPosition);
        $this->assertNotFalse($beforeNewerPosition);

        // The newer listing should appear first.
        $this->assertLessThan(
            $beforeOlderPosition,
            $beforeNewerPosition
        );

        // -----------------------------------------
        // 3. Authenticate and renew the older listing.
        // -----------------------------------------

        $token = $this->logInAs($seller['email']);

        $response = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'renew_listing',
                'listing_id' => (string) $olderId,
                'csrf_token' => $token,
            ]
        );

        $this->assertSame(
            303,
            $response['status'],
            $response['body']
        );

        $this->assertSame(
            '/pages/my-listings.php?renewed=1',
            $response['location']
        );

        // -----------------------------------------
        // 4. Verify the renewal timestamp was updated.
        // -----------------------------------------

        $olderAfter = $this->listingRecord($olderId);
        $newerAfter = $this->listingRecord($newerId);

        $this->assertNotNull(
            $olderAfter['renewed_at']
        );

        $this->assertNull(
            $newerAfter['renewed_at']
        );

        $this->assertSame(
            'active',
            $olderAfter['listing_status']
        );

        $this->assertSame(
            'active',
            $newerAfter['listing_status']
        );

        // -----------------------------------------
        // 5. Search again after renewing the older listing.
        // -----------------------------------------

        $afterSearch = $this->browser->get($searchUrl);

        $this->assertSame(
            200,
            $afterSearch['status']
        );

        // Both listings should still be visible.
        $this->assertStringContainsString(
            $olderName,
            $afterSearch['body']
        );

        $this->assertStringContainsString(
            $newerName,
            $afterSearch['body']
        );

        // Find their new positions in the search results.
        $afterOlderPosition = strpos(
            $afterSearch['body'],
            $olderLink
        );

        $afterNewerPosition = strpos(
            $afterSearch['body'],
            $newerLink
        );

        $this->assertNotFalse($afterOlderPosition);
        $this->assertNotFalse($afterNewerPosition);

        // The renewed older listing should now appear first.
        $this->assertLessThan(
            $afterNewerPosition,
            $afterOlderPosition
        );
    }
    
    // =========================================
    // TC-15-04: PREVENT UNAUTHORIZED LISTING RENEWAL
    // =========================================

    public function testUserCannotRenewAnotherUsersListing(): void
    {
        // -----------------------------------------
        // 1. Create two separate sellers.
        // -----------------------------------------

        $sellerA = $this->createSeller();
        $sellerB = $this->createSeller();
        $categoryId = $this->createCategory();

        // Create an active listing owned by Seller A.
        $listingName = 'UC15 Protected Listing '
            . bin2hex(random_bytes(6));

        $listingId = $this->createListing(
            $sellerA['id'],
            $categoryId,
            $listingName
        );

        // -----------------------------------------
        // 2. Verify the original listing information.
        // -----------------------------------------

        $before = $this->listingRecord($listingId);

        // Seller A must own the listing.
        $this->assertSame(
            $sellerA['id'],
            (int) $before['seller_user_id']
        );

        // The listing must initially be active.
        $this->assertSame(
            'active',
            $before['listing_status']
        );

        // The listing has never been renewed.
        $this->assertNull(
            $before['renewed_at']
        );

        // -----------------------------------------
        // 3. Authenticate as Seller B.
        // -----------------------------------------

        // Seller B is NOT the listing owner.
        $token = $this->logInAs($sellerB['email']);

        // -----------------------------------------
        // 4. Attempt to renew Seller A's listing.
        // -----------------------------------------

        $response = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'renew_listing',
                'listing_id' => (string) $listingId,
                'csrf_token' => $token,
            ]
        );

        // The backend should reject the unauthorized request.
        $this->assertSame(
            404,
            $response['status'],
            $response['body']
        );

        // -----------------------------------------
        // 5. Verify the database was not modified.
        // -----------------------------------------

        $after = $this->listingRecord($listingId);

        // Seller A must still own the listing.
        $this->assertSame(
            $sellerA['id'],
            (int) $after['seller_user_id']
        );

        // The listing must remain active.
        $this->assertSame(
            'active',
            $after['listing_status']
        );

        // The renewal timestamp must remain NULL.
        $this->assertNull(
            $after['renewed_at']
        );

        // All retrieved listing fields must be unchanged.
        $this->assertSame(
            $before,
            $after
        );

        // -----------------------------------------
        // 6. Verify Seller B cannot see Seller A's
        //    listing on their My Listings page.
        // -----------------------------------------

        $myListings = $this->browser->get(
            '/pages/my-listings.php'
        );

        $this->assertSame(
            200,
            $myListings['status']
        );

        $this->assertStringNotContainsString(
            $listingName,
            $myListings['body']
        );
    }

    // =========================================
    // TC-15-05: PREVENT RENEWAL OF INELIGIBLE LISTINGS
    // =========================================

    public function testIneligibleListingsCannotBeRenewed(): void
    {
        // -----------------------------------------
        // 1. Create a temporary seller and category.
        // -----------------------------------------

        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        // Create an active listing to act as a control.
        $controlId = $this->createListing(
            $seller['id'],
            $categoryId,
            'UC15 Active Control ' . bin2hex(random_bytes(6))
        );

        $controlBefore = $this->listingRecord($controlId);

        // -----------------------------------------
        // 2. Authenticate as the listing owner.
        // -----------------------------------------

        $token = $this->logInAs($seller['email']);

        // -----------------------------------------
        // 3. Define the ineligible listing statuses.
        // -----------------------------------------

        $ineligibleStatuses = [
            'sold',
            'archived',
            'hidden',
            'draft'
        ];

        // -----------------------------------------
        // 4. Test each ineligible status.
        // -----------------------------------------

        foreach ($ineligibleStatuses as $status) {

            // Create a separate listing for this status.
            $listingName = 'UC15 Ineligible ' . $status . ' '
                . bin2hex(random_bytes(6));

            $listingId = $this->createListing(
                $seller['id'],
                $categoryId,
                $listingName
            );

            // Change the listing to the ineligible status.
            $stmt = $this->pdo->prepare(
                'UPDATE listings
                 SET listing_status = ?
                 WHERE listing_id = ?'
            );

            $stmt->execute([
                $status,
                $listingId
            ]);

            // -----------------------------------------
            // 5. Verify the original database record.
            // -----------------------------------------

            $before = $this->listingRecord($listingId);

            $this->assertSame(
                $status,
                $before['listing_status']
            );

            $this->assertNull(
                $before['renewed_at']
            );

            $this->assertSame(
                $seller['id'],
                (int) $before['seller_user_id']
            );

            // -----------------------------------------
            // 6. Attempt to renew the ineligible listing.
            // -----------------------------------------

            $response = $this->browser->post(
                '/pages/my-listings.php',
                [
                    'action' => 'renew_listing',
                    'listing_id' => (string) $listingId,
                    'csrf_token' => $token,
                ]
            );

            // The backend should reject the request.
            $this->assertSame(
                404,
                $response['status'],
                "Expected HTTP 404 for {$status} listing."
            );

            // -----------------------------------------
            // 7. Verify the database was not modified.
            // -----------------------------------------

            $after = $this->listingRecord($listingId);

            // The listing must retain its original status.
            $this->assertSame(
                $status,
                $after['listing_status']
            );

            // The renewal timestamp must remain NULL.
            $this->assertNull(
                $after['renewed_at']
            );

            // All retrieved listing fields must be unchanged.
            $this->assertSame(
                $before,
                $after,
                "The {$status} listing was unexpectedly modified."
            );
        }

        // -----------------------------------------
        // 8. Verify the unrelated active listing
        //    was not modified.
        // -----------------------------------------

        $controlAfter = $this->listingRecord($controlId);

        $this->assertSame(
            $controlBefore,
            $controlAfter
        );
    }

    // =========================================
    // TC-15-06: PREVENT UNAUTHENTICATED LISTING RENEWAL
    // =========================================

    public function testUnauthenticatedUserCannotRenewListing(): void
    {
        // -----------------------------------------
        // 1. Create a temporary seller and listing.
        // -----------------------------------------

        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        $listingName = 'UC15 Unauthenticated Test '
            . bin2hex(random_bytes(6));

        $listingId = $this->createListing(
            $seller['id'],
            $categoryId,
            $listingName
        );

        // -----------------------------------------
        // 2. Verify the original listing information.
        // -----------------------------------------

        $before = $this->listingRecord($listingId);

        $this->assertSame(
            'active',
            $before['listing_status']
        );

        $this->assertNull(
            $before['renewed_at']
        );

        $this->assertSame(
            $seller['id'],
            (int) $before['seller_user_id']
        );

        // -----------------------------------------
        // 3. Create an unauthenticated HTTP session.
        // -----------------------------------------

        // This client has not logged in as any user.
        $guest = HttpClient::fromEnvironment();

        // Visit the login page to obtain a valid
        // CSRF token for the guest session.
        $loginPage = $guest->get(
            '/pages/login.php'
        );

        $this->assertSame(
            200,
            $loginPage['status']
        );

        $token = HttpClient::csrfToken(
            $loginPage['body']
        );

        // -----------------------------------------
        // 4. Attempt to renew without logging in.
        // -----------------------------------------

        $response = $guest->post(
            '/pages/my-listings.php',
            [
                'action' => 'renew_listing',
                'listing_id' => (string) $listingId,
                'csrf_token' => $token,
            ]
        );

        // The application should redirect the
        // unauthenticated visitor to login.
        $this->assertSame(
            302,
            $response['status'],
            $response['body']
        );

        $this->assertSame(
            '/pages/login.php',
            $response['location']
        );

        // -----------------------------------------
        // 5. Verify the listing was not modified.
        // -----------------------------------------

        $after = $this->listingRecord($listingId);

        // The listing must remain active.
        $this->assertSame(
            'active',
            $after['listing_status']
        );

        // The renewal timestamp must remain NULL.
        $this->assertNull(
            $after['renewed_at']
        );

        // The listing must retain its original owner.
        $this->assertSame(
            $seller['id'],
            (int) $after['seller_user_id']
        );

        // All retrieved listing fields must be unchanged.
        $this->assertSame(
            $before,
            $after
        );

        // -----------------------------------------
        // 6. Verify the guest cannot access My Listings.
        // -----------------------------------------

        $pageResponse = $guest->get(
            '/pages/my-listings.php'
        );

        $this->assertSame(
            302,
            $pageResponse['status']
        );

        $this->assertSame(
            '/pages/login.php',
            $pageResponse['location']
        );
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
