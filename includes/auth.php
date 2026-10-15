<?php

/**
 * OmniBuy authentication: registration (UC-01), login/logout and sessions (UC-02).
 *
 * Session contract used across the site (other pages read these keys):
 *   $_SESSION['user_id']     int     the logged-in user's users.user_id
 *   $_SESSION['username']    string
 *   $_SESSION['avatar_url']  string  users.profile_image_url or the default avatar
 *   $_SESSION['is_seller']   bool    true if the user has a seller_profiles row
 *
 * Protect a page with:
 *   require_once __DIR__ . '/../../includes/auth.php';
 *   requireLogin();
 */

declare(strict_types=1);

const AUTH_DEFAULT_AVATAR = '/assets/icons/default-avatar.png';
const AUTH_PASSWORD_MIN_LENGTH = 8;
const AUTH_PASSWORD_MAX_BYTES = 72; // bcrypt ignores anything past 72 bytes
const AUTH_USERNAME_PATTERN = '/^[A-Za-z0-9_.]{3,30}$/';
const AUTH_PHONE_PATTERN = '/^\+?[0-9 ()\-]{7,20}$/';

// A real bcrypt hash (of a throwaway string) checked when no account matches,
// so failed logins take the same time whether or not the account exists.
const AUTH_TIMING_DUMMY_HASH = '$2y$10$h5cV0SxGSXkCQY92lIhRy.J7.2QMBsIIDexADov1X45OgvrE8yXlS';

// =========================================
// SESSIONS
// =========================================

/**
 * Starts the session with secure cookie settings. Safe to call more than once.
 */
function startSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * The logged-in user in the shape the shared header expects, or null.
 */
function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'user_id' => (int) $_SESSION['user_id'],
        'username' => (string) ($_SESSION['username'] ?? ''),
        'avatar_url' => (string) ($_SESSION['avatar_url'] ?? AUTH_DEFAULT_AVATAR),
        'is_seller' => (bool) ($_SESSION['is_seller'] ?? false),
    ];
}

/**
 * Sends logged-out visitors to the login page, then back here after login.
 */
function requireLogin(): void
{
    startSession();

    if (isLoggedIn()) {
        return;
    }

    $returnTo = safeRedirectTarget($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: /pages/login.php?redirect=' . urlencode($returnTo));
    exit;
}

/**
 * Stores the user in the session after a successful login or registration.
 */
function logInUser(PDO $pdo, array $user): void
{
    startSession();

    // New session ID on login prevents session fixation.
    session_regenerate_id(true);

    $stmt = $pdo->prepare('SELECT 1 FROM seller_profiles WHERE user_id = :id');
    $stmt->execute(['id' => $user['user_id']]);

    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['avatar_url'] = (string) ($user['profile_image_url'] ?? '') ?: AUTH_DEFAULT_AVATAR;
    $_SESSION['is_seller'] = (bool) $stmt->fetchColumn();
}

function logOutUser(): void
{
    startSession();

    $_SESSION = [];

    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ]);

    session_destroy();
}

/**
 * Only allows redirects to paths on this site, so a link like
 * login.php?redirect=https://evil.example can't send users elsewhere.
 */
function safeRedirectTarget(?string $target, string $fallback = '/'): string
{
    $target = (string) $target;

    if (
        $target === ''
        || $target[0] !== '/'
        || str_starts_with($target, '//')
        || str_contains($target, '\\')
        || preg_match('/[\x00-\x1F]/', $target)
    ) {
        return $fallback;
    }

    return $target;
}

// =========================================
// CSRF PROTECTION
// =========================================

function csrfToken(): string
{
    startSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function isValidCsrfToken(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// =========================================
// FORM HELPERS (sign-up and account pages)
// =========================================

/**
 * The error message under a form field, or '' when the field is valid.
 */
function fieldError(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="auth-field__error" id="' . $field . '-error">'
        . htmlspecialchars($errors[$field]) . '</p>';
}

/**
 * Marks an invalid field for screen readers and links it to its message.
 */
function invalidAttributes(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . $field . '-error"' : '';
}

// =========================================
// UC-01 REGISTRATION
// =========================================

/**
 * Trims the submitted registration fields. Email is lowercased so the
 * same address can't register twice with different capitalization.
 */
function normalizeRegistrationInput(array $post): array
{
    $field = static fn (string $name): string => is_string($post[$name] ?? null) ? trim($post[$name]) : '';

    return [
        'first_name' => $field('first_name'),
        'last_name' => $field('last_name'),
        'username' => $field('username'),
        'email' => strtolower($field('email')),
        'phone_number' => $field('phone_number'),
        'birth_date' => $field('birth_date'),
        // Passwords are not trimmed: spaces are allowed characters.
        'password' => is_string($post['password'] ?? null) ? $post['password'] : '',
        'password_confirm' => is_string($post['password_confirm'] ?? null) ? $post['password_confirm'] : '',
    ];
}

/**
 * @return array<string, string> field name => error message (empty if valid)
 */
function validateRegistration(array $input, ?DateTimeImmutable $today = null): array
{
    $today ??= new DateTimeImmutable('today');
    $errors = validateAccountFields($input);

    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $input['birth_date']);
    if ($input['birth_date'] === '') {
        $errors['birth_date'] = 'Birth date is required.';
    } elseif ($birthDate === false || $birthDate->format('Y-m-d') !== $input['birth_date']) {
        $errors['birth_date'] = 'Enter a valid birth date.';
    } elseif ($birthDate > $today) {
        $errors['birth_date'] = 'Birth date cannot be in the future.';
    }

    return $errors + validateNewPassword($input['password'], $input['password_confirm']);
}

/**
 * Name, username, email and phone rules shared by sign-up and profile editing.
 *
 * @return array<string, string> field name => error message
 */
function validateAccountFields(array $input): array
{
    $errors = [];

    foreach (['first_name' => 'First name', 'last_name' => 'Last name'] as $name => $label) {
        if ($input[$name] === '') {
            $errors[$name] = "$label is required.";
        } elseif (mb_strlen($input[$name]) > 100) {
            $errors[$name] = "$label must be 100 characters or fewer.";
        }
    }

    if ($input['username'] === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match(AUTH_USERNAME_PATTERN, $input['username'])) {
        $errors['username'] = 'Username must be 3–30 characters: letters, numbers, underscores, or periods.';
    }

    if ($input['email'] === '') {
        $errors['email'] = 'Email is required.';
    } elseif (strlen($input['email']) > 255 || filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($input['phone_number'] === '') {
        $errors['phone_number'] = 'Phone number is required.';
    } elseif (!preg_match(AUTH_PHONE_PATTERN, $input['phone_number'])) {
        $errors['phone_number'] = 'Enter a valid phone number.';
    }

    return $errors;
}

/**
 * Password rules shared by sign-up, password reset and password change.
 *
 * @return array<string, string> 'password' / 'password_confirm' => error message
 */
function validateNewPassword(string $password, string $confirm): array
{
    $errors = [];

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (mb_strlen($password) < AUTH_PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'Password must be at least ' . AUTH_PASSWORD_MIN_LENGTH . ' characters.';
    } elseif (strlen($password) > AUTH_PASSWORD_MAX_BYTES) {
        $errors['password'] = 'Password is too long.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must include at least one letter and one number.';
    }

    if (!isset($errors['password']) && $confirm !== $password) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }

    return $errors;
}

/**
 * Checks whether the email or username is already registered. Profile
 * editing passes the user's own ID so their current values don't count.
 *
 * @return array<string, string> field name => error message
 */
function findTakenAccountFields(PDO $pdo, string $email, string $username, ?int $exceptUserId = null): array
{
    $stmt = $pdo->prepare('
        SELECT
            SUM(email = :email) AS email_taken,
            SUM(username = :username) AS username_taken
        FROM users
        WHERE (email = :email2 OR username = :username2)
            AND user_id <> :except_id
    ');
    $stmt->execute([
        'email' => $email,
        'username' => $username,
        'email2' => $email,
        'username2' => $username,
        'except_id' => $exceptUserId ?? 0,
    ]);
    $row = $stmt->fetch();

    $errors = [];
    if (!empty($row['email_taken'])) {
        $errors['email'] = 'An account with this email already exists.';
    }
    if (!empty($row['username_taken'])) {
        $errors['username'] = 'This username is taken.';
    }

    return $errors;
}

/**
 * Validates and creates the account.
 *
 * @return array{user_id: ?int, errors: array<string, string>}
 */
function registerUser(PDO $pdo, array $input): array
{
    $errors = validateRegistration($input);
    if ($errors === []) {
        $errors = findTakenAccountFields($pdo, $input['email'], $input['username']);
    }
    if ($errors !== []) {
        return ['user_id' => null, 'errors' => $errors];
    }

    try {
        $pdo->prepare('
            INSERT INTO users (
                first_name, last_name, username, email, password_hash,
                phone_number, birth_date
            ) VALUES (
                :first_name, :last_name, :username, :email, :password_hash,
                :phone_number, :birth_date
            )
        ')->execute([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'phone_number' => $input['phone_number'],
            'birth_date' => $input['birth_date'],
        ]);
    } catch (PDOException $e) {
        // Someone registered the same email/username between our check and the insert.
        if ($e->getCode() === '23000') {
            $taken = findTakenAccountFields($pdo, $input['email'], $input['username']);
            return ['user_id' => null, 'errors' => $taken ?: ['email' => 'This account already exists.']];
        }
        throw $e;
    }

    return ['user_id' => (int) $pdo->lastInsertId(), 'errors' => []];
}

// =========================================
// UC-02 LOGIN
// =========================================

/**
 * Checks an email-or-username and password.
 *
 * @return array{user: ?array, error: ?string}
 */
function attemptLogin(PDO $pdo, string $identifier, string $password): array
{
    $invalid = ['user' => null, 'error' => 'Incorrect email/username or password.'];
    $identifier = trim($identifier);

    if ($identifier === '' || $password === '') {
        return ['user' => null, 'error' => 'Enter your email or username and password.'];
    }

    $stmt = $pdo->prepare('
        SELECT user_id, username, email, password_hash, profile_image_url, account_status
        FROM users
        WHERE email = :email OR username = :username
        LIMIT 1
    ');
    $stmt->execute(['email' => strtolower($identifier), 'username' => $identifier]);
    $user = $stmt->fetch();

    if ($user === false) {
        // Hash anyway so a missing account takes as long as a wrong password,
        // which stops attackers from timing which accounts exist.
        password_verify($password, AUTH_TIMING_DUMMY_HASH);
        return $invalid;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return $invalid;
    }

    if ($user['account_status'] !== 'active') {
        return ['user' => null, 'error' => 'This account is ' . $user['account_status'] . '. Contact support for help.'];
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :id')->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $user['user_id'],
        ]);
    }

    unset($user['password_hash']);

    return ['user' => $user, 'error' => null];
}
