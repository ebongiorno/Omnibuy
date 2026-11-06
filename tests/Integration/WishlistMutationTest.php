<?php
declare(strict_types=1);

/**
 * Real HTTP -> PHP -> MySQL -> HTTP add/remove/duplicate/persistence workflows.
 * Uses two test sessions and a disposable OmniBuy test database.
 */

require_once __DIR__ . '/../Support/WishlistApiTestCase.php';

final class WishlistMutationTest extends WishlistApiTestCase
{
    public function test12FirstSaveReturnsCreated(): void
    {
        $id = $this->testListingId();
        $this->makeAbsent('A', $id);
        $response = $this->write('POST', 'A', ['listing_id' => $id]);
        self::assertSame(201, $response['status']);
        self::assertSame(true, $response['body']['success']);
        self::assertSame($id, $response['body']['listing_id']);
        self::assertTrue($response['body']['saved']);
        self::assertFalse($response['body']['already_saved']);
    }
    public function test13SavedListingAppearsInList(): void
    {
        $id = $this->testListingId();
        $this->makeSaved('A', $id);
        self::assertTrue($this->hasListing($this->request('GET'), $id));
    }
    public function test14DuplicateSaveIsIdempotent(): void
    {
        $id = $this->testListingId();
        $this->makeAbsent('A', $id);
        self::assertSame(201, $this->write('POST', 'A', ['listing_id' => $id])['status']);
        $duplicate = $this->write('POST', 'A', ['listing_id' => $id]);
        self::assertSame(200, $duplicate['status']);
        self::assertTrue($duplicate['body']['already_saved']);
        $items = $this->request('GET')['body']['items'];
        $matches = array_filter($items, static fn (array $item): bool => (int) $item['listing_id'] === $id);
        self::assertCount(1, $matches);
    }
    public function test15StatusBecomesSavedAfterPost(): void
    {
        $id = $this->testListingId();
        $this->makeAbsent('A', $id);
        $this->write('POST', 'A', ['listing_id' => $id]);
        self::assertTrue($this->wishlistSavedStatus('A', $id));
    }
    public function test16DeleteRemovesAnExistingSave(): void
    {
        $id = $this->testListingId();
        $this->makeSaved('A', $id);
        $response = $this->write('DELETE', 'A', ['listing_id' => $id]);
        self::assertSame(200, $response['status']);
        self::assertSame(true, $response['body']['success']);
        self::assertFalse($response['body']['saved']);
        self::assertTrue($response['body']['removed']);
    }
    public function test17SecondDeleteReturnsRemovedFalse(): void
    {
        $id = $this->testListingId();
        $this->makeAbsent('A', $id);
        $response = $this->write('DELETE', 'A', ['listing_id' => $id]);
        self::assertSame(200, $response['status']);
        self::assertFalse($response['body']['removed']);
    }
    public function test18DeleteUpdatesSavedStatus(): void
    {
        $id = $this->testListingId();
        $this->makeSaved('A', $id);
        $this->write('DELETE', 'A', ['listing_id' => $id]);
        self::assertFalse($this->wishlistSavedStatus('A', $id));
        self::assertFalse($this->hasListing($this->request('GET'), $id));
    }
    public function test19CountChangesWhenOneListingIsAdded(): void
    {
        $id = $this->testListingId();
        $this->makeAbsent('A', $id);
        $before = $this->request('GET')['body']['count'];
        $this->write('POST', 'A', ['listing_id' => $id]);
        $after = $this->request('GET')['body']['count'];
        self::assertSame($before + 1, $after);
    }
    public function test20SavedListingReturnsExpectedMetadata(): void
    {
        $id = $this->testListingId();
        $this->makeSaved('A', $id);
        $items = $this->request('GET')['body']['items'];
        $matches = array_values(array_filter($items, static fn (array $item): bool => (int) $item['listing_id'] === $id));
        self::assertCount(1, $matches);
        foreach (['wishlist_item_id', 'listing_id', 'saved_at', 'title', 'price', 'item_condition', 'status', 'category', 'image_url'] as $key) {
            self::assertArrayHasKey($key, $matches[0]);
        }
        self::assertSame('active', $matches[0]['status']);
    }
    public function test21SavePersistsAcrossSeparateHttpRequests(): void
    {
        $id = $this->testListingId();
        $this->makeAbsent('A', $id);
        $this->write('POST', 'A', ['listing_id' => $id]);
        self::assertTrue($this->wishlistSavedStatus('A', $id));
        self::assertTrue($this->hasListing($this->request('GET'), $id));
        self::assertTrue($this->wishlistSavedStatus('A', $id));
    }
}
