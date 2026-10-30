<?php
declare(strict_types=1);

/**
 * HTTP authentication, HTTP methods, CSRF, and CSRF session isolation.
 * Uses two test sessions and a disposable OmniBuy test database.
 */

require_once __DIR__ . '/../Support/WishlistApiTestCase.php';

final class WishlistApiSecurityTest extends WishlistApiTestCase
{
    public function test01AnonymousWishlistListIsDenied(): void
    {
        $this->assertError($this->request('GET', null), 401);
    }
    public function test02AnonymousStatusLookupIsDenied(): void
    {
        $this->assertError($this->request('GET', null, ['listing_id' => 1]), 401);
    }
    public function test03AnonymousAddIsDenied(): void
    {
        $this->assertError($this->request('POST', null, [], '{"listing_id":1}', ['Content-Type: application/json']), 401);
    }
    public function test04AnonymousDeleteIsDenied(): void
    {
        $this->assertError($this->request('DELETE', null, [], '{"listing_id":1}', ['Content-Type: application/json']), 401);
    }
    public function test05PatchIsRejectedAndAllowHeaderIsProvided(): void
    {
        $response = $this->request('PATCH', null);
        $this->assertError($response, 405);
        self::assertStringContainsString('GET, POST, DELETE', implode(',', $response['headers']['allow'] ?? []));
    }
    public function test06OptionsIsRejected(): void
    {
        $this->assertError($this->request('OPTIONS', null), 405);
    }
    public function test24PostWithoutCsrfTokenIsDenied(): void
    {
        $this->assertError($this->write('POST', 'A', ['listing_id' => $this->testListingId()], 'missing'), 403);
    }
    public function test25PostWithWrongCsrfTokenIsDenied(): void
    {
        $this->assertError($this->write('POST', 'A', ['listing_id' => $this->testListingId()], 'not-a-token'), 403);
    }
    public function test26DeleteWithoutCsrfTokenIsDenied(): void
    {
        $this->assertError($this->write('DELETE', 'A', ['listing_id' => $this->testListingId()], 'missing'), 403);
    }
    public function test27DeleteWithWrongCsrfTokenIsDenied(): void
    {
        $this->assertError($this->write('DELETE', 'A', ['listing_id' => $this->testListingId()], 'not-a-token'), 403);
    }
    public function test28AnotherUsersCsrfTokenIsRejected(): void
    {
        $this->cookie('B');
        $otherToken = $this->token('B');
        self::assertNotSame($this->token('A'), $otherToken, 'Use separate test user sessions.');
        $this->assertError($this->write('POST', 'A', ['listing_id' => $this->testListingId()], $otherToken), 403);
    }
}
