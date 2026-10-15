<?php

// UC-01 Register with Email

require_once __DIR__ . '/../../includes/auth.php';

startSession();

if (isLoggedIn()) {
    header('Location: /');
    exit;
}

$input = normalizeRegistrationInput([]);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = normalizeRegistrationInput($_POST);

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    } else {
        require __DIR__ . '/../../db_connect.php';

        $result = registerUser($pdo, $input);
        $errors = $result['errors'];

        if ($result['user_id'] !== null) {
            logInUser($pdo, [
                'user_id' => $result['user_id'],
                'username' => $input['username'],
                'profile_image_url' => null,
            ]);

            header('Location: /?welcome=1');
            exit;
        }
    }
}

$pageTitle = 'Sign Up | OmniBuy';
$isLoggedIn = false;
$currentUser = null;

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/auth.css">

<section class="shell auth-page">
  <div class="auth-card">
    <h1>Create your account</h1>
    <p class="auth-card__subtitle">
      Already have an account? <a href="/pages/login.php">Log in</a>
    </p>

    <?php if (isset($errors['form'])): ?>
      <p class="auth-alert auth-alert--error" role="alert"><?= htmlspecialchars($errors['form']) ?></p>
    <?php elseif ($errors !== []): ?>
      <p class="auth-alert auth-alert--error" role="alert">Please fix the highlighted fields.</p>
    <?php endif; ?>

    <form class="auth-form" method="post" action="/pages/signup.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

      <div class="auth-form__row">
        <div class="auth-field">
          <label for="first_name">First name</label>
          <input type="text" id="first_name" name="first_name" maxlength="100" autocomplete="given-name" required
            value="<?= htmlspecialchars($input['first_name']) ?>"<?= invalidAttributes($errors, 'first_name') ?>>
          <?= fieldError($errors, 'first_name') ?>
        </div>

        <div class="auth-field">
          <label for="last_name">Last name</label>
          <input type="text" id="last_name" name="last_name" maxlength="100" autocomplete="family-name" required
            value="<?= htmlspecialchars($input['last_name']) ?>"<?= invalidAttributes($errors, 'last_name') ?>>
          <?= fieldError($errors, 'last_name') ?>
        </div>
      </div>

      <div class="auth-field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" maxlength="30" autocomplete="username" required
          value="<?= htmlspecialchars($input['username']) ?>"<?= invalidAttributes($errors, 'username') ?>>
        <p class="auth-field__hint">3–30 characters: letters, numbers, underscores, or periods.</p>
        <?= fieldError($errors, 'username') ?>
      </div>

      <div class="auth-field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" maxlength="255" autocomplete="email" required
          value="<?= htmlspecialchars($input['email']) ?>"<?= invalidAttributes($errors, 'email') ?>>
        <?= fieldError($errors, 'email') ?>
      </div>

      <div class="auth-form__row">
        <div class="auth-field">
          <label for="phone_number">Phone number</label>
          <input type="tel" id="phone_number" name="phone_number" maxlength="20" autocomplete="tel" required
            value="<?= htmlspecialchars($input['phone_number']) ?>"<?= invalidAttributes($errors, 'phone_number') ?>>
          <?= fieldError($errors, 'phone_number') ?>
        </div>

        <div class="auth-field">
          <label for="birth_date">Birth date</label>
          <input type="date" id="birth_date" name="birth_date" max="<?= date('Y-m-d') ?>" autocomplete="bday" required
            value="<?= htmlspecialchars($input['birth_date']) ?>"<?= invalidAttributes($errors, 'birth_date') ?>>
          <?= fieldError($errors, 'birth_date') ?>
        </div>
      </div>

      <div class="auth-field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="new-password" required
          minlength="<?= AUTH_PASSWORD_MIN_LENGTH ?>"<?= invalidAttributes($errors, 'password') ?>>
        <p class="auth-field__hint">At least <?= AUTH_PASSWORD_MIN_LENGTH ?> characters, including a letter and a number.</p>
        <?= fieldError($errors, 'password') ?>
      </div>

      <div class="auth-field">
        <label for="password_confirm">Confirm password</label>
        <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required
          <?= invalidAttributes($errors, 'password_confirm') ?>>
        <?= fieldError($errors, 'password_confirm') ?>
      </div>

      <button class="button button--primary auth-form__submit" type="submit">Create account</button>
    </form>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
