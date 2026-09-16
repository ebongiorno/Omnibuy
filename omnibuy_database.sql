-- Omnibuy Database
-- CPSC 491, Section 10

CREATE DATABASE IF NOT EXISTS omnibuy;
USE omnibuy;

-- =========================================
-- USERS AND PROFILES
-- =========================================
CREATE TABLE users (
    user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_email_verified BOOLEAN NOT NULL
        DEFAULT FALSE,
    phone_number VARCHAR(20) NOT NULL,
    birth_date DATE NOT NULL,
    profile_image_url VARCHAR(2000) NULL,
    bio TEXT NULL
        DEFAULT NULL,
    is_last_name_hidden BOOLEAN NOT NULL
        DEFAULT FALSE,
    account_status ENUM(
        'active',
        'suspended',
        'restricted'
    ) NOT NULL
        DEFAULT 'active',
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


-- =========================================================
-- BIRTH DATE VALIDATION
-- Original schema rule:
-- birth_date <= CURRENT_DATE
--
-- MySQL does not reliably support CURRENT_DATE() inside a
-- CHECK constraint, so triggers are used instead.
-- =========================================================

DELIMITER $$

CREATE TRIGGER trg_users_birth_date_insert
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    IF NEW.birth_date > CURRENT_DATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'birth_date cannot be in the future';
    END IF;
END$$

CREATE TRIGGER trg_users_birth_date_update
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    IF NEW.birth_date > CURRENT_DATE() THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'birth_date cannot be in the future';
    END IF;
END$$
DELIMITER ;

CREATE TABLE seller_profiles (
    user_id BIGINT UNSIGNED NOT NULL,
    store_name VARCHAR(100) NOT NULL,
    became_seller_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,
    is_verified BOOLEAN NOT NULL
        DEFAULT FALSE,
    payout_account_reference VARCHAR(100) NOT NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_seller_profiles_store_name (
        store_name
    ),
    UNIQUE KEY uq_seller_profiles_payout_reference (
        payout_account_reference
    ),
    CONSTRAINT fk_seller_profiles_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


-- =========================================
-- CATEGORIES
-- =========================================
-- categories
CREATE TABLE categories (
    category_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_category_id BIGINT UNSIGNED NULL, 
    category_name VARCHAR(100) NOT NULL,
    description TEXT NULL DEFAULT NULL,
    image_url VARCHAR(2000) NULL

    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_category_ai)
        REFERENCES categories(category_id)
    
)

CREATE TABLE listings (
    listing_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    seller_user_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    item_description TEXT NULL,

    sale_type ENUM(
        'auction',
        'fixed_price'
    ) NOT NULL,

    fulfillment_type ENUM(
        'shipping',
        'meetup'
    ) NOT NULL,

    shipping_cost DECIMAL(10, 2) NULL,
    estimated_shipping_days TINYINT UNSIGNED NULL,

    location_zip_code VARCHAR(10) NULL,
    latitude DECIMAL(9, 6) NULL,
    longitude DECIMAL(9, 6) NULL,

    meetup_location_type ENUM(
        'coffee_shop',
        'parking_lot',
        'library',
        'other'
    ) NULL,

    seller_meetup_radius SMALLINT UNSIGNED NULL,
    auction_end_at TIMESTAMP NULL,
    price DECIMAL(10, 2) NOT NULL,

    `condition` ENUM(
        'new',
        'open_box',
        'like_new',
        'excellent',
        'good',
        'fair',
        'poor',
        'refurbished',
        'for_parts'
    ) NOT NULL,

    quantity INT UNSIGNED NOT NULL,

    listing_status ENUM(
        'draft',
        'active',
        'hidden',
        'sold',
        'archived'
    ) NOT NULL,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    renewed_at TIMESTAMP NULL,

    CONSTRAINT fk_listings_seller
        FOREIGN KEY (seller_user_id)
        REFERENCES seller_profiles(user_id),

    CONSTRAINT fk_listings_category
        FOREIGN KEY (category_id)
        REFERENCES categories(category_id),

    CONSTRAINT chk_listings_shipping_cost
        CHECK (shipping_cost >= 0),

    CONSTRAINT chk_listings_shipping_days
        CHECK (estimated_shipping_days > 0),

    CONSTRAINT chk_listings_seller_meetup_radius
        CHECK (seller_meetup_radius > 0),

    CONSTRAINT chk_listings_price
        CHECK (price > 0),

    CONSTRAINT chk_listings_quantity
        CHECK (quantity >= 0)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


CREATE TABLE listing_images (
    listing_image_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    listing_id BIGINT UNSIGNED NOT NULL,

    image_url VARCHAR(2000)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL,

    display_order TINYINT UNSIGNED NOT NULL,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_listing_images_listing
        FOREIGN KEY (listing_id)
        REFERENCES listings(listing_id),

    CONSTRAINT chk_listing_images_display_order
        CHECK (display_order >= 1),

    CONSTRAINT uq_listing_images_url
        UNIQUE (listing_id, image_url),

    CONSTRAINT uq_listing_images_display_order
        UNIQUE (listing_id, display_order)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


CREATE TABLE listing_price_history (
    listing_price_history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    listing_id BIGINT UNSIGNED NOT NULL,
    price DECIMAL(10, 2) NOT NULL,

    changed_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_listing_price_history_listing
        FOREIGN KEY (listing_id)
        REFERENCES listings(listing_id),

    CONSTRAINT chk_listing_price_history_price
        CHECK (price > 0)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


CREATE TABLE bids (
    bid_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    listing_id BIGINT UNSIGNED NOT NULL,
    bidder_user_id BIGINT UNSIGNED NOT NULL,
    bid_amount DECIMAL(10, 2) NOT NULL,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_bids_listing
        FOREIGN KEY (listing_id)
        REFERENCES listings(listing_id),

    CONSTRAINT fk_bids_bidder
        FOREIGN KEY (bidder_user_id)
        REFERENCES users(user_id),

    CONSTRAINT chk_bids_bid_amount
        CHECK (bid_amount > 0)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

-- =========================================
-- MARKETPLACE DISCOVERY
-- =========================================
-- search_history
-- listing_views


-- =========================================
-- CARTS AND CART ITEMS
-- =========================================
-- carts
-- cart_items


-- =========================================
-- ADDRESSES AND LOCATION
-- =========================================
-- addresses


-- =========================================
-- ORDERS AND FULFILLMENT
-- =========================================
-- orders
-- order_items


-- =========================================
-- REVIEWS AND REPUTATION
-- =========================================
CREATE TABLE user_reviews (
    user_review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reviewer_user_id BIGINT UNSIGNED NOT NULL,
    reviewed_user_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,
    star_rating TINYINT UNSIGNED NOT NULL,
    title VARCHAR(100) NULL
        DEFAULT NULL,
    description VARCHAR(2000) NULL
        DEFAULT NULL,
    PRIMARY KEY (user_review_id),
    CONSTRAINT chk_user_reviews_different_users
        CHECK (
            reviewer_user_id <> reviewed_user_id
        ),
    CONSTRAINT chk_user_reviews_star_rating
        CHECK (
            star_rating BETWEEN 1 AND 5
        ),
    CONSTRAINT uq_user_reviews_order_item
        UNIQUE (
            reviewer_user_id,
            order_item_id
        ),
    CONSTRAINT fk_user_reviews_reviewer
        FOREIGN KEY (reviewer_user_id)
        REFERENCES users(user_id),
    CONSTRAINT fk_user_reviews_reviewed
        FOREIGN KEY (reviewed_user_id)
        REFERENCES users(user_id),
    CONSTRAINT fk_user_reviews_order_item
        FOREIGN KEY (order_item_id)
        REFERENCES order_items(order_item_id)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

CREATE TABLE listing_reviews (
    listing_review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reviewer_user_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,
    star_rating TINYINT UNSIGNED NOT NULL,
    title VARCHAR(100) NULL
        DEFAULT NULL,
    description TEXT NULL
        DEFAULT NULL,
    PRIMARY KEY (listing_review_id),
    CONSTRAINT chk_listing_reviews_star_rating
        CHECK (
            star_rating BETWEEN 1 AND 5
        ),
    CONSTRAINT uq_listing_reviews_order_item
        UNIQUE (
            reviewer_user_id,
            order_item_id
        ),
    CONSTRAINT fk_listing_reviews_reviewer
        FOREIGN KEY (reviewer_user_id)
        REFERENCES users(user_id),
    CONSTRAINT fk_listing_reviews_order_item
        FOREIGN KEY (order_item_id)
        REFERENCES order_items(order_item_id)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;


-- =========================================
-- FAVORITES
-- =========================================
-- wishlist_items


-- =========================================
-- MESSAGING
-- =========================================
-- conversations
-- messages


-- =========================================
-- SAFETY AND MODERATION
-- =========================================
-- user_blocks
-- user_reports