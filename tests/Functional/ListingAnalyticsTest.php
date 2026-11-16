<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Functional;

use OmniBuy\Tests\Support\HttpClient;
use PHPUnit\Framework\TestCase;

final class ListingAnalyticsTest extends TestCase
{
    private HttpClient $browser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->browser = HttpClient::fromEnvironment();
    }

    // =========================================
    // TC-11-06: ANALYTICS REQUIRES LOGIN
    // =========================================

    public function testAnalyticsPageRequiresLogin(): void
    {
        $response = $this->browser->get(
            '/pages/listing-analytics.php?id=1'
        );

        $this->assertSame(302, $response['status']);

        $this->assertStringStartsWith(
            '/pages/login.php?redirect=',
            $response['location'] ?? ''
        );
    }


}