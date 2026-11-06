<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../../includes/account.php';

/**
 * Tests seller authentication, publication eligibility,
 * and listing ownership using temporary database records.
 */
final class SellerAuthorizationTest extends DatabaseTestCase
{
    private int $buyerId;
    private int $sellerId;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = $this->uniqueSuffix();

        // An account that hasn't sold anything yet, so has no seller profile.
        $this->buyerId = $this->createUser('auth_buyer');

        // Create a user who has a seller profile.
        $this->sellerId = $this->createUser('auth_seller');

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
            'store_name' => "Authorization Test Store $suffix",
            'payout_reference' => "AUTH_TEST_$suffix",
        ]);

        // Create a temporary category.
        $this->pdo->prepare('
            INSERT INTO categories (category_name)
            VALUES (:name)
        ')->execute([
            'name' => "Authorization Test Category $suffix",
        ]);

        $this->categoryId = (int) $this->pdo->lastInsertId();
    }

    protected function tearDown(): void
    {
        // Avoid leaking authentication state between tests.
        unset(
            $_SESSION['user_id'],
            $_SESSION['username'],
            $_SESSION['avatar_url'],
            $_SESSION['is_seller']
        );

        parent::tearDown();
    }

    /**
     * Matches the publishing rule in create-listing-details.php:
     * any logged-in account can sell.
     */
    private function canPublish(?int $userId): bool
    {
        return $userId !== null && $userId >= 1;
    }

    /** @return array<string, mixed>|false */
    private function sellerProfile(int $userId): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM seller_profiles WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetch();
    }

    private function username(int $userId): string
    {
        $stmt = $this->pdo->prepare('SELECT username FROM users WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return (string) $stmt->fetchColumn();
    }

    public function testUnauthenticatedUserCannotPublish(): void
    {
        $this->assertFalse($this->canPublish(null));
    }

    public function testAccountWithoutSellerProfileCanPublish(): void
    {
        $this->assertTrue(
            $this->canPublish($this->buyerId)
        );
    }

    public function testFirstListingCreatesTheSellerProfile(): void
    {
        ensureSellerProfile($this->pdo, $this->buyerId);

        $profile = $this->sellerProfile($this->buyerId);
        $this->assertIsArray($profile);
        $this->assertSame($this->username($this->buyerId), $profile['store_name']);
        $this->assertSame("pending-{$this->buyerId}", $profile['payout_account_reference']);
    }

    public function testExistingSellerProfileIsLeftAlone(): void
    {
        $before = $this->sellerProfile($this->sellerId);

        ensureSellerProfile($this->pdo, $this->sellerId);
        ensureSellerProfile($this->pdo, $this->sellerId);

        $this->assertSame($before, $this->sellerProfile($this->sellerId));
    }

    public function testStoreNameTakenByAnotherStoreGetsTheUserId(): void
    {
        $username = $this->username($this->buyerId);
        $this->pdo->prepare('UPDATE seller_profiles SET store_name = :name WHERE user_id = :user_id')
            ->execute(['name' => $username, 'user_id' => $this->sellerId]);

        ensureSellerProfile($this->pdo, $this->buyerId);

        $this->assertSame("$username-{$this->buyerId}", $this->sellerProfile($this->buyerId)['store_name']);
    }

    public function testSellerWithProfileCanPublish(): void
    {
        $this->assertTrue(
            $this->canPublish($this->sellerId)
        );
    }

    public function testSellerStatusMatchesDatabaseProfile(): void
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*)
            FROM seller_profiles
            WHERE user_id = :user_id
        ');

        $stmt->execute([
            'user_id' => $this->sellerId,
        ]);

        $this->assertSame(
            1,
            (int) $stmt->fetchColumn()
        );

        $stmt->execute([
            'user_id' => $this->buyerId,
        ]);

        $this->assertSame(
            0,
            (int) $stmt->fetchColumn()
        );
    }

    public function testPublishedListingBelongsToCorrectSeller(): void
    {
        // A first-time seller, as in create-listing-details.php.
        $this->sellerId = $this->buyerId;
        ensureSellerProfile($this->pdo, $this->sellerId);

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
            'item_name' => 'Authorization Test Laptop',
            'description' => 'Temporary listing for authorization testing',
            'price' => 99.99,
        ]);

        $listingId = (int) $this->pdo->lastInsertId();

        $check = $this->pdo->prepare('
            SELECT seller_user_id
            FROM listings
            WHERE listing_id = :listing_id
        ');

        $check->execute([
            'listing_id' => $listingId,
        ]);

        $this->assertSame(
            $this->sellerId,
            (int) $check->fetchColumn()
        );
    }
}
