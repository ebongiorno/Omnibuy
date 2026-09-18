

<?php foreach ($listings as $listing): ?>
    <article class="listing-card">
        <a
            class="listing-card__image-link"
            href="listing.php?id=<?= $listing['listing_id'] ?>"
        >
            <img
                src="<?= htmlspecialchars($listing['image_url'] ?? 'assets/listing-default-img.jpg') ?>"
            >
        </a>

        <div class="listing-card__body">
            <span class="listing-card__condition">
                <?= htmlspecialchars($listing['condition']) ?>
            </span>

            <h3 class="listing-card__title">
                <a href="listing.php?id=<?= $listing['listing_id'] ?>">
                    <?= htmlspecialchars($listing['item_name']) ?>
                </a>
            </h3>

            <p class="listing-card__price">
                $<?= number_format($listing['price'], 2) ?>
            </p>

            <div class="listing-card__meta">
                <span>
                    <?= htmlspecialchars($listing['category_name']) ?>
                </span>

                <span>
                    <?= htmlspecialchars($listing['fulfillment_type']) ?>
                </span>
            </div>
        </div>
    </article>
<?php endforeach; ?>