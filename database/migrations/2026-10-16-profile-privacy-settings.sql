-- Adds the privacy settings for city, email, phone and bio to a database
-- created before 2026-10-16. Fresh databases get them from
-- database/init/omnibuy_database.sql. Everything stays visible until the
-- user turns it off.
--
--   docker compose exec -T db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < database/migrations/2026-10-16-profile-privacy-settings.sql

ALTER TABLE users
    ADD COLUMN is_city_hidden BOOLEAN NOT NULL DEFAULT FALSE AFTER is_last_name_hidden,
    ADD COLUMN is_email_hidden BOOLEAN NOT NULL DEFAULT FALSE AFTER is_city_hidden,
    ADD COLUMN is_phone_hidden BOOLEAN NOT NULL DEFAULT FALSE AFTER is_email_hidden,
    ADD COLUMN is_bio_hidden BOOLEAN NOT NULL DEFAULT FALSE AFTER is_phone_hidden;
