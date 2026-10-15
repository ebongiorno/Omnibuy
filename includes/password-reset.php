<?php

/**
 * OmniBuy password reset by email (UC-03).
 *
 * 1. forgot-password.php: requestPasswordReset() emails a one-time link.
 * 2. reset-password.php?token=...: resetPasswordWithToken() sets the new password.
 *
 * The database keeps only a SHA-256 hash of each token. A token works once,
 * expires after PASSWORD_RESET_LIFETIME_MINUTES, and asking for a new link
 * cancels the older ones.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mailer.php';

const PASSWORD_RESET_LIFETIME_MINUTES = 30;
const PASSWORD_RESET_MAX_REQUESTS = 3;            // per account...
const PASSWORD_RESET_REQUEST_WINDOW_MINUTES = 15; // ...in this many minutes

/**
 * Emails a reset link if the address belongs to an active account.
 *
 * Returns nothing on purpose: the page shows the same message either way,
 * so the form can't be used to find out which emails have accounts.
 */
function requestPasswordReset(PDO $pdo, string $email): void
{
    $reset = createPasswordReset($pdo, $email);

    if ($reset !== null) {
        sendPasswordResetEmail($reset['user'], $reset['token']);
    }
}

/**
 * Creates a reset token for the account with this email.
 *
 * @return array{token: string, user: array{user_id: int, first_name: string, email: string}}|null
 *         null when there is no active account or it asked too many times
 */
function createPasswordReset(PDO $pdo, string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT user_id, first_name, email
        FROM users
        WHERE email = :email AND account_status = 'active'
    ");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user === false) {
        return null;
    }
    $user['user_id'] = (int) $user['user_id'];

    // Limit how many emails one account can be sent, so the form can't be
    // used to flood someone's inbox.
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM password_resets
        WHERE user_id = :id
            AND created_at > NOW() - INTERVAL ' . PASSWORD_RESET_REQUEST_WINDOW_MINUTES . ' MINUTE
    ');
    $stmt->execute(['id' => $user['user_id']]);
    if ((int) $stmt->fetchColumn() >= PASSWORD_RESET_MAX_REQUESTS) {
        return null;
    }

    cancelPasswordResets($pdo, $user['user_id']);

    $token = bin2hex(random_bytes(32));

    $pdo->prepare('
        INSERT INTO password_resets (user_id, token_hash, expires_at)
        VALUES (:id, :hash, NOW() + INTERVAL ' . PASSWORD_RESET_LIFETIME_MINUTES . ' MINUTE)
    ')->execute([
        'id' => $user['user_id'],
        'hash' => passwordResetTokenHash($token),
    ]);

    return ['token' => $token, 'user' => $user];
}

function sendPasswordResetEmail(array $user, string $token): bool
{
    $link = passwordResetLink($token);
    $minutes = PASSWORD_RESET_LIFETIME_MINUTES;

    $body = <<<TEXT
        Hi {$user['first_name']},

        Someone asked to reset the password for your OmniBuy account. If it was you, open this link within {$minutes} minutes to choose a new password:

        {$link}

        The link works once. If you didn't ask for this, you can ignore this email and your password will stay the same.

        - The OmniBuy team
        TEXT;

    return sendMail($user['email'], 'Reset your OmniBuy password', $body);
}

/**
 * The link in the email. The site address comes from SITE_URL, never from
 * the request's Host header, which an attacker could set to their own site
 * to receive someone else's token.
 */
function passwordResetLink(string $token): string
{
    $siteUrl = rtrim(getenv('SITE_URL') ?: 'http://localhost:8080', '/');

    return $siteUrl . '/pages/reset-password.php?token=' . urlencode($token);
}

function passwordResetTokenHash(string $token): string
{
    return hash('sha256', $token);
}

function isWellFormedResetToken(string $token): bool
{
    return preg_match('/^[0-9a-f]{64}$/', $token) === 1;
}

/**
 * The unused, unexpired reset for this token, or null.
 *
 * @return array{reset_id: int, user_id: int}|null
 */
function findPasswordReset(PDO $pdo, string $token, bool $lockRow = false): ?array
{
    if (!isWellFormedResetToken($token)) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT pr.reset_id, pr.user_id
        FROM password_resets pr
        JOIN users u ON u.user_id = pr.user_id
        WHERE pr.token_hash = :hash
            AND pr.used_at IS NULL
            AND pr.expires_at > NOW()
            AND u.account_status = 'active'
    " . ($lockRow ? ' FOR UPDATE' : ''));
    $stmt->execute(['hash' => passwordResetTokenHash($token)]);
    $reset = $stmt->fetch();

    if ($reset === false) {
        return null;
    }

    return ['reset_id' => (int) $reset['reset_id'], 'user_id' => (int) $reset['user_id']];
}

/**
 * Sets a new password using an emailed token.
 *
 * @return array<string, string> errors keyed 'token', 'password' or 'password_confirm' (empty on success)
 */
function resetPasswordWithToken(PDO $pdo, string $token, string $password, string $confirm): array
{
    $expired = ['token' => 'This reset link is invalid or has expired. Request a new one.'];

    if (findPasswordReset($pdo, $token) === null) {
        return $expired;
    }

    $errors = validateNewPassword($password, $confirm);
    if ($errors !== []) {
        return $errors;
    }

    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) {
        $pdo->beginTransaction();
    }

    try {
        // Lock the row so the same link can't be used twice at the same moment.
        $reset = findPasswordReset($pdo, $token, true);
        if ($reset === null) {
            if ($ownTransaction) {
                $pdo->rollBack();
            }
            return $expired;
        }

        $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :id')->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $reset['user_id'],
        ]);

        cancelPasswordResets($pdo, $reset['user_id']);

        if ($ownTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return [];
}

/**
 * Makes every outstanding reset link for the user stop working. Called when
 * a new link is requested and whenever the password changes.
 */
function cancelPasswordResets(PDO $pdo, int $userId): void
{
    $pdo->prepare('
        UPDATE password_resets
        SET used_at = NOW()
        WHERE user_id = :id AND used_at IS NULL
    ')->execute(['id' => $userId]);
}
