<?php
declare(strict_types=1);
namespace OmniBuy\Tests\Security;

final class SessionSecurityTest extends SecurityTestCase
{
    public function testLogoutRevokesAuthenticatedAccess(): void
    {
        $this->requireConfig('OMNIBUY_TEST_USER_EMAIL', 'OMNIBUY_TEST_USER_PASSWORD', 'OMNIBUY_PROFILE_PATH', 'OMNIBUY_LOGOUT_PATH');
        $this->login('user', 'OMNIBUY_TEST_USER_EMAIL', 'OMNIBUY_TEST_USER_PASSWORD');
        $before = $this->request('user', 'GET', 'OMNIBUY_PROFILE_PATH');
        self::assertSame(200, $before['status'], 'Fixture must be able to access profile before logout');
        $logout = $this->request('user', 'GET', 'OMNIBUY_LOGOUT_PATH');
        self::assertContains($logout['status'], [200, 302, 303]);
        $this->assertDenied($this->request('user', 'GET', 'OMNIBUY_PROFILE_PATH'));
    }

    public function testSessionCookieFlags(): void
    {
        $this->requireConfig('OMNIBUY_TEST_USER_EMAIL', 'OMNIBUY_TEST_USER_PASSWORD');
        // Capturing cookie flags may require reviewing headers on the login response directly.
        $this->markTestIncomplete('Capture Set-Cookie during login; verify HttpOnly, Secure on HTTPS, and SameSite according to deployment policy.');
    }
}
