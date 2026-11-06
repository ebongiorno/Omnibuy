<?php
declare(strict_types=1);

/**
 * HTTP GET list/status responses, headers, and status validation.
 * Uses two test sessions and a disposable OmniBuy test database.
 */

require_once __DIR__ . '/../Support/WishlistApiTestCase.php';

final class WishlistApiReadTest extends WishlistApiTestCase
{
    public function test07ListReturnsItemsCountAndCsrfToken(): void
    {
        $response = $this->request('GET');
        self::assertSame(200, $response['status']);
        self::assertSame(true, $response['body']['success'] ?? null);
        self::assertIsArray($response['body']['items'] ?? null);
        self::assertSame(count($response['body']['items']), $response['body']['count']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $response['body']['csrf_token']);
    }
    public function test08ApiSetsPrivacyAndJsonHeaders(): void
    {
        $response = $this->request('GET');
        $headers = $response['headers'];
        self::assertStringContainsString('application/json', implode(',', $headers['content-type'] ?? []));
        self::assertStringContainsString('private', implode(',', $headers['cache-control'] ?? []));
        self::assertStringContainsString('no-store', implode(',', $headers['cache-control'] ?? []));
        self::assertContains('nosniff', $headers['x-content-type-options'] ?? []);
        self::assertStringContainsString('Cookie', implode(',', $headers['vary'] ?? []));
    }
    public function test09StatusResponseContainsListingIdAndBoolean(): void
    {
        $id = $this->testListingId();
        $response = $this->request('GET', 'A', ['listing_id' => $id]);
        self::assertSame(200, $response['status']);
        self::assertSame(true, $response['body']['success'] ?? null);
        self::assertSame($id, $response['body']['listing_id']);
        self::assertIsBool($response['body']['saved']);
        self::assertIsString($response['body']['csrf_token']);
    }
    public function test10StatusRejectsInvalidListingIds(): void
    {
        foreach (['0', '-1', 'abc', ' 1', '1.5', '1 OR 1=1', '999999999999999999999999999999'] as $badId) {
            $this->assertError($this->request('GET', 'A', ['listing_id' => $badId]), 422);
        }
        $this->assertError($this->request('GET', 'A', ['listing_id' => ['1']]), 422);
    }
    public function test11NonexistentListingStatusIsFalse(): void
    {
        self::assertFalse($this->wishlistSavedStatus('A', $this->missingListingId()));
    }
}
