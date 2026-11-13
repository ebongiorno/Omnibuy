<?php

/**
 * OmniBuy shopping cart and checkout.
 *
 * Every rule is checked on the server: the quantity a browser sends is only
 * a request. A listing can go in the cart only when it is an active,
 * fixed-price listing with stock, from an active seller who isn't the buyer.
 * Checkout re-checks all of that under row locks before creating the order.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/orders.php';

const CART_ADDRESS_FIELDS = ['address_line_1', 'address_line_2', 'city', 'state_name', 'zip_code', 'country'];

// =========================================
// CART
// =========================================

/**
 * The user's cart_id, creating the cart the first time it is needed.
 */
function cartIdForUser(PDO $pdo, int $userId): int
{
    // INSERT IGNORE relies on carts.user_id being UNIQUE: at most one cart per user.
    $pdo->prepare('INSERT IGNORE INTO carts (user_id) VALUES (:user_id)')
        ->execute(['user_id' => $userId]);

    $stmt = $pdo->prepare('SELECT cart_id FROM carts WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);

    return (int) $stmt->fetchColumn();
}

/**
 * Why a listing can't be bought by this user, or null when it can.
 * $listing needs listing_status, sale_type, quantity, seller_user_id, seller_status.
 */
function listingPurchaseProblem(array $listing, int $buyerId): ?string
{
    if ($listing['listing_status'] !== 'active' || $listing['seller_status'] !== 'active') {
        return 'This listing is no longer available.';
    }
    if ($listing['sale_type'] !== 'fixed_price') {
        return 'Auction listings can\'t be bought from the cart.';
    }
    if ((int) $listing['seller_user_id'] === $buyerId) {
        return 'You can\'t buy your own listing.';
    }
    if ((int) $listing['quantity'] < 1) {
        return 'This listing is out of stock.';
    }

    return null;
}

/**
 * Adds a listing to the cart. If it is already there, the quantities are
 * combined, capped at the stock available.
 *
 * @return array{ok: bool, message: string}
 */
function addToCart(PDO $pdo, int $userId, int $listingId, int $quantity): array
{
    if ($quantity < 1) {
        return ['ok' => false, 'message' => 'Choose a quantity of at least 1.'];
    }

    $stmt = $pdo->prepare('
        SELECT l.listing_id, l.listing_status, l.sale_type, l.quantity, l.seller_user_id,
               u.account_status AS seller_status
        FROM listings l
        JOIN users u ON u.user_id = l.seller_user_id
        WHERE l.listing_id = :listing_id
    ');
    $stmt->execute(['listing_id' => $listingId]);
    $listing = $stmt->fetch();

    if ($listing === false) {
        return ['ok' => false, 'message' => 'This listing is no longer available.'];
    }

    $problem = listingPurchaseProblem($listing, $userId);
    if ($problem !== null) {
        return ['ok' => false, 'message' => $problem];
    }

    $cartId = cartIdForUser($pdo, $userId);
    $stock = (int) $listing['quantity'];

    $existing = $pdo->prepare('SELECT quantity FROM cart_items WHERE cart_id = :cart_id AND listing_id = :listing_id');
    $existing->execute(['cart_id' => $cartId, 'listing_id' => $listingId]);
    $inCart = (int) ($existing->fetchColumn() ?: 0);

    $newQuantity = min($inCart + $quantity, $stock);

    $pdo->prepare('
        INSERT INTO cart_items (cart_id, listing_id, quantity)
        VALUES (:cart_id, :listing_id, :quantity)
        ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)
    ')->execute(['cart_id' => $cartId, 'listing_id' => $listingId, 'quantity' => $newQuantity]);

    if ($inCart + $quantity > $stock) {
        return ['ok' => true, 'message' => "Only $stock available, so your cart now has $newQuantity."];
    }

    return ['ok' => true, 'message' => 'Added to your cart.'];
}

/**
 * Changes a cart line's quantity (capped at the stock available).
 * A quantity of 0 removes the line.
 *
 * @return array{ok: bool, message: string}
 */
function updateCartQuantity(PDO $pdo, int $userId, int $listingId, int $quantity): array
{
    if ($quantity < 1) {
        removeFromCart($pdo, $userId, $listingId);
        return ['ok' => true, 'message' => 'Item removed from your cart.'];
    }

    $stmt = $pdo->prepare('
        SELECT l.quantity
        FROM cart_items ci
        JOIN carts c ON c.cart_id = ci.cart_id
        JOIN listings l ON l.listing_id = ci.listing_id
        WHERE c.user_id = :user_id AND ci.listing_id = :listing_id
    ');
    $stmt->execute(['user_id' => $userId, 'listing_id' => $listingId]);
    $stock = $stmt->fetchColumn();

    if ($stock === false) {
        return ['ok' => false, 'message' => 'That item isn\'t in your cart.'];
    }

    $stock = (int) $stock;
    if ($stock < 1) {
        return ['ok' => false, 'message' => 'This listing is out of stock.'];
    }

    $newQuantity = min($quantity, $stock);

    // Joining carts limits the update to this user's own cart.
    $pdo->prepare('
        UPDATE cart_items ci
        JOIN carts c ON c.cart_id = ci.cart_id
        SET ci.quantity = :quantity
        WHERE c.user_id = :user_id AND ci.listing_id = :listing_id
    ')->execute(['quantity' => $newQuantity, 'user_id' => $userId, 'listing_id' => $listingId]);

    if ($quantity > $stock) {
        return ['ok' => true, 'message' => "Only $stock available, so the quantity is now $newQuantity."];
    }

    return ['ok' => true, 'message' => 'Cart updated.'];
}

function removeFromCart(PDO $pdo, int $userId, int $listingId): void
{
    $pdo->prepare('
        DELETE ci FROM cart_items ci
        JOIN carts c ON c.cart_id = ci.cart_id
        WHERE c.user_id = :user_id AND ci.listing_id = :listing_id
    ')->execute(['user_id' => $userId, 'listing_id' => $listingId]);
}

/**
 * Total quantity in the user's cart, for the header badge.
 */
function cartItemCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('
        SELECT COALESCE(SUM(ci.quantity), 0)
        FROM cart_items ci
        JOIN carts c ON c.cart_id = ci.cart_id
        WHERE c.user_id = :user_id
    ');
    $stmt->execute(['user_id' => $userId]);

    return (int) $stmt->fetchColumn();
}

/**
 * The user's cart lines, oldest first. Each line has a 'problem' message
 * (or null) and 'line_shipping' / 'line_total'. 'summary' totals only the
 * lines that can be bought; 'can_checkout' is true when there is at least
 * one line and none has a problem.
 *
 * @return array{items: array, summary: array{subtotal: float, shipping: float, total: float, quantity: int}, can_checkout: bool, needs_shipping: bool}
 */
function loadCart(PDO $pdo, int $userId, bool $lockListings = false): array
{
    // FOR UPDATE (inside checkout's transaction) stops two checkouts from
    // selling the same last unit.
    $stmt = $pdo->prepare('
        SELECT
            ci.listing_id, ci.quantity,
            l.item_name, l.price, l.quantity AS stock, l.listing_status, l.sale_type,
            l.fulfillment_type, l.shipping_cost, l.seller_user_id,
            u.account_status AS seller_status,
            sp.store_name,
            (
                SELECT li.image_url
                FROM listing_images li
                WHERE li.listing_id = l.listing_id
                ORDER BY li.display_order
                LIMIT 1
            ) AS image_url
        FROM cart_items ci
        JOIN carts c ON c.cart_id = ci.cart_id
        JOIN listings l ON l.listing_id = ci.listing_id
        JOIN users u ON u.user_id = l.seller_user_id
        LEFT JOIN seller_profiles sp ON sp.user_id = l.seller_user_id
        WHERE c.user_id = :user_id
        ORDER BY ci.added_at, ci.cart_item_id
    ' . ($lockListings ? ' FOR UPDATE' : ''));
    $stmt->execute(['user_id' => $userId]);

    $items = [];
    $summary = ['subtotal' => 0.0, 'shipping' => 0.0, 'total' => 0.0, 'quantity' => 0];
    $needsShipping = false;

    foreach ($stmt->fetchAll() as $item) {
        // The purchase check reads 'quantity' as stock; the left side of + wins.
        $problem = listingPurchaseProblem(['quantity' => $item['stock']] + $item, $userId);
        if ($problem === null && (int) $item['quantity'] > (int) $item['stock']) {
            $problem = 'Only ' . (int) $item['stock'] . ' available. Lower the quantity to continue.';
        }

        // Shipping is charged once per cart line, as the listing's flat rate.
        $item['line_shipping'] = $item['fulfillment_type'] === 'shipping' ? (float) ($item['shipping_cost'] ?? 0) : 0.0;
        $item['line_total'] = (float) $item['price'] * (int) $item['quantity'];
        $item['problem'] = $problem;

        if ($problem === null) {
            $summary['subtotal'] += $item['line_total'];
            $summary['shipping'] += $item['line_shipping'];
            $summary['quantity'] += (int) $item['quantity'];
            $needsShipping = $needsShipping || $item['fulfillment_type'] === 'shipping';
        }

        $items[] = $item;
    }

    $summary['subtotal'] = round($summary['subtotal'], 2);
    $summary['shipping'] = round($summary['shipping'], 2);
    $summary['total'] = round($summary['subtotal'] + $summary['shipping'], 2);

    $hasProblem = array_filter($items, static fn (array $item): bool => $item['problem'] !== null) !== [];

    return [
        'items' => $items,
        'summary' => $summary,
        'can_checkout' => $items !== [] && !$hasProblem,
        'needs_shipping' => $needsShipping,
    ];
}

// =========================================
// CHECKOUT
// =========================================

/**
 * Trims a submitted address. $prefix is 'billing_' or 'shipping_'.
 */
function normalizeAddressInput(array $post, string $prefix): array
{
    $address = [];
    foreach (CART_ADDRESS_FIELDS as $field) {
        $value = $post[$prefix . $field] ?? '';
        $address[$field] = is_string($value) ? trim($value) : '';
    }

    return $address;
}

/**
 * @return array<string, string> "<prefix><field>" => error message
 */
function validateAddress(array $address, string $prefix): array
{
    $errors = [];
    $required = [
        'address_line_1' => 'Street address',
        'city' => 'City',
        'state_name' => 'State',
        'zip_code' => 'ZIP code',
        'country' => 'Country',
    ];

    foreach ($required as $field => $label) {
        if ($address[$field] === '') {
            $errors[$prefix . $field] = "$label is required.";
        }
    }

    foreach (['address_line_1', 'address_line_2', 'city', 'state_name', 'country'] as $field) {
        if (!isset($errors[$prefix . $field]) && mb_strlen($address[$field]) > 100) {
            $errors[$prefix . $field] = 'Must be 100 characters or fewer.';
        }
    }

    if (!isset($errors[$prefix . 'zip_code']) && !preg_match('/^[A-Za-z0-9 \-]{3,20}$/', $address['zip_code'])) {
        $errors[$prefix . 'zip_code'] = 'Enter a valid ZIP code.';
    }

    return $errors;
}

/**
 * The user's default saved address of this type (to prefill checkout), or blanks.
 */
function loadDefaultAddress(PDO $pdo, int $userId, string $type): array
{
    $stmt = $pdo->prepare('
        SELECT address_line_1, address_line_2, city, state_name, zip_code, country
        FROM addresses
        WHERE user_id = :user_id AND address_type = :type
        ORDER BY is_default DESC, address_id DESC
        LIMIT 1
    ');
    $stmt->execute(['user_id' => $userId, 'type' => $type]);
    $row = $stmt->fetch();

    $blank = array_fill_keys(CART_ADDRESS_FIELDS, '');
    $blank['country'] = 'USA';

    return $row === false ? $blank : array_map(static fn ($v): string => (string) ($v ?? ''), $row);
}

/**
 * Saves an address and makes it the user's default for that type.
 */
function saveCheckoutAddress(PDO $pdo, int $userId, string $type, array $address): int
{
    $pdo->prepare('UPDATE addresses SET is_default = FALSE WHERE user_id = :user_id AND address_type = :type')
        ->execute(['user_id' => $userId, 'type' => $type]);

    $pdo->prepare('
        INSERT INTO addresses (
            user_id, address_line_1, address_line_2, city, state_name, zip_code, country,
            address_type, is_default
        ) VALUES (
            :user_id, :line_1, :line_2, :city, :state_name, :zip_code, :country, :type, TRUE
        )
    ')->execute([
        'user_id' => $userId,
        'line_1' => $address['address_line_1'],
        'line_2' => $address['address_line_2'] !== '' ? $address['address_line_2'] : null,
        'city' => $address['city'],
        'state_name' => $address['state_name'],
        'zip_code' => $address['zip_code'],
        'country' => $address['country'],
        'type' => $type,
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * Turns the user's cart into one order. Runs in a transaction with the
 * listings locked: stock and availability are re-checked, stock is
 * decremented (a listing that reaches 0 is marked sold), and the cart is
 * emptied. Payment is not processed: the order is created with
 * payment_status 'pending'.
 *
 * @param array $billing  normalized billing address
 * @param ?array $shipping normalized shipping address; null means "same as billing"
 * @return array{order_id: ?int, errors: array<string, string>}
 */
function placeOrder(PDO $pdo, int $userId, array $billing, ?array $shipping): array
{
    $errors = validateAddress($billing, 'billing_');
    if ($shipping !== null) {
        $errors += validateAddress($shipping, 'shipping_');
    }
    if ($errors !== []) {
        return ['order_id' => null, 'errors' => $errors];
    }

    // Callers (such as tests) may already be in a transaction. Then a
    // savepoint lets a failed checkout undo only its own writes.
    $ownTransaction = !$pdo->inTransaction();
    $ownTransaction ? $pdo->beginTransaction() : $pdo->exec('SAVEPOINT checkout');
    $undo = static function () use ($pdo, $ownTransaction): void {
        if ($ownTransaction) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } else {
            $pdo->exec('ROLLBACK TO SAVEPOINT checkout');
        }
    };

    try {
        $cart = loadCart($pdo, $userId, true);

        if ($cart['items'] === []) {
            throw new CheckoutProblem('Your cart is empty.');
        }
        if (!$cart['can_checkout']) {
            throw new CheckoutProblem('Some items in your cart changed. Review your cart and try again.');
        }

        $billingId = saveCheckoutAddress($pdo, $userId, 'billing', $billing);
        $shippingId = null;
        if ($cart['needs_shipping']) {
            $shippingId = $shipping === null
                ? saveCheckoutAddress($pdo, $userId, 'shipping', $billing)
                : saveCheckoutAddress($pdo, $userId, 'shipping', $shipping);
        }

        $summary = $cart['summary'];
        $pdo->prepare('
            INSERT INTO orders (
                buyer_user_id, shipping_address_id, billing_address_id,
                subtotal, shipping_cost, tax_amount, total_amount
            ) VALUES (
                :buyer_id, :shipping_id, :billing_id, :subtotal, :shipping, 0.00, :total
            )
        ')->execute([
            'buyer_id' => $userId,
            'shipping_id' => $shippingId,
            'billing_id' => $billingId,
            'subtotal' => $summary['subtotal'],
            'shipping' => $summary['shipping'],
            'total' => $summary['total'],
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare('
            INSERT INTO order_items (order_id, listing_id, quantity, unit_price, fulfillment_type, shipping_cost)
            VALUES (:order_id, :listing_id, :quantity, :unit_price, :fulfillment_type, :shipping_cost)
        ');
        $decrementStock = $pdo->prepare('
            UPDATE listings
            SET quantity = quantity - :quantity,
                listing_status = IF(quantity = 0, \'sold\', listing_status)
            WHERE listing_id = :listing_id AND quantity >= :min_quantity
        ');

        foreach ($cart['items'] as $item) {
            $insertItem->execute([
                'order_id' => $orderId,
                'listing_id' => $item['listing_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['price'],
                'fulfillment_type' => $item['fulfillment_type'],
                'shipping_cost' => $item['line_shipping'],
            ]);

            // MySQL applies SET left to right, so the IF sees the new quantity.
            $decrementStock->execute([
                'quantity' => $item['quantity'],
                'listing_id' => $item['listing_id'],
                'min_quantity' => $item['quantity'],
            ]);
            if ($decrementStock->rowCount() !== 1) {
                throw new CheckoutProblem('Some items in your cart changed. Review your cart and try again.');
            }
        }

        $pdo->prepare('
            DELETE ci FROM cart_items ci
            JOIN carts c ON c.cart_id = ci.cart_id
            WHERE c.user_id = :user_id
        ')->execute(['user_id' => $userId]);

        $ownTransaction ? $pdo->commit() : $pdo->exec('RELEASE SAVEPOINT checkout');

        return ['order_id' => $orderId, 'errors' => []];
    } catch (CheckoutProblem $e) {
        $undo();
        return ['order_id' => null, 'errors' => ['cart' => $e->getMessage()]];
    } catch (Throwable $e) {
        $undo();
        throw $e;
    }
}

/**
 * A checkout failure the buyer can fix (cart changed or empty).
 */
final class CheckoutProblem extends RuntimeException
{
}
