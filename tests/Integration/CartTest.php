<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

/**
 * Checks the cart rules the carts and cart_items tables enforce.
 */
final class CartTest extends DatabaseTestCase
{
    private const INSERT_CART_ITEM = '
        INSERT INTO cart_items (cart_id, listing_id, quantity)
        VALUES (:cart_id, :listing_id, :quantity)
    ';

    private int $buyerId;
    private int $cartId;
    private int $listingId;
    private int $secondListingId;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = $this->uniqueSuffix();
        $sellerId = $this->createUser('seller');

        $this->pdo->prepare('
            INSERT INTO seller_profiles (user_id, store_name, payout_account_reference)
            VALUES (:user_id, :store_name, :payout_ref)
        ')->execute([
            'user_id' => $sellerId,
            'store_name' => "Test Store $suffix",
            'payout_ref' => "TEST_PAYOUT_$suffix",
        ]);

        $this->pdo->prepare('INSERT INTO categories (category_name) VALUES (:name)')
            ->execute(['name' => "Test Category $suffix"]);
        $categoryId = (int) $this->pdo->lastInsertId();

        $this->listingId = $this->createListing($sellerId, $categoryId, "Test Listing $suffix A", 20.00);
        $this->secondListingId = $this->createListing($sellerId, $categoryId, "Test Listing $suffix B", 15.00);

        $this->buyerId = $this->createUser('buyer');

        $this->pdo->prepare('INSERT INTO carts (user_id) VALUES (:user_id)')
            ->execute(['user_id' => $this->buyerId]);
        $this->cartId = (int) $this->pdo->lastInsertId();
    }

    public function testBuyerCanAddItemToCart(): void
    {
        $this->addToCart($this->listingId, 2);

        $stmt = $this->pdo->prepare('
            SELECT ci.quantity, l.price
            FROM cart_items ci
            JOIN listings l ON l.listing_id = ci.listing_id
            WHERE ci.cart_id = :cart_id AND ci.listing_id = :listing_id
        ');
        $stmt->execute(['cart_id' => $this->cartId, 'listing_id' => $this->listingId]);
        $row = $stmt->fetch();

        $this->assertIsArray($row);
        $this->assertSame(2, (int) $row['quantity']);
        $this->assertSame(20.00, (float) $row['price']);
    }

    public function testCartCanHoldMultipleDifferentListings(): void
    {
        $this->addToCart($this->listingId, 1);
        $this->addToCart($this->secondListingId, 3);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cart_items WHERE cart_id = :cart_id');
        $stmt->execute(['cart_id' => $this->cartId]);

        $this->assertSame(2, (int) $stmt->fetchColumn());
    }

    public function testSameListingCannotBeAddedTwice(): void
    {
        $this->addToCart($this->listingId, 1);

        $this->assertRejectedByDatabase(self::INSERT_CART_ITEM, [
            'cart_id' => $this->cartId,
            'listing_id' => $this->listingId,
            'quantity' => 1,
        ], 'Adding the same listing to a cart twice should be rejected');
    }

    public function testQuantityOfZeroIsRejected(): void
    {
        $this->assertRejectedByDatabase(self::INSERT_CART_ITEM, [
            'cart_id' => $this->cartId,
            'listing_id' => $this->secondListingId,
            'quantity' => 0,
        ], 'A cart item quantity of zero should be rejected');
    }

    public function testQuantityOfOneIsAccepted(): void
    {
        // Boundary: 1 is the smallest valid quantity.
        $this->addToCart($this->secondListingId, 1);

        $this->addToAssertionCount(1);
    }

    public function testUserCannotHaveSecondCart(): void
    {
        $this->assertRejectedByDatabase(
            'INSERT INTO carts (user_id) VALUES (:user_id)',
            ['user_id' => $this->buyerId],
            'A user should not be able to have a second cart'
        );
    }

    private function addToCart(int $listingId, int $quantity): void
    {
        $this->pdo->prepare(self::INSERT_CART_ITEM)->execute([
            'cart_id' => $this->cartId,
            'listing_id' => $listingId,
            'quantity' => $quantity,
        ]);
    }

    private function createListing(int $sellerId, int $categoryId, string $name, float $price): int
    {
        $this->pdo->prepare('
            INSERT INTO listings (
                seller_user_id, category_id, item_name, sale_type, fulfillment_type,
                shipping_cost, estimated_shipping_days, price, `condition`, quantity, listing_status
            ) VALUES (
                :seller_user_id, :category_id, :item_name, "fixed_price", "shipping",
                5.00, 3, :price, "good", 5, "active"
            )
        ')->execute([
            'seller_user_id' => $sellerId,
            'category_id' => $categoryId,
            'item_name' => $name,
            'price' => $price,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
