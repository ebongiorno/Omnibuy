<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use PDO;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/recommendations.php';

/**
 * UC-16: Category-based homepage recommendation integration tests.
 *
 * Fixtures and activity are created on one PDO connection inside an InnoDB
 * transaction. No HTTP requests, schema changes or external connections are
 * made by these tests; tearDown() rolls back everything the test inserted.
 */
final class PersonalizedRecommendationsTest extends TestCase
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

    // TC-16-05: Listing views receive three points; searches receive two.
    public function testCategoryScoresCombineViewAndSearchWeights(): void
    {
        $buyer = $this->createUser();
        $seller = $this->createUser(true);
        $category = $this->createCategory();
        $term = $this->uniqueTerm();
        $listing = $this->createListing($seller, $category, $term . ' item');

        $this->addView($buyer, $listing);
        $this->addView($buyer, $listing);
        $this->addSearch($buyer, $term);

        $scores = recommendationCategoryScores($this->pdo, $buyer);
        $this->assertSame(8, $scores[$category] ?? null);
    }

    // TC-16-06: One search adds only two points per category, not per listing.
    public function testOneSearchContributesOncePerMatchingRootCategory(): void
    {
        $buyer = $this->createUser();
        $seller = $this->createUser(true);
        $category = $this->createCategory();
        $term = $this->uniqueTerm();

        $this->createListing($seller, $category, $term . ' first');
        $this->createListing($seller, $category, $term . ' second');
        $this->addSearch($buyer, $term);

        $this->assertSame(2, recommendationCategoryScores($this->pdo, $buyer)[$category] ?? null);
    }

    // TC-16-07: Subcategory signals roll up to their immediate parent.
    public function testSubcategoryActivityAccumulatesUnderParentCategory(): void
    {
        $buyer = $this->createUser();
        $seller = $this->createUser(true);
        $parent = $this->createCategory();
        $child = $this->createCategory($parent);
        $term = $this->uniqueTerm();
        $listing = $this->createListing($seller, $child, $term . ' product');

        $this->addView($buyer, $listing);
        $this->addSearch($buyer, $term);

        $scores = recommendationCategoryScores($this->pdo, $buyer);
        $this->assertSame(5, $scores[$parent] ?? null);
        $this->assertArrayNotHasKey($child, $scores);
    }

    // TC-16-08: Other users' activity does not affect this user's scores.
    public function testCategoryScoresAreIsolatedPerUser(): void
    {
        $buyerA = $this->createUser();
        $buyerB = $this->createUser();
        $seller = $this->createUser(true);
        $categoryA = $this->createCategory();
        $categoryB = $this->createCategory();
        $listingA = $this->createListing($seller, $categoryA);
        $listingB = $this->createListing($seller, $categoryB);

        $this->addView($buyerA, $listingA);
        $this->addView($buyerB, $listingB);

        $scoresA = recommendationCategoryScores($this->pdo, $buyerA);
        $scoresB = recommendationCategoryScores($this->pdo, $buyerB);
        $this->assertSame([$categoryA => 3], $scoresA);
        $this->assertSame([$categoryB => 3], $scoresB);
        $this->assertSame([], recommendationCategoryScores($this->pdo, 0));
    }

    // TC-16-09: Ignore old activity, seller-profile searches and own listings.
    public function testIrrelevantOrExpiredActivityDoesNotIncreaseScores(): void
    {
        $buyer = $this->createUser(true);
        $otherSeller = $this->createUser(true);
        $category = $this->createCategory();
        $term = $this->uniqueTerm();
        $otherListing = $this->createListing($otherSeller, $category, $term . ' eligible');
        $ownListing = $this->createListing($buyer, $category, 'owned item');

        $this->addSearch($buyer, $term, 'seller_profiles');
        $this->addSearch($buyer, $term, 'items', 91);
        $this->addView($buyer, $otherListing, 91);
        // Raw historical activity may contain an own-listing view; it must be ignored.
        $this->addView($buyer, $ownListing);

        $this->assertSame([], recommendationCategoryScores($this->pdo, $buyer));
    }

    // TC-16-10: Activity from unavailable listings / inactive sellers is ignored.
    public function testInactiveListingAndSellerActivityIsExcludedFromScores(): void
    {
        $buyer = $this->createUser();
        $seller = $this->createUser(true);
        $category = $this->createCategory();
        $term = $this->uniqueTerm();
        $listing = $this->createListing($seller, $category, $term . ' restricted');
        $this->addView($buyer, $listing);
        $this->addSearch($buyer, $term);

        $this->pdo->prepare("UPDATE listings SET listing_status = 'sold' WHERE listing_id = ?")
            ->execute([$listing]);
        $this->assertSame([], recommendationCategoryScores($this->pdo, $buyer));

        $this->pdo->prepare("UPDATE listings SET listing_status = 'active' WHERE listing_id = ?")
            ->execute([$listing]);
        $this->pdo->prepare("UPDATE users SET account_status = 'suspended' WHERE user_id = ?")
            ->execute([$seller]);
        $this->assertSame([], recommendationCategoryScores($this->pdo, $buyer));
    }

    // TC-16-11: Relevance selects the candidate set; newest sorts within it.
    public function testRecentFeedPrioritizesHigherScoringCategories(): void
    {
        $buyer = $this->createUser();
        $seller = $this->createUser(true);
        $strong = $this->createCategory();
        $weak = $this->createCategory();
        $none = $this->createCategory();
        $strongListing = $this->createListing($seller, $strong);
        $weakListing = $this->createListing($seller, $weak);
        $unscoredListing = $this->createListing($seller, $none);

        $this->addView($buyer, $strongListing);
        $this->addView($buyer, $strongListing);
        $this->addView($buyer, $weakListing);
        $this->setListingTime($strongListing, '-3 days');
        $this->setListingTime($weakListing, '-2 days');
        $this->setListingTime($unscoredListing, '+1 day');

        // Only the two highest-scoring listings are selected, even though
        // an unrelated listing has a more recent timestamp.
        $recent = $this->listingIds(getPersonalizedRecommendations($this->pdo, $buyer, 2, 'recent'));
        $this->assertSame([$weakListing, $strongListing], $recent);
        $this->assertNotContains($unscoredListing, $recent);
    }

    // TC-16-12: Recency and listing ID break ties deterministically.
    public function testEqualScoresUseRenewalAndListingIdTieBreakers(): void
    {
        $buyer = $this->createUser();
        $seller = $this->createUser(true);
        $category = $this->createCategory();
        $older = $this->createListing($seller, $category);
        $newer = $this->createListing($seller, $category);
        $renewed = $this->createListing($seller, $category);
        $this->addView($buyer, $older);
        $this->setListingTime($older, '-4 days');
        $this->setListingTime($newer, '-1 day');
        $this->setListingTime($renewed, '-6 days', '+1 day');

        $ids = $this->listingIds(getPersonalizedRecommendations($this->pdo, $buyer, 50));
        $this->assertSame([$renewed, $newer, $older], array_values(array_intersect($ids, [$older, $newer, $renewed])));

        // Same category, equal effective time: higher ID comes first.
        $sameTime = gmdate('Y-m-d H:i:s', time() + 172800);
        $this->pdo->prepare('UPDATE listings SET created_at = ?, renewed_at = NULL WHERE listing_id IN (?, ?)')
            ->execute([$sameTime, $older, $newer]);
        $ids = $this->listingIds(getPersonalizedRecommendations($this->pdo, $buyer, 50));
        $this->assertLessThan(array_search($older, $ids, true), array_search($newer, $ids, true));
    }

    // TC-16-13: Guests and users without interests sort the full catalog.
    public function testGuestAndNewUserReceiveNewestEligibleFallback(): void
    {
        $newUser = $this->createUser();
        $seller = $this->createUser(true);
        $category = $this->createCategory();
        $listing = $this->createListing($seller, $category);
        $this->setListingTime($listing, '+2 days');

        $guest = getPersonalizedRecommendations($this->pdo, null, 10);
        $new = getPersonalizedRecommendations($this->pdo, $newUser, 10);
        $this->assertSame($listing, (int) $guest[0]['listing_id']);
        $this->assertSame($listing, (int) $new[0]['listing_id']);

        // Add old extreme-price listings and 11 newer normal-price listings.
        // The extremes must not be in the newest ten, but must be eligible
        // when guests sort the entire catalog by price BEFORE applying LIMIT.
        $cheap = $this->createListing($seller, $category, price: 0.01);
        $expensive = $this->createListing($seller, $category, price: 99999999.99);
        $this->setListingTime($cheap, '-20 days');
        $this->setListingTime($expensive, '-20 days');
        for ($i = 0; $i < 11; $i++) {
            $newListing = $this->createListing($seller, $category, price: 25.0);
            $this->setListingTime($newListing, '+3 days');
        }

        $newestIds = $this->listingIds(getPersonalizedRecommendations($this->pdo, null, 10, 'recent'));
        $lowIds = $this->listingIds(getPersonalizedRecommendations($this->pdo, null, 10, 'price_low'));
        $highIds = $this->listingIds(getPersonalizedRecommendations($this->pdo, null, 10, 'price_high'));
        $this->assertNotContains($cheap, $newestIds);
        $this->assertNotContains($expensive, $newestIds);
        $this->assertContains($cheap, $lowIds);
        $this->assertContains($expensive, $highIds);

        // A new authenticated user has no category scores, so all three
        // sorting choices behave like a guest's full-catalog sorting.
        $newestMember = $this->listingIds(getPersonalizedRecommendations($this->pdo, $newUser, 10, 'recent'));
        $cheapMember = $this->listingIds(getPersonalizedRecommendations($this->pdo, $newUser, 10, 'price_low'));
        $expensiveMember = $this->listingIds(getPersonalizedRecommendations($this->pdo, $newUser, 10, 'price_high'));
        $this->assertSame($newestIds, $newestMember);
        $this->assertSame($lowIds, $cheapMember);
        $this->assertSame($highIds, $expensiveMember);
        $this->assertContains($cheap, $cheapMember);
        $this->assertContains($expensive, $expensiveMember);

        // A search with no matching eligible category still provides no
        // meaningful personalization signal, so full-catalog sorting remains.
        $this->addSearch($newUser, $this->uniqueTerm());
        $this->assertSame(
            $lowIds,
            $this->listingIds(getPersonalizedRecommendations($this->pdo, $newUser, 10, 'price_low'))
        );

        // Once the member has an eligible listing view, category interests
        // exist and the same ten relevance-selected listings are retained
        // while sorting their prices.
        $this->addView($newUser, $listing);
        $personalizedNewest = $this->listingIds(getPersonalizedRecommendations($this->pdo, $newUser, 10, 'recent'));
        $personalizedLow = $this->listingIds(getPersonalizedRecommendations($this->pdo, $newUser, 10, 'price_low'));
        $personalizedHigh = $this->listingIds(getPersonalizedRecommendations($this->pdo, $newUser, 10, 'price_high'));
        $this->assertEqualsCanonicalizing($personalizedNewest, $personalizedLow);
        $this->assertEqualsCanonicalizing($personalizedNewest, $personalizedHigh);
        $this->assertNotContains($cheap, $personalizedLow);
        $this->assertNotContains($expensive, $personalizedHigh);
    }

    // TC-16-14: Own listings are excluded only for logged-in viewers.
    public function testAuthenticatedFeedExcludesViewerOwnedListings(): void
    {
        $sellerViewer = $this->createUser(true);
        $otherSeller = $this->createUser(true);
        $category = $this->createCategory();
        $ownedListing = $this->createListing($sellerViewer, $category);
        $otherListing = $this->createListing($otherSeller, $category);
        $this->setListingTime($ownedListing, '+2 days');
        $this->setListingTime($otherListing, '+1 day');

        $memberIds = $this->listingIds(getPersonalizedRecommendations($this->pdo, $sellerViewer, 50));
        $guestIds = $this->listingIds(getPersonalizedRecommendations($this->pdo, null, 50));
        $this->assertNotContains($ownedListing, $memberIds);
        $this->assertContains($otherListing, $memberIds);
        $this->assertContains($ownedListing, $guestIds);
    }

    // TC-16-15: All inactive listing statuses and sellers are excluded.
    public function testFeedExcludesUnavailableListingsAndInactiveSellers(): void
    {
        $buyer = $this->createUser();
        $seller = $this->createUser(true);
        $category = $this->createCategory();
        $active = $this->createListing($seller, $category);
        $this->setListingTime($active, '+2 days');
        $excluded = [];
        foreach (['sold', 'hidden', 'archived', 'draft'] as $status) {
            $id = $this->createListing($seller, $category);
            $this->pdo->prepare('UPDATE listings SET listing_status = ? WHERE listing_id = ?')
                ->execute([$status, $id]);
            $excluded[] = $id;
        }
        $inactiveSeller = $this->createUser(true);
        $inactiveListing = $this->createListing($inactiveSeller, $category);
        $this->pdo->prepare("UPDATE users SET account_status = 'restricted' WHERE user_id = ?")
            ->execute([$inactiveSeller]);
        $excluded[] = $inactiveListing;

        $ids = $this->listingIds(getPersonalizedRecommendations($this->pdo, $buyer, 50));
        $this->assertContains($active, $ids);
        foreach ($excluded as $id) {
            $this->assertNotContains($id, $ids);
        }
    }

    // TC-16-16: Price sorts reorder the same relevance-selected listings.
    public function testPriceSortOverridesRecommendationRelevance(): void
    {
        $buyer = $this->createUser();
        $seller = $this->createUser(true);
        $category = $this->createCategory();
        $cheap = $this->createListing($seller, $category, price: 0.01);
        $expensive = $this->createListing($seller, $category, price: 999999.99);
        $this->addView($buyer, $cheap);
        $this->addView($buyer, $expensive);
        $this->setListingTime($cheap, '+1 day');
        $this->setListingTime($expensive, '+2 days');

        // The two viewed listings are the top two relevance candidates;
        // all sort modes must return the same IDs, only in a different order.
        $recent = $this->listingIds(getPersonalizedRecommendations($this->pdo, $buyer, 2, 'recent'));
        $ascending = $this->listingIds(getPersonalizedRecommendations($this->pdo, $buyer, 2, 'price_low'));
        $descending = $this->listingIds(getPersonalizedRecommendations($this->pdo, $buyer, 2, 'price_high'));
        $this->assertSame([$expensive, $cheap], $recent);
        $this->assertSame([$cheap, $expensive], $ascending);
        $this->assertSame([$expensive, $cheap], $descending);
        $this->assertEqualsCanonicalizing($recent, $ascending);
        $this->assertEqualsCanonicalizing($recent, $descending);
    }

    // TC-16-17: Invalid sort falls back safely; limits remain bounded.
    public function testInvalidSortAndLimitValuesFallBackSafely(): void
    {
        $seller = $this->createUser(true);
        $listing = $this->createListing($seller, $this->createCategory());
        $this->setListingTime($listing, '+2 days');

        $one = getPersonalizedRecommendations($this->pdo, null, 0, 'invalid_sort');
        $this->assertCount(1, $one);
        $this->assertSame($listing, (int) $one[0]['listing_id']);
        $this->assertLessThanOrEqual(50, count(getPersonalizedRecommendations($this->pdo, null, 500)));
    }

    // TC-16-18: No duplicate listing IDs; correct fields for homepage cards.
    public function testResultsContainUniqueListingsAndHomepageCardFields(): void
    {
        $seller = $this->createUser(true);
        $category = $this->createCategory();
        $listing = $this->createListing($seller, $category);
        $this->setListingTime($listing, '+2 days');
        $this->pdo->prepare('INSERT INTO listing_images (listing_id, image_url, display_order) VALUES (?, ?, 1)')
            ->execute([$listing, '/uploads/fixture-' . bin2hex(random_bytes(6)) . '.png']);

        $rows = getPersonalizedRecommendations($this->pdo, null, 50);
        $ids = $this->listingIds($rows);
        $this->assertCount(count(array_unique($ids)), $ids);
        $row = $rows[array_search($listing, $ids, true)];
        foreach (['listing_id', 'condition', 'item_name', 'price', 'category_id', 'fulfillment_type', 'image_url', 'category_name'] as $column) {
            $this->assertArrayHasKey($column, $row);
        }
        $this->assertStringStartsWith('/uploads/fixture-', $row['image_url']);
    }

    // =========================================
    // TRANSACTION-SCOPED DATABASE FIXTURES
    // =========================================

    private function createUser(bool $seller = false): int
    {
        $suffix = bin2hex(random_bytes(8));
        $this->pdo->prepare(<<<'SQL'
            INSERT INTO users (
                first_name, last_name, username, email, password_hash,
                phone_number, birth_date, is_email_verified
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        SQL)->execute([
            'Recommendation', 'Fixture', "recommend_{$suffix}",
            "recommend_{$suffix}@example.test",
            password_hash('TestFixtureOnly!', PASSWORD_BCRYPT),
            '555-0000', '2000-01-01', 1,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        if ($seller) {
            $this->pdo->prepare('INSERT INTO seller_profiles (user_id, store_name, payout_account_reference) VALUES (?, ?, ?)')
                ->execute([$id, "Recommendation Store {$suffix}", "recommend_{$suffix}"]);
        }
        return $id;
    }

    private function createCategory(?int $parent = null): int
    {
        $this->pdo->prepare('INSERT INTO categories (parent_category_id, category_name) VALUES (?, ?)')
            ->execute([$parent, 'Recommendation Category ' . bin2hex(random_bytes(8))]);
        return (int) $this->pdo->lastInsertId();
    }

    private function createListing(int $seller, int $category, ?string $name = null, float $price = 25.0): int
    {
        $this->pdo->prepare(<<<'SQL'
            INSERT INTO listings (
                seller_user_id, category_id, item_name, item_description,
                sale_type, fulfillment_type, price, `condition`, quantity, listing_status
            ) VALUES (?, ?, ?, ?, 'fixed_price', 'shipping', ?, 'good', 1, 'active')
        SQL)->execute([
            $seller, $category, $name ?? ('Recommendation Listing ' . bin2hex(random_bytes(8))),
            'Temporary recommendation fixture', $price,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function addSearch(int $buyer, string $term, string $type = 'items', int $daysAgo = 0): void
    {
        $this->pdo->prepare('INSERT INTO search_history (user_id, search_query, search_type, searched_at) VALUES (?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))')
            ->execute([$buyer, $term, $type, $daysAgo]);
    }

    private function addView(int $buyer, int $listing, int $daysAgo = 0): void
    {
        $this->pdo->prepare('INSERT INTO listing_views (user_id, listing_id, viewed_at) VALUES (?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))')
            ->execute([$buyer, $listing, $daysAgo]);
    }

    private function setListingTime(int $id, string $createdOffset, ?string $renewedOffset = null): void
    {
        $created = (new \DateTimeImmutable('now'))->modify($createdOffset)->format('Y-m-d H:i:s');
        $renewed = $renewedOffset !== null
            ? (new \DateTimeImmutable('now'))->modify($renewedOffset)->format('Y-m-d H:i:s')
            : null;
        $this->pdo->prepare('UPDATE listings SET created_at = ?, renewed_at = ? WHERE listing_id = ?')
            ->execute([$created, $renewed, $id]);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function listingIds(array $rows): array
    {
        return array_map(static fn(array $row): int => (int) $row['listing_id'], $rows);
    }

    private function uniqueTerm(): string
    {
        return 'recommendkeyword' . bin2hex(random_bytes(8));
    }
}
