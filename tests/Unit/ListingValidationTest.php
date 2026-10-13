<?php
declare(strict_types=1);

namespace OmniBuy\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/listing-validation.php';

final class ListingValidationTest extends TestCase
{
    private array $validCategoryIds = ['1', '2', '3'];

    private function validListing(): array
    {
        return [
            'category' => '1',
            'condition' => 'like_new',
            'quantity' => '1',
            'fulfillment_type' => 'shipping',
        ];
    }

    public function testValidListingDetailsAreAccepted(): void
    {
        $errors = \validateListingDetails(
            $this->validListing(),
            $this->validCategoryIds
        );

        $this->assertEmpty($errors);
    }

    public function testMissingCategoryIsRejected(): void
    {
        $data = $this->validListing();
        $data['category'] = '';

        $errors = \validateListingDetails(
            $data,
            $this->validCategoryIds
        );

        $this->assertArrayHasKey('category', $errors);
    }

    public function testInvalidCategoryIsRejected(): void
    {
        $data = $this->validListing();
        $data['category'] = '999';

        $errors = \validateListingDetails(
            $data,
            $this->validCategoryIds
        );

        $this->assertArrayHasKey('category', $errors);
    }

    public function testInvalidConditionIsRejected(): void
    {
        $data = $this->validListing();
        $data['condition'] = 'like-new';

        $errors = \validateListingDetails(
            $data,
            $this->validCategoryIds
        );

        $this->assertArrayHasKey('condition', $errors);
    }

    public function testZeroQuantityIsRejected(): void
    {
        $data = $this->validListing();
        $data['quantity'] = '0';

        $errors = \validateListingDetails(
            $data,
            $this->validCategoryIds
        );

        $this->assertArrayHasKey('quantity', $errors);
    }

    public function testNegativeQuantityIsRejected(): void
    {
        $data = $this->validListing();
        $data['quantity'] = '-5';

        $errors = \validateListingDetails(
            $data,
            $this->validCategoryIds
        );

        $this->assertArrayHasKey('quantity', $errors);
    }

    public function testNonnumericQuantityIsRejected(): void
    {
        $data = $this->validListing();
        $data['quantity'] = 'abc';

        $errors = \validateListingDetails(
            $data,
            $this->validCategoryIds
        );

        $this->assertArrayHasKey('quantity', $errors);
    }

    public function testMeetupFulfillmentIsAccepted(): void
    {
        $data = $this->validListing();
        $data['fulfillment_type'] = 'meetup';

        $errors = \validateListingDetails(
            $data,
            $this->validCategoryIds
        );

        $this->assertEmpty($errors);
    }

    public function testInvalidFulfillmentIsRejected(): void
    {
        $data = $this->validListing();
        $data['fulfillment_type'] = 'pickup';

        $errors = \validateListingDetails(
            $data,
            $this->validCategoryIds
        );

        $this->assertArrayHasKey('fulfillment', $errors);
    }
}
