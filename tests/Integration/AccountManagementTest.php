<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

require_once __DIR__ . '/../../includes/account.php';

/**
 * UC-04 updateProfile(), UC-05 updatePrivacySettings() and changePassword()
 * against the real database.
 */
final class AccountManagementTest extends DatabaseTestCase
{
    private const PASSWORD = 'Sup3rSecret!'; // DatabaseTestCase::createUser() uses this password

    // ---------- UC-04 edit profile ----------

    public function testProfileUpdateSavesFields(): void
    {
        $account = $this->account($this->createUser('profile'));
        $input = $this->inputFrom($account, [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'username' => 'upd_' . $this->uniqueSuffix(),
            'phone_number' => '714-555-0100',
            'bio' => "Collector of\nvintage cameras",
            'city' => 'Fresno',
        ]);

        $result = updateProfile($this->pdo, $account, $input);

        $this->assertSame([], $result['errors']);
        $saved = $this->account($account['user_id']);
        foreach (['first_name', 'last_name', 'username', 'phone_number', 'bio', 'city'] as $field) {
            $this->assertSame($input[$field], $saved[$field], $field);
        }
        $this->assertSame($saved, $result['account']);
    }

    public function testKeepingOwnUsernameAndEmailIsAllowed(): void
    {
        $account = $this->account($this->createUser('profile'));

        $this->assertSame([], updateProfile($this->pdo, $account, $this->inputFrom($account))['errors']);
    }

    public function testEmptyBioIsStoredAsNull(): void
    {
        $account = $this->account($this->createUserWith(['bio' => 'old bio']));

        updateProfile($this->pdo, $account, $this->inputFrom($account, ['bio' => '']));

        $this->assertNull($this->account($account['user_id'])['bio']);
    }

    public function testEmptyCityIsStoredAsNull(): void
    {
        $account = $this->account($this->createUserWith(['city' => 'Fresno']));

        updateProfile($this->pdo, $account, $this->inputFrom($account, ['city' => '']));

        $this->assertNull($this->account($account['user_id'])['city']);
    }

    public function testUsernameTakenByAnotherUserIsRejected(): void
    {
        $other = $this->account($this->createUser('other'));
        $account = $this->account($this->createUser('profile'));

        $result = updateProfile($this->pdo, $account, $this->inputFrom($account, ['username' => $other['username']]));

        $this->assertSame('This username is taken.', $result['errors']['username']);
        $this->assertSame($account['username'], $this->account($account['user_id'])['username']);
    }

    public function testInvalidFieldsChangeNothing(): void
    {
        $account = $this->account($this->createUser('profile'));

        $result = updateProfile($this->pdo, $account, $this->inputFrom($account, [
            'first_name' => 'Changed',
            'phone_number' => 'not a phone',
        ]));

        $this->assertArrayHasKey('phone_number', $result['errors']);
        $this->assertSame($account, $this->account($account['user_id']));
    }

    public function testEmailChangeNeedsCurrentPassword(): void
    {
        // Security: someone at an unattended, logged-in computer can't take the account.
        $account = $this->account($this->createUser('profile'));
        $newEmail = 'new_' . $this->uniqueSuffix() . '@example.com';

        $missing = updateProfile($this->pdo, $account, $this->inputFrom($account, ['email' => $newEmail]));
        $wrong = updateProfile($this->pdo, $account, $this->inputFrom($account, [
            'email' => $newEmail,
            'current_password' => 'WrongPassw0rd',
        ]));

        $this->assertSame('Enter your current password to change your email.', $missing['errors']['current_password']);
        $this->assertSame('Your current password is incorrect.', $wrong['errors']['current_password']);
        $this->assertSame($account['email'], $this->account($account['user_id'])['email']);
    }

    public function testEmailChangeWithPasswordMarksEmailUnverified(): void
    {
        $userId = $this->createUserWith(['is_email_verified' => 1]);
        $account = $this->account($userId);
        $newEmail = 'new_' . $this->uniqueSuffix() . '@example.com';

        $result = updateProfile($this->pdo, $account, $this->inputFrom($account, [
            'email' => $newEmail,
            'current_password' => self::PASSWORD,
        ]));

        $this->assertSame([], $result['errors']);
        $this->assertSame($newEmail, $this->account($userId)['email']);
        $this->assertSame(0, (int) $this->pdo->query("SELECT is_email_verified FROM users WHERE user_id = $userId")->fetchColumn());
    }

    public function testOtherChangesKeepEmailVerified(): void
    {
        $userId = $this->createUserWith(['is_email_verified' => 1]);
        $account = $this->account($userId);

        updateProfile($this->pdo, $account, $this->inputFrom($account, ['first_name' => 'Renamed']));

        $this->assertSame(1, (int) $this->pdo->query("SELECT is_email_verified FROM users WHERE user_id = $userId")->fetchColumn());
    }

    public function testEmailTakenByAnotherUserIsRejected(): void
    {
        $other = $this->account($this->createUser('other'));
        $account = $this->account($this->createUser('profile'));

        $result = updateProfile($this->pdo, $account, $this->inputFrom($account, [
            'email' => $other['email'],
            'current_password' => self::PASSWORD,
        ]));

        $this->assertSame('An account with this email already exists.', $result['errors']['email']);
    }

    public function testRemovePhotoClearsIt(): void
    {
        $account = $this->account($this->createUserWith(['profile_image_url' => '/assets/images/seller.jpg']));

        updateProfile($this->pdo, $account, $this->inputFrom($account, ['remove_photo' => true]));

        $this->assertNull($this->account($account['user_id'])['profile_image_url']);
    }

    // ---------- UC-05 privacy ----------

    public function testHideAndShowLastName(): void
    {
        $userId = $this->createUser('privacy');

        updatePrivacySettings($this->pdo, $userId, true);
        $this->assertTrue($this->account($userId)['is_last_name_hidden']);

        updatePrivacySettings($this->pdo, $userId, false);
        $this->assertFalse($this->account($userId)['is_last_name_hidden']);
    }

    // ---------- Change password ----------

    public function testChangePasswordWithCorrectCurrentPassword(): void
    {
        $userId = $this->createUser('password');

        $this->assertSame([], changePassword($this->pdo, $userId, self::PASSWORD, 'N3wPassword', 'N3wPassword'));
        $this->assertTrue(verifyCurrentPassword($this->pdo, $userId, 'N3wPassword'));
        $this->assertFalse(verifyCurrentPassword($this->pdo, $userId, self::PASSWORD));
    }

    public function testChangePasswordRejectsWrongCurrentPassword(): void
    {
        $userId = $this->createUser('password');

        $errors = changePassword($this->pdo, $userId, 'WrongPassw0rd', 'N3wPassword', 'N3wPassword');

        $this->assertSame(['current_password' => 'Your current password is incorrect.'], $errors);
        $this->assertTrue(verifyCurrentPassword($this->pdo, $userId, self::PASSWORD));
    }

    public function testChangePasswordRejectsSamePassword(): void
    {
        $userId = $this->createUser('password');

        $errors = changePassword($this->pdo, $userId, self::PASSWORD, self::PASSWORD, self::PASSWORD);

        $this->assertSame('Choose a password different from your current one.', $errors['password']);
    }

    public function testChangePasswordAppliesPasswordRules(): void
    {
        $userId = $this->createUser('password');

        $this->assertArrayHasKey('password', changePassword($this->pdo, $userId, self::PASSWORD, 'short', 'short'));
        $this->assertArrayHasKey('password_confirm', changePassword($this->pdo, $userId, self::PASSWORD, 'N3wPassword', 'Other1234'));
    }

    public function testChangePasswordCancelsResetLinks(): void
    {
        // Security: an old reset email must not undo a password change.
        $userId = $this->createUser('password');
        $email = (string) $this->pdo->query("SELECT email FROM users WHERE user_id = $userId")->fetchColumn();
        $reset = createPasswordReset($this->pdo, $email);

        changePassword($this->pdo, $userId, self::PASSWORD, 'N3wPassword', 'N3wPassword');

        $this->assertNull(findPasswordReset($this->pdo, $reset['token']));
    }

    /**
     * A test user with extra users columns set (createUser() only takes the sign-up columns).
     */
    private function createUserWith(array $columns): int
    {
        $userId = $this->createUser('profile');
        $assignments = implode(', ', array_map(static fn (string $column): string => "$column = :$column", array_keys($columns)));

        $this->pdo->prepare("UPDATE users SET $assignments WHERE user_id = :id")->execute($columns + ['id' => $userId]);

        return $userId;
    }

    private function account(int $userId): array
    {
        return loadAccount($this->pdo, $userId);
    }

    private function inputFrom(array $account, array $overrides = []): array
    {
        return array_merge(profileInputFromAccount($account), $overrides);
    }
}
