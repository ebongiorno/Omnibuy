<?php

// View User Profile: anyone can see a user's name, photo, city and
// listings. Phone and email are only shown to logged-in users.

require_once __DIR__ . '/../../includes/profile.php';
require_once __DIR__ . '/../../includes/marketplace-image.php';
require_once __DIR__ . '/../../includes/reviews.php';

startSession();

$userId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$profile = null;
$listings = ['active' => [], 'sold' => []];
$sellerReviews = ['review_count' => 0, 'average_rating' => null, 'reviews' => []];

if ($userId !== false && $userId !== null) {
    require __DIR__ . '/../../db_connect.php';

    $profile = loadPublicProfile($pdo, $userId, isLoggedIn());
    if ($profile !== null) {
        $listings = loadProfileListings($pdo, $userId);
        $sellerReviews = loadSellerReviews($pdo, $userId);
    }
}

if ($profile === null) {
    http_response_code(404);
}

$viewer = currentUser();
$isOwnProfile = $profile !== null && $viewer !== null && $viewer['user_id'] === $profile['user_id'];

$pageTitle = ($profile !== null ? $profile['display_name'] : 'User not found') . ' | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/listing-card.css">
<link rel="stylesheet" href="/assets/css/profile.css">
<link rel="stylesheet" href="/assets/css/reviews.css">

<section class="shell profile-page">
  <?php if ($profile === null): ?>
    <div class="profile-missing">
      <h1>User not found</h1>
      <p>This profile doesn't exist or is no longer available.</p>
      <a class="button button--primary" href="/pages/search.php">Browse listings</a>
    </div>
  <?php else: ?>
    <header class="profile-card">
      <?php
      $profileAvatarUrl = marketplaceImageUrl(
        $profile['profile_image_url'] ?? null,
        '/assets/icons/default-avatar.png'
      );
      ?>

      <img
        class="profile-card__avatar"
        alt=""
        src="<?= htmlspecialchars(
            $profileAvatarUrl,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        ) ?>"
        data-profile-avatar
      >

      <div class="profile-card__body">
        <h1 class="profile-card__name"><?= htmlspecialchars($profile['display_name']) ?></h1>
        <p class="profile-card__username">@<?= htmlspecialchars($profile['username']) ?></p>

        <?php if ($profile['store_name'] !== null): ?>
          <p class="profile-card__store">
            <?= htmlspecialchars($profile['store_name']) ?>
            <?php if ($profile['is_verified']): ?>
              <span class="profile-card__verified">Verified seller</span>
            <?php endif; ?>
          </p>
        <?php endif; ?>

        <ul class="profile-facts">
          <?php if ($profile['city'] !== null): ?>
            <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= htmlspecialchars($profile['city']) ?></li>
          <?php endif; ?>
          <li>
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            Member since <?= htmlspecialchars(date('F Y', strtotime($profile['created_at']))) ?>
          </li>
        </ul>

        <?php if ($profile['bio'] !== null): ?>
          <p class="profile-card__bio"><?= htmlspecialchars($profile['bio']) ?></p>
        <?php endif; ?>

        <?php if ($isOwnProfile): ?>
          <?php if ($profile['hidden'] !== []): ?>
            <p class="profile-card__privacy">
              <i class="fa-solid fa-eye-slash" aria-hidden="true"></i>
              Hidden from other users: <?= htmlspecialchars(implode(', ', array_map(
                  static fn (string $setting): string => PROFILE_PRIVACY_LABELS[$setting],
                  $profile['hidden']
              ))) ?>.
              <a href="/pages/privacy-settings.php">Change</a>
            </p>
          <?php endif; ?>
          <a class="button profile-card__edit" href="/pages/edit-profile.php">Edit profile</a>
        <?php endif; ?>
      </div>

      <aside class="profile-contact" aria-label="Contact">
        <h2>Contact</h2>
        <?php if ($profile['contact_hidden']): ?>
          <p class="profile-contact__locked">This user keeps their contact details private.</p>
        <?php elseif ($viewer === null): ?>
          <p class="profile-contact__locked">
            <a href="/pages/login.php?redirect=<?= urlencode('/pages/profile.php?id=' . $profile['user_id']) ?>">Log in</a>
            to see this user's contact details.
          </p>
        <?php elseif ($profile['email'] === null && $profile['phone_number'] === null): ?>
          <p class="profile-contact__locked">This user keeps their contact details private.</p>
        <?php else: ?>
          <dl>
            <?php if ($profile['email'] !== null): ?>
              <dt>Email</dt>
              <dd><a href="mailto:<?= htmlspecialchars($profile['email']) ?>"><?= htmlspecialchars($profile['email']) ?></a></dd>
            <?php endif; ?>
            <?php if ($profile['phone_number'] !== null): ?>
              <dt>Phone</dt>
              <dd><a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $profile['phone_number'])) ?>"><?= htmlspecialchars($profile['phone_number']) ?></a></dd>
            <?php endif; ?>
          </dl>
        <?php endif; ?>
      </aside>
    </header>

    <section class="profile-listings" aria-labelledby="active-heading">
      <h2 id="active-heading">Listings <span class="profile-listings__count"><?= count($listings['active']) ?></span></h2>
      <?php if ($listings['active'] === []): ?>
        <p class="profile-listings__empty">
          <?= $isOwnProfile ? "You don't have any active listings." : 'No active listings right now.' ?>
        </p>
      <?php else: ?>
        <div class="listing-grid">
          <?php foreach ($listings['active'] as $listing): ?>
            <?php include __DIR__ . '/../components/listing-card-component.php'; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php if ($listings['sold'] !== []): ?>
      <section class="profile-listings profile-listings--sold" aria-labelledby="sold-heading">
        <h2 id="sold-heading">Sold <span class="profile-listings__count"><?= count($listings['sold']) ?></span></h2>
        <div class="listing-grid">
          <?php foreach ($listings['sold'] as $listing): ?>
            <?php include __DIR__ . '/../components/listing-card-component.php'; ?>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php // Every account can sell, so every profile shows the reviews it received as a seller. ?>
      <section class="profile-reviews" aria-labelledby="reviews-heading">
        <h2 id="reviews-heading">Reviews <span class="profile-listings__count"><?= $sellerReviews['review_count'] ?></span></h2>

        <?php if ($sellerReviews['review_count'] === 0): ?>
          <p class="profile-listings__empty">No reviews yet.</p>
        <?php else: ?>
          <p class="profile-reviews__summary">
            <span class="review-stars" aria-hidden="true"><?= reviewStars((int) round($sellerReviews['average_rating'])) ?></span>
            <strong><?= number_format($sellerReviews['average_rating'], 1) ?> out of 5</strong>
            from <?= $sellerReviews['review_count'] ?> review<?= $sellerReviews['review_count'] === 1 ? '' : 's' ?>
          </p>

          <ol class="profile-reviews__list">
            <?php foreach ($sellerReviews['reviews'] as $review): ?>
              <li class="profile-review">
                <article aria-label="Review by <?= htmlspecialchars($review['reviewer_username'] ?? 'OmniBuy member') ?>">
                  <div class="profile-review__meta">
                    <span>
                      <span class="profile-review__author"><?= htmlspecialchars($review['reviewer_username'] ?? 'OmniBuy member') ?></span>
                      <span class="review-stars" aria-hidden="true"><?= reviewStars((int) $review['star_rating']) ?></span>
                      <span class="sr-only"><?= (int) $review['star_rating'] ?> out of 5 stars</span>
                    </span>
                    <time datetime="<?= htmlspecialchars(date('Y-m-d', strtotime($review['created_at']))) ?>">
                      <?= htmlspecialchars(date('M j, Y', strtotime($review['created_at']))) ?>
                    </time>
                  </div>
                  <?php if ($review['title'] !== null): ?>
                    <h3 class="profile-review__title"><?= htmlspecialchars($review['title']) ?></h3>
                  <?php endif; ?>
                  <?php if ($review['description'] !== null): ?>
                    <p class="profile-review__text"><?= nl2br(htmlspecialchars($review['description'])) ?></p>
                  <?php endif; ?>
                  <p class="profile-review__item">Bought <a href="/pages/listing.php?id=<?= (int) $review['listing_id'] ?>"><?= htmlspecialchars($review['item_name']) ?></a></p>
                </article>
              </li>
            <?php endforeach; ?>
          </ol>
          <?php if ($sellerReviews['review_count'] > count($sellerReviews['reviews'])): ?>
            <p class="profile-reviews__summary">Showing the <?= count($sellerReviews['reviews']) ?> most recent reviews.</p>
          <?php endif; ?>
        <?php endif; ?>
      </section>
  <?php endif; ?>
</section>

<script src="/assets/js/profile-avatar-fallback.js" defer></script>

<?php include __DIR__ . '/../components/footer.php'; ?>
