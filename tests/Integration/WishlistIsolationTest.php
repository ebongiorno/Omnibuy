<?php
declare(strict_types=1);

/**
 * Cross-session wishlist persistence and forged user ID prevention.
 * Uses two test sessions and a disposable OmniBuy test database.
 */

require_once __DIR__ . '/../Support/WishlistApiTestCase.php';

final class WishlistIsolationTest extends WishlistApiTestCase
{
    public function test29DifferentUsersHaveSeparateWishlists(): void
    {
        $id = $this->testListingId();
        $this->cookie('B');
        $this->makeAbsent('A', $id);
        $this->makeAbsent('B', $id);
        $this->makeSaved('A', $id);
        self::assertTrue($this->wishlistSavedStatus('A', $id));
        self::assertFalse($this->wishlistSavedStatus('B', $id));
    }
    public function test38GetIgnoresForgedUserId(): void
    {
        $otherId = $this->seededSetting('OMNIBUY_USER_B_ID', '2');
        $id = $this->testListingId();
        $this->makeAbsent('A', $id);
        $this->makeSaved('B', $id);
        $response = $this->request('GET', 'A', ['user_id' => $otherId]);
        self::assertSame(200, $response['status']);
        self::assertFalse($this->hasListing($response, $id));
    }
    public function test39PostIgnoresForgedUserId(): void
    {
        $otherId = $this->seededSetting('OMNIBUY_USER_B_ID', '2');
        $id = $this->testListingId();
        $this->makeAbsent('A', $id);
        $this->makeAbsent('B', $id);
        $response = $this->write('POST', 'A', ['listing_id' => $id, 'user_id' => (int) $otherId]);
        self::assertSame(201, $response['status']);
        self::assertTrue($this->wishlistSavedStatus('A', $id));
        self::assertFalse($this->wishlistSavedStatus('B', $id));
    }
    public function test40DeleteIgnoresForgedUserId(): void
    {
        $otherId = $this->seededSetting('OMNIBUY_USER_B_ID', '2');
        $id = $this->testListingId();
        $this->makeSaved('A', $id);
        $this->makeSaved('B', $id);
        $response = $this->write('DELETE', 'A', ['listing_id' => $id, 'user_id' => (int) $otherId]);
        self::assertSame(200, $response['status']);
        self::assertFalse($this->wishlistSavedStatus('A', $id));
        self::assertTrue($this->wishlistSavedStatus('B', $id));
    }
}
