<?php
declare(strict_types=1);
namespace OmniBuy\Tests\Security;

final class PrivacySettingsTest extends SecurityTestCase
{
    public function testPrivacySettingsRequireAuthentication(): void
    {
        $this->requireConfig('OMNIBUY_PRIVACY_PATH');
        $this->assertDenied($this->request('guest', 'GET', 'OMNIBUY_PRIVACY_PATH'));
    }

    public function testPrivacyPreferenceIsEnforcedServerSide(): void
    {
        $this->markTestIncomplete('Map privacy preference field, update route, and read endpoint; verify visibility from another disposable account before/after preference change.');
    }
}
