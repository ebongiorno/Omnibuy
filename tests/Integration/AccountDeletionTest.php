<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../../includes/account.php';

/**
 * deleteAccount() against the real database.
 */
final class AccountDeletionTest extends DatabaseTestCase
{
    private const PASSWORD = 'Sup3rSecret!'; // DatabaseTestCase::createUser() uses this password

    private int $user;
    private int $seller;
    private int $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser('delete_me');
        $this->seller = $this->createSellerWithListing();
    }

    private function createSellerWithListing(?int $userId = null): int
    {
        $suffix = $this->uniqueSuffix();
        $sellerId = $userId ?? $this->createUser('delete_seller');

        $this->pdo->prepare('
            INSERT INTO seller_profiles (user_id, store_name, payout_account_reference)
            VALUES (:user_id, :store_name, :payout_reference)
        ')->execute([
            'user_id' => $sellerId,
            'store_name' => "Delete Store $suffix",
            'payout_reference' => "DELETE_$suffix",
        ]);

        $this->pdo->prepare('INSERT INTO categories (category_name) VALUES (:name)')
            ->execute(['name' => "Delete Category $suffix"]);

        $this->pdo->prepare('
            INSERT INTO listings (
                seller_user_id, category_id, item_name, sale_type, fulfillment_type,
                price, `condition`, quantity, listing_status
            ) VALUES (
                :seller_id, :category_id, "Delete Test Item", "fixed_price", "shipping",
                10.00, "good", 3, "active"
            )
        ')->execute(['seller_id' => $sellerId, 'category_id' => (int) $this->pdo->lastInsertId()]);
        $this->listing = (int) $this->pdo->lastInsertId();

        return $sellerId;
    }

    private function createOrder(int $buyerId, string $orderStatus, string $itemStatus): int
    {
        $this->pdo->prepare('
            INSERT INTO addresses (user_id, address_line_1, city, state_name, zip_code, country, address_type)
            VALUES (:user_id, "1 Main St", "Fresno", "CA", "93740", "USA", "billing")
        ')->execute(['user_id' => $buyerId]);
        $addressId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare('
            INSERT INTO orders (buyer_user_id, billing_address_id, order_status, subtotal, total_amount)
            VALUES (:buyer_id, :address_id, :status, 10.00, 10.00)
        ')->execute(['buyer_id' => $buyerId, 'address_id' => $addressId, 'status' => $orderStatus]);
        $orderId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare('
            INSERT INTO order_items (order_id, listing_id, quantity, unit_price, fulfillment_type, item_status)
            VALUES (:order_id, :listing_id, 1, 10.00, "shipping", :status)
        ')->execute(['order_id' => $orderId, 'listing_id' => $this->listing, 'status' => $itemStatus]);

        return $orderId;
    }

    private function userRow(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);

        return $stmt->fetch();
    }

    private function countRows(string $table, string $column, int $userId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE $column = :id");
        $stmt->execute(['id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    // ---------- checks before deleting ----------

    public function testWrongPasswordIsRejected(): void
    {
        $errors = deleteAccount($this->pdo, $this->user, 'not-my-password', 'DELETE');

        $this->assertArrayHasKey('password', $errors);
        $this->assertSame('active', $this->userRow($this->user)['account_status']);
    }

    public function testMissingConfirmationIsRejected(): void
    {
        $errors = deleteAccount($this->pdo, $this->user, self::PASSWORD, 'delete');

        $this->assertArrayHasKey('confirm', $errors);
        $this->assertSame('active', $this->userRow($this->user)['account_status']);
    }

    public function testOpenOrderAsBuyerBlocksDeletion(): void
    {
        $this->createOrder($this->user, 'in_progress', 'shipped');

        $errors = deleteAccount($this->pdo, $this->user, self::PASSWORD, 'DELETE');

        $this->assertStringContainsString('1 open order', $errors['form'] ?? '');
        $this->assertSame('active', $this->userRow($this->user)['account_status']);
    }

    public function testOpenSaleAsSellerBlocksDeletion(): void
    {
        $this->createOrder($this->user, 'pending', 'processing');

        $errors = deleteAccount($this->pdo, $this->seller, self::PASSWORD, 'DELETE');

        $this->assertArrayHasKey('form', $errors);
        $this->assertSame('active', $this->userRow($this->seller)['account_status']);
    }

    // ---------- deleting ----------

    public function testDeletionErasesPersonalDetails(): void
    {
        $before = $this->userRow($this->user);

        $this->assertSame([], deleteAccount($this->pdo, $this->user, self::PASSWORD, ' DELETE '));

        $after = $this->userRow($this->user);
        $this->assertSame('deleted', $after['account_status']);
        $this->assertSame('Deleted', $after['first_name']);
        $this->assertSame('User', $after['last_name']);
        $this->assertSame("deleted_user_{$this->user}", $after['username']);
        $this->assertSame("deleted-{$this->user}@deleted.invalid", $after['email']);
        $this->assertSame('', $after['phone_number']);
        $this->assertNull($after['bio']);
        $this->assertNull($after['profile_image_url']);
        $this->assertFalse(password_verify(self::PASSWORD, $after['password_hash']));
        $this->assertNotSame($before['email'], $after['email']);
    }

    public function testDeletedUserCannotLogIn(): void
    {
        $email = $this->userRow($this->user)['email'];

        deleteAccount($this->pdo, $this->user, self::PASSWORD, 'DELETE');

        $this->assertNull(attemptLogin($this->pdo, $email, self::PASSWORD)['user']);
    }

    public function testUsernameAndEmailCanBeReused(): void
    {
        $before = $this->userRow($this->user);
        deleteAccount($this->pdo, $this->user, self::PASSWORD, 'DELETE');

        $newUser = $this->createUser('reuse', [
            'username' => $before['username'],
            'email' => $before['email'],
        ]);

        $this->assertGreaterThan(0, $newUser);
    }

    public function testPersonalDataIsRemoved(): void
    {
        $this->pdo->prepare('INSERT INTO wishlist_items (user_id, listing_id) VALUES (:id, :listing)')
            ->execute(['id' => $this->user, 'listing' => $this->listing]);
        $this->pdo->prepare('INSERT INTO carts (user_id) VALUES (:id)')->execute(['id' => $this->user]);
        $this->pdo->prepare('INSERT INTO cart_items (cart_id, listing_id) VALUES (:cart, :listing)')
            ->execute(['cart' => (int) $this->pdo->lastInsertId(), 'listing' => $this->listing]);
        $this->pdo->prepare('INSERT INTO search_history (user_id, search_query) VALUES (:id, "lamp")')
            ->execute(['id' => $this->user]);
        $this->pdo->prepare('INSERT INTO user_blocks (blocker_user_id, blocked_user_id) VALUES (:id, :other)')
            ->execute(['id' => $this->user, 'other' => $this->seller]);
        $this->pdo->prepare('
            INSERT INTO addresses (user_id, address_line_1, city, state_name, zip_code, country, address_type)
            VALUES (:id, "2 Side St", "Fresno", "CA", "93740", "USA", "shipping")
        ')->execute(['id' => $this->user]);

        deleteAccount($this->pdo, $this->user, self::PASSWORD, 'DELETE');

        $this->assertSame(0, $this->countRows('wishlist_items', 'user_id', $this->user));
        $this->assertSame(0, $this->countRows('carts', 'user_id', $this->user));
        $this->assertSame(0, $this->countRows('search_history', 'user_id', $this->user));
        $this->assertSame(0, $this->countRows('user_blocks', 'blocker_user_id', $this->user));
        $this->assertSame(0, $this->countRows('addresses', 'user_id', $this->user));
    }

    public function testCompletedOrdersAreKeptForTheOtherSide(): void
    {
        $orderId = $this->createOrder($this->user, 'completed', 'completed');

        $this->assertSame([], deleteAccount($this->pdo, $this->user, self::PASSWORD, 'DELETE'));

        $this->assertSame(1, $this->countRows('orders', 'order_id', $orderId));
        // The order's billing address stays with the order.
        $this->assertSame(1, $this->countRows('addresses', 'user_id', $this->user));
    }

    public function testSellerListingsAreArchivedAndStoreRenamed(): void
    {
        $this->assertSame([], deleteAccount($this->pdo, $this->seller, self::PASSWORD, 'DELETE'));

        $stmt = $this->pdo->prepare('SELECT listing_status FROM listings WHERE listing_id = :id');
        $stmt->execute(['id' => $this->listing]);
        $this->assertSame('archived', $stmt->fetchColumn());

        $stmt = $this->pdo->prepare('SELECT store_name FROM seller_profiles WHERE user_id = :id');
        $stmt->execute(['id' => $this->seller]);
        $this->assertSame("Deleted store {$this->seller}", $stmt->fetchColumn());
    }
}
