<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/listing-validation.php';

/**
 * The seller's return-policy slider value (0-30 days).
 */
final class ReturnPolicyTest extends TestCase
{
    #[DataProvider('validDays')]
    public function testWholeDaysFromZeroToThirtyAreAccepted(mixed $input, int $expected): void
    {
        self::assertSame($expected, parseReturnPolicyDays($input));
    }

    public static function validDays(): array
    {
        return [
            'no returns' => ['0', 0],
            'one day' => ['1', 1],
            'maximum' => ['30', 30],
            'padded' => [' 14 ', 14],
            'integer' => [7, 7],
        ];
    }

    #[DataProvider('invalidDays')]
    public function testAnythingElseIsRejected(mixed $input): void
    {
        self::assertNull(parseReturnPolicyDays($input));
    }

    public static function invalidDays(): array
    {
        return [
            'over the maximum' => ['31'],
            'negative' => ['-1'],
            'fraction' => ['1.5'],
            'text' => ['thirty'],
            'empty' => [''],
            'array' => [['5']],
            'null' => [null],
        ];
    }

    public function testPolicyLabels(): void
    {
        self::assertSame('No returns', formatReturnPolicy(0));
        self::assertSame('1-day returns', formatReturnPolicy(1));
        self::assertSame('30-day returns', formatReturnPolicy(30));
    }
}
