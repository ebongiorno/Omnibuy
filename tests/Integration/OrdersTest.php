<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../../includes/orders.php';

/**
 * loadBuyerOrders() for the My Orders page against the real database.
 */
final class OrdersTest extends DatabaseTestCase
{
    private int $buyer;
    private int $otherBuyer;
    private int $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = $this->uniqueSuffix();

        $seller = $this->createUser('orders_seller');
        $this->pdo->prepare('
            INSERT INTO seller_profiles (user_id, store_name, payout_account_reference)
            VALUES (:user_id, :store_name, :payout_reference)
        ')->execute([
            'user_id' => $seller,
            'store_name' => "Orders Store $suffix",
            'payout_reference' => "ORDERS_$suffix",
        ]);

        $this->pdo->prepare('INSERT INTO categories (category_name) VALUES (:name)')
            ->execute(['name' => "Orders Category $suffix"]);
        $categoryId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare('
            INSERT INTO listings (
                seller_user_id, category_id, item_name, sale_type, fulfillment_type,
                price, `condition`, quantity, listing_status
            ) VALUES (
                :seller_id, :category_id, "Orders Test Lamp", "fixed_price", "shipping",
                25.00, "good", 5, "active"
            )
        ')->execute(['seller_id' => $seller, 'category_id' => $categoryId]);
        $this->listing = (int) $this->pdo->lastInsertId();

        $this->buyer = $this->createUser('orders_buyer');
        $this->otherBuyer = $this->createUser('orders_other');
    }

    private function createOrder(int $buyerId, string $createdAt, int $quantity = 1): int
    {
        $this->pdo->prepare('
            INSERT INTO addresses (user_id, address_line_1, city, state_name, zip_code, country, address_type)
            VALUES (:user_id, "1 Main St", "Fresno", "CA", "93740", "USA", "billing")
        ')->execute(['user_id' => $buyerId]);
        $addressId = (int) $this->pdo->lastInsertId();

        $subtotal = 25.00 * $quantity;
        $this->pdo->prepare('
            INSERT INTO orders (buyer_user_id, billing_address_id, subtotal, total_amount, created_at)
            VALUES (:buyer_id, :address_id, :subtotal, :total, :created_at)
        ')->execute([
            'buyer_id' => $buyerId,
            'address_id' => $addressId,
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'created_at' => $createdAt,
        ]);
        $orderId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare('
            INSERT INTO order_items (order_id, listing_id, quantity, unit_price, fulfillment_type)
            VALUES (:order_id, :listing_id, :quantity, 25.00, "shipping")
        ')->execute([
            'order_id' => $orderId,
            'listing_id' => $this->listing,
            'quantity' => $quantity,
        ]);

        return $orderId;
    }

    private function addImage(string $url, int $displayOrder): void
    {
        $this->pdo->prepare('
            INSERT INTO listing_images (listing_id, image_url, display_order)
            VALUES (:listing_id, :url, :display_order)
        ')->execute([
            'listing_id' => $this->listing,
            'url' => $url,
            'display_order' => $displayOrder,
        ]);
    }

    public function testBuyerWithNoOrdersGetsEmptyList(): void
    {
        $this->assertSame([], loadBuyerOrders($this->pdo, $this->buyer));
    }

    public function testBuyerSeesOnlyTheirOwnOrders(): void
    {
        $own = $this->createOrder($this->buyer, '2026-10-01 10:00:00');
        $this->createOrder($this->otherBuyer, '2026-10-02 10:00:00');

        $orders = loadBuyerOrders($this->pdo, $this->buyer);

        $this->assertSame([$own], array_map(fn ($o) => (int) $o['order_id'], $orders));
    }

    public function testOrdersAreNewestFirst(): void
    {
        $older = $this->createOrder($this->buyer, '2026-09-01 10:00:00');
        $newer = $this->createOrder($this->buyer, '2026-10-01 10:00:00');

        $orders = loadBuyerOrders($this->pdo, $this->buyer);

        $this->assertSame([$newer, $older], array_map(fn ($o) => (int) $o['order_id'], $orders));
    }

    public function testOrderIncludesItsItemsWithListingAndSeller(): void
    {
        $this->createOrder($this->buyer, '2026-10-01 10:00:00', 3);

        $items = loadBuyerOrders($this->pdo, $this->buyer)[0]['items'];

        $this->assertCount(1, $items);
        $this->assertSame('Orders Test Lamp', $items[0]['item_name']);
        $this->assertStringStartsWith('Orders Store ', $items[0]['store_name']);
        $this->assertSame(3, (int) $items[0]['quantity']);
        $this->assertNull($items[0]['image_url']);
    }

    public function testItemImageIsTheListingsFirstPhoto(): void
    {
        $this->addImage('/uploads/listings/second.jpg', 2);
        $this->addImage('/uploads/listings/first.jpg', 1);
        $this->createOrder($this->buyer, '2026-10-01 10:00:00');

        $items = loadBuyerOrders($this->pdo, $this->buyer)[0]['items'];

        $this->assertSame('/uploads/listings/first.jpg', $items[0]['image_url']);
    }

    public function testStatusFormatting(): void
    {
        $this->assertSame('Ready for meetup', formatOrderStatus('ready_for_meetup'));
        $this->assertSame('done', orderStatusTone('completed'));
        $this->assertSame('cancelled', orderStatusTone('refunded'));
        $this->assertSame('pending', orderStatusTone('shipped'));
        $this->assertSame('$1,234.50', formatMoney('1234.5'));
    }
}
