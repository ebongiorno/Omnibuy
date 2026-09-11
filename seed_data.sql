-- =========================================
-- OMNIBUY SEED DATA
-- =========================================
-- Intended for development/testing only.
-- Run after the database schema has been created.


START TRANSACTION;


-- =========================================
-- USERS
-- =========================================

INSERT INTO users (
    user_id,
    first_name,
    last_name,
    username,
    email,
    password_hash,
    is_email_verified,
    phone_number,
    birth_date,
    profile_image_url,
    bio,
    is_last_name_hidden,
    account_status
)
VALUES
    (
        1,
        'Alex',
        'Morgan',
        'alexm',
        'alex@example.com',
        'DEV_ONLY_HASH_1',
        TRUE,
        '555-0101',
        '1998-04-12',
        NULL,
        'Tech enthusiast selling electronics.',
        FALSE,
        'active'
    ),
    (
        2,
        'Jordan',
        'Lee',
        'jordanlee',
        'jordan@example.com',
        'DEV_ONLY_HASH_2',
        TRUE,
        '555-0102',
        '1995-08-22',
        NULL,
        'Selling home goods and furniture.',
        FALSE,
        'active'
    ),
    (
        3,
        'Taylor',
        'Nguyen',
        'taylorn',
        'taylor@example.com',
        'DEV_ONLY_HASH_3',
        TRUE,
        '555-0103',
        '2000-01-15',
        NULL,
        'Clothing and accessories.',
        TRUE,
        'active'
    ),
    (
        4,
        'Jamie',
        'Patel',
        'jamiep',
        'jamie@example.com',
        'DEV_ONLY_HASH_4',
        TRUE,
        NULL,
        '2001-06-03',
        NULL,
        'Looking for good marketplace deals.',
        FALSE,
        'active'
    ),
    (
        5,
        'Casey',
        'Kim',
        'caseyk',
        'casey@example.com',
        'DEV_ONLY_HASH_5',
        FALSE,
        NULL,
        '1999-11-18',
        NULL,
        NULL,
        FALSE,
        'active'
    );


-- =========================================
-- SELLER PROFILES
-- =========================================

INSERT INTO seller_profiles (
    user_id,
    store_name,
    is_verified,
    payout_account_reference
)
VALUES
    (1, 'TechNest', TRUE, 'DEV_PAYOUT_001'),
    (2, 'HomeCorner', TRUE, 'DEV_PAYOUT_002'),
    (3, 'CampusCloset', FALSE, 'DEV_PAYOUT_003');


-- =========================================
-- CATEGORIES
-- =========================================

INSERT INTO categories (
    category_id,
    parent_category_id,
    category_name,
    description,
    image_url
)
VALUES
    (1, NULL, 'Electronics', 'Electronic devices and accessories.', NULL),
    (2, NULL, 'Home and Furniture', 'Furniture, decor, and household products.', NULL),
    (3, NULL, 'Clothing', 'Clothing, shoes, and fashion accessories.', NULL),
    (4, NULL, 'Books', 'Books and educational materials.', NULL),

    (5, 1, 'Computers', 'Computers and computer accessories.', NULL),
    (6, 1, 'Audio', 'Headphones, speakers, and audio equipment.', NULL),

    (7, 2, 'Furniture', 'Desks, chairs, tables, and other furniture.', NULL),
    (8, 2, 'Kitchen', 'Kitchen tools and appliances.', NULL),

    (9, 3, 'Mens Clothing', 'Clothing and accessories for men.', NULL),
    (10, 3, 'Womens Clothing', 'Clothing and accessories for women.', NULL);


-- =========================================
-- LISTINGS
-- =========================================
-- 10 active listings plus listings in other
-- states so feed filtering can be tested.

INSERT INTO listings (
    listing_id,
    seller_user_id,
    category_id,
    item_name,
    item_description,
    sale_type,
    fulfillment_type,
    shipping_cost,
    estimated_shipping_days,
    meetup_location_type,
    meetup_radius,
    price,
    `condition`,
    quantity,
    listing_status,
    renewed_at
)
VALUES

    -- Active / Shipping
    (
        1, 1, 5,
        'Wireless Mechanical Keyboard',
        'Compact mechanical keyboard in excellent condition.',
        'fixed_price',
        'shipping',
        7.99,
        4,
        NULL,
        NULL,
        45.00,
        'excellent',
        1,
        'active',
        NULL
    ),

    (
        2, 1, 6,
        'Noise Cancelling Headphones',
        'Over-ear headphones with carrying case.',
        'fixed_price',
        'shipping',
        6.50,
        3,
        NULL,
        NULL,
        85.00,
        'like_new',
        1,
        'active',
        NULL
    ),

    (
        3, 3, 9,
        'Vintage Denim Jacket',
        'Blue denim jacket with light wear.',
        'fixed_price',
        'shipping',
        8.00,
        5,
        NULL,
        NULL,
        38.00,
        'good',
        1,
        'active',
        NULL
    ),

    (
        4, 2, 8,
        'Electric Kettle',
        'Stainless steel electric kettle.',
        'fixed_price',
        'shipping',
        5.99,
        4,
        NULL,
        NULL,
        24.99,
        'good',
        2,
        'active',
        NULL
    ),

    (
        5, 1, 5,
        'USB-C Docking Station',
        'Dock with HDMI, USB, and Ethernet ports.',
        'fixed_price',
        'shipping',
        4.99,
        3,
        NULL,
        NULL,
        39.99,
        'like_new',
        1,
        'active',
        NULL
    ),

    -- Active / Meetup
    (
        6, 2, 7,
        'Wooden Study Desk',
        'Compact study desk suitable for an apartment.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        'parking_lot',
        15,
        70.00,
        'good',
        1,
        'active',
        NULL
    ),

    (
        7, 2, 7,
        'Office Chair',
        'Adjustable office chair with minor cosmetic wear.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        'coffee_shop',
        10,
        42.00,
        'fair',
        1,
        'active',
        NULL
    ),

    (
        8, 3, 10,
        'Black Winter Coat',
        'Warm winter coat worn only a few times.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        'library',
        10,
        55.00,
        'like_new',
        1,
        'active',
        NULL
    ),

    (
        9, 2, 4,
        'Database Systems Textbook',
        'Introductory database textbook with some highlighting.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        'library',
        5,
        30.00,
        'good',
        1,
        'active',
        NULL
    ),

    (
        10, 3, 9,
        'Running Shoes',
        'Lightweight running shoes.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        'other',
        10,
        35.00,
        'excellent',
        1,
        'active',
        NULL
    ),

    -- Hidden listing
    (
        11, 1, 5,
        'Gaming Mouse',
        'Gaming mouse currently hidden by seller.',
        'fixed_price',
        'shipping',
        4.50,
        3,
        NULL,
        NULL,
        25.00,
        'good',
        1,
        'hidden',
        NULL
    ),

    -- Sold listing
    (
        12, 2, 7,
        'Floor Lamp',
        'Standing lamp previously sold.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        'parking_lot',
        10,
        20.00,
        'good',
        1,
        'sold',
        NULL
    ),

    -- Archived listing
    (
        13, 3, 10,
        'Canvas Backpack',
        'Older listing kept for seller history.',
        'fixed_price',
        'shipping',
        6.00,
        5,
        NULL,
        NULL,
        28.00,
        'fair',
        1,
        'archived',
        NULL
    ),

    -- Draft listing
    (
        14, 1, 6,
        'Portable Bluetooth Speaker',
        'Draft listing not yet visible to buyers.',
        'fixed_price',
        'shipping',
        5.50,
        4,
        NULL,
        NULL,
        32.00,
        'excellent',
        1,
        'draft',
        NULL
    );


-- =========================================
-- LISTING IMAGES
-- =========================================
-- Replace these placeholder paths/URLs later
-- with actual project images if desired.

INSERT INTO listing_images (
    listing_image_id,
    listing_id,
    image_url,
    display_order
)
VALUES
    (1, 1, '/assets/images/seed/keyboard.jpg', 1),
    (2, 1, '/assets/images/seed/keyboard_2.jpg', 2),

    (3, 2, '/assets/images/seed/headphones.jpg', 1),
    (4, 3, '/assets/images/seed/denim_jacket.jpg', 1),
    (5, 4, '/assets/images/seed/kettle.jpg', 1),
    (6, 5, '/assets/images/seed/docking_station.jpg', 1),
    (7, 6, '/assets/images/seed/study_desk.jpg', 1),
    (8, 7, '/assets/images/seed/office_chair.jpg', 1),
    (9, 8, '/assets/images/seed/winter_coat.jpg', 1),
    (10, 9, '/assets/images/seed/database_textbook.jpg', 1),
    (11, 10, '/assets/images/seed/running_shoes.jpg', 1),
    (12, 11, '/assets/images/seed/gaming_mouse.jpg', 1),
    (13, 12, '/assets/images/seed/floor_lamp.jpg', 1),
    (14, 13, '/assets/images/seed/backpack.jpg', 1),
    (15, 14, '/assets/images/seed/speaker.jpg', 1);


-- =========================================
-- SEARCH HISTORY
-- =========================================

INSERT INTO search_history (
    user_id,
    search_query,
    search_type
)
VALUES
    (4, 'mechanical keyboard', 'items'),
    (4, 'headphones', 'items'),
    (4, 'computer accessories', 'items'),
    (4, 'TechNest', 'seller_profiles'),

    (5, 'desk', 'items'),
    (5, 'office chair', 'items'),
    (5, 'winter coat', 'items'),
    (5, 'HomeCorner', 'seller_profiles');


-- =========================================
-- LISTING VIEWS
-- =========================================
-- Repeated views are intentional. They can
-- later act as stronger interest signals.

INSERT INTO listing_views (
    user_id,
    listing_id
)
VALUES
    (4, 1),
    (4, 1),
    (4, 1),
    (4, 2),
    (4, 2),
    (4, 5),
    (4, 6),

    (5, 6),
    (5, 6),
    (5, 7),
    (5, 7),
    (5, 8),
    (5, 9);


COMMIT;