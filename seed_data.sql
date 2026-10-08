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
        '555-0104',
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
        '555-0105',
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
    (11, NULL, 'Collectibles', 'Collectible items, memorabilia, and trading cards.', NULL),

    (5, 1, 'Computers', 'Computers and computer accessories.', NULL),
    (6, 1, 'Audio', 'Headphones, speakers, and audio equipment.', NULL),

    (7, 2, 'Furniture', 'Desks, chairs, tables, and other furniture.', NULL),
    (8, 2, 'Kitchen', 'Kitchen tools and appliances.', NULL),

    (9, 3, 'Mens Clothing', 'Clothing and accessories for men.', NULL),
    (10, 3, 'Womens Clothing', 'Clothing and accessories for women.', NULL),

    (12, 11, 'Trading Cards', 'Collectible trading cards including sports and gaming cards.', NULL);


-- =========================================
-- LISTINGS
-- =========================================
-- 16 active listings plus listings in other
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
    location_zip_code,
    latitude,
    longitude,
    meetup_location_type,
    seller_meetup_radius,
    auction_end_at,
    price,
    `condition`,
    quantity,
    listing_status,
    created_at,
    updated_at,
    renewed_at
)
VALUES

    -- =====================================
    -- ACTIVE / SHIPPING
    -- =====================================

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
        NULL,
        NULL,
        NULL,
        NULL,
        45.00,
        'excellent',
        1,
        'active',
        '2026-08-28 14:35:00',
        '2026-08-28 14:35:00',
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
        NULL,
        NULL,
        NULL,
        NULL,
        85.00,
        'like_new',
        1,
        'active',
        '2026-09-18 09:20:00',
        '2026-09-18 09:20:00',
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
        NULL,
        NULL,
        NULL,
        NULL,
        38.00,
        'good',
        1,
        'active',
        '2026-09-03 17:45:00',
        '2026-09-03 17:45:00',
        NULL
    ),

    (
        4, 2, 8,
        'Vintage Kettle Collection',
        'Stainless steel kettles.',
        'fixed_price',
        'shipping',
        5.99,
        4,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        24.99,
        'good',
        2,
        'active',
        '2026-07-26 11:10:00',
        '2026-07-26 11:10:00',
        NULL
    ),

    (
        5, 1, 5,
        'Nintendo Switch 2 Charging Dock',
        'Dock includes AC power adapter, USB ports, and controller.',
        'fixed_price',
        'shipping',
        4.99,
        3,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        39.99,
        'like_new',
        1,
        'active',
        '2026-09-21 08:15:00',
        '2026-09-21 08:15:00',
        NULL
    ),

    (
        17, 3, 10,
        'Leather Doc Martens Boots Shoes',
        'Brown leather ankle boots with minimal wear. Comfortable everyday boots in excellent condition.',
        'fixed_price',
        'shipping',
        8.50,
        4,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        58.00,
        'excellent',
        1,
        'active',
        '2026-10-03 09:25:00',
        '2026-10-03 09:25:00',
        NULL
    ),

    (
        18, 1, 6,
        'Keyboard and Sound Equipment Bundle',
        'Electronic keyboard bundled with sound equipment and accessories. Great starter setup for music practice or home recording.',
        'fixed_price',
        'shipping',
        18.00,
        5,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        552.00,
        'good',
        1,
        'active',
        '2026-10-04 15:10:00',
        '2026-10-04 15:10:00',
        NULL
    ),

    (
        19, 1, 12,
        'Classic Charizard Pokemon Card',
        'Classic Charizard trading card kept in protective storage. A great addition for Pokemon card collectors.',
        'fixed_price',
        'shipping',
        5.00,
        4,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        100.00,
        'fair',
        1,
        'active',
        '2026-10-05 11:45:00',
        '2026-10-05 11:45:00',
        NULL
    ),

    (
        20, 1, 12,
        'Classic Blastoise Pokemon Card',
        'Classic Blastoise trading card stored in a protective sleeve. Ideal for collectors of vintage Pokemon cards.',
        'fixed_price',
        'shipping',
        5.00,
        4,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        150.00,
        'excellent',
        1,
        'active',
        '2026-10-06 14:20:00',
        '2026-10-06 14:20:00',
        NULL
    ),

    -- =====================================
    -- ACTIVE / MEETUP
    -- =====================================

    (
        6, 2, 7,
        'Wooden Study Desk',
        'Compact study desk suitable for an apartment.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        '92507',
        33.975100,
        -117.325400,
        'parking_lot',
        15,
        NULL,
        70.00,
        'good',
        1,
        'active',
        '2026-08-11 13:05:00',
        '2026-08-11 13:05:00',
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
        '92501',
        33.980600,
        -117.375500,
        'coffee_shop',
        10,
        NULL,
        42.00,
        'fair',
        1,
        'active',
        '2026-09-09 16:30:00',
        '2026-09-09 16:30:00',
        NULL
    ),

    (
        8, 3, 10,
        'Mint Winter Coat',
        'Warm winter coat worn only a few times.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        '92503',
        33.938300,
        -117.460300,
        'library',
        10,
        NULL,
        55.00,
        'like_new',
        1,
        'active',
        '2026-06-14 10:25:00',
        '2026-06-14 10:25:00',
        NULL
    ),

    (
        9, 2, 12,
        'Shiny Charizard Pokemon Card',
        'Shiny Charizard Pokemon trading card stored in a protective sleeve.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        '92507',
        33.971900,
        -117.328100,
        'library',
        5,
        NULL,
        1500.00,
        'excellent',
        1,
        'active',
        '2026-09-20 19:40:00',
        '2026-09-20 19:40:00',
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
        '92504',
        33.927500,
        -117.411200,
        'other',
        10,
        NULL,
        35.00,
        'good',
        1,
        'active',
        '2026-08-31 07:50:00',
        '2026-08-31 07:50:00',
        NULL
    ),

    (
        15, 2, 7,
        'Vintage Accent Chair',
        'Vintage upholstered accent chair with a solid wooden frame. Shows light wear consistent with age.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        '92507',
        33.975100,
        -117.325400,
        'parking_lot',
        15,
        NULL,
        85.00,
        'good',
        1,
        'active',
        '2026-10-01 10:15:00',
        '2026-10-01 10:15:00',
        NULL
    ),

    (
        16, 2, 7,
        'Wooden Dining Chair',
        'Solid wooden dining chair with a natural finish. Sturdy and in good overall condition.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        '92501',
        33.980600,
        -117.375500,
        'coffee_shop',
        10,
        NULL,
        40.00,
        'good',
        1,
        'active',
        '2026-10-02 13:40:00',
        '2026-10-02 13:40:00',
        NULL
    ),

    -- =====================================
    -- HIDDEN LISTING
    -- =====================================

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
        NULL,
        NULL,
        NULL,
        NULL,
        25.00,
        'good',
        1,
        'hidden',
        '2026-09-12 12:00:00',
        '2026-09-15 08:30:00',
        NULL
    ),

    -- =====================================
    -- SOLD LISTING
    -- =====================================

    (
        12, 2, 7,
        'Floor Lamp',
        'Standing lamp previously sold.',
        'fixed_price',
        'meetup',
        NULL,
        NULL,
        '92501',
        33.982000,
        -117.373000,
        'parking_lot',
        10,
        NULL,
        20.00,
        'good',
        0,
        'sold',
        '2026-07-05 15:15:00',
        '2026-08-02 18:10:00',
        NULL
    ),

    -- =====================================
    -- ARCHIVED LISTING
    -- =====================================

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
        NULL,
        NULL,
        NULL,
        NULL,
        28.00,
        'fair',
        1,
        'archived',
        '2026-05-22 09:05:00',
        '2026-07-01 10:00:00',
        NULL
    ),

    -- =====================================
    -- DRAFT LISTING
    -- =====================================

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
        NULL,
        NULL,
        NULL,
        NULL,
        32.00,
        'excellent',
        1,
        'draft',
        '2026-09-19 21:10:00',
        '2026-09-19 21:10:00',
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
    (1, 1, '/assets/images/listing/listing-wireless-keyboard.jpg', 1),
    (2, 1, '/assets/images/listing/listing-wireless-keyboard-2.jpg', 2),

    (3, 2, '/assets/images/listing/listing-headphones.jpg', 1),
    (4, 3, '/assets/images/listing/listing-denim-jacket.jpg', 1),
    (5, 4, '/assets/images/listing/listing-kettle.jpg', 1),
    (6, 5, '/assets/images/listing/listing-docking-station.jpg', 1),
    (7, 6, '/assets/images/listing/listing-study-desk.jpg', 1),
    (8, 7, '/assets/images/listing/listing-office-chair.jpg', 1),
    (9, 8, '/assets/images/listing/listing-winter-coat.jpg', 1),
    (10, 9, '/assets/images/listing/listing-trading-cards.jpg', 1),
    (11, 10, '/assets/images/listing/listing-running-shoes.jpg', 1),
    (12, 11, '/assets/images/listing/listing-gaming-mouse.jpg', 1),
    (13, 12, '/assets/images/listing/listing-floor-lamp.jpg', 1),
    (14, 13, '/assets/images/listing/listing-backpack.jpg', 1),
    (15, 14, '/assets/images/listing/listing-speaker.jpg', 1),

    (16, 15, '/assets/images/listing/listing-vintage-chair.jpg', 1),
    (17, 16, '/assets/images/listing/listing-chair.jpg', 1),
    (18, 17, '/assets/images/listing/listing-boots1.jpg', 1),
    (19, 17, '/assets/images/listing/listing-boots2.jpg', 2),
    (20, 18, '/assets/images/listing/listing-keyboard-equipment.jpg', 1),
    (21, 19, '/assets/images/listing/listing-trading-cards2.jpg', 1),
    (22, 20, '/assets/images/listing/listing-trading-cards3.jpg', 1);


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