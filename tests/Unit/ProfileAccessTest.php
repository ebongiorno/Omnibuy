<?php
declare(strict_types=1);
namespace OmniBuy\Tests\Security;

final class ProfileAccessTest extends SecurityTestCase
{
    public function testGuestCannotViewProtectedProfile(): void
    {
        $this->requireConfig('OMNIBUY_PROFILE_PATH');
        $this->assertDenied($this->request('guest', 'GET', 'OMNIBUY_PROFILE_PATH'));
    }

    public function testCrossAccountProfileModificationIsDenied(): void
    {
        $this->markTestIncomplete('Map profile update route, owner identifier field, and CSRF token workflow. Use two disposable accounts; verify A cannot change B and B data remains unchanged.');
    }
}
