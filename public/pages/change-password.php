<?php

// Change the password while logged in (the logged-out version is UC-03 Reset Password).

require_once __DIR__ . '/../../includes/account.php';

requireLogin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    } else {
        require __DIR__ . '/../../db_connect.php';

        $field = static fn (string $name): string => is_string($_POST[$name] ?? null) ? $_POST[$name] : '';
        $errors = changePassword(
            $pdo,
            currentUser()['user_id'],
            $field('current_password'),
            $field('password'),
            $field('password_confirm')
        );

        if ($errors === []) {
            session_regenerate_id(true);

            header('Location: /pages/account.php?updated=password');
            exit;
        }
    }
}

$pageTitle = 'Change Password | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/auth.css">

<section class="shell auth-page">
  <div class="auth-card">
    <h1>Change password</h1>
    <p class="auth-card__subtitle"><a href="/pages/account.php">Back to My Account</a></p>

    <?php if (isset($errors['form'])): ?>
      <p class="auth-alert auth-alert--error" role="alert"><?= htmlspecialchars($errors['form']) ?></p>
    <?php endif; ?>

    <form class="auth-form" method="post" action="/pages/change-password.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

      <div class="auth-field">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required
          <?= invalidAttributes($errors, 'current_password') ?>>
        <?= fieldError($errors, 'current_password') ?>
        <a class="auth-field__link" href="/pages/forgot-password.php">Forgot your password?</a>
      </div>

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

      <button class="button button--primary auth-form__submit" type="submit">Change password</button>
    </form>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
