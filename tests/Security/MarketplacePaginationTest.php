<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Security;

use DOMDocument;
use DOMElement;
use DOMXPath;
use OmniBuy\Tests\Support\DatabaseTestCase;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../Support/TestHelpers.php';

/**
 * MD-SEC-08: Database-backed, HTTP-level pagination regression tests.
 *
 * RUN ONLY AGAINST A DISPOSABLE TEST DATABASE. Fixtures are committed because
 * the HTTP server uses another database connection. Never run these tests on
 * production or a teammate's shared development database.
 */
final class MarketplacePaginationTest extends DatabaseTestCase
{
    private const PER_PAGE = 12;

    /**
     * Fixture writes must be committed before the HTTP request. DatabaseTestCase
     * may wrap its normal PDO connection in a transaction, which the web server
     * cannot see. This independent PDO uses autocommit and the disposable test DB.
     */
    private ?PDO $fixturePdo = null;

    private function fixtureConnection(): PDO
    {
        if ($this->fixturePdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST') ?: 'db',
                (int) (getenv('DB_PORT') ?: 3306),
                getenv('DB_NAME') ?: 'omnibuy'
            );
            $this->fixturePdo = new PDO(
                $dsn,
                getenv('DB_USER') ?: '',
                getenv('DB_PASSWORD') ?: '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }

        return $this->fixturePdo;
    }

    public function testBrowseAllIsBoundedAndReportsCompleteCount(): void
    {
        $response = $this->search(['browse' => 'all']);
        $ids = $this->listingIds($response);
        $expected = (int) $this->pdo->query(<<<'SQL'
            SELECT COUNT(*) FROM listings AS l
            INNER JOIN users AS u ON u.user_id = l.seller_user_id
            WHERE l.listing_status = 'active' AND u.account_status = 'active'
            SQL)->fetchColumn();

        self::assertSame($expected, $this->resultCount($response));
        self::assertCount(min(self::PER_PAGE, $expected), $ids);
        self::assertSame(count($ids), count(array_unique($ids)));
        self::assertStringNotContainsString('Unable to retrieve search results', $response);
    }

    public function testItemPagesHaveNoMissingOrDuplicateListings(): void
    {
        $this->withListings(function (array $fixture): void {
            $query = ['q' => $fixture['prefix'], 'sort' => 'recent'];
            $first = $this->search($query + ['page' => '1']);
            $second = $this->search($query + ['page' => '2']);
            $firstIds = $this->listingIds($first);
            $secondIds = $this->listingIds($second);

            self::assertSame(15, $this->resultCount($first));
            self::assertSame(15, $this->resultCount($second));
            self::assertCount(12, $firstIds);
            self::assertCount(3, $secondIds);
            self::assertSame([], array_values(array_intersect($firstIds, $secondIds)));
            // Every eligible fixture appears, and no ineligible seller appears.
            self::assertSame(
                $fixture['active_ids'],
                array_values(array_unique(array_merge($firstIds, $secondIds)))
            );
            self::assertNotContains($fixture['inactive_id'], $firstIds);
            self::assertNotContains($fixture['inactive_id'], $secondIds);
            self::assertStringContainsString('Showing 1', $first);
            self::assertStringContainsString('Showing 13', $second);
        });
    }

    public function testPaginationControlsAndBoundaries(): void
    {
        $this->withListings(function (array $fixture): void {
            $query = ['q' => $fixture['prefix']];
            $first = $this->search($query + ['page' => '1']);
            $second = $this->search($query + ['page' => '2']);

            self::assertSame(0, $this->paginationLinksByLabel($first, 'Previous'));
            self::assertSame(1, $this->paginationLinksByLabel($first, 'Next'));
            self::assertSame(1, $this->paginationLinksByLabel($second, 'Previous'));
            self::assertSame(0, $this->paginationLinksByLabel($second, 'Next'));
            self::assertSame(1, $this->currentPageLinks($first));
            self::assertSame(1, $this->currentPageLinks($second));
            self::assertSame('2', $this->paginationTarget($first, 'Next')['page'] ?? null);
            self::assertSame('1', $this->paginationTarget($second, 'Previous')['page'] ?? null);
            self::assertSame($fixture['prefix'], $this->paginationTarget($first, 'Next')['q'] ?? null);
        });
    }

    public function testFiltersAndSortRemainAppliedOnPageTwo(): void
    {
        $this->withListings(function (array $fixture): void {
            $query = [
                'q' => $fixture['prefix'],
                'category' => (string) $fixture['category_id'],
                'min_price' => '20',
                'max_price' => '34',
                'condition' => ['good'],
                'fulfillment' => 'shipping',
                'sort' => 'price_low',
            ];
            $page1 = $this->search($query + ['page' => '1']);
            $page2 = $this->search($query + ['page' => '2']);
            $page1Ids = $this->listingIds($page1);
            $page2Ids = $this->listingIds($page2);
            self::assertSame(15, $this->resultCount($page1));
            self::assertSame(15, $this->resultCount($page2));
            self::assertCount(12, $page1Ids);
            self::assertCount(3, $page2Ids);
            self::assertSame($fixture['price_order_ids'], array_merge($page1Ids, $page2Ids));

            $next = $this->paginationTarget($page1, 'Next');
            foreach (['q', 'category', 'min_price', 'max_price', 'fulfillment', 'sort'] as $name) {
                self::assertSame($query[$name], $next[$name] ?? null, "Pagination lost {$name}.");
            }
            self::assertSame(['good'], $next['condition'] ?? null);
            self::assertSame('2', $next['page'] ?? null);
        });
    }

    /** @return array<string, array{string}> */
    public static function malformedPages(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'alphabetic' => ['abc'],
            'decimal' => ['2.5'],
            'overflow' => ['999999999999999999999'],
            'html payload' => ['<script>alert(1)</script>'],
        ];
    }

    #[DataProvider('malformedPages')]
    public function testMalformedPageFallsBackToFirstPage(string $page): void
    {
        $baseline = $this->search(['browse' => 'all', 'page' => '1']);
        $response = $this->search(['browse' => 'all', 'page' => $page]);
        self::assertSame($this->listingIds($baseline), $this->listingIds($response));
        self::assertSame($this->resultCount($baseline), $this->resultCount($response));
        $this->assertNoDiagnosticLeak($response);
    }

    public function testArrayPageFallsBackToFirstPage(): void
    {
        $baseline = $this->search(['browse' => 'all', 'page' => '1']);
        $response = $this->search(['browse' => 'all', 'page' => ['2']]);
        self::assertSame($this->listingIds($baseline), $this->listingIds($response));
        $this->assertNoDiagnosticLeak($response);
    }

    public function testOutOfRangeValidPageClampsToLastPage(): void
    {
        $this->withListings(function (array $fixture): void {
            $query = ['q' => $fixture['prefix']];
            $last = $this->search($query + ['page' => '2']);
            $beyond = $this->search($query + ['page' => '999']);
            self::assertSame($this->listingIds($last), $this->listingIds($beyond));
            self::assertSame(15, $this->resultCount($beyond));
            self::assertSame(1, $this->currentPageLinks($beyond));
            $this->assertNoDiagnosticLeak($beyond);
        });
    }

    public function testSellerSearchBoundsResultsAndExcludesSuspendedProfiles(): void
    {
        $this->withSellers(function (array $fixture): void {
            $query = ['search_type' => 'seller_profiles', 'q' => $fixture['prefix']];
            $first = $this->search($query + ['page' => '1']);
            $second = $this->search($query + ['page' => '2']);
            $firstIds = $this->sellerIds($first);
            $secondIds = $this->sellerIds($second);

            self::assertSame(13, $this->resultCount($first));
            self::assertSame(13, $this->resultCount($second));
            self::assertCount(12, $firstIds);
            self::assertCount(1, $secondIds);
            self::assertSame([], array_values(array_intersect($firstIds, $secondIds)));
            self::assertSame($fixture['active_ids'], array_merge($firstIds, $secondIds));
            self::assertNotContains($fixture['suspended_id'], $firstIds);
            self::assertNotContains($fixture['suspended_id'], $secondIds);
            self::assertSame('seller_profiles', $this->paginationTarget($first, 'Next')['search_type'] ?? null);
            self::assertSame($fixture['prefix'], $this->paginationTarget($first, 'Next')['q'] ?? null);
        });
    }

    /** @param array<string, mixed> $query */
    private function search(array $query): string
    {
        return \httpGet('/pages/search.php', $query);
    }

    /** @return list<int> */
    private function listingIds(string $html): array
    {
        $xpath = $this->xpath($html);
        $links = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " listing-card ")]//h3[contains(concat(" ", normalize-space(@class), " "), " listing-card__title ")]//a[@href]');
        self::assertNotFalse($links);
        $ids = [];
        foreach ($links as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }
            $href = $link->getAttribute('href');
            if (parse_url($href, PHP_URL_PATH) !== '/pages/listing.php') {
                continue;
            }
            parse_str((string) parse_url($href, PHP_URL_QUERY), $params);
            if (isset($params['id']) && ctype_digit((string) $params['id'])) {
                $ids[] = (int) $params['id'];
            }
        }
        return $ids;
    }

    /** @return list<int> */
    private function sellerIds(string $html): array
    {
        $links = $this->xpath($html)->query('//article[contains(concat(" ", normalize-space(@class), " "), " seller-card ")]//a[@href]');
        self::assertNotFalse($links);
        $ids = [];
        foreach ($links as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }
            $href = $link->getAttribute('href');
            if (parse_url($href, PHP_URL_PATH) !== '/pages/profile.php') {
                continue;
            }
            parse_str((string) parse_url($href, PHP_URL_QUERY), $params);
            if (isset($params['id']) && ctype_digit((string) $params['id'])) {
                $ids[] = (int) $params['id'];
            }
        }
        return $ids;
    }

    private function resultCount(string $html): int
    {
        $value = trim((string) $this->xpath($html)->evaluate(
            'string(//*[contains(concat(" ", normalize-space(@class), " "), " search-results__count ")])'
        ));
        self::assertMatchesRegularExpression('/^\d+ results?$/', $value);
        return (int) $value;
    }

    private function paginationLinksByLabel(string $html, string $label): int
    {
        $links = $this->xpath($html)->query('//nav[@aria-label="Search results pagination"]//a');
        self::assertNotFalse($links);
        $count = 0;
        foreach ($links as $link) {
            if (trim((string) $link->textContent) === $label) {
                $count++;
            }
        }
        return $count;
    }

    private function currentPageLinks(string $html): int
    {
        return (int) $this->xpath($html)->evaluate(
            'count(//nav[@aria-label="Search results pagination"]//a[@aria-current="page"])'
        );
    }

    /** @return array<string, mixed> */
    private function paginationTarget(string $html, string $label): array
    {
        $links = $this->xpath($html)->query('//nav[@aria-label="Search results pagination"]//a');
        self::assertNotFalse($links);
        foreach ($links as $link) {
            if ($link instanceof DOMElement && trim((string) $link->textContent) === $label) {
                parse_str((string) parse_url($link->getAttribute('href'), PHP_URL_QUERY), $params);
                return $params;
            }
        }
        self::fail("Could not find pagination link {$label}.");
    }

    private function assertNoDiagnosticLeak(string $html): void
    {
        foreach (['Fatal error', 'Warning:', 'SQLSTATE[', 'Unable to retrieve search results'] as $message) {
            self::assertStringNotContainsString($message, $html);
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            self::assertTrue($dom->loadHTML('<?xml encoding="UTF-8"?>' . $html, LIBXML_NONET));
            return new DOMXPath($dom);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Creates 15 active listings and one suspended seller listing matching a
     * random unique keyword. All inserts are committed before HTTP requests.
     *
     * @param callable(array<string, mixed>):void $callback
     */
    private function withListings(callable $callback): void
    {
        $created = [];
        try {
            $seller = $this->fixtureConnection()->query(<<<'SQL'
                SELECT sp.user_id FROM seller_profiles AS sp
                JOIN users AS u ON u.user_id = sp.user_id
                WHERE u.account_status = 'active'
                ORDER BY sp.user_id LIMIT 1
                SQL)->fetchColumn();
            self::assertNotFalse($seller, 'Need an active seeded seller.');
            $category = $this->fixtureConnection()->query('SELECT category_id FROM categories ORDER BY category_id LIMIT 1')->fetchColumn();
            self::assertNotFalse($category, 'Need a seeded category.');
            $token = bin2hex(random_bytes(6));
            $prefix = 'MDSEC08-' . $token;
            $inactiveId = $this->createFixtureUser($created, $token . 'z', $prefix, 'suspended');
            $this->insertTracked($created, 'seller_profiles', [
                'user_id' => $inactiveId,
                'store_name' => $prefix . ' suspended',
                'payout_account_reference' => 'mdsec08-' . $token . '-suspended',
            ]);
            $common = [
                'category_id' => (int) $category,
                'item_description' => 'Temporary security pagination fixture',
                'sale_type' => 'fixed_price',
                'fulfillment_type' => 'shipping',
                'shipping_cost' => '0.00',
                'estimated_shipping_days' => 3,
                'condition' => 'good',
                'quantity' => 2,
                'listing_status' => 'active',
                'created_at' => '2037-01-01 00:00:00',
            ];
            $priceIds = [];
            for ($i = 0; $i < 15; $i++) {
                $priceIds[] = $this->insertTracked($created, 'listings', [
                    'seller_user_id' => (int) $seller,
                    'item_name' => $prefix . ' item ' . $i,
                    'price' => (string) (20 + $i) . '.00',
                ] + $common);
            }
            $inactiveListingId = $this->insertTracked($created, 'listings', [
                'seller_user_id' => $inactiveId,
                'item_name' => $prefix . ' suspended item',
                'price' => '22.00',
            ] + $common);
            $callback([
                'prefix' => $prefix,
                'category_id' => (int) $category,
                'active_ids' => array_reverse($priceIds), // newest (IDs descending)
                'price_order_ids' => $priceIds, // ascending prices
                'inactive_id' => $inactiveListingId,
            ]);
        } finally {
            $this->deleteTracked($created);
        }
    }

    /**
     * @param callable(array<string, mixed>):void $callback
     */
    private function withSellers(callable $callback): void
    {
        $created = [];
        try {
            $token = bin2hex(random_bytes(6));
            $prefix = 'MDSEC08Seller-' . $token;
            $ids = [];
            for ($i = 0; $i < 13; $i++) {
                $suffix = $token . sprintf('%02d', $i);
                $id = $this->createFixtureUser($created, $suffix, $prefix, 'active');
                $this->insertTracked($created, 'seller_profiles', [
                    'user_id' => $id,
                    'store_name' => $prefix . sprintf(' %02d', $i),
                    'payout_account_reference' => 'mdsec08-' . $suffix,
                ]);
                $ids[] = $id;
            }
            $suspendedSuffix = $token . 'zz';
            $suspendedId = $this->createFixtureUser($created, $suspendedSuffix, $prefix, 'suspended');
            $this->insertTracked($created, 'seller_profiles', [
                'user_id' => $suspendedId,
                'store_name' => $prefix . ' suspended',
                'payout_account_reference' => 'mdsec08-' . $suspendedSuffix,
            ]);
            $callback([
                'prefix' => $prefix,
                'active_ids' => $ids,
                'suspended_id' => $suspendedId,
            ]);
        } finally {
            $this->deleteTracked($created);
        }
    }

    /** @param list<array{string,string,int}> $created */
    private function createFixtureUser(array &$created, string $suffix, string $prefix, string $status): int
    {
        return $this->insertTracked($created, 'users', [
            'first_name' => $prefix,
            'last_name' => 'Fixture',
            'username' => 'mdsec08_' . $suffix,
            'email' => 'mdsec08_' . $suffix . '@example.test',
            'password_hash' => password_hash('fixture-not-a-real-login', PASSWORD_DEFAULT),
            'phone_number' => '5550000000',
            'birth_date' => '2000-01-01',
            'account_status' => $status,
        ]);
    }

    /**
     * @param list<array{string,string,int}> $created
     * @param array<string, mixed> $values
     */
    private function insertTracked(array &$created, string $table, array $values): int
    {
        $idColumns = ['users' => 'user_id', 'seller_profiles' => 'user_id', 'listings' => 'listing_id'];
        if (!isset($idColumns[$table])) {
            throw new \LogicException('Unsupported fixture table.');
        }
        foreach (array_keys($values) as $column) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/D', $column)) {
                throw new \LogicException('Invalid fixture field name.');
            }
        }
        $columns = implode(', ', array_map(static fn(string $name): string => "`{$name}`", array_keys($values)));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $stmt = $this->fixtureConnection()->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute(array_values($values));
        $id = $table === 'seller_profiles' ? (int) $values['user_id'] : (int) $this->fixtureConnection()->lastInsertId();
        $created[] = [$table, $idColumns[$table], $id];
        return $id;
    }

    /** @param list<array{string,string,int}> $created */
    private function deleteTracked(array $created): void
    {
        foreach (array_reverse($created) as [$table, $column, $id]) {
            $stmt = $this->fixtureConnection()->prepare("DELETE FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$id]);
        }
    }
}
