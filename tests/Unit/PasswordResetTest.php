<?php
declare(strict_types=1);
namespace OmniBuy\Tests\Security;

final class PasswordResetTest extends SecurityTestCase
{
    public function testResetTokenExpiration(): void
    {
        $this->markTestIncomplete('Requires a dedicated reset-token test fixture and endpoint mapping. Verify expired tokens are rejected without changing password.');
    }

    public function testResetTokenCannotBeReused(): void
    {
        $this->markTestIncomplete('Requires controlled disposable test account and reset token. Redeem once, then confirm replay is rejected.');
    }

    public function testResetDoesNotExposeAccountExistence(): void
    {
        $this->markTestIncomplete('Review password recovery response for registered vs unregistered emails and verify consistent non-disclosing messaging.');
    }
}
