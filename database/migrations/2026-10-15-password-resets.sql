-- Adds the UC-03 password_resets table to a database created before 2026-10-15.
-- Fresh databases get it from database/init/omnibuy_database.sql.
--
--   docker compose exec -T db sh -c 'mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' < database/migrations/2026-10-15-password-resets.sql

-- =========================================================
-- PASSWORD RESETS (UC-03)
-- Only a SHA-256 hash of each emailed token is stored, so a
-- leaked table can't be used to reset passwords. A token
-- works once (used_at) and only until expires_at.
-- =========================================================
CREATE TABLE IF NOT EXISTS password_resets (
    reset_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,

    UNIQUE KEY uq_password_resets_token_hash (token_hash),
    KEY idx_password_resets_user_created (user_id, created_at),
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;
