<?php
/**
 * Local PHPUnit-only fixture. Never expose under the public/ document root.
 * Creates/deletes real PHP sessions using the application's startSession().
 * Usage (CLI only): php wishlist-seed-session.php create <seed-user-id>
 *                   php wishlist-seed-session.php destroy <session-id>
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('OMNIBUY_WISHLIST_SEED_SESSIONS') !== '1') {
    fwrite(STDERR, "Wishlist seed session fixture requires CLI and explicit opt-in.\n");
    exit(2);
}

$action = $argv[1] ?? '';
$value = $argv[2] ?? '';
if (!in_array($action, ['create', 'destroy'], true)) {
    fwrite(STDERR, "Usage: create <user-id> | destroy <session-id>\n");
    exit(2);
}

try {
    // Docker runs `docker compose exec web ...` as root by default, while
    // Apache serves requests as www-data. If we write PHP sessions as root,
    // Apache cannot open those mode-0600 session files (Permission denied).
    // Drop privileges ONLY in this short-lived, explicitly opt-in CLI child.
    // The PHPUnit parent process and all application files are untouched.
    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        $webUser = getenv('OMNIBUY_WISHLIST_WEB_USER') ?: 'www-data';
        if (!function_exists('posix_getpwnam') ||
            !function_exists('posix_setgid') ||
            !function_exists('posix_setuid')) {
            throw new RuntimeException(
                'POSIX user switching is unavailable; run PHPUnit with docker compose exec -u www-data.'
            );
        }
        $account = posix_getpwnam($webUser);
        if (!is_array($account)) {
            throw new RuntimeException("Web-server user {$webUser} was not found.");
        }
        $gid = (int) $account['gid'];
        $uid = (int) $account['uid'];
        if (function_exists('posix_initgroups') && !posix_initgroups($webUser, $gid)) {
            throw new RuntimeException("Could not initialize groups for {$webUser}.");
        }
        if (!posix_setgid($gid) || !posix_setuid($uid)) {
            throw new RuntimeException(
                "Could not switch to {$webUser} to create web-readable PHP sessions."
            );
        }
    }

    $authFile = dirname(__DIR__, 2) . '/includes/auth.php';
    if (!is_file($authFile)) {
        throw new RuntimeException("Missing {$authFile}; run tests within the OmniBuy repository.");
    }
    require_once $authFile;
    if (!function_exists('startSession')) {
        throw new RuntimeException('OmniBuy includes/auth.php must define startSession().');
    }

    if ($action === 'create') {
        if (!preg_match('/^[1-9][0-9]*$/D', $value)) {
            throw new InvalidArgumentException('The seed user ID must be a positive integer.');
        }
        $userId = (int) $value;
        if ((string) $userId !== $value) {
            throw new InvalidArgumentException('Invalid seed user ID.');
        }

        // Check real seeded user rows before creating a privileged session.
        $dbFile = dirname(__DIR__, 2) . '/db_connect.php';
        if (!is_file($dbFile)) {
            throw new RuntimeException("Missing {$dbFile}.");
        }
        require_once $dbFile;
        if (!isset($pdo) || !$pdo instanceof PDO) {
            throw new RuntimeException('db_connect.php did not provide PDO $pdo.');
        }
        $lookup = $pdo->prepare('SELECT account_status FROM users WHERE user_id = :user_id');
        $lookup->execute(['user_id' => $userId]);
        if ($lookup->fetchColumn() !== 'active') {
            throw new RuntimeException("Seed user {$userId} is missing or inactive.");
        }

        startSession();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('The application did not open a PHP session.');
        }
        $_SESSION['user_id'] = $userId;
        unset($_SESSION['wishlist_csrf_token']);
        $id = session_id();
        $name = session_name();
        session_write_close();
        if ($id === '' || $name === '') {
            throw new RuntimeException('Could not capture the test session cookie.');
        }
        echo json_encode(['cookie' => $name . '=' . $id, 'session_id' => $id], JSON_THROW_ON_ERROR);
        exit(0);
    }

    // Only destroy sessions that were created by this test run; the manager
    // passes their IDs. Never accept session IDs from HTTP/user input.
    if ($value === '' || !preg_match('/^[a-zA-Z0-9,-]{16,256}$/D', $value)) {
        throw new InvalidArgumentException('Invalid temporary session ID.');
    }
    session_id($value);
    startSession();
    if (session_status() !== PHP_SESSION_ACTIVE || session_id() !== $value) {
        throw new RuntimeException('Could not reopen the temporary session for cleanup.');
    }
    $_SESSION = [];
    session_destroy();
    echo json_encode(['destroyed' => true], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, 'Wishlist session fixture failed: ' . $error->getMessage() . "\n");
    exit(1);
}
