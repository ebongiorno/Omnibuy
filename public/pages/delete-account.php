<?php

// Delete Account: the logged-in user permanently deletes their own account.

require_once __DIR__ . '/../../includes/account.php';

requireLogin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    } else {
        require __DIR__ . '/../../db_connect.php';

        $field = static fn (string $name): string => is_string($_POST[$name] ?? null) ? $_POST[$name] : '';
        $errors = deleteAccount($pdo, currentUser()['user_id'], $field('password'), $field('confirm'));

        if ($errors === []) {
            logOutUser();

            header('Location: /pages/login.php?deleted=1');
            exit;
        }
    }
}

$pageTitle = 'Delete Account | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/auth.css">

<section class="shell auth-page">
  <div class="auth-card">
    <h1>Delete account</h1>
    <p class="auth-card__subtitle"><a href="/pages/account.php">Back to My Account</a></p>

    <?php if (isset($errors['form'])): ?>
      <p class="auth-alert auth-alert--error" role="alert"><?= htmlspecialchars($errors['form']) ?></p>
    <?php endif; ?>

    <div class="delete-warning">
      <p><strong>This can't be undone.</strong> Deleting your account will:</p>
      <ul>
        <li>Erase your name, username, email, phone number, bio and photo</li>
        <li>Remove your cart, wishlist, saved addresses and search history</li>
        <li>Take down all of your listings</li>
        <li>Log you out. You won't be able to log in again.</li>
      </ul>
      <p>Your past orders, reviews and messages stay for the other people involved, under the name "Deleted User".</p>
    </div>

    <form class="auth-form" method="post" action="/pages/delete-account.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

      <div class="auth-field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required
          <?= invalidAttributes($errors, 'password') ?>>
        <?= fieldError($errors, 'password') ?>
      </div>

      <div class="auth-field">
        <label for="confirm">Type <?= ACCOUNT_DELETE_CONFIRMATION ?> to confirm</label>
        <input type="text" id="confirm" name="confirm" autocomplete="off" autocapitalize="characters" required
          <?= invalidAttributes($errors, 'confirm') ?>>
        <?= fieldError($errors, 'confirm') ?>
      </div>

      <button class="button auth-form__submit button--danger" type="submit">Delete my account</button>
    </form>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
