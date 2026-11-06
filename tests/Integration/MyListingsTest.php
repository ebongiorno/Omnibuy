<?php
declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

final class MyListingsTest extends DatabaseTestCase
{
    private int $sellerA;
    private int $sellerB;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = $this->uniqueSuffix();

        $this->sellerA = $this->createSeller("Seller A $suffix");
        $this->sellerB = $this->createSeller("Seller B $suffix");

        $this->pdo->prepare('
            INSERT INTO categories (category_name)
            VALUES (:name)
        ')->execute([
            'name' => "My Listings Category $suffix",
        ]);

        $this->categoryId = (int) $this->pdo->lastInsertId();
    }

    private function createSeller(string $name): int
    {
        $sellerId = $this->createUser('my_listings_seller');
        $suffix = $this->uniqueSuffix();

        $this->pdo->prepare('
            INSERT INTO seller_profiles (
                user_id, store_name, payout_account_reference
            ) VALUES (
                :user_id, :store_name, :payout_reference
            )
        ')->execute([
            'user_id' => $sellerId,
            'store_name' => "$name $suffix",
            'payout_reference' => "MY_LISTINGS_$suffix",
        ]);

        return $sellerId;
    }

    private function createListing(
        int $sellerId,
        string $name,
        float $price = 49.95,
        string $status = 'active'
    ): int {
        $this->pdo->prepare('
            INSERT INTO listings (
                seller_user_id,
                category_id,
                item_name,
                item_description,
                sale_type,
                fulfillment_type,
                price,
                `condition`,
                quantity,
                listing_status
            ) VALUES (
                :seller_id,
                :category_id,
                :name,
                "My Listings integration test",
                "fixed_price",
                "shipping",
                :price,
                "good",
                1,
                :status
            )
        ')->execute([
            'seller_id' => $sellerId,
            'category_id' => $this->categoryId,
            'name' => $name,
            'price' => $price,
            'status' => $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function getSellerListings(int $sellerId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT
                listing_id,
                item_name,
                price,
                `condition`,
                quantity,
                fulfillment_type,
                listing_status,
                created_at
            FROM listings
            WHERE seller_user_id = :seller_user_id
            ORDER BY created_at DESC, listing_id DESC
        ');

        $stmt->execute(['seller_user_id' => $sellerId]);

        return $stmt->fetchAll();
    }

    public function testSellerCanRetrieveOwnListing(): void
    {
        $listingId = $this->createListing(
            $this->sellerA,
            'Seller A Laptop'
        );

        $listings = $this->getSellerListings($this->sellerA);

        $ids = array_map(
            'intval',
            array_column($listings, 'listing_id')
        );

        $this->assertContains($listingId, $ids);
    }

    public function testSellerCannotSeeOtherSellersListing(): void
    {
        $otherListingId = $this->createListing(
            $this->sellerB,
            'Seller B Headphones'
        );

        $listings = $this->getSellerListings($this->sellerA);

        $ids = array_map(
            'intval',
            array_column($listings, 'listing_id')
        );

        $this->assertNotContains($otherListingId, $ids);
    }

    public function testListingInformationIsCorrect(): void
    {
        $listingId = $this->createListing(
            $this->sellerA,
            'Test Keyboard',
            49.95
        );

        $listings = $this->getSellerListings($this->sellerA);

        $matching = array_values(array_filter(
            $listings,
            fn ($listing) =>
                (int) $listing['listing_id'] === $listingId
        ));

        $this->assertCount(1, $matching);

        $listing = $matching[0];

        $this->assertSame('Test Keyboard', $listing['item_name']);
        $this->assertEquals(49.95, (float) $listing['price']);
        $this->assertSame('good', $listing['condition']);
        $this->assertSame(1, (int) $listing['quantity']);
        $this->assertSame('shipping', $listing['fulfillment_type']);
        $this->assertSame('active', $listing['listing_status']);
    }

    public function testSellerWithNoListingsGetsEmptyResult(): void
    {
        $listings = $this->getSellerListings($this->sellerA);

        $this->assertSame([], $listings);
    }

    public function testNewestListingsAppearFirst(): void
    {
        $firstId = $this->createListing(
            $this->sellerA,
            'Older Listing'
        );

        $secondId = $this->createListing(
            $this->sellerA,
            'Newer Listing'
        );

        $listings = $this->getSellerListings($this->sellerA);

        $ids = array_map(
            'intval',
            array_column($listings, 'listing_id')
        );

        $this->assertSame($secondId, $ids[0]);
        $this->assertSame($firstId, $ids[1]);
    }

    public function testSellerCanViewListingsWithDifferentStatuses(): void
    {
        $statuses = [
            'active',
            'hidden',
            'draft',
            'sold',
            'archived',
        ];

        $listingIds = [];

        foreach ($statuses as $status) {
            $listingIds[$status] = $this->createListing(
                $this->sellerA,
                "Test $status Listing",
                49.95,
                $status
            );
        }

        $listings = $this->getSellerListings($this->sellerA);

        $this->assertCount(5, $listings);

        foreach ($statuses as $status) {
            $matching = array_values(array_filter(
                $listings,
                fn ($listing) =>
                    (int) $listing['listing_id'] === $listingIds[$status]
            ));

            $this->assertCount(1, $matching);
            $this->assertSame(
                $status,
                $matching[0]['listing_status']
            );
        }
    }

    public function testSellerCannotViewOtherSellersListingsAcrossStatuses(): void
    {
        $statuses = [
            'active',
            'hidden',
            'draft',
            'sold',
            'archived',
        ];

        $otherSellerListingIds = [];

        foreach ($statuses as $status) {
            $otherSellerListingIds[] = $this->createListing(
                $this->sellerB,
                "Seller B $status Listing",
                49.95,
                $status
            );
        }

        $ownListingId = $this->createListing(
            $this->sellerA,
            'Seller A Listing'
        );

        $listings = $this->getSellerListings($this->sellerA);

        $ids = array_map(
            'intval',
            array_column($listings, 'listing_id')
        );

        $this->assertCount(1, $listings);
        $this->assertContains($ownListingId, $ids);

        foreach ($otherSellerListingIds as $listingId) {
            $this->assertNotContains($listingId, $ids);
        }
    }
}
