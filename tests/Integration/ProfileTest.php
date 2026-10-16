<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../../includes/profile.php';

/**
 * loadPublicProfile() and loadProfileListings() for View User Profile
 * against the real database.
 */
final class ProfileTest extends DatabaseTestCase
{
    private int $seller;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $suffix = $this->uniqueSuffix();
        $this->seller = $this->createUser('profile_seller', ['first_name' => 'Pat', 'last_name' => 'Rivera']);
        $this->pdo->prepare('
            UPDATE users SET city = "Fresno", bio = "Vintage cameras", phone_number = "559-555-0100"
            WHERE user_id = :id
        ')->execute(['id' => $this->seller]);

        $this->pdo->prepare('
            INSERT INTO seller_profiles (user_id, store_name, payout_account_reference)
            VALUES (:user_id, :store_name, :payout_reference)
        ')->execute([
            'user_id' => $this->seller,
            'store_name' => "Profile Store $suffix",
            'payout_reference' => "PROFILE_$suffix",
        ]);

        $this->pdo->prepare('INSERT INTO categories (category_name) VALUES (:name)')
            ->execute(['name' => "Profile Category $suffix"]);
        $this->categoryId = (int) $this->pdo->lastInsertId();
    }

    private function createListing(string $name, string $status, ?int $sellerId = null): int
    {
        $this->pdo->prepare('
            INSERT INTO listings (
                seller_user_id, category_id, item_name, sale_type, fulfillment_type,
                price, `condition`, quantity, listing_status
            ) VALUES (
                :seller_id, :category_id, :name, "fixed_price", "shipping",
                20.00, "good", 1, :status
            )
        ')->execute([
            'seller_id' => $sellerId ?? $this->seller,
            'category_id' => $this->categoryId,
            'name' => $name,
            'status' => $status,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function names(array $listings): array
    {
        return array_column($listings, 'item_name');
    }

    // ---------- profile details ----------

    public function testProfileShowsPublicDetails(): void
    {
        $profile = loadPublicProfile($this->pdo, $this->seller, false);

        $this->assertSame('Pat Rivera', $profile['display_name']);
        $this->assertSame('Fresno', $profile['city']);
        $this->assertSame('Vintage cameras', $profile['bio']);
        $this->assertStringStartsWith('Profile Store ', $profile['store_name']);
        $this->assertSame(AUTH_DEFAULT_AVATAR, $profile['profile_image_url']);
    }

    public function testContactDetailsOnlyWhenRequested(): void
    {
        $loggedOut = loadPublicProfile($this->pdo, $this->seller, false);
        $loggedIn = loadPublicProfile($this->pdo, $this->seller, true);

        $this->assertNull($loggedOut['email']);
        $this->assertNull($loggedOut['phone_number']);
        $this->assertStringContainsString('@example.com', $loggedIn['email']);
        $this->assertSame('559-555-0100', $loggedIn['phone_number']);
    }

    public function testHiddenLastNameIsNotShown(): void
    {
        $this->pdo->prepare('UPDATE users SET is_last_name_hidden = TRUE WHERE user_id = :id')
            ->execute(['id' => $this->seller]);

        $this->assertSame('Pat', loadPublicProfile($this->pdo, $this->seller, true)['display_name']);
    }

    public function testHiddenFieldsAreLeftOut(): void
    {
        updatePrivacySettings($this->pdo, $this->seller, ['city' => true, 'bio' => true, 'email' => true]);

        $profile = loadPublicProfile($this->pdo, $this->seller, true);

        $this->assertNull($profile['city']);
        $this->assertNull($profile['bio']);
        $this->assertNull($profile['email']);
        $this->assertSame('559-555-0100', $profile['phone_number'], 'Phone is still shown');
        $this->assertFalse($profile['contact_hidden']);
        $this->assertSame(['city', 'email', 'bio'], $profile['hidden']);
    }

    public function testContactHiddenWhenEmailAndPhoneAreBothHidden(): void
    {
        updatePrivacySettings($this->pdo, $this->seller, ['email' => true, 'phone' => true]);

        $profile = loadPublicProfile($this->pdo, $this->seller, true);

        $this->assertTrue($profile['contact_hidden']);
        $this->assertNull($profile['email']);
        $this->assertNull($profile['phone_number']);
    }

    public function testNothingHiddenByDefault(): void
    {
        $profile = loadPublicProfile($this->pdo, $this->seller, true);

        $this->assertSame([], $profile['hidden']);
        $this->assertFalse($profile['contact_hidden']);
    }

    public function testUserWithoutStoreOrCityHasNulls(): void
    {
        $profile = loadPublicProfile($this->pdo, $this->createUser('profile_buyer'), false);

        $this->assertNull($profile['store_name']);
        $this->assertNull($profile['city']);
    }

    public function testMissingUserIsNotFound(): void
    {
        $this->assertNull(loadPublicProfile($this->pdo, PHP_INT_MAX, true));
    }

    public function testInactiveAccountsAreNotFound(): void
    {
        foreach (['deleted', 'suspended', 'restricted'] as $status) {
            $this->pdo->prepare('UPDATE users SET account_status = :status WHERE user_id = :id')
                ->execute(['status' => $status, 'id' => $this->seller]);

            $this->assertNull(loadPublicProfile($this->pdo, $this->seller, true), $status);
        }
    }

    // ---------- listings ----------

    public function testOnlyActiveAndSoldListingsAreShown(): void
    {
        $this->createListing('Active Camera', 'active');
        $this->createListing('Sold Lens', 'sold');
        $this->createListing('Draft Tripod', 'draft');
        $this->createListing('Hidden Flash', 'hidden');
        $this->createListing('Archived Bag', 'archived');

        $listings = loadProfileListings($this->pdo, $this->seller);

        $this->assertSame(['Active Camera'], $this->names($listings['active']));
        $this->assertSame(['Sold Lens'], $this->names($listings['sold']));
    }

    public function testOtherSellersListingsAreNotShown(): void
    {
        $other = $this->createUser('profile_other');
        $suffix = $this->uniqueSuffix();
        $this->pdo->prepare('
            INSERT INTO seller_profiles (user_id, store_name, payout_account_reference)
            VALUES (:user_id, :store_name, :payout_reference)
        ')->execute(['user_id' => $other, 'store_name' => "Other $suffix", 'payout_reference' => "OTHER_$suffix"]);
        $this->createListing('Other Seller Item', 'active', $other);

        $this->assertSame([], loadProfileListings($this->pdo, $this->seller)['active']);
    }

    public function testListingsHaveCardFieldsAndDefaultImage(): void
    {
        $this->createListing('Active Camera', 'active');

        $listing = loadProfileListings($this->pdo, $this->seller)['active'][0];

        $this->assertStringStartsWith('Profile Category ', $listing['category_name']);
        $this->assertSame(PROFILE_LISTING_DEFAULT_IMAGE, $listing['image_url']);
        foreach (['listing_id', 'price', 'condition', 'fulfillment_type'] as $field) {
            $this->assertArrayHasKey($field, $listing);
        }
    }

    public function testListingImageIsTheFirstPhoto(): void
    {
        $listingId = $this->createListing('Active Camera', 'active');
        foreach (['/uploads/listings/b.jpg' => 2, '/uploads/listings/a.jpg' => 1] as $url => $order) {
            $this->pdo->prepare('
                INSERT INTO listing_images (listing_id, image_url, display_order)
                VALUES (:listing_id, :url, :display_order)
            ')->execute(['listing_id' => $listingId, 'url' => $url, 'display_order' => $order]);
        }

        $this->assertSame('/uploads/listings/a.jpg', loadProfileListings($this->pdo, $this->seller)['active'][0]['image_url']);
    }
}
