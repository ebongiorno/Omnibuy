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


-- ============================================================
-- SECTION 5: POPULATE CONVERSATIONS AND MESSAGES
-- ============================================================

-- Create the first demonstration conversation.
INSERT INTO conversations (
    buyer_id,
    seller_id,
    listing_id
)
SELECT
    @demo_viewer_1_id,
    @demo_owner_id,
    @demo_listing_id
WHERE @demo_viewer_1_id IS NOT NULL
  AND @demo_owner_id IS NOT NULL
  AND @demo_listing_id IS NOT NULL
  AND @demo_viewer_1_id <> @demo_owner_id
  AND NOT EXISTS (
      SELECT 1
      FROM conversations
      WHERE buyer_id = @demo_viewer_1_id
        AND seller_id = @demo_owner_id
        AND listing_id = @demo_listing_id
  );

-- Create the second demonstration conversation.
INSERT INTO conversations (
    buyer_id,
    seller_id,
    listing_id
)
SELECT
    @demo_viewer_2_id,
    @demo_owner_id,
    @demo_listing_id
WHERE @demo_viewer_2_id IS NOT NULL
  AND @demo_owner_id IS NOT NULL
  AND @demo_listing_id IS NOT NULL
  AND @demo_viewer_2_id <> @demo_owner_id
  AND @demo_viewer_2_id <> @demo_viewer_1_id
  AND NOT EXISTS (
      SELECT 1
      FROM conversations
      WHERE buyer_id = @demo_viewer_2_id
        AND seller_id = @demo_owner_id
        AND listing_id = @demo_listing_id
  );

-- Retrieve the first conversation ID.
SET @demo_conversation_1_id = (
    SELECT conversation_id
    FROM conversations
    WHERE buyer_id = @demo_viewer_1_id
      AND seller_id = @demo_owner_id
      AND listing_id = @demo_listing_id
    LIMIT 1
);

-- Retrieve the second conversation ID.
SET @demo_conversation_2_id = (
    SELECT conversation_id
    FROM conversations
    WHERE buyer_id = @demo_viewer_2_id
      AND seller_id = @demo_owner_id
      AND listing_id = @demo_listing_id
    LIMIT 1
);

SELECT
    conversation_id,
    buyer_id,
    seller_id,
    listing_id
FROM conversations
WHERE listing_id = @demo_listing_id;

-- Create five identifiable demonstration messages.
INSERT INTO messages (
    conversation_id,
    sender_user_id,
    message_text
)
SELECT
    demo_messages.conversation_id,
    demo_messages.sender_user_id,
    demo_messages.message_text
FROM (
    -- Conversation 1: Three messages.
    SELECT
        @demo_conversation_1_id AS conversation_id,
        @demo_viewer_1_id AS sender_user_id,
        '[UC11-DEMO-01] Hi, are the headphones still available?'
            AS message_text

    UNION ALL

    SELECT
        @demo_conversation_1_id,
        @demo_owner_id,
        '[UC11-DEMO-02] Yes, they are still available.'

    UNION ALL

    SELECT
        @demo_conversation_1_id,
        @demo_viewer_1_id,
        '[UC11-DEMO-03] Does the carrying case come with them?'

    -- Conversation 2: Two messages.
    UNION ALL

    SELECT
        @demo_conversation_2_id,
        @demo_viewer_2_id,
        '[UC11-DEMO-04] Could you ship these this week?'

    UNION ALL

    SELECT
        @demo_conversation_2_id,
        @demo_owner_id,
        '[UC11-DEMO-05] Yes, I can ship within two days.'
) AS demo_messages
WHERE demo_messages.conversation_id IS NOT NULL
  AND demo_messages.sender_user_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM messages AS existing
      WHERE existing.conversation_id =
            demo_messages.conversation_id
        AND existing.message_text =
            demo_messages.message_text
  );

-- Verify conversation and message totals.
SELECT
    (
        SELECT COUNT(*)
        FROM conversations
        WHERE listing_id = @demo_listing_id
    ) AS total_conversations,

    (
        SELECT COUNT(*)
        FROM messages AS m
        INNER JOIN conversations AS c
            ON m.conversation_id = c.conversation_id
        WHERE c.listing_id = @demo_listing_id
    ) AS total_messages;


-- ============================================================
-- SECTION 6: FINAL DEMONSTRATION DATA VERIFICATION
-- ============================================================

-- Verify all six UC-11 analytics metrics.
SELECT
    @demo_listing_id AS listing_id,
    @demo_listing_name AS listing_name,

    -- Total Views
    (
        SELECT COUNT(*)
        FROM listing_views
        WHERE listing_id = @demo_listing_id
    ) AS total_views,

    -- Unique Viewers
    (
        SELECT COUNT(DISTINCT user_id)
        FROM listing_views
        WHERE listing_id = @demo_listing_id
    ) AS unique_viewers,

    -- Views in Last 7 Days
    (
        SELECT COUNT(*)
        FROM listing_views
        WHERE listing_id = @demo_listing_id
          AND viewed_at >= NOW() - INTERVAL 7 DAY
    ) AS views_last_7_days,

    -- Wishlist Saves
    (
        SELECT COUNT(*)
        FROM wishlist_items
        WHERE listing_id = @demo_listing_id
    ) AS wishlist_saves,

    -- Total Conversations
    (
        SELECT COUNT(*)
        FROM conversations
        WHERE listing_id = @demo_listing_id
    ) AS total_conversations,

    -- Total Messages
    (
        SELECT COUNT(*)
        FROM messages AS m
        INNER JOIN conversations AS c
            ON m.conversation_id = c.conversation_id
        WHERE c.listing_id = @demo_listing_id
    ) AS total_messages;

-- ============================================================
-- SECTION 7: CREATE SECOND DEMONSTRATION LISTING
-- ============================================================

-- Dedicated listing used to demonstrate sold-listing analytics.
SET @demo_sold_listing_name = 'UC11 Demo - Portable Bluetooth Speaker';

-- Create the second listing only if it does not exist.
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
    @demo_sold_listing_name,
    'Compact portable Bluetooth speaker in good condition. Fully functional and includes charging cable.',
    'fixed_price',
    'shipping',
    0.00,
    3,
    45.00,
    'good',
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
        AND item_name = @demo_sold_listing_name
  );

-- Find the second demonstration listing.
SET @demo_sold_listing_id = (
    SELECT listing_id
    FROM listings
    WHERE seller_user_id = @demo_owner_id
      AND item_name = @demo_sold_listing_name
    LIMIT 1
);

-- Verify the second listing was created.
SELECT
    listing_id,
    item_name,
    price,
    listing_status,
    seller_user_id
FROM listings
WHERE listing_id = @demo_sold_listing_id;


-- ============================================================
-- SECTION 8: POPULATE SOLD LISTING ANALYTICS
-- ============================================================

-- Create four views for the second listing.
-- Only insert if this demonstration listing has no views yet.
-- This preserves the existing history when the script is rerun.

INSERT INTO listing_views (
    user_id,
    listing_id,
    viewed_at
)
SELECT
    demo_views.user_id,
    @demo_sold_listing_id,
    NOW() - INTERVAL demo_views.days_ago DAY
FROM (
    -- Viewer 1: two recent views and one older view.
    SELECT @demo_viewer_1_id AS user_id, 1 AS days_ago
    UNION ALL SELECT @demo_viewer_1_id, 3
    UNION ALL SELECT @demo_viewer_1_id, 10

    -- Viewer 2: one older view.
    UNION ALL SELECT @demo_viewer_2_id, 12
) AS demo_views
WHERE @demo_sold_listing_id IS NOT NULL
  AND @demo_viewer_1_id IS NOT NULL
  AND @demo_viewer_2_id IS NOT NULL
  AND @demo_viewer_1_id <> @demo_owner_id
  AND @demo_viewer_2_id <> @demo_owner_id
  AND @demo_viewer_1_id <> @demo_viewer_2_id
  AND NOT EXISTS (
      SELECT 1
      FROM listing_views
      WHERE listing_id = @demo_sold_listing_id
  );

-- Add one wishlist save for the Bluetooth speaker.

INSERT INTO wishlist_items (
    user_id,
    listing_id
)
SELECT
    @demo_viewer_1_id,
    @demo_sold_listing_id
WHERE @demo_sold_listing_id IS NOT NULL
  AND @demo_viewer_1_id IS NOT NULL
  AND @demo_viewer_1_id <> @demo_owner_id
  AND NOT EXISTS (
      SELECT 1
      FROM wishlist_items
      WHERE user_id = @demo_viewer_1_id
        AND listing_id = @demo_sold_listing_id
  );

-- Create a conversation about the Bluetooth speaker.

INSERT INTO conversations (
    buyer_id,
    seller_id,
    listing_id
)
SELECT
    @demo_viewer_2_id,
    @demo_owner_id,
    @demo_sold_listing_id
WHERE @demo_viewer_2_id IS NOT NULL
  AND @demo_owner_id IS NOT NULL
  AND @demo_sold_listing_id IS NOT NULL
  AND @demo_viewer_2_id <> @demo_owner_id
  AND NOT EXISTS (
      SELECT 1
      FROM conversations
      WHERE buyer_id = @demo_viewer_2_id
        AND seller_id = @demo_owner_id
        AND listing_id = @demo_sold_listing_id
  );

-- Find the Bluetooth speaker conversation.

SET @demo_sold_conversation_id = (
    SELECT conversation_id
    FROM conversations
    WHERE buyer_id = @demo_viewer_2_id
      AND seller_id = @demo_owner_id
      AND listing_id = @demo_sold_listing_id
    LIMIT 1
);

-- Buyer asks about the Bluetooth speaker.

INSERT INTO messages (
    conversation_id,
    sender_user_id,
    message_text
)
SELECT
    @demo_sold_conversation_id,
    @demo_viewer_2_id,
    '[UC11-SOLD-01] Is the Bluetooth speaker still available?'
WHERE @demo_sold_conversation_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM messages
      WHERE conversation_id = @demo_sold_conversation_id
        AND message_text =
            '[UC11-SOLD-01] Is the Bluetooth speaker still available?'
  );

  -- Listing owner responds.

INSERT INTO messages (
    conversation_id,
    sender_user_id,
    message_text
)
SELECT
    @demo_sold_conversation_id,
    @demo_owner_id,
    '[UC11-SOLD-02] Yes, it is available and works perfectly!'
WHERE @demo_sold_conversation_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM messages
      WHERE conversation_id = @demo_sold_conversation_id
        AND message_text =
            '[UC11-SOLD-02] Yes, it is available and works perfectly!'
  );

-- ============================================================
-- VERIFY BLUETOOTH SPEAKER ANALYTICS
-- ============================================================

SELECT
    @demo_sold_listing_id AS listing_id,

    (
        SELECT COUNT(*)
        FROM listing_views
        WHERE listing_id = @demo_sold_listing_id
    ) AS total_views,

    (
        SELECT COUNT(DISTINCT user_id)
        FROM listing_views
        WHERE listing_id = @demo_sold_listing_id
    ) AS unique_viewers,

    (
        SELECT COUNT(*)
        FROM listing_views
        WHERE listing_id = @demo_sold_listing_id
          AND viewed_at >= NOW() - INTERVAL 7 DAY
    ) AS views_last_7_days,

    (
        SELECT COUNT(*)
        FROM wishlist_items
        WHERE listing_id = @demo_sold_listing_id
    ) AS wishlist_saves,

    (
        SELECT COUNT(*)
        FROM conversations
        WHERE listing_id = @demo_sold_listing_id
    ) AS total_conversations,

    (
        SELECT COUNT(*)
        FROM messages AS m
        INNER JOIN conversations AS c
            ON m.conversation_id = c.conversation_id
        WHERE c.listing_id = @demo_sold_listing_id
    ) AS total_messages;

