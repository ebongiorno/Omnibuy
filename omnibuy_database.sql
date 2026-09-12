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
    description TEXT NULL,
    image_url VARCHAR(2000) NULL,

    CONSTRAINT fk_categories_parent
        FOREIGN KEY (parent_category_id)
        REFERENCES categories(category_id),
    
    CONSTRAINT uq_categories_parent_name
        UNIQUE (parent_category_id, category_name),

    CONSTRAINT chk_categories_not_self_parent
        CHECK (
            parent_category_id IS NULL
            OR parent_category_id <> category_id
        )
    
);


-- =========================================
-- LISTINGS AND SELLER MANAGEMENT
-- =========================================
-- listings
-- listing_images
-- listing_price_history
-- bids


-- =========================================
-- MARKETPLACE DISCOVERY
-- =========================================
-- search_history
CREATE TABLE search_history (
    search_history_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    search_query VARCHAR(255) NOT NULL, 
    search_type ENUM('items', 'seller_profiles') NOT NULL DEFAULT 'items',
    searched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_search_history_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
);

-- listing_views
CREATE TABLE listing_views (
    listing_view_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    viewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_listing_views_user
    FOREIGN KEY (user_id)
    REFERENCES users(user_id),

    CONSTRAINT fk_listing_views_listing
        FOREIGN KEY (listing_id)
        REFERENCES listings(listing_id)
);


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
CREATE TABLE orders (
    order_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_user_id BIGINT UNSIGNED NOT NULL,
    shipping_address_id BIGINT UNSIGNED NULL DEFAULT NULL,
    billing_address_id BIGINT UNSIGNED NOT NULL

    order_status ENUM(
        'pending',
        'in_progress',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    payment_status ENUM(
        'pending',
        'paid',
        'failed',
        'refunded'
    ) NOT NULL DEFAULT 'pending',
    subtotal DECIMAL(10, 2)
        NOT NULL
        CHECK (subtotal > 0),
    shipping_cost DECIMAL(10, 2)
        NOT NULL
        DEFAULT 0.00
        CHECK (shipping_cost >= 0),
    tax_amount DECIMAL(10, 2)
        NOT NULL
        DEFAULT 0.00
        CHECK (tax_amount >= 0),
    total_amount DECIMAL(10, 2)
        NOT NULL
        CHECK (total_amount >= 0),
    created_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP
        NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_orders_buyer
        FOREIGN KEY (buyer_user_id)
        REFERENCES users(user_id),
    CONSTRAINT fk_orders_shipping_address
        FOREIGN KEY (shipping_address_id)
        REFERENCES addresses(address_id),
    CONSTRAINT fk_orders_billing_address
        FOREIGN KEY (billing_address_id)
        REFERENCES addresses(address_id)
);

CREATE TABLE order_items (
    order_items_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    quantity INT UNSIGNED
        NOT NULL
        CHECK  (quantity > 0),
    unit_price DECIMAL(10, 2)
        NOT NULL
        CHECK (unit_price > 0),
    fullfillment_type ENUM(
        'shipping',
        'meetup'
    ) NOT NULL
    item_status ENUM(
        'pending',
        'confirmed',
        'processing',
        'shipped',
        'ready_for_meetup',
        'completed',
        'cancelled'
    ) NOT NULL DEFAULT 'pending',
    shipping_cost DECIMAL(10, 2)
        NOT NULL
        DEFAULT 0.00
        CHECK (shipping_cost >= 0),
    
    CONSTRAINT chk_meetup_shipping_cost
        CHECK (
        fulfillment_type <> 'meetup'
        OR shipping_cost = 0.00
        ),
    CONSTRAINT chk_fulfillment_item_status
        CHECK (
            NOT (
                fulfillment_type = 'meetup'
                AND item_status = 'shipped'
            )
            AND
            NOT (
                fulfillment_type = 'shipping'
                AND item_status = 'ready_for_meetup'
            )
        ),
    
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id)
        REFERENCES orders(order_id),
    CONSTRAINT fk_order_items_listing
        FOREIGN KEY (listing_id)
        REFERENCES listings(listing_id)
);


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