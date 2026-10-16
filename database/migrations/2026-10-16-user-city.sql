-- Adds the optional users.city column (View User Profile) to a database
-- created before 2026-10-16. Fresh databases get it from
-- database/init/omnibuy_database.sql.
--
--   docker compose exec -T db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < database/migrations/2026-10-16-user-city.sql

ALTER TABLE users
    ADD COLUMN city VARCHAR(100) NULL DEFAULT NULL AFTER bio;
