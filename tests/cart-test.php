<?php

declare(strict_types=1);

require __DIR__ . '/../db_connect.php';

/** @var PDO $pdo */

$failures = [];

function check(string $description, bool $condition, array &$failures): void
{
    if ($condition) {
        echo "  [PASS] $description\n";
    } else {
        echo "  [FAIL] $description\n";
        $failures[] = $description;
    }
}

echo "Running cart tests...\n";

$suffix = bin2hex(random_bytes(4));

$sellerId = null;
$buyerId = null;
$categoryId = null;
$listingId = null;
$secondListingId = null;
$cartId = null;

try {
    // Set up a seller, a category, and two listings to exercise the cart with.
    $pdo->prepare('
        INSERT INTO users (first_name, last_name, username, email, password_hash, phone_number, birth_date)
        VALUES ("CI", "Seller", :username, :email, "CI_TEST_HASH", "555-1000", "1995-01-01")
    ')->execute([
        'username' => "ci_seller_$suffix",
        'email' => "ci_seller_$suffix@example.com",
    ]);
    $sellerId = (int) $pdo->lastInsertId();

    $pdo->prepare('
        INSERT INTO seller_profiles (user_id, store_name, payout_account_reference)
        VALUES (:user_id, :store_name, :payout_ref)
    ')->execute([
        'user_id' => $sellerId,
        'store_name' => "CI Store $suffix",
        'payout_ref' => "CI_PAYOUT_$suffix",
    ]);

    $pdo->prepare('INSERT INTO categories (category_name) VALUES (:name)')
        ->execute(['name' => "CI Category $suffix"]);
    $categoryId = (int) $pdo->lastInsertId();

    $createListing = $pdo->prepare('
        INSERT INTO listings (
            seller_user_id, category_id, item_name, sale_type, fulfillment_type,
            shipping_cost, estimated_shipping_days, price, `condition`, quantity, listing_status
        ) VALUES (
            :seller_user_id, :category_id, :item_name, "fixed_price", "shipping",
            5.00, 3, :price, "good", 5, "active"
        )
    ');

    $createListing->execute([
        'seller_user_id' => $sellerId,
        'category_id' => $categoryId,
        'item_name' => "CI Test Listing $suffix A",
        'price' => 20.00,
    ]);
    $listingId = (int) $pdo->lastInsertId();

    $createListing->execute([
        'seller_user_id' => $sellerId,
        'category_id' => $categoryId,
        'item_name' => "CI Test Listing $suffix B",
        'price' => 15.00,
    ]);
    $secondListingId = (int) $pdo->lastInsertId();

    // Set up a buyer.
    $pdo->prepare('
        INSERT INTO users (first_name, last_name, username, email, password_hash, phone_number, birth_date)
        VALUES ("CI", "Buyer", :username, :email, "CI_TEST_HASH", "555-2000", "1996-01-01")
    ')->execute([
        'username' => "ci_buyer_$suffix",
        'email' => "ci_buyer_$suffix@example.com",
    ]);
    $buyerId = (int) $pdo->lastInsertId();

    // 1. A buyer can create a cart and add an item to it.
    $pdo->prepare('INSERT INTO carts (user_id) VALUES (:user_id)')->execute(['user_id' => $buyerId]);
    $cartId = (int) $pdo->lastInsertId();

    check('a cart is created for the buyer', $cartId > 0, $failures);

    $pdo->prepare('
        INSERT INTO cart_items (cart_id, listing_id, quantity) VALUES (:cart_id, :listing_id, 2)
    ')->execute(['cart_id' => $cartId, 'listing_id' => $listingId]);

    $item = $pdo->prepare('
        SELECT ci.quantity, l.price
        FROM cart_items ci
        JOIN listings l ON l.listing_id = ci.listing_id
        WHERE ci.cart_id = :cart_id AND ci.listing_id = :listing_id
    ');
    $item->execute(['cart_id' => $cartId, 'listing_id' => $listingId]);
    $row = $item->fetch();

    check('the cart item is stored with the correct quantity', $row && (int) $row['quantity'] === 2, $failures);
    check('the cart item reflects the listing price', $row && (float) $row['price'] === 20.00, $failures);

    // 2. Adding the same listing to the same cart twice is rejected.
    $duplicateRejected = false;

    try {
        $pdo->prepare('
            INSERT INTO cart_items (cart_id, listing_id, quantity) VALUES (:cart_id, :listing_id, 1)
        ')->execute(['cart_id' => $cartId, 'listing_id' => $listingId]);
    } catch (PDOException $e) {
        $duplicateRejected = true;
    }

    check('adding the same listing to a cart twice is rejected', $duplicateRejected, $failures);

    // 3. A cart item quantity of zero is rejected (tested on a fresh listing so
    // it isn't masked by the duplicate-item constraint above).
    $zeroQuantityRejected = false;

    try {
        $pdo->prepare('
            INSERT INTO cart_items (cart_id, listing_id, quantity) VALUES (:cart_id, :listing_id, 0)
        ')->execute(['cart_id' => $cartId, 'listing_id' => $secondListingId]);
    } catch (PDOException $e) {
        $zeroQuantityRejected = true;
    }

    check('a cart item quantity of zero is rejected', $zeroQuantityRejected, $failures);

    // 4. A single user cannot have more than one cart.
    $secondCartRejected = false;

    try {
        $pdo->prepare('INSERT INTO carts (user_id) VALUES (:user_id)')->execute(['user_id' => $buyerId]);
    } catch (PDOException $e) {
        $secondCartRejected = true;
    }

    check('a user cannot have a second cart', $secondCartRejected, $failures);
} finally {
    if ($cartId !== null) {
        $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :id')->execute(['id' => $cartId]);
        $pdo->prepare('DELETE FROM carts WHERE cart_id = :id')->execute(['id' => $cartId]);
    }
    if ($listingId !== null) {
        $pdo->prepare('DELETE FROM listings WHERE listing_id = :id')->execute(['id' => $listingId]);
    }
    if ($secondListingId !== null) {
        $pdo->prepare('DELETE FROM listings WHERE listing_id = :id')->execute(['id' => $secondListingId]);
    }
    if ($categoryId !== null) {
        $pdo->prepare('DELETE FROM categories WHERE category_id = :id')->execute(['id' => $categoryId]);
    }
    if ($sellerId !== null) {
        $pdo->prepare('DELETE FROM seller_profiles WHERE user_id = :id')->execute(['id' => $sellerId]);
    }
    if ($buyerId !== null) {
        $pdo->prepare('DELETE FROM users WHERE user_id = :id')->execute(['id' => $buyerId]);
    }
    if ($sellerId !== null) {
        $pdo->prepare('DELETE FROM users WHERE user_id = :id')->execute(['id' => $sellerId]);
    }
}

if (!empty($failures)) {
    echo "\n" . count($failures) . " check(s) failed.\n";
    exit(1);
}

echo "\nAll cart checks passed.\n";
exit(0);
