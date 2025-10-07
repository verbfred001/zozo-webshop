-- Migration: create login_tokens table for magic-link authentication
CREATE TABLE IF NOT EXISTS login_tokens (
  token_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  klant_id BIGINT NULL,
  token_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  ip VARCHAR(45) NULL,
  user_agent TEXT NULL,
  INDEX (token_hash),
  INDEX (klant_id)
);

-- Note: run this migration manually (or via your migration tooling) against your database.
