<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use DOMDocument;
use DOMXPath;
use OmniBuy\Tests\Support\DatabaseTestCase;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../Support/TestHelpers.php';

/**
 * UC-25: Listing Details (Phases 1-4).
 *
 * Uses the same HTTP helper and seeded MySQL database as the existing
 * Marketplace Discovery functional tests. Tests are read-only: no seed data
 * or order/review records are inserted or modified.
 *
 * Browser JavaScript behavior (gallery clicks and quantity increments) must
 * still be checked manually or with a browser test; these tests inspect the
 * server-rendered HTML and the database-backed content.
 */
final class ListingDetailTest extends DatabaseTestCase
{
    public function testActiveListingDisplaysItsDatabaseInformation(): void
    {
        $listing = $this->activeListing();
        $html = $this->requestListing((int) $listing['listing_id']);
        $xpath = $this->xpath($html);

        self::assertSame(
            (string) $listing['item_name'],
            trim((string) $xpath->evaluate('string(//*[@id="listing-detail-title"])'))
        );
        self::assertStringContainsString(
            '$' . number_format((float) $listing['price'], 2),
            $html
        );
        self::assertStringContainsString((string) $listing['category_name'], $html);
        self::assertStringContainsString(
            ucwords(str_replace('_', ' ', (string) $listing['condition'])),
            $html
        );
        self::assertStringContainsString('About this item', $html);
        self::assertStringNotContainsString('Listing unavailable', $html);

        $description = trim((string) ($listing['item_description'] ?? ''));
        if ($description !== '') {
            self::assertStringContainsString(
                htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $html,
                'The listing description should be safely encoded as HTML.'
            );
        } else {
            self::assertStringContainsString('No description provided.', $html);
        }
    }

    public function testListingIdSelectsTheCorrectListing(): void
    {
        $first = $this->activeListing();
        $second = $this->fetchOne("
            SELECT l.listing_id, l.item_name
            FROM listings AS l
            INNER JOIN users AS u ON u.user_id = l.seller_user_id
            WHERE l.listing_status = 'active'
              AND u.account_status = 'active'
              AND l.listing_id <> :first_id
            ORDER BY l.listing_id ASC LIMIT 1
        ", ['first_id' => $first['listing_id']]);
        self::assertIsArray($second, 'Two active listings are needed for this test.');

        $firstPage = $this->xpath($this->requestListing((int) $first['listing_id']));
        $secondPage = $this->xpath($this->requestListing((int) $second['listing_id']));

        self::assertSame(
            (string) $first['item_name'],
            trim((string) $firstPage->evaluate('string(//*[@id="listing-detail-title"])'))
        );
        self::assertSame(
            (string) $second['item_name'],
            trim((string) $secondPage->evaluate('string(//*[@id="listing-detail-title"])'))
        );
    }

    public function testMissingListingIdIsRejected(): void
    {
        $html = \httpGet('/pages/listing.php');
        self::assertStringContainsString('Invalid listing ID.', $html);
        self::assertStringContainsString('Listing unavailable', $html);
        self::assertStringNotContainsString('id="listing-detail-title"', $html);
    }

    /** @param array<string, mixed> $parameters */
    #[DataProvider('invalidListingIds')]
    public function testInvalidListingIdIsRejected(array $parameters): void
    {
        $html = \httpGet('/pages/listing.php', $parameters);
        self::assertStringContainsString('Invalid listing ID.', $html);
        self::assertStringNotContainsString('id="listing-detail-title"', $html);
        self::assertStringNotContainsString('SQLSTATE', $html);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidListingIds(): array
    {
        return [
            'letters' => [['id' => 'abc']],
            'zero' => [['id' => '0']],
            'negative' => [['id' => '-1']],
            'decimal' => [['id' => '1.5']],
            'injection-like text' => [['id' => '1 OR 1=1']],
            'array instead of scalar' => [['id' => ['1']]],
        ];
    }

    public function testNonexistentListingIsUnavailable(): void
    {
        $maxId = (int) $this->pdo->query(
            'SELECT COALESCE(MAX(listing_id), 0) FROM listings'
        )->fetchColumn();
        $html = $this->requestListing($maxId + 1);

        self::assertStringContainsString('This listing is not available.', $html);
        self::assertStringContainsString('Listing unavailable', $html);
        self::assertStringNotContainsString('id="listing-detail-title"', $html);
    }

    #[DataProvider('inactiveStatuses')]
    public function testInactiveListingCannotBeOpened(string $status): void
    {
        $listing = $this->fetchOne("
            SELECT listing_id FROM listings
            WHERE listing_status = :status
            ORDER BY listing_id ASC LIMIT 1
        ", ['status' => $status]);

        if ($listing === false) {
            self::markTestSkipped("No {$status} listing exists in the test database.");
        }

        $html = $this->requestListing((int) $listing['listing_id']);
        self::assertStringContainsString('This listing is not available.', $html);
        self::assertStringNotContainsString('id="listing-detail-title"', $html);
    }

    /** @return array<string, array{string}> */
    public static function inactiveStatuses(): array
    {
        return [
            'hidden' => ['hidden'],
            'sold' => ['sold'],
            'archived' => ['archived'],
            'draft' => ['draft'],
        ];
    }

    public function testListingFromInactiveSellerIsUnavailable(): void
    {
        $listing = $this->fetchOne("
            SELECT l.listing_id
            FROM listings AS l
            INNER JOIN users AS u ON u.user_id = l.seller_user_id
            WHERE l.listing_status = 'active' AND u.account_status <> 'active'
            ORDER BY l.listing_id ASC LIMIT 1
        ");
        if ($listing === false) {
            self::markTestSkipped('No active listing belonging to an inactive seller is seeded.');
        }

        $html = $this->requestListing((int) $listing['listing_id']);
        self::assertStringContainsString('This listing is not available.', $html);
    }

    public function testShippingInformationMatchesDatabase(): void
    {
        $listing = $this->activeListing("l.fulfillment_type = 'shipping'");
        $html = $this->requestListing((int) $listing['listing_id']);
        $xpath = $this->xpath($html);
        $delivery = (string) $xpath->evaluate('string(//*[@id="listing-delivery-heading"]/..)');

        self::assertStringContainsString('Shipping', $delivery);
        if ($listing['shipping_cost'] === null) {
            self::assertStringContainsString('Not specified', $delivery);
        } elseif ((float) $listing['shipping_cost'] === 0.0) {
            self::assertStringContainsString('Free', $delivery);
        } else {
            self::assertStringContainsString(
                '$' . number_format((float) $listing['shipping_cost'], 2),
                $delivery
            );
        }
        if ($listing['estimated_shipping_days'] !== null) {
            self::assertStringContainsString(
                (int) $listing['estimated_shipping_days'] . ' day(s)',
                $delivery
            );
        }
    }

    public function testMeetupInformationMatchesDatabaseWithoutExposingCoordinates(): void
    {
        $listing = $this->activeListing("l.fulfillment_type = 'meetup'");
        $html = $this->requestListing((int) $listing['listing_id']);
        $xpath = $this->xpath($html);
        $delivery = (string) $xpath->evaluate('string(//*[@id="listing-delivery-heading"]/..)');

        self::assertStringContainsString('Local meetup', $delivery);
        if (!empty($listing['location_zip_code'])) {
            self::assertStringContainsString((string) $listing['location_zip_code'], $delivery);
        }
        if (!empty($listing['meetup_location_type'])) {
            self::assertStringContainsString(
                ucwords(str_replace('_', ' ', (string) $listing['meetup_location_type'])),
                $delivery
            );
        }
        self::assertStringNotContainsString('latitude', strtolower($html));
        self::assertStringNotContainsString('longitude', strtolower($html));
    }

    public function testSellerCardDisplaysCorrectPublicProfileLink(): void
    {
        $listing = $this->activeListing();
        $xpath = $this->xpath($this->requestListing((int) $listing['listing_id']));
        $sellerLinks = $xpath->query(
            '//a[contains(concat(" ", normalize-space(@class), " "), " listing-detail__seller-name ")]'
        );

        self::assertCount(1, $sellerLinks);
        self::assertSame((string) $listing['store_name'], trim($sellerLinks->item(0)->textContent));
        self::assertSame(
            '/pages/profile.php?id=' . (int) $listing['seller_user_id'],
            $sellerLinks->item(0)->getAttribute('href')
        );
        self::assertStringContainsString(
            '@' . (string) $listing['seller_username'],
            $this->requestListing((int) $listing['listing_id'])
        );
    }

    public function testSellerPrivateAccountFieldsAreNotExposed(): void
    {
        $listing = $this->activeListing();
        $seller = $this->fetchOne("
            SELECT u.email, u.phone_number, u.birth_date, u.password_hash,
                   sp.payout_account_reference
            FROM users AS u
            INNER JOIN seller_profiles AS sp ON sp.user_id = u.user_id
            WHERE u.user_id = :seller_id
        ", ['seller_id' => $listing['seller_user_id']]);
        self::assertIsArray($seller);

        $html = $this->requestListing((int) $listing['listing_id']);
        foreach ($seller as $field => $value) {
            self::assertStringNotContainsString($field, $html);
            if (is_string($value) && $value !== '') {
                self::assertStringNotContainsString(
                    $value,
                    $html,
                    "The public listing page must not disclose {$field}."
                );
            }
        }
    }

    public function testMultipleListingImagesRenderInDatabaseDisplayOrder(): void
    {
        $listing = $this->activeListing('(
            SELECT COUNT(*) FROM listing_images AS li
            WHERE li.listing_id = l.listing_id
        ) >= 2');
        $stmt = $this->pdo->prepare("
            SELECT image_url FROM listing_images
            WHERE listing_id = :listing_id
            ORDER BY display_order ASC, listing_image_id ASC
        ");
        $stmt->execute(['listing_id' => $listing['listing_id']]);
        $imageUrls = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $xpath = $this->xpath($this->requestListing((int) $listing['listing_id']));
        $thumbnails = $xpath->query('//*[@data-gallery-thumbnail]');
        $mainImage = $xpath->query('//*[@data-gallery-main]');

        self::assertCount(count($imageUrls), $thumbnails);
        self::assertCount(1, $mainImage);
        foreach ($imageUrls as $index => $url) {
            $expectedUrl = $this->publicImageUrl((string) $url);
            self::assertSame($expectedUrl, $thumbnails->item($index)->getAttribute('href'));
        }
        self::assertSame(
            $this->publicImageUrl((string) $imageUrls[0]),
            $mainImage->item(0)->getAttribute('src')
        );
        self::assertCount(1, $xpath->query('//*[@data-gallery-prev]'));
        self::assertCount(1, $xpath->query('//*[@data-gallery-next]'));
        self::assertCount(1, $xpath->query('//*[@data-gallery-counter]'));
    }

    public function testSingleImageListingDoesNotNeedGalleryNavigation(): void
    {
        $listing = $this->activeListing('(
            SELECT COUNT(*) FROM listing_images AS li
            WHERE li.listing_id = l.listing_id
        ) = 1');
        $xpath = $this->xpath($this->requestListing((int) $listing['listing_id']));

        self::assertCount(1, $xpath->query('//*[@data-gallery-main]'));
        self::assertCount(0, $xpath->query('//*[@data-gallery-thumbnail]'));
        self::assertCount(0, $xpath->query('//*[@data-gallery-prev]'));
    }

    public function testListingWithoutImagesUsesFallback(): void
    {
        $listing = $this->fetchOne("
            SELECT l.listing_id
            FROM listings AS l
            INNER JOIN users AS u ON u.user_id = l.seller_user_id
            WHERE l.listing_status = 'active' AND u.account_status = 'active'
              AND NOT EXISTS (
                  SELECT 1 FROM listing_images AS li WHERE li.listing_id = l.listing_id
              )
            ORDER BY l.listing_id ASC LIMIT 1
        ");
        if ($listing === false) {
            self::markTestSkipped('All active seeded listings have images; no fallback fixture exists.');
        }

        $xpath = $this->xpath($this->requestListing((int) $listing['listing_id']));
        self::assertSame(
            '/assets/images/listing/listing-default-img.jpg',
            $xpath->evaluate('string(//*[@data-gallery-main]/@src)')
        );
        self::assertStringContainsString(
            'No photos have been uploaded for this listing.',
            $this->requestListing((int) $listing['listing_id'])
        );
    }

    public function testFixedPriceQuantityAndDisabledActionButtons(): void
    {
        $listing = $this->activeListing("l.sale_type = 'fixed_price' AND l.quantity > 0");
        $xpath = $this->xpath($this->requestListing((int) $listing['listing_id']));

        $selectors = $xpath->query('//*[@data-quantity-selector]');
        self::assertCount(1, $selectors);
        self::assertSame(
            (string) $listing['quantity'],
            $selectors->item(0)->getAttribute('data-max-quantity')
        );
        $inputs = $xpath->query('//*[@data-quantity-value]');
        self::assertCount(1, $inputs);
        self::assertSame('1', $inputs->item(0)->getAttribute('min'));
        self::assertSame((string) $listing['quantity'], $inputs->item(0)->getAttribute('max'));
        self::assertSame('1', $inputs->item(0)->getAttribute('value'));

        $this->assertButtonsArePlaceholders($xpath);
    }

    public function testAuctionListingDoesNotShowDirectPurchaseQuantitySelector(): void
    {
        $listing = $this->fetchOne("
            SELECT l.listing_id FROM listings AS l
            INNER JOIN users AS u ON u.user_id = l.seller_user_id
            WHERE l.listing_status = 'active' AND u.account_status = 'active'
              AND l.sale_type = 'auction'
            ORDER BY l.listing_id ASC LIMIT 1
        ");
        if ($listing === false) {
            self::markTestSkipped('No active auction listing exists in seed data.');
        }

        $xpath = $this->xpath($this->requestListing((int) $listing['listing_id']));
        self::assertCount(0, $xpath->query('//*[@data-quantity-selector]'));
        self::assertStringContainsString(
            'Auction listings are not available for direct cart purchase.',
            (string) $xpath->evaluate('string(//*[@id="listing-purchase-heading"]/..)')
        );
        $this->assertButtonsArePlaceholders($xpath);
    }

    public function testListingWithoutReviewsDisplaysEmptyState(): void
    {
        $listing = $this->fetchOne("
            SELECT l.listing_id FROM listings AS l
            INNER JOIN users AS u ON u.user_id = l.seller_user_id
            WHERE l.listing_status = 'active' AND u.account_status = 'active'
              AND NOT EXISTS (
                  SELECT 1 FROM listing_reviews AS lr
                  INNER JOIN order_items AS oi ON oi.order_item_id = lr.order_item_id
                  WHERE oi.listing_id = l.listing_id
              )
            ORDER BY l.listing_id ASC LIMIT 1
        ");
        if ($listing === false) {
            self::markTestSkipped('Every available listing has reviews; no empty-state fixture exists.');
        }

        $html = $this->requestListing((int) $listing['listing_id']);
        self::assertStringContainsString('No reviews for this listing yet.', $html);
        self::assertStringContainsString('No ratings yet', $html);
        self::assertStringNotContainsString('listing-detail__review-item', $html);
    }

    public function testListingReviewsMatchOnlyTheSelectedListingWhenDataExists(): void
    {
        $listing = $this->fetchOne("
            SELECT l.listing_id FROM listings AS l
            INNER JOIN users AS u ON u.user_id = l.seller_user_id
            WHERE l.listing_status = 'active' AND u.account_status = 'active'
              AND EXISTS (
                  SELECT 1 FROM listing_reviews AS lr
                  INNER JOIN order_items AS oi ON oi.order_item_id = lr.order_item_id
                  WHERE oi.listing_id = l.listing_id
              )
            ORDER BY l.listing_id ASC LIMIT 1
        ");
        if ($listing === false) {
            self::markTestSkipped('No listing_reviews rows exist for an active listing.');
        }

        $stmt = $this->pdo->prepare("
            SELECT lr.star_rating, lr.title, lr.description, u.username,
                   u.account_status
            FROM listing_reviews AS lr
            INNER JOIN order_items AS oi ON oi.order_item_id = lr.order_item_id
            INNER JOIN users AS u ON u.user_id = lr.reviewer_user_id
            WHERE oi.listing_id = :listing_id
            ORDER BY lr.created_at DESC, lr.listing_review_id DESC
            LIMIT 10
        ");
        $stmt->execute(['listing_id' => $listing['listing_id']]);
        $expectedReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalStmt = $this->pdo->prepare("
            SELECT COUNT(*) AS n, ROUND(AVG(lr.star_rating), 1) AS avg_rating
            FROM listing_reviews AS lr
            INNER JOIN order_items AS oi ON oi.order_item_id = lr.order_item_id
            WHERE oi.listing_id = :listing_id
        ");
        $totalStmt->execute(['listing_id' => $listing['listing_id']]);
        $total = $totalStmt->fetch(PDO::FETCH_ASSOC);

        $xpath = $this->xpath($this->requestListing((int) $listing['listing_id']));
        $reviewItems = $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " listing-detail__review-item ")]');
        self::assertCount(count($expectedReviews), $reviewItems);
        self::assertStringContainsString(
            number_format((float) $total['avg_rating'], 1) . ' / 5',
            (string) $xpath->evaluate('string(//*[@aria-label="Listing review summary"])')
        );
        self::assertStringContainsString(
            (string) $total['n'] . ' review',
            (string) $xpath->evaluate('string(//*[@aria-label="Listing review summary"])')
        );

        foreach ($expectedReviews as $index => $review) {
            $text = $reviewItems->item($index)->textContent;
            $publicName = $review['account_status'] === 'active'
                ? (string) $review['username'] : 'OmniBuy member';
            self::assertStringContainsString($publicName, $text);
            self::assertStringContainsString((int) $review['star_rating'] . ' / 5', $text);
            if (trim((string) ($review['title'] ?? '')) !== '') {
                self::assertStringContainsString((string) $review['title'], $text);
            }
            if (trim((string) ($review['description'] ?? '')) !== '') {
                self::assertStringContainsString((string) $review['description'], $text);
            }
        }
    }

    public function testSellerAccountRatingIsIndependentOfListingReviewsWhenDataExists(): void
    {
        $listing = $this->fetchOne("
            SELECT l.listing_id, l.seller_user_id
            FROM listings AS l
            INNER JOIN users AS u ON u.user_id = l.seller_user_id
            WHERE l.listing_status = 'active' AND u.account_status = 'active'
              AND EXISTS (
                  SELECT 1 FROM user_reviews AS ur
                  WHERE ur.reviewed_user_id = l.seller_user_id
              )
            ORDER BY l.listing_id ASC LIMIT 1
        ");
        if ($listing === false) {
            self::markTestSkipped('No seller user_reviews are available in seed data.');
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS n, ROUND(AVG(star_rating), 1) AS avg_rating
            FROM user_reviews WHERE reviewed_user_id = :seller_id
        ");
        $stmt->execute(['seller_id' => $listing['seller_user_id']]);
        $rating = $stmt->fetch(PDO::FETCH_ASSOC);
        $xpath = $this->xpath($this->requestListing((int) $listing['listing_id']));
        $sellerRatingText = (string) $xpath->evaluate('string(//*[@aria-label="Seller account rating"])');

        self::assertStringContainsString(
            number_format((float) $rating['avg_rating'], 1) . ' / 5',
            $sellerRatingText
        );
        self::assertStringContainsString('from ' . (int) $rating['n'] . ' user review(s)', $sellerRatingText);
    }

    /** @return array<string, mixed> */
    private function activeListing(string $additionalCondition = '1 = 1'): array
    {
        // The extra SQL is always a hardcoded condition from this test class,
        // never a request parameter or user-supplied value.
        $listing = $this->fetchOne("
            SELECT l.*, c.category_name, sp.store_name, u.username AS seller_username
            FROM listings AS l
            INNER JOIN users AS u ON u.user_id = l.seller_user_id
            INNER JOIN seller_profiles AS sp ON sp.user_id = l.seller_user_id
            LEFT JOIN categories AS c ON c.category_id = l.category_id
            WHERE l.listing_status = 'active' AND u.account_status = 'active'
              AND ({$additionalCondition})
            ORDER BY l.listing_id ASC LIMIT 1
        ");
        self::assertIsArray($listing, 'Expected a seeded active listing matching test criteria.');
        return $listing;
    }

    /** @param array<string, mixed> $params
     *  @return array<string, mixed>|false
     */
    private function fetchOne(string $sql, array $params = []): array|false
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    private function requestListing(int $listingId): string
    {
        return \httpGet('/pages/listing.php', ['id' => (string) $listingId]);
    }

    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8"?>' . $html, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new DOMXPath($document);
    }

    private function publicImageUrl(string $url): string
    {
        if (str_starts_with($url, 'assets/') || str_starts_with($url, 'uploads/')) {
            return '/' . $url;
        }
        return $url;
    }

    private function assertButtonsArePlaceholders(DOMXPath $xpath): void
    {
        $cartButtons = $xpath->query('//*[@data-add-to-cart]');
        $wishlistButtons = $xpath->query('//*[@data-add-to-wishlist]');
        self::assertCount(1, $cartButtons);
        self::assertCount(1, $wishlistButtons);
        self::assertTrue($cartButtons->item(0)->hasAttribute('disabled'));
        self::assertTrue($wishlistButtons->item(0)->hasAttribute('disabled'));
    }
}
