<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../Support/TestHelpers.php';

/** UC-16: Browse Feed and Browse All. */
final class MarketplaceBrowseTest extends DatabaseTestCase
{
    public function testBrowseFeedDisplaysActiveSeededListing(): void
    {
        $stmt = $this->pdo->query("
            SELECT item_name
            FROM listings
            WHERE listing_status = 'active'
            ORDER BY created_at DESC, listing_id DESC
            LIMIT 1
        ");
        $browseItem = $stmt->fetchColumn();
        self::assertNotFalse($browseItem, 'No active seeded listing available.');

        $browseResponse = \httpGet('/');
        self::assertStringContainsString(
            (string) $browseItem,
            $browseResponse,
            'Browse Feed should display an active seeded listing.'
        );
    }

    public function testBrowseFeedExcludesInactiveListings(): void
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

        $browseResponse = \httpGet('/');
        self::assertStringNotContainsString(
            (string) $inactiveItem,
            $browseResponse,
            'Browse Feed should exclude inactive listings.'
        );
    }

    public function testBrowseAllDisplaysActiveSeededListing(): void
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

        $browseAllResponse = \httpGet('/pages/search.php', [
            'browse' => 'all',
            'search_type' => 'items',
        ]);
        self::assertStringContainsString(
            (string) $sampleItem,
            $browseAllResponse,
            'Browse All should display an active seeded listing.'
        );
    }
}
