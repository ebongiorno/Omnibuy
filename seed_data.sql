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
    ),
    (
        6,
        'Morgan',
        'Reed',
        'morganr',
        'morgan.reed@example.com',
        'DEV_ONLY_HASH_6',
        TRUE,
        '555-0106',
        '1997-03-14',
        NULL,
        'Seller account used for inactive seller regression testing.',
        FALSE,
        'suspended'
    ),

    (
        7,
        'Maya',
        'Chen',
        'neonattic',
        'neonattic@example.com',
        'DEV_ONLY_HASH_7',
        TRUE,
        '555-0107',
        '1997-05-14',
        NULL,
        'Retro games, consoles, and nostalgic tech finds.',
        FALSE,
        'active'
    ),

    (
        8,
        'Owen',
        'Rivera',
        'deskcraftdaily',
        'deskcraftdaily@example.com',
        'DEV_ONLY_HASH_8',
        TRUE,
        '555-0108',
        '1993-11-08',
        NULL,
        'My shop is all about making workspaces more comfortable. I usually sell desk accessories, monitor stands, stationery,
         and the occasional chair after a home office refresh.',
        TRUE,
        'active'
    ),

    (
        9,
        'Priya',
        'Shah',
        'page.turner_88',
        'page.turner_88@example.com',
        'DEV_ONLY_HASH_9',
        TRUE,
        '555-0109',
        '1995-02-23',
        NULL,
        'Book collector with a soft spot for mysteries, fantasy, and old college textbooks. 
         I photograph any highlighting, creased pages, or shelf wear so buyers know what they are getting.
         I also enjoy putting together inexpensive reading bundles.',
        TRUE,
        'active'
    ),

    (
        10,
        'Luca',
        'Bennett',
        'loopandloom',
        'loopandloom@example.com',
        'DEV_ONLY_HASH_10',
        TRUE,
        '555-0110',
        '2002-06-19',
        NULL,
        'Pre-loved clothes with personality.',
        FALSE,
        'active'
    ),

    (
        11,
        'Samira',
        'Brooks',
        'pixelcrate',
        'pixelcrate@example.com',
        'DEV_ONLY_HASH_11',
        TRUE,
        '555-0111',
        '1998-09-04',
        NULL,
        'I love finding practical gear for creative people. My listings range from spare computer peripherals and drawing tablets to little studio upgrades. 
         Everything gets tested and cleaned before I post it, and I try to include useful photos of ports, cables, and wear.',
        FALSE,
        'active'
    ),

    (
        12,
        'Elias',
        'Martinez',
        'greenroomgoods',
        'greenroomgoods@example.com',
        'DEV_ONLY_HASH_12',
        TRUE,
        '555-0112',
        '1989-12-30',
        NULL,
        'Plants, planters, small shelves, and cozy apartment decor for a greener room.',
        TRUE,
        'active'
    ),

    (
        13,
        'Riley',
        'Thompson',
        'themidnightmarket',
        'themidnightmarket@example.com',
        'DEV_ONLY_HASH_13',
        TRUE,
        '555-0113',
        '1991-03-12',
        NULL,
        'Welcome to my rotating collection of unusual finds. One week it might be a film camera or a board game,
         and the next it might be retro home decor. I focus on items that are still useful, even if they have a story to tell.
          I note missing pieces and cosmetic flaws in the descriptions, and I am happy to answer questions before a sale.',
        FALSE,
        'active'
    ),

    (
        14,
        'Nia',
        'Carter',
        'vintagevault22',
        'vintagevault22@example.com',
        'DEV_ONLY_HASH_14',
        TRUE,
        '555-0114',
        '1999-07-27',
        NULL,
        'Vintage jackets, bags, and accessories with a little history.',
        TRUE,
        'active'
    ),

    (
        15,
        'Theo',
        'Park',
        'cupboardcollective',
        'cupboardcollective@example.com',
        'DEV_ONLY_HASH_15',
        TRUE,
        '555-0115',
        '1996-10-16',
        NULL,
        'I collect useful kitchen extras and pass along the pieces I do not need. 
         Expect bakeware, mugs, cookware, and small appliances, with clear condition notes and measurements.',
        FALSE,
        'active'
    ),

    (
        16,
        'Hazel',
        'Collins',
        'campusflip',
        'campusflip@example.com',
        'DEV_ONLY_HASH_16',
        TRUE,
        '555-0116',
        '2003-01-21',
        NULL,
        'Affordable essentials for students.',
        TRUE,
        'active'
    ),

    (
        17,
        'Arjun',
        'Desai',
        'soundwave_stash',
        'soundwave_stash@example.com',
        'DEV_ONLY_HASH_17',
        TRUE,
        '555-0117',
        '1994-04-09',
        NULL,
        'Music lover trading headphones, speakers, cables, and beginner recording equipment. 
         I test audio gear before listing and mention any battery issues, scratches, or missing accessories.',
        FALSE,
        'active'
    ),

    (
        18,
        'Zoe',
        'Foster',
        'mossandmarble',
        'mossandmarble@example.com',
        'DEV_ONLY_HASH_18',
        TRUE,
        '555-0118',
        '1992-08-18',
        NULL,
        'I enjoy finding home pieces that look good without costing a fortune. My storefront features lamps, vases, baskets, little side tables, 
         and other decorating finds. Some pieces are nearly new; others have visible character from years of use. 
         I list dimensions whenever I can and flag anything fragile or awkward to transport, especially for local meetups.',
        TRUE,
        'active'
    ),

    (
        19,
        'Mateo',
        'Silva',
        'boardgamebunker',
        'boardgamebunker@example.com',
        'DEV_ONLY_HASH_19',
        TRUE,
        '555-0119',
        '1988-05-06',
        NULL,
        'Board games, puzzles, and tabletop extras. I count the pieces before I post each game.',
        FALSE,
        'active'
    ),

    (
        20,
        'Iris',
        'Walker',
        'clovercloset',
        'clovercloset@example.com',
        'DEV_ONLY_HASH_20',
        TRUE,
        '555-0120',
        '2000-12-02',
        NULL,
        'A mix of everyday fashion, thrifted favorites, and accessories that deserve a second home. 
        I include sizing details and photos of labels because fit varies so much between brands. I am always adding seasonal pieces as I reorganize my closet.',
        TRUE,
        'active'
    ),

    (
        21,
        'Jae',
        'Robinson',
        'wirednook',
        'wirednook@example.com',
        'DEV_ONLY_HASH_21',
        TRUE,
        '555-0121',
        '1997-01-11',
        NULL,
        'Small gadgets. Fair prices. Clear photos.',
        FALSE,
        'active'
    ),

    (
        22,
        'Avery',
        'Walsh',
        'second_chance_shelf',
        'second_chance_shelf@example.com',
        'DEV_ONLY_HASH_22',
        TRUE,
        '555-0122',
        '1990-09-25',
        NULL,
        'I sell a little bit of everything that still has plenty of life left, from study guides and desk organizers to collectibles 
         and household odds and ends. I prefer detailed listings over surprises: if there is a scratch, a worn corner, or a missing box, I will mention it. 
         Browse around and you might find exactly what your shelf needs.',
        TRUE,
        'active'
    ),

    (
        23,
        'Harper',
        'Sullivan',
        'thecuriousfox',
        'harper.sullivan@example.com',
        'DEV_ONLY_HASH_23',
        TRUE,
        '555-0123',
        '1997-09-14',
        NULL,
        'Welcome to my little corner of OmniBuy! I enjoy finding unique vintage pieces, handmade decorations, and interesting collectibles.
         My shop is a mix of nostalgic treasures and everyday discoveries. I believe every item has a story, and I hope you find something special here!',
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
    (3, 'CampusCloset', FALSE, 'DEV_PAYOUT_003'),
    (6, 'SuspendedSellerTest', FALSE, 'DEV_PAYOUT_006'),
    (7, 'Neon Attic', TRUE, 'DEV_PAYOUT_007'),
    (8, 'DeskCraft Daily', TRUE, 'DEV_PAYOUT_008'),
    (9, 'Paper Trail Books', FALSE, 'DEV_PAYOUT_009'),
    (10, 'Loop & Loom', FALSE, 'DEV_PAYOUT_010'),
    (11, 'Pixel Crate', TRUE, 'DEV_PAYOUT_011'),
    (12, 'Green Room Goods', FALSE, 'DEV_PAYOUT_012'),
    (13, 'Midnight Market', TRUE, 'DEV_PAYOUT_013'),
    (14, 'Vintage Vault', TRUE, 'DEV_PAYOUT_014'),
    (15, 'Cupboard Collective', FALSE, 'DEV_PAYOUT_015'),
    (16, 'Campus Flip', FALSE, 'DEV_PAYOUT_016'),
    (17, 'Soundwave Stash', TRUE, 'DEV_PAYOUT_017'),
    (18, 'Moss & Marble', FALSE, 'DEV_PAYOUT_018'),
    (19, 'Board Game Bunker', TRUE, 'DEV_PAYOUT_019'),
    (20, 'Clover Closet', FALSE, 'DEV_PAYOUT_020'),
    (21, 'Wired Nook', TRUE, 'DEV_PAYOUT_021'),
    (22, 'Second Chance Shelf', FALSE, 'DEV_PAYOUT_022'),
    (23, 'The Curious Fox', FALSE, 'DEV_PAYOUT_023');


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

    (12, 11, 'Trading Cards', 'Collectible trading cards including sports and gaming cards.', NULL),
    (13, 11, 'Action Figures',
     'Collectible action figures featuring characters from movies, television, comics, and popular franchises.',
     NULL);



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