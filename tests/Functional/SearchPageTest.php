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

    // =========================================
    // TC-21-02: FILTER BY MEETUP
    // =========================================

    public function testMeetupFilterMatchesDatabase(): void
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

        // Count active meetup listings.
        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
                AND fulfillment_type = 'meetup'
        ");

        $expectedCount = (int)$stmt->fetchColumn();

        // Make sure meetup test data exists.
        $this->assertGreaterThan(
            0,
            $expectedCount,
            'Test data needs active meetup listings.'
        );

        // Verify shipping listings also exist.
        // This ensures there are listings the filter should exclude.
        $shippingCount = (int)$pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
                AND fulfillment_type = 'shipping'
        ")->fetchColumn();

        $this->assertGreaterThan(
            0,
            $shippingCount,
            'Test data needs active shipping listings.'
        );

        // Request Browse All with Meetup selected.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'fulfillment' => 'meetup'
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
            'Meetup filter count does not match the database.'
        );

        // Verify Meetup remains selected.
        $checked = preg_match(
            '/<input\b(?=[^>]*name="fulfillment")(?=[^>]*value="meetup")(?=[^>]*\bchecked\b)[^>]*>/s',
            $body
        );

        $this->assertSame(
            1,
            $checked,
            'The Meetup radio button should remain selected.'
        );

        // Locate the Meetup Distance filter.
        $distanceFilterFound = preg_match(
            '/<div\b[^>]*\bid="meetup-distance-filter"[^>]*>/s',
            $body,
            $distanceMatches
        );

        $this->assertSame(
            1,
            $distanceFilterFound,
            'Could not find the Meetup Distance filter.'
        );

        // Verify the Meetup Distance filter is not hidden.
        $this->assertDoesNotMatchRegularExpression(
            '/\shidden(?:\s|=|>)/',
            $distanceMatches[0],
            'Meetup Distance should be visible when Meetup is selected.'
        );
    }

    // =========================================
    // TC-21-03: NO DELIVERY METHOD SELECTED
    // =========================================

    public function testNoDeliveryFilterShowsAllListings(): void
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
        $expectedCount = (int)$pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
        ")->fetchColumn();

        // Count active shipping listings.
        $shippingCount = (int)$pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
                AND fulfillment_type = 'shipping'
        ")->fetchColumn();

        // Count active meetup listings.
        $meetupCount = (int)$pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
                AND fulfillment_type = 'meetup'
        ")->fetchColumn();

        // Make sure both delivery types exist.
        $this->assertGreaterThan(
            0,
            $shippingCount,
            'Test data needs active shipping listings.'
        );

        $this->assertGreaterThan(
            0,
            $meetupCount,
            'Test data needs active meetup listings.'
        );

        // Verify the total consists of both delivery types.
        $this->assertSame(
            $expectedCount,
            $shippingCount + $meetupCount
        );

        // Request Browse All without a delivery filter.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all'
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

        // Verify all active listings are included.
        $this->assertSame(
            $expectedCount,
            (int)$matches[1],
            'Unfiltered search should show all active listings.'
        );

        // Verify Shipping is NOT selected.
        $shippingChecked = preg_match(
            '/<input\b(?=[^>]*name="fulfillment")(?=[^>]*value="shipping")(?=[^>]*\bchecked\b)[^>]*>/s',
            $body
        );

        $this->assertSame(
            0,
            $shippingChecked,
            'Shipping should not be selected.'
        );

        // Verify Meetup is NOT selected.
        $meetupChecked = preg_match(
            '/<input\b(?=[^>]*name="fulfillment")(?=[^>]*value="meetup")(?=[^>]*\bchecked\b)[^>]*>/s',
            $body
        );

        $this->assertSame(
            0,
            $meetupChecked,
            'Meetup should not be selected.'
        );

        // Verify the Meetup Distance section exists
        // and is not hidden.
        $distanceFilterFound = preg_match(
            '/<div\b[^>]*\bid="meetup-distance-filter"[^>]*>/s',
            $body,
            $distanceMatches
        );

        $this->assertSame(
            1,
            $distanceFilterFound,
            'Could not find the Meetup Distance filter.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\shidden(?:\s|=|>)/',
            $distanceMatches[0],
            'Meetup Distance should be visible.'
        );
    }

    // =========================================
    // TC-21-04: COMBINE DELIVERY AND CONDITION
    // =========================================

    public function testShippingAndGoodConditionFiltersTogether(): void
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

        // Count active listings that are BOTH
        // Shipping and Good condition.
        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
                AND fulfillment_type = 'shipping'
                AND `condition` = 'good'
        ");

        $expectedCount = (int)$stmt->fetchColumn();

        // Make sure matching test data exists.
        $this->assertGreaterThan(
            0,
            $expectedCount,
            'Test data needs active Good-condition shipping listings.'
        );

        // Make sure other shipping conditions exist,
        // so the condition filter excludes something.
        $shippingTotal = (int)$pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
                AND fulfillment_type = 'shipping'
        ")->fetchColumn();

        $this->assertGreaterThan(
            $expectedCount,
            $shippingTotal,
            'Test data needs shipping listings with other conditions.'
        );

        // Request Browse All with both filters.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'fulfillment' => 'shipping',
                'condition' => ['good']
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

        // Compare website results with MySQL.
        $this->assertSame(
            $expectedCount,
            (int)$matches[1],
            'Combined filters returned an incorrect result count.'
        );

        // Verify Shipping remains selected.
        $shippingChecked = preg_match(
            '/<input\b(?=[^>]*name="fulfillment")(?=[^>]*value="shipping")(?=[^>]*\bchecked\b)[^>]*>/s',
            $body
        );

        $this->assertSame(
            1,
            $shippingChecked,
            'Shipping should remain selected.'
        );

        // Verify Good remains selected.
        $goodChecked = preg_match(
            '/<input\b(?=[^>]*name="condition\[\]")(?=[^>]*value="good")(?=[^>]*\bchecked\b)[^>]*>/s',
            $body
        );

        $this->assertSame(
            1,
            $goodChecked,
            'Good condition should remain selected.'
        );
    }

    // =========================================
    // TC-21-05: CLEAR DELIVERY METHOD FILTER
    // =========================================

    public function testRemovingDeliveryFilterRestoresAllListings(): void
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

        // Count active shipping listings.
        $shippingCount = (int)$pdo->query("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
                AND fulfillment_type = 'shipping'
        ")->fetchColumn();

        // Ensure there are shipping listings to filter.
        $this->assertGreaterThan(
            0,
            $shippingCount,
            'Test data needs active shipping listings.'
        );

        // Ensure there are other listings to restore.
        $this->assertGreaterThan(
            $shippingCount,
            $totalCount,
            'Test data needs active meetup listings.'
        );

        // Request Browse All with Shipping selected.
        [$filteredStatus, $filteredBody] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'fulfillment' => 'shipping'
            ]
        );

        $this->assertSame(200, $filteredStatus);

        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $filteredBody
        );

        // Verify filtered result count.
        $this->assertSame(
            1,
            preg_match(
                '/class="search-results__count"[^>]*>\s*(\d+)\s+results?\b/',
                $filteredBody,
                $filteredMatches
            )
        );

        $this->assertSame(
            $shippingCount,
            (int)$filteredMatches[1]
        );

        // Simulate the GET request after clearing
        // the delivery filter.
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

        // Verify the full active listing count is restored.
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
            'Clearing delivery should restore all active listings.'
        );

        // Verify Shipping is no longer selected.
        $shippingChecked = preg_match(
            '/<input\b(?=[^>]*name="fulfillment")(?=[^>]*value="shipping")(?=[^>]*\bchecked\b)[^>]*>/s',
            $clearedBody
        );

        $this->assertSame(
            0,
            $shippingChecked,
            'Shipping should be unselected.'
        );

        // Verify Meetup is also unselected.
        $meetupChecked = preg_match(
            '/<input\b(?=[^>]*name="fulfillment")(?=[^>]*value="meetup")(?=[^>]*\bchecked\b)[^>]*>/s',
            $clearedBody
        );

        $this->assertSame(
            0,
            $meetupChecked,
            'Meetup should be unselected.'
        );

        // Verify Meetup Distance is visible again.
        $this->assertSame(
            1,
            preg_match(
                '/<div\b(?=[^>]*id="meetup-distance-filter")[^>]*>/s',
                $clearedBody,
                $distanceMatches
            ),
            'Could not find the Meetup Distance filter.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\shidden(?:\s|=|>)/',
            $distanceMatches[0],
            'Meetup Distance should be visible after clearing.'
        );
    }

    // =========================================
    // TC-23-01: FILTER MEETUP LISTINGS WITHIN A RADIUS
    // =========================================

    public function testMeetupRadiusIncludesNearbyListings(): void
    {
        $zip = '92831';
        $radiusMiles = 10;

        // Connect to the test database.
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
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Retrieve coordinates for the selected ZIP code.
        $zipStmt = $pdo->prepare("
            SELECT latitude, longitude
            FROM zip_code_locations
            WHERE zip_code = :zip
        ");

        $zipStmt->execute(['zip' => $zip]);
        $location = $zipStmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray(
            $location,
            'ZIP 92831 must exist in the test database.'
        );

        // Create a nearby meetup listing for this test.
        $insert = $pdo->prepare("
            INSERT INTO listings (
                seller_user_id,
                category_id,
                item_name,
                sale_type,
                fulfillment_type,
                location_zip_code,
                latitude,
                longitude,
                meetup_location_type,
                seller_meetup_radius,
                price,
                `condition`,
                quantity,
                listing_status
            ) VALUES (
                2, 7,
                'TC-23-01 Nearby Meetup Fixture',
                'fixed_price',
                'meetup',
                :zip,
                :latitude,
                :longitude,
                'library',
                10,
                15.00,
                'good',
                1,
                'active'
            )
        ");

        $insert->execute([
            'zip' => $zip,
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude']
        ]);

        $fixtureId = (int)$pdo->lastInsertId();

        try {
            // Find all eligible meetup listings in MySQL.
            $expectedStmt = $pdo->prepare("
                SELECT l.listing_id
                FROM listings AS l
                JOIN zip_code_locations AS z
                    ON z.zip_code = :zip
                WHERE l.listing_status = 'active'
                    AND l.fulfillment_type = 'meetup'
                    AND l.latitude IS NOT NULL
                    AND l.longitude IS NOT NULL
                    AND ST_Distance_Sphere(
                        POINT(l.longitude, l.latitude),
                        POINT(z.longitude, z.latitude)
                    ) <= :radius_meters
            ");

            $expectedStmt->execute([
                'zip' => $zip,
                'radius_meters' => $radiusMiles * 1609.344
            ]);

            $expectedIds = array_map(
                'intval',
                $expectedStmt->fetchAll(\PDO::FETCH_COLUMN)
            );

            sort($expectedIds);

            $this->assertContains($fixtureId, $expectedIds);

            // Request the search results page.
            [$status, $body] = $this->get(
                '/pages/search.php',
                [
                    'browse' => 'all',
                    'fulfillment' => 'meetup',
                    'zip' => $zip,
                    'radius' => (string)$radiusMiles
                ]
            );

            // Verify the page loads without errors.
            $this->assertSame(200, $status);

            $this->assertStringNotContainsString(
                'Unable to retrieve search results',
                $body
            );

            $this->assertStringNotContainsString(
                'Invalid Meetup Location',
                $body
            );

            // Verify the ZIP code remains selected.
            $this->assertMatchesRegularExpression(
                '/<input\b(?=[^>]*name="zip")' .
                '(?=[^>]*value="92831")[^>]*>/s',
                $body
            );

            // Verify the 10-mile radius remains selected.
            $this->assertMatchesRegularExpression(
                '/<select\b[^>]*name="radius"[^>]*>.*?' .
                '<option\b(?=[^>]*value="10")' .
                '(?=[^>]*selected)[^>]*>/s',
                $body
            );

            // Verify Meetup remains selected.
            $this->assertMatchesRegularExpression(
                '/<input\b(?=[^>]*name="fulfillment")' .
                '(?=[^>]*value="meetup")' .
                '(?=[^>]*checked)[^>]*>/s',
                $body
            );

            // Extract listing IDs from rendered listing cards.
            preg_match_all(
                '/<article class="listing-card">.*?' .
                'href="listing\.php\?id=(\d+)".*?' .
                '<\/article>/s',
                $body,
                $matches
            );

            $actualIds = array_map('intval', $matches[1]);
            sort($actualIds);

            // Compare the actual results against MySQL.
            $this->assertSame(
                $expectedIds,
                $actualIds,
                'The page should show only active meetup ' .
                'listings within 10 miles.'
            );

        } finally {
            // Remove the temporary test listing.
            $delete = $pdo->prepare("
                DELETE FROM listings
                WHERE listing_id = :id
            ");

            $delete->execute(['id' => $fixtureId]);
        }
    }

    // =========================================
    // TC-23-02: EXCLUDE MEETUP LISTINGS OUTSIDE THE RADIUS
    // =========================================

    public function testMeetupRadiusExcludesDistantListings(): void
    {
        $zip = '92831';
        $radiusMiles = 10;
        $radiusMeters = $radiusMiles * 1609.344;

        // Connect to the Docker test database.
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
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Retrieve coordinates for the buyer ZIP code.
        $zipStmt = $pdo->prepare("
            SELECT latitude, longitude
            FROM zip_code_locations
            WHERE zip_code = :zip
        ");

        $zipStmt->execute(['zip' => $zip]);
        $location = $zipStmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray(
            $location,
            'ZIP 92831 must exist.'
        );

        // Find all active meetup listings beyond 10 miles.
        $farStmt = $pdo->prepare("
            SELECT listing_id
            FROM listings
            WHERE listing_status = 'active'
                AND fulfillment_type = 'meetup'
                AND latitude IS NOT NULL
                AND longitude IS NOT NULL
                AND ST_Distance_Sphere(
                    POINT(longitude, latitude),
                    POINT(:buyer_longitude, :buyer_latitude)
                ) > :radius_meters
        ");

        $farStmt->execute([
            'buyer_longitude' => $location['longitude'],
            'buyer_latitude' => $location['latitude'],
            'radius_meters' => $radiusMeters
        ]);

        $farIds = $farStmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertNotEmpty(
            $farIds,
            'Test data needs active meetup listings outside 10 miles.'
        );

        // Create a temporary nearby meetup listing.
        $insert = $pdo->prepare("
            INSERT INTO listings (
                seller_user_id,
                category_id,
                item_name,
                sale_type,
                fulfillment_type,
                location_zip_code,
                latitude,
                longitude,
                meetup_location_type,
                seller_meetup_radius,
                price,
                `condition`,
                quantity,
                listing_status
            ) VALUES (
                2, 7,
                'TC-23-02 Nearby Meetup Fixture',
                'fixed_price',
                'meetup',
                :zip,
                :latitude,
                :longitude,
                'library',
                10,
                15.00,
                'good',
                1,
                'active'
            )
        ");

        $insert->execute([
            'zip' => $zip,
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude']
        ]);

        $nearId = (int)$pdo->lastInsertId();

        try {
            // Verify distant listings appear without a radius.
            [$allStatus, $allBody] = $this->get(
                '/pages/search.php',
                [
                    'browse' => 'all',
                    'fulfillment' => 'meetup'
                ]
            );

            $this->assertSame(200, $allStatus);

            $this->assertStringContainsString(
                'href="listing.php?id=' . $farIds[0] . '"',
                $allBody,
                'A distant listing should appear without a radius.'
            );

            // Apply the 10-mile meetup radius filter.
            [$status, $body] = $this->get(
                '/pages/search.php',
                [
                    'browse' => 'all',
                    'fulfillment' => 'meetup',
                    'zip' => $zip,
                    'radius' => (string)$radiusMiles
                ]
            );

            // Verify the page loads without errors.
            $this->assertSame(200, $status);

            $this->assertStringNotContainsString(
                'Fatal error',
                $body
            );

            $this->assertStringNotContainsString(
                'Unable to retrieve search results',
                $body
            );

            $this->assertStringNotContainsString(
                'Invalid Meetup Location',
                $body
            );

            // Verify the nearby meetup listing is included.
            $this->assertStringContainsString(
                'href="listing.php?id=' . $nearId . '"',
                $body,
                'The nearby meetup listing should remain visible.'
            );

            // Verify every known distant listing is excluded.
            foreach ($farIds as $farId) {
                $this->assertStringNotContainsString(
                    'href="listing.php?id=' . $farId . '"',
                    $body,
                    'Distant meetup listing ' . $farId .
                    ' was not excluded.'
                );
            }

        } finally {
            // Remove the temporary test listing.
            $delete = $pdo->prepare("
                DELETE FROM listings
                WHERE listing_id = :id
            ");

            $delete->execute(['id' => $nearId]);
        }
    }


    // =========================================
    // TC-23-03: TEST RADIUS BOUNDARY CONDITIONS
    // =========================================

    public function testMeetupRadiusHandlesBoundaryListings(): void
    {
        $zip = '92831';
        $radiusMiles = 10;
        $metersPerMile = 1609.344;
        $radiusMeters = $radiusMiles * $metersPerMile;
        $earthRadiusMeters = 6370986.0;

        // Connect to the Docker test database.
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
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Retrieve the buyer's ZIP coordinates.
        $zipStmt = $pdo->prepare("
            SELECT latitude, longitude
            FROM zip_code_locations
            WHERE zip_code = :zip
        ");

        $zipStmt->execute(['zip' => $zip]);
        $buyer = $zipStmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray(
            $buyer,
            'ZIP 92831 must exist in the test database.'
        );

        $buyerLat = (float)$buyer['latitude'];
        $buyerLon = (float)$buyer['longitude'];

        // Independently calculate great-circle distance
        // using PHP rather than the search page's SQL.
        // All test listings use the buyer's longitude.
        $distanceAt = static function (
            float $listingLat
        ) use ($buyerLat, $earthRadiusMeters): float {
            $halfAngle = deg2rad(
                $listingLat - $buyerLat
            ) / 2;

            return 2 * $earthRadiusMeters
                * asin(abs(sin($halfAngle)));
        };

        // Calculate latitude for a specified distance.
        $latitudeAtMiles = static function (
            float $miles
        ) use (
            $buyerLat,
            $earthRadiusMeters,
            $metersPerMile
        ): float {
            return round(
                $buyerLat + rad2deg(
                    $miles * $metersPerMile
                    / $earthRadiusMeters
                ),
                6
            );
        };

        // Prepare three boundary test locations.
        $insideLat = $latitudeAtMiles(9.99);
        $boundaryLat = $latitudeAtMiles(10.0);
        $outsideLat = $latitudeAtMiles(10.01);

        // Adjust the boundary point if rounding places
        // it slightly outside the selected radius.
        if ($distanceAt($boundaryLat) > $radiusMeters) {
            $boundaryLat = round(
                $boundaryLat - 0.000001,
                6
            );
        }

        // Verify the independently calculated distances.
        $this->assertLessThan(
            $radiusMeters,
            $distanceAt($insideLat)
        );

        $this->assertLessThanOrEqual(
            $radiusMeters,
            $distanceAt($boundaryLat)
        );

        // Boundary should be within 0.12 meters.
        $this->assertLessThanOrEqual(
            0.12,
            $radiusMeters - $distanceAt($boundaryLat)
        );

        $this->assertGreaterThan(
            $radiusMeters,
            $distanceAt($outsideLat)
        );

        // Define temporary listings for each scenario.
        $fixtures = [
            'inside' => [
                'TC-23-03 Inside Radius',
                $insideLat
            ],
            'boundary' => [
                'TC-23-03 At Radius Boundary',
                $boundaryLat
            ],
            'outside' => [
                'TC-23-03 Outside Radius',
                $outsideLat
            ]
        ];

        $createdIds = [];

        $insert = $pdo->prepare("
            INSERT INTO listings (
                seller_user_id,
                category_id,
                item_name,
                sale_type,
                fulfillment_type,
                location_zip_code,
                latitude,
                longitude,
                meetup_location_type,
                seller_meetup_radius,
                price,
                `condition`,
                quantity,
                listing_status
            ) VALUES (
                2, 7, :name,
                'fixed_price',
                'meetup',
                :zip,
                :latitude,
                :longitude,
                'library',
                10,
                15.00,
                'good',
                1,
                'active'
            )
        ");

        try {
            // Create all three temporary listings.
            foreach ($fixtures as $key => [$name, $latitude]) {
                $insert->execute([
                    'name' => $name,
                    'zip' => $zip,
                    'latitude' => $latitude,
                    'longitude' => $buyerLon
                ]);

                $createdIds[$key] = (int)$pdo->lastInsertId();
            }

            // Request listings within 10 miles of ZIP 92831.
            [$status, $body] = $this->get(
                '/pages/search.php',
                [
                    'browse' => 'all',
                    'fulfillment' => 'meetup',
                    'zip' => $zip,
                    'radius' => (string)$radiusMiles
                ]
            );

            // Verify successful page loading.
            $this->assertSame(200, $status);

            $this->assertStringNotContainsString(
                'Fatal error',
                $body
            );

            $this->assertStringNotContainsString(
                'Unable to retrieve search results',
                $body
            );

            $this->assertStringNotContainsString(
                'Invalid Meetup Location',
                $body
            );

            // Extract IDs of displayed listing cards.
            preg_match_all(
                '/href="listing\.php\?id=(\d+)"/',
                $body,
                $matches
            );

            $visibleIds = array_map(
                'intval',
                $matches[1]
            );

            // Listing A: Inside the radius.
            $this->assertContains(
                $createdIds['inside'],
                $visibleIds,
                'Inside-radius listing is missing.'
            );

            // Listing B: At the boundary.
            $this->assertContains(
                $createdIds['boundary'],
                $visibleIds,
                'Boundary listing is missing.'
            );

            // Listing C: Beyond the boundary.
            $this->assertNotContains(
                $createdIds['outside'],
                $visibleIds,
                'Outside-radius listing was incorrectly included.'
            );

        } finally {
            // Remove all temporary test listings,
            // even if an assertion fails.
            $delete = $pdo->prepare("
                DELETE FROM listings
                WHERE listing_id = :id
            ");

            foreach ($createdIds as $id) {
                $delete->execute(['id' => $id]);
            }
        }
    }

    // =========================================
    // TC-23-04: HANDLE MISSING BUYER ZIP CODE
    // =========================================

    public function testMeetupRadiusRejectsMissingZipCode(): void
    {
        // First confirm that active meetup listings exist
        // when no radius filter is applied.
        [$baselineStatus, $baselineBody] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'fulfillment' => 'meetup'
            ]
        );

        $this->assertSame(200, $baselineStatus);

        // Ensure the baseline returns actual listings.
        $this->assertMatchesRegularExpression(
            '/class="search-results__count"[^>]*>' .
            '\s*[1-9]\d*\s+results?\b/s',
            $baselineBody,
            'Test data needs active meetup listings.'
        );

        // Apply a 10-mile radius with no ZIP code.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'fulfillment' => 'meetup',
                'zip' => '',
                'radius' => '10'
            ]
        );

        // Verify the page responds successfully.
        $this->assertSame(200, $status);

        // Verify the validation error is displayed.
        $this->assertStringContainsString(
            'Invalid Meetup Location',
            $body
        );

        $this->assertStringContainsString(
            'Enter a valid 5-digit ZIP code to filter or sort by distance.',
            $body
        );

        // Verify the error is displayed as an alert.
        $this->assertMatchesRegularExpression(
            '/class="search-message search-message--error"' .
            '\s+role="alert"/s',
            $body
        );

        // Verify no unfiltered listing results appear.
        $this->assertStringNotContainsString(
            '<article class="listing-card">',
            $body,
            'Listings should not appear when the ZIP code is missing.'
        );

        // Verify the result count remains zero.
        $this->assertMatchesRegularExpression(
            '/class="search-results__count"[^>]*>' .
            '\s*0 results\b/s',
            $body
        );

        // Verify the ZIP code remains blank.
        $this->assertMatchesRegularExpression(
            '/<input\b(?=[^>]*name="zip")' .
            '(?=[^>]*value="")[^>]*>/s',
            $body
        );

        // Verify the 10-mile radius remains selected.
        $this->assertMatchesRegularExpression(
            '/<option\b(?=[^>]*value="10")' .
            '(?=[^>]*selected)[^>]*>/s',
            $body
        );

        // Verify no PHP or database errors appear.
        $this->assertStringNotContainsString(
            'Fatal error',
            $body
        );

        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        $this->assertStringNotContainsString(
            'SQLSTATE[',
            $body
        );
    }

    // =========================================
    // TC-23-05: HANDLE INVALID OR UNKNOWN ZIP CODE
    // =========================================

    public function testMeetupRadiusRejectsInvalidOrUnknownZipCodes(): void
    {
        // Connect to the Docker test database.
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
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Verify that the unknown ZIP is not in the
        // geographic reference table.
        $unknownZip = '00000';

        $zipStmt = $pdo->prepare("
            SELECT 1
            FROM zip_code_locations
            WHERE zip_code = :zip
            LIMIT 1
        ");

        $zipStmt->execute(['zip' => $unknownZip]);

        $this->assertFalse(
            $zipStmt->fetchColumn(),
            'ZIP 00000 must be absent from the reference table.'
        );

        // Confirm that meetup listings normally appear
        // when no radius filter is applied.
        [$baselineStatus, $baselineBody] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'fulfillment' => 'meetup'
            ]
        );

        $this->assertSame(200, $baselineStatus);

        $this->assertStringContainsString(
            '<article class="listing-card">',
            $baselineBody,
            'Test data needs active meetup listings.'
        );

        // Define the two invalid ZIP scenarios.
        $testCases = [
            'Invalid ZIP format' => [
                'zip' => '12A45',
                'message' =>
                    'Enter a valid 5-digit ZIP code to filter or sort by distance.'
            ],
            'Unknown ZIP code' => [
                'zip' => $unknownZip,
                'message' =>
                    'ZIP code not found. Please enter another ZIP code.'
            ]
        ];

        foreach ($testCases as $scenario => $testData) {

            // Request the search page using invalid ZIP data.
            [$status, $body] = $this->get(
                '/pages/search.php',
                [
                    'browse' => 'all',
                    'fulfillment' => 'meetup',
                    'zip' => $testData['zip'],
                    'radius' => '10'
                ]
            );

            // Verify the page responds successfully.
            $this->assertSame(
                200,
                $status,
                $scenario . ': Unexpected HTTP status.'
            );

            // Verify the location error appears.
            $this->assertStringContainsString(
                'Invalid Meetup Location',
                $body,
                $scenario . ': Missing validation heading.'
            );

            // Verify the correct error message appears.
            $this->assertStringContainsString(
                $testData['message'],
                $body,
                $scenario . ': Incorrect validation message.'
            );

            // Verify the error is marked as an alert.
            $this->assertMatchesRegularExpression(
                '/class="search-message search-message--error"' .
                '\s+role="alert"/s',
                $body
            );

            // Verify invalid input does not return
            // unfiltered marketplace listings.
            $this->assertStringNotContainsString(
                '<article class="listing-card">',
                $body,
                $scenario . ': Listings should not be displayed.'
            );

            // Verify the results count remains zero.
            $this->assertMatchesRegularExpression(
                '/class="search-results__count"[^>]*>' .
                '\s*0 results\b/s',
                $body,
                $scenario . ': Result count should be zero.'
            );

            // Verify the entered ZIP remains in the form.
            $this->assertMatchesRegularExpression(
                '/<input\b(?=[^>]*name="zip")' .
                '(?=[^>]*value="' .
                preg_quote($testData['zip'], '/') .
                '")[^>]*>/s',
                $body,
                $scenario . ': ZIP selection was not preserved.'
            );

            // Verify the 10-mile radius remains selected.
            $this->assertMatchesRegularExpression(
                '/<option\b(?=[^>]*value="10")' .
                '(?=[^>]*selected)[^>]*>/s',
                $body
            );

            // Verify no PHP or database errors appear.
            $this->assertStringNotContainsString(
                'Fatal error',
                $body
            );

            $this->assertStringNotContainsString(
                'Warning:',
                $body
            );

            $this->assertStringNotContainsString(
                'SQLSTATE[',
                $body
            );

            $this->assertStringNotContainsString(
                'Unable to retrieve search results',
                $body
            );
        }
    }

    // =========================================
    // TC-23-06: TEST ALL SUPPORTED RADIUS SELECTIONS
    // =========================================

    public function testMeetupRadiusSupportsAllDistanceSelections(): void
    {
        $zip = '92831';
        $radii = [5, 10, 25, 50];

        // Connect to the Docker test database.
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
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Retrieve coordinates for ZIP 92831.
        $zipStmt = $pdo->prepare("
            SELECT latitude, longitude
            FROM zip_code_locations
            WHERE zip_code = :zip
        ");

        $zipStmt->execute(['zip' => $zip]);
        $buyer = $zipStmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray(
            $buyer,
            'ZIP 92831 must exist.'
        );

        $buyerLat = (float)$buyer['latitude'];
        $buyerLon = (float)$buyer['longitude'];

        // Create controlled listings at different distances.
        $fixtureMiles = [3, 8, 20, 40, 55];
        $fixtureIds = [];

        $insert = $pdo->prepare("
            INSERT INTO listings (
                seller_user_id,
                category_id,
                item_name,
                sale_type,
                fulfillment_type,
                location_zip_code,
                latitude,
                longitude,
                meetup_location_type,
                seller_meetup_radius,
                price,
                `condition`,
                quantity,
                listing_status
            ) VALUES (
                2, 7, :name,
                'fixed_price',
                'meetup',
                :zip,
                :latitude,
                :longitude,
                'library',
                10,
                15.00,
                'good',
                1,
                'active'
            )
        ");

        // Establish the expected database results
        // for each supported radius.
        $expectedStmt = $pdo->prepare("
            SELECT listing_id
            FROM listings
            WHERE listing_status = 'active'
                AND fulfillment_type = 'meetup'
                AND latitude IS NOT NULL
                AND longitude IS NOT NULL
                AND ST_Distance_Sphere(
                    POINT(longitude, latitude),
                    POINT(:buyer_longitude, :buyer_latitude)
                ) <= :radius_meters
        ");

        try {
            // Insert all five temporary test listings.
            foreach ($fixtureMiles as $miles) {

                // Calculate coordinates north of the buyer.
                $latitude = round(
                    $buyerLat + rad2deg(
                        $miles * 1609.344 / 6370986.0
                    ),
                    6
                );

                $insert->execute([
                    'name' => "TC-23-06 Meetup {$miles} Miles",
                    'zip' => $zip,
                    'latitude' => $latitude,
                    'longitude' => $buyerLon
                ]);

                $fixtureIds[$miles] =
                    (int)$pdo->lastInsertId();
            }

            $previousIds = [];

            // Test all four supported radius selections.
            foreach ($radii as $radius) {

                // Find expected listings in MySQL.
                $expectedStmt->execute([
                    'buyer_longitude' => $buyerLon,
                    'buyer_latitude' => $buyerLat,
                    'radius_meters' => $radius * 1609.344
                ]);

                $expectedIds = array_map(
                    'intval',
                    $expectedStmt->fetchAll(
                        \PDO::FETCH_COLUMN
                    )
                );

                sort($expectedIds);

                // Request search results for this radius.
                [$status, $body] = $this->get(
                    '/pages/search.php',
                    [
                        'browse' => 'all',
                        'fulfillment' => 'meetup',
                        'zip' => $zip,
                        'radius' => (string)$radius
                    ]
                );

                // Verify the page loads without errors.
                $this->assertSame(200, $status);

                $this->assertStringNotContainsString(
                    'Fatal error',
                    $body
                );

                $this->assertStringNotContainsString(
                    'Unable to retrieve search results',
                    $body
                );

                $this->assertStringNotContainsString(
                    'Invalid Meetup Location',
                    $body
                );

                $this->assertStringNotContainsString(
                    'SQLSTATE[',
                    $body
                );

                // Extract displayed listing IDs.
                preg_match_all(
                    '/<article class="listing-card">.*?' .
                    'href="listing\.php\?id=(\d+)"/s',
                    $body,
                    $matches
                );

                $actualIds = array_map(
                    'intval',
                    $matches[1]
                );

                sort($actualIds);

                // Verify results against MySQL.
                $this->assertSame(
                    $expectedIds,
                    $actualIds,
                    "Incorrect meetup results for {$radius} miles."
                );

                // Verify that each temporary listing is
                // included or excluded at the correct radius.
                foreach ($fixtureIds as $miles => $id) {

                    if ($miles <= $radius) {

                        $this->assertContains(
                            $id,
                            $actualIds,
                            "{$miles}-mile listing missing " .
                            "at {$radius} miles."
                        );

                    } else {

                        $this->assertNotContains(
                            $id,
                            $actualIds,
                            "{$miles}-mile listing appeared " .
                            "at {$radius} miles."
                        );
                    }
                }

                // Increasing the radius must not remove
                // listings included at a smaller radius.
                $this->assertSame(
                    [],
                    array_values(
                        array_diff($previousIds, $actualIds)
                    ),
                    "Increasing to {$radius} miles " .
                    "removed a previous listing."
                );

                $previousIds = $actualIds;
            }

        } finally {

            // Remove all temporary listings,
            // including when an assertion fails.
            $delete = $pdo->prepare("
                DELETE FROM listings
                WHERE listing_id = :id
            ");

            foreach ($fixtureIds as $id) {
                $delete->execute(['id' => $id]);
            }
        }
    }

    // =========================================
    // TC-23-07: PRESERVE SHIPPING DURING RADIUS FILTERING
    // =========================================

    public function testMeetupRadiusPreservesShippingWithoutDeliverySelection(): void
    {
        $zip = '92831';
        $radiusMiles = 10;

        // Connect to the Docker test database.
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
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Retrieve coordinates for ZIP 92831.
        $zipStmt = $pdo->prepare("
            SELECT latitude, longitude
            FROM zip_code_locations
            WHERE zip_code = :zip
        ");

        $zipStmt->execute(['zip' => $zip]);
        $buyer = $zipStmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray(
            $buyer,
            'ZIP 92831 must exist.'
        );

        $buyerLat = (float)$buyer['latitude'];
        $buyerLon = (float)$buyer['longitude'];

        $fixtureIds = [];

        // Create three listings at controlled distances.
        $fixtures = [
            'near_meetup' => [
                'type' => 'meetup',
                'miles' => 3
            ],
            'far_meetup' => [
                'type' => 'meetup',
                'miles' => 20
            ],
            'far_shipping' => [
                'type' => 'shipping',
                'miles' => 40
            ]
        ];

        $insert = $pdo->prepare("
            INSERT INTO listings (
                seller_user_id,
                category_id,
                item_name,
                sale_type,
                fulfillment_type,
                location_zip_code,
                latitude,
                longitude,
                meetup_location_type,
                seller_meetup_radius,
                price,
                `condition`,
                quantity,
                listing_status
            ) VALUES (
                2, 7, :name,
                'fixed_price',
                :type,
                :zip,
                :latitude,
                :longitude,
                :meetup_type,
                :meetup_radius,
                15.00,
                'good',
                1,
                'active'
            )
        ");

        try {
            // Insert the temporary test listings.
            foreach ($fixtures as $label => $fixture) {
                $latitude = round(
                    $buyerLat + rad2deg(
                        $fixture['miles'] * 1609.344 / 6370986.0
                    ),
                    6
                );

                $isMeetup = $fixture['type'] === 'meetup';

                $insert->execute([
                    'name' => 'TC-23-07 ' . $label,
                    'type' => $fixture['type'],
                    'zip' => $zip,
                    'latitude' => $latitude,
                    'longitude' => $buyerLon,
                    'meetup_type' => $isMeetup ? 'library' : null,
                    'meetup_radius' => $isMeetup ? 10 : null
                ]);

                $fixtureIds[$label] = (int)$pdo->lastInsertId();
            }

            // Find every active shipping listing.
            $shippingIds = array_map(
                'intval',
                $pdo->query("
                    SELECT listing_id
                    FROM listings
                    WHERE listing_status = 'active'
                        AND fulfillment_type = 'shipping'
                ")->fetchAll(\PDO::FETCH_COLUMN)
            );

            $this->assertNotEmpty(
                $shippingIds,
                'Active shipping listings must exist.'
            );

            // Calculate expected results:
            // All shipping listings OR nearby meetup listings.
            $expectedStmt = $pdo->prepare("
                SELECT listing_id
                FROM listings
                WHERE listing_status = 'active'
                    AND (
                        fulfillment_type = 'shipping'
                        OR (
                            fulfillment_type = 'meetup'
                            AND latitude IS NOT NULL
                            AND longitude IS NOT NULL
                            AND ST_Distance_Sphere(
                                POINT(longitude, latitude),
                                POINT(:lon, :lat)
                            ) <= :meters
                        )
                    )
            ");

            $expectedStmt->execute([
                'lon' => $buyerLon,
                'lat' => $buyerLat,
                'meters' => $radiusMiles * 1609.344
            ]);

            $expectedIds = array_map(
                'intval',
                $expectedStmt->fetchAll(\PDO::FETCH_COLUMN)
            );

            sort($expectedIds);

            // Request the page without selecting
            // either Shipping or Meetup.
            [$status, $body] = $this->get(
                '/pages/search.php',
                [
                    'browse' => 'all',
                    'zip' => $zip,
                    'radius' => (string)$radiusMiles
                ]
            );

            // Verify successful page loading.
            $this->assertSame(200, $status);

            foreach ([
                'Fatal error',
                'SQLSTATE[',
                'Unable to retrieve search results',
                'Invalid Meetup Location'
            ] as $error) {
                $this->assertStringNotContainsString(
                    $error,
                    $body
                );
            }

            // Extract the listing IDs shown on the page.
            preg_match_all(
                '/<article class="listing-card">.*?' .
                'href="listing\.php\?id=(\d+)"/s',
                $body,
                $matches
            );

            $actualIds = array_map(
                'intval',
                $matches[1]
            );

            sort($actualIds);

            // Verify the complete result set.
            $this->assertSame(
                $expectedIds,
                $actualIds,
                'Radius filtering must preserve shipping ' .
                'and include only nearby meetup listings.'
            );

            // Verify all shipping listings remain visible.
            foreach ($shippingIds as $id) {
                $this->assertContains(
                    $id,
                    $actualIds,
                    "Shipping listing {$id} disappeared."
                );
            }

            // Verify each controlled test scenario.
            $this->assertContains(
                $fixtureIds['near_meetup'],
                $actualIds,
                'Nearby meetup listing should appear.'
            );

            $this->assertContains(
                $fixtureIds['far_shipping'],
                $actualIds,
                'Distant shipping listing should remain visible.'
            );

            $this->assertNotContains(
                $fixtureIds['far_meetup'],
                $actualIds,
                'Distant meetup listing should be excluded.'
            );

            // Confirm neither delivery method is selected.
            $this->assertDoesNotMatchRegularExpression(
                '/<input\b(?=[^>]*name="fulfillment")' .
                '(?=[^>]*\bchecked\b)[^>]*>/s',
                $body
            );

            // Verify ZIP and radius selections remain applied.
            $this->assertMatchesRegularExpression(
                '/<input\b(?=[^>]*name="zip")' .
                '(?=[^>]*value="92831")[^>]*>/s',
                $body
            );

            $this->assertMatchesRegularExpression(
                '/<option\b(?=[^>]*value="10")' .
                '(?=[^>]*\bselected\b)[^>]*>/s',
                $body
            );

        } finally {
            // Delete all temporary listings,
            // including if an assertion fails.
            $delete = $pdo->prepare("
                DELETE FROM listings
                WHERE listing_id = :id
            ");

            foreach ($fixtureIds as $id) {
                $delete->execute(['id' => $id]);
            }
        }
    }

    // =========================================
    // TC-23-08: HANDLE MEETUP LISTINGS WITH MISSING COORDINATES
    // =========================================

    public function testMeetupRadiusExcludesListingsWithMissingCoordinates(): void
    {
        $zip = '92831';
        $radiusMiles = 10;

        // Connect to the Docker test database.
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
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Retrieve the coordinates for ZIP 92831.
        $zipStmt = $pdo->prepare("
            SELECT latitude, longitude
            FROM zip_code_locations
            WHERE zip_code = :zip
            LIMIT 1
        ");

        $zipStmt->execute(['zip' => $zip]);
        $buyer = $zipStmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray(
            $buyer,
            'ZIP 92831 must exist.'
        );

        // Define three controlled meetup listings.
        $fixtures = [
            'missing_latitude' => [
                'name' => 'TC-23-08 Missing Latitude',
                'latitude' => null,
                'longitude' => $buyer['longitude']
            ],
            'missing_longitude' => [
                'name' => 'TC-23-08 Missing Longitude',
                'latitude' => $buyer['latitude'],
                'longitude' => null
            ],
            'valid_coordinates' => [
                'name' => 'TC-23-08 Valid Coordinates',
                'latitude' => $buyer['latitude'],
                'longitude' => $buyer['longitude']
            ]
        ];

        // Prepare temporary listing insertion.
        $insert = $pdo->prepare("
            INSERT INTO listings (
                seller_user_id,
                category_id,
                item_name,
                sale_type,
                fulfillment_type,
                location_zip_code,
                latitude,
                longitude,
                meetup_location_type,
                seller_meetup_radius,
                price,
                `condition`,
                quantity,
                listing_status
            ) VALUES (
                2, 7, :name,
                'fixed_price',
                'meetup',
                :zip,
                :latitude,
                :longitude,
                'library',
                10,
                15.00,
                'good',
                1,
                'active'
            )
        ");

        $createdIds = [];

        try {
            // Insert the three temporary listings.
            foreach ($fixtures as $scenario => $fixture) {
                $insert->execute([
                    'name' => $fixture['name'],
                    'zip' => $zip,
                    'latitude' => $fixture['latitude'],
                    'longitude' => $fixture['longitude']
                ]);

                $createdIds[$scenario] =
                    (int)$pdo->lastInsertId();
            }

            // First verify all three listings appear
            // when no radius filter is applied.
            [$baselineStatus, $baselineBody] = $this->get(
                '/pages/search.php',
                [
                    'browse' => 'all',
                    'fulfillment' => 'meetup'
                ]
            );

            $this->assertSame(200, $baselineStatus);

            foreach ($createdIds as $scenario => $id) {
                $this->assertStringContainsString(
                    'href="listing.php?id=' . $id . '"',
                    $baselineBody,
                    "{$scenario} fixture must appear without a radius."
                );
            }

            // Apply the 10-mile meetup radius filter.
            [$status, $body] = $this->get(
                '/pages/search.php',
                [
                    'browse' => 'all',
                    'fulfillment' => 'meetup',
                    'zip' => $zip,
                    'radius' => (string)$radiusMiles
                ]
            );

            // Verify the page loads successfully.
            $this->assertSame(200, $status);

            foreach ([
                'Fatal error',
                'SQLSTATE[',
                'Unable to retrieve search results',
                'Invalid Meetup Location'
            ] as $errorText) {
                $this->assertStringNotContainsString(
                    $errorText,
                    $body
                );
            }

            // Extract displayed listing IDs.
            preg_match_all(
                '/<article\b[^>]*class="listing-card"[^>]*>.*?' .
                'href="listing\.php\?id=(\d+)"/s',
                $body,
                $matches
            );

            $visibleIds = array_map(
                'intval',
                $matches[1]
            );

            // Verify the NULL latitude listing is excluded.
            $this->assertNotContains(
                $createdIds['missing_latitude'],
                $visibleIds,
                'Meetup listing with NULL latitude must be excluded.'
            );

            // Verify the NULL longitude listing is excluded.
            $this->assertNotContains(
                $createdIds['missing_longitude'],
                $visibleIds,
                'Meetup listing with NULL longitude must be excluded.'
            );

            // Verify the valid nearby listing is included.
            $this->assertContains(
                $createdIds['valid_coordinates'],
                $visibleIds,
                'Nearby meetup listing with valid coordinates must appear.'
            );

        } finally {
            // Remove temporary listings even if a test fails.
            $delete = $pdo->prepare("
                DELETE FROM listings
                WHERE listing_id = :id
            ");

            foreach ($createdIds as $id) {
                $delete->execute(['id' => $id]);
            }
        }
    }

    // =========================================
    // TC-23-09: VERIFY DISTANCE CALCULATION ACCURACY
    // =========================================

    public function testMeetupRadiusDistanceCalculationAccuracy(): void
    {
        $zip = '92831';
        $metersPerMile = 1609.344;
        $toleranceMiles = 0.02;

        // Predetermined expected distances, calculated
        // independently from these known coordinates.
        $fixtures = [
            'same_location' => [
                'latitude' => 33.887300,
                'longitude' => -117.894600,
                'expected_miles' => 0.0000
            ],
            'north' => [
                'latitude' => 33.937300,
                'longitude' => -117.894600,
                'expected_miles' => 3.4547
            ],
            'northeast' => [
                'latitude' => 34.087300,
                'longitude' => -117.694600,
                'expected_miles' => 17.9510
            ],
            'west' => [
                'latitude' => 33.887300,
                'longitude' => -118.294600,
                'expected_miles' => 22.9428
            ]
        ];

        // Connect to the disposable Docker test database.
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
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Confirm that ZIP 92831 matches the known
        // coordinates used for expected distances.
        $zipStmt = $pdo->prepare("
            SELECT latitude, longitude
            FROM zip_code_locations
            WHERE zip_code = :zip
        ");

        $zipStmt->execute(['zip' => $zip]);
        $buyer = $zipStmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray(
            $buyer,
            'ZIP 92831 must exist.'
        );

        $this->assertEqualsWithDelta(
            33.887300,
            (float)$buyer['latitude'],
            0.000001
        );

        $this->assertEqualsWithDelta(
            -117.894600,
            (float)$buyer['longitude'],
            0.000001
        );

        // Prepare temporary meetup listings.
        $insert = $pdo->prepare("
            INSERT INTO listings (
                seller_user_id,
                category_id,
                item_name,
                sale_type,
                fulfillment_type,
                location_zip_code,
                latitude,
                longitude,
                meetup_location_type,
                seller_meetup_radius,
                price,
                `condition`,
                quantity,
                listing_status
            ) VALUES (
                2, 7, :name,
                'fixed_price',
                'meetup',
                :zip,
                :latitude,
                :longitude,
                'library',
                10,
                15.00,
                'good',
                1,
                'active'
            )
        ");

        // Use the same distance calculation
        // as the search page.
        $distanceStmt = $pdo->prepare("
            SELECT ST_Distance_Sphere(
                POINT(l.longitude, l.latitude),
                POINT(z.longitude, z.latitude)
            ) AS distance_meters
            FROM listings AS l
            JOIN zip_code_locations AS z
                ON z.zip_code = :zip
            WHERE l.listing_id = :id
        ");

        $createdIds = [];

        try {
            foreach ($fixtures as $name => $fixture) {

                // Insert a listing at known coordinates.
                $insert->execute([
                    'name' => 'TC-23-09 ' . $name,
                    'zip' => $zip,
                    'latitude' => $fixture['latitude'],
                    'longitude' => $fixture['longitude']
                ]);

                $id = (int)$pdo->lastInsertId();
                $createdIds[] = $id;

                // Calculate the same distance twice
                // to verify repeatable results.
                $distances = [];

                for ($run = 0; $run < 2; $run++) {
                    $distanceStmt->execute([
                        'zip' => $zip,
                        'id' => $id
                    ]);

                    $meters = $distanceStmt->fetchColumn();

                    $this->assertNotFalse($meters);
                    $distances[] = (float)$meters;
                }

                // Verify identical coordinates produce
                // consistent distance calculations.
                $this->assertEqualsWithDelta(
                    $distances[0],
                    $distances[1],
                    0.000001,
                    "Repeated distance calculation changed: {$name}."
                );

                // Convert MySQL meters into miles.
                $actualMiles = $distances[0] / $metersPerMile;

                // Compare against independently
                // calculated expected distance.
                $this->assertEqualsWithDelta(
                    $fixture['expected_miles'],
                    $actualMiles,
                    $toleranceMiles,
                    "Distance calculation is incorrect: {$name}."
                );
            }

        } finally {

            // Remove all temporary test listings.
            $delete = $pdo->prepare("
                DELETE FROM listings
                WHERE listing_id = :id
            ");

            foreach ($createdIds as $id) {
                $delete->execute(['id' => $id]);
            }
        }
    }

    // =========================================
    // TC-23-10: RADIUS + CONDITION + DELIVERY + DISTANCE SORT
    // =========================================

    public function testMeetupRadiusCombinesWithConditionAndDistanceSort(): void
    {
        $zip = '92831';
        $radiusMiles = 25;

        // Connect to the Docker test database.
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
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Retrieve coordinates for ZIP 92831.
        $zipStmt = $pdo->prepare("
            SELECT latitude, longitude
            FROM zip_code_locations
            WHERE zip_code = :zip
            LIMIT 1
        ");

        $zipStmt->execute(['zip' => $zip]);
        $buyer = $zipStmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray(
            $buyer,
            'ZIP 92831 must exist.'
        );

        $buyerLat = (float)$buyer['latitude'];
        $buyerLon = (float)$buyer['longitude'];

        // Each excluded listing violates a different filter.
        $fixtures = [
            'near_good' => [3, 'meetup', 'good', 'active'],
            'middle_good' => [12, 'meetup', 'good', 'active'],
            'far_good' => [23, 'meetup', 'good', 'active'],
            'outside_radius' => [30, 'meetup', 'good', 'active'],
            'wrong_condition' => [8, 'meetup', 'excellent', 'active'],
            'wrong_delivery' => [4, 'shipping', 'good', 'active'],
            'inactive' => [6, 'meetup', 'good', 'sold']
        ];

        // Prepare temporary listing insertion.
        $insert = $pdo->prepare("
            INSERT INTO listings (
                seller_user_id,
                category_id,
                item_name,
                sale_type,
                fulfillment_type,
                location_zip_code,
                latitude,
                longitude,
                meetup_location_type,
                seller_meetup_radius,
                price,
                `condition`,
                quantity,
                listing_status
            ) VALUES (
                2, 7, :name,
                'fixed_price',
                :fulfillment,
                :zip,
                :latitude,
                :longitude,
                :meetup_type,
                :meetup_radius,
                15.00,
                :condition,
                1,
                :status
            )
        ");

        $fixtureIds = [];

        try {
            // Insert all seven temporary listings.
            foreach ($fixtures as $label => $details) {
                [$miles, $fulfillment, $condition, $status] = $details;

                $latitude = round(
                    $buyerLat + rad2deg(
                        $miles * 1609.344 / 6370986.0
                    ),
                    6
                );

                $isMeetup = $fulfillment === 'meetup';

                $insert->execute([
                    'name' => 'TC-23-10 ' . $label,
                    'fulfillment' => $fulfillment,
                    'zip' => $zip,
                    'latitude' => $latitude,
                    'longitude' => $buyerLon,
                    'meetup_type' => $isMeetup ? 'library' : null,
                    'meetup_radius' => $isMeetup ? 10 : null,
                    'condition' => $condition,
                    'status' => $status
                ]);

                $fixtureIds[$label] = (int)$pdo->lastInsertId();
            }

            // Determine all expected eligible listings
            // in nearest-first order using MySQL.
            $expectedStmt = $pdo->prepare("
                SELECT
                    l.listing_id,
                    ST_Distance_Sphere(
                        POINT(l.longitude, l.latitude),
                        POINT(z.longitude, z.latitude)
                    ) AS distance_meters
                FROM listings AS l

                JOIN zip_code_locations AS z
                    ON z.zip_code = :zip

                WHERE l.listing_status = 'active'
                    AND l.fulfillment_type = 'meetup'
                    AND l.`condition` = 'good'
                    AND l.latitude IS NOT NULL
                    AND l.longitude IS NOT NULL
                    AND ST_Distance_Sphere(
                        POINT(l.longitude, l.latitude),
                        POINT(z.longitude, z.latitude)
                    ) <= :radius_meters

                ORDER BY
                    distance_meters ASC,
                    l.created_at DESC,
                    l.listing_id DESC
            ");

            $expectedStmt->execute([
                'zip' => $zip,
                'radius_meters' => $radiusMiles * 1609.344
            ]);

            $expectedIds = array_map(
                'intval',
                $expectedStmt->fetchAll(\PDO::FETCH_COLUMN)
            );

            // Apply all filters.
            $query = [
                'browse' => 'all',
                'fulfillment' => 'meetup',
                'condition' => ['good'],
                'zip' => $zip,
                'radius' => (string)$radiusMiles
            ];

            // Request the page before changing sorting.
            [$beforeStatus, $beforeBody] = $this->get(
                '/pages/search.php',
                $query + ['sort' => 'recent']
            );

            // Change sorting while keeping the filters.
            [$afterStatus, $afterBody] = $this->get(
                '/pages/search.php',
                $query + ['sort' => 'distance_near']
            );

            // Verify both requests complete without errors.
            foreach (
                [
                    [$beforeStatus, $beforeBody],
                    [$afterStatus, $afterBody]
                ] as [$status, $body]
            ) {
                $this->assertSame(200, $status);

                foreach (
                    [
                        'Fatal error',
                        'SQLSTATE[',
                        'Unable to retrieve search results',
                        'Invalid Meetup Location'
                    ] as $error
                ) {
                    $this->assertStringNotContainsString(
                        $error,
                        $body
                    );
                }
            }

            // Extract displayed listing IDs in page order.
            $extractIds = static function (string $html): array {
                preg_match_all(
                    '/<article class="listing-card">.*?' .
                    'href="listing\.php\?id=(\d+)"/s',
                    $html,
                    $matches
                );

                return array_map('intval', $matches[1]);
            };

            $beforeIds = $extractIds($beforeBody);
            $afterIds = $extractIds($afterBody);

            // Before sorting, verify filters return
            // the correct set of listings.
            $beforeSorted = $beforeIds;
            $expectedSorted = $expectedIds;

            sort($beforeSorted);
            sort($expectedSorted);

            $this->assertSame(
                $expectedSorted,
                $beforeSorted,
                'Filters must return the correct listings.'
            );

            // After sorting, verify the exact result
            // set and nearest-first ordering.
            $this->assertSame(
                $expectedIds,
                $afterIds,
                'Distance sorting must retain all filters ' .
                'and order results nearest first.'
            );

            // Verify all three eligible listings appear.
            foreach (
                ['near_good', 'middle_good', 'far_good'] as $label
            ) {
                $this->assertContains(
                    $fixtureIds[$label],
                    $afterIds,
                    "{$label} should appear."
                );
            }

            // Verify each ineligible listing is excluded.
            foreach (
                [
                    'outside_radius',
                    'wrong_condition',
                    'wrong_delivery',
                    'inactive'
                ] as $label
            ) {
                $this->assertNotContains(
                    $fixtureIds[$label],
                    $afterIds,
                    "{$label} should be excluded."
                );
            }

            // Confirm the sorting form preserves
            // all active filter parameters.
            $this->assertSame(
                1,
                preg_match(
                    '/<form\b(?=[^>]*class="search-results__sort")' .
                    '[^>]*>.*?<\/form>/s',
                    $afterBody,
                    $formMatches
                )
            );

            $sortForm = $formMatches[0];

            foreach (
                [
                    ['condition\[\]', 'good'],
                    ['fulfillment', 'meetup'],
                    ['zip', '92831'],
                    ['radius', '25'],
                    ['browse', 'all']
                ] as [$namePattern, $value]
            ) {
                $this->assertMatchesRegularExpression(
                    '/<input\b(?=[^>]*name="' .
                    $namePattern .
                    '")(?=[^>]*value="' .
                    $value .
                    '")[^>]*>/s',
                    $sortForm
                );
            }

            // Verify Distance: Near to Far is selected.
            $this->assertMatchesRegularExpression(
                '/<option\b(?=[^>]*value="distance_near")' .
                '(?=[^>]*\bselected\b)[^>]*>/s',
                $sortForm
            );

            // Verify Good remains checked.
            $this->assertMatchesRegularExpression(
                '/<input\b(?=[^>]*name="condition\[\]")' .
                '(?=[^>]*value="good")' .
                '(?=[^>]*\bchecked\b)[^>]*>/s',
                $afterBody
            );

            // Verify Meetup remains checked.
            $this->assertMatchesRegularExpression(
                '/<input\b(?=[^>]*name="fulfillment")' .
                '(?=[^>]*value="meetup")' .
                '(?=[^>]*\bchecked\b)[^>]*>/s',
                $afterBody
            );

            // Verify the 25-mile radius is preserved.
            $this->assertMatchesRegularExpression(
                '/<option\b(?=[^>]*value="25")' .
                '(?=[^>]*\bselected\b)[^>]*>/s',
                $afterBody
            );

        } finally {
            // Delete temporary listings even if
            // an assertion fails.
            $delete = $pdo->prepare(
                'DELETE FROM listings WHERE listing_id = :id'
            );

            foreach ($fixtureIds as $id) {
                $delete->execute(['id' => $id]);
            }
        }
    }


    // =========================================
    // TC-24-01: PRICE LOW TO HIGH
    // =========================================

    public function testPriceLowToHighSorting(): void
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

        // Retrieve active listings with unique item names.
        // Unique names let us identify listings in the HTML.
        $stmt = $pdo->query("
            SELECT
                l.item_name,
                l.price
            FROM listings AS l
            WHERE l.listing_status = 'active'
                AND (
                    SELECT COUNT(*)
                    FROM listings AS other
                    WHERE other.listing_status = 'active'
                        AND other.item_name = l.item_name
                ) = 1
        ");

        $expectedListings = $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );

        // Require enough test data.
        $this->assertGreaterThanOrEqual(
            2,
            count($expectedListings),
            'Test data needs at least two uniquely named active listings.'
        );

        $distinctPrices = array_unique(
            array_column($expectedListings, 'price')
        );

        $this->assertGreaterThanOrEqual(
            2,
            count($distinctPrices),
            'Test data needs at least two different prices.'
        );

        // Request the search page with ascending price sort.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'sort' => 'price_low'
            ]
        );

        // Verify the page loads without errors.
        $this->assertSame(200, $status);

        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        // Verify the correct sorting option is selected.
        $this->assertMatchesRegularExpression(
            '/<option\b(?=[^>]*value="price_low")(?=[^>]*\bselected\b)[^>]*>/s',
            $body
        );

        // Find where each listing name appears in the HTML.
        $displayedListings = [];

        foreach ($expectedListings as $listing) {

            $itemName = htmlspecialchars(
                $listing['item_name'],
                ENT_QUOTES,
                'UTF-8'
            );

            $position = strpos($body, $itemName);

            $this->assertNotFalse(
                $position,
                'Listing not found in search results: ' . $itemName
            );

            $displayedListings[] = [
                'price' => (float)$listing['price'],
                'position' => $position
            ];
        }

        // Order records by their position on the page.
        usort(
            $displayedListings,
            fn($a, $b) => $a['position'] <=> $b['position']
        );

        // Verify prices never decrease as we move down.
        for ($i = 1; $i < count($displayedListings); $i++) {

            $previousPrice = $displayedListings[$i - 1]['price'];
            $currentPrice = $displayedListings[$i]['price'];

            $this->assertGreaterThanOrEqual(
                $previousPrice,
                $currentPrice,
                'Listings are not sorted from lowest to highest price.'
            );
        }
    }

    // =========================================
    // TC-24-02: PRICE HIGH TO LOW
    // =========================================

    public function testPriceHighToLowSorting(): void
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

        // Retrieve active listings with unique item names.
        $stmt = $pdo->query("
            SELECT
                l.item_name,
                l.price
            FROM listings AS l
            WHERE l.listing_status = 'active'
                AND (
                    SELECT COUNT(*)
                    FROM listings AS other
                    WHERE other.listing_status = 'active'
                        AND other.item_name = l.item_name
                ) = 1
        ");

        $expectedListings = $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );

        // Require enough test data.
        $this->assertGreaterThanOrEqual(
            2,
            count($expectedListings),
            'Test data needs at least two uniquely named active listings.'
        );

        $distinctPrices = array_unique(
            array_column($expectedListings, 'price')
        );

        $this->assertGreaterThanOrEqual(
            2,
            count($distinctPrices),
            'Test data needs at least two different prices.'
        );

        // Request the search page with descending price sorting.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'sort' => 'price_high'
            ]
        );

        // Verify the page loads.
        $this->assertSame(200, $status);

        // Verify no search error occurred.
        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        // Verify Price High to Low remains selected.
        $this->assertMatchesRegularExpression(
            '/<option\b(?=[^>]*value="price_high")(?=[^>]*\bselected\b)[^>]*>/s',
            $body
        );

        // Find each listing's position in the HTML.
        $displayedListings = [];

        foreach ($expectedListings as $listing) {

            $itemName = htmlspecialchars(
                $listing['item_name'],
                ENT_QUOTES,
                'UTF-8'
            );

            $position = strpos($body, $itemName);

            $this->assertNotFalse(
                $position,
                'Listing not found in search results: ' . $itemName
            );

            $displayedListings[] = [
                'price' => (float)$listing['price'],
                'position' => $position
            ];
        }

        // Sort listings by their position on the page.
        usort(
            $displayedListings,
            fn($a, $b) => $a['position'] <=> $b['position']
        );

        // Verify prices never increase as we move down.
        for ($i = 1; $i < count($displayedListings); $i++) {

            $previousPrice = $displayedListings[$i - 1]['price'];
            $currentPrice = $displayedListings[$i]['price'];

            $this->assertTrue(
                $previousPrice >= $currentPrice,
                'Listings are not sorted from highest to lowest price.'
            );
        }
    }

    // =========================================
    // TC-24-03: SORT BY NEWEST
    // =========================================

    public function testNewestSorting(): void
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

        // Retrieve active listings in the expected order.
        // Unique item names help us identify them in the HTML.
        $stmt = $pdo->query("
            SELECT
                l.listing_id,
                l.item_name,
                l.created_at
            FROM listings AS l
            WHERE l.listing_status = 'active'
                AND (
                    SELECT COUNT(*)
                    FROM listings AS other
                    WHERE other.listing_status = 'active'
                        AND other.item_name = l.item_name
                ) = 1
            ORDER BY
                l.created_at DESC,
                l.listing_id DESC
        ");

        $expectedListings = $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );

        // Require enough test data.
        $this->assertGreaterThanOrEqual(
            2,
            count($expectedListings),
            'Test data needs at least two uniquely named active listings.'
        );

        // Verify listings have different creation timestamps.
        $distinctDates = array_unique(
            array_column($expectedListings, 'created_at')
        );

        $this->assertGreaterThanOrEqual(
            2,
            count($distinctDates),
            'Test data needs at least two different creation timestamps.'
        );

        // Request Browse All with Newest sorting.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'sort' => 'recent'
            ]
        );

        // Verify the page loads.
        $this->assertSame(200, $status);

        // Verify no search error occurred.
        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        // Verify Newest remains selected.
        $this->assertMatchesRegularExpression(
            '/<option\b(?=[^>]*value="recent")(?=[^>]*\bselected\b)[^>]*>/s',
            $body
        );

        // Track the position of each listing in the HTML.
        $previousPosition = -1;

        foreach ($expectedListings as $listing) {

            $itemName = htmlspecialchars(
                $listing['item_name'],
                ENT_QUOTES,
                'UTF-8'
            );

            // Find this listing in the displayed results.
            $position = strpos($body, $itemName);

            $this->assertNotFalse(
                $position,
                'Listing not found in search results: ' . $itemName
            );

            // The SQL query is already sorted newest first.
            // Each next listing must appear later in the HTML.
            $this->assertGreaterThan(
                $previousPosition,
                $position,
                'Listings are not displayed in newest-first order. ' .
                'Check listing ID: ' . $listing['listing_id']
            );

            $previousPosition = $position;
        }
    }

    // =========================================
    // TC-24-04: SORT BY OLDEST
    // =========================================

    public function testOldestSorting(): void
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

        // Retrieve active listings in oldest-first order.
        // Only use unique item names to identify listings.
        $stmt = $pdo->query("
            SELECT
                l.listing_id,
                l.item_name,
                l.created_at
            FROM listings AS l
            WHERE l.listing_status = 'active'
                AND (
                    SELECT COUNT(*)
                    FROM listings AS other
                    WHERE other.listing_status = 'active'
                        AND other.item_name = l.item_name
                ) = 1
            ORDER BY
                l.created_at ASC,
                l.listing_id ASC
        ");

        $expectedListings = $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );

        // Require at least two active listings.
        $this->assertGreaterThanOrEqual(
            2,
            count($expectedListings),
            'Test data needs at least two uniquely named active listings.'
        );

        // Verify that different creation dates exist.
        $distinctDates = array_unique(
            array_column($expectedListings, 'created_at')
        );

        $this->assertGreaterThanOrEqual(
            2,
            count($distinctDates),
            'Test data needs at least two different creation timestamps.'
        );

        // Request Browse All with Oldest sorting.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'sort' => 'oldest'
            ]
        );

        // Verify the page loads successfully.
        $this->assertSame(200, $status);

        // Verify no database error occurred.
        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        // Verify Oldest remains selected.
        $this->assertMatchesRegularExpression(
            '/<option\b(?=[^>]*value="oldest")(?=[^>]*\bselected\b)[^>]*>/s',
            $body
        );

        // Track listing positions in the HTML.
        $previousPosition = -1;

        foreach ($expectedListings as $listing) {

            $itemName = htmlspecialchars(
                $listing['item_name'],
                ENT_QUOTES,
                'UTF-8'
            );

            // Find the listing in the returned HTML.
            $position = strpos($body, $itemName);

            $this->assertNotFalse(
                $position,
                'Listing not found in search results: ' . $itemName
            );

            // Expected listings are ordered oldest first.
            // Each next listing must appear later in the HTML.
            $this->assertGreaterThan(
                $previousPosition,
                $position,
                'Listings are not displayed in oldest-first order. ' .
                'Check listing ID: ' . $listing['listing_id']
            );

            $previousPosition = $position;
        }
    }

    // =========================================
    // TC-24-05: SORTING WITH ACTIVE FILTERS
    // =========================================

    public function testSortingPreservesActiveFilters(): void
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

        // Find a shipping/condition combination with
        // at least two uniquely named listings
        // having different prices.
        $stmt = $pdo->query("
            SELECT l.`condition` AS selected_condition
            FROM listings AS l
            WHERE l.listing_status = 'active'
                AND l.fulfillment_type = 'shipping'
                AND (
                    SELECT COUNT(*)
                    FROM listings AS other
                    WHERE other.listing_status = 'active'
                        AND other.item_name = l.item_name
                ) = 1
            GROUP BY l.`condition`
            HAVING COUNT(*) >= 2
                AND COUNT(DISTINCT l.price) >= 2
            ORDER BY
                CASE
                    WHEN l.`condition` = 'good' THEN 0
                    ELSE 1
                END,
                COUNT(*) DESC
            LIMIT 1
        ");

        $condition = $stmt->fetchColumn();

        $this->assertNotFalse(
            $condition,
            'Test data needs two uniquely named shipping listings ' .
            'with the same condition and different prices.'
        );

        // Count all matching active listings.
        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM listings
            WHERE listing_status = 'active'
                AND fulfillment_type = 'shipping'
                AND `condition` = :condition
        ");

        $countStmt->execute([
            'condition' => $condition
        ]);

        $expectedCount = (int)$countStmt->fetchColumn();

        // Retrieve uniquely named matching listings
        // in the expected price order.
        $stmt = $pdo->prepare("
            SELECT
                l.listing_id,
                l.item_name,
                l.price
            FROM listings AS l
            WHERE l.listing_status = 'active'
                AND l.fulfillment_type = 'shipping'
                AND l.`condition` = :condition
                AND (
                    SELECT COUNT(*)
                    FROM listings AS other
                    WHERE other.listing_status = 'active'
                        AND other.item_name = l.item_name
                ) = 1
            ORDER BY
                l.price ASC,
                l.listing_id DESC
        ");

        $stmt->execute([
            'condition' => $condition
        ]);

        $expectedListings = $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );

        // Start with Newest sorting and both filters.
        [$initialStatus, $initialBody] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'fulfillment' => 'shipping',
                'condition' => [$condition],
                'sort' => 'recent'
            ]
        );

        $this->assertSame(200, $initialStatus);

        // Change sorting while keeping both filters.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'fulfillment' => 'shipping',
                'condition' => [$condition],
                'sort' => 'price_low'
            ]
        );

        $this->assertSame(200, $status);

        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        // Verify result counts before and after sorting.
        foreach ([$initialBody, $body] as $pageBody) {

            $matched = preg_match(
                '/class="search-results__count"[^>]*>\s*(\d+)\s+results?\b/',
                $pageBody,
                $matches
            );

            $this->assertSame(
                1,
                $matched,
                'Could not find the search result count.'
            );

            $this->assertSame(
                $expectedCount,
                (int)$matches[1],
                'Sorting should not change the filtered result count.'
            );

            // Verify Shipping remains selected.
            $this->assertMatchesRegularExpression(
                '/<input\b(?=[^>]*name="fulfillment")(?=[^>]*value="shipping")(?=[^>]*\bchecked\b)[^>]*>/s',
                $pageBody
            );

            // Verify the condition remains selected.
            $conditionPattern = preg_quote(
                (string)$condition,
                '/'
            );

            $this->assertMatchesRegularExpression(
                '/<input\b(?=[^>]*name="condition\[\]")' .
                '(?=[^>]*value="' . $conditionPattern . '")' .
                '(?=[^>]*\bchecked\b)[^>]*>/s',
                $pageBody
            );
        }

        // Verify Price Low to High is selected.
        $this->assertMatchesRegularExpression(
            '/<option\b(?=[^>]*value="price_low")(?=[^>]*\bselected\b)[^>]*>/s',
            $body
        );

        // Verify matching listings appear in ascending
        // price order, using their names in the HTML.
        $previousPosition = -1;

        foreach ($expectedListings as $listing) {

            $itemName = htmlspecialchars(
                $listing['item_name'],
                ENT_QUOTES,
                'UTF-8'
            );

            $position = strpos($body, $itemName);

            $this->assertNotFalse(
                $position,
                'Listing missing from results: ' . $itemName
            );

            $this->assertGreaterThan(
                $previousPosition,
                $position,
                'Filtered listings are not in ascending ' .
                'price order. Check listing ID: ' .
                $listing['listing_id']
            );

            $previousPosition = $position;
        }
    }

    // =========================================
    // TC-24-06: SORT BY DISTANCE NEAR TO FAR
    // =========================================

    public function testDistanceNearToFarSorting(): void
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

        // Choose a valid ZIP code from the database.
        // Prefer 90001 when available.
        $zipCode = $pdo->query("
            SELECT zip_code
            FROM zip_code_locations
            WHERE latitude IS NOT NULL
                AND longitude IS NOT NULL
            ORDER BY
                (zip_code = '90001') DESC,
                zip_code ASC
            LIMIT 1
        ")->fetchColumn();

        $this->assertNotFalse(
            $zipCode,
            'Test data needs at least one valid ZIP code.'
        );

        // Calculate distances for active meetup listings.
        // Use unique item names to identify listings in HTML.
        $stmt = $pdo->prepare("
            SELECT
                l.listing_id,
                l.item_name,
                ST_Distance_Sphere(
                    POINT(l.longitude, l.latitude),
                    POINT(z.longitude, z.latitude)
                ) AS distance_meters
            FROM listings AS l
            JOIN zip_code_locations AS z
                ON z.zip_code = :zip
            WHERE l.listing_status = 'active'
                AND l.fulfillment_type = 'meetup'
                AND l.latitude IS NOT NULL
                AND l.longitude IS NOT NULL
                AND (
                    SELECT COUNT(*)
                    FROM listings AS other
                    WHERE other.listing_status = 'active'
                        AND other.item_name = l.item_name
                ) = 1
            ORDER BY
                distance_meters ASC,
                l.listing_id DESC
        ");

        $stmt->execute([
            'zip' => $zipCode
        ]);

        $expectedListings = $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );

        // Require at least two meetup listings.
        $this->assertGreaterThanOrEqual(
            2,
            count($expectedListings),
            'Test data needs at least two uniquely named ' .
            'meetup listings with coordinates.'
        );

        // Ensure the test includes different distances.
        $distinctDistances = array_unique(
            array_map(
                fn($listing) => round(
                    (float)$listing['distance_meters'],
                    1
                ),
                $expectedListings
            )
        );

        $this->assertGreaterThanOrEqual(
            2,
            count($distinctDistances),
            'Test data needs meetup listings at different distances.'
        );

        // Request Browse All with Near to Far sorting.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'fulfillment' => 'meetup',
                'zip' => $zipCode,
                'sort' => 'distance_near'
            ]
        );

        // Verify the page loads.
        $this->assertSame(200, $status);

        // Verify no search error occurred.
        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        // Verify Near to Far remains selected.
        $this->assertMatchesRegularExpression(
            '/<option\b(?=[^>]*value="distance_near")' .
            '(?=[^>]*\bselected\b)[^>]*>/s',
            $body
        );

        // Find each listing's position in the HTML.
        $displayedListings = [];

        foreach ($expectedListings as $listing) {

            $itemName = htmlspecialchars(
                $listing['item_name'],
                ENT_QUOTES,
                'UTF-8'
            );

            $position = strpos($body, $itemName);

            $this->assertNotFalse(
                $position,
                'Meetup listing not found: ' . $itemName
            );

            $displayedListings[] = [
                'listing_id' => $listing['listing_id'],
                'distance' => (float)$listing['distance_meters'],
                'position' => $position
            ];
        }

        // Arrange listings in their displayed HTML order.
        usort(
            $displayedListings,
            fn($a, $b) => $a['position'] <=> $b['position']
        );

        // Verify distances never decrease down the page.
        // Allow a very small tolerance for floating-point values.
        for ($i = 1; $i < count($displayedListings); $i++) {

            $previousDistance =
                $displayedListings[$i - 1]['distance'];

            $currentDistance =
                $displayedListings[$i]['distance'];

            $this->assertGreaterThanOrEqual(
                $previousDistance - 0.1,
                $currentDistance,
                'Listings are not sorted nearest first. ' .
                'Check listing ID: ' .
                $displayedListings[$i]['listing_id']
            );
        }
    }

    // =========================================
    // TC-24-07: INVALID SORTING OPTION
    // =========================================

    public function testInvalidSortFallsBackToNewest(): void
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

        // Get active listings in the default Newest order.
        // Use unique item names for HTML comparisons.
        $stmt = $pdo->query("
            SELECT
                l.listing_id,
                l.item_name,
                l.created_at
            FROM listings AS l
            WHERE l.listing_status = 'active'
                AND (
                    SELECT COUNT(*)
                    FROM listings AS other
                    WHERE other.listing_status = 'active'
                        AND other.item_name = l.item_name
                ) = 1
            ORDER BY
                l.created_at DESC,
                l.listing_id DESC
        ");

        $expectedListings = $stmt->fetchAll(
            \PDO::FETCH_ASSOC
        );

        // Require enough data to verify the sorting.
        $this->assertGreaterThanOrEqual(
            2,
            count($expectedListings),
            'Test data needs at least two uniquely named active listings.'
        );

        // Request the search page with an invalid sort value.
        [$status, $body] = $this->get(
            '/pages/search.php',
            [
                'browse' => 'all',
                'sort' => 'invalid_option'
            ]
        );

        // Verify the page loads.
        $this->assertSame(200, $status);

        // Verify no search error occurred.
        $this->assertStringNotContainsString(
            'Unable to retrieve search results',
            $body
        );

        // Verify the results count is still displayed.
        $this->assertMatchesRegularExpression(
            '/class="search-results__count"[^>]*>\s*\d+\s+results?\b/',
            $body
        );

        // Check the position of each listing in the HTML.
        $previousPosition = -1;

        foreach ($expectedListings as $listing) {

            $itemName = htmlspecialchars(
                $listing['item_name'],
                ENT_QUOTES,
                'UTF-8'
            );

            $position = strpos($body, $itemName);

            // Every expected listing should be present.
            $this->assertNotFalse(
                $position,
                'Listing not found: ' . $itemName
            );

            // Listings should follow the default Newest order.
            $this->assertGreaterThan(
                $previousPosition,
                $position,
                'Invalid sort did not fall back to Newest order. ' .
                'Check listing ID: ' . $listing['listing_id']
            );

            $previousPosition = $position;
        }

        // Verify the fallback sorting option is Newest.
        $this->assertMatchesRegularExpression(
            '/<option\b(?=[^>]*value="recent")' .
            '(?=[^>]*\bselected\b)[^>]*>/s',
            $body,
            'Newest should be selected after an invalid sort.'
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

        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 10
            ]
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            $this->fail(
                "Could not reach $url. Is the web container running?"
            );
        }

        preg_match(
            '{HTTP/\S+\s(\d{3})}',
            $http_response_header[0] ?? '',
            $matches
        );

        return [(int)($matches[1] ?? 0), $body];
    }
}