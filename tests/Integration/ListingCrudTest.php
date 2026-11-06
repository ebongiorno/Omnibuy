<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

final class ListingCrudTest extends DatabaseTestCase
{
    private int $sellerId;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = $this->uniqueSuffix();

        $this->sellerId = $this->createUser('listing_seller');

        $this->pdo->prepare('
            INSERT INTO seller_profiles (
                user_id,
                store_name,
                payout_account_reference
            ) VALUES (
                :user_id,
                :store_name,
                :payout_reference
            )
        ')->execute([
            'user_id' => $this->sellerId,
            'store_name' => "Listing Test Store $suffix",
            'payout_reference' => "LISTING_TEST_$suffix",
        ]);

        $this->pdo->prepare('
            INSERT INTO categories (category_name)
            VALUES (:category_name)
        ')->execute([
            'category_name' => "Listing Test Category $suffix",
        ]);

        $this->categoryId = (int) $this->pdo->lastInsertId();
    }

    private function createTestListing(): int
    {
        $stmt = $this->pdo->prepare('
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
                :item_name,
                :description,
                "fixed_price",
                "shipping",
                :price,
                "good",
                1,
                "active"
            )
        ');

        $stmt->execute([
            'seller_id' => $this->sellerId,
            'category_id' => $this->categoryId,
            'item_name' => 'Test Laptop',
            'description' => 'Laptop used for CRUD testing',
            'price' => 499.99,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function testCreateListing(): void
    {
        $listingId = $this->createTestListing();

        $this->assertGreaterThan(0, $listingId);
    }

    public function testReadListing(): void
    {
        $listingId = $this->createTestListing();

        $stmt = $this->pdo->prepare('
            SELECT item_name, price, seller_user_id
            FROM listings
            WHERE listing_id = :listing_id
        ');

        $stmt->execute([
            'listing_id' => $listingId,
        ]);

        $listing = $stmt->fetch();

        $this->assertIsArray($listing);
        $this->assertSame('Test Laptop', $listing['item_name']);
        $this->assertEquals(499.99, (float) $listing['price']);
        $this->assertSame(
            $this->sellerId,
            (int) $listing['seller_user_id']
        );
    }

    public function testUpdateListing(): void
    {
        $listingId = $this->createTestListing();

        $stmt = $this->pdo->prepare('
            UPDATE listings
            SET price = :price
            WHERE listing_id = :listing_id
              AND seller_user_id = :seller_id
        ');

        $stmt->execute([
            'price' => 399.99,
            'listing_id' => $listingId,
            'seller_id' => $this->sellerId,
        ]);

        $this->assertSame(1, $stmt->rowCount());

        $check = $this->pdo->prepare('
            SELECT price
            FROM listings
            WHERE listing_id = :listing_id
        ');

        $check->execute([
            'listing_id' => $listingId,
        ]);

        $this->assertEquals(
            399.99,
            (float) $check->fetchColumn()
        );
    }

    public function testDeleteListing(): void
    {
        $listingId = $this->createTestListing();

        $stmt = $this->pdo->prepare('
            DELETE FROM listings
            WHERE listing_id = :listing_id
        ');

        $stmt->execute([
            'listing_id' => $listingId,
        ]);

        $check = $this->pdo->prepare('
            SELECT COUNT(*)
            FROM listings
            WHERE listing_id = :listing_id
        ');

        $check->execute([
            'listing_id' => $listingId,
        ]);

        $this->assertSame(
            0,
            (int) $check->fetchColumn()
        );
    }

    public function testOtherSellerCannotUpdateListingPrice(): void
    {
        $listingId = $this->createTestListing();

        $otherSellerId = $this->createUser('other_listing_seller');

        $stmt = $this->pdo->prepare('
            UPDATE listings
            SET price = :price
            WHERE listing_id = :listing_id
              AND seller_user_id = :seller_id
        ');

        $stmt->execute([
            'price' => 1.00,
            'listing_id' => $listingId,
            'seller_id' => $otherSellerId,
        ]);

        $this->assertSame(0, $stmt->rowCount());

        $check = $this->pdo->prepare('
            SELECT price
            FROM listings
            WHERE listing_id = :listing_id
        ');

        $check->execute([
            'listing_id' => $listingId,
        ]);

        $this->assertEquals(
            499.99,
            (float) $check->fetchColumn()
        );
    }
}
