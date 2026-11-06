<?php
declare(strict_types=1);

/**
 * HTTP invalid listing, JSON body, content type, and payload limit cases.
 * Uses two test sessions and a disposable OmniBuy test database.
 */

require_once __DIR__ . '/../Support/WishlistApiTestCase.php';

final class WishlistApiValidationTest extends WishlistApiTestCase
{
    public function test22CannotAddNonexistentListing(): void
    {
        $this->assertError($this->write('POST', 'A', ['listing_id' => $this->missingListingId()]), 404);
    }
    public function test23CannotAddInactiveListing(): void
    {
        $id = $this->inactiveListingId();
        self::assertFalse(
            $this->wishlistSavedStatus('A', $id),
            'Choose an inactive listing not already saved by test user A.'
        );
        $this->assertError($this->write('POST', 'A', ['listing_id' => $id]), 404);
    }
    public function test30PostRequiresJsonContentType(): void
    {
        $this->assertError($this->write('POST', 'A', ['listing_id' => $this->testListingId()], 'valid', 'text/plain'), 415);
    }
    public function test31PostRejectsMalformedJson(): void
    {
        $this->assertError($this->write('POST', 'A', '{"listing_id":', 'valid', 'application/json', true), 400);
    }
    public function test32PostRejectsNonObjectJson(): void
    {
        foreach (['[1]', 'null', '"hello"', '42'] as $json) {
            $this->assertError($this->write('POST', 'A', $json, 'valid', 'application/json', true), 400);
        }
    }
    public function test33PostRequiresListingId(): void
    {
        $this->assertError(
            $this->write('POST', 'A', new stdClass()),
            422
        );
    }
    public function test34PostRejectsMalformedListingIds(): void
    {
        foreach ([0, -1, '0', '01', ' 1', '1.5', '', 'abc', '1 OR 1=1', '1; DROP TABLE wishlist_items', null, true, 1.5, [1]] as $badId) {
            $this->assertError($this->write('POST', 'A', ['listing_id' => $badId]), 422);
        }
    }
    public function test35PostRejectsBodyLargerThan4096Bytes(): void
    {
        $body = '{"listing_id":1,"extra":"' . str_repeat('x', 4097) . '"}';
        $this->assertError($this->write('POST', 'A', $body, 'valid', 'application/json', true), 413);
    }
    public function test36DeleteRejectsInvalidListingId(): void
    {
        $this->assertError($this->write('DELETE', 'A', ['listing_id' => '1 OR 1=1']), 422);
    }
    public function test37DeleteRequiresJsonContentType(): void
    {
        $this->assertError($this->write('DELETE', 'A', ['listing_id' => $this->testListingId()], 'valid', 'text/plain'), 415);
    }
}
