<?php

// UC-02 Login

require_once __DIR__ . '/../../includes/auth.php';

startSession();

$redirect = safeRedirectTarget($_POST['redirect'] ?? $_GET['redirect'] ?? '/');

if (isLoggedIn()) {
    header('Location: ' . $redirect);
    exit;
}

$identifier = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = is_string($_POST['identifier'] ?? null) ? trim($_POST['identifier']) : '';
    // Not named $password: db_connect.php sets a global $password (the database password).
    $submittedPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        require __DIR__ . '/../../db_connect.php';

        $result = attemptLogin($pdo, $identifier, $submittedPassword);
        $error = $result['error'];

        if ($result['user'] !== null) {
            logInUser($pdo, $result['user']);

            header('Location: ' . $redirect);
            exit;
        }
    }
}

$notice = null;
if (isset($_GET['logged_out'])) {
    $notice = 'You have been logged out.';
} elseif (isset($_GET['redirect']) && $error === null) {
    $notice = 'Please log in to continue.';
}

$pageTitle = 'Log In | OmniBuy';
$isLoggedIn = false;
$currentUser = null;

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/omnibuy-design-tokens.css">
<link rel="stylesheet" href="/assets/css/shared-navigation.css">
<link rel="stylesheet" href="/assets/css/auth.css">

<section class="shell auth-page">
  <div class="auth-card">
    <h1>Log in to OmniBuy</h1>
    <p class="auth-card__subtitle">
      New to OmniBuy? <a href="/pages/signup.php">Create an account</a>
    </p>

    <?php if ($error !== null): ?>
      <p class="auth-alert auth-alert--error" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php elseif ($notice !== null): ?>
      <p class="auth-alert" role="status"><?= htmlspecialchars($notice) ?></p>
    <?php endif; ?>

    <form class="auth-form" method="post" action="/pages/login.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

      <div class="auth-field">
        <label for="identifier">Email or username</label>
        <input type="text" id="identifier" name="identifier" autocomplete="username" required
          value="<?= htmlspecialchars($identifier) ?>">
      </div>

      <div class="auth-field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>

      <button class="button button--primary auth-form__submit" type="submit">Log in</button>
    </form>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
