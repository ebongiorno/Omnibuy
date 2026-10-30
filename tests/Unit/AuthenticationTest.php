<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Unit;

use OmniBuy\Tests\Security\SecurityTestCase;
declare(strict_types=1);


final class AuthenticationTest extends SecurityTestCase
{
    public function testInvalidCredentialsAreRejected(): void
    {
        $this->requireConfig('OMNIBUY_TEST_USER_EMAIL', 'OMNIBUY_LOGIN_PATH');
        $response = $this->request('invalid', 'POST', 'OMNIBUY_LOGIN_PATH', [
            $this->config('OMNIBUY_LOGIN_EMAIL_FIELD') ?: 'email' => $this->config('OMNIBUY_TEST_USER_EMAIL'),
            $this->config('OMNIBUY_LOGIN_PASSWORD_FIELD') ?: 'password' => 'definitely-not-a-valid-password-987654',
        ]);
        $this->requireConfig('OMNIBUY_PROFILE_PATH');
        $profile = $this->request('invalid', 'GET', 'OMNIBUY_PROFILE_PATH');
        $this->assertDenied($profile);
        self::assertNotSame(500, $response['status']);
    }

    public function testUnauthenticatedAccountAccessIsDenied(): void
    {
        $this->requireConfig('OMNIBUY_PROFILE_PATH');
        $this->assertDenied($this->request('guest', 'GET', 'OMNIBUY_PROFILE_PATH'));
    }

    public function testPasswordHashingIsVerifiedByCodeReview(): void
    {
        $this->markTestIncomplete('Manual evidence required: inspect password_hash/password_verify usage and redacted database sample; do not query production credentials.');
    }
}
