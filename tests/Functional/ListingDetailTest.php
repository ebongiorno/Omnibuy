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
 * Uses the HTTP helper and a disposable Docker test database. Five tests
 * temporarily create committed fixtures (visible to HTTP) and delete them
 * in finally blocks; never run this suite against shared/production data.
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
        $this->withListingFixture(function (PDO $db, array &$created): void {
            $seller = $this->findInactiveSeller($db);
            if ($seller === null) {
                $userId = $this->insertFixtureUser($db, $created, 'suspended');
                $this->insertFixtureSeller($db, $created, $userId);
                $seller = $userId;
            }
            $listingId = $this->insertFixtureListing($db, $created, $seller);
            $html = $this->requestListing($listingId);
            self::assertStringContainsString('This listing is not available.', $html);
            self::assertStringNotContainsString('id="listing-detail-title"', $html);
        });
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
            $this->elementAt($sellerLinks)->getAttribute('href')
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
            self::assertSame($expectedUrl, $this->elementAt($thumbnails, $index)->getAttribute('href'));
        }
        self::assertSame(
            $this->publicImageUrl((string) $imageUrls[0]),
            $this->elementAt($mainImage)->getAttribute('src')
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
        $this->withListingFixture(function (PDO $db, array &$created): void {
            $sellerId = $this->findActiveSeller($db);
            $listingId = $this->insertFixtureListing($db, $created, $sellerId);
            $html = $this->requestListing($listingId);
            $xpath = $this->xpath($html);
            self::assertSame(
                '/assets/images/listing/listing-default-img.jpg',
                $xpath->evaluate('string(//*[@data-gallery-main]/@src)')
            );
            self::assertStringContainsString('No photos have been uploaded for this listing.', $html);
            self::assertCount(0, $xpath->query('//*[@data-gallery-thumbnail]'));
        });
    }

    public function testFixedPriceQuantityAndDisabledActionButtons(): void
    {
        $listing = $this->activeListing("l.sale_type = 'fixed_price' AND l.quantity > 0");
        $xpath = $this->xpath($this->requestListing((int) $listing['listing_id']));

        $selectors = $xpath->query('//*[@data-quantity-selector]');
        self::assertCount(1, $selectors);
        self::assertSame(
            (string) $listing['quantity'],
            $this->elementAt($selectors)->getAttribute('data-max-quantity')
        );
        $inputs = $xpath->query('//*[@data-quantity-value]');
        self::assertCount(1, $inputs);
        self::assertSame('1', $this->elementAt($inputs)->getAttribute('min'));
        self::assertSame((string) $listing['quantity'], $this->elementAt($inputs)->getAttribute('max'));
        self::assertSame('1', $this->elementAt($inputs)->getAttribute('value'));

        $this->assertButtonsArePlaceholders($xpath);
    }

    public function testAuctionListingDoesNotShowDirectPurchaseQuantitySelector(): void
    {
        $this->withListingFixture(function (PDO $db, array &$created): void {
            $sellerId = $this->findActiveSeller($db);
            $listingId = $this->insertFixtureListing($db, $created, $sellerId, 'auction');
            $xpath = $this->xpath($this->requestListing($listingId));
            self::assertCount(0, $xpath->query('//*[@data-quantity-selector]'));
            self::assertStringContainsString(
                'Auction listings are not available for direct cart purchase.',
                (string) $xpath->evaluate('string(//*[@id="listing-purchase-heading"]/..)')
            );
            $this->assertButtonsArePlaceholders($xpath);
        });
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
        $this->withListingFixture(function (PDO $db, array &$created): void {
            $sellerId = $this->findActiveSeller($db);
            $buyerId = $this->findActiveBuyer($db, $sellerId);
            $listingA = $this->insertFixtureListing($db, $created, $sellerId);
            $listingB = $this->insertFixtureListing($db, $created, $sellerId);
            $this->insertFixtureReview($db, $created, $listingA, $buyerId, 2, 'UC25 own review');
            $this->insertFixtureReview($db, $created, $listingB, $buyerId, 5, 'UC25 other listing review');

            $html = $this->requestListing($listingA);
            $xpath = $this->xpath($html);
            self::assertCount(1, $xpath->query('//li[contains(concat(" ", normalize-space(@class), " "), " listing-detail__review-item ")]'));
            self::assertStringContainsString('UC25 own review', $html);
            self::assertStringNotContainsString('UC25 other listing review', $html);
            $summary = (string) $xpath->evaluate('string(//*[@aria-label="Listing review summary"])');
            self::assertStringContainsString('2.0 / 5', $summary);
            self::assertStringContainsString('1 review', $summary);
        });
    }

    public function testSellerAccountRatingIsIndependentOfListingReviewsWhenDataExists(): void
    {
        $this->withListingFixture(function (PDO $db, array &$created): void {
            $sellerId = $this->findActiveSeller($db);
            $buyerId = $this->findActiveBuyer($db, $sellerId);
            $listingId = $this->insertFixtureListing($db, $created, $sellerId);
            $orderItemId = $this->insertFixtureReview($db, $created, $listingId, $buyerId, 2, 'UC25 listing rating');
            $this->insertTracked($db, $created, 'user_reviews', [
                'reviewer_user_id' => $buyerId,
                'reviewed_user_id' => $sellerId,
                'order_item_id' => $orderItemId,
                'star_rating' => 5,
                'title' => 'UC25 seller account rating',
            ]);

            $stmt = $db->prepare('SELECT COUNT(*) AS n, ROUND(AVG(star_rating), 1) AS avg_rating FROM user_reviews WHERE reviewed_user_id = ?');
            $stmt->execute([$sellerId]);
            $expected = $stmt->fetch(PDO::FETCH_ASSOC);
            $xpath = $this->xpath($this->requestListing($listingId));
            $sellerSummary = (string) $xpath->evaluate('string(//*[@aria-label="Seller account rating"])');
            $listingSummary = (string) $xpath->evaluate('string(//*[@aria-label="Listing review summary"])');
            self::assertStringContainsString(number_format((float)$expected['avg_rating'], 1) . ' / 5', $sellerSummary);
            self::assertStringContainsString('from ' . (int)$expected['n'] . ' user review(s)', $sellerSummary);
            self::assertStringContainsString('2.0 / 5', $listingSummary);
            self::assertStringContainsString('1 review', $listingSummary);
        });
    }


    /**
     * HTTP requests use a DIFFERENT MySQL connection from PHPUnit. Test fixtures
     * must therefore be committed before httpGet() can see them. Do not run this
     * class against a shared or production database; use your disposable Docker
     * test database. All inserted rows are deleted in reverse FK order in finally.
     *
     * @param callable(PDO, array):void $callback
     */
    private function withListingFixture(callable $callback): void
    {
        $db = new PDO(sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'db',
            (int) (getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: 'omnibuy'
        ), (string) getenv('DB_USER'), (string) getenv('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $created = [];
        try {
            $callback($db, $created);
        } finally {
            foreach (array_reverse($created) as [$table, $idColumn, $id]) {
                // Table/column identifiers are fixed in insertTracked(), never from users.
                $statement = $db->prepare("DELETE FROM {$table} WHERE {$idColumn} = ?");
                $statement->execute([$id]);
            }
        }
    }

    /** @return int */
    private function insertTracked(PDO $db, array &$created, string $table, array $values): int
    {
        $keys = [
            'users' => 'user_id', 'seller_profiles' => 'user_id',
            'listings' => 'listing_id', 'addresses' => 'address_id',
            'orders' => 'order_id', 'order_items' => 'order_item_id',
            'listing_reviews' => 'listing_review_id', 'user_reviews' => 'user_review_id',
        ];
        if (!isset($keys[$table])) {
            throw new \LogicException('Disallowed test fixture table.');
        }
        foreach (array_keys($values) as $column) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/D', $column)) {
                throw new \LogicException('Invalid test fixture column.');
            }
        }
        $cols = implode(', ', array_map(
            static fn(string $column): string => "`{$column}`",
            array_keys($values)
        ));
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $statement = $db->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$marks})");
        $statement->execute(array_values($values));
        $id = $table === 'seller_profiles' ? (int) $values['user_id'] : (int) $db->lastInsertId();
        $created[] = [$table, $keys[$table], $id];
        return $id;
    }

    private function findActiveSeller(PDO $db): int
    {
        $id = $db->query("SELECT sp.user_id FROM seller_profiles sp JOIN users u ON u.user_id=sp.user_id WHERE u.account_status='active' ORDER BY sp.user_id LIMIT 1")->fetchColumn();
        self::assertNotFalse($id, 'A seeded active seller is required.');
        return (int) $id;
    }

    private function findInactiveSeller(PDO $db): ?int
    {
        $id = $db->query("SELECT sp.user_id FROM seller_profiles sp JOIN users u ON u.user_id=sp.user_id WHERE u.account_status <> 'active' ORDER BY sp.user_id LIMIT 1")->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private function findActiveBuyer(PDO $db, int $sellerId): int
    {
        $statement = $db->prepare("SELECT user_id FROM users WHERE account_status='active' AND user_id <> ? ORDER BY user_id LIMIT 1");
        $statement->execute([$sellerId]);
        $id = $statement->fetchColumn();
        self::assertNotFalse($id, 'A second seeded active user is required.');
        return (int) $id;
    }

    private function insertFixtureUser(PDO $db, array &$created, string $status): int
    {
        $suffix = bin2hex(random_bytes(6));
        return $this->insertTracked($db, $created, 'users', [
            'first_name' => 'UC25', 'last_name' => 'Test',
            'username' => 'uc25_' . $suffix, 'email' => 'uc25_' . $suffix . '@example.test',
            'password_hash' => password_hash('fixture-not-a-real-login', PASSWORD_DEFAULT),
            'phone_number' => '5550000000', 'birth_date' => '2000-01-01',
            'account_status' => $status,
        ]);
    }

    private function insertFixtureSeller(PDO $db, array &$created, int $userId): void
    {
        $this->insertTracked($db, $created, 'seller_profiles', [
            'user_id' => $userId, 'store_name' => 'UC25 test store ' . $userId,
            'payout_account_reference' => 'uc25-fixture-' . $userId,
        ]);
    }

    private function insertFixtureListing(PDO $db, array &$created, int $sellerId, string $saleType = 'fixed_price'): int
    {
        $categoryId = (int) $db->query('SELECT category_id FROM categories ORDER BY category_id LIMIT 1')->fetchColumn();
        self::assertGreaterThan(0, $categoryId, 'A seeded category is required.');
        return $this->insertTracked($db, $created, 'listings', [
            'seller_user_id' => $sellerId, 'category_id' => $categoryId,
            'item_name' => 'UC25 Fixture ' . bin2hex(random_bytes(5)),
            'item_description' => 'Temporary PHPUnit fixture',
            'sale_type' => $saleType, 'fulfillment_type' => 'shipping',
            'shipping_cost' => '0.00', 'estimated_shipping_days' => 3,
            'price' => '10.00', 'condition' => 'good',
            'quantity' => 2, 'listing_status' => 'active',
        ]);
    }

    private function insertFixtureReview(PDO $db, array &$created, int $listingId, int $buyerId, int $stars, string $title): int
    {
        $addressId = $this->insertTracked($db, $created, 'addresses', [
            'user_id' => $buyerId, 'address_line_1' => '1 Test Way',
            'city' => 'Fullerton', 'state_name' => 'CA',
            'zip_code' => '92831', 'country' => 'USA', 'address_type' => 'billing',
        ]);
        $orderId = $this->insertTracked($db, $created, 'orders', [
            'buyer_user_id' => $buyerId, 'billing_address_id' => $addressId,
            'subtotal' => '10.00', 'shipping_cost' => '0.00',
            'tax_amount' => '0.00', 'total_amount' => '10.00',
        ]);
        $itemId = $this->insertTracked($db, $created, 'order_items', [
            'order_id' => $orderId, 'listing_id' => $listingId,
            'quantity' => 1, 'unit_price' => '10.00',
            'fulfillment_type' => 'shipping', 'shipping_cost' => '0.00',
        ]);
        $this->insertTracked($db, $created, 'listing_reviews', [
            'reviewer_user_id' => $buyerId, 'order_item_id' => $itemId,
            'star_rating' => $stars, 'title' => $title,
            'description' => 'Safe fixture review text',
        ]);
        return $itemId;
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

    /**
     * Ensure a queried DOM node is an element before accessing element-only methods.
     */
    private function elementAt(\DOMNodeList $nodes, int $index = 0): \DOMElement
    {
        $element = $nodes->item($index);
        self::assertInstanceOf(
            \DOMElement::class,
            $element,
            'Expected an HTML element at the specified index.'
        );
        return $element;
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
        self::assertTrue($this->elementAt($cartButtons)->hasAttribute('disabled'));
        self::assertTrue($this->elementAt($wishlistButtons)->hasAttribute('disabled'));
    }
}
