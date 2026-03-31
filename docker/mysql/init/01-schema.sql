CREATE TABLE IF NOT EXISTS user (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    remember_token_hash CHAR(64) NULL,
    remember_token_expires_at DATETIME NULL,
    two_factor_email_enabled TINYINT(1) NOT NULL DEFAULT 0,
    two_factor_email_code_hash VARCHAR(255) NULL,
    two_factor_email_code_expires_at DATETIME NULL,
    two_factor_totp_secret VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role (role)
);

CREATE TABLE IF NOT EXISTS password_resets (
    user_id INT UNSIGNED NOT NULL PRIMARY KEY,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user
        FOREIGN KEY (user_id) REFERENCES user(id)
        ON DELETE CASCADE,
    INDEX idx_password_resets_token_hash (token_hash)
);
