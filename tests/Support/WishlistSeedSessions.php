<?php
/**
 * Starts app-authenticated sessions in separate PHP CLI processes.
 * They share the web container's configured session handler with Apache.
 */
declare(strict_types=1);

final class WishlistSeedSessions
{
    /** @var array<string, array{cookie:string, session_id:string}> */
    private static array $created = [];
    private static bool $cleanupRegistered = false;

    public static function cookie(string $actor, int $userId): string
    {
        if (isset(self::$created[$actor])) {
            return self::$created[$actor]['cookie'];
        }
        if (getenv('OMNIBUY_WISHLIST_SEED_SESSIONS') !== '1') {
            throw new RuntimeException('Explicitly enable OMNIBUY_WISHLIST_SEED_SESSIONS=1.');
        }
        $record = self::run('create', (string) $userId);
        if (!isset($record['cookie'], $record['session_id']) ||
            !is_string($record['cookie']) || !is_string($record['session_id'])) {
            throw new RuntimeException('Session creator returned an invalid response.');
        }
        self::$created[$actor] = [
            'cookie' => $record['cookie'],
            'session_id' => $record['session_id'],
        ];
        if (!self::$cleanupRegistered) {
            self::$cleanupRegistered = true;
            register_shutdown_function([self::class, 'cleanup']);
        }
        return $record['cookie'];
    }

    public static function cleanup(): void
    {
        foreach (self::$created as $record) {
            try {
                self::run('destroy', $record['session_id']);
            } catch (Throwable $error) {
                // Shutdown-time cleanup errors cannot be PHPUnit assertions.
                error_log('Wishlist temporary session cleanup: ' . $error->getMessage());
            }
        }
        self::$created = [];
    }

    /** @return array<string,mixed> */
    private static function run(string $operation, string $value): array
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('PHP proc_open is required for isolated wishlist sessions.');
        }
        $script = __DIR__ . '/wishlist-seed-session.php';
        $command = [PHP_BINARY, $script, $operation, $value];
        $pipes = [];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2)
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to launch PHP session fixture.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(trim((string) $stderr) ?: 'Session fixture failed.');
        }
        $decoded = json_decode((string) $stdout, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Session fixture returned non-JSON output.');
        }
        return $decoded;
    }
}
