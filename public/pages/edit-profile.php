<?php

// UC-04 Edit Profile

require_once __DIR__ . '/../../includes/account.php';

requireLogin();

require __DIR__ . '/../../db_connect.php';

$account = loadAccount($pdo, currentUser()['user_id']);
if ($account === null) {
    logOutUser();
    header('Location: /pages/login.php');
    exit;
}

$input = profileInputFromAccount($account);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = normalizeProfileInput($_POST);

    if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        // PHP drops the whole form when the upload is over post_max_size.
        $input = profileInputFromAccount($account);
        $errors['photo'] = 'Photo must be 2 MB or smaller.';
    } elseif (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    } else {
        $result = updateProfile($pdo, $account, $input, $_FILES['photo'] ?? null);
        $errors = $result['errors'];

        if ($errors === []) {
            refreshSessionProfile($result['account']);

            header('Location: /pages/account.php?updated=profile');
            exit;
        }
    }
}

$pageTitle = 'Edit Profile | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/auth.css">

<section class="shell auth-page">
  <div class="auth-card">
    <h1>Edit profile</h1>
    <p class="auth-card__subtitle"><a href="/pages/account.php">Back to My Account</a></p>

    <?php if (isset($errors['form'])): ?>
      <p class="auth-alert auth-alert--error" role="alert"><?= htmlspecialchars($errors['form']) ?></p>
    <?php elseif ($errors !== []): ?>
      <p class="auth-alert auth-alert--error" role="alert">Please fix the highlighted fields.</p>
    <?php endif; ?>

    <form class="auth-form" method="post" action="/pages/edit-profile.php" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

      <fieldset class="auth-fieldset">
        <legend>Profile photo</legend>
        <div class="photo-field">
          <img class="account-avatar" alt="Your current profile photo"
            src="<?= htmlspecialchars($account['profile_image_url'] ?: AUTH_DEFAULT_AVATAR) ?>">
          <div class="auth-field">
            <label for="photo">Upload a new photo</label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp"
              <?= invalidAttributes($errors, 'photo') ?>>
            <p class="auth-field__hint">JPEG, PNG or WebP, up to 2 MB.</p>
            <?= fieldError($errors, 'photo') ?>
            <?php if ($account['profile_image_url'] !== null): ?>
              <label class="auth-checkbox">
                <input type="checkbox" name="remove_photo" value="1"<?= $input['remove_photo'] ? ' checked' : '' ?>>
                Remove my photo
              </label>
            <?php endif; ?>
          </div>
        </div>
      </fieldset>

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
        <label for="phone_number">Phone number</label>
        <input type="tel" id="phone_number" name="phone_number" maxlength="20" autocomplete="tel" required
          value="<?= htmlspecialchars($input['phone_number']) ?>"<?= invalidAttributes($errors, 'phone_number') ?>>
        <?= fieldError($errors, 'phone_number') ?>
      </div>

      <div class="auth-field">
        <label for="city">City <span class="auth-field__optional">(optional)</span></label>
        <input type="text" id="city" name="city" maxlength="<?= PROFILE_CITY_MAX_LENGTH ?>" autocomplete="address-level2"
          value="<?= htmlspecialchars($input['city']) ?>"<?= invalidAttributes($errors, 'city') ?>>
        <p class="auth-field__hint">Shown on your public profile.</p>
        <?= fieldError($errors, 'city') ?>
      </div>

      <div class="auth-field">
        <label for="bio">Bio <span class="auth-field__optional">(optional)</span></label>
        <textarea id="bio" name="bio" rows="4" maxlength="<?= PROFILE_BIO_MAX_LENGTH ?>"
          <?= invalidAttributes($errors, 'bio') ?>><?= htmlspecialchars($input['bio']) ?></textarea>
        <p class="auth-field__hint">Up to <?= PROFILE_BIO_MAX_LENGTH ?> characters. Shown on your seller profile.</p>
        <?= fieldError($errors, 'bio') ?>
      </div>

      <fieldset class="auth-fieldset">
        <legend>Email</legend>
        <div class="auth-field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" maxlength="255" autocomplete="email" required
            value="<?= htmlspecialchars($input['email']) ?>"<?= invalidAttributes($errors, 'email') ?>>
          <?= fieldError($errors, 'email') ?>
        </div>

        <div class="auth-field">
          <label for="current_password">Current password</label>
          <input type="password" id="current_password" name="current_password" autocomplete="current-password"
            <?= invalidAttributes($errors, 'current_password') ?>>
          <p class="auth-field__hint">Only needed if you change your email.</p>
          <?= fieldError($errors, 'current_password') ?>
        </div>
      </fieldset>

      <button class="button button--primary auth-form__submit" type="submit">Save changes</button>
    </form>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
