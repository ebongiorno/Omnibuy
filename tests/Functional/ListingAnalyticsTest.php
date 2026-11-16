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
                    // Remove messages associated with this listing.
                    $this->pdo->prepare(
                        'DELETE FROM messages
                        WHERE conversation_id IN (
                            SELECT conversation_id
                            FROM conversations
                            WHERE listing_id = ?
                        )'
                    )->execute([$id]);

                    // Remove conversations associated with this listing.
                    $this->pdo->prepare(
                        'DELETE FROM conversations WHERE listing_id = ?'
                    )->execute([$id]);

                    // Remove wishlist saves associated with this listing.
                    $this->pdo->prepare(
                        'DELETE FROM wishlist_items WHERE listing_id = ?'
                    )->execute([$id]);
                    
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
    // TC-11-01: VIEW ANALYTICS FOR OWN LISTING
    // =========================================

    public function testUserCanViewOwnListingAnalytics(): void
    {
        // Create a temporary listing owner.
        $owner = $this->createListingOwner();

        // Create a category for the listing.
        $categoryId = $this->createCategory();

        // Generate a unique listing name.
        $listingName = 'UC11 Analytics Test '
            . bin2hex(random_bytes(4));

        // Create a listing owned by the test user.
        $listingId = $this->createListing(
            $owner['id'],
            $categoryId,
            $listingName
        );

        // Authenticate as the listing owner.
        $this->logInAs($owner['email']);

        // Open My Listings.
        $myListingsResponse = $this->browser->get(
            '/pages/my-listings.php'
        );

        // Verify the page loads successfully.
        $this->assertSame(
            200,
            $myListingsResponse['status']
        );

        // Verify the owner's listing appears.
        $this->assertStringContainsString(
            $listingName,
            $myListingsResponse['body']
        );

        // Build the expected analytics URL.
        $analyticsUrl = '/pages/listing-analytics.php?id='
            . $listingId;

        // Verify My Listings contains the correct link.
        $this->assertStringContainsString(
            $analyticsUrl,
            $myListingsResponse['body']
        );

        // Open analytics for the owned listing.
        $analyticsResponse = $this->browser->get(
            $analyticsUrl
        );

        // Verify successful access.
        $this->assertSame(
            200,
            $analyticsResponse['status'],
            $analyticsResponse['body']
        );

        // Verify the analytics page heading.
        $this->assertStringContainsString(
            'Listing Analytics',
            $analyticsResponse['body']
        );

        // Verify the correct listing is displayed.
        $this->assertStringContainsString(
            $listingName,
            $analyticsResponse['body']
        );

        // Verify the listing's price.
        $this->assertStringContainsString(
            '$25.00',
            $analyticsResponse['body']
        );

        // Verify all six analytics metrics are displayed.
        $this->assertSame(
            6,
            substr_count(
                $analyticsResponse['body'],
                'class="listing-analytics__card-label"'
            )
        );
    }

    // =========================================
    // TC-11-02: VERIFY ANALYTICS METRIC ACCURACY
    // =========================================

    public function testAnalyticsMetricsMatchDatabaseRecords(): void 
    {
        // Create the listing owner.
        $owner = $this->createListingOwner();

        // Create two additional users to generate activity.
        $viewerOne = $this->createListingOwner();
        $viewerTwo = $this->createListingOwner();

        // Create the listing.
        $categoryId = $this->createCategory();

        $listingName = 'UC11 Metrics Test '
            . bin2hex(random_bytes(4));

        $listingId = $this->createListing(
            $owner['id'],
            $categoryId,
            $listingName
        );

        // Insert a recent listing view.
        // Insert a recent listing view.
        $recentView = $this->pdo->prepare(
            'INSERT INTO listing_views (
                user_id,
                listing_id,
                viewed_at
            ) VALUES (?, ?, NOW())'
        );

        // Insert an older listing view.
        $olderView = $this->pdo->prepare(
            'INSERT INTO listing_views (
                user_id,
                listing_id,
                viewed_at
            ) VALUES (?, ?, NOW() - INTERVAL 10 DAY)'
        );

        // Viewer one views the listing twice recently.
        $recentView->execute([
            $viewerOne['id'],
            $listingId
        ]);

        $recentView->execute([
            $viewerOne['id'],
            $listingId
        ]);

        // Viewer two viewed the listing 10 days ago.
        $olderView->execute([
            $viewerTwo['id'],
            $listingId
        ]);

        // Save the listing to two users' wishlists.
        $wishlistStmt = $this->pdo->prepare(
            'INSERT INTO wishlist_items (
                user_id,
                listing_id
            ) VALUES (?, ?)'
        );

        $wishlistStmt->execute([
            $viewerOne['id'],
            $listingId
        ]);

        $wishlistStmt->execute([
            $viewerTwo['id'],
            $listingId
        ]);

        // Create a conversation about the listing.
        $conversationStmt = $this->pdo->prepare(
            'INSERT INTO conversations (
                buyer_id,
                seller_id,
                listing_id
            ) VALUES (?, ?, ?)'
        );

        $conversationStmt->execute([
            $viewerOne['id'],
            $owner['id'],
            $listingId
        ]);

        $conversationId = (int) $this->pdo->lastInsertId();

        // Create two messages in the conversation.
        $messageStmt = $this->pdo->prepare(
            'INSERT INTO messages (
                conversation_id,
                sender_user_id,
                message_text
            ) VALUES (?, ?, ?)'
        );

        // Buyer sends a message.
        $messageStmt->execute([
            $conversationId,
            $viewerOne['id'],
            'Is this listing still available?'
        ]);

        // Listing owner replies.
        $messageStmt->execute([
            $conversationId,
            $owner['id'],
            'Yes, it is still available.'
        ]);

        // Log in as the listing owner.
        $this->logInAs($owner['email']);

        // Open the listing analytics page.
        $response = $this->browser->get(
            '/pages/listing-analytics.php?id=' . $listingId
        );

        // Verify the analytics page loads successfully.
        $this->assertSame(
            200,
            $response['status'],
            $response['body']
        );

        // Parse the analytics page HTML.
        $document = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response['body']);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);

        // Find all analytics metric cards.
        $cards = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-analytics__card ')]"
        );

        $actualMetrics = [];

        foreach ($cards as $card) {
            $labelNode = $xpath->query(
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-analytics__card-label ')]",
                $card
            )->item(0);

            $valueNode = $xpath->query(
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-analytics__card-value ')]",
                $card
            )->item(0);

            if ($labelNode === null || $valueNode === null) {
                continue;
            }

            $label = trim($labelNode->textContent);
            $value = str_replace(',', '', trim($valueNode->textContent));

            $actualMetrics[$label] = (int) $value;
        }

        // Verify the six calculated analytics metrics.
        $this->assertSame(
            [
                'Total Views' => 3,
                'Unique Viewers' => 2,
                'Views in Last 7 Days' => 2,
                'Wishlist Saves' => 2,
                'Total Conversations' => 1,
                'Total Messages' => 2,
            ],
            $actualMetrics
        );
    }

    // =========================================
    // TC-11-03: VERIFY LISTING ANALYTICS ISOLATION
    // =========================================

    public function testAnalyticsBelongToCorrectListing(): void
    {
        // Create one owner and two viewers.
        $owner = $this->createListingOwner();
        $viewerOne = $this->createListingOwner();
        $viewerTwo = $this->createListingOwner();

        // Both listings belong to the same owner.
        $categoryId = $this->createCategory();

        $listingNameA = 'UC11 Listing A '
            . bin2hex(random_bytes(4));

        $listingNameB = 'UC11 Listing B '
            . bin2hex(random_bytes(4));

        $listingIdA = $this->createListing(
            $owner['id'],
            $categoryId,
            $listingNameA
        );

        $listingIdB = $this->createListing(
            $owner['id'],
            $categoryId,
            $listingNameB
        );

        // Create recent and older listing views.
        $recentView = $this->pdo->prepare(
            'INSERT INTO listing_views (
                user_id,
                listing_id,
                viewed_at
            ) VALUES (?, ?, NOW())'
        );

        $olderView = $this->pdo->prepare(
            'INSERT INTO listing_views (
                user_id,
                listing_id,
                viewed_at
            ) VALUES (?, ?, NOW() - INTERVAL 10 DAY)'
        );

        // Listing A: three views, two unique viewers.
        $recentView->execute([$viewerOne['id'], $listingIdA]);
        $recentView->execute([$viewerOne['id'], $listingIdA]);
        $olderView->execute([$viewerTwo['id'], $listingIdA]);

        // Listing B: one recent view.
        $recentView->execute([$viewerOne['id'], $listingIdB]);

        // Listing A: two wishlist saves.
        $wishlistStmt = $this->pdo->prepare(
            'INSERT INTO wishlist_items (
                user_id,
                listing_id
            ) VALUES (?, ?)'
        );

        $wishlistStmt->execute([$viewerOne['id'], $listingIdA]);
        $wishlistStmt->execute([$viewerTwo['id'], $listingIdA]);

        // Listing A: one conversation.
        $conversationStmt = $this->pdo->prepare(
            'INSERT INTO conversations (
                buyer_id,
                seller_id,
                listing_id
            ) VALUES (?, ?, ?)'
        );

        $conversationStmt->execute([
            $viewerOne['id'],
            $owner['id'],
            $listingIdA
        ]);

        $conversationId = (int) $this->pdo->lastInsertId();

        // Listing A: two messages.
        $messageStmt = $this->pdo->prepare(
            'INSERT INTO messages (
                conversation_id,
                sender_user_id,
                message_text
            ) VALUES (?, ?, ?)'
        );

        $messageStmt->execute([
            $conversationId,
            $viewerOne['id'],
            'TC-11-03 test message from buyer.'
        ]);

        $messageStmt->execute([
            $conversationId,
            $owner['id'],
            'TC-11-03 test reply from owner.'
        ]);

        // Log in as the owner of both listings.
        $this->logInAs($owner['email']);

        // Retrieve analytics for Listing A.
        $responseA = $this->browser->get(
            '/pages/listing-analytics.php?id=' . $listingIdA
        );

        // Retrieve analytics for Listing B.
        $responseB = $this->browser->get(
            '/pages/listing-analytics.php?id=' . $listingIdB
        );

        // Both requests should succeed.
        $this->assertSame(200, $responseA['status'], $responseA['body']);
        $this->assertSame(200, $responseB['status'], $responseB['body']);

        // Verify that each response identifies the correct listing.
        $this->assertStringContainsString(
            $listingNameA,
            $responseA['body']
        );

        $this->assertStringContainsString(
            $listingNameB,
            $responseB['body']
        );

        // Neither page should show the other listing's name.
        $this->assertStringNotContainsString(
            $listingNameB,
            $responseA['body']
        );

        $this->assertStringNotContainsString(
            $listingNameA,
            $responseB['body']
        );
        
        // Extract the metrics from each response.
        $metricsA = $this->extractAnalyticsMetrics(
            $responseA['body']
        );

        $metricsB = $this->extractAnalyticsMetrics(
            $responseB['body']
        );

        // Listing A must show only its own activity.
        $this->assertSame(
            [
                'Total Views' => 3,
                'Unique Viewers' => 2,
                'Views in Last 7 Days' => 2,
                'Wishlist Saves' => 2,
                'Total Conversations' => 1,
                'Total Messages' => 2,
            ],
            $metricsA
        );

        // Listing B must not include Listing A's activity.
        $this->assertSame(
            [
                'Total Views' => 1,
                'Unique Viewers' => 1,
                'Views in Last 7 Days' => 1,
                'Wishlist Saves' => 0,
                'Total Conversations' => 0,
                'Total Messages' => 0,
            ],
            $metricsB
        );
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

    private function extractAnalyticsMetrics(string $html): array
    {
        $document = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);

        $cards = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-analytics__card ')]"
        );

        $actualMetrics = [];

        foreach ($cards as $card) {
            $labelNode = $xpath->query(
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-analytics__card-label ')]",
                $card
            )->item(0);

            $valueNode = $xpath->query(
                ".//*[contains(concat(' ', normalize-space(@class), ' '), ' listing-analytics__card-value ')]",
                $card
            )->item(0);

            if ($labelNode === null || $valueNode === null) {
                continue;
            }

            $label = trim($labelNode->textContent);

            $value = str_replace(
                ',',
                '',
                trim($valueNode->textContent)
            );

            $actualMetrics[$label] = (int) $value;
        }

        return $actualMetrics;
    }
}