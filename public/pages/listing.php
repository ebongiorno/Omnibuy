<?php

declare(strict_types=1);

// UC-25, Phase 3: listing details, multi-image gallery, and purchase interface.
// Install as public/pages/listing.php.
require_once __DIR__ . '/../../includes/auth.php';
startSession();

/** Escape text and attribute values from the database before rendering HTML. */
function listingEscape(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Normalize allowed listing/profile image URLs and safely fall back for bad values. */
function listingImageUrl(mixed $value, string $fallback): string
{
    if (!is_string($value)) {
        return $fallback;
    }

    $url = trim($value);
    if ($url === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $url)) {
        return $fallback;
    }

    // Permit local application paths and ordinary HTTPS/HTTP image URLs only.
    // Reject protocol-relative URLs (//host) and dangerous schemes.
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        return $url;
    }
    if (str_starts_with($url, 'assets/') || str_starts_with($url, 'uploads/')) {
        return '/' . $url;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) !== false &&
        in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['https', 'http'], true)) {
        return $url;
    }

    return $fallback;
}

function listingHumanLabel(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

$listing = null;
$images = [];
$pageError = null;

// The query parameter must be a scalar positive integer.
$rawId = $_GET['id'] ?? null;
$listingId = is_string($rawId)
    ? filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

if ($listingId === false) {
    http_response_code(400);
    $pageError = 'Invalid listing ID.';
} else {
    require_once __DIR__ . '/../../db_connect.php';

    try {
        // Public fields only: do not query private contact information or coordinates.
        $statement = $pdo->prepare("
            SELECT
                l.listing_id,
                l.seller_user_id,
                l.category_id,
                l.item_name,
                l.item_description,
                l.sale_type,
                l.fulfillment_type,
                l.shipping_cost,
                l.estimated_shipping_days,
                l.location_zip_code,
                l.meetup_location_type,
                l.seller_meetup_radius,
                l.auction_end_at,
                l.price,
                l.`condition`,
                l.quantity,
                l.created_at,
                c.category_name,
                c.parent_category_id,
                sp.store_name,
                sp.is_verified AS seller_is_verified,
                u.username AS seller_username,
                u.profile_image_url AS seller_image_url,
                u.first_name AS seller_first_name,
                CASE WHEN u.is_last_name_hidden = FALSE
                    THEN u.last_name ELSE NULL END AS seller_last_name
            FROM listings AS l
            INNER JOIN seller_profiles AS sp
                ON sp.user_id = l.seller_user_id
            INNER JOIN users AS u
                ON u.user_id = sp.user_id
            LEFT JOIN categories AS c
                ON c.category_id = l.category_id
            WHERE l.listing_id = :listing_id
              AND l.listing_status = 'active'
              AND u.account_status = 'active'
            LIMIT 1
        ");
        $statement->execute(['listing_id' => $listingId]);
        $listing = $statement->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($listing === null) {
            http_response_code(404);
            $pageError = 'This listing is not available.';
        } else {
            // Multiple images are retrieved independently of the main listing.
            $imageStatement = $pdo->prepare("
                SELECT listing_image_id, image_url, display_order
                FROM listing_images
                WHERE listing_id = :listing_id
                ORDER BY display_order ASC, listing_image_id ASC
            ");
            $imageStatement->execute(['listing_id' => $listingId]);
            $images = $imageStatement->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $exception) {
        error_log('Listing detail retrieval failed: ' . $exception->getMessage());
        http_response_code(503);
        $pageError = 'Unable to load this listing right now. Please try again later.';
        $listing = null;
        $images = [];
    }
}

$defaultImage = '/assets/images/listing/listing-default-img.jpg';
$hasListingPhotos = count($images) > 0;
$galleryImages = [];

foreach ($images as $image) {
    $galleryImages[] = listingImageUrl($image['image_url'] ?? null, $defaultImage);
}

if (!$galleryImages) {
    $galleryImages[] = $defaultImage;
}

$imageCount = count($galleryImages);
$primaryImage = $galleryImages[0];
$pageTitle = $listing !== null ? $listing['item_name'] . ' | OmniBuy' : 'Listing | OmniBuy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= listingEscape($pageTitle) ?></title>

    <link rel="stylesheet" href="/assets/css/omnibuy-design-tokens.css">
    <link rel="stylesheet" href="/assets/css/shared-navigation.css">
    <link rel="stylesheet" href="/assets/css/listing-detail.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?php include __DIR__ . '/../components/header.php'; ?>

    <main id="main-content" class="page-main listing-detail-page">
        <div class="shell">
            <?php if ($pageError !== null): ?>
                <div class="listing-detail__not-found" role="status">
                    <p class="listing-detail__eyebrow">Marketplace</p>
                    <h1>Listing unavailable</h1>
                    <p><?= listingEscape($pageError) ?></p>
                    <a class="listing-detail__back-button" href="/pages/search.php?browse=all&amp;search_type=items">Browse all listings</a>
                </div>
            <?php else: ?>
                <nav class="listing-detail__breadcrumb" aria-label="Breadcrumb">
                    <a href="/">Home</a>
                    <span aria-hidden="true">/</span>
                    <a href="/pages/search.php?browse=all&amp;search_type=items">Marketplace</a>
                    <span aria-hidden="true">/</span>
                    <a href="/pages/search.php?browse=all&amp;search_type=items&amp;category=<?= (int) $listing['category_id'] ?>">
                        <?= listingEscape($listing['category_name'] ?? 'Category') ?>
                    </a>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page" class="listing-detail__breadcrumb-current"><?= listingEscape($listing['item_name']) ?></span>
                </nav>

                <article class="listing-detail" aria-labelledby="listing-detail-title">
                    <div class="listing-detail__hero">
                        <section class="listing-gallery" aria-label="Photos for <?= listingEscape($listing['item_name']) ?>" data-listing-gallery data-fallback-src="<?= listingEscape($defaultImage) ?>">
                            <div class="listing-gallery__stage">
                                <img
                                    class="listing-gallery__main-image"
                                    data-gallery-main
                                    src="<?= listingEscape($primaryImage) ?>"
                                    alt="<?= listingEscape($listing['item_name']) ?> — image 1 of <?= $imageCount ?>"
                                    width="700"
                                    height="700"
                                    fetchpriority="high"
                                >
                                <?php if ($imageCount > 1): ?>
                                    <div class="listing-gallery__controls" data-gallery-controls hidden>
                                        <button type="button" class="listing-gallery__arrow" data-gallery-prev aria-label="Previous listing image">
                                            <span aria-hidden="true">&#8249;</span>
                                        </button>
                                        <span class="listing-gallery__counter" data-gallery-counter aria-live="polite">1 / <?= $imageCount ?></span>
                                        <button type="button" class="listing-gallery__arrow" data-gallery-next aria-label="Next listing image">
                                            <span aria-hidden="true">&#8250;</span>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($imageCount > 1): ?>
                                <div class="listing-gallery__thumbnails" aria-label="Choose listing image">
                                    <?php foreach ($galleryImages as $index => $imageUrl): ?>
                                        <a
                                            class="listing-gallery__thumbnail<?= $index === 0 ? ' is-active' : '' ?>"
                                            href="<?= listingEscape($imageUrl) ?>"
                                            data-gallery-thumbnail
                                            aria-label="View image <?= $index + 1 ?> of <?= $imageCount ?>"
                                            <?= $index === 0 ? 'aria-current="true"' : '' ?>
                                        >
                                            <img
                                                src="<?= listingEscape($imageUrl) ?>"
                                                alt=""
                                                loading="lazy"
                                                width="100"
                                                height="100"
                                            >
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif (!$hasListingPhotos): ?>
                                <p class="listing-gallery__note">No photos have been uploaded for this listing.</p>
                            <?php endif; ?>
                        </section>

                        <div class="listing-detail__summary">
                            <p class="listing-detail__eyebrow"><?= listingEscape($listing['category_name'] ?? 'Marketplace') ?></p>
                            <h1 id="listing-detail-title"><?= listingEscape($listing['item_name']) ?></h1>
                            <p class="listing-detail__price-label"><?= $listing['sale_type'] === 'auction' ? 'Listed price' : 'Price' ?></p>
                            <p class="listing-detail__price">$<?= number_format((float) $listing['price'], 2) ?></p>

                            <div class="listing-detail__chips">
                                <span class="listing-detail__chip"><?= listingEscape(listingHumanLabel((string) $listing['condition'])) ?></span>
                                <span class="listing-detail__chip listing-detail__chip--accent"><?= $listing['fulfillment_type'] === 'shipping' ? 'Shipping' : 'Local meetup' ?></span>
                                <?php if ($listing['sale_type'] === 'auction'): ?>
                                    <span class="listing-detail__chip">Auction listing</span>
                                <?php endif; ?>
                            </div>

                            <div class="listing-detail__stock" aria-label="Availability">
                                <?php if ((int) $listing['quantity'] > 0): ?>
                                    <span class="listing-detail__stock-dot" aria-hidden="true"></span>
                                    <?= (int) $listing['quantity'] ?> available
                                <?php else: ?>
                                    <span class="listing-detail__stock-dot listing-detail__stock-dot--empty" aria-hidden="true"></span>
                                    Currently out of stock
                                <?php endif; ?>
                            </div>

                            <section class="listing-detail__delivery" aria-labelledby="listing-delivery-heading">
                                <h2 id="listing-delivery-heading">Delivery details</h2>
                                <?php if ($listing['fulfillment_type'] === 'shipping'): ?>
                                    <dl class="listing-detail__specs">
                                        <div><dt>Method</dt><dd>Shipping</dd></div>
                                        <div><dt>Shipping cost</dt><dd>
                                            <?php if ($listing['shipping_cost'] === null): ?>
                                                Not specified
                                            <?php elseif ((float) $listing['shipping_cost'] === 0.0): ?>
                                                Free
                                            <?php else: ?>
                                                $<?= number_format((float) $listing['shipping_cost'], 2) ?>
                                            <?php endif; ?>
                                        </dd></div>
                                        <?php if ($listing['estimated_shipping_days'] !== null): ?>
                                            <div><dt>Estimated time</dt><dd><?= (int) $listing['estimated_shipping_days'] ?> day(s)</dd></div>
                                        <?php endif; ?>
                                    </dl>
                                <?php else: ?>
                                    <dl class="listing-detail__specs">
                                        <div><dt>Method</dt><dd>Local meetup</dd></div>
                                        <?php if (!empty($listing['location_zip_code'])): ?>
                                            <div><dt>ZIP code area</dt><dd><?= listingEscape($listing['location_zip_code']) ?></dd></div>
                                        <?php endif; ?>
                                        <?php if (!empty($listing['meetup_location_type'])): ?>
                                            <div><dt>Preferred meeting place</dt><dd><?= listingEscape(listingHumanLabel($listing['meetup_location_type'])) ?></dd></div>
                                        <?php endif; ?>
                                    </dl>
                                <?php endif; ?>
                            </section>

                            <!-- Phase 3: presentation and local quantity selection only.
                                 Cart/Wishlist requests, authentication, and inventory enforcement
                                 belong to the teammates implementing those features. -->
                            <section class="listing-detail__purchase" aria-labelledby="listing-purchase-heading">
                                <h2 id="listing-purchase-heading">Purchase options</h2>

                                <?php if ($listing['sale_type'] === 'fixed_price' && (int) $listing['quantity'] > 0): ?>
                                    <div class="listing-detail__quantity-row" data-quantity-selector data-max-quantity="<?= (int) $listing['quantity'] ?>">
                                        <label class="listing-detail__quantity-label" for="listing-quantity">Quantity</label>
                                        <div class="listing-detail__quantity-stepper">
                                            <button type="button" class="listing-detail__quantity-button" data-quantity-decrease aria-label="Decrease quantity" disabled>&minus;</button>
                                            <input
                                                type="number"
                                                id="listing-quantity"
                                                class="listing-detail__quantity-input"
                                                data-quantity-value
                                                value="1"
                                                min="1"
                                                max="<?= (int) $listing['quantity'] ?>"
                                                step="1"
                                                inputmode="numeric"
                                                autocomplete="off"
                                                aria-label="Selected quantity"
                                            >
                                            <button type="button" class="listing-detail__quantity-button" data-quantity-increase aria-label="Increase quantity" <?= (int) $listing['quantity'] === 1 ? 'disabled' : '' ?>>+</button>
                                        </div>
                                        <span class="listing-detail__quantity-hint">Maximum <?= (int) $listing['quantity'] ?> available</span>
                                    </div>
                                <?php elseif ($listing['sale_type'] === 'auction'): ?>
                                    <p class="listing-detail__purchase-note">Auction listings are not available for direct cart purchase.</p>
                                <?php else: ?>
                                    <p class="listing-detail__purchase-note">This listing is currently out of stock.</p>
                                <?php endif; ?>

                                <div class="listing-detail__actions" data-listing-actions data-listing-id="<?= (int) $listing['listing_id'] ?>">
                                    <button
                                        type="button"
                                        class="listing-detail__action listing-detail__action--primary"
                                        data-add-to-cart
                                        data-selected-quantity="1"
                                        disabled
                                        title="Cart integration is pending"
                                    >Add to Cart</button>
                                    <button
                                        type="button"
                                        class="listing-detail__action listing-detail__action--secondary"
                                        data-add-to-wishlist
                                        disabled
                                        title="Wishlist integration is pending"
                                    ><span aria-hidden="true">&#9825;</span> Add to Wishlist</button>
                                </div>
                                <p class="listing-detail__integration-note" role="note">Cart and Wishlist buttons are visual placeholders. Your selection will not be saved or submitted until those features are connected by the team.</p>
                            </section>
                        </div>
                    </div>

                    <div class="listing-detail__lower">
                        <section class="listing-detail__panel" aria-labelledby="listing-description-heading">
                            <h2 id="listing-description-heading">About this item</h2>
                            <p class="listing-detail__description"><?= nl2br(listingEscape($listing['item_description'] ?: 'No description provided.')) ?></p>
                            <dl class="listing-detail__specs listing-detail__specs--extra">
                                <div><dt>Condition</dt><dd><?= listingEscape(listingHumanLabel((string) $listing['condition'])) ?></dd></div>
                                <div><dt>Category</dt><dd><?= listingEscape($listing['category_name'] ?? 'Uncategorized') ?></dd></div>
                                <div><dt>Sale type</dt><dd><?= $listing['sale_type'] === 'auction' ? 'Auction' : 'Fixed price' ?></dd></div>
                            </dl>
                        </section>

                        <section class="listing-detail__panel" aria-labelledby="listing-seller-heading">
                            <h2 id="listing-seller-heading">About the seller</h2>
                            <div class="listing-detail__seller">
                                <div class="listing-detail__seller-avatar" aria-hidden="true">
                                    <?php if (!empty($listing['seller_image_url'])): ?>
                                        <img src="<?= listingEscape(listingImageUrl($listing['seller_image_url'], $defaultImage)) ?>" alt="" loading="lazy" width="64" height="64">
                                    <?php else: ?>
                                        <?= listingEscape(strtoupper(substr((string) $listing['store_name'], 0, 1))) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="listing-detail__seller-info">
                                    <a class="listing-detail__seller-name" href="/pages/profile.php?id=<?= (int) $listing['seller_user_id'] ?>"><?= listingEscape($listing['store_name']) ?></a>
                                    <p>@<?= listingEscape($listing['seller_username']) ?></p>
                                    <?php if ((bool) $listing['seller_is_verified']): ?>
                                        <span class="listing-detail__verified">Verified seller</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a class="listing-detail__seller-link" href="/pages/profile.php?id=<?= (int) $listing['seller_user_id'] ?>">View seller profile <span aria-hidden="true">&rarr;</span></a>
                        </section>
                    </div>
                </article>
            <?php endif; ?>
        </div>
    </main>

    <script src="/assets/js/shared-navigation.js" defer></script>
    <script src="/assets/js/listing-gallery.js" defer></script>
    <script src="/assets/js/listing-purchase-ui.js" defer></script>
</body>
</html>
