<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Security;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SOC 2 CC6.6 / MD-SEC-01, 02, 03, 07.
 *
 * Read-only HTTP regression tests against the running OmniBuy web container.
 * Requires the normal Docker Compose stack and PHP cURL and DOM extensions.
 * Uses APP_URL from phpunit.xml; no isolated XSS database or special accounts.
 */
final class MarketplaceMalformedInputTest extends TestCase
{
    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function malformedRequests(): array
    {
        return [
            'MD-SEC-01 homepage array sort' => [
                '/', ['sort' => ['recent']], 'Recommended for You',
            ],
            'MD-SEC-02 search array keyword' => [
                '/pages/search.php', ['q' => ['test']], 'Start searching',
            ],
            'MD-SEC-03 search array sort' => [
                '/pages/search.php', ['sort' => ['recent']], 'Start searching',
            ],
            'MD-SEC-07 nested condition' => [
                '/pages/search.php',
                ['browse' => 'all', 'condition' => [['new']]],
                'All Listings',
            ],
            'MD-SEC-07 mixed valid and nested conditions' => [
                '/pages/search.php',
                ['browse' => 'all', 'condition' => ['new', ['used']]],
                'All Listings',
            ],
        ];
    }

    /** @param array<string, mixed> $parameters */
    #[DataProvider('malformedRequests')]
    public function testMalformedInputDoesNotCrashOrDiscloseDiagnostics(
        string $path,
        array $parameters,
        string $expectedText
    ): void {
        $response = $this->get($path, $parameters);
        self::assertSame(200, $response['status'], 'Expected controlled page response.');
        self::assertStringContainsString($expectedText, $response['body']);
        $this->assertNoPhpDiagnostics($response['body']);
    }

    public function testHomepageArraySortFallsBackToRecent(): void
    {
        $xpath = $this->xpath($this->get('/', ['sort' => ['recent']])['body']);
        self::assertSame(
            'recent',
            $this->selectedOption($xpath, 'feed-sort'),
            'Malformed homepage sort must select the default recent option.'
        );
    }

    public function testSearchArraySortFallsBackToRecent(): void
    {
        $xpath = $this->xpath($this->get('/pages/search.php', [
            'browse' => 'all', 'sort' => ['recent'],
        ])['body']);
        self::assertSame('recent', $this->selectedOption($xpath, 'results-sort'));
    }

    public function testValidHomepageSortRemainsAvailable(): void
    {
        $response = $this->get('/', ['sort' => 'price_low']);
        self::assertSame(200, $response['status']);
        self::assertSame('price_low', $this->selectedOption($this->xpath($response['body']), 'feed-sort'));
        $this->assertNoPhpDiagnostics($response['body']);
    }

    public function testValidSearchSortRemainsAvailable(): void
    {
        $response = $this->get('/pages/search.php', [
            'browse' => 'all', 'sort' => 'price_high',
        ]);
        self::assertSame(200, $response['status']);
        self::assertSame('price_high', $this->selectedOption($this->xpath($response['body']), 'results-sort'));
        $this->assertNoPhpDiagnostics($response['body']);
    }

    public function testValidSearchKeywordIsPreserved(): void
    {
        $response = $this->get('/pages/search.php', ['q' => 'OmniBuyRegressionKeyword']);
        self::assertSame(200, $response['status']);
        self::assertStringContainsString('Results for', $response['body']);
        self::assertStringContainsString('OmniBuyRegressionKeyword', $response['body']);
        $this->assertNoPhpDiagnostics($response['body']);
    }

    public function testMixedConditionsRetainOnlyAllowedStringValue(): void
    {
        $response = $this->get('/pages/search.php', [
            'browse' => 'all', 'condition' => ['new', ['used']],
        ]);
        self::assertSame(200, $response['status']);
        $xpath = $this->xpath($response['body']);
        self::assertSame(
            1,
            (int) $xpath->evaluate('count(//input[@name="condition[]" and @value="new" and @checked])'),
            'Valid new condition should remain selected.'
        );
        self::assertSame(
            1,
            (int) $xpath->evaluate('count(//input[@type="hidden" and @name="condition[]" and @value="new"])'),
            'Valid new condition should be preserved by the sorting form.'
        );
        $this->assertNoPhpDiagnostics($response['body']);
    }

    /** @param array<string, mixed> $parameters
     *  @return array{status:int, body:string}
     */
    private function get(string $path, array $parameters = []): array
    {
        self::assertTrue(extension_loaded('curl'), 'PHP cURL extension is required.');
        $baseUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost'), '/');
        $url = $baseUrl . $path;
        if ($parameters !== []) {
            $url .= '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        }
        $handle = curl_init($url);
        self::assertNotFalse($handle, 'Could not initialize HTTP request.');
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        self::assertNotFalse($body, "HTTP request failed: {$error}");
        return ['status' => $status, 'body' => (string) $body];
    }

    private function assertNoPhpDiagnostics(string $html): void
    {
        foreach ([
            'Fatal error', 'Uncaught TypeError', 'Uncaught Error',
            'Stack trace:', 'Array to string conversion',
            'Warning:', 'Notice:', 'SQLSTATE', '/var/www/html/',
        ] as $diagnostic) {
            self::assertStringNotContainsString(
                $diagnostic,
                $html,
                "Unexpected diagnostic disclosure: {$diagnostic}"
            );
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument();
            self::assertTrue($document->loadHTML('<?xml encoding="UTF-8"?>' . $html, LIBXML_NONET));
            return new DOMXPath($document);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function selectedOption(DOMXPath $xpath, string $selectId): string
    {
        $nodes = $xpath->query('//select[@id="' . $selectId . '"]/option[@selected]');
        self::assertNotFalse($nodes);
        self::assertCount(1, $nodes, "Expected one selected option in {$selectId}.");
        $node = $nodes->item(0);
        self::assertInstanceOf(\DOMElement::class, $node);
        return $node->getAttribute('value');
    }
}
