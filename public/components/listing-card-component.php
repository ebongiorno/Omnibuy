<article class="listing-card">
    <a
        class="listing-card__image-link"
        href="/pages/listing.php?id=<?= (int) $listing['listing_id'] ?>"
    >
        <div class="listing-card__image">
            <img
                src="<?= htmlspecialchars($listing['image_url'] ?? '/assets/images/listing/listing-default-img.jpg', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($listing['item_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            >
        </div>
    </a>

    <div class="listing-card__body">
        <span class="listing-card__condition">
            <?= htmlspecialchars(
                ucwords(str_replace('_', ' ', $listing['condition'])),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            ) ?>
        </span>

        <h3 class="listing-card__title">
            <a href="/pages/listing.php?id=<?= (int) $listing['listing_id'] ?>">
                <?= htmlspecialchars($listing['item_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </a>
        </h3>

        <p class="listing-card__price">
            $<?= number_format((float) $listing['price'], 2) ?>
        </p>

        <div class="listing-card__meta">
            <span>
                <?= htmlspecialchars($listing['category_name'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </span>

            <span>
                <?= htmlspecialchars(
                    ucwords(str_replace('_', ' ', $listing['fulfillment_type'])),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                ) ?>
            </span>
        </div>
    </div>
</article>
