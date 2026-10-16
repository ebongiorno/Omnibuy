-- Adds the 'deleted' account status (Delete Account) to a database created
-- before 2026-10-16. Fresh databases get it from database/init/omnibuy_database.sql.
--
--   docker compose exec -T db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < database/migrations/2026-10-16-account-deleted-status.sql

ALTER TABLE users
    MODIFY account_status ENUM(
        'active',
        'suspended',
        'restricted',
        'deleted'
    ) NOT NULL
        DEFAULT 'active';
