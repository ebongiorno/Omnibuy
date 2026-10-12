<?php

// Account overview. Logged-in users only (UC-02); profile editing comes with UC-04.

require_once __DIR__ . '/../../includes/auth.php';

requireLogin();

require __DIR__ . '/../../db_connect.php';

$stmt = $pdo->prepare('
    SELECT username, email, first_name, last_name, created_at
    FROM users
    WHERE user_id = :id
');
$stmt->execute(['id' => currentUser()['user_id']]);
$account = $stmt->fetch();

// The account was deleted while the user was logged in.
if ($account === false) {
    logOutUser();
    header('Location: /pages/login.php');
    exit;
}

$pageTitle = 'My Account | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/omnibuy-design-tokens.css">
<link rel="stylesheet" href="/assets/css/shared-navigation.css">
<link rel="stylesheet" href="/assets/css/auth.css">

<section class="shell auth-page">
  <div class="auth-card">
    <h1>My Account</h1>
    <p class="auth-card__subtitle">
      Member since <?= htmlspecialchars(date('F j, Y', strtotime($account['created_at']))) ?>
    </p>

    <dl class="account-details">
      <dt>Name</dt>
      <dd><?= htmlspecialchars($account['first_name'] . ' ' . $account['last_name']) ?></dd>

      <dt>Username</dt>
      <dd><?= htmlspecialchars($account['username']) ?></dd>

      <dt>Email</dt>
      <dd><?= htmlspecialchars($account['email']) ?></dd>
    </dl>

    <form action="/pages/logout.php" method="post">
      <button class="button auth-form__submit" type="submit">Log out</button>
    </form>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
