<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\DatabaseTestCase;
use PDO;

require_once __DIR__ . '/../Support/TestHelpers.php';

/**
 * UC-20: Minimum/maximum price filtering and validation.
 *
 * Expected result counts are calculated from active database records, so
 * adding more seeded listings does not hardcode or invalidate these tests.
 */
final class PriceRangeFilterTest extends DatabaseTestCase
{
    public function testMinimumPriceOnlyIncludesListingsAtOrAboveMinimum(): void
    {
        $response = $this->searchByPrice(['min_price' => '50']);

        $this->assertResultCount($response, $this->activeCount('price >= :minimum', [
            'minimum' => '50',
        ]));
        self::assertStringContainsString(
            $this->activeListingNameWhere('price >= :minimum', ['minimum' => '50']),
            $this->decodedHtml($response)
        );
        self::assertStringNotContainsString(
            $this->activeListingNameWhere('price < :minimum', ['minimum' => '50']),
            $this->decodedHtml($response)
        );
    }

    public function testMaximumPriceOnlyIncludesListingsAtOrBelowMaximum(): void
    {
        $response = $this->searchByPrice(['max_price' => '100']);

        $this->assertResultCount($response, $this->activeCount('price <= :maximum', [
            'maximum' => '100',
        ]));
        self::assertStringContainsString(
            $this->activeListingNameWhere('price <= :maximum', ['maximum' => '100']),
            $this->decodedHtml($response)
        );
        self::assertStringNotContainsString(
            $this->activeListingNameWhere('price > :maximum', ['maximum' => '100']),
            $this->decodedHtml($response)
        );
    }

    public function testInclusiveMinimumAndMaximumPriceRange(): void
    {
        $response = $this->searchByPrice([
            'min_price' => '50',
            'max_price' => '100',
        ]);

        $this->assertResultCount($response, $this->activeCount(
            'price >= :minimum AND price <= :maximum',
            ['minimum' => '50', 'maximum' => '100']
        ));
        self::assertStringContainsString(
            $this->activeListingNameWhere(
                'price >= :minimum AND price <= :maximum',
                ['minimum' => '50', 'maximum' => '100']
            ),
            $this->decodedHtml($response)
        );
        self::assertStringNotContainsString(
            $this->activeListingNameWhere('price < :minimum', ['minimum' => '50']),
            $this->decodedHtml($response)
        );
    }

    public function testEqualBoundsIncludeListingsAtThatExactPrice(): void
    {
        // Choose a real active listing price, rather than assuming a fixed seed price.
        $stmt = $this->pdo->query("
            SELECT item_name, price
            FROM listings
            WHERE listing_status = 'active'
            ORDER BY listing_id ASC
            LIMIT 1
        ");
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($listing, 'No active listing available for boundary testing.');

        $price = (string) $listing['price'];
        $response = $this->searchByPrice([
            'min_price' => $price,
            'max_price' => $price,
        ]);

        self::assertStringContainsString((string) $listing['item_name'], $this->decodedHtml($response));
        $this->assertResultCount($response, $this->activeCount('price = :price', [
            'price' => $price,
        ]));
    }

    public function testDecimalPriceRangeReturnsCorrectResults(): void
    {
        $response = $this->searchByPrice([
            'min_price' => '19.99',
            'max_price' => '49.99',
        ]);

        $this->assertResultCount($response, $this->activeCount(
            'price >= :minimum AND price <= :maximum',
            ['minimum' => '19.99', 'maximum' => '49.99']
        ));
        self::assertStringNotContainsString('Invalid Price Range', $response);
    }

    public function testMinimumGreaterThanMaximumDisplaysValidationError(): void
    {
        $response = $this->searchByPrice([
            'min_price' => '100',
            'max_price' => '50',
        ]);

        self::assertStringContainsString('Invalid Price Range', $response);
        self::assertStringContainsString(
            'Minimum price cannot be greater than maximum price.',
            $response
        );
    }

    public function testNegativeMinimumPriceDisplaysValidationError(): void
    {
        $response = $this->searchByPrice(['min_price' => '-10']);

        self::assertStringContainsString('Invalid Price Range', $response);
        self::assertStringContainsString(
            'Enter a valid minimum price using up to two decimal places.',
            $response
        );
    }

    public function testNegativeMaximumPriceDisplaysValidationError(): void
    {
        $response = $this->searchByPrice(['max_price' => '-25']);

        self::assertStringContainsString('Invalid Price Range', $response);
        self::assertStringContainsString(
            'Enter a valid maximum price using up to two decimal places.',
            $response
        );
    }

    public function testNonnumericMinimumPriceDisplaysValidationError(): void
    {
        $response = $this->searchByPrice(['min_price' => 'abc']);

        self::assertStringContainsString('Invalid Price Range', $response);
        self::assertStringContainsString(
            'Enter a valid minimum price using up to two decimal places.',
            $response
        );
    }

    public function testNonnumericMaximumPriceDisplaysValidationError(): void
    {
        $response = $this->searchByPrice(['max_price' => 'xyz']);

        self::assertStringContainsString('Invalid Price Range', $response);
        self::assertStringContainsString(
            'Enter a valid maximum price using up to two decimal places.',
            $response
        );
    }

    public function testMoreThanTwoDecimalPlacesDisplaysValidationError(): void
    {
        $response = $this->searchByPrice(['min_price' => '15.999']);

        self::assertStringContainsString('Invalid Price Range', $response);
        self::assertStringContainsString(
            'Enter a valid minimum price using up to two decimal places.',
            $response
        );
    }

    public function testZeroMinimumIncludesAllActiveListings(): void
    {
        $response = $this->searchByPrice(['min_price' => '0']);

        $this->assertResultCount($response, $this->activeCount('price >= :minimum', [
            'minimum' => '0',
        ]));
        self::assertStringNotContainsString('Invalid Price Range', $response);
    }

    public function testVeryHighMinimumDisplaysNoResults(): void
    {
        $response = $this->searchByPrice(['min_price' => '999999999']);

        self::assertStringContainsString('No listings found', $response);
        $this->assertResultCount($response, 0);
    }

    public function testBlankPriceFieldsDoNotRestrictBrowseAll(): void
    {
        $response = $this->searchByPrice([
            'browse' => 'all',
            'min_price' => '',
            'max_price' => '',
        ]);

        $this->assertResultCount($response, $this->activeCount('1 = 1'));
        self::assertStringNotContainsString('Invalid Price Range', $response);
    }

    public function testInactiveListingIsExcludedEvenIfItsPriceMatches(): void
    {
        $stmt = $this->pdo->query("
            SELECT item_name, price
            FROM listings
            WHERE listing_status <> 'active'
            ORDER BY listing_id ASC
            LIMIT 1
        ");
        $inactive = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($inactive === false) {
            self::markTestSkipped('No inactive seeded listing is available.');
        }

        $price = (string) $inactive['price'];
        $response = $this->searchByPrice([
            'min_price' => $price,
            'max_price' => $price,
        ]);

        self::assertStringNotContainsString(
            (string) $inactive['item_name'],
            $this->decodedHtml($response),
            'Inactive listings must remain hidden even when the price matches.'
        );
        $this->assertResultCount($response, $this->activeCount('price = :price', [
            'price' => $price,
        ]));
    }

    public function testSubmittedPriceValuesRemainInFilterInputs(): void
    {
        $response = $this->searchByPrice([
            'min_price' => '25.50',
            'max_price' => '125.75',
        ]);

        self::assertMatchesRegularExpression(
            '/<input\b(?=[^>]*name="min_price")(?=[^>]*value="25\.50")[^>]*>/s',
            $response
        );
        self::assertMatchesRegularExpression(
            '/<input\b(?=[^>]*name="max_price")(?=[^>]*value="125\.75")[^>]*>/s',
            $response
        );
    }

    /** @param array<string, string> $params */
    private function searchByPrice(array $params): string
    {
        return \httpGet('/pages/search.php', array_merge([
            'search_type' => 'items',
        ], $params));
    }

    /**
     * Only internally authored, constant SQL predicates are passed to this
     * helper; never use untrusted input in the $whereSql argument.
     *
     * @param array<string, string> $params
     */
    private function activeCount(string $whereSql, array $params = []): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active' AND {$whereSql}
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, string> $params
     */
    private function activeListingNameWhere(string $whereSql, array $params): string
    {
        $stmt = $this->pdo->prepare("
            SELECT item_name
            FROM listings
            WHERE listing_status = 'active' AND {$whereSql}
            ORDER BY listing_id ASC
            LIMIT 1
        ");
        $stmt->execute($params);
        $itemName = $stmt->fetchColumn();

        self::assertNotFalse($itemName, 'Expected an active fixture matching this price boundary.');
        return (string) $itemName;
    }

    private function decodedHtml(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function assertResultCount(string $html, int $expected): void
    {
        $matched = preg_match(
            '/class="search-results__count"\s*>\s*(\d+)\s+results?\s*</s',
            $html,
            $matches
        );
        self::assertSame(1, $matched, 'Search result count was not found in the response.');
        self::assertSame($expected, (int) $matches[1], 'Wrong number of filtered results.');
    }
}
