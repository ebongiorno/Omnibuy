<?php

/**
 * OmniBuy order history: the "My Orders" page for buyers.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

const ORDER_DEFAULT_IMAGE = '/assets/images/listing/listing-default-img.jpg';

/**
 * The buyer's orders, newest first, each with its items under 'items'.
 * Only orders placed by $buyerId are returned.
 */
function loadBuyerOrders(PDO $pdo, int $buyerId): array
{
    $stmt = $pdo->prepare('
        SELECT
            order_id, order_status, payment_status, subtotal, shipping_cost,
            tax_amount, total_amount, created_at
        FROM orders
        WHERE buyer_user_id = :buyer_id
        ORDER BY created_at DESC, order_id DESC
    ');
    $stmt->execute(['buyer_id' => $buyerId]);

    $orders = [];
    foreach ($stmt->fetchAll() as $order) {
        $order['items'] = [];
        $orders[(int) $order['order_id']] = $order;
    }

    if ($orders === []) {
        return [];
    }

    // One query for every order's items. The image is the listing's first
    // photo; seller_profiles is a LEFT JOIN so an item still shows if the
    // seller profile is gone.
    $placeholders = implode(', ', array_fill(0, count($orders), '?'));
    $items = $pdo->prepare("
        SELECT
            oi.order_id, oi.order_item_id, oi.listing_id, oi.quantity,
            oi.unit_price, oi.fulfillment_type, oi.item_status, oi.shipping_cost,
            l.item_name, sp.store_name,
            (
                SELECT li.image_url
                FROM listing_images li
                WHERE li.listing_id = oi.listing_id
                ORDER BY li.display_order
                LIMIT 1
            ) AS image_url
        FROM order_items oi
        JOIN listings l ON l.listing_id = oi.listing_id
        LEFT JOIN seller_profiles sp ON sp.user_id = l.seller_user_id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.order_item_id
    ");
    $items->execute(array_keys($orders));

    foreach ($items->fetchAll() as $item) {
        $orders[(int) $item['order_id']]['items'][] = $item;
    }

    return array_values($orders);
}

/**
 * Turns a status value such as 'ready_for_meetup' into 'Ready for meetup'.
 */
function formatOrderStatus(string $status): string
{
    return ucfirst(str_replace('_', ' ', $status));
}

/**
 * CSS modifier for a status badge: done, cancelled or pending styling.
 */
function orderStatusTone(string $status): string
{
    return match ($status) {
        'completed', 'paid' => 'done',
        'cancelled', 'failed', 'refunded' => 'cancelled',
        default => 'pending',
    };
}

function formatMoney(string|float $amount): string
{
    return '$' . number_format((float) $amount, 2);
}
