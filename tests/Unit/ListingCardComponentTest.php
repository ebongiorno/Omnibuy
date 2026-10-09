<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Renders public/components/listing-card-component.php with sample data
 * and checks the HTML it produces.
 */
final class ListingCardComponentTest extends TestCase
{
    private const COMPONENT = __DIR__ . '/../../public/components/listing-card-component.php';

    public function testShowsItemNameCategoryAndFormattedPrice(): void
    {
        $html = $this->render(['item_name' => 'Wireless Keyboard', 'price' => 1234.5]);

        $this->assertStringContainsString('Wireless Keyboard', $html);
        $this->assertStringContainsString('Electronics', $html);
        $this->assertStringContainsString('$1,234.50', $html);
    }

    public function testFormatsConditionAndFulfillmentForDisplay(): void
    {
        $html = $this->render(['condition' => 'like_new', 'fulfillment_type' => 'meetup']);

        $this->assertStringContainsString('Like New', $html);
        $this->assertStringContainsString('Meetup', $html);
    }

    public function testLinksToTheListingPage(): void
    {
        $html = $this->render(['listing_id' => 42]);

        $this->assertStringContainsString('href="listing.php?id=42"', $html);
    }

    public function testFallsBackToDefaultImageWhenListingHasNoImage(): void
    {
        $html = $this->render(['image_url' => null]);

        $this->assertStringContainsString('assets/images/listing/listing-default-img.jpg', $html);
    }

    public function testEscapesHtmlInSellerProvidedFields(): void
    {
        // Security: item names and image URLs come from sellers and must not inject markup.
        $html = $this->render([
            'item_name' => '<script>alert("xss")</script>',
            'image_url' => '"><script>alert(1)</script>',
            'category_name' => '<b>Category</b>',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>Category</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    private function render(array $overrides = []): string
    {
        $listing = array_merge([
            'listing_id' => 1,
            'item_name' => 'Test Item',
            'price' => 10.00,
            'condition' => 'good',
            'fulfillment_type' => 'shipping',
            'category_name' => 'Electronics',
            'image_url' => '/assets/images/listing/test.jpg',
        ], $overrides);

        ob_start();
        include self::COMPONENT;

        return (string) ob_get_clean();
    }
}
