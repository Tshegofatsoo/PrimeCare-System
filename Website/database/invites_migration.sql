-- ============================================================
-- PrimeCare — Staff invitations migration
-- Run ONCE against primecare_db (after schema.sql and staff_migration.sql).
--
-- Replaces the old single shared "clinic registration code" with
-- per-person invitation codes:
--   * issued by an ADMIN (Settings > Invite a staff member) or by the
--     command line (database/create-invite.php) for the very first admin
--   * tied to ONE email address and ONE role
--   * single use, expires after 48 hours
--   * only a keyed hash is stored, never the code itself
--   * locked after 5 wrong guesses
-- ============================================================
USE primecare_db;

CREATE TABLE IF NOT EXISTS staff_invites (
    invite_id       INT AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(100) NOT NULL,
    role            ENUM('ADMIN', 'DOCTOR') NOT NULL,
    code_hash       CHAR(64) NOT NULL,
    created_by      INT NULL,                       -- staff_id of the admin (NULL = command line)
    expires_at      DATETIME NOT NULL,
    used_at         DATETIME NULL,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invites_email (email, used_at),
    CONSTRAINT fk_invites_creator FOREIGN KEY (created_by) REFERENCES staff(staff_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;
