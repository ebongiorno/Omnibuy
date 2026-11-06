<?php
/**
 * Shared HTTP request, assertion, and safe state-restoration helpers for wishlist tests.
 * Load through tests/Support/WishlistApiTestCase.php.
 * Tests call the real wishlist.php HTTP endpoint, not a mock.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/WishlistSeedSessions.php';

abstract class WishlistApiTestCase extends TestCase
{
    /** @var array<string, array{actor:string,id:int,saved:bool}> */
    protected array $originalStates = [];

    protected function tearDown(): void
    {
        // Restore test users' saved relationships even if an assertion failed.
        try {
            foreach (array_reverse($this->originalStates) as $state) {
                $current = $this->wishlistSavedStatus($state['actor'], $state['id']);
                if ($current !== $state['saved']) {
                    $result = $this->write(
                        $state['saved'] ? 'POST' : 'DELETE',
                        $state['actor'],
                        ['listing_id' => $state['id']]
                    );
                    $expected = $state['saved'] ? [200, 201] : [200];
                    self::assertContains(
                        $result['status'],
                        $expected,
                        'Wishlist cleanup failed; inspect the test database.'
                    );
                }
            }
        } finally {
            $this->originalStates = [];
            parent::tearDown();
        }
    }

    protected function setting(string $name): string
    {
        $value = getenv($name);
        return $value === false ? '' : trim($value);
    }

    protected function requiredSetting(string $name): string
    {
        $value = $this->setting($name);
        if ($value === '') {
            $message = "Set {$name} to run the live wishlist API tests.";
            if ($this->setting('OMNIBUY_REQUIRE_WISHLIST_TEST_CONFIG') === '1') {
                self::fail($message);
            }
            self::markTestSkipped($message);
        }
        return $value;
    }

    protected function seededSetting(string $name, string $default): string
    {
        $value = $this->setting($name);
        return $value === '' ? $default : $value;
    }

    protected function testListingId(): int
    {
        $id = $this->seededSetting('OMNIBUY_ACTIVE_LISTING_ID', '1');
        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $id);
        return (int) $id;
    }

    protected function missingListingId(): int
    {
        $id = $this->seededSetting('OMNIBUY_MISSING_LISTING_ID', '999999999');
        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $id);
        return (int) $id;
    }

    protected function inactiveListingId(): int
    {
        $id = $this->seededSetting('OMNIBUY_INACTIVE_LISTING_ID', '11');
        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $id);
        return (int) $id;
    }

    protected function cookie(?string $actor): string
    {
        if ($actor === null) {
            return '';
        }
        $name = $actor === 'B' ? 'OMNIBUY_COOKIE_B' : 'OMNIBUY_COOKIE_A';
        $cookie = $this->setting($name);
        if ($cookie === '' && $this->setting('OMNIBUY_WISHLIST_SEED_SESSIONS') === '1') {
            // Use seeded active users. The CLI-only helper creates separate
            // real PHP sessions and destroys them on test-process shutdown.
            $userId = (int) $this->seededSetting(
                $actor === 'B' ? 'OMNIBUY_USER_B_ID' : 'OMNIBUY_USER_A_ID',
                $actor === 'B' ? '2' : '4'
            );
            if ($userId < 1) {
                self::fail('Seed user IDs must be positive integers.');
            }
            $cookie = WishlistSeedSessions::cookie($actor, $userId);
        }
        if ($cookie === '') {
            $cookie = $this->requiredSetting($name);
        }
        return preg_replace('/^Cookie:\s*/i', '', $cookie) ?? $cookie;
    }

    /**
     * Calls the real API; uses PHP's HTTP stream wrapper (no ext-curl required).
     * @return array{status:int,headers:array<string,list<string>>,body:array<string,mixed>|null,raw:string}
     */
    protected function request(
        string $method,
        ?string $actor = 'A',
        array $query = [],
        ?string $rawBody = null,
        array $extraHeaders = []
    ): array {
        // The repository's phpunit.xml already sets APP_URL=http://localhost
        // when PHPUnit runs inside the web container. A full endpoint override
        // remains available for tests executed from the host machine.
        $base = $this->setting('OMNIBUY_WISHLIST_URL');
        if ($base === '') {
            $appUrl = $this->requiredSetting('APP_URL');
            $base = rtrim($appUrl, '/') . '/pages/wishlist.php';
        }
        $path = explode('?', $base, 2)[0];
        parse_str((string) parse_url($base, PHP_URL_QUERY), $existingQuery);
        $parameters = array_merge($existingQuery, ['wishlist_api' => '1'], $query);
        $url = $path . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        $headers = ['Accept: application/json'];
        $cookie = $this->cookie($actor);
        if ($cookie !== '') {
            $headers[] = 'Cookie: ' . $cookie;
        }
        foreach ($extraHeaders as $header) {
            $headers[] = $header;
        }

        $options = [
            'method' => $method,
            'header' => implode("\r\n", $headers) . "\r\n",
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 15,
        ];
        if ($rawBody !== null) {
            $options['content'] = $rawBody;
        }
        $context = stream_context_create(['http' => $options]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            self::fail("HTTP request failed: {$method} {$path}. Is OmniBuy running?");
        }

        /** @var list<string> $http_response_header */
        $rawHeaders = $http_response_header ?? [];
        $status = 0;
        $parsedHeaders = [];
        foreach ($rawHeaders as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $m)) {
                $status = (int) $m[1];
                $parsedHeaders = [];
                continue;
            }
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $parsedHeaders[strtolower(trim($name))][] = trim($value);
            }
        }
        self::assertGreaterThan(0, $status, 'Server did not send an HTTP status line.');
        $decoded = json_decode($response, true);
        self::assertIsArray(
            $decoded,
            "Expected JSON response, got HTTP {$status}: " . substr($response, 0, 200)
        );

        return ['status' => $status, 'headers' => $parsedHeaders, 'body' => $decoded, 'raw' => $response];
    }

    /** @return array{status:int,headers:array<string,list<string>>,body:array<string,mixed>|null,raw:string} */
    protected function write(
        string $method,
        string $actor,
        mixed $payload,
        string $csrf = 'valid',
        ?string $contentType = 'application/json',
        bool $raw = false
    ): array {
        $headers = [];
        if ($contentType !== null) {
            $headers[] = 'Content-Type: ' . $contentType;
        }
        if ($csrf === 'valid') {
            $headers[] = 'X-CSRF-Token: ' . $this->token($actor);
        } elseif ($csrf !== 'missing') {
            $headers[] = 'X-CSRF-Token: ' . $csrf;
        }
        $body = $raw ? (string) $payload : json_encode($payload, JSON_THROW_ON_ERROR);
        return $this->request($method, $actor, [], $body, $headers);
    }

    protected function token(string $actor): string
    {
        $response = $this->request('GET', $actor);
        self::assertSame(200, $response['status'], 'Could not obtain a logged-in CSRF token.');
        self::assertArrayHasKey('csrf_token', $response['body']);
        self::assertIsString($response['body']['csrf_token']);
        return $response['body']['csrf_token'];
    }

    protected function wishlistSavedStatus(string $actor, int $listingId): bool
    {
        $response = $this->request('GET', $actor, ['listing_id' => $listingId]);
        self::assertSame(200, $response['status']);
        self::assertIsBool($response['body']['saved'] ?? null);
        return $response['body']['saved'];
    }

    protected function remember(string $actor, int $listingId): void
    {
        $key = $actor . ':' . $listingId;
        if (!isset($this->originalStates[$key])) {
            $this->originalStates[$key] = [
                'actor' => $actor,
                'id' => $listingId,
                'saved' => $this->wishlistSavedStatus($actor, $listingId),
            ];
        }
    }

    protected function makeAbsent(string $actor, int $listingId): void
    {
        $this->remember($actor, $listingId);
        $result = $this->write('DELETE', $actor, ['listing_id' => $listingId]);
        self::assertSame(200, $result['status']);
        self::assertFalse($this->wishlistSavedStatus($actor, $listingId));
    }

    protected function makeSaved(string $actor, int $listingId): void
    {
        $this->remember($actor, $listingId);
        $result = $this->write('POST', $actor, ['listing_id' => $listingId]);
        self::assertContains($result['status'], [200, 201]);
        self::assertTrue($this->wishlistSavedStatus($actor, $listingId));
    }

    /** @param array<string,mixed> $response */
    protected function assertError(array $response, int $expectedCode): void
    {
        self::assertSame($expectedCode, $response['status']);
        self::assertSame(false, $response['body']['success'] ?? null);
        self::assertIsString($response['body']['error'] ?? null);
        self::assertNotSame('', $response['body']['error']);
    }

    /** @param array<string,mixed> $response */
    protected function hasListing(array $response, int $id): bool
    {
        foreach ($response['body']['items'] ?? [] as $item) {
            if ((int) ($item['listing_id'] ?? -1) === $id) {
                return true;
            }
        }
        return false;
    }
}
