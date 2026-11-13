<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\HttpClient;
use PDO;
use PHPUnit\Framework\TestCase;

final class MarkListingSoldTest extends TestCase
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
    // TC-14-01: MARK AN ACTIVE LISTING AS SOLD
    // =========================================

    public function testSellerCanMarkOwnActiveListingAsSold(): void
    {
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        $listingName = 'UC14 Active Listing '
            . bin2hex(random_bytes(4));

        $listingId = $this->createListing(
            $seller['id'],
            $categoryId,
            $listingName
        );

        // Confirm the listing starts as active.
        $this->assertSame(
            'active',
            $this->listingStatus($listingId)
        );

        // Log in as the listing owner.
        $token = $this->logInAs($seller['email']);

        // Verify the seller can see the listing and button.
        $before = $this->browser->get('/pages/my-listings.php');

        $this->assertSame(200, $before['status']);
        $this->assertStringContainsString(
            $listingName,
            $before['body']
        );
        $this->assertStringContainsString(
            'Mark as Sold',
            $before['body']
        );

        // Submit the Mark as Sold form.
        $result = $this->browser->post('/pages/my-listings.php', [
            'action' => 'mark_sold',
            'listing_id' => (string) $listingId,
            'csrf_token' => $token,
        ]);

        // Verify the redirect and database update.
        $this->assertSame(303, $result['status'], $result['body']);
        $this->assertSame(
            '/pages/my-listings.php?sold=1',
            $result['location']
        );

        $this->assertSame(
            'sold',
            $this->listingStatus($listingId)
        );

        // Verify the updated page displays the sold listing.
        $after = $this->browser->get($result['location']);

        $this->assertSame(200, $after['status']);

        $this->assertStringContainsString(
            $listingName,
            $after['body']
        );

        $this->assertStringContainsString(
            'Your listing was marked as sold successfully!',
            $after['body']
        );

        // A sold listing should no longer offer this action.
        $this->assertStringNotContainsString(
            'Mark as Sold',
            $after['body']
        );
    }


    // =========================================
    // TC-14-02: VERIFY LISTING STATUS IN DATABASE
    // =========================================

    public function testMarkSoldUpdatesOnlyTargetListingRecord(): void
    {
        // Create a temporary seller and category.
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        // Create two active listings belonging to the same seller.
        $targetId = $this->createListing(
            $seller['id'],
            $categoryId,
            'UC14 Target Listing ' . bin2hex(random_bytes(4))
        );

        $otherId = $this->createListing(
            $seller['id'],
            $categoryId,
            'UC14 Unrelated Listing ' . bin2hex(random_bytes(4))
        );

        // Retrieve the target record before the update.
        $stmt = $this->pdo->prepare(
            'SELECT listing_id, seller_user_id, listing_status
             FROM listings
             WHERE listing_id = ?'
        );

        $stmt->execute([$targetId]);
        $before = $stmt->fetch();

        $this->assertIsArray($before);
        $this->assertSame('active', $before['listing_status']);
        $this->assertSame(
            'active',
            $this->listingStatus($otherId)
        );

        // Log in as the owner and mark the target listing as sold.
        $token = $this->logInAs($seller['email']);

        $response = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'mark_sold',
                'listing_id' => (string) $targetId,
                'csrf_token' => $token,
            ]
        );

        $this->assertSame(303, $response['status']);

        // Retrieve the target record after the update.
        $stmt->execute([$targetId]);
        $after = $stmt->fetch();

        $this->assertIsArray($after);

        // Verify the correct listing was changed to sold.
        $this->assertSame(
            'sold',
            $after['listing_status']
        );

        // Verify the listing ID did not change.
        $this->assertSame(
            $before['listing_id'],
            $after['listing_id']
        );

        // Verify seller ownership did not change.
        $this->assertSame(
            $before['seller_user_id'],
            $after['seller_user_id']
        );

        // Verify the unrelated listing remains active.
        $this->assertSame(
            'active',
            $this->listingStatus($otherId)
        );
    }


    // =========================================
    // TC-14-03: EXCLUDE SOLD LISTINGS FROM SEARCH
    // =========================================

    public function testSoldListingIsExcludedFromActiveSearchResults(): void
    {
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        // Use a unique term to isolate this test's listings.
        $searchTerm = 'UC14Search' . bin2hex(random_bytes(6));

        $soldName = $searchTerm . ' Sold Candidate';
        $activeName = $searchTerm . ' Active Control';

        $soldId = $this->createListing(
            $seller['id'],
            $categoryId,
            $soldName
        );

        $activeId = $this->createListing(
            $seller['id'],
            $categoryId,
            $activeName
        );

        $browseUrl = '/pages/search.php?browse=all';

        $searchUrl = '/pages/search.php?' . http_build_query([
            'q' => $searchTerm,
            'search_type' => 'items',
        ]);

        // Both listings should initially appear in Browse All.
        $beforeBrowse = $this->browser->get($browseUrl);

        $this->assertSame(200, $beforeBrowse['status']);
        $this->assertStringContainsString(
            $soldName,
            $beforeBrowse['body']
        );
        $this->assertStringContainsString(
            $activeName,
            $beforeBrowse['body']
        );

        // Both should also appear in keyword search.
        $beforeSearch = $this->browser->get($searchUrl);

        $this->assertSame(200, $beforeSearch['status']);
        $this->assertStringContainsString(
            $soldName,
            $beforeSearch['body']
        );
        $this->assertStringContainsString(
            $activeName,
            $beforeSearch['body']
        );

        // Log in and mark the first listing as sold.
        $token = $this->logInAs($seller['email']);

        $response = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'mark_sold',
                'listing_id' => (string) $soldId,
                'csrf_token' => $token,
            ]
        );

        $this->assertSame(303, $response['status']);

        // Confirm the database statuses.
        $this->assertSame(
            'sold',
            $this->listingStatus($soldId)
        );

        $this->assertSame(
            'active',
            $this->listingStatus($activeId)
        );

        // The sold listing must disappear from Browse All.
        $afterBrowse = $this->browser->get($browseUrl);

        $this->assertSame(200, $afterBrowse['status']);
        $this->assertStringNotContainsString(
            $soldName,
            $afterBrowse['body']
        );
        $this->assertStringContainsString(
            $activeName,
            $afterBrowse['body']
        );

        // The sold listing must also disappear from search.
        $afterSearch = $this->browser->get($searchUrl);

        $this->assertSame(200, $afterSearch['status']);
        $this->assertStringNotContainsString(
            $soldName,
            $afterSearch['body']
        );
        $this->assertStringContainsString(
            $activeName,
            $afterSearch['body']
        );

        // Verify the active listing's link remains available.
        $this->assertStringContainsString(
            'href="/pages/listing.php?id=' . $activeId . '"',
            $afterSearch['body']
        );

        // Verify the sold listing's link is absent.
        $this->assertStringNotContainsString(
            'href="/pages/listing.php?id=' . $soldId . '"',
            $afterSearch['body']
        );
    }


    // =========================================
    // TC-14-04: VERIFY SOLD LISTING HISTORY
    // =========================================

    public function testSoldListingRemainsInSellerHistory(): void
    {
        // Create a temporary seller and listing.
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        $listingName = 'UC14 Sold History '
            . bin2hex(random_bytes(6));

        $listingId = $this->createListing(
            $seller['id'],
            $categoryId,
            $listingName
        );

        // Confirm the listing initially exists and is active.
        $this->assertSame(
            'active',
            $this->listingStatus($listingId)
        );

        // Log in as the listing owner.
        $token = $this->logInAs($seller['email']);

        // Mark the listing as sold.
        $response = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'mark_sold',
                'listing_id' => (string) $listingId,
                'csrf_token' => $token,
            ]
        );

        $this->assertSame(303, $response['status']);

        // Confirm the database still contains the listing.
        $stmt = $this->pdo->prepare(
            'SELECT listing_id,
                    seller_user_id,
                    item_name,
                    listing_status
             FROM listings
             WHERE listing_id = ?'
        );

        $stmt->execute([$listingId]);
        $listing = $stmt->fetch();

        $this->assertIsArray($listing);

        $this->assertSame(
            $seller['id'],
            (int) $listing['seller_user_id']
        );

        $this->assertSame(
            $listingName,
            $listing['item_name']
        );

        $this->assertSame(
            'sold',
            $listing['listing_status']
        );

        // Visit My Listings again without the success parameter.
        $history = $this->browser->get(
            '/pages/my-listings.php'
        );

        $this->assertSame(200, $history['status']);

        // The sold listing should remain visible to its owner.
        $this->assertStringContainsString(
            $listingName,
            $history['body']
        );

        // Its updated status should be displayed.
        $this->assertStringContainsString(
            'Sold',
            $history['body']
        );

        // This seller has no other active listings,
        // so no Mark as Sold button should be available.
        $this->assertStringNotContainsString(
            'Mark as Sold',
            $history['body']
        );
    }

    
    // =========================================
    // TC-14-05: PREVENT UNAUTHORIZED STATUS CHANGES
    // =========================================

    public function testUserCannotMarkAnotherUsersListingAsSold(): void
    {
        // Create two separate users.
        $sellerA = $this->createSeller();
        $sellerB = $this->createSeller();
        $categoryId = $this->createCategory();

        // Create a listing owned by User A.
        $listingName = 'UC14 Protected Listing '
            . bin2hex(random_bytes(6));

        $listingId = $this->createListing(
            $sellerA['id'],
            $categoryId,
            $listingName
        );

        // Confirm the listing belongs to User A
        // and is initially active.
        $stmt = $this->pdo->prepare(
            'SELECT seller_user_id, listing_status
             FROM listings
             WHERE listing_id = ?'
        );

        $stmt->execute([$listingId]);
        $before = $stmt->fetch();

        $this->assertIsArray($before);

        $this->assertSame(
            $sellerA['id'],
            (int) $before['seller_user_id']
        );

        $this->assertSame(
            'active',
            $before['listing_status']
        );

        // Authenticate as User B, not the listing owner.
        $token = $this->logInAs($sellerB['email']);

        // User B attempts to mark User A's listing as sold.
        $response = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'mark_sold',
                'listing_id' => (string) $listingId,
                'csrf_token' => $token,
            ]
        );

        // The backend should reject the unauthorized update.
        $this->assertSame(
            404,
            $response['status'],
            $response['body']
        );

        // Verify that the listing still exists,
        // belongs to User A, and remains active.
        $stmt->execute([$listingId]);
        $after = $stmt->fetch();

        $this->assertIsArray($after);

        $this->assertSame(
            $sellerA['id'],
            (int) $after['seller_user_id']
        );

        $this->assertSame(
            'active',
            $after['listing_status']
        );

        // User B should not see User A's listing
        // on their own My Listings page.
        $myListings = $this->browser->get(
            '/pages/my-listings.php'
        );

        $this->assertSame(200, $myListings['status']);

        $this->assertStringNotContainsString(
            $listingName,
            $myListings['body']
        );
    }

    
    // =========================================
    // TC-14-06: HANDLE REPEATED OR INVALID CHANGES
    // =========================================

    public function testRepeatedAndInvalidStatusChangesAreRejected(): void
    {
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        // Create two active listings.
        $soldId = $this->createListing(
            $seller['id'],
            $categoryId,
            'UC14 Repeat Test ' . bin2hex(random_bytes(4))
        );

        $controlId = $this->createListing(
            $seller['id'],
            $categoryId,
            'UC14 Invalid Request Control ' . bin2hex(random_bytes(4))
        );

        $this->assertSame(
            'active',
            $this->listingStatus($soldId)
        );

        $this->assertSame(
            'active',
            $this->listingStatus($controlId)
        );

        // Authenticate as the listing owner.
        $token = $this->logInAs($seller['email']);

        // -----------------------------------------
        // 1. Successfully mark the first listing sold.
        // -----------------------------------------

        $firstResponse = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'mark_sold',
                'listing_id' => (string) $soldId,
                'csrf_token' => $token,
            ]
        );

        $this->assertSame(303, $firstResponse['status']);

        $this->assertSame(
            'sold',
            $this->listingStatus($soldId)
        );

        // -----------------------------------------
        // 2. Attempt to mark it sold again.
        // -----------------------------------------

        $repeatResponse = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'mark_sold',
                'listing_id' => (string) $soldId,
                'csrf_token' => $token,
            ]
        );

        $this->assertSame(
            404,
            $repeatResponse['status'],
            $repeatResponse['body']
        );

        $this->assertSame(
            'sold',
            $this->listingStatus($soldId)
        );

        // -----------------------------------------
        // 3. Submit an invalid listing ID.
        // -----------------------------------------

        $invalidIdResponse = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'mark_sold',
                'listing_id' => 'invalid-id',
                'csrf_token' => $token,
            ]
        );

        $this->assertSame(
            400,
            $invalidIdResponse['status'],
            $invalidIdResponse['body']
        );

        // -----------------------------------------
        // 4. Submit a missing CSRF token.
        // -----------------------------------------

        $missingTokenResponse = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'mark_sold',
                'listing_id' => (string) $controlId,
            ]
        );

        $this->assertSame(
            403,
            $missingTokenResponse['status'],
            $missingTokenResponse['body']
        );

        $this->assertSame(
            'active',
            $this->listingStatus($controlId)
        );

        // -----------------------------------------
        // 5. Submit an incorrect CSRF token.
        // -----------------------------------------

        $invalidTokenResponse = $this->browser->post(
            '/pages/my-listings.php',
            [
                'action' => 'mark_sold',
                'listing_id' => (string) $controlId,
                'csrf_token' => 'invalid-security-token',
            ]
        );

        $this->assertSame(
            403,
            $invalidTokenResponse['status'],
            $invalidTokenResponse['body']
        );

        $this->assertSame(
            'active',
            $this->listingStatus($controlId)
        );

        // -----------------------------------------
        // 6. Attempt the status change using GET.
        // -----------------------------------------

        $getResponse = $this->browser->get(
            '/pages/my-listings.php?' . http_build_query([
                'action' => 'mark_sold',
                'listing_id' => $controlId,
            ])
        );

        $this->assertSame(
            200,
            $getResponse['status'],
            $getResponse['body']
        );

        // -----------------------------------------
        // 7. Verify the final database statuses.
        // -----------------------------------------

        $this->assertSame(
            'sold',
            $this->listingStatus($soldId)
        );

        $this->assertSame(
            'active',
            $this->listingStatus($controlId)
        );
    }


    // =========================================
    // TC-14-07: PREVENT UNAUTHENTICATED STATUS CHANGES
    // =========================================

    public function testUnauthenticatedUserCannotMarkListingAsSold(): void
    {
        // Create a temporary listing owner.
        $seller = $this->createSeller();
        $categoryId = $this->createCategory();

        $listingName = 'UC14 Unauthenticated Test '
            . bin2hex(random_bytes(6));

        $listingId = $this->createListing(
            $seller['id'],
            $categoryId,
            $listingName
        );

        // Confirm the listing initially belongs to the seller
        // and has an active status.
        $this->assertSame(
            'active',
            $this->listingStatus($listingId)
        );

        // Create a separate HTTP client without logging in.
        // This simulates an unauthenticated visitor.
        $guest = HttpClient::fromEnvironment();

        // Obtain a valid guest-session CSRF token.
        // This isolates authentication as the reason
        // the listing update must be rejected.
        $loginPage = $guest->get('/pages/login.php');

        $this->assertSame(200, $loginPage['status']);

        $token = HttpClient::csrfToken(
            $loginPage['body']
        );

        // Attempt to mark the listing as sold without logging in.
        $response = $guest->post(
            '/pages/my-listings.php',
            [
                'action' => 'mark_sold',
                'listing_id' => (string) $listingId,
                'csrf_token' => $token,
            ]
        );

        // The application should redirect the visitor to login.
        $this->assertSame(
            302,
            $response['status'],
            $response['body']
        );

        $this->assertSame(
            '/pages/login.php',
            $response['location']
        );

        // Verify the listing was not modified.
        $this->assertSame(
            'active',
            $this->listingStatus($listingId)
        );

        // Verify the listing still belongs to its original owner.
        $stmt = $this->pdo->prepare(
            'SELECT seller_user_id, item_name, listing_status
             FROM listings
             WHERE listing_id = ?'
        );

        $stmt->execute([$listingId]);
        $listing = $stmt->fetch();

        $this->assertIsArray($listing);

        $this->assertSame(
            $seller['id'],
            (int) $listing['seller_user_id']
        );

        $this->assertSame(
            $listingName,
            $listing['item_name']
        );

        $this->assertSame(
            'active',
            $listing['listing_status']
        );

        // The unauthenticated visitor should also be
        // unable to access the My Listings page.
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
    // TEST DATA HELPERS
    // =========================================

    private function createSeller(): array
    {
        $suffix = bin2hex(random_bytes(6));
        $username = "uc14_$suffix";
        $email = "uc14_$suffix@example.test";

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
            'UC14',
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
            "UC14 Store $suffix",
            "UC14_$suffix",
        ]);

        return [
            'id' => $userId,
            'email' => $email,
        ];
    }

    private function createCategory(): int
    {
        $name = 'UC14 Category '
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
            'UC-14 temporary test listing',
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

    private function listingStatus(int $listingId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT listing_status
             FROM listings
             WHERE listing_id = ?'
        );

        $stmt->execute([$listingId]);

        return (string) $stmt->fetchColumn();
    }
}
