<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that talk to the real MySQL database.
 *
 * Each test runs inside a transaction that is rolled back afterwards,
 * so tests never leave rows behind and can't affect each other.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        // Connect directly instead of including db_connect.php: that file calls
        // die() on failure, which exits with status 0 and would hide the error.
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'db',
            (int) (getenv('DB_PORT') ?: 3306),
            getenv('DB_NAME') ?: 'omnibuy'
        );

        $this->pdo = new PDO($dsn, getenv('DB_USER') ?: '', getenv('DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        parent::tearDown();
    }

    /**
     * Asserts that running the given statement is rejected by the database.
     */
    protected function assertRejectedByDatabase(string $sql, array $params, string $message): void
    {
        try {
            $this->pdo->prepare($sql)->execute($params);
        } catch (\PDOException) {
            $this->addToAssertionCount(1);
            return;
        }

        $this->fail($message);
    }

    protected function uniqueSuffix(): string
    {
        return bin2hex(random_bytes(4));
    }

    protected function createUser(string $label, array $overrides = []): int
    {
        $suffix = $this->uniqueSuffix();

        $this->pdo->prepare('
            INSERT INTO users (
                first_name, last_name, username, email, password_hash,
                phone_number, birth_date
            ) VALUES (
                :first_name, :last_name, :username, :email, :password_hash,
                :phone_number, :birth_date
            )
        ')->execute(array_merge([
            'first_name' => 'Test',
            'last_name' => $label,
            'username' => "test_{$label}_$suffix",
            'email' => "test_{$label}_$suffix@example.com",
            'password_hash' => password_hash('Sup3rSecret!', PASSWORD_BCRYPT),
            'phone_number' => '555-0000',
            'birth_date' => '2000-01-01',
        ], $overrides));

        return (int) $this->pdo->lastInsertId();
    }
}
