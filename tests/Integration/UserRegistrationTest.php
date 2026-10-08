<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use OmniBuy\Tests\Support\DatabaseTestCase;

/**
 * UC-01 Registration: checks the account rules the users table enforces.
 */
final class UserRegistrationTest extends DatabaseTestCase
{
    private const INSERT_USER = '
        INSERT INTO users (
            first_name, last_name, username, email, password_hash,
            phone_number, birth_date
        ) VALUES (
            "Test", "Duplicate", :username, :email, "TEST_HASH", "555-0001", :birth_date
        )
    ';

    public function testNewUserCanRegisterWithValidDetails(): void
    {
        $userId = $this->createUser('valid');

        $this->assertGreaterThan(0, $userId);
    }

    public function testStoredPasswordHashVerifiesAgainstOriginalPassword(): void
    {
        $userId = $this->createUser('hash');

        $row = $this->fetchUser($userId);

        $this->assertNotSame('Sup3rSecret!', $row['password_hash'], 'Password must not be stored in plain text');
        $this->assertTrue(password_verify('Sup3rSecret!', $row['password_hash']));
        $this->assertFalse(password_verify('WrongPassword!', $row['password_hash']));
    }

    public function testStoredEmailMatchesSubmittedEmail(): void
    {
        $email = 'test_email_' . $this->uniqueSuffix() . '@example.com';
        $userId = $this->createUser('email', ['email' => $email]);

        $this->assertSame($email, $this->fetchUser($userId)['email']);
    }

    public function testNewAccountsDefaultToActiveStatus(): void
    {
        $userId = $this->createUser('status');

        $this->assertSame('active', $this->fetchUser($userId)['account_status']);
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $email = 'test_dup_' . $this->uniqueSuffix() . '@example.com';
        $this->createUser('original', ['email' => $email]);

        $this->assertRejectedByDatabase(self::INSERT_USER, [
            'username' => 'test_dup_' . $this->uniqueSuffix(),
            'email' => $email,
            'birth_date' => '2000-01-01',
        ], 'Registering with a duplicate email should be rejected');
    }

    public function testDuplicateUsernameIsRejected(): void
    {
        $username = 'test_dup_' . $this->uniqueSuffix();
        $this->createUser('original', ['username' => $username]);

        $this->assertRejectedByDatabase(self::INSERT_USER, [
            'username' => $username,
            'email' => 'test_dup_' . $this->uniqueSuffix() . '@example.com',
            'birth_date' => '2000-01-01',
        ], 'Registering with a duplicate username should be rejected');
    }

    public function testFutureBirthDateIsRejected(): void
    {
        $this->assertRejectedByDatabase(self::INSERT_USER, [
            'username' => 'test_future_' . $this->uniqueSuffix(),
            'email' => 'test_future_' . $this->uniqueSuffix() . '@example.com',
            'birth_date' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d'),
        ], 'Registering with a future birth date should be rejected');
    }

    public function testBirthDateOfTodayIsAccepted(): void
    {
        // Boundary: the trigger rejects dates after today, so today itself is allowed.
        $userId = $this->createUser('today', ['birth_date' => date('Y-m-d')]);

        $this->assertGreaterThan(0, $userId);
    }

    public function testExistingUserCannotBeUpdatedToFutureBirthDate(): void
    {
        $userId = $this->createUser('update');

        $this->assertRejectedByDatabase(
            'UPDATE users SET birth_date = :birth_date WHERE user_id = :id',
            ['birth_date' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d'), 'id' => $userId],
            'Updating a user to a future birth date should be rejected'
        );
    }

    private function fetchUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);

        $row = $stmt->fetch();
        $this->assertIsArray($row, "User $userId should exist");

        return $row;
    }
}
