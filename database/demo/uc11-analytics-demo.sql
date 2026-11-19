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


