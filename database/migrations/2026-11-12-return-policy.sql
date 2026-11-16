-- Return policy: sellers choose 0-30 days per listing (0 = no returns), and
-- each order item keeps the window it was bought with, so a seller changing
-- the listing later doesn't change past purchases. Adds the columns to a
-- database created before 2026-11-12. Fresh databases get them from
-- database/init/omnibuy_database.sql.
--
--   docker compose exec -T db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < database/migrations/2026-11-12-return-policy.sql

ALTER TABLE listings
    ADD COLUMN return_policy_days TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity,
    ADD CONSTRAINT chk_listings_return_policy_days CHECK (return_policy_days <= 30);

ALTER TABLE order_items
    ADD COLUMN return_window_days TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER shipping_cost,
    ADD COLUMN cancelled_at TIMESTAMP NULL DEFAULT NULL AFTER return_window_days,
    ADD CONSTRAINT chk_order_items_return_window_days CHECK (return_window_days <= 30);
