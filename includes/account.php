<?php

/**
 * OmniBuy account management: profile editing (UC-04), privacy settings
 * (UC-05) and changing the password while logged in.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/password-reset.php';

const PROFILE_BIO_MAX_LENGTH = 500;
const PROFILE_IMAGE_MAX_BYTES = 2 * 1024 * 1024;
const PROFILE_IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
// Uploaded photos live in a Docker volume mounted here (see docker-compose.yml).
const PROFILE_IMAGE_DIR = __DIR__ . '/../public/uploads/avatars';
const PROFILE_IMAGE_URL_PREFIX = '/uploads/avatars/';

/**
 * Everything the account pages show. Never includes the password hash.
 */
function loadAccount(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('
        SELECT
            user_id, first_name, last_name, username, email, phone_number,
            birth_date, profile_image_url, bio, is_last_name_hidden, created_at
        FROM users
        WHERE user_id = :id
    ');
    $stmt->execute(['id' => $userId]);
    $account = $stmt->fetch();

    if ($account === false) {
        return null;
    }

    $account['user_id'] = (int) $account['user_id'];
    $account['is_last_name_hidden'] = (bool) $account['is_last_name_hidden'];

    return $account;
}

/**
 * The name other users should see: first name only when the user hid
 * their last name (UC-05). Use this wherever a user's name is shown to
 * someone else.
 */
function publicDisplayName(string $firstName, string $lastName, bool $isLastNameHidden): string
{
    return $isLastNameHidden || $lastName === '' ? $firstName : "$firstName $lastName";
}

/**
 * Keeps the header's name and photo in step after a profile change.
 */
function refreshSessionProfile(array $account): void
{
    $_SESSION['username'] = (string) $account['username'];
    $_SESSION['avatar_url'] = (string) ($account['profile_image_url'] ?? '') ?: AUTH_DEFAULT_AVATAR;
}

// =========================================
// UC-04 EDIT PROFILE
// =========================================

function normalizeProfileInput(array $post): array
{
    $field = static fn (string $name): string => is_string($post[$name] ?? null) ? trim($post[$name]) : '';

    return [
        'first_name' => $field('first_name'),
        'last_name' => $field('last_name'),
        'username' => $field('username'),
        'email' => strtolower($field('email')),
        'phone_number' => $field('phone_number'),
        'bio' => str_replace(["\r\n", "\r"], "\n", $field('bio')),
        'remove_photo' => ($post['remove_photo'] ?? '') === '1',
        'current_password' => is_string($post['current_password'] ?? null) ? $post['current_password'] : '',
    ];
}

/**
 * The form's starting values, from the saved account.
 */
function profileInputFromAccount(array $account): array
{
    return [
        'first_name' => $account['first_name'],
        'last_name' => $account['last_name'],
        'username' => $account['username'],
        'email' => $account['email'],
        'phone_number' => $account['phone_number'],
        'bio' => (string) ($account['bio'] ?? ''),
        'remove_photo' => false,
        'current_password' => '',
    ];
}

/**
 * @return array<string, string> field name => error message
 */
function validateProfile(array $input): array
{
    $errors = validateAccountFields($input);

    if (mb_strlen($input['bio']) > PROFILE_BIO_MAX_LENGTH) {
        $errors['bio'] = 'Bio must be ' . PROFILE_BIO_MAX_LENGTH . ' characters or fewer.';
    }

    return $errors;
}

/**
 * Checks an uploaded profile photo from $_FILES without moving it.
 *
 * @return array{error: ?string, extension: ?string} extension is null when no file was chosen
 */
function validateProfileImageUpload(?array $file): array
{
    $none = ['error' => null, 'extension' => null];
    $tooBig = ['error' => 'Photo must be 2 MB or smaller.', 'extension' => null];
    $wrongType = ['error' => 'Photo must be a JPEG, PNG or WebP image.', 'extension' => null];

    if ($file === null || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $none;
    }

    // photo[]=... sends arrays instead of a single file.
    if (!is_int($file['error']) || !is_string($file['tmp_name'] ?? null)) {
        return ['error' => 'Upload one photo at a time.', 'extension' => null];
    }

    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return $tooBig;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'The photo could not be uploaded. Please try again.', 'extension' => null];
    }
    if ((int) ($file['size'] ?? 0) > PROFILE_IMAGE_MAX_BYTES || filesize($file['tmp_name']) > PROFILE_IMAGE_MAX_BYTES) {
        return $tooBig;
    }

    // Check the file's real contents, not the name or the browser's claimed type.
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(PROFILE_IMAGE_TYPES[$mimeType]) || @getimagesize($file['tmp_name']) === false) {
        return $wrongType;
    }

    return ['error' => null, 'extension' => PROFILE_IMAGE_TYPES[$mimeType]];
}

/**
 * Saves the user's profile changes. Changing the email needs the current
 * password, so someone at an unattended logged-in computer can't take
 * over the account by changing the email and resetting the password.
 *
 * @param array|null $photo the $_FILES entry for the photo field
 * @return array{errors: array<string, string>, account: array} account is the saved row
 */
function updateProfile(PDO $pdo, array $account, array $input, ?array $photo = null): array
{
    $userId = $account['user_id'];
    $errors = validateProfile($input);
    $emailChanged = $input['email'] !== $account['email'];

    if ($emailChanged && !isset($errors['email']) && !verifyCurrentPassword($pdo, $userId, $input['current_password'])) {
        $errors['current_password'] = $input['current_password'] === ''
            ? 'Enter your current password to change your email.'
            : 'Your current password is incorrect.';
    }

    $upload = validateProfileImageUpload($photo);
    if ($upload['error'] !== null) {
        $errors['photo'] = $upload['error'];
    }

    if ($errors === []) {
        $errors = findTakenAccountFields($pdo, $input['email'], $input['username'], $userId);
    }
    if ($errors !== []) {
        return ['errors' => $errors, 'account' => $account];
    }

    $oldImageUrl = $account['profile_image_url'];
    $imageUrl = $input['remove_photo'] ? null : $oldImageUrl;
    if ($upload['extension'] !== null) {
        $imageUrl = storeProfileImage($photo['tmp_name'], $upload['extension']);
        if ($imageUrl === null) {
            return ['errors' => ['photo' => 'The photo could not be saved. Please try again.'], 'account' => $account];
        }
    }

    try {
        $pdo->prepare('
            UPDATE users
            SET first_name = :first_name,
                last_name = :last_name,
                username = :username,
                email = :email,
                phone_number = :phone_number,
                bio = :bio,
                profile_image_url = :profile_image_url,
                is_email_verified = is_email_verified AND NOT :email_changed
            WHERE user_id = :id
        ')->execute([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'username' => $input['username'],
            'email' => $input['email'],
            'phone_number' => $input['phone_number'],
            'bio' => $input['bio'] === '' ? null : $input['bio'],
            'profile_image_url' => $imageUrl,
            'email_changed' => (int) $emailChanged,
            'id' => $userId,
        ]);
    } catch (PDOException $e) {
        if ($imageUrl !== $oldImageUrl) {
            deleteProfileImage($imageUrl);
        }
        // Someone took the email/username between our check and the update.
        if ($e->getCode() === '23000') {
            $taken = findTakenAccountFields($pdo, $input['email'], $input['username'], $userId);
            return ['errors' => $taken ?: ['username' => 'This username is taken.'], 'account' => $account];
        }
        throw $e;
    }

    if ($imageUrl !== $oldImageUrl) {
        deleteProfileImage($oldImageUrl);
    }

    return ['errors' => [], 'account' => loadAccount($pdo, $userId)];
}

/**
 * Moves an uploaded photo into the avatars folder under a random name.
 *
 * @return string|null the photo's URL, or null if it could not be saved
 */
function storeProfileImage(string $tmpPath, string $extension): ?string
{
    if (!is_dir(PROFILE_IMAGE_DIR) && !@mkdir(PROFILE_IMAGE_DIR, 0775, true) && !is_dir(PROFILE_IMAGE_DIR)) {
        error_log('storeProfileImage: cannot create ' . PROFILE_IMAGE_DIR);
        return null;
    }

    // Random names: users can't pick the file name, overwrite someone
    // else's photo, or upload something the server would run as code.
    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;

    if (!move_uploaded_file($tmpPath, PROFILE_IMAGE_DIR . '/' . $fileName)) {
        error_log('storeProfileImage: cannot write to ' . PROFILE_IMAGE_DIR);
        return null;
    }

    return PROFILE_IMAGE_URL_PREFIX . $fileName;
}

/**
 * Deletes a photo this code uploaded. Other URLs (seed data, external
 * images) are left alone.
 */
function deleteProfileImage(?string $url): void
{
    if ($url === null || !str_starts_with($url, PROFILE_IMAGE_URL_PREFIX)) {
        return;
    }

    $fileName = substr($url, strlen(PROFILE_IMAGE_URL_PREFIX));
    if (preg_match('/^[0-9a-f]{32}\.(jpg|png|webp)$/', $fileName)) {
        @unlink(PROFILE_IMAGE_DIR . '/' . $fileName);
    }
}

// =========================================
// UC-05 PRIVACY SETTINGS
// =========================================

function updatePrivacySettings(PDO $pdo, int $userId, bool $hideLastName): void
{
    $pdo->prepare('UPDATE users SET is_last_name_hidden = :hidden WHERE user_id = :id')->execute([
        'hidden' => (int) $hideLastName,
        'id' => $userId,
    ]);
}

// =========================================
// CHANGE PASSWORD (logged in)
// =========================================

function verifyCurrentPassword(PDO $pdo, int $userId, string $password): bool
{
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
    $stmt->execute(['id' => $userId]);
    $hash = $stmt->fetchColumn();

    return $password !== '' && is_string($hash) && password_verify($password, $hash);
}

/**
 * @return array<string, string> errors keyed 'current_password', 'password' or 'password_confirm'
 */
function changePassword(PDO $pdo, int $userId, string $currentPassword, string $newPassword, string $confirm): array
{
    if (!verifyCurrentPassword($pdo, $userId, $currentPassword)) {
        return ['current_password' => $currentPassword === ''
            ? 'Enter your current password.'
            : 'Your current password is incorrect.'];
    }

    $errors = validateNewPassword($newPassword, $confirm);
    if ($errors === [] && $newPassword === $currentPassword) {
        $errors['password'] = 'Choose a password different from your current one.';
    }
    if ($errors !== []) {
        return $errors;
    }

    $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :id')->execute([
        'hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'id' => $userId,
    ]);

    // A reset link emailed before the change must not undo it.
    cancelPasswordResets($pdo, $userId);

    return [];
}
