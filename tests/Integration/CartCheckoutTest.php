<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../../includes/cart.php';

/**
 * Cart rules (addToCart / updateCartQuantity / loadCart) and checkout
 * (placeOrder) against the real database.
 */
final class CartCheckoutTest extends DatabaseTestCase
{
    private const ADDRESS = [
        'address_line_1' => '800 N State College Blvd',
        'address_line_2' => '',
        'city' => 'Fullerton',
        'state_name' => 'CA',
        'zip_code' => '92831',
        'country' => 'USA',
    ];

    private int $seller;
    private int $buyer;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = $this->uniqueSuffix();
        $this->seller = $this->createUser('cart_seller');
        $this->pdo->prepare('
            INSERT INTO seller_profiles (user_id, store_name, payout_account_reference)
            VALUES (:user_id, :store_name, :payout_reference)
        ')->execute([
            'user_id' => $this->seller,
            'store_name' => "Cart Store $suffix",
            'payout_reference' => "CART_$suffix",
        ]);

        $this->pdo->prepare('INSERT INTO categories (category_name) VALUES (:name)')
            ->execute(['name' => "Cart Category $suffix"]);
        $this->categoryId = (int) $this->pdo->lastInsertId();

        $this->buyer = $this->createUser('cart_buyer');
    }

    private function createListing(array $overrides = []): int
    {
        $values = array_merge([
            'item_name' => 'Cart Test Lamp',
            'sale_type' => 'fixed_price',
            'fulfillment_type' => 'shipping',
            'shipping_cost' => 5.00,
            'price' => 20.00,
            'quantity' => 3,
            'listing_status' => 'active',
        ], $overrides);

        $this->pdo->prepare('
            INSERT INTO listings (
                seller_user_id, category_id, item_name, sale_type, fulfillment_type,
                shipping_cost, price, `condition`, quantity, listing_status
            ) VALUES (
                :seller_id, :category_id, :item_name, :sale_type, :fulfillment_type,
                :shipping_cost, :price, "good", :quantity, :listing_status
            )
        ')->execute($values + ['seller_id' => $this->seller, 'category_id' => $this->categoryId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function listingRow(int $listingId): array
    {
        $stmt = $this->pdo->prepare('SELECT quantity, listing_status FROM listings WHERE listing_id = :id');
        $stmt->execute(['id' => $listingId]);

        return $stmt->fetch();
    }

    // ---------- cart ----------

    public function testAddingAListingPutsItInTheBuyersCart(): void
    {
        $listing = $this->createListing();

        $result = addToCart($this->pdo, $this->buyer, $listing, 2);

        self::assertTrue($result['ok']);
        $cart = loadCart($this->pdo, $this->buyer);
        self::assertCount(1, $cart['items']);
        self::assertSame(2, (int) $cart['items'][0]['quantity']);
        self::assertSame(2, cartItemCount($this->pdo, $this->buyer));
    }

    public function testAddingTheSameListingTwiceCombinesUpToTheStock(): void
    {
        $listing = $this->createListing(['quantity' => 3]);

        addToCart($this->pdo, $this->buyer, $listing, 2);
        $result = addToCart($this->pdo, $this->buyer, $listing, 5);

        self::assertTrue($result['ok']);
        self::assertStringContainsString('Only 3 available', $result['message']);
        self::assertSame(3, cartItemCount($this->pdo, $this->buyer));
        self::assertCount(1, loadCart($this->pdo, $this->buyer)['items']);
    }

    #[DataProvider('unbuyableListings')]
    public function testListingsThatCantBeBoughtAreRejected(array $overrides, string $expected): void
    {
        $listing = $this->createListing($overrides);

        $result = addToCart($this->pdo, $this->buyer, $listing, 1);

        self::assertFalse($result['ok']);
        self::assertStringContainsString($expected, $result['message']);
        self::assertSame(0, cartItemCount($this->pdo, $this->buyer));
    }

    public static function unbuyableListings(): array
    {
        return [
            'hidden' => [['listing_status' => 'hidden'], 'no longer available'],
            'sold' => [['listing_status' => 'sold'], 'no longer available'],
            'draft' => [['listing_status' => 'draft'], 'no longer available'],
            'auction' => [['sale_type' => 'auction'], 'Auction'],
            'out of stock' => [['quantity' => 0], 'out of stock'],
        ];
    }

    public function testSellersCantBuyTheirOwnListing(): void
    {
        $listing = $this->createListing();

        $result = addToCart($this->pdo, $this->seller, $listing, 1);

        self::assertFalse($result['ok']);
        self::assertSame(0, cartItemCount($this->pdo, $this->seller));
    }

    public function testListingFromSuspendedSellerIsRejected(): void
    {
        $listing = $this->createListing();
        $this->pdo->prepare("UPDATE users SET account_status = 'suspended' WHERE user_id = :id")
            ->execute(['id' => $this->seller]);

        self::assertFalse(addToCart($this->pdo, $this->buyer, $listing, 1)['ok']);
    }

    public function testZeroOrNegativeQuantityIsRejected(): void
    {
        $listing = $this->createListing();

        self::assertFalse(addToCart($this->pdo, $this->buyer, $listing, 0)['ok']);
        self::assertFalse(addToCart($this->pdo, $this->buyer, $listing, -2)['ok']);
    }

    public function testUpdatingQuantityIsCappedAtStockAndZeroRemoves(): void
    {
        $listing = $this->createListing(['quantity' => 4]);
        addToCart($this->pdo, $this->buyer, $listing, 1);

        updateCartQuantity($this->pdo, $this->buyer, $listing, 10);
        self::assertSame(4, cartItemCount($this->pdo, $this->buyer));

        updateCartQuantity($this->pdo, $this->buyer, $listing, 0);
        self::assertSame(0, cartItemCount($this->pdo, $this->buyer));
    }

    public function testUsersCanOnlyChangeTheirOwnCart(): void
    {
        $listing = $this->createListing();
        addToCart($this->pdo, $this->buyer, $listing, 2);
        $other = $this->createUser('cart_other');

        $result = updateCartQuantity($this->pdo, $other, $listing, 1);
        removeFromCart($this->pdo, $other, $listing);

        self::assertFalse($result['ok']);
        self::assertSame(2, cartItemCount($this->pdo, $this->buyer));
    }

    public function testCartTotalsIncludeShippingPerLineAndSkipProblemLines(): void
    {
        $lamp = $this->createListing(['price' => 20.00, 'shipping_cost' => 5.00]);
        $chair = $this->createListing([
            'item_name' => 'Meetup Chair', 'fulfillment_type' => 'meetup',
            'shipping_cost' => null, 'price' => 40.00,
        ]);
        $gone = $this->createListing(['item_name' => 'Gone Soon']);
        addToCart($this->pdo, $this->buyer, $lamp, 2);
        addToCart($this->pdo, $this->buyer, $chair, 1);
        addToCart($this->pdo, $this->buyer, $gone, 1);
        $this->pdo->prepare("UPDATE listings SET listing_status = 'sold' WHERE listing_id = :id")
            ->execute(['id' => $gone]);

        $cart = loadCart($this->pdo, $this->buyer);

        self::assertSame(80.0, $cart['summary']['subtotal']);
        self::assertSame(5.0, $cart['summary']['shipping']);
        self::assertSame(85.0, $cart['summary']['total']);
        self::assertFalse($cart['can_checkout'], 'A sold item in the cart must block checkout.');
        self::assertNotNull($cart['items'][2]['problem']);
    }

    // ---------- checkout ----------

    public function testCheckoutCreatesTheOrderDecrementsStockAndEmptiesTheCart(): void
    {
        $lamp = $this->createListing(['quantity' => 3, 'price' => 20.00, 'shipping_cost' => 5.00]);
        addToCart($this->pdo, $this->buyer, $lamp, 2);

        $result = placeOrder($this->pdo, $this->buyer, self::ADDRESS, null);

        self::assertSame([], $result['errors']);
        self::assertNotNull($result['order_id']);

        $order = $this->pdo->prepare('SELECT * FROM orders WHERE order_id = :id');
        $order->execute(['id' => $result['order_id']]);
        $order = $order->fetch();
        self::assertSame($this->buyer, (int) $order['buyer_user_id']);
        self::assertSame('40.00', $order['subtotal']);
        self::assertSame('5.00', $order['shipping_cost']);
        self::assertSame('45.00', $order['total_amount']);
        self::assertSame('pending', $order['payment_status']);
        self::assertNotNull($order['shipping_address_id'], 'Shipped items need a shipping address.');

        $items = $this->pdo->prepare('SELECT * FROM order_items WHERE order_id = :id');
        $items->execute(['id' => $result['order_id']]);
        $items = $items->fetchAll();
        self::assertCount(1, $items);
        self::assertSame(2, (int) $items[0]['quantity']);
        self::assertSame('20.00', $items[0]['unit_price']);

        self::assertSame(['quantity' => 1, 'listing_status' => 'active'], array_map(
            static fn ($v) => is_numeric($v) ? (int) $v : $v,
            $this->listingRow($lamp)
        ));
        self::assertSame(0, cartItemCount($this->pdo, $this->buyer));

        // The new order shows up on My Orders.
        self::assertSame($result['order_id'], (int) loadBuyerOrders($this->pdo, $this->buyer)[0]['order_id']);
    }

    public function testBuyingTheLastUnitMarksTheListingSold(): void
    {
        $listing = $this->createListing(['quantity' => 1]);
        addToCart($this->pdo, $this->buyer, $listing, 1);

        placeOrder($this->pdo, $this->buyer, self::ADDRESS, null);

        self::assertSame('sold', $this->listingRow($listing)['listing_status']);
        self::assertSame(0, (int) $this->listingRow($listing)['quantity']);
    }

    public function testMeetupOnlyOrderHasNoShippingAddress(): void
    {
        $listing = $this->createListing(['fulfillment_type' => 'meetup', 'shipping_cost' => null]);
        addToCart($this->pdo, $this->buyer, $listing, 1);

        $result = placeOrder($this->pdo, $this->buyer, self::ADDRESS, null);

        $stmt = $this->pdo->prepare('SELECT shipping_address_id, shipping_cost FROM orders WHERE order_id = :id');
        $stmt->execute(['id' => $result['order_id']]);
        $order = $stmt->fetch();
        self::assertNull($order['shipping_address_id']);
        self::assertSame('0.00', $order['shipping_cost']);
    }

    public function testCheckoutFailsWhenStockRanOutAfterAdding(): void
    {
        $listing = $this->createListing(['quantity' => 2]);
        addToCart($this->pdo, $this->buyer, $listing, 2);
        // Someone else bought one in the meantime.
        $this->pdo->prepare('UPDATE listings SET quantity = 1 WHERE listing_id = :id')->execute(['id' => $listing]);

        $result = placeOrder($this->pdo, $this->buyer, self::ADDRESS, null);

        self::assertNull($result['order_id']);
        self::assertArrayHasKey('cart', $result['errors']);
        self::assertSame(1, (int) $this->listingRow($listing)['quantity']);
        self::assertSame(2, cartItemCount($this->pdo, $this->buyer), 'A failed checkout keeps the cart.');

        $orders = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE buyer_user_id = :id');
        $orders->execute(['id' => $this->buyer]);
        self::assertSame(0, (int) $orders->fetchColumn());
    }

    public function testCheckoutWithAnEmptyCartFails(): void
    {
        $result = placeOrder($this->pdo, $this->buyer, self::ADDRESS, null);

        self::assertNull($result['order_id']);
        self::assertSame('Your cart is empty.', $result['errors']['cart']);
    }

    public function testMissingAddressFieldsAreReportedAndNoOrderIsCreated(): void
    {
        $listing = $this->createListing();
        addToCart($this->pdo, $this->buyer, $listing, 1);

        $result = placeOrder(
            $this->pdo,
            $this->buyer,
            ['address_line_1' => '', 'zip_code' => '!!'] + self::ADDRESS,
            null
        );

        self::assertNull($result['order_id']);
        self::assertArrayHasKey('billing_address_line_1', $result['errors']);
        self::assertArrayHasKey('billing_zip_code', $result['errors']);
        self::assertSame(1, cartItemCount($this->pdo, $this->buyer));
    }

    public function testSeparateShippingAddressIsSavedAndBecomesTheDefault(): void
    {
        $listing = $this->createListing();
        addToCart($this->pdo, $this->buyer, $listing, 1);
        $shipping = ['address_line_1' => '1 Shipping Way', 'city' => 'Irvine'] + self::ADDRESS;

        $result = placeOrder($this->pdo, $this->buyer, self::ADDRESS, $shipping);

        $stmt = $this->pdo->prepare('
            SELECT a.address_line_1, a.city
            FROM orders o JOIN addresses a ON a.address_id = o.shipping_address_id
            WHERE o.order_id = :id
        ');
        $stmt->execute(['id' => $result['order_id']]);
        self::assertSame(['address_line_1' => '1 Shipping Way', 'city' => 'Irvine'], $stmt->fetch());
        self::assertSame('1 Shipping Way', loadDefaultAddress($this->pdo, $this->buyer, 'shipping')['address_line_1']);
    }
}
