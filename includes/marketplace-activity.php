<?php

declare(strict_types=1);

/**
 * OmniBuy marketplace activity tracking.
 *
 * Place this file in includes/marketplace-activity.php.
 * The calling page must provide its existing PDO connection and the
 * authenticated user's ID. This file does not start a session.
 *
 * Both helpers return true only when a new activity row is inserted.
 * Returning false means the event was skipped, deduplicated, or could not
 * be saved. Tracking errors are logged and must not interrupt browsing.
 */

/** Normalize spaces while keeping the submitted search's capitalization. */
function normalizeMarketplaceSearchQuery(string $searchQuery): string
{
    $normalized = preg_replace('/\s+/u', ' ', trim($searchQuery));

    return $normalized ?? '';
}

/**
 * Record an authenticated user's item search, at most once per five minutes
 * for the same normalized query. Caller must only invoke this after a valid,
 * successful item search (not after pagination or a filter/sort change).
 */
function recordSearchActivity(
    PDO $pdo,
    int $userId,
    string $searchQuery
): bool {
    if ($userId <= 0) {
        return false;
    }

    $query = normalizeMarketplaceSearchQuery($searchQuery);

    // The schema uses search_history.search_query VARCHAR(255).
    // A byte limit is deliberately conservative for UTF-8 text.
    if ($query === '' || strlen($query) > 255) {
        return false;
    }

    try {
        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO search_history (
                user_id,
                search_query,
                search_type
            )
            SELECT :new_user_id, :new_query, 'items'
            WHERE NOT EXISTS (
                SELECT 1
                FROM search_history AS previous
                WHERE previous.user_id = :previous_user_id
                  AND previous.search_type = 'items'
                  AND LOWER(previous.search_query) = LOWER(:previous_query)
                  AND previous.searched_at >= NOW() - INTERVAL 5 MINUTE
            )
        SQL);

        $statement->bindValue(':new_user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':new_query', $query, PDO::PARAM_STR);
        $statement->bindValue(':previous_user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':previous_query', $query, PDO::PARAM_STR);
        $statement->execute();

        return $statement->rowCount() === 1;
    } catch (PDOException $exception) {
        error_log('OmniBuy item-search activity tracking failed: ' . $exception->getMessage());

        return false;
    }
}

/**
 * Record an authenticated user's view of an eligible listing, at most once
 * per fifteen minutes. Own-listing views, inactive listings, and listings
 * from non-active sellers are excluded at the database level.
 */
function recordListingView(
    PDO $pdo,
    int $userId,
    int $listingId
): bool {
    if ($userId <= 0 || $listingId <= 0) {
        return false;
    }

    try {
        $statement = $pdo->prepare(<<<'SQL'
            INSERT INTO listing_views (
                user_id,
                listing_id
            )
            SELECT :new_user_id, listing.listing_id
            FROM listings AS listing
            INNER JOIN users AS seller
                ON seller.user_id = listing.seller_user_id
            WHERE listing.listing_id = :requested_listing_id
              AND listing.seller_user_id <> :viewer_owner_check
              AND listing.listing_status = 'active'
              AND seller.account_status = 'active'
              AND NOT EXISTS (
                  SELECT 1
                  FROM listing_views AS previous
                  WHERE previous.user_id = :previous_user_id
                    AND previous.listing_id = :previous_listing_id
                    AND previous.viewed_at >= NOW() - INTERVAL 15 MINUTE
              )
        SQL);

        $statement->bindValue(':new_user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':requested_listing_id', $listingId, PDO::PARAM_INT);
        $statement->bindValue(':viewer_owner_check', $userId, PDO::PARAM_INT);
        $statement->bindValue(':previous_user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':previous_listing_id', $listingId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    } catch (PDOException $exception) {
        error_log('OmniBuy listing-view activity tracking failed: ' . $exception->getMessage());

        return false;
    }
}
