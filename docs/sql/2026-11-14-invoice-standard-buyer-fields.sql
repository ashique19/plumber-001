-- Standard B2B issue buyer contact + due date metadata. Safe to re-run.
-- Payload keys: buyer_phone, buyer_email, buyer_country (default SA), buyer_due_date (form hint).
-- Canonical due date: invoices.due_date (see 2026-11-14-invoice-due-date.sql).
-- Signing deferral: payload signing_submission_status = not_submitted when Z-Heart is unavailable.

SET NAMES utf8mb4;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'invoices'
      AND COLUMN_NAME = 'due_date'
);

SET @ddl := IF(
    @col_exists = 0,
    'ALTER TABLE `invoices` ADD COLUMN `due_date` DATE NULL AFTER `issued_at`',
    'DO 0'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;