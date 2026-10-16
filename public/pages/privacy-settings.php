<?php

// UC-05 Privacy Settings: choose what other users see on your profile.

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
        updatePrivacySettings($pdo, $account['user_id'], privacyInputFromPost($_POST));

        header('Location: /pages/account.php?updated=privacy');
        exit;
    }
}

$notSet = '<span class="privacy-toggle__empty">not set yet</span>';
$toggles = [
    'last_name' => [
        'label' => 'Last name',
        'value' => htmlspecialchars($account['last_name']),
        'hint' => 'When off, other users see <strong>' . htmlspecialchars($account['first_name'])
            . '</strong> and searching for your last name won\'t find you.',
    ],
    'city' => [
        'label' => 'Town or city',
        'value' => $account['city'] !== null ? htmlspecialchars($account['city']) : $notSet,
        'hint' => 'Shown on your profile.',
    ],
    'email' => [
        'label' => 'Email',
        'value' => htmlspecialchars($account['email']),
        'hint' => 'Only logged-in users can see it.',
    ],
    'phone' => [
        'label' => 'Phone number',
        'value' => $account['phone_number'] !== '' ? htmlspecialchars($account['phone_number']) : $notSet,
        'hint' => 'Only logged-in users can see it.',
    ],
    'bio' => [
        'label' => 'Bio',
        'value' => $account['bio'] !== null
            ? '“' . htmlspecialchars(mb_strimwidth(preg_replace('/\s+/u', ' ', $account['bio']), 0, 60, '…')) . '”'
            : $notSet,
        'hint' => 'Shown on your profile and on your seller card in search.',
    ],
];

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
        <legend>Show on my profile</legend>

        <?php foreach ($toggles as $setting => $toggle): ?>
          <?php $isShown = !$account[PRIVACY_SETTINGS[$setting]]; ?>
          <div class="privacy-toggle">
            <div class="privacy-toggle__text">
              <label class="privacy-toggle__label" for="show_<?= $setting ?>"><?= $toggle['label'] ?></label>
              <p class="auth-field__hint" id="show_<?= $setting ?>_hint">
                <span class="privacy-toggle__value"><?= $toggle['value'] ?></span>. <?= $toggle['hint'] ?>
              </p>
            </div>
            <input class="privacy-switch" type="checkbox" role="switch" value="1"
              id="show_<?= $setting ?>" name="show_<?= $setting ?>" aria-describedby="show_<?= $setting ?>_hint"
              <?= $isShown ? ' checked' : '' ?>>
          </div>
        <?php endforeach; ?>
      </fieldset>

      <p class="auth-field__hint">
        Your first name, username, photo and listings are always visible. Your birth date is never shown.
        <a class="privacy-profile-link" href="/pages/profile.php?id=<?= $account['user_id'] ?>">See your public profile</a>
      </p>

      <button class="button button--primary auth-form__submit" type="submit">Save settings</button>
    </form>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
