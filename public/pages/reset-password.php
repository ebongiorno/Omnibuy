<?php

// UC-03 Reset Password: step 2, choose a new password from the emailed link.

require_once __DIR__ . '/../../includes/password-reset.php';

// The token is in this page's URL; don't send it to other sites in the Referer header.
header('Referrer-Policy: no-referrer');

startSession();

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$token = is_string($token) ? trim($token) : '';
$errors = [];

require __DIR__ . '/../../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    } else {
        $errors = resetPasswordWithToken(
            $pdo,
            $token,
            is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
            is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : ''
        );

        if ($errors === []) {
            // Whoever was logged in on this browser isn't necessarily the account owner.
            logOutUser();

            header('Location: /pages/login.php?reset=1');
            exit;
        }
    }
}

$linkIsValid = !isset($errors['token']) && findPasswordReset($pdo, $token) !== null;

$pageTitle = 'Choose a New Password | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/auth.css">

<section class="shell auth-page">
  <div class="auth-card">
    <?php if (!$linkIsValid): ?>
      <h1>Link expired</h1>
      <p class="auth-alert auth-alert--error" role="alert">
        This reset link is invalid or has expired. Links work once and for <?= PASSWORD_RESET_LIFETIME_MINUTES ?> minutes.
      </p>
      <a class="button button--primary auth-form__submit" href="/pages/forgot-password.php">Request a new link</a>
    <?php else: ?>
      <h1>Choose a new password</h1>
      <p class="auth-card__subtitle">After this, log in with your new password.</p>

      <?php if (isset($errors['form'])): ?>
        <p class="auth-alert auth-alert--error" role="alert"><?= htmlspecialchars($errors['form']) ?></p>
      <?php endif; ?>

      <form class="auth-form" method="post" action="/pages/reset-password.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="auth-field">
          <label for="password">New password</label>
          <input type="password" id="password" name="password" autocomplete="new-password" required
            minlength="<?= AUTH_PASSWORD_MIN_LENGTH ?>"<?= invalidAttributes($errors, 'password') ?>>
          <p class="auth-field__hint">At least <?= AUTH_PASSWORD_MIN_LENGTH ?> characters, including a letter and a number.</p>
          <?= fieldError($errors, 'password') ?>
        </div>

        <div class="auth-field">
          <label for="password_confirm">Confirm new password</label>
          <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required
            <?= invalidAttributes($errors, 'password_confirm') ?>>
          <?= fieldError($errors, 'password_confirm') ?>
        </div>

        <button class="button button--primary auth-form__submit" type="submit">Save new password</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
