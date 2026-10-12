<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/auth.php';

/**
 * UC-01 registration validation and the redirect safety check in includes/auth.php.
 */
final class AuthValidationTest extends TestCase
{
    private const TODAY = '2026-10-12';

    public function testValidRegistrationHasNoErrors(): void
    {
        $this->assertSame([], $this->validate([]));
    }

    public function testAllFieldsAreRequired(): void
    {
        $errors = validateRegistration(normalizeRegistrationInput([]), new DateTimeImmutable(self::TODAY));

        $this->assertEqualsCanonicalizing(
            ['first_name', 'last_name', 'username', 'email', 'phone_number', 'birth_date', 'password'],
            array_keys($errors)
        );
    }

    public function testNormalizeTrimsFieldsAndLowercasesEmail(): void
    {
        $input = normalizeRegistrationInput([
            'first_name' => '  Kenny ',
            'email' => ' Kenny@Example.COM ',
            'password' => ' secret1 ',
        ]);

        $this->assertSame('Kenny', $input['first_name']);
        $this->assertSame('kenny@example.com', $input['email']);
        $this->assertSame(' secret1 ', $input['password'], 'Passwords must not be trimmed');
    }

    public function testNormalizeIgnoresNonStringValues(): void
    {
        // Negative: email[]=x in a crafted request must not cause a PHP error.
        $input = normalizeRegistrationInput(['email' => ['a@b.com'], 'password' => ['x']]);

        $this->assertSame('', $input['email']);
        $this->assertSame('', $input['password']);
    }

    #[DataProvider('invalidEmails')]
    public function testInvalidEmailIsRejected(string $email): void
    {
        $this->assertArrayHasKey('email', $this->validate(['email' => $email]));
    }

    public static function invalidEmails(): array
    {
        return [
            'no at sign' => ['kenny.example.com'],
            'no domain' => ['kenny@'],
            'spaces' => ['ken ny@example.com'],
            'too long' => [str_repeat('a', 250) . '@example.com'],
        ];
    }

    #[DataProvider('usernameBoundaries')]
    public function testUsernameLengthAndCharacters(string $username, bool $valid): void
    {
        $this->assertSame(!$valid, array_key_exists('username', $this->validate(['username' => $username])));
    }

    public static function usernameBoundaries(): array
    {
        return [
            '2 chars (too short)' => ['ab', false],
            '3 chars (minimum)' => ['abc', true],
            '30 chars (maximum)' => [str_repeat('a', 30), true],
            '31 chars (too long)' => [str_repeat('a', 31), false],
            'underscore and period' => ['ken_ly.99', true],
            'space' => ['ken ly', false],
            'html' => ['<script>', false],
        ];
    }

    #[DataProvider('passwordCases')]
    public function testPasswordRules(string $password, bool $valid): void
    {
        $errors = $this->validate(['password' => $password, 'password_confirm' => $password]);

        $this->assertSame(!$valid, array_key_exists('password', $errors));
    }

    public static function passwordCases(): array
    {
        return [
            '7 chars (too short)' => ['abcde12', false],
            '8 chars (minimum)' => ['abcdef12', true],
            'letters only' => ['abcdefgh', false],
            'numbers only' => ['12345678', false],
            '72 bytes (maximum)' => [str_repeat('a', 71) . '1', true],
            '73 bytes (too long)' => [str_repeat('a', 72) . '1', false],
        ];
    }

    public function testPasswordConfirmationMustMatch(): void
    {
        $errors = $this->validate(['password' => 'Sup3rSecret!', 'password_confirm' => 'Sup3rSecret?']);

        $this->assertArrayHasKey('password_confirm', $errors);
    }

    #[DataProvider('birthDates')]
    public function testBirthDateRules(string $birthDate, bool $valid): void
    {
        $this->assertSame(!$valid, array_key_exists('birth_date', $this->validate(['birth_date' => $birthDate])));
    }

    public static function birthDates(): array
    {
        return [
            'today (boundary)' => [self::TODAY, true],
            'tomorrow (future)' => ['2026-10-13', false],
            'impossible date' => ['2001-02-30', false],
            'wrong format' => ['10/12/2000', false],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function testPhoneNumberRules(string $phone, bool $valid): void
    {
        $this->assertSame(!$valid, array_key_exists('phone_number', $this->validate(['phone_number' => $phone])));
    }

    public static function phoneNumbers(): array
    {
        return [
            'US format' => ['(657) 555-0123', true],
            'international' => ['+1 657 555 0123', true],
            'letters' => ['call me', false],
            'too short' => ['12345', false],
        ];
    }

    public function testNameLongerThan100CharactersIsRejected(): void
    {
        $this->assertArrayHasKey('first_name', $this->validate(['first_name' => str_repeat('a', 101)]));
        $this->assertArrayNotHasKey('first_name', $this->validate(['first_name' => str_repeat('a', 100)]));
    }

    #[DataProvider('redirectTargets')]
    public function testSafeRedirectTargetOnlyAllowsLocalPaths(?string $target, string $expected): void
    {
        $this->assertSame($expected, safeRedirectTarget($target));
    }

    public static function redirectTargets(): array
    {
        return [
            'local page' => ['/pages/account.php', '/pages/account.php'],
            'local page with query' => ['/pages/search.php?q=lamp', '/pages/search.php?q=lamp'],
            'absolute URL' => ['https://evil.example/', '/'],
            'protocol-relative URL' => ['//evil.example/', '/'],
            'backslash trick' => ['/\\evil.example', '/'],
            'javascript URL' => ['javascript:alert(1)', '/'],
            'header injection' => ["/ok\r\nSet-Cookie: x=1", '/'],
            'empty' => ['', '/'],
            'missing' => [null, '/'],
        ];
    }

    private function validate(array $overrides): array
    {
        $input = array_merge([
            'first_name' => 'Kenny',
            'last_name' => 'Ly',
            'username' => 'kenny_ly',
            'email' => 'kenny@example.com',
            'phone_number' => '657-555-0123',
            'birth_date' => '2003-05-14',
            'password' => 'Sup3rSecret!',
            'password_confirm' => 'Sup3rSecret!',
        ], $overrides);

        return validateRegistration($input, new DateTimeImmutable(self::TODAY));
    }
}
