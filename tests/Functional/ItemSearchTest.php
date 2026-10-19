<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../Support/TestHelpers.php';

/**
 * UC-17: Search Items. Uses the same seeded database fixtures and HTTP helper
 * as the original standalone test, but lets PHPUnit manage assertions/results.
 */
final class ItemSearchTest extends DatabaseTestCase
{
    public function testExactItemSearchReturnsSeededListing(): void
    {
        $sampleItem = $this->activeItemName();
        $response = \httpGet('/pages/search.php', [
            'q' => $sampleItem,
            'search_type' => 'items',
        ]);

        self::assertStringContainsString($sampleItem, $response);
        self::assertStringNotContainsString('No listings found', $response);
    }

    public function testPartialItemSearchReturnsExpectedListing(): void
    {
        $sampleItem = $this->activeItemName();
        $partialItem = explode(' ', $sampleItem)[0];
        $response = \httpGet('/pages/search.php', [
            'q' => $partialItem,
            'search_type' => 'items',
        ]);

        self::assertStringContainsString(
            $sampleItem,
            $response,
            'Partial Item Search should return the expected seeded listing.'
        );
    }

    public function testItemSearchDisplaysNoResultsState(): void
    {
        $response = \httpGet('/pages/search.php', [
            'q' => 'ci-no-such-item-948372',
            'search_type' => 'items',
        ]);

        self::assertStringContainsString('No listings found', $response);
    }

    public function testItemSearchExcludesInactiveListings(): void
    {
        $stmt = $this->pdo->query("
            SELECT item_name
            FROM listings
            WHERE listing_status <> 'active'
            ORDER BY listing_id ASC
            LIMIT 1
        ");
        $inactiveItem = $stmt->fetchColumn();

        if ($inactiveItem === false) {
            self::markTestSkipped('No inactive seeded listing available.');
        }

        $response = \httpGet('/pages/search.php', [
            'q' => (string) $inactiveItem,
            'search_type' => 'items',
        ]);

        self::assertStringContainsString(
            'No listings found',
            $response,
            'Item Search should not return inactive listings.'
        );
    }

    private function activeItemName(): string
    {
        $stmt = $this->pdo->query("
            SELECT item_name
            FROM listings
            WHERE listing_status = 'active'
            ORDER BY listing_id ASC
            LIMIT 1
        ");
        $sampleItem = $stmt->fetchColumn();

        self::assertNotFalse($sampleItem, 'No active seeded listing available.');

        return (string) $sampleItem;
    }
}
