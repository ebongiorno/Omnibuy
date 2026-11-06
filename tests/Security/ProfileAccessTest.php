<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Security;

use OmniBuy\Tests\Support\HttpClient;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * CC6.1 SEC-03 / SEC-07: account pages need a login, guests don't see
 * contact details, and one user can't change another user's profile.
 */
final class ProfileAccessTest extends SecurityTestCase
{
    /** @return array<string, array{string}> */
    public static function protectedPages(): array
    {
        return [
            'account' => ['/pages/account.php'],
            'edit profile' => ['/pages/edit-profile.php'],
            'privacy settings' => ['/pages/privacy-settings.php'],
            'change password' => ['/pages/change-password.php'],
            'delete account' => ['/pages/delete-account.php'],
            'orders' => ['/pages/orders.php'],
        ];
    }

    /** SEC-03 */
    #[DataProvider('protectedPages')]
    public function testGuestIsSentToLogin(string $path): void
    {
        $this->assertRedirectsToLogin($this->browser()->get($path));
    }

    /** SEC-03 */
    public function testGuestCannotSeeContactDetailsOnAPublicProfile(): void
    {
        $account = $this->createAccount();

        $page = $this->browser()->get('/pages/profile.php?id=' . $account['user_id']);

        self::assertSame(200, $page['status']);
        self::assertStringContainsString($account['username'], $page['body'], 'Fixture: the profile itself is public');
        self::assertStringNotContainsString($account['email'], $page['body']);
        self::assertStringNotContainsString($account['phone_number'], $page['body']);
    }

    /** SEC-07: the edit form only ever changes the logged-in user, whatever IDs are posted. */
    public function testCrossAccountProfileModificationIsDenied(): void
    {
        $attacker = $this->createAccount();
        $victim = $this->createAccount();
        $browser = $this->loggedInAs($attacker);
        $form = $browser->get('/pages/edit-profile.php')['body'];

        $response = $browser->post('/pages/edit-profile.php', array_merge($this->profileFormFields($form), [
            'user_id' => (string) $victim['user_id'],
            'id' => (string) $victim['user_id'],
            'first_name' => 'Changed',
        ]));

        self::assertSame('/pages/account.php?updated=profile', $response['location']);
        $after = $this->userRow($victim['user_id']);
        foreach (['first_name', 'last_name', 'username', 'email', 'phone_number'] as $column) {
            self::assertSame($victim[$column], $after[$column], "Victim's $column must not change");
        }
        self::assertSame('Changed', $this->userRow($attacker['user_id'])['first_name'], 'The change applies to the attacker only');
    }

    /** SEC-07: another user's data can't be read through the account pages. */
    public function testAccountPageShowsOnlyTheLoggedInUser(): void
    {
        $viewer = $this->createAccount();
        $other = $this->createAccount();
        $browser = $this->loggedInAs($viewer);

        foreach (['/pages/account.php', '/pages/edit-profile.php'] as $path) {
            $page = $browser->get($path . '?id=' . $other['user_id'] . '&user_id=' . $other['user_id']);
            self::assertStringContainsString($viewer['email'], $page['body'], $path);
            self::assertStringNotContainsString($other['email'], $page['body'], $path);
        }
    }

    /** @return array<string, string> the edit-profile form's current values */
    private function profileFormFields(string $html): array
    {
        $fields = ['csrf_token' => HttpClient::csrfToken($html)];

        foreach (['first_name', 'last_name', 'username', 'email', 'phone_number', 'city'] as $name) {
            preg_match('/name="' . $name . '"[^>]*value="([^"]*)"/s', $html, $match);
            $fields[$name] = html_entity_decode($match[1] ?? '');
        }
        preg_match('#<textarea[^>]*name="bio"[^>]*>(.*?)</textarea>#s', $html, $bio);
        $fields['bio'] = html_entity_decode($bio[1] ?? '');

        return $fields;
    }
}
