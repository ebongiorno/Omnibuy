<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SecCi03IntentionalFailureTest extends TestCase
{
    public function testIntentionalFailureIsDetectedByCI(): void
    {
        $this->assertSame(
            'expected',
            'actual',
            'SEC-CI-03: Intentional PHPUnit failure for security compliance testing.'
        );
    }
}