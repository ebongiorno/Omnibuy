<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Security;

/**
 * CC6.1 SEC-04 / SEC-05: logging out ends the session on the server,
 * logging in issues a fresh session ID, and the session cookie can't be
 * read by JavaScript or sent on cross-site requests.
 */
final class SessionSecurityTest extends SecurityTestCase
{
    private const SESSION_COOKIE = 'PHPSESSID';

    /** SEC-04 */
    public function testLogoutRevokesAuthenticatedAccess(): void
    {
        $browser = $this->loggedInAs($this->createAccount());
        self::assertSame(200, $browser->get('/pages/account.php')['status'], 'Fixture: logged in before logout');
        $oldSessionId = (string) $browser->cookie(self::SESSION_COOKIE);

        $logout = $browser->post('/pages/logout.php', []);

        self::assertSame('/pages/login.php?logged_out=1', $logout['location']);
        $this->assertRedirectsToLogin($browser->get('/pages/account.php'));

        // The old ID must be dead on the server, not just forgotten by this browser.
        $replay = $this->browser();
        $replay->setCookie(self::SESSION_COOKIE, $oldSessionId);
        $this->assertRedirectsToLogin($replay->get('/pages/account.php'), 'A logged-out session ID must not work again');
    }

    /** SEC-04: a link or image on another site can't log the user out. */
    public function testLogoutIgnoresGetRequests(): void
    {
        $browser = $this->loggedInAs($this->createAccount());

        $browser->get('/pages/logout.php');

        self::assertSame(200, $browser->get('/pages/account.php')['status']);
    }

    /** SEC-04: prevents session fixation. */
    public function testLoginIssuesANewSessionId(): void
    {
        $account = $this->createAccount();
        $browser = $this->browser();
        $browser->get('/pages/login.php');
        $before = (string) $browser->cookie(self::SESSION_COOKIE);
        self::assertNotSame('', $before, 'Fixture: the login page should start a session');

        $this->logIn($browser, $account['email']);

        self::assertNotSame($before, $browser->cookie(self::SESSION_COOKIE));
        $planted = $this->browser();
        $planted->setCookie(self::SESSION_COOKIE, $before);
        $this->assertRedirectsToLogin($planted->get('/pages/account.php'), 'The pre-login session ID must not be logged in');
    }

    /**
     * SEC-05. The Secure flag is set only when the request arrives over
     * HTTPS (startSession() in includes/auth.php); this local test server is
     * plain HTTP, so that part is checked by code review.
     */
    public function testSessionCookieFlags(): void
    {
        $account = $this->createAccount();
        $browser = $this->browser();

        $this->logIn($browser, $account['email']);

        $cookie = $browser->lastSetCookies[self::SESSION_COOKIE] ?? '';
        self::assertNotSame('', $cookie, 'Login should send a new session cookie');
        self::assertMatchesRegularExpression('/;\s*HttpOnly/i', $cookie);
        self::assertMatchesRegularExpression('/;\s*SameSite=(Lax|Strict)/i', $cookie);
        self::assertDoesNotMatchRegularExpression('/;\s*expires=/i', $cookie, 'Session cookie should end with the browser session');
    }
}
