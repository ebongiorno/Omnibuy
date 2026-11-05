<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Security;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * MD-SEC-06: Image URL validation, HTML attribute injection prevention,
 * and the server-side listing-card fallback.
 *
 * No HTTP requests or database writes: these tests render the real shared
 * listing-card component with synthetic data and call the production helper.
 * Browser image-load failures must also be verified manually because PHPUnit
 * does not execute JavaScript or fire <img> error events.
 */
final class MarketplaceImageSecurityTest extends TestCase
{
    private const FALLBACK = '/assets/images/listing/listing-default-img.jpg';

    protected function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../../includes/marketplace-image.php';
    }

    /** @return array<string, array{mixed}> */
    public static function invalidImageUrls(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace' => ['   '],
            'array input' => [['/uploads/product.jpg']],
            'quote and event-handler injection' => ['x" onerror="console.log(\'OmniBuy-IMG-01\')'],
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme' => ['data:text/html,<script>alert(1)</script>'],
            'protocol-relative URL' => ['//untrusted.example/pic.jpg'],
            'HTTP URL' => ['http://images.example/pic.jpg'],
            'unapproved local directory' => ['/private/pic.jpg'],
            'local path traversal' => ['/uploads/../private/pic.jpg'],
            'encoded local traversal' => ['/uploads/%2e%2e/private/pic.jpg'],
            'backslash in local path' => ['/uploads\\pic.jpg'],
            'control character' => ["/uploads/a\n.jpg"],
        ];
    }

    #[DataProvider('invalidImageUrls')]
    public function testInvalidImageUrlUsesDefault(mixed $url): void
    {
        self::assertSame(self::FALLBACK, \marketplaceImageUrl($url));
    }

    /** @return array<string, array{string,string}> */
    public static function allowedImageUrls(): array
    {
        return [
            'rooted asset' => ['/assets/images/listing/item.jpg', '/assets/images/listing/item.jpg'],
            'relative asset' => ['assets/images/listing/item.jpg', '/assets/images/listing/item.jpg'],
            'rooted upload' => ['/uploads/item.png', '/uploads/item.png'],
            'relative upload' => ['uploads/item.png', '/uploads/item.png'],
            'HTTPS image' => ['https://images.example/item.jpg', 'https://images.example/item.jpg'],
        ];
    }

    #[DataProvider('allowedImageUrls')]
    public function testAllowedImageUrlIsPreserved(string $url, string $expected): void
    {
        self::assertSame($expected, \marketplaceImageUrl($url));
    }

    public function testListingCardWithNoImageUsesDefault(): void
    {
        $image = $this->listingCardImage(null);
        self::assertSame(self::FALLBACK, $image->getAttribute('src'));
        self::assertSame('Fixture item', $image->getAttribute('alt'));
        self::assertTrue($image->hasAttribute('data-image-fallback'));
    }

    public function testListingCardWithEmptyImageUsesDefault(): void
    {
        $image = $this->listingCardImage('');
        self::assertSame(self::FALLBACK, $image->getAttribute('src'));
    }

    public function testListingCardRejectsAttributeInjection(): void
    {
        $image = $this->listingCardImage('x" onerror="console.log(\'OmniBuy-IMG-01\')');
        self::assertSame(self::FALLBACK, $image->getAttribute('src'));
        self::assertFalse($image->hasAttribute('onerror'));
        self::assertFalse($image->hasAttribute('onload'));
    }

    public function testListingCardEscapesItemNameAttribute(): void
    {
        $image = $this->listingCardImage('/uploads/item.jpg', 'Hat " onerror="alert(1)');
        self::assertSame('/uploads/item.jpg', $image->getAttribute('src'));
        self::assertSame('Hat " onerror="alert(1)', $image->getAttribute('alt'));
        self::assertFalse($image->hasAttribute('onerror'));
    }

    public function testListingCardPreservesValidImage(): void
    {
        $image = $this->listingCardImage('uploads/item.png');
        self::assertSame('/uploads/item.png', $image->getAttribute('src'));
        self::assertTrue($image->hasAttribute('data-image-fallback'));
    }

    public function testListingCardKeepsInaccessibleButWellFormedUrlForBrowserFallback(): void
    {
        // A well-formed URL can still return 404. JavaScript handles the load failure.
        $image = $this->listingCardImage('/uploads/omnibuy-nonexistent-image-test.jpg');
        self::assertSame('/uploads/omnibuy-nonexistent-image-test.jpg', $image->getAttribute('src'));
        self::assertTrue($image->hasAttribute('data-image-fallback'));
    }

    private function listingCardImage(mixed $url, string $name = 'Fixture item'): DOMElement
    {
        $listing = [
            'listing_id' => 42,
            'image_url' => $url,
            'item_name' => $name,
            'condition' => 'good',
            'price' => '12.50',
            'category_name' => 'Electronics',
            'fulfillment_type' => 'shipping',
        ];

        ob_start();
        try {
            include __DIR__ . '/../../public/components/listing-card-component.php';
            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $old = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            self::assertTrue($document->loadHTML('<?xml encoding="UTF-8"?>' . $html, LIBXML_NONET));
            $xpath = new DOMXPath($document);
            $images = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " listing-card ")]//img');
            self::assertNotFalse($images);
            self::assertCount(1, $images);
            $image = $images->item(0);
            self::assertInstanceOf(DOMElement::class, $image);
            return $image;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($old);
        }
    }
}
