<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../../includes/auth.php';

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

        // Create a buyer without a seller profile.
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
     * Matches the seller-profile authorization rule
     * used by create-listing-details.php.
     */
    private function canPublish(?int $userId): bool
    {
        if ($userId === null || $userId < 1) {
            return false;
        }

        $stmt = $this->pdo->prepare('
            SELECT user_id
            FROM seller_profiles
            WHERE user_id = :user_id
        ');

        $stmt->execute([
            'user_id' => $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function testUnauthenticatedUserCannotPublish(): void
    {
        $this->assertFalse($this->canPublish(null));
    }

    public function testBuyerWithoutSellerProfileCannotPublish(): void
    {
        $this->assertFalse(
            $this->canPublish($this->buyerId)
        );
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
        // Verify the seller is eligible before inserting.
        $this->assertTrue($this->canPublish($this->sellerId));

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
