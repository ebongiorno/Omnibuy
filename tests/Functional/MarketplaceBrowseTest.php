<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use DOMDocument;
use DOMXPath;
use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../Support/TestHelpers.php';

final class MarketplaceBrowseTest extends DatabaseTestCase
{
    public function testBrowseFeedDisplaysActiveSeededListing(): void
    {
        $stmt = $this->pdo->query("
            SELECT item_name
            FROM listings AS l
            INNER JOIN users AS u ON u.user_id = l.seller_user_id
            WHERE l.listing_status = 'active' AND u.account_status = 'active'
            ORDER BY
                COALESCE(l.renewed_at, l.created_at) DESC,
                l.listing_id DESC
            LIMIT 1
        ");

        $browseItem = $stmt->fetchColumn();

        self::assertNotFalse($browseItem, 'No active seeded listing available.');

        $browseResponse = \httpGet('/');

        self::assertStringContainsString(
            (string) $browseItem,
            $browseResponse
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
            $browseResponse
        );
    }

    public function testBrowseAllDisplaysActiveSeededListing(): void
    {
        $stmt = $this->pdo->query("
            SELECT item_name
            FROM listings AS l
            INNER JOIN users AS u ON u.user_id = l.seller_user_id
            WHERE l.listing_status = 'active' AND u.account_status = 'active'
            ORDER BY
                COALESCE(l.renewed_at, l.created_at) DESC,
                l.listing_id DESC
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
            $browseAllResponse
        );
    }

    public function testHomepageListingCardsLinkToCorrectListing(): void
    {
        $html = \httpGet('/');

        $dom = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($dom);

        $cards = $xpath->query(
            '//article[contains(concat(" ", normalize-space(@class), " "), " listing-card ")]'
        );

        self::assertNotFalse($cards);
        self::assertGreaterThan(0, $cards->length);

        foreach ($cards as $card) {
            $imageLinks = $xpath->query(
                './/a[contains(concat(" ", normalize-space(@class), " "), " listing-card__image-link ")]',
                $card
            );

            $titleLinks = $xpath->query(
                './/h3[contains(concat(" ", normalize-space(@class), " "), " listing-card__title ")]//a',
                $card
            );

            self::assertCount(1, $imageLinks);
            self::assertCount(1, $titleLinks);

            $imageLink = $imageLinks->item(0);
            $titleLink = $titleLinks->item(0);

            // Verify that both links are valid DOM elements.
            if (!$imageLink instanceof \DOMElement) {
                $this->fail('Expected the listing image link to be a DOM element.');
            }

            if (!$titleLink instanceof \DOMElement) {
                $this->fail('Expected the listing title link to be a DOM element.');
            }

            // Safely retrieve the href attributes.
            $imageUrl = $imageLink->getAttribute('href');
            $titleUrl = $titleLink->getAttribute('href');

            self::assertSame($imageUrl, $titleUrl);

            self::assertMatchesRegularExpression(
                '~^/pages/listing\.php\?id=[1-9][0-9]*$~',
                $imageUrl
            );

            parse_str(
                (string) parse_url($imageUrl, PHP_URL_QUERY),
                $parameters
            );

            $listingId = (int) $parameters['id'];

            $stmt = $this->pdo->prepare('
                SELECT item_name
                FROM listings
                WHERE listing_id = :listing_id
                  AND listing_status = "active"
            ');

            $stmt->execute([
                'listing_id' => $listingId,
            ]);

            $listingName = $stmt->fetchColumn();

            self::assertNotFalse($listingName);

            $detailHtml = \httpGet('/pages/listing.php', [
                'id' => (string) $listingId,
            ]);

            self::assertStringContainsString(
                'id="listing-detail-title"',
                $detailHtml
            );

            self::assertStringContainsString(
                htmlspecialchars(
                    (string) $listingName,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ),
                $detailHtml
            );
        }
    }
}
