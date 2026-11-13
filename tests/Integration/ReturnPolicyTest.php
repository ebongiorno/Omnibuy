<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../../includes/cart.php';

/**
 * Return policies: checkout keeps the listing's window on the order item,
 * and cancelOrderItem() lets the buyer cancel only inside that window.
 */
final class ReturnPolicyTest extends DatabaseTestCase
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
        $this->seller = $this->createUser('return_seller');
        $this->pdo->prepare('
            INSERT INTO seller_profiles (user_id, store_name, payout_account_reference)
            VALUES (:user_id, :store_name, :payout_reference)
        ')->execute([
            'user_id' => $this->seller,
            'store_name' => "Return Store $suffix",
            'payout_reference' => "RETURN_$suffix",
        ]);

        $this->pdo->prepare('INSERT INTO categories (category_name) VALUES (:name)')
            ->execute(['name' => "Return Category $suffix"]);
        $this->categoryId = (int) $this->pdo->lastInsertId();

        $this->buyer = $this->createUser('return_buyer');
    }

    private function createListing(int $returnDays, int $quantity = 3): int
    {
        $this->pdo->prepare('
            INSERT INTO listings (
                seller_user_id, category_id, item_name, sale_type, fulfillment_type,
                shipping_cost, price, `condition`, quantity, return_policy_days, listing_status
            ) VALUES (
                :seller_id, :category_id, "Return Test Lamp", "fixed_price", "shipping",
                5.00, 20.00, "good", :quantity, :return_days, "active"
            )
        ')->execute([
            'seller_id' => $this->seller,
            'category_id' => $this->categoryId,
            'quantity' => $quantity,
            'return_days' => $returnDays,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Buys the listings through the real cart and checkout.
     *
     * @param array<int, int> $quantities listing_id => quantity
     * @return array{order_id: int, items: array<int, int>} items: listing_id => order_item_id
     */
    private function buy(array $quantities, ?int $buyer = null): array
    {
        $buyer ??= $this->buyer;
        foreach ($quantities as $listingId => $quantity) {
            addToCart($this->pdo, $buyer, $listingId, $quantity);
        }

        $result = placeOrder($this->pdo, $buyer, self::ADDRESS, null);
        self::assertSame([], $result['errors']);

        $stmt = $this->pdo->prepare('SELECT listing_id, order_item_id FROM order_items WHERE order_id = :id');
        $stmt->execute(['id' => $result['order_id']]);

        return [
            'order_id' => $result['order_id'],
            'items' => array_map('intval', array_column($stmt->fetchAll(), 'order_item_id', 'listing_id')),
        ];
    }

    private function backdateOrder(int $orderId, int $days): void
    {
        $this->pdo->prepare('UPDATE orders SET created_at = NOW() - INTERVAL :days DAY WHERE order_id = :id')
            ->execute(['days' => $days, 'id' => $orderId]);
    }

    private function row(string $sql, int $id): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }

    public function testCheckoutKeepsTheListingsReturnWindowOnTheOrderItem(): void
    {
        $listing = $this->createListing(14);
        $order = $this->buy([$listing => 1]);

        // The seller changing the policy later doesn't affect this purchase.
        $this->pdo->prepare('UPDATE listings SET return_policy_days = 0 WHERE listing_id = :id')
            ->execute(['id' => $listing]);

        $item = $this->row('SELECT return_window_days FROM order_items WHERE order_item_id = :id', $order['items'][$listing]);
        self::assertSame(14, (int) $item['return_window_days']);
        self::assertTrue(cancelOrderItem($this->pdo, $this->buyer, $order['items'][$listing])['ok']);
    }

    public function testBuyerCanCancelInsideTheWindowAndStockComesBack(): void
    {
        $listing = $this->createListing(30, 3);
        $order = $this->buy([$listing => 2]);
        $this->backdateOrder($order['order_id'], 29);

        $result = cancelOrderItem($this->pdo, $this->buyer, $order['items'][$listing]);

        self::assertTrue($result['ok'], $result['message']);
        $item = $this->row('SELECT item_status, cancelled_at FROM order_items WHERE order_item_id = :id', $order['items'][$listing]);
        self::assertSame('cancelled', $item['item_status']);
        self::assertNotNull($item['cancelled_at']);
        self::assertSame(3, (int) $this->row('SELECT quantity FROM listings WHERE listing_id = :id', $listing)['quantity']);

        $orderRow = $this->row('SELECT order_status FROM orders WHERE order_id = :id', $order['order_id']);
        self::assertSame('cancelled', $orderRow['order_status'], 'Cancelling the only item cancels the order.');
    }

    public function testCancellingTheLastUnitReactivatesTheSoldOutListing(): void
    {
        $listing = $this->createListing(7, 1);
        $order = $this->buy([$listing => 1]);
        self::assertSame('sold', $this->row('SELECT listing_status FROM listings WHERE listing_id = :id', $listing)['listing_status']);

        cancelOrderItem($this->pdo, $this->buyer, $order['items'][$listing]);

        $row = $this->row('SELECT listing_status, quantity FROM listings WHERE listing_id = :id', $listing);
        self::assertSame('active', $row['listing_status']);
        self::assertSame(1, (int) $row['quantity']);
    }

    public function testListingTheSellerMarkedSoldStaysSold(): void
    {
        $listing = $this->createListing(7, 3);
        $order = $this->buy([$listing => 1]);
        $this->pdo->prepare("UPDATE listings SET listing_status = 'sold' WHERE listing_id = :id")
            ->execute(['id' => $listing]);

        cancelOrderItem($this->pdo, $this->buyer, $order['items'][$listing]);

        self::assertSame('sold', $this->row('SELECT listing_status FROM listings WHERE listing_id = :id', $listing)['listing_status']);
    }

    public function testCancellingOneOfSeveralItemsLeavesTheOrderOpen(): void
    {
        $lamp = $this->createListing(30);
        $chair = $this->createListing(30);
        $order = $this->buy([$lamp => 1, $chair => 1]);

        cancelOrderItem($this->pdo, $this->buyer, $order['items'][$lamp]);

        self::assertSame('pending', $this->row('SELECT order_status FROM orders WHERE order_id = :id', $order['order_id'])['order_status']);
        self::assertSame('pending', $this->row('SELECT item_status FROM order_items WHERE order_item_id = :id', $order['items'][$chair])['item_status']);
    }

    public function testNoReturnItemsCantBeCancelled(): void
    {
        $listing = $this->createListing(0);
        $order = $this->buy([$listing => 1]);

        $result = cancelOrderItem($this->pdo, $this->buyer, $order['items'][$listing]);

        self::assertFalse($result['ok']);
        self::assertStringContainsString('no returns', $result['message']);
        self::assertSame('pending', $this->row('SELECT item_status FROM order_items WHERE order_item_id = :id', $order['items'][$listing])['item_status']);
    }

    public function testItemsCantBeCancelledAfterTheWindowEnds(): void
    {
        $listing = $this->createListing(30, 3);
        $order = $this->buy([$listing => 1]);
        $this->backdateOrder($order['order_id'], 31);

        $result = cancelOrderItem($this->pdo, $this->buyer, $order['items'][$listing]);

        self::assertFalse($result['ok']);
        self::assertStringContainsString('return window', $result['message']);
        self::assertSame(2, (int) $this->row('SELECT quantity FROM listings WHERE listing_id = :id', $listing)['quantity']);
    }

    public function testBuyersCantCancelSomeoneElsesItem(): void
    {
        $listing = $this->createListing(30);
        $order = $this->buy([$listing => 1]);
        $stranger = $this->createUser('return_stranger');

        $result = cancelOrderItem($this->pdo, $stranger, $order['items'][$listing]);

        self::assertFalse($result['ok']);
        self::assertSame('That order item could not be found.', $result['message']);
        self::assertSame('pending', $this->row('SELECT item_status FROM order_items WHERE order_item_id = :id', $order['items'][$listing])['item_status']);
    }

    public function testAnItemCantBeCancelledTwice(): void
    {
        $listing = $this->createListing(30, 3);
        $order = $this->buy([$listing => 1]);

        cancelOrderItem($this->pdo, $this->buyer, $order['items'][$listing]);
        $second = cancelOrderItem($this->pdo, $this->buyer, $order['items'][$listing]);

        self::assertFalse($second['ok']);
        self::assertSame(3, (int) $this->row('SELECT quantity FROM listings WHERE listing_id = :id', $listing)['quantity'], 'Stock is restored only once.');
    }

    public function testMyOrdersShowsWhetherAnItemCanBeCancelled(): void
    {
        $open = $this->createListing(30);
        $none = $this->createListing(0);
        $order = $this->buy([$open => 1, $none => 1]);

        $items = array_column(loadBuyerOrders($this->pdo, $this->buyer)[0]['items'], null, 'listing_id');

        self::assertTrue((bool) $items[$open]['can_cancel']);
        self::assertFalse((bool) $items[$none]['can_cancel']);
        self::assertNotNull($items[$open]['return_deadline']);
    }

    public function testDatabaseRejectsAReturnPolicyOverThirtyDays(): void
    {
        $this->assertRejectedByDatabase(
            'UPDATE listings SET return_policy_days = 31 WHERE listing_id = :id',
            ['id' => $this->createListing(5)],
            'return_policy_days must be at most 30.'
        );
    }
}
