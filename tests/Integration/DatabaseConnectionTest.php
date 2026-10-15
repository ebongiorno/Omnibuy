<?php

declare(strict_types=1);

namespace OmniBuy\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Checks the connection the app gets from db_connect.php.
 */
final class DatabaseConnectionTest extends TestCase
{
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        require __DIR__ . '/../../db_connect.php';

        /** @var PDO $pdo */
        self::$pdo = $pdo;
    }

    public function testConnectsUsingTheComposeServiceName(): void
    {
        $this->assertSame('db', getenv('DB_HOST'), 'PHP should reach MySQL by service name, not localhost');
    }

    public function testConnectionCanRunQueries(): void
    {
        $this->assertSame(1, (int) self::$pdo->query('SELECT 1')->fetchColumn());
    }

    public function testConnectsAsAppUserNotRoot(): void
    {
        $user = self::$pdo->query('SELECT SUBSTRING_INDEX(CURRENT_USER(), "@", 1)')->fetchColumn();

        $this->assertSame(getenv('DB_USER'), $user);
        $this->assertNotSame('root', $user);
    }

    public function testUsesUtf8mb4(): void
    {
        $this->assertSame('utf8mb4', self::$pdo->query('SELECT @@character_set_connection')->fetchColumn());
    }

    public function testThrowsExceptionsOnSqlErrors(): void
    {
        $this->assertSame(PDO::ERRMODE_EXCEPTION, self::$pdo->getAttribute(PDO::ATTR_ERRMODE));

        $this->expectException(\PDOException::class);
        self::$pdo->query('SELECT * FROM table_that_does_not_exist');
    }

    public function testFetchesRowsAsAssociativeArrays(): void
    {
        $row = self::$pdo->query('SELECT 1 AS one')->fetch();

        $this->assertSame(['one' => 1], $row);
    }

    public function testUsesNativePreparedStatements(): void
    {
        // Real server-side prepares, so parameters can never be spliced into SQL.
        $this->assertFalse((bool) self::$pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
    }

    public function testConnectedToTheConfiguredDatabase(): void
    {
        $this->assertSame(getenv('DB_NAME'), self::$pdo->query('SELECT DATABASE()')->fetchColumn());
    }
}
