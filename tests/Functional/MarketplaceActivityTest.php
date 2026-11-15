<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/marketplace-activity.php';

/**
 * UC-17 / UC-25: Database integration tests for the activity helpers.
 *
 * These tests deliberately use one PDO connection and a transaction per test.
 * Rollback removes all temporary activity, listings, seller profiles, categories,
 * and users without deleting seed data or leaving foreign-key dependencies.
 *
 * The helpers are tested directly. The calling pages' authentication, HTTP
 * flow, and pagination/filter detection are separate functional concerns.
 */
final class MarketplaceActivityTest extends TestCase
{
    private PDO $pdo;

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
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        // No HTTP calls or DDL: all fixtures and helper writes remain in this
        // transaction, making rollback reliable for an InnoDB test database.
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->pdo) && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    // =========================================
    // UC-17: SEARCH ACTIVITY
    // =========================================

    // TC-17-06
    public function testItemSearchIsSavedWithNormalizedQueryAndTimestamp(): void
    {
        $buyerId = $this->createUser();

        $this->assertTrue(recordSearchActivity($this->pdo, $buyerId, '  Mechanical   Keyboard  '));

        $rows = $this->searchHistoryFor($buyerId);
        $this->assertCount(1, $rows);
        $this->assertSame($buyerId, (int) $rows[0]['user_id']);
        $this->assertSame('Mechanical Keyboard', $rows[0]['search_query']);
        $this->assertSame('items', $rows[0]['search_type']);
        $this->assertNotEmpty($rows[0]['searched_at']);
    }

    // TC-17-07
    public function testSameSearchIsDeduplicatedWithinFiveMinutes(): void
    {
        $buyerId = $this->createUser();

        $this->assertTrue(recordSearchActivity($this->pdo, $buyerId, 'Wireless Keyboard'));
        // Case and surrounding/repeated spaces should not create a new event.
        $this->assertFalse(recordSearchActivity($this->pdo, $buyerId, ' wireless   keyboard '));
        $this->assertCount(1, $this->searchHistoryFor($buyerId));
    }

    // TC-17-08
    public function testSameSearchCanBeRecordedAfterFiveMinutes(): void
    {
        $buyerId = $this->createUser();
        $this->assertTrue(recordSearchActivity($this->pdo, $buyerId, 'headphones'));

        // Backdate test data instead of sleeping five minutes.
        $statement = $this->pdo->prepare(
            'UPDATE search_history SET searched_at = NOW() - INTERVAL 6 MINUTE WHERE user_id = ?'
        );
        $statement->execute([$buyerId]);

        $this->assertTrue(recordSearchActivity($this->pdo, $buyerId, 'headphones'));
        $this->assertCount(2, $this->searchHistoryFor($buyerId));
    }

    // TC-17-09
    public function testSearchesAreIndependentByUserAndKeyword(): void
    {
        $buyerA = $this->createUser();
        $buyerB = $this->createUser();

        $this->assertTrue(recordSearchActivity($this->pdo, $buyerA, 'laptop'));
        $this->assertTrue(recordSearchActivity($this->pdo, $buyerA, 'camera'));
        $this->assertTrue(recordSearchActivity($this->pdo, $buyerB, 'laptop'));

        $this->assertCount(2, $this->searchHistoryFor($buyerA));
        $this->assertCount(1, $this->searchHistoryFor($buyerB));
        $this->assertSame('laptop', $this->searchHistoryFor($buyerB)[0]['search_query']);
    }

    // TC-17-10
    public function testInvalidSearchActivityIsNotSaved(): void
    {
        $buyerId = $this->createUser();

        $this->assertFalse(recordSearchActivity($this->pdo, 0, 'headphones'));
        $this->assertFalse(recordSearchActivity($this->pdo, $buyerId, ''));
        $this->assertFalse(recordSearchActivity($this->pdo, $buyerId, "  \t  "));
        $this->assertFalse(recordSearchActivity($this->pdo, $buyerId, str_repeat('x', 256)));
        $this->assertCount(0, $this->searchHistoryFor($buyerId));
    }

    // TC-17-11
    public function testSearchTrackingWriteErrorIsHandledWithoutThrowing(): void
    {
        $this->assertFalse(recordSearchActivity(new FailingActivityPdo(), 1, 'keyboard'));
    }

    // =========================================
    // UC-25: LISTING VIEW ACTIVITY
    // =========================================

    // TC-25-06
    public function testEligibleListingViewIsSavedWithTimestamp(): void
    {
        $sellerId = $this->createUser(true);
        $buyerId = $this->createUser();
        $categoryId = $this->createCategory();
        $listingId = $this->createListing($sellerId, $categoryId);

        $this->assertTrue(recordListingView($this->pdo, $buyerId, $listingId));

        $rows = $this->listingViewsFor($buyerId);
        $this->assertCount(1, $rows);
        $this->assertSame($buyerId, (int) $rows[0]['user_id']);
        $this->assertSame($listingId, (int) $rows[0]['listing_id']);
        $this->assertNotEmpty($rows[0]['viewed_at']);
    }

    // TC-25-07
    public function testSameListingViewIsDeduplicatedWithinFifteenMinutes(): void
    {
        $sellerId = $this->createUser(true);
        $buyerId = $this->createUser();
        $listingId = $this->createListing($sellerId, $this->createCategory());

        $this->assertTrue(recordListingView($this->pdo, $buyerId, $listingId));
        $this->assertFalse(recordListingView($this->pdo, $buyerId, $listingId));
        $this->assertCount(1, $this->listingViewsFor($buyerId));
    }

    // TC-25-08
    public function testListingViewCanBeRecordedAfterFifteenMinutes(): void
    {
        $sellerId = $this->createUser(true);
        $buyerId = $this->createUser();
        $listingId = $this->createListing($sellerId, $this->createCategory());

        $this->assertTrue(recordListingView($this->pdo, $buyerId, $listingId));
        $statement = $this->pdo->prepare(
            'UPDATE listing_views SET viewed_at = NOW() - INTERVAL 16 MINUTE WHERE user_id = ? AND listing_id = ?'
        );
        $statement->execute([$buyerId, $listingId]);

        $this->assertTrue(recordListingView($this->pdo, $buyerId, $listingId));
        $this->assertCount(2, $this->listingViewsFor($buyerId));
    }

    // TC-25-09
    public function testSellerOwnListingViewIsNotSaved(): void
    {
        $sellerId = $this->createUser(true);
        $listingId = $this->createListing($sellerId, $this->createCategory());

        $this->assertFalse(recordListingView($this->pdo, $sellerId, $listingId));
        $this->assertCount(0, $this->listingViewsFor($sellerId));
    }

    // TC-25-10
    public function testInactiveListingsDoNotGenerateViews(): void
    {
        $sellerId = $this->createUser(true);
        $buyerId = $this->createUser();
        $listingId = $this->createListing($sellerId, $this->createCategory());
        $setStatus = $this->pdo->prepare(
            'UPDATE listings SET listing_status = ? WHERE listing_id = ?'
        );

        foreach (['sold', 'hidden', 'archived', 'draft'] as $status) {
            $setStatus->execute([$status, $listingId]);
            $this->assertFalse(
                recordListingView($this->pdo, $buyerId, $listingId),
                "Unexpected view inserted for {$status} listing."
            );
        }
        $this->assertCount(0, $this->listingViewsFor($buyerId));
    }

    // TC-25-11
    public function testInactiveSellerListingsDoNotGenerateViews(): void
    {
        $sellerId = $this->createUser(true);
        $buyerId = $this->createUser();
        $listingId = $this->createListing($sellerId, $this->createCategory());
        $updateSeller = $this->pdo->prepare(
            'UPDATE users SET account_status = ? WHERE user_id = ?'
        );

        foreach (['suspended', 'restricted'] as $status) {
            $updateSeller->execute([$status, $sellerId]);
            $this->assertFalse(
                recordListingView($this->pdo, $buyerId, $listingId),
                "Unexpected view inserted for {$status} seller."
            );
        }
        $this->assertCount(0, $this->listingViewsFor($buyerId));

        $updateSeller->execute(['active', $sellerId]);
        $this->assertTrue(recordListingView($this->pdo, $buyerId, $listingId));
    }

    // TC-25-12
    public function testListingViewsAreIndependentByBuyerAndListing(): void
    {
        $sellerId = $this->createUser(true);
        $buyerA = $this->createUser();
        $buyerB = $this->createUser();
        $categoryId = $this->createCategory();
        $listingA = $this->createListing($sellerId, $categoryId);
        $listingB = $this->createListing($sellerId, $categoryId);

        $this->assertTrue(recordListingView($this->pdo, $buyerA, $listingA));
        $this->assertTrue(recordListingView($this->pdo, $buyerA, $listingB));
        $this->assertTrue(recordListingView($this->pdo, $buyerB, $listingA));

        $this->assertCount(2, $this->listingViewsFor($buyerA));
        $this->assertCount(1, $this->listingViewsFor($buyerB));
        $this->assertSame($listingA, (int) $this->listingViewsFor($buyerB)[0]['listing_id']);
    }

    // TC-25-13
    public function testInvalidListingViewIdentifiersAreRejected(): void
    {
        $sellerId = $this->createUser(true);
        $buyerId = $this->createUser();
        $listingId = $this->createListing($sellerId, $this->createCategory());

        $this->assertFalse(recordListingView($this->pdo, 0, $listingId));
        $this->assertFalse(recordListingView($this->pdo, $buyerId, 0));
        $this->assertFalse(recordListingView($this->pdo, $buyerId, PHP_INT_MAX));
        $this->assertCount(0, $this->listingViewsFor($buyerId));
    }

    // TC-25-14
    public function testListingViewWriteErrorIsHandledWithoutThrowing(): void
    {
        $this->assertFalse(recordListingView(new FailingActivityPdo(), 1, 1));
    }

    // =========================================
    // TRANSACTION-SCOPED FIXTURE HELPERS
    // =========================================

    private function createUser(bool $withSellerProfile = false): int
    {
        $suffix = bin2hex(random_bytes(8));
        $this->pdo->prepare(<<<'SQL'
            INSERT INTO users (
                first_name, last_name, username, email,
                password_hash, phone_number, birth_date, is_email_verified
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        SQL)->execute([
            'Activity', 'Fixture', "activity_{$suffix}",
            "activity_{$suffix}@example.test",
            password_hash('ActivityTestOnly!', PASSWORD_BCRYPT),
            '555-0000', '2000-01-01', 1,
        ]);
        $userId = (int) $this->pdo->lastInsertId();

        if ($withSellerProfile) {
            $this->pdo->prepare(<<<'SQL'
                INSERT INTO seller_profiles (
                    user_id, store_name, payout_account_reference
                ) VALUES (?, ?, ?)
            SQL)->execute([$userId, "Activity Store {$suffix}", "activity_{$suffix}"]);
        }

        return $userId;
    }

    private function createCategory(): int
    {
        $this->pdo->prepare(
            'INSERT INTO categories (category_name) VALUES (?)'
        )->execute(['Activity Category ' . bin2hex(random_bytes(8))]);

        return (int) $this->pdo->lastInsertId();
    }

    private function createListing(int $sellerId, int $categoryId): int
    {
        $this->pdo->prepare(<<<'SQL'
            INSERT INTO listings (
                seller_user_id, category_id, item_name, item_description,
                sale_type, fulfillment_type, price, `condition`,
                quantity, listing_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL)->execute([
            $sellerId, $categoryId,
            'Activity Listing ' . bin2hex(random_bytes(8)),
            'Temporary activity tracking fixture',
            'fixed_price', 'shipping', 25.00, 'good', 1, 'active',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function searchHistoryFor(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT user_id, search_query, search_type, searched_at '
            . 'FROM search_history WHERE user_id = ? ORDER BY search_history_id ASC'
        );
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function listingViewsFor(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT user_id, listing_id, viewed_at '
            . 'FROM listing_views WHERE user_id = ? ORDER BY listing_view_id ASC'
        );
        $statement->execute([$userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}

/** Simulate a PDO write failure without altering production tables. */
final class FailingActivityPdo extends PDO
{
    public function __construct()
    {
        // No database connection is needed for this intentional failure.
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        throw new PDOException('Simulated marketplace activity write failure');
    }
}
