<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Security;

use DOMDocument;
use DOMElement;
use DOMXPath;
use OmniBuy\Tests\Support\DatabaseTestCase;
use PDO;

require_once __DIR__ . '/../Support/TestHelpers.php';
require_once __DIR__ . '/../../includes/marketplace-image.php';

/**
 * MD-SEC-06: Read-only HTTP regression checks for seller avatars.
 *
 * Exercises real Seller Search and public Profile HTML with a seeded active
 * seller. Does not change the database. PHP/browser image-load events are
 * separate: browser fallbacks require manual verification.
 */
final class MarketplaceSellerAvatarTest extends DatabaseTestCase
{
    private const PROFILE_FALLBACK = '/assets/icons/default-avatar.png';

    public function testSellerSearchAvatarUsesValidatedUrlOrUserIcon(): void
    {
        $seller = $this->activeSeller();
        $html = $this->sellerSearchHtml((string) $seller['username']);
        $xpath = $this->xpath($html);
        $card = $this->sellerCardFor($xpath, (int) $seller['user_id']);
        $images = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " seller-card__image ")]//img', $card);
        $icons = $xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " seller-card__image ")]//i[contains(concat(" ", normalize-space(@class), " "), " fa-user ")]', $card);

        self::assertNotFalse($images);
        self::assertNotFalse($icons);

        $expected = \marketplaceImageUrl($seller['profile_image_url'], '');
        if ($expected === '') {
            self::assertCount(0, $images, 'Invalid or missing seller image URL must not render an img element.');
            self::assertCount(1, $icons, 'Seller Search should render its existing user icon.');
        } else {
            self::assertCount(1, $images, 'A valid seller image URL should render one img element.');
            $image = $this->elementAt($images);
            self::assertSame($expected, $image->getAttribute('src'));
            self::assertTrue($image->hasAttribute('data-seller-image'));
            self::assertCount(1, $icons, 'The backup user icon should be present for browser failures.');
            self::assertTrue($this->elementAt($icons)->hasAttribute('hidden'));
        }
    }

    public function testSellerSearchImageCannotContainHtmlEventAttributes(): void
    {
        $seller = $this->activeSeller();
        $xpath = $this->xpath($this->sellerSearchHtml((string) $seller['username']));
        $card = $this->sellerCardFor($xpath, (int) $seller['user_id']);
        $images = $xpath->query('.//img', $card);
        self::assertNotFalse($images);

        foreach ($images as $node) {
            self::assertInstanceOf(DOMElement::class, $node);
            $this->assertNoEventHandlerAttributes($node);
        }
    }

    public function testSellerSearchIncludesBrowserAvatarFallback(): void
    {
        $seller = $this->activeSeller();
        $html = $this->sellerSearchHtml((string) $seller['username']);
        self::assertStringContainsString('/assets/js/seller-image-fallback.js', $html);
        self::assertStringNotContainsString('Fatal error:', $html);
        self::assertStringNotContainsString('Uncaught TypeError', $html);
    }

    public function testPublicProfileAvatarUsesValidatedUrlAndFallbackMarker(): void
    {
        $seller = $this->activeSeller();
        $html = \httpGet('/pages/profile.php', ['id' => (string) $seller['user_id']]);
        $xpath = $this->xpath($html);
        $nodes = $xpath->query('//img[contains(concat(" ", normalize-space(@class), " "), " profile-card__avatar ")]');
        self::assertNotFalse($nodes);
        self::assertCount(1, $nodes);

        $image = $this->elementAt($nodes);
        $expected = \marketplaceImageUrl($seller['profile_image_url'], self::PROFILE_FALLBACK);
        self::assertSame($expected, $image->getAttribute('src'));
        self::assertTrue($image->hasAttribute('data-profile-avatar'));
        $this->assertNoEventHandlerAttributes($image);
        self::assertStringContainsString('/assets/js/profile-avatar-fallback.js', $html);
    }

    public function testPublicProfileDoesNotRenderDatabaseImageValueAsEventAttributes(): void
    {
        $seller = $this->activeSeller();
        $xpath = $this->xpath(\httpGet('/pages/profile.php', ['id' => (string) $seller['user_id']]));
        $nodes = $xpath->query('//header[contains(concat(" ", normalize-space(@class), " "), " profile-card ")]//img');
        self::assertNotFalse($nodes);
        foreach ($nodes as $node) {
            self::assertInstanceOf(DOMElement::class, $node);
            $this->assertNoEventHandlerAttributes($node);
        }
        self::assertGreaterThan(0, $nodes->count());
    }

    /** @return array{user_id:int|string,username:string,profile_image_url:mixed} */
    private function activeSeller(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT u.user_id, u.username, u.profile_image_url
            FROM users AS u
            INNER JOIN seller_profiles AS sp ON sp.user_id = u.user_id
            WHERE u.account_status = 'active'
            ORDER BY u.user_id ASC
            LIMIT 1
            SQL)->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row, 'An active seeded seller is required for this read-only HTTP test.');
        return $row;
    }

    private function sellerSearchHtml(string $username): string
    {
        return \httpGet('/pages/search.php', [
            'q' => $username,
            'search_type' => 'seller_profiles',
        ]);
    }

    private function sellerCardFor(DOMXPath $xpath, int $sellerId): DOMElement
    {
        $href = '/pages/profile.php?id=' . $sellerId;
        $cards = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " seller-card ")]');
        self::assertNotFalse($cards);
        foreach ($cards as $card) {
            self::assertInstanceOf(DOMElement::class, $card);
            $links = $xpath->query('.//a[@href="' . $href . '"]', $card);
            if ($links !== false && $links->count() > 0) {
                return $card;
            }
        }
        self::fail('Seller Search did not render the selected seeded seller card.');
    }

    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            self::assertTrue($document->loadHTML('<?xml encoding="UTF-8"?>' . $html, LIBXML_NONET));
            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function elementAt(\DOMNodeList $nodes): DOMElement
    {
        $element = $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $element);
        return $element;
    }

    private function assertNoEventHandlerAttributes(DOMElement $element): void
    {
        foreach ($element->attributes as $attribute) {
            self::assertDoesNotMatchRegularExpression(
                '/^on[a-z]+$/i',
                $attribute->name,
                'An image must not have inline event-handler attributes.'
            );
        }
    }
}
