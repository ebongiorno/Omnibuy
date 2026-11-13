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
            oi.return_window_days, oi.cancelled_at,
            DATE_ADD(o.created_at, INTERVAL oi.return_window_days DAY) AS return_deadline,
            (
                oi.item_status <> 'cancelled'
                AND oi.return_window_days > 0
                AND NOW() <= DATE_ADD(o.created_at, INTERVAL oi.return_window_days DAY)
            ) AS can_cancel,
            l.item_name, sp.store_name,
            (
                SELECT li.image_url
                FROM listing_images li
                WHERE li.listing_id = oi.listing_id
                ORDER BY li.display_order
                LIMIT 1
            ) AS image_url
        FROM order_items oi
        JOIN orders o ON o.order_id = oi.order_id
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

// =========================================
// CANCEL / RETURN AN ORDER ITEM
// =========================================

/**
 * Cancels one item of the buyer's order, if it is still inside the return
 * window it was bought with (order date + return_window_days). The
 * quantity goes back into the listing's stock, and a listing that sold out
 * because of this purchase becomes active again. When every item in the
 * order is cancelled, the order is cancelled too (a paid order is marked
 * refunded).
 *
 * The time check uses the database clock, the same clock as created_at.
 *
 * @return array{ok: bool, message: string}
 */
function cancelOrderItem(PDO $pdo, int $buyerId, int $orderItemId): array
{
    // Callers (such as tests) may already be in a transaction: use a savepoint then.
    $ownTransaction = !$pdo->inTransaction();
    $ownTransaction ? $pdo->beginTransaction() : $pdo->exec('SAVEPOINT cancel_item');
    $finish = static function (bool $commit) use ($pdo, $ownTransaction): void {
        if ($ownTransaction) {
            $commit ? $pdo->commit() : $pdo->rollBack();
        } else {
            $pdo->exec($commit ? 'RELEASE SAVEPOINT cancel_item' : 'ROLLBACK TO SAVEPOINT cancel_item');
        }
    };

    try {
        // Joining orders on buyer_user_id means a buyer can only cancel their own items.
        $stmt = $pdo->prepare('
            SELECT
                oi.order_item_id, oi.order_id, oi.listing_id, oi.quantity,
                oi.item_status, oi.return_window_days,
                DATE_ADD(o.created_at, INTERVAL oi.return_window_days DAY) AS return_deadline,
                NOW() <= DATE_ADD(o.created_at, INTERVAL oi.return_window_days DAY) AS in_window
            FROM order_items oi
            JOIN orders o ON o.order_id = oi.order_id
            WHERE oi.order_item_id = :order_item_id AND o.buyer_user_id = :buyer_id
            FOR UPDATE
        ');
        $stmt->execute(['order_item_id' => $orderItemId, 'buyer_id' => $buyerId]);
        $item = $stmt->fetch();

        $problem = match (true) {
            $item === false => 'That order item could not be found.',
            $item['item_status'] === 'cancelled' => 'This item is already cancelled.',
            (int) $item['return_window_days'] === 0 => 'This item was sold with no returns.',
            !(bool) $item['in_window'] => 'The return window for this item ended on '
                . date('F j, Y', strtotime((string) $item['return_deadline'])) . '.',
            default => null,
        };
        if ($problem !== null) {
            $finish(false);
            return ['ok' => false, 'message' => $problem];
        }

        $pdo->prepare("
            UPDATE order_items
            SET item_status = 'cancelled', cancelled_at = NOW()
            WHERE order_item_id = :order_item_id
        ")->execute(['order_item_id' => $orderItemId]);

        // listing_status is assigned first, so it sees the old quantity:
        // only a listing that sold out (quantity 0) is reactivated, not one
        // the seller marked sold by hand.
        $pdo->prepare("
            UPDATE listings
            SET listing_status = IF(listing_status = 'sold' AND quantity = 0, 'active', listing_status),
                quantity = quantity + :quantity
            WHERE listing_id = :listing_id
        ")->execute(['quantity' => $item['quantity'], 'listing_id' => $item['listing_id']]);

        $remaining = $pdo->prepare("
            SELECT COUNT(*) FROM order_items
            WHERE order_id = :order_id AND item_status <> 'cancelled'
        ");
        $remaining->execute(['order_id' => $item['order_id']]);

        if ((int) $remaining->fetchColumn() === 0) {
            $pdo->prepare("
                UPDATE orders
                SET order_status = 'cancelled',
                    payment_status = IF(payment_status = 'paid', 'refunded', payment_status)
                WHERE order_id = :order_id
            ")->execute(['order_id' => $item['order_id']]);
        }

        $finish(true);

        return ['ok' => true, 'message' => 'The item was cancelled.'];
    } catch (Throwable $e) {
        $finish(false);
        throw $e;
    }
}
