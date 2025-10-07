-- Migration: add password_hash and last_login to klanten
ALTER TABLE klanten
  ADD COLUMN password_hash VARCHAR(255) NULL,
  ADD COLUMN last_login DATETIME NULL;

-- Run this migration manually against your database.
