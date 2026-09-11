-- Omnibuy Database
-- CPSC 491, Section 10

CREATE DATABASE IF NOT EXISTS omnibuy;
USE omnibuy;

-- =========================================
-- USERS AND PROFILES
-- =========================================
-- users
-- seller_profiles


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
-- user_reviews
-- listing_reviews


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