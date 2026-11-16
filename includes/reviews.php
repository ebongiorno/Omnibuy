<?php

/**
 * OmniBuy reviews: after a purchase, the buyer rates the item and the
 * person who sold it. Every account can both buy and sell, so "buyer" and
 * "seller" here are only roles in one sale, not account types.
 *
 * Every review belongs to one order item the reviewer bought, so only real
 * buyers can review, and only once per purchase (the UNIQUE keys on
 * listing_reviews / user_reviews). Two kinds, kept separate on purpose:
 *   listing_reviews  rate the item; shown on the listing page
 *   user_reviews     rate the seller; shown on the seller's profile
 * Cancelled items can't be reviewed.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

const REVIEW_TITLE_MAX_LENGTH = 100;
const REVIEW_TEXT_MAX_LENGTH = 2000;
const REVIEW_KINDS = ['listing', 'seller'];

/**
 * One of the buyer's order items with what's needed to review it, or null
 * if it doesn't exist or belongs to someone else's order.
 */
function loadReviewableItem(PDO $pdo, int $buyerId, int $orderItemId): ?array
{
    $stmt = $pdo->prepare('
        SELECT
            oi.order_item_id, oi.order_id, oi.listing_id, oi.item_status,
            l.item_name, l.seller_user_id,
            sp.store_name, su.username AS seller_username,
            (
                SELECT li.image_url FROM listing_images li
                WHERE li.listing_id = oi.listing_id
                ORDER BY li.display_order LIMIT 1
            ) AS image_url,
            lr.star_rating AS listing_rating, lr.title AS listing_title, lr.description AS listing_description,
            ur.star_rating AS seller_rating, ur.title AS seller_title, ur.description AS seller_description
        FROM order_items oi
        JOIN orders o ON o.order_id = oi.order_id
        JOIN listings l ON l.listing_id = oi.listing_id
        JOIN users su ON su.user_id = l.seller_user_id
        LEFT JOIN seller_profiles sp ON sp.user_id = l.seller_user_id
        LEFT JOIN listing_reviews lr
            ON lr.order_item_id = oi.order_item_id AND lr.reviewer_user_id = o.buyer_user_id
        LEFT JOIN user_reviews ur
            ON ur.order_item_id = oi.order_item_id AND ur.reviewer_user_id = o.buyer_user_id
        WHERE oi.order_item_id = :order_item_id AND o.buyer_user_id = :buyer_id
    ');
    $stmt->execute(['order_item_id' => $orderItemId, 'buyer_id' => $buyerId]);
    $item = $stmt->fetch();

    if ($item === false) {
        return null;
    }

    $item['has_listing_review'] = $item['listing_rating'] !== null;
    $item['has_seller_review'] = $item['seller_rating'] !== null;

    return $item;
}

/**
 * Why this item can't be reviewed right now, or null if it can.
 */
function reviewProblem(array $item, int $buyerId): ?string
{
    if ($item['item_status'] === 'cancelled') {
        return 'Cancelled items can\'t be reviewed.';
    }
    if ((int) $item['seller_user_id'] === $buyerId) {
        return 'You can\'t review your own listing.';
    }
    if ($item['has_listing_review'] && $item['has_seller_review']) {
        return 'You\'ve already reviewed this purchase.';
    }

    return null;
}

/**
 * The submitted review for one kind ('listing' or 'seller'), trimmed.
 * The rating is null when no star was chosen.
 *
 * @return array{rating: ?int, title: string, description: string}
 */
function normalizeReviewInput(array $post, string $kind): array
{
    $text = static function (string $name) use ($post): string {
        $value = $post[$name] ?? '';
        return is_string($value) ? trim($value) : '';
    };

    $rating = filter_var(
        $post[$kind . '_rating'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 5]]
    );

    return [
        'rating' => $rating === false ? null : $rating,
        'title' => $text($kind . '_title'),
        'description' => $text($kind . '_description'),
    ];
}

/**
 * @return array<string, string> "<kind>_<field>" => error message
 */
function validateReview(array $review, string $kind): array
{
    $errors = [];
    $ratingGiven = $review['rating'] !== null;
    $wroteSomething = $review['title'] !== '' || $review['description'] !== '';

    if (!$ratingGiven && $wroteSomething) {
        $errors[$kind . '_rating'] = 'Choose a star rating from 1 to 5.';
    }
    if (mb_strlen($review['title']) > REVIEW_TITLE_MAX_LENGTH) {
        $errors[$kind . '_title'] = 'Title must be ' . REVIEW_TITLE_MAX_LENGTH . ' characters or fewer.';
    }
    if (mb_strlen($review['description']) > REVIEW_TEXT_MAX_LENGTH) {
        $errors[$kind . '_description'] = 'Review must be ' . REVIEW_TEXT_MAX_LENGTH . ' characters or fewer.';
    }

    return $errors;
}

/**
 * Saves the buyer's item and/or seller review for one order item. Only
 * kinds not yet reviewed are saved; at least one rating is required.
 *
 * @param array<string, array{rating: ?int, title: string, description: string}> $reviews keyed by 'listing' / 'seller'
 * @return array{ok: bool, errors: array<string, string>}
 */
function submitReviews(PDO $pdo, int $buyerId, int $orderItemId, array $reviews): array
{
    $item = loadReviewableItem($pdo, $buyerId, $orderItemId);
    if ($item === null) {
        return ['ok' => false, 'errors' => ['form' => 'That purchase could not be found.']];
    }

    $problem = reviewProblem($item, $buyerId);
    if ($problem !== null) {
        return ['ok' => false, 'errors' => ['form' => $problem]];
    }

    // Ignore anything sent for a kind that's already reviewed.
    $open = array_filter(
        REVIEW_KINDS,
        static fn (string $kind): bool => !$item['has_' . $kind . '_review']
    );

    $errors = [];
    $toSave = [];
    foreach ($open as $kind) {
        $review = $reviews[$kind] ?? ['rating' => null, 'title' => '', 'description' => ''];
        $errors += validateReview($review, $kind);
        if ($review['rating'] !== null) {
            $toSave[$kind] = $review;
        }
    }

    if ($errors === [] && $toSave === []) {
        $errors['form'] = 'Choose a star rating to leave a review.';
    }
    if ($errors !== []) {
        return ['ok' => false, 'errors' => $errors];
    }

    $nullable = static fn (string $value): ?string => $value === '' ? null : $value;

    try {
        if (isset($toSave['listing'])) {
            $pdo->prepare('
                INSERT INTO listing_reviews (reviewer_user_id, order_item_id, star_rating, title, description)
                VALUES (:reviewer, :order_item_id, :rating, :title, :description)
            ')->execute([
                'reviewer' => $buyerId,
                'order_item_id' => $orderItemId,
                'rating' => $toSave['listing']['rating'],
                'title' => $nullable($toSave['listing']['title']),
                'description' => $nullable($toSave['listing']['description']),
            ]);
        }

        if (isset($toSave['seller'])) {
            $pdo->prepare('
                INSERT INTO user_reviews (
                    reviewer_user_id, reviewed_user_id, order_item_id, star_rating, title, description
                ) VALUES (
                    :reviewer, :reviewed, :order_item_id, :rating, :title, :description
                )
            ')->execute([
                'reviewer' => $buyerId,
                'reviewed' => $item['seller_user_id'],
                'order_item_id' => $orderItemId,
                'rating' => $toSave['seller']['rating'],
                'title' => $nullable($toSave['seller']['title']),
                'description' => $nullable($toSave['seller']['description']),
            ]);
        }
    } catch (PDOException $e) {
        // A double submit raced past the check above: the UNIQUE key caught it.
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'errors' => ['form' => 'You\'ve already reviewed this purchase.']];
        }
        throw $e;
    }

    return ['ok' => true, 'errors' => []];
}

/**
 * A seller's average rating and most recent reviews, for their profile.
 * Reviewers whose accounts are no longer active are shown anonymously.
 *
 * @return array{review_count: int, average_rating: ?float, reviews: array}
 */
function loadSellerReviews(PDO $pdo, int $sellerId, int $limit = 10): array
{
    $summary = $pdo->prepare('
        SELECT COUNT(*) AS review_count, ROUND(AVG(star_rating), 1) AS average_rating
        FROM user_reviews
        WHERE reviewed_user_id = :seller_id
    ');
    $summary->execute(['seller_id' => $sellerId]);
    $row = $summary->fetch();

    $reviews = $pdo->prepare('
        SELECT
            ur.user_review_id, ur.star_rating, ur.title, ur.description, ur.created_at,
            CASE WHEN u.account_status = \'active\' THEN u.username ELSE NULL END AS reviewer_username,
            l.item_name, l.listing_id
        FROM user_reviews ur
        JOIN users u ON u.user_id = ur.reviewer_user_id
        JOIN order_items oi ON oi.order_item_id = ur.order_item_id
        JOIN listings l ON l.listing_id = oi.listing_id
        WHERE ur.reviewed_user_id = :seller_id
        ORDER BY ur.created_at DESC, ur.user_review_id DESC
        LIMIT ' . max(1, $limit)
    );
    $reviews->execute(['seller_id' => $sellerId]);

    return [
        'review_count' => (int) $row['review_count'],
        'average_rating' => $row['average_rating'] === null ? null : (float) $row['average_rating'],
        'reviews' => $reviews->fetchAll(),
    ];
}

/**
 * "★★★★☆" for a 1-5 rating (decorative; pair it with a text label).
 */
function reviewStars(int $rating): string
{
    $rating = max(0, min(5, $rating));

    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}
