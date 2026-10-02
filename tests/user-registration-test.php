<?php

declare(strict_types=1);

require __DIR__ . '/../db_connect.php';

/** @var PDO $pdo */

$failures = [];

function check(string $description, bool $condition, array &$failures): void
{
    if ($condition) {
        echo "  [PASS] $description\n";
    } else {
        echo "  [FAIL] $description\n";
        $failures[] = $description;
    }
}

echo "Running user registration tests...\n";

$suffix = bin2hex(random_bytes(4));
$username = "ci_user_$suffix";
$email = "ci_user_$suffix@example.com";
$plainPassword = 'Sup3rSecret!';
$passwordHash = password_hash($plainPassword, PASSWORD_BCRYPT);

$userId = null;

try {
    // 1. A new user can register with valid details.
    $insert = $pdo->prepare('
        INSERT INTO users (
            first_name, last_name, username, email, password_hash,
            phone_number, birth_date
        ) VALUES (
            :first_name, :last_name, :username, :email, :password_hash,
            :phone_number, :birth_date
        )
    ');

    $insert->execute([
        'first_name' => 'CI',
        'last_name' => 'Tester',
        'username' => $username,
        'email' => $email,
        'password_hash' => $passwordHash,
        'phone_number' => '555-0000',
        'birth_date' => '2000-01-01',
    ]);

    $userId = (int) $pdo->lastInsertId();

    check('registration inserts a new user row', $userId > 0, $failures);

    $stored = $pdo->prepare('SELECT * FROM users WHERE user_id = :id');
    $stored->execute(['id' => $userId]);
    $row = $stored->fetch();

    check(
        'stored password hash verifies against the original password',
        $row && password_verify($plainPassword, $row['password_hash']),
        $failures
    );
    check('stored email matches what was submitted', $row && $row['email'] === $email, $failures);
    check('new accounts default to active status', $row && $row['account_status'] === 'active', $failures);

    // 2. Registering a second account with the same email must be rejected.
    $duplicateEmailRejected = false;

    try {
        $pdo->prepare('
            INSERT INTO users (
                first_name, last_name, username, email, password_hash,
                phone_number, birth_date
            ) VALUES (
                "CI", "Duplicate", :username, :email, :password_hash, "555-0001", "2000-01-01"
            )
        ')->execute([
            'username' => "{$username}_dup",
            'email' => $email,
            'password_hash' => $passwordHash,
        ]);
    } catch (PDOException $e) {
        $duplicateEmailRejected = true;
    }

    check('registering with a duplicate email is rejected', $duplicateEmailRejected, $failures);

    // 3. Registering a second account with the same username must be rejected.
    $duplicateUsernameRejected = false;

    try {
        $pdo->prepare('
            INSERT INTO users (
                first_name, last_name, username, email, password_hash,
                phone_number, birth_date
            ) VALUES (
                "CI", "Duplicate", :username, :email, :password_hash, "555-0003", "2000-01-01"
            )
        ')->execute([
            'username' => $username,
            'email' => "{$username}_dup@example.com",
            'password_hash' => $passwordHash,
        ]);
    } catch (PDOException $e) {
        $duplicateUsernameRejected = true;
    }

    check('registering with a duplicate username is rejected', $duplicateUsernameRejected, $failures);

    // 4. A birth date in the future must be rejected.
    $futureBirthDateRejected = false;

    try {
        $pdo->prepare('
            INSERT INTO users (
                first_name, last_name, username, email, password_hash,
                phone_number, birth_date
            ) VALUES (
                "CI", "FutureDate", :username, :email, :password_hash, "555-0002", :birth_date
            )
        ')->execute([
            'username' => "{$username}_future",
            'email' => "{$username}_future@example.com",
            'password_hash' => $passwordHash,
            'birth_date' => (new DateTime('+1 year'))->format('Y-m-d'),
        ]);
    } catch (PDOException $e) {
        $futureBirthDateRejected = true;
    }

    check('registering with a future birth date is rejected', $futureBirthDateRejected, $failures);
} finally {
    if ($userId !== null) {
        $pdo->prepare('DELETE FROM users WHERE user_id = :id')->execute(['id' => $userId]);
    }
}

if (!empty($failures)) {
    echo "\n" . count($failures) . " check(s) failed.\n";
    exit(1);
}

echo "\nAll user registration checks passed.\n";
exit(0);
