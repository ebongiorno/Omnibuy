<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Security;

use OmniBuy\Tests\Support\HttpClient;

/**
 * CC6.1 SEC-08: privacy settings are enforced by the server when another
 * user views the profile, and only the owner can change them.
 */
final class PrivacySettingsTest extends SecurityTestCase
{
    private const SETTINGS = ['last_name', 'city', 'email', 'phone', 'bio'];
    private const HIDDEN_COLUMNS = ['is_last_name_hidden', 'is_city_hidden', 'is_email_hidden', 'is_phone_hidden', 'is_bio_hidden'];

    /** SEC-08 */
    public function testPrivacySettingsRequireAuthentication(): void
    {
        $browser = $this->browser();

        $this->assertRedirectsToLogin($browser->get('/pages/privacy-settings.php'));
        $this->assertRedirectsToLogin($browser->post('/pages/privacy-settings.php', ['show_email' => '1']));
    }

    /** SEC-08: hidden details are left out of the HTML, not just hidden on screen. */
    public function testHiddenDetailsAreNotSentToOtherUsers(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $owner = $this->createAccount([
            'last_name' => "Surname$suffix",
            'city' => "Town$suffix",
            'bio' => "Bio text $suffix",
        ]);
        $viewer = $this->loggedInAs($this->createAccount());
        $visible = [$owner['last_name'], $owner['city'], $owner['email'], $owner['phone_number'], $owner['bio']];
        $profilePath = '/pages/profile.php?id=' . $owner['user_id'];

        $before = $viewer->get($profilePath)['body'];
        foreach ($visible as $value) {
            self::assertStringContainsString($value, $before, 'Fixture: shown while the settings are on');
        }

        $saved = $this->savePrivacy($this->loggedInAs($owner), []);
        self::assertSame('/pages/account.php?updated=privacy', $saved['location']);

        $after = $viewer->get($profilePath)['body'];
        foreach ($visible as $value) {
            self::assertStringNotContainsString($value, $after);
        }
    }

    /** SEC-08: posting another user's ID changes the poster's own settings only. */
    public function testCannotChangeAnotherUsersPrivacySettings(): void
    {
        $attacker = $this->createAccount();
        $victim = $this->createAccount();

        $saved = $this->savePrivacy($this->loggedInAs($attacker), [], [
            'user_id' => (string) $victim['user_id'],
            'id' => (string) $victim['user_id'],
        ]);

        self::assertSame('/pages/account.php?updated=privacy', $saved['location']);
        $victimRow = $this->userRow($victim['user_id']);
        $attackerRow = $this->userRow($attacker['user_id']);
        foreach (self::HIDDEN_COLUMNS as $column) {
            self::assertSame(0, (int) $victimRow[$column], "Victim's $column must not change");
            self::assertSame(1, (int) $attackerRow[$column], "Attacker's own $column should be saved");
        }
    }

    /**
     * Submits the privacy form with only $show switched on.
     *
     * @param list<string> $show
     */
    private function savePrivacy(HttpClient $browser, array $show, array $extra = []): array
    {
        $page = $browser->get('/pages/privacy-settings.php');
        $fields = ['csrf_token' => HttpClient::csrfToken($page['body'])] + $extra;
        foreach (array_intersect(self::SETTINGS, $show) as $setting) {
            $fields["show_$setting"] = '1';
        }

        return $browser->post('/pages/privacy-settings.php', $fields);
    }
}
