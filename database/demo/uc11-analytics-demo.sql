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


