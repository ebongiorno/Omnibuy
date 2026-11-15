<?php

declare(strict_types=1);

/**
 * OmniBuy category-based homepage recommendations (UC-16).
 *
 * Inputs: existing PDO connection, authenticated user ID (or null for guests),
 * maximum number of cards, and homepage sorting option.
 *
 * Returns the same listing fields expected by the homepage listing-card
 * component. This file does not start sessions or open a database connection.
 *
 * Recommendation policy:
 * - Last 90 days of activity (max 100 listing views / 50 item searches).
 * - +3 for each eligible listing view, +2 for each item search associated with
 *   a category. One search contributes at most once to any given category.
 * - Subcategory activity is grouped under its immediate parent category.
 * - Signed-in users with usable category-interest scores: select the highest-
 *   scoring eligible listings first, then sort that fixed set by date or price.
 * - Guests and signed-in users without usable category-interest scores: sort
 *   all eligible marketplace listings first, then return the top N.
 * - Active listings from active sellers only; exclude the viewer's listings.
 * - Unscored eligible listings fill remaining slots for signed-in users.
 * - On failure to read activity, still return a general eligible feed.
 */

/**
 * Calculate root-category interest points for the given user.
 *
 * @return array<int, int> root category ID => interest score
 */
function recommendationCategoryScores(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $scores = [];

    // A bounded, recent set of recorded views prevents old activity from
    // dominating the feed and keeps this request inexpensive.
    $viewsSql = <<<'SQL'
        SELECT COALESCE(category.parent_category_id, category.category_id) AS root_category_id,
               COUNT(*) * 3 AS points
        FROM (
            SELECT listing_id
            FROM listing_views
            WHERE user_id = :view_user_id
              AND viewed_at >= NOW() - INTERVAL 90 DAY
            ORDER BY viewed_at DESC, listing_view_id DESC
            LIMIT 100
        ) AS activity
        INNER JOIN listings AS listing
            ON listing.listing_id = activity.listing_id
        INNER JOIN users AS seller
            ON seller.user_id = listing.seller_user_id
        INNER JOIN categories AS category
            ON category.category_id = listing.category_id
        WHERE listing.listing_status = 'active'
          AND seller.account_status = 'active'
          AND listing.seller_user_id <> :view_exclude_owner
        GROUP BY root_category_id
    SQL;

    $viewStmt = $pdo->prepare($viewsSql);
    $viewStmt->bindValue(':view_user_id', $userId, PDO::PARAM_INT);
    $viewStmt->bindValue(':view_exclude_owner', $userId, PDO::PARAM_INT);
    $viewStmt->execute();

    foreach ($viewStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $scores[(int) $row['root_category_id']] = (int) $row['points'];
    }

    // Search history saves terms rather than categories. Associate each
    // recorded item search with the categories of eligible matching items.
    // DISTINCT ensures one search contributes at most +2 per root category,
    // regardless of how many matching listings that category contains.
    // The terms are escaped with the same '!' convention as search.php.
    // Explicit collations prevent MySQL 8 schema-column defaults and the
    // connection collation from conflicting in LIKE comparisons.
    $searchSql = <<<'SQL'
        SELECT match_categories.root_category_id,
               COUNT(*) * 2 AS points
        FROM (
            SELECT DISTINCT
                recent.search_history_id,
                COALESCE(category.parent_category_id, category.category_id) AS root_category_id
            FROM (
                SELECT search_history_id, search_query
                FROM search_history
                WHERE user_id = :search_user_id
                  AND search_type = 'items'
                  AND searched_at >= NOW() - INTERVAL 90 DAY
                ORDER BY searched_at DESC, search_history_id DESC
                LIMIT 50
            ) AS recent
            INNER JOIN listings AS listing
                ON listing.listing_status = 'active'
               AND listing.seller_user_id <> :search_exclude_owner
            INNER JOIN users AS seller
                ON seller.user_id = listing.seller_user_id
               AND seller.account_status = 'active'
            INNER JOIN categories AS category
                ON category.category_id = listing.category_id
            WHERE listing.item_name COLLATE utf8mb4_unicode_ci LIKE CONCAT('%',
                      REPLACE(REPLACE(REPLACE(recent.search_query, '!', '!!'), '%', '!%'), '_', '!_'),
                      '%') COLLATE utf8mb4_unicode_ci ESCAPE '!'
               OR listing.item_description COLLATE utf8mb4_unicode_ci LIKE CONCAT('%',
                      REPLACE(REPLACE(REPLACE(recent.search_query, '!', '!!'), '%', '!%'), '_', '!_'),
                      '%') COLLATE utf8mb4_unicode_ci ESCAPE '!'
               OR category.category_name COLLATE utf8mb4_unicode_ci LIKE CONCAT('%',
                      REPLACE(REPLACE(REPLACE(recent.search_query, '!', '!!'), '%', '!%'), '_', '!_'),
                      '%') COLLATE utf8mb4_unicode_ci ESCAPE '!'
        ) AS match_categories
        GROUP BY match_categories.root_category_id
    SQL;

    $searchStmt = $pdo->prepare($searchSql);
    $searchStmt->bindValue(':search_user_id', $userId, PDO::PARAM_INT);
    $searchStmt->bindValue(':search_exclude_owner', $userId, PDO::PARAM_INT);
    $searchStmt->execute();

    foreach ($searchStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $categoryId = (int) $row['root_category_id'];
        $scores[$categoryId] = ($scores[$categoryId] ?? 0) + (int) $row['points'];
    }

    return $scores;
}

/**
 * Return eligible homepage listing cards with category-based personalization.
 *
 * @return array<int, array<string, mixed>>
 */
function getPersonalizedRecommendations(
    PDO $pdo,
    ?int $userId,
    int $limit = 10,
    string $sort = 'recent'
): array {
    // Bound the caller-selected count; the normal homepage requests 10.
    $limit = max(1, min(50, $limit));
    if (!in_array($sort, ['recent', 'price_low', 'price_high'], true)) {
        $sort = 'recent';
    }

    $personalizedUserId = $userId !== null && $userId > 0 ? $userId : null;
    $scores = [];

    // Category scores are available only for authenticated users with recent,
    // eligible item-search or listing-view activity that maps to a category.
    // Merely being logged in must not lock the user into the newest ten.
    if ($personalizedUserId !== null) {
        try {
            $scores = recommendationCategoryScores($pdo, $personalizedUserId);
        } catch (PDOException $exception) {
            // Recommendation signals are optional: failure must not break
            // browsing. Still attempt to load a general eligible listing feed.
            error_log('OmniBuy recommendation activity lookup failed: ' . $exception->getMessage());
            $scores = [];
        }
    }

    // No usable interests means browse-style sorting, regardless of login.
    // An unmatched search, expired activity, or a failed activity lookup does
    // not provide enough category information to personalize the candidate set.
    $hasCategoryInterests = $personalizedUserId !== null && $scores !== [];

    // Dynamic CASE contains *only* parameter placeholders, never raw category
    // IDs or user input. A zero score supplies the newest-first fallback.
    // Select the score under a named alias: ORDER BY 0 would be interpreted
    // by MySQL as a column position and fail for users without history.
    $scoreExpression = '0';
    $scoreParams = [];
    if ($scores !== []) {
        $branches = [];
        $index = 0;
        foreach ($scores as $categoryId => $points) {
            $branches[] = "WHEN :interest_category_$index THEN :interest_points_$index";
            $scoreParams["interest_category_$index"] = (int) $categoryId;
            $scoreParams["interest_points_$index"] = $points;
            $index++;
        }
        $scoreExpression = 'CASE COALESCE(category.parent_category_id, category.category_id) '
            . implode(' ', $branches) . ' ELSE 0 END';
    }

    // Users with category interests: select the same top-N recommendation
    // candidates for every sort option. Guests and users without interests:
    // apply the selected sort to the full eligible catalog BEFORE LIMIT.
    $recencyOrder = 'COALESCE(listing.renewed_at, listing.created_at) DESC, listing.listing_id DESC';
    if ($hasCategoryInterests) {
        $orderBy = 'recommendation_score DESC, ' . $recencyOrder;
    } elseif ($sort === 'price_low') {
        $orderBy = 'listing.price ASC, ' . $recencyOrder;
    } elseif ($sort === 'price_high') {
        $orderBy = 'listing.price DESC, ' . $recencyOrder;
    } else {
        $orderBy = $recencyOrder;
    }

    $sql = "
        SELECT listing.listing_id,
               listing.`condition`,
               listing.item_name,
               listing.price,
               listing.category_id,
               listing.fulfillment_type,
               image.image_url,
               category.category_name,
               COALESCE(listing.renewed_at, listing.created_at) AS recommendation_recency,
               $scoreExpression AS recommendation_score
        FROM listings AS listing
        INNER JOIN users AS seller
            ON seller.user_id = listing.seller_user_id
        LEFT JOIN listing_images AS image
            ON image.listing_id = listing.listing_id
           AND image.display_order = 1
        LEFT JOIN categories AS category
            ON category.category_id = listing.category_id
        WHERE listing.listing_status = 'active'
          AND seller.account_status = 'active'
          " . ($personalizedUserId !== null ? 'AND listing.seller_user_id <> :viewer_user_id' : '') . "
        ORDER BY $orderBy
        LIMIT :feed_limit
    ";

    $statement = $pdo->prepare($sql);
    if ($personalizedUserId !== null) {
        $statement->bindValue(':viewer_user_id', $personalizedUserId, PDO::PARAM_INT);
    }
    foreach ($scoreParams as $name => $value) {
        $statement->bindValue(':' . $name, $value, PDO::PARAM_INT);
    }
    $statement->bindValue(':feed_limit', $limit, PDO::PARAM_INT);
    $statement->execute();

    $recommendations = $statement->fetchAll(PDO::FETCH_ASSOC);

    // General-feed results were already sorted by MySQL before LIMIT.
    // Only users with category interests reorder the fixed candidate set.
    if ($hasCategoryInterests) {
        usort($recommendations, static function (array $left, array $right) use ($sort): int {
            if ($sort === 'price_low' || $sort === 'price_high') {
                $comparison = (float) $left['price'] <=> (float) $right['price'];
                if ($sort === 'price_high') {
                    $comparison = -$comparison;
                }
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            // MySQL timestamps have lexicographically sortable ISO representations.
            $comparison = strcmp(
                (string) $right['recommendation_recency'],
                (string) $left['recommendation_recency']
            );
            if ($comparison !== 0) {
                return $comparison;
            }

            return (int) $right['listing_id'] <=> (int) $left['listing_id'];
        });
    }

    // Keep the returned shape compatible with the existing homepage cards.
    foreach ($recommendations as &$listing) {
        unset($listing['recommendation_recency']);
    }
    unset($listing);

    return $recommendations;
}
