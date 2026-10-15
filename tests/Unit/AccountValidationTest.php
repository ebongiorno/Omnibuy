<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/account.php';

/**
 * UC-03 / UC-04 / UC-05 checks that need no database: profile and photo
 * validation, display names, reset tokens and the reset email.
 */
final class AccountValidationTest extends TestCase
{
    // A real 1x1 PNG image.
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
    }

    // ---------- UC-04 profile fields ----------

    public function testValidProfileHasNoErrors(): void
    {
        $this->assertSame([], validateProfile($this->profileInput()));
    }

    public function testProfileUsesSignupRulesForSharedFields(): void
    {
        $errors = validateProfile($this->profileInput([
            'first_name' => '',
            'username' => 'a b',
            'email' => 'not-an-email',
            'phone_number' => 'call me',
        ]));

        $this->assertSame('First name is required.', $errors['first_name']);
        $this->assertArrayHasKey('username', $errors);
        $this->assertSame('Enter a valid email address.', $errors['email']);
        $this->assertSame('Enter a valid phone number.', $errors['phone_number']);
    }

    public function testBioMayBeEmpty(): void
    {
        $this->assertSame([], validateProfile($this->profileInput(['bio' => ''])));
    }

    public function testBioLengthBoundary(): void
    {
        // Multi-byte characters count as one character each.
        $this->assertSame([], validateProfile($this->profileInput(['bio' => str_repeat('é', PROFILE_BIO_MAX_LENGTH)])));
        $this->assertArrayHasKey('bio', validateProfile($this->profileInput(['bio' => str_repeat('é', PROFILE_BIO_MAX_LENGTH + 1)])));
    }

    public function testNormalizeProfileTrimsAndLowercasesEmail(): void
    {
        $input = normalizeProfileInput([
            'first_name' => '  Kenny ',
            'email' => ' Kenny@Example.COM ',
            'bio' => "Line one\r\nLine two ",
            'current_password' => ' secret1 ',
        ]);

        $this->assertSame('Kenny', $input['first_name']);
        $this->assertSame('kenny@example.com', $input['email']);
        $this->assertSame("Line one\nLine two", $input['bio']);
        $this->assertSame(' secret1 ', $input['current_password'], 'Passwords must not be trimmed');
        $this->assertFalse($input['remove_photo']);
    }

    public function testNormalizeProfileIgnoresNonStringValues(): void
    {
        $input = normalizeProfileInput(['username' => ['x'], 'bio' => ['y'], 'remove_photo' => ['1']]);

        $this->assertSame('', $input['username']);
        $this->assertSame('', $input['bio']);
        $this->assertFalse($input['remove_photo']);
    }

    // ---------- UC-04 profile photo ----------

    public function testNoPhotoChosenIsNotAnError(): void
    {
        $this->assertSame(['error' => null, 'extension' => null], validateProfileImageUpload(null));
        $this->assertSame(
            ['error' => null, 'extension' => null],
            validateProfileImageUpload(['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'size' => 0])
        );
    }

    public function testRealPngIsAccepted(): void
    {
        $file = $this->uploadedFile(base64_decode(self::PNG_1X1));

        $this->assertSame(['error' => null, 'extension' => 'png'], validateProfileImageUpload($file));
    }

    #[DataProvider('notImages')]
    public function testFilesThatAreNotImagesAreRejected(string $contents): void
    {
        // Security: the file's contents decide, not its name or claimed type.
        $file = $this->uploadedFile($contents);
        $file['name'] = 'avatar.png';
        $file['type'] = 'image/png';

        $this->assertSame('Photo must be a JPEG, PNG or WebP image.', validateProfileImageUpload($file)['error']);
    }

    public static function notImages(): array
    {
        return [
            'php script' => ['<?php system($_GET["c"]); ?>'],
            'html' => ['<html><script>alert(1)</script></html>'],
            'svg (can carry scripts)' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'fake png header' => ["\x89PNG\r\n\x1a\nnot really an image"],
        ];
    }

    public function testPhotoOverTwoMegabytesIsRejected(): void
    {
        $file = $this->uploadedFile(base64_decode(self::PNG_1X1) . str_repeat("\0", PROFILE_IMAGE_MAX_BYTES));

        $this->assertSame('Photo must be 2 MB or smaller.', validateProfileImageUpload($file)['error']);
    }

    public function testPhotoRejectedByPhpSizeLimitShowsSizeMessage(): void
    {
        $result = validateProfileImageUpload(['error' => UPLOAD_ERR_INI_SIZE, 'tmp_name' => '', 'size' => 0]);

        $this->assertSame('Photo must be 2 MB or smaller.', $result['error']);
    }

    public function testMultipleFilesInOneFieldAreRejected(): void
    {
        // Negative: photo[]=a&photo[]=b gives arrays instead of strings.
        $result = validateProfileImageUpload(['error' => [0, 0], 'tmp_name' => ['a', 'b'], 'size' => [1, 1]]);

        $this->assertSame('Upload one photo at a time.', $result['error']);
    }

    public function testDeleteProfileImageOnlyTouchesUploadedPhotos(): void
    {
        $outside = tempnam(sys_get_temp_dir(), 'keep');
        $this->tempFiles[] = $outside;

        // Security: a crafted URL must not delete files outside the avatars folder.
        deleteProfileImage('/uploads/avatars/../../../' . basename($outside));
        deleteProfileImage('/assets/icons/default-avatar.png');
        deleteProfileImage(null);

        $this->assertFileExists($outside);
    }

    // ---------- UC-05 display name ----------

    public function testDisplayNameShowsFullNameByDefault(): void
    {
        $this->assertSame('Kenny Ly', publicDisplayName('Kenny', 'Ly', false));
    }

    public function testDisplayNameHidesLastNameWhenChosen(): void
    {
        $this->assertSame('Kenny', publicDisplayName('Kenny', 'Ly', true));
    }

    // ---------- Passwords (UC-03 reset and password change) ----------

    public function testNewPasswordRules(): void
    {
        $this->assertSame([], validateNewPassword('N3wPassword', 'N3wPassword'));
        $this->assertArrayHasKey('password', validateNewPassword('short1', 'short1'));
        $this->assertArrayHasKey('password', validateNewPassword('nodigitshere', 'nodigitshere'));
        $this->assertSame(['password_confirm' => 'Passwords do not match.'], validateNewPassword('N3wPassword', 'N3wPassw0rd'));
    }

    public function testResetTokenFormat(): void
    {
        $this->assertTrue(isWellFormedResetToken(bin2hex(random_bytes(32))));
        $this->assertFalse(isWellFormedResetToken(''));
        $this->assertFalse(isWellFormedResetToken(str_repeat('g', 64)));
        $this->assertFalse(isWellFormedResetToken("' OR 1=1 -- "));
        $this->assertFalse(isWellFormedResetToken(strtoupper(bin2hex(random_bytes(32)))));
    }

    public function testOnlyTheTokenHashIsStored(): void
    {
        $token = bin2hex(random_bytes(32));

        $this->assertSame(hash('sha256', $token), passwordResetTokenHash($token));
        $this->assertNotSame($token, passwordResetTokenHash($token));
    }

    public function testResetLinkUsesConfiguredSiteNotRequestHost(): void
    {
        // Security: a forged Host header must not change where the emailed link points.
        $_SERVER['HTTP_HOST'] = 'evil.example';
        putenv('SITE_URL=https://omnibuy.test/');

        try {
            $this->assertSame('https://omnibuy.test/pages/reset-password.php?token=abc', passwordResetLink('abc'));
        } finally {
            putenv('SITE_URL');
            unset($_SERVER['HTTP_HOST']);
        }
    }

    // ---------- Email ----------

    public function testMailMessageUsesCrlfAndDotStuffing(): void
    {
        $message = buildMailMessage('from@omnibuy.test', 'to@example.com', 'Hello', "Line one\n.hidden\nLast");

        $this->assertStringContainsString("Subject: Hello\r\n", $message);
        $this->assertStringContainsString("\r\n\r\nLine one\r\n..hidden\r\nLast", $message);
        $this->assertStringNotContainsString("\n.hidden", $message, 'A body line starting with "." must be escaped');
    }

    public function testSendMailRejectsHeaderInjection(): void
    {
        // Security: a line break in the address or subject could add recipients or headers.
        $previousLog = ini_set('error_log', '/dev/null');

        try {
            $this->assertFalse(sendMail("victim@example.com\r\nBcc: everyone@example.com", 'Hi', 'Body'));
            $this->assertFalse(sendMail('victim@example.com', "Hi\r\nBcc: everyone@example.com", 'Body'));
        } finally {
            ini_set('error_log', (string) $previousLog);
        }
    }

    private function profileInput(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Kenny',
            'last_name' => 'Ly',
            'username' => 'kenny_ly',
            'email' => 'kenny@example.com',
            'phone_number' => '657-555-0123',
            'bio' => 'I sell vintage keyboards.',
            'remove_photo' => false,
            'current_password' => '',
        ], $overrides);
    }

    /**
     * A $_FILES-style entry for a temporary file with these contents.
     */
    private function uploadedFile(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return ['name' => 'photo', 'type' => '', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($contents)];
    }
}
