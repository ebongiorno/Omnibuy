<?php

// Write a review for one purchased item: rate the item (shown on the
// listing page) and/or the seller (shown on their profile). Reached from
// My Orders with ?order_item_id=N; only the buyer of that item can use it.

declare(strict_types=1);

require_once __DIR__ . '/../../includes/reviews.php';
require_once __DIR__ . '/../../includes/orders.php';

requireLogin();

require __DIR__ . '/../../db_connect.php';

$buyerId = currentUser()['user_id'];
$orderItemId = filter_var(
    $_POST['order_item_id'] ?? $_GET['order_item_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$item = $orderItemId === false ? null : loadReviewableItem($pdo, $buyerId, $orderItemId);

if ($item === null) {
    http_response_code(404);
}

$errors = [];
$input = [];
foreach (REVIEW_KINDS as $kind) {
    $input[$kind] = ['rating' => null, 'title' => '', 'description' => ''];
}

if ($item !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (REVIEW_KINDS as $kind) {
        $input[$kind] = normalizeReviewInput($_POST, $kind);
    }

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    } else {
        $result = submitReviews($pdo, $buyerId, (int) $orderItemId, $input);
        $errors = $result['errors'];

        if ($result['ok']) {
            $_SESSION['orders_flash'] = ['ok' => true, 'message' => 'Thanks for your review!'];
            header('Location: /pages/orders.php', true, 303);
            exit;
        }
    }
}

$problem = $item === null ? null : reviewProblem($item, $buyerId);
$pageTitle = 'Write a Review | OmniBuy';

/**
 * Star picker plus optional title and text for one review kind. A kind
 * that's already reviewed shows the saved review instead.
 */
function renderReviewSection(string $kind, string $heading, string $hint, array $item, array $input, array $errors): void
{
    if ($item['has_' . $kind . '_review']) {
        $rating = (int) $item[$kind . '_rating'];
        ?>
        <fieldset class="auth-fieldset review-section review-section--done">
          <legend><?= htmlspecialchars($heading) ?></legend>
          <p class="review-saved">
            <span class="review-stars" aria-hidden="true"><?= reviewStars($rating) ?></span>
            <span>You rated this <?= $rating ?> out of 5.</span>
          </p>
          <?php if ($item[$kind . '_title'] !== null): ?>
            <p class="review-saved__title"><?= htmlspecialchars($item[$kind . '_title']) ?></p>
          <?php endif; ?>
          <?php if ($item[$kind . '_description'] !== null): ?>
            <p class="review-saved__text"><?= nl2br(htmlspecialchars($item[$kind . '_description'])) ?></p>
          <?php endif; ?>
        </fieldset>
        <?php
        return;
    }

    $ratingError = $errors[$kind . '_rating'] ?? null;
    ?>
    <fieldset class="auth-fieldset review-section">
      <legend><?= htmlspecialchars($heading) ?></legend>
      <p class="auth-field__hint"><?= htmlspecialchars($hint) ?></p>

      <div class="review-rating" role="radiogroup" aria-label="<?= htmlspecialchars($heading) ?> rating"
        <?= $ratingError !== null ? 'aria-describedby="' . $kind . '_rating-error"' : '' ?>>
        <?php for ($star = 5; $star >= 1; $star--): ?>
          <input type="radio" id="<?= $kind ?>_rating_<?= $star ?>" name="<?= $kind ?>_rating" value="<?= $star ?>"
            <?= $input[$kind]['rating'] === $star ? 'checked' : '' ?>>
          <label for="<?= $kind ?>_rating_<?= $star ?>" title="<?= $star ?> star<?= $star === 1 ? '' : 's' ?>">
            <span aria-hidden="true">★</span>
            <span class="sr-only"><?= $star ?> star<?= $star === 1 ? '' : 's' ?></span>
          </label>
        <?php endfor; ?>
      </div>
      <?= fieldError($errors, $kind . '_rating') ?>

      <div class="auth-field">
        <label for="<?= $kind ?>_title">Title <span class="auth-field__optional">(optional)</span></label>
        <input type="text" id="<?= $kind ?>_title" name="<?= $kind ?>_title" maxlength="<?= REVIEW_TITLE_MAX_LENGTH ?>"
          value="<?= htmlspecialchars($input[$kind]['title']) ?>"<?= invalidAttributes($errors, $kind . '_title') ?>>
        <?= fieldError($errors, $kind . '_title') ?>
      </div>

      <div class="auth-field">
        <label for="<?= $kind ?>_description">Your review <span class="auth-field__optional">(optional)</span></label>
        <textarea id="<?= $kind ?>_description" name="<?= $kind ?>_description" rows="4"
          maxlength="<?= REVIEW_TEXT_MAX_LENGTH ?>"<?= invalidAttributes($errors, $kind . '_description') ?>><?= htmlspecialchars($input[$kind]['description']) ?></textarea>
        <?= fieldError($errors, $kind . '_description') ?>
      </div>
    </fieldset>
    <?php
}

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/auth.css">
<link rel="stylesheet" href="/assets/css/reviews.css">

<section class="shell auth-page">
  <div class="auth-card review-card">
    <h1>Write a review</h1>
    <p class="auth-card__subtitle"><a href="/pages/orders.php">Back to My Orders</a></p>

    <?php if ($item === null): ?>
      <p class="auth-alert auth-alert--error" role="alert">That purchase could not be found.</p>
    <?php else: ?>
      <div class="review-item">
        <img alt="" width="64" height="64" src="<?= htmlspecialchars($item['image_url'] ?? ORDER_DEFAULT_IMAGE) ?>">
        <div>
          <a class="review-item__name" href="/pages/listing.php?id=<?= (int) $item['listing_id'] ?>"><?= htmlspecialchars($item['item_name']) ?></a>
          <p class="review-item__meta">Order #<?= (int) $item['order_id'] ?> · Sold by
            <a href="/pages/profile.php?id=<?= (int) $item['seller_user_id'] ?>"><?= htmlspecialchars($item['store_name'] ?? $item['seller_username']) ?></a>
          </p>
        </div>
      </div>

      <?php if (isset($errors['form'])): ?>
        <p class="auth-alert auth-alert--error" role="alert"><?= htmlspecialchars($errors['form']) ?></p>
      <?php elseif ($errors !== []): ?>
        <p class="auth-alert auth-alert--error" role="alert">Please fix the highlighted fields.</p>
      <?php endif; ?>

      <?php if ($problem !== null && !isset($errors['form'])): ?>
        <p class="auth-alert" role="status"><?= htmlspecialchars($problem) ?></p>
      <?php endif; ?>

      <?php if ($problem === null): ?>
        <form class="auth-form" method="post" action="/pages/review.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
          <input type="hidden" name="order_item_id" value="<?= (int) $item['order_item_id'] ?>">

          <?php renderReviewSection('listing', 'Rate the item', 'Shown on the listing page.', $item, $input, $errors); ?>
          <?php renderReviewSection('seller', 'Rate the seller', 'Shown on the seller\'s profile. Skip it if you only want to rate the item.', $item, $input, $errors); ?>

          <button type="submit" class="button button--primary">Submit review</button>
        </form>
      <?php else: ?>
        <?php // Read-only: show only the reviews already saved. ?>
        <?php if ($item['has_listing_review']): ?>
          <?php renderReviewSection('listing', 'Your item review', '', $item, $input, $errors); ?>
        <?php endif; ?>
        <?php if ($item['has_seller_review']): ?>
          <?php renderReviewSection('seller', 'Your seller review', '', $item, $input, $errors); ?>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
