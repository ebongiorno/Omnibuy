<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Security;

use DOMDocument;
use DOMXPath;
use OmniBuy\Tests\Support\DatabaseTestCase;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../Support/TestHelpers.php';

/**
 * MD-SEC-05: Public discovery must exclude listings owned by non-active sellers.
 *
 * HTTP runs on a separate database connection, so these fixtures use committed
 * inserts/updates. Run ONLY against a disposable Docker test database, never
 * shared development, staging, or production data. All created rows are tracked
 * and removed in reverse foreign-key order in finally, including on failures.
 */
final class MarketplaceSuspendedSellerVisibilityTest extends DatabaseTestCase
{
    /** @return array<string, array{string, string}> */
    public static function homepageSorts(): array
    {
        return [
            'newest' => ['recent', '19.99'],
            'price low to high' => ['price_low', '1.00'],
            'price high to low' => ['price_high', '9999.00'],
        ];
    }

    #[DataProvider('homepageSorts')]
    public function testHomepageExcludesSuspendedSellerBeforeSortingAndLimit(string $sort, string $price): void
    {
        $this->withFixture('suspended', $price, function (PDO $db, array $fixture) use ($sort): void {
            $inactiveListingId = $fixture['inactive_listing_id'];

            // Prove the fixture is eligible for the top 10 before suspension.
            // Only the temporary user created by this test is ever updated.
            $this->setFixtureSellerStatus($db, $fixture['inactive_user_id'], 'active');
            try {
                $before = \httpGet('/index.php', ['sort' => $sort]);
                self::assertTrue(
                    $this->containsListingLink($before, $inactiveListingId),
                    'Control: fixture must appear in the top 10 before suspension; otherwise this sorting test is inconclusive.'
                );
            } finally {
                $this->setFixtureSellerStatus($db, $fixture['inactive_user_id'], 'suspended');
            }

            $after = \httpGet('/index.php', ['sort' => $sort]);
            self::assertFalse(
                $this->containsListingLink($after, $inactiveListingId),
                'Homepage must not show a suspended seller listing for sort=' . $sort
            );
            self::assertStringNotContainsString('Unable to display feed', $after);
        });
    }

    public function testExactItemSearchExcludesSuspendedSellerButShowsActiveSeller(): void
    {
        $this->withFixture('suspended', '19.99', function (PDO $db, array $fixture): void {
            $html = $this->searchItems(['q' => $fixture['prefix']]);
            $this->assertOnlyActiveFixtureIsShown($html, $fixture);
            self::assertSame(1, $this->displayedResultCount($html));
        });
    }

    public function testBrowseAllExcludesSuspendedSellerAndCountMatchesEligibleListings(): void
    {
        $this->withFixture('suspended', '19.99', function (PDO $db, array $fixture): void {
            $html = $this->searchItems(['browse' => 'all']);
            self::assertFalse($this->containsListingLink($html, $fixture['inactive_listing_id']));
            self::assertTrue($this->containsListingLink($html, $fixture['active_listing_id']));
            $eligibleCount = (int) $db->query(<<<'SQL'
                SELECT COUNT(*) FROM listings AS l
                INNER JOIN users AS u ON u.user_id = l.seller_user_id
                WHERE l.listing_status = 'active' AND u.account_status = 'active'
                SQL)->fetchColumn();
            self::assertSame($eligibleCount, $this->displayedResultCount($html));
        });
    }

    public function testCategoryFilterExcludesSuspendedSeller(): void
    {
        $this->withFixture('suspended', '19.99', function (PDO $db, array $fixture): void {
            $html = $this->searchItems([
                'q' => $fixture['prefix'],
                'category' => (string) $fixture['category_id'],
            ]);
            $this->assertOnlyActiveFixtureIsShown($html, $fixture);
        });
    }

    public function testPriceConditionAndFulfillmentFiltersExcludeSuspendedSeller(): void
    {
        $this->withFixture('suspended', '19.99', function (PDO $db, array $fixture): void {
            $html = $this->searchItems([
                'q' => $fixture['prefix'],
                'min_price' => '10',
                'max_price' => '25',
                'condition' => ['good'],
                'fulfillment' => 'shipping',
            ]);
            $this->assertOnlyActiveFixtureIsShown($html, $fixture);
        });
    }

    public function testSuspendedSellerListingDetailsRemainUnavailable(): void
    {
        $this->withFixture('suspended', '19.99', function (PDO $db, array $fixture): void {
            $html = \httpGet('/pages/listing.php', ['id' => (string) $fixture['inactive_listing_id']]);
            self::assertStringContainsString('Listing unavailable', $html);
            self::assertStringContainsString('This listing is not available.', $html);
            self::assertStringNotContainsString('id="listing-detail-title"', $html);
        });
    }

    /** @return array<string, array{string}> */
    public static function nonActiveStatuses(): array
    {
        return [
            'suspended' => ['suspended'],
            'restricted' => ['restricted'],
            'deleted' => ['deleted'],
        ];
    }

    #[DataProvider('nonActiveStatuses')]
    public function testAllNonActiveSellerStatusesAreExcluded(string $status): void
    {
        $this->withFixture($status, '19.99', function (PDO $db, array $fixture): void {
            $html = $this->searchItems(['q' => $fixture['prefix']]);
            $this->assertOnlyActiveFixtureIsShown($html, $fixture);
        });
    }

    /** @param array<string, mixed> $params */
    private function searchItems(array $params): string
    {
        return \httpGet('/pages/search.php', ['search_type' => 'items'] + $params);
    }

    /** @param array<string, mixed> $fixture */
    private function assertOnlyActiveFixtureIsShown(string $html, array $fixture): void
    {
        self::assertTrue(
            $this->containsListingLink($html, $fixture['active_listing_id']),
            'An active seller listing must remain discoverable.'
        );
        self::assertFalse(
            $this->containsListingLink($html, $fixture['inactive_listing_id']),
            'Non-active seller listing must not be discoverable.'
        );
        self::assertStringNotContainsString('Unable to retrieve search results', $html);
    }

    private function containsListingLink(string $html, int $listingId): bool
    {
        $xpath = $this->xpath($html);
        $links = $xpath->query('//a[@href]');
        self::assertNotFalse($links);
        foreach ($links as $link) {
            $url = $link->attributes?->getNamedItem('href')?->nodeValue ?? '';
            $path = parse_url($url, PHP_URL_PATH);
            if (!is_string($path) || basename($path) !== 'listing.php') {
                continue;
            }
            $query = parse_url($url, PHP_URL_QUERY);
            if (!is_string($query)) {
                continue;
            }
            parse_str($query, $parameters);
            if (isset($parameters['id']) && (string) $parameters['id'] === (string) $listingId) {
                return true;
            }
        }
        return false;
    }

    private function displayedResultCount(string $html): int
    {
        $value = trim((string) $this->xpath($html)->evaluate(
            'string(//*[contains(concat(" ", normalize-space(@class), " "), " search-results__count ")])'
        ));
        self::assertMatchesRegularExpression('/^\d+ results?$/', $value);
        return (int) $value;
    }

    private function xpath(string $html): DOMXPath
    {
        $old = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            self::assertTrue($document->loadHTML('<?xml encoding="UTF-8"?>' . $html, LIBXML_NONET));
            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
    }

    private function setFixtureSellerStatus(PDO $db, int $userId, string $status): void
    {
        $stmt = $db->prepare('UPDATE users SET account_status = :status WHERE user_id = :user_id');
        $stmt->execute(['status' => $status, 'user_id' => $userId]);
        self::assertSame(1, $stmt->rowCount());
    }

    /**
     * Fixture inserts are autocommitted so requests from the running web server
     * can read them. No existing rows are modified. Cleanup is in reverse FK order.
     *
     * @param callable(PDO, array<string, mixed>):void $callback
     */
    private function withFixture(string $inactiveStatus, string $inactivePrice, callable $callback): void
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
            $activeSellerId = $db->query(<<<'SQL'
                SELECT sp.user_id FROM seller_profiles AS sp
                INNER JOIN users AS u ON u.user_id = sp.user_id
                WHERE u.account_status = 'active'
                ORDER BY sp.user_id LIMIT 1
                SQL)->fetchColumn();
            self::assertNotFalse($activeSellerId, 'At least one active seeded seller is needed.');
            $categoryId = $db->query('SELECT category_id FROM categories ORDER BY category_id LIMIT 1')->fetchColumn();
            self::assertNotFalse($categoryId, 'At least one seeded category is needed.');
            $token = bin2hex(random_bytes(6));
            $prefix = 'MDSEC05-' . $token;

            $inactiveUserId = $this->insertTracked($db, $created, 'users', [
                'first_name' => 'MDSEC05',
                'last_name' => 'Fixture',
                'username' => 'mdsec05_' . $token,
                'email' => 'mdsec05_' . $token . '@example.test',
                'password_hash' => password_hash('fixture-not-a-real-login', PASSWORD_DEFAULT),
                'phone_number' => '5550000000',
                'birth_date' => '2000-01-01',
                'account_status' => $inactiveStatus,
            ]);
            $this->insertTracked($db, $created, 'seller_profiles', [
                'user_id' => $inactiveUserId,
                'store_name' => 'MDSEC05 fixture ' . $token,
                'payout_account_reference' => 'mdsec05-fixture-' . $token,
            ]);
            $base = [
                'category_id' => (int) $categoryId,
                'item_description' => 'Temporary security regression fixture',
                'sale_type' => 'fixed_price',
                'fulfillment_type' => 'shipping',
                'shipping_cost' => '0.00',
                'estimated_shipping_days' => 3,
                'condition' => 'good',
                'quantity' => 2,
                'listing_status' => 'active',
                // Puts the fixture at the front of the newest Homepage feed.
                'created_at' => '2037-01-01 00:00:00',
            ];
            $activeListingId = $this->insertTracked($db, $created, 'listings', [
                'seller_user_id' => (int) $activeSellerId,
                'item_name' => $prefix . ' active',
                'price' => '19.99',
            ] + $base);
            $inactiveListingId = $this->insertTracked($db, $created, 'listings', [
                'seller_user_id' => $inactiveUserId,
                'item_name' => $prefix . ' inactive',
                'price' => $inactivePrice,
            ] + $base);
            $callback($db, [
                'prefix' => $prefix,
                'active_listing_id' => $activeListingId,
                'inactive_listing_id' => $inactiveListingId,
                'inactive_user_id' => $inactiveUserId,
                'category_id' => (int) $categoryId,
            ]);
        } finally {
            foreach (array_reverse($created) as [$table, $idColumn, $id]) {
                $statement = $db->prepare("DELETE FROM {$table} WHERE {$idColumn} = ?");
                $statement->execute([$id]);
            }
        }
    }

    /** @param array<int, array{string,string,int}> $created
     *  @param array<string,mixed> $values
     */
    private function insertTracked(PDO $db, array &$created, string $table, array $values): int
    {
        $idColumns = [
            'users' => 'user_id',
            'seller_profiles' => 'user_id',
            'listings' => 'listing_id',
        ];
        if (!isset($idColumns[$table])) {
            throw new \LogicException('Unexpected fixture table name.');
        }
        foreach (array_keys($values) as $column) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/D', $column)) {
                throw new \LogicException('Unexpected fixture column name.');
            }
        }
        $columns = implode(', ', array_map(
            static fn(string $column): string => "`{$column}`",
            array_keys($values)
        ));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $statement = $db->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
        $statement->execute(array_values($values));
        $id = $table === 'seller_profiles'
            ? (int) $values['user_id']
            : (int) $db->lastInsertId();
        $created[] = [$table, $idColumns[$table], $id];
        return $id;
    }
}
