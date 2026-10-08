<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use PHPUnit\Framework\TestCase;

/**
 * Sends real HTTP requests to the search page on the running web container
 * and checks the page that comes back. Relies on seed_data.sql being loaded.
 */
final class SearchPageTest extends TestCase
{
    public function testSearchPageLoadsWithoutQuery(): void
    {
        [$status, $body] = $this->get('/pages/search.php');

        $this->assertSame(200, $status);
        $this->assertStringContainsString('Start searching', $body);
    }

    public function testSearchFindsSeededListing(): void
    {
        [$status, $body] = $this->get('/pages/search.php', ['q' => 'keyboard']);

        $this->assertSame(200, $status);
        $this->assertStringNotContainsString('Fatal error', $body);
        $this->assertStringContainsString('Wireless Mechanical Keyboard', $body);
        $this->assertStringNotContainsString('No listings found', $body);
    }

    public function testSearchWithNoMatchesShowsEmptyState(): void
    {
        [$status, $body] = $this->get('/pages/search.php', ['q' => 'zzqx-no-such-item-zzqx']);

        $this->assertSame(200, $status);
        $this->assertStringContainsString('No listings found', $body);
        $this->assertMatchesRegularExpression('/\b0 results/', $body);
    }

    public function testSearchQueryIsEscapedInPage(): void
    {
        // Security: the search term is echoed back and must not inject markup.
        [, $body] = $this->get('/pages/search.php', ['q' => '<script>alert("xss")</script>']);

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function testSqlInjectionInQueryDoesNotBreakSearch(): void
    {
        // Security: quotes and SQL in the search term must be treated as plain text.
        [$status, $body] = $this->get('/pages/search.php', ['q' => "' OR '1'='1' -- "]);

        $this->assertSame(200, $status);
        $this->assertStringNotContainsString('Unable to retrieve search results', $body);
        $this->assertStringContainsString('No listings found', $body);
    }

    public function testInvalidSortOptionFallsBackToDefault(): void
    {
        // Negative: sort values outside the allow-list must not reach the SQL ORDER BY.
        [$status, $body] = $this->get('/pages/search.php', [
            'q' => 'keyboard',
            'sort' => 'price DESC; DROP TABLE listings',
        ]);

        $this->assertSame(200, $status);
        $this->assertStringNotContainsString('Unable to retrieve search results', $body);
        $this->assertStringContainsString('Wireless Mechanical Keyboard', $body);
    }

    public function testUnknownConditionFilterIsIgnored(): void
    {
        [$status, $body] = $this->get('/pages/search.php', [
            'q' => 'keyboard',
            'condition' => ['not_a_condition'],
        ]);

        $this->assertSame(200, $status);
        $this->assertStringNotContainsString('Unable to retrieve search results', $body);
        $this->assertStringContainsString('Wireless Mechanical Keyboard', $body);
    }

    public function testConditionFilterIsKeptWhenChangingSort(): void
    {
        // Regression: the sort form crashed with a fatal error once the
        // condition filter became multi-select (array instead of string).
        [$status, $body] = $this->get('/pages/search.php', [
            'q' => 'keyboard',
            'condition' => ['excellent', 'good'],
            'sort' => 'price_low',
        ]);

        $this->assertSame(200, $status);
        $this->assertStringNotContainsString('Fatal error', $body);
        $this->assertStringContainsString('name="condition[]"', $body);
        $this->assertStringContainsString('value="excellent"', $body);
    }

    /**
     * @return array{int, string} HTTP status code and response body
     */
    private function get(string $path, array $query = []): array
    {
        $url = rtrim(getenv('APP_URL') ?: 'http://localhost', '/') . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            $this->fail("Could not reach $url. Is the web container running?");
        }

        preg_match('{HTTP/\S+\s(\d{3})}', $http_response_header[0] ?? '', $matches);

        return [(int) ($matches[1] ?? 0), $body];
    }
}
