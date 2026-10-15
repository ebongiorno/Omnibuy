<?php

// UC-03 Reset Password: step 1, ask for the reset email.

require_once __DIR__ . '/../../includes/password-reset.php';

startSession();

$email = '';
$error = null;
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } elseif ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $error = 'Enter the email address you signed up with.';
    } else {
        require __DIR__ . '/../../db_connect.php';

        requestPasswordReset($pdo, $email);
        $sent = true;
    }
}

$pageTitle = 'Forgot Password | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/auth.css">

<section class="shell auth-page">
  <div class="auth-card">
    <h1>Reset your password</h1>

    <?php if ($sent): ?>
      <p class="auth-alert" role="status">
        If an account uses <strong><?= htmlspecialchars($email) ?></strong>, we've sent it a link to reset the password.
        The link works for <?= PASSWORD_RESET_LIFETIME_MINUTES ?> minutes.
      </p>
      <p class="auth-card__subtitle">
        No email? Check your spam folder, or <a href="/pages/forgot-password.php">try again</a>.
      </p>
      <a class="button button--primary auth-form__submit" href="/pages/login.php">Back to log in</a>
    <?php else: ?>
      <p class="auth-card__subtitle">
        Enter the email you signed up with and we'll send you a link to choose a new password.
      </p>

      <?php if ($error !== null): ?>
        <p class="auth-alert auth-alert--error" role="alert"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form class="auth-form" method="post" action="/pages/forgot-password.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

        <div class="auth-field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" maxlength="255" autocomplete="email" required
            value="<?= htmlspecialchars($email) ?>">
        </div>

        <button class="button button--primary auth-form__submit" type="submit">Send reset link</button>
      </form>

      <p class="auth-card__footer">
        Remembered it? <a href="/pages/login.php">Log in</a>
      </p>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
