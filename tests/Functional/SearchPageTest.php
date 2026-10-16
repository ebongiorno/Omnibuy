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

    // =========================================
    // UC-22: FILTER BY CONDITION
    // =========================================

    public function testSingleConditionFilterMatchesDatabase(): void
    {
        // Connect to the existing MySQL database.
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'db',
            (int)(getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: 'omnibuy'
        );

        $pdo = new \PDO(
            $dsn,
            getenv('DB_USER') ?: '',
            getenv('DB_PASSWORD') ?: '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ]
        );

        // Find how many active listings have New condition.
        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
                AND `condition` = 'good'
        ");

        $expectedCount = (int)$stmt->fetchColumn();

        // Ensure the test data contains a matching listing.
        $this->assertGreaterThan(
            0,
            $expectedCount,
            'Test data needs at least one active New listing.'
        );

        // Request the search page with New selected.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'condition' => ['good']
            ]
        );

        // Confirm the page loads successfully.
        $this->assertSame(200, $status);

        // Confirm the search did not produce a database error.
        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        // Read the result count displayed on the page.
        $matched = preg_match(
            '/class="search-results__count"[^>]*>\s*(\d+)\s+results?\b/',
            $body,
            $matches
        );

        $this->assertSame(
            1,
            $matched,
            'Could not find the search result count.'
        );

        $actualCount = (int)$matches[1];

        // Compare the website count with the database count.
        $this->assertSame(
            $expectedCount,
            $actualCount,
            'Condition filter result count does not match the database.'
        );
    }

    // =========================================
    // TC-22-02: FILTER BY MULTIPLE CONDITIONS
    // =========================================

    public function testMultipleConditionFilterMatchesDatabase(): void
    {
        // Connect to MySQL.
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'db',
            (int)(getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: 'omnibuy'
        );

        $pdo = new \PDO(
            $dsn,
            getenv('DB_USER') ?: '',
            getenv('DB_PASSWORD') ?: '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ]
        );

        // Find two different conditions with active listings.
        $stmt = $pdo->query("
            SELECT `condition`
            FROM listings
            WHERE listing_status = 'active'
            GROUP BY `condition`
            ORDER BY COUNT(*) DESC
            LIMIT 2
        ");

        $conditions = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Make sure enough test data exists.
        $this->assertCount(
            2,
            $conditions,
            'Test data needs two different active listing conditions.'
        );

        // Count listings matching either condition.
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
                AND `condition` IN (?, ?)
        ");

        $stmt->execute($conditions);

        $expectedCount = (int)$stmt->fetchColumn();

        // Request the search page with both conditions.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'condition' => $conditions
            ]
        );

        // Verify that the page loads.
        $this->assertSame(200, $status);

        // Verify that no search error occurred.
        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        // Extract the number of displayed results.
        $matched = preg_match(
            '/class="search-results__count"[^>]*>\s*(\d+)\s+results?\b/',
            $body,
            $matches
        );

        $this->assertSame(
            1,
            $matched,
            'Could not find the search result count.'
        );

        $actualCount = (int)$matches[1];

        // Compare website results with database results.
        $this->assertSame(
            $expectedCount,
            $actualCount,
            'Multiple condition filter count does not match the database.'
        );
    }

    // =========================================
    // TC-22-03: COMBINE CONDITION AND DELIVERY
    // =========================================

    public function testConditionAndDeliveryFiltersTogether(): void
    {
        // Connect to MySQL.
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'db',
            (int)(getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: 'omnibuy'
        );

        $pdo = new \PDO(
            $dsn,
            getenv('DB_USER') ?: '',
            getenv('DB_PASSWORD') ?: '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ]
        );

        // Find an existing condition/delivery combination.
        $stmt = $pdo->query("
            SELECT
                `condition` AS selected_condition,
                fulfillment_type
            FROM listings
            WHERE listing_status = 'active'
                AND fulfillment_type IN ('shipping', 'meetup')
            GROUP BY `condition`, fulfillment_type
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ");

        $combination = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray(
            $combination,
            'Test data needs active listings with a condition and delivery method.'
        );

        $condition = $combination['selected_condition'];
        $fulfillment = $combination['fulfillment_type'];

        // Count listings matching BOTH filters.
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
                AND `condition` = :condition
                AND fulfillment_type = :fulfillment
        ");

        $stmt->execute([
            'condition' => $condition,
            'fulfillment' => $fulfillment
        ]);

        $expectedCount = (int)$stmt->fetchColumn();

        // Confirm the combination has matching listings.
        $this->assertGreaterThan(0, $expectedCount);

        // Confirm some active listings do not match the combination.
        $totalCount = (int)$pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
        ")->fetchColumn();

        $this->assertGreaterThan(
            $expectedCount,
            $totalCount,
            'Test data needs listings outside the selected filter combination.'
        );

        // Request the search page with both filters.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'condition' => [$condition],
                'fulfillment' => $fulfillment
            ]
        );

        // Verify the request succeeded.
        $this->assertSame(200, $status);

        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        // Extract the displayed result count.
        $matched = preg_match(
            '/class="search-results__count"[^>]*>\s*(\d+)\s+results?\b/',
            $body,
            $matches
        );

        $this->assertSame(
            1,
            $matched,
            'Could not find the search result count.'
        );

        $actualCount = (int)$matches[1];

        // Verify the results match the database.
        $this->assertSame(
            $expectedCount,
            $actualCount,
            'Combined condition and delivery filters returned an incorrect count.'
        );
    }

    // =========================================
    // TC-22-04: CLEAR CONDITION FILTERS
    // =========================================

    public function testRemovingConditionFilterRestoresResults(): void
    {
        // Connect to MySQL.
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'db',
            (int)(getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: 'omnibuy'
        );

        $pdo = new \PDO(
            $dsn,
            getenv('DB_USER') ?: '',
            getenv('DB_PASSWORD') ?: '',
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ]
        );

        // Count all active listings.
        $totalCount = (int)$pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
        ")->fetchColumn();

        // Choose an existing condition with active listings.
        $stmt = $pdo->query("
            SELECT
                `condition` AS selected_condition,
                COUNT(*) AS condition_count
            FROM listings
            WHERE listing_status = 'active'
            GROUP BY `condition`
            ORDER BY COUNT(*) ASC
            LIMIT 1
        ");

        $testData = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray(
            $testData,
            'Test data needs active listings.'
        );

        $condition = $testData['selected_condition'];
        $filteredCount = (int)$testData['condition_count'];

        // Ensure removing this filter should change the results.
        $this->assertGreaterThan(
            $filteredCount,
            $totalCount,
            'Test data needs listings with other conditions.'
        );

        // Request the search with the condition selected.
        [$filteredStatus, $filteredBody] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'condition' => [$condition]
            ]
        );

        $this->assertSame(200, $filteredStatus);

        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $filteredBody
        );

        // Read the filtered result count.
        $this->assertSame(
            1,
            preg_match(
                '/class="search-results__count"[^>]*>\s*(\d+)\s+results?\b/',
                $filteredBody,
                $filteredMatches
            )
        );

        $this->assertSame(
            $filteredCount,
            (int)$filteredMatches[1]
        );

        // Request the page again with NO condition filter.
        // This simulates the GET request after clearing.
        [$clearedStatus, $clearedBody] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all'
            ]
        );

        $this->assertSame(200, $clearedStatus);

        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $clearedBody
        );

        // Read the unfiltered result count.
        $this->assertSame(
            1,
            preg_match(
                '/class="search-results__count"[^>]*>\s*(\d+)\s+results?\b/',
                $clearedBody,
                $clearedMatches
            )
        );

        $this->assertSame(
            $totalCount,
            (int)$clearedMatches[1],
            'Removing the condition filter should restore all active listings.'
        );
    }

    // =========================================
    // TC-22-05: NO MATCHING RESULTS
    // =========================================

    public function testConditionFilterWithNoMatchingResults(): void
    {
        // Generate a search term that should not exist
        // in the marketplace.
        $searchTerm =
            'omnibuy_no_match_tc2205_' .
            bin2hex(random_bytes(12));

        // Request the search page with a keyword
        // and the Poor condition selected.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'q' => $searchTerm,
                'condition' => ['poor']
            ]
        );

        // Verify the page loads successfully.
        $this->assertSame(200, $status);

        // Verify no database error occurred.
        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        // Verify the result count displays 0 results.
        $matched = preg_match(
            '/class="search-results__count"[^>]*>\s*0\s+results\b/',
            $body
        );

        $this->assertSame(
            1,
            $matched,
            'Expected the search page to display 0 results.'
        );

        // Verify the No listings found message appears.
        $this->assertMatchesRegularExpression(
            '/<h3>\s*No listings found\s*<\/h3>/i',
            $body
        );

        // Verify the Poor checkbox remains selected.
        $checked = preg_match(
            '/<input\b(?=[^>]*name="condition\[\]")(?=[^>]*value="poor")(?=[^>]*\bchecked\b)[^>]*>/s',
            $body
        );

        $this->assertSame(
            1,
            $checked,
            'The Poor condition should remain selected.'
        );
    }
    // =========================================
// TC-21-01: FILTER BY SHIPPING
// =========================================

public function testShippingFilterMatchesDatabase(): void
{
    // Connect to MySQL.
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: 'db',
        (int)(getenv('DB_PORT') ?: 3306),
        getenv('DB_NAME') ?: 'omnibuy'
    );

    $pdo = new \PDO(
        $dsn,
        getenv('DB_USER') ?: '',
        getenv('DB_PASSWORD') ?: '',
        [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]
    );

    // Count active shipping listings.
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM listings
        WHERE listing_status = 'active'
            AND fulfillment_type = 'shipping'
    ");

    $expectedCount = (int)$stmt->fetchColumn();

    // Make sure shipping test data exists.
    $this->assertGreaterThan(
        0,
        $expectedCount,
        'Test data needs active shipping listings.'
    );

    // Ensure meetup listings also exist so the
    // delivery filter has something to exclude.
    $meetupCount = (int)$pdo->query("
        SELECT COUNT(*)
        FROM listings
        WHERE listing_status = 'active'
            AND fulfillment_type = 'meetup'
    ")->fetchColumn();

    $this->assertGreaterThan(
        0,
        $meetupCount,
        'Test data needs active meetup listings.'
    );

    // Request Browse All with Shipping selected.
    [$status, $body] = $this->get(
        '/pages/search.php',
        [
            'browse' => 'all',
            'fulfillment' => 'shipping'
        ]
    );

    // Verify the page loads.
    $this->assertSame(200, $status);

    // Verify no database error occurred.
    $this->assertStringNotContainsString(
        'Unable to retrieve search results',
        $body
    );

    // Extract the displayed result count.
    $matched = preg_match(
        '/class="search-results__count"[^>]*>\s*(\d+)\s+results?\b/',
        $body,
        $matches
    );

    $this->assertSame(
        1,
        $matched,
        'Could not find the search result count.'
    );

    $actualCount = (int)$matches[1];

    // Compare website results with MySQL.
    $this->assertSame(
        $expectedCount,
        $actualCount,
        'Shipping filter count does not match the database.'
    );

    // Verify Shipping remains selected.
    $checked = preg_match(
        '/<input\b(?=[^>]*name="fulfillment")(?=[^>]*value="shipping")(?=[^>]*\bchecked\b)[^>]*>/s',
        $body
    );

    $this->assertSame(
        1,
        $checked,
        'The Shipping radio button should remain selected.'
    );

    // Verify the Meetup Distance controls are hidden.
    $this->assertMatchesRegularExpression(
        '/<div\b(?=[^>]*id="meetup-distance-filter")(?=[^>]*\bhidden\b)[^>]*>/s',
        $body
    );
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
