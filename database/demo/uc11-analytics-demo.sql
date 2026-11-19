-- ============================================================
-- OmniBuy: UC-11 Listing Analytics Demonstration Data
-- ============================================================
--
-- Purpose:
-- Populate a demonstration listing with sample analytics
-- activity for the final OmniBuy presentation.
--
-- Usage:
-- Run manually in phpMyAdmin after initializing the
-- standard OmniBuy database and seed data.
--
-- Requirements:
-- 1. The shared demonstration user must exist.
-- 2. The demonstration listing must belong to that user.
-- 3. Other valid user records must be available.
--
-- Safety:
-- This script is intended for local demonstration databases.
-- It must not be executed against production data.
--
-- ============================================================

-- ============================================================
-- SECTION 1: IDENTIFY DEMONSTRATION RECORDS
-- ============================================================

-- Shared account used during the OmniBuy demonstration.
SET @demo_username = 'omnibuy_tester';

-- Listing used specifically for the UC-11 demonstration.
SET @demo_listing_name = 'Wireless Noise-Canceling Headphones';

-- Find the demonstration account's user ID.
SET @demo_owner_id = (
    SELECT user_id
    FROM users
    WHERE username = @demo_username
    LIMIT 1
);

-- Find the Audio subcategory under Electronics.
SET @demo_category_id = (
    SELECT c.category_id
    FROM categories AS c
    INNER JOIN categories AS parent
        ON c.parent_category_id = parent.category_id
    WHERE c.category_name = 'Audio'
      AND parent.category_name = 'Electronics'
    LIMIT 1
);

-- Find the demonstration listing if it already exists.
SET @demo_listing_id = (
    SELECT listing_id
    FROM listings
    WHERE seller_user_id = @demo_owner_id
      AND item_name = @demo_listing_name
    LIMIT 1
);

-- Verify the records identified by the script.
SELECT
    @demo_username AS demo_username,
    @demo_owner_id AS owner_id,
    @demo_category_id AS category_id,
    @demo_listing_name AS listing_name,
    @demo_listing_id AS listing_id,
    EXISTS (
        SELECT 1
        FROM seller_profiles
        WHERE user_id = @demo_owner_id
    ) AS has_seller_profile;


-- ============================================================
-- SECTION 2: CREATE DEMONSTRATION LISTING IF MISSING
-- ============================================================

-- Create the shared account's seller profile only if needed.
INSERT INTO seller_profiles (
    user_id,
    store_name,
    payout_account_reference
)
SELECT
    @demo_owner_id,
    'OmniBuy UC11 Demo Store',
    'UC11_DEMO_PAYOUT'
WHERE @demo_owner_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM seller_profiles
      WHERE user_id = @demo_owner_id
  );

-- Create the demonstration listing if missing.
INSERT INTO listings (
    seller_user_id,
    category_id,
    item_name,
    item_description,
    sale_type,
    fulfillment_type,
    shipping_cost,
    estimated_shipping_days,
    price,
    `condition`,
    quantity,
    listing_status
)
SELECT
    @demo_owner_id,
    @demo_category_id,
    @demo_listing_name,
    'Gently used wireless headphones in excellent condition. Includes charging cable and carrying case. Fully functional with no known issues.',
    'fixed_price',
    'shipping',
    0.00,
    4,
    85.00,
    'like_new',
    1,
    'active'
WHERE @demo_owner_id IS NOT NULL
  AND @demo_category_id IS NOT NULL
  AND EXISTS (
      SELECT 1
      FROM seller_profiles
      WHERE user_id = @demo_owner_id
  )
  AND NOT EXISTS (
      SELECT 1
      FROM listings
      WHERE seller_user_id = @demo_owner_id
        AND item_name = @demo_listing_name
  );

-- Retrieve the demonstration listing ID.
SET @demo_listing_id = (
    SELECT listing_id
    FROM listings
    WHERE seller_user_id = @demo_owner_id
      AND item_name = @demo_listing_name
    LIMIT 1
);

-- Add a bundled product image if the listing has no images.
INSERT INTO listing_images (
    listing_id,
    image_url,
    display_order
)
SELECT
    @demo_listing_id,
    '/assets/images/listing/listing-headphones.jpg',
    1
WHERE @demo_listing_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM listing_images
      WHERE listing_id = @demo_listing_id
  );

-- Verify the demonstration listing.
SELECT
    l.listing_id,
    u.username,
    l.item_name,
    l.price,
    l.`condition`,
    l.listing_status,
    (
        SELECT COUNT(*)
        FROM listing_images AS li
        WHERE li.listing_id = l.listing_id
    ) AS image_count
FROM listings AS l
INNER JOIN users AS u
    ON l.seller_user_id = u.user_id
WHERE u.username = @demo_username
  AND l.item_name = @demo_listing_name;


-- ============================================================
-- SECTION 3: POPULATE LISTING VIEWS
-- ============================================================

-- Find the three users who will generate demonstration views.
SET @demo_viewer_1_id = (
    SELECT user_id
    FROM users
    WHERE username = 'alexm'
      AND account_status = 'active'
    LIMIT 1
);

SET @demo_viewer_2_id = (
    SELECT user_id
    FROM users
    WHERE username = 'jordanlee'
      AND account_status = 'active'
    LIMIT 1
);

SET @demo_viewer_3_id = (
    SELECT user_id
    FROM users
    WHERE username = 'taylorn'
      AND account_status = 'active'
    LIMIT 1
);

-- Refresh only the designated demo viewers' activity
-- on the dedicated UC-11 demonstration listing.
-- Do not use this approach on a real marketplace listing.

DELETE FROM listing_views
WHERE listing_id = @demo_listing_id
  AND user_id IN (
      @demo_viewer_1_id,
      @demo_viewer_2_id,
      @demo_viewer_3_id
  )
  AND @demo_listing_id IS NOT NULL
  AND @demo_viewer_1_id IS NOT NULL
  AND @demo_viewer_2_id IS NOT NULL
  AND @demo_viewer_3_id IS NOT NULL;

-- Insert eight controlled demonstration views.
INSERT INTO listing_views (
    user_id,
    listing_id,
    viewed_at
)
SELECT
    demo_views.user_id,
    @demo_listing_id,
    NOW() - INTERVAL demo_views.days_ago DAY
FROM (
    -- Viewer 1: three recent views and one older view.
    SELECT @demo_viewer_1_id AS user_id, 1 AS days_ago
    UNION ALL SELECT @demo_viewer_1_id, 2
    UNION ALL SELECT @demo_viewer_1_id, 3
    UNION ALL SELECT @demo_viewer_1_id, 10

    -- Viewer 2: two recent views and one older view.
    UNION ALL SELECT @demo_viewer_2_id, 1
    UNION ALL SELECT @demo_viewer_2_id, 4
    UNION ALL SELECT @demo_viewer_2_id, 12

    -- Viewer 3: one older view.
    UNION ALL SELECT @demo_viewer_3_id, 15
) AS demo_views
WHERE @demo_listing_id IS NOT NULL
  AND @demo_viewer_1_id IS NOT NULL
  AND @demo_viewer_2_id IS NOT NULL
  AND @demo_viewer_3_id IS NOT NULL;

-- Verify the first three analytics metrics.
SELECT
    COUNT(*) AS total_views,
    COUNT(DISTINCT user_id) AS unique_viewers,
    COUNT(
        CASE
            WHEN viewed_at >= NOW() - INTERVAL 7 DAY
            THEN 1
        END
    ) AS views_last_7_days
FROM listing_views
WHERE listing_id = @demo_listing_id;


-- ============================================================
-- SECTION 4: POPULATE WISHLIST SAVES
-- ============================================================

-- Add the demonstration listing to Viewer 1's wishlist.
-- Skip insertion if the user already saved the listing.
INSERT INTO wishlist_items (
    user_id,
    listing_id
)
SELECT
    @demo_viewer_1_id,
    @demo_listing_id
WHERE @demo_listing_id IS NOT NULL
  AND @demo_viewer_1_id IS NOT NULL
  AND @demo_viewer_1_id <> @demo_owner_id
  AND NOT EXISTS (
      SELECT 1
      FROM wishlist_items
      WHERE user_id = @demo_viewer_1_id
        AND listing_id = @demo_listing_id
  );

-- Add the demonstration listing to Viewer 2's wishlist.
-- Skip insertion if the user already saved the listing.
INSERT INTO wishlist_items (
    user_id,
    listing_id
)
SELECT
    @demo_viewer_2_id,
    @demo_listing_id
WHERE @demo_listing_id IS NOT NULL
  AND @demo_viewer_2_id IS NOT NULL
  AND @demo_viewer_2_id <> @demo_owner_id
  AND @demo_viewer_2_id <> @demo_viewer_1_id
  AND NOT EXISTS (
      SELECT 1
      FROM wishlist_items
      WHERE user_id = @demo_viewer_2_id
        AND listing_id = @demo_listing_id
  );

-- Verify the number of wishlist saves.
SELECT
    COUNT(*) AS wishlist_saves
FROM wishlist_items
WHERE listing_id = @demo_listing_id;


