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

    
}