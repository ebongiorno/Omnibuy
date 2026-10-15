<?php

// Account overview: links to profile editing (UC-04), privacy settings
// (UC-05) and password change. Logged-in users only (UC-02).

require_once __DIR__ . '/../../includes/account.php';

requireLogin();

require __DIR__ . '/../../db_connect.php';

$account = loadAccount($pdo, currentUser()['user_id']);

// The account was deleted while the user was logged in.
if ($account === null) {
    logOutUser();
    header('Location: /pages/login.php');
    exit;
}

$notices = [
    'profile' => 'Your profile has been updated.',
    'privacy' => 'Your privacy settings have been saved.',
    'password' => 'Your password has been changed.',
];
$notice = $notices[$_GET['updated'] ?? ''] ?? null;

$pageTitle = 'My Account | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/auth.css">

<section class="shell auth-page">
  <div class="auth-card">
    <div class="account-heading">
      <img class="account-avatar" alt=""
        src="<?= htmlspecialchars($account['profile_image_url'] ?: AUTH_DEFAULT_AVATAR) ?>">
      <div>
        <h1>My Account</h1>
        <p class="auth-card__subtitle">
          Member since <?= htmlspecialchars(date('F j, Y', strtotime($account['created_at']))) ?>
        </p>
      </div>
    </div>

    <?php if ($notice !== null): ?>
      <p class="auth-alert" role="status"><?= htmlspecialchars($notice) ?></p>
    <?php endif; ?>

    <dl class="account-details">
      <dt>Name</dt>
      <dd><?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?></dd>

      <dt>Shown to others as</dt>
      <dd><?= htmlspecialchars(publicDisplayName(
          $account['first_name'],
          $account['last_name'],
          $account['is_last_name_hidden']
      )) ?></dd>

      <dt>Username</dt>
      <dd><?= htmlspecialchars($account['username']) ?></dd>

      <dt>Email</dt>
      <dd><?= htmlspecialchars($account['email']) ?></dd>

      <dt>Phone</dt>
      <dd><?= htmlspecialchars($account['phone_number']) ?></dd>

      <dt>Bio</dt>
      <dd class="account-details__bio"><?= $account['bio'] !== null
          ? htmlspecialchars($account['bio'])
          : '<span class="account-details__empty">No bio yet</span>' ?></dd>
    </dl>

    <nav class="account-actions" aria-label="Account settings">
      <a class="account-actions__link" href="/pages/edit-profile.php">
        <span>Edit profile</span>
        <span class="account-actions__hint">Name, username, email, phone, bio and photo</span>
      </a>
      <a class="account-actions__link" href="/pages/privacy-settings.php">
        <span>Privacy settings</span>
        <span class="account-actions__hint">Choose what other users can see</span>
      </a>
      <a class="account-actions__link" href="/pages/change-password.php">
        <span>Change password</span>
        <span class="account-actions__hint">Update the password you log in with</span>
      </a>
    </nav>

    <form action="/pages/logout.php" method="post">
      <button class="button auth-form__submit" type="submit">Log out</button>
    </form>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
