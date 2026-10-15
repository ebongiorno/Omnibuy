<?php

// UC-05 Privacy Settings

require_once __DIR__ . '/../../includes/account.php';

requireLogin();

require __DIR__ . '/../../db_connect.php';

$account = loadAccount($pdo, currentUser()['user_id']);
if ($account === null) {
    logOutUser();
    header('Location: /pages/login.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please submit the form again.';
    } else {
        updatePrivacySettings($pdo, $account['user_id'], ($_POST['hide_last_name'] ?? '') === '1');

        header('Location: /pages/account.php?updated=privacy');
        exit;
    }
}

$pageTitle = 'Privacy Settings | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/auth.css">

<section class="shell auth-page">
  <div class="auth-card">
    <h1>Privacy settings</h1>
    <p class="auth-card__subtitle"><a href="/pages/account.php">Back to My Account</a></p>

    <?php if ($error !== null): ?>
      <p class="auth-alert auth-alert--error" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form class="auth-form" method="post" action="/pages/privacy-settings.php">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

      <fieldset class="auth-fieldset">
        <legend>Your name</legend>

        <label class="auth-checkbox">
          <input type="checkbox" name="hide_last_name" value="1" aria-describedby="hide-last-name-hint"
            <?= $account['is_last_name_hidden'] ? ' checked' : '' ?>>
          Hide my last name from other users
        </label>
        <p class="auth-field__hint" id="hide-last-name-hint">
          Other users will see <strong><?= htmlspecialchars($account['first_name']) ?></strong>
          instead of <strong><?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?></strong>,
          and searching for your last name won't find you. Your username is always visible.
        </p>
      </fieldset>

      <p class="auth-field__hint">
        Your email, phone number and birth date are never shown to other users.
      </p>

      <button class="button button--primary auth-form__submit" type="submit">Save settings</button>
    </form>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
