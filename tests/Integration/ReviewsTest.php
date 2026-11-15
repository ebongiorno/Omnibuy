<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../../includes/cart.php';
require_once __DIR__ . '/../../includes/reviews.php';

/**
 * Review submission (submitReviews) and display (loadSellerReviews, My
 * Orders flags) against the real database. Purchases go through the real
 * cart and checkout.
 */
final class ReviewsTest extends DatabaseTestCase
{
    private const ADDRESS = [
        'address_line_1' => '800 N State College Blvd',
        'address_line_2' => '',
        'city' => 'Fullerton',
        'state_name' => 'CA',
        'zip_code' => '92831',
        'country' => 'USA',
    ];

    private int $seller;
    private int $buyer;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = $this->uniqueSuffix();
        $this->pdo->prepare('INSERT INTO categories (category_name) VALUES (:name)')
            ->execute(['name' => "Review Category $suffix"]);
        $this->categoryId = (int) $this->pdo->lastInsertId();

        $this->seller = $this->createUser('review_seller');
        $this->buyer = $this->createUser('review_buyer');
    }

    private function createListing(int $sellerId): int
    {
        // Any account can sell: its first listing creates its seller profile.
        $this->pdo->prepare('
            INSERT IGNORE INTO seller_profiles (user_id, store_name, payout_account_reference)
            VALUES (:user_id, :store_name, :payout_reference)
        ')->execute([
            'user_id' => $sellerId,
            'store_name' => 'Review Store ' . $this->uniqueSuffix(),
            'payout_reference' => 'REVIEW_' . $this->uniqueSuffix(),
        ]);

        $this->pdo->prepare('
            INSERT INTO listings (
                seller_user_id, category_id, item_name, sale_type, fulfillment_type,
                shipping_cost, price, `condition`, quantity, listing_status
            ) VALUES (
                :seller_id, :category_id, "Review Test Lamp", "fixed_price", "shipping",
                5.00, 20.00, "good", 5, "active"
            )
        ')->execute(['seller_id' => $sellerId, 'category_id' => $this->categoryId]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Buys one unit and returns the new order_item_id. */
    private function buy(int $buyerId, int $listingId): int
    {
        addToCart($this->pdo, $buyerId, $listingId, 1);
        $result = placeOrder($this->pdo, $buyerId, self::ADDRESS, null);
        self::assertSame([], $result['errors']);

        $stmt = $this->pdo->prepare('SELECT order_item_id FROM order_items WHERE order_id = :id');
        $stmt->execute(['id' => $result['order_id']]);

        return (int) $stmt->fetchColumn();
    }

    private static function review(?int $rating, string $title = '', string $description = ''): array
    {
        return ['rating' => $rating, 'title' => $title, 'description' => $description];
    }

    private function rowCount(string $table, int $orderItemId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE order_item_id = :id");
        $stmt->execute(['id' => $orderItemId]);

        return (int) $stmt->fetchColumn();
    }

    public function testBuyerCanReviewTheItemAndTheSeller(): void
    {
        $listing = $this->createListing($this->seller);
        $orderItem = $this->buy($this->buyer, $listing);

        $result = submitReviews($this->pdo, $this->buyer, $orderItem, [
            'listing' => self::review(4, 'Nice lamp', 'Bright and sturdy.'),
            'seller' => self::review(5, 'Fast shipping'),
        ]);

        self::assertTrue($result['ok'], json_encode($result['errors']));
        self::assertSame(1, $this->rowCount('listing_reviews', $orderItem));
        self::assertSame(1, $this->rowCount('user_reviews', $orderItem));

        $stmt = $this->pdo->prepare('SELECT reviewed_user_id, description FROM user_reviews WHERE order_item_id = :id');
        $stmt->execute(['id' => $orderItem]);
        $row = $stmt->fetch();
        self::assertSame($this->seller, (int) $row['reviewed_user_id']);
        self::assertNull($row['description'], 'Empty text is stored as NULL.');
    }

    public function testSellerReviewIsOptionalAndCanBeAddedLater(): void
    {
        $orderItem = $this->buy($this->buyer, $this->createListing($this->seller));

        self::assertTrue(submitReviews($this->pdo, $this->buyer, $orderItem, [
            'listing' => self::review(3),
            'seller' => self::review(null),
        ])['ok']);
        self::assertSame(0, $this->rowCount('user_reviews', $orderItem));

        // Coming back later: the item part is ignored, the seller part is saved.
        self::assertTrue(submitReviews($this->pdo, $this->buyer, $orderItem, [
            'listing' => self::review(1),
            'seller' => self::review(5),
        ])['ok']);
        self::assertSame(1, $this->rowCount('listing_reviews', $orderItem));
        self::assertSame(1, $this->rowCount('user_reviews', $orderItem));

        $item = loadReviewableItem($this->pdo, $this->buyer, $orderItem);
        self::assertSame(3, (int) $item['listing_rating'], 'The first item review is kept.');
        self::assertSame('You\'ve already reviewed this purchase.', reviewProblem($item, $this->buyer));
    }

    public function testAtLeastOneRatingIsRequired(): void
    {
        $orderItem = $this->buy($this->buyer, $this->createListing($this->seller));

        $result = submitReviews($this->pdo, $this->buyer, $orderItem, [
            'listing' => self::review(null),
            'seller' => self::review(null),
        ]);

        self::assertFalse($result['ok']);
        self::assertArrayHasKey('form', $result['errors']);
    }

    public function testWrittenReviewWithoutStarsIsRejected(): void
    {
        $orderItem = $this->buy($this->buyer, $this->createListing($this->seller));

        $result = submitReviews($this->pdo, $this->buyer, $orderItem, [
            'listing' => self::review(null, 'Great'),
            'seller' => self::review(null),
        ]);

        self::assertFalse($result['ok']);
        self::assertArrayHasKey('listing_rating', $result['errors']);
        self::assertSame(0, $this->rowCount('listing_reviews', $orderItem));
    }

    public function testTooLongTitleAndTextAreRejected(): void
    {
        $orderItem = $this->buy($this->buyer, $this->createListing($this->seller));

        $result = submitReviews($this->pdo, $this->buyer, $orderItem, [
            'listing' => self::review(4, str_repeat('a', REVIEW_TITLE_MAX_LENGTH + 1)),
            'seller' => self::review(4, '', str_repeat('b', REVIEW_TEXT_MAX_LENGTH + 1)),
        ]);

        self::assertFalse($result['ok']);
        self::assertArrayHasKey('listing_title', $result['errors']);
        self::assertArrayHasKey('seller_description', $result['errors']);
        self::assertSame(0, $this->rowCount('listing_reviews', $orderItem), 'Nothing is saved when any part is invalid.');
    }

    public function testRatingsOutsideOneToFiveAreNotAccepted(): void
    {
        foreach (['0', '6', '4.5', 'five', ''] as $raw) {
            self::assertNull(normalizeReviewInput(['listing_rating' => $raw], 'listing')['rating'], "Rating '$raw'");
        }
        self::assertSame(5, normalizeReviewInput(['listing_rating' => '5'], 'listing')['rating']);
    }

    public function testOnlyTheBuyerCanReviewTheirPurchase(): void
    {
        $orderItem = $this->buy($this->buyer, $this->createListing($this->seller));
        $stranger = $this->createUser('review_stranger');

        self::assertNull(loadReviewableItem($this->pdo, $stranger, $orderItem));
        $result = submitReviews($this->pdo, $stranger, $orderItem, ['listing' => self::review(1), 'seller' => self::review(1)]);

        self::assertFalse($result['ok']);
        self::assertSame(0, $this->rowCount('listing_reviews', $orderItem));
        // The seller of the item isn't its buyer either.
        self::assertNull(loadReviewableItem($this->pdo, $this->seller, $orderItem));
    }

    public function testCancelledItemsCantBeReviewed(): void
    {
        $orderItem = $this->buy($this->buyer, $this->createListing($this->seller));
        $this->pdo->prepare("UPDATE order_items SET item_status = 'cancelled' WHERE order_item_id = :id")
            ->execute(['id' => $orderItem]);

        $result = submitReviews($this->pdo, $this->buyer, $orderItem, ['listing' => self::review(5), 'seller' => self::review(null)]);

        self::assertFalse($result['ok']);
        self::assertStringContainsString('Cancelled', $result['errors']['form']);
    }

    public function testTheSameAccountCanBeReviewedAsASellerAndReviewOthersAsABuyer(): void
    {
        // One account type: each user sells to the other.
        $fromSeller = $this->buy($this->buyer, $this->createListing($this->seller));
        $fromBuyer = $this->buy($this->seller, $this->createListing($this->buyer));

        submitReviews($this->pdo, $this->buyer, $fromSeller, ['listing' => self::review(4), 'seller' => self::review(4)]);
        submitReviews($this->pdo, $this->seller, $fromBuyer, ['listing' => self::review(2), 'seller' => self::review(2)]);

        $sellerReviews = loadSellerReviews($this->pdo, $this->seller);
        $buyerReviews = loadSellerReviews($this->pdo, $this->buyer);
        self::assertSame(1, $sellerReviews['review_count']);
        self::assertSame(4.0, $sellerReviews['average_rating']);
        self::assertSame(1, $buyerReviews['review_count']);
        self::assertSame(2.0, $buyerReviews['average_rating']);
    }

    public function testProfileReviewsAverageAndHideInactiveReviewers(): void
    {
        $listing = $this->createListing($this->seller);
        $other = $this->createUser('review_other');
        submitReviews($this->pdo, $this->buyer, $this->buy($this->buyer, $listing), ['listing' => self::review(null), 'seller' => self::review(5, 'Great')]);
        submitReviews($this->pdo, $other, $this->buy($other, $listing), ['listing' => self::review(null), 'seller' => self::review(2)]);
        $this->pdo->prepare("UPDATE users SET account_status = 'deleted' WHERE user_id = :id")->execute(['id' => $other]);

        $reviews = loadSellerReviews($this->pdo, $this->seller);

        self::assertSame(2, $reviews['review_count']);
        self::assertSame(3.5, $reviews['average_rating']);
        $names = array_column($reviews['reviews'], 'reviewer_username');
        self::assertContains(null, $names, 'A deleted reviewer is shown anonymously.');
        self::assertCount(1, array_filter($names));
    }

    public function testMyOrdersKnowsWhichItemsAreReviewed(): void
    {
        $orderItem = $this->buy($this->buyer, $this->createListing($this->seller));
        submitReviews($this->pdo, $this->buyer, $orderItem, ['listing' => self::review(5), 'seller' => self::review(null)]);

        $item = loadBuyerOrders($this->pdo, $this->buyer)[0]['items'][0];

        self::assertTrue((bool) $item['has_listing_review']);
        self::assertFalse((bool) $item['has_seller_review']);
    }

    public function testDatabaseAllowsOnlyOneReviewOfEachKindPerPurchase(): void
    {
        $orderItem = $this->buy($this->buyer, $this->createListing($this->seller));
        submitReviews($this->pdo, $this->buyer, $orderItem, ['listing' => self::review(5), 'seller' => self::review(null)]);

        $this->assertRejectedByDatabase(
            'INSERT INTO listing_reviews (reviewer_user_id, order_item_id, star_rating) VALUES (:reviewer, :item, 1)',
            ['reviewer' => $this->buyer, 'item' => $orderItem],
            'A second listing review for the same purchase must be rejected.'
        );
    }
}
