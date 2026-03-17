-- ============================================================
--  UB Lost & Found — TEST RESET SCRIPT
--  Clears all transactional data while keeping accounts,
--  configuration, and schema intact.
--
--  Run in phpMyAdmin → lostandfound_db → SQL tab.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Transactional / user-generated data
TRUNCATE TABLE `items`;          -- all found items + lost reports (REF-)
TRUNCATE TABLE `matches`;        -- match pairs (migration 004)
TRUNCATE TABLE `claims`;         -- student claim requests
TRUNCATE TABLE `archives`;       -- resolved/archived claim snapshots
TRUNCATE TABLE `notifications`;  -- all notifications (admin + student)
TRUNCATE TABLE `activity_log`;   -- audit trail

-- item_matches is created at runtime by the app (AdminDashboard link feature).
-- Truncate only if the table exists.
DROP TABLE IF EXISTS `item_matches`;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Confirm row counts (all should be 0) ───────────────────
SELECT 'items'         AS tbl, COUNT(*) AS row_count FROM items
UNION ALL
SELECT 'matches',              COUNT(*)          FROM matches
UNION ALL
SELECT 'claims',               COUNT(*)          FROM claims
UNION ALL
SELECT 'archives',             COUNT(*)          FROM archives
UNION ALL
SELECT 'notifications',        COUNT(*)          FROM notifications
UNION ALL
SELECT 'activity_log',         COUNT(*)          FROM activity_log;

-- ── These are intentionally NOT cleared ────────────────────
-- admins          → admin login accounts stay
-- students        → student login accounts stay
-- support_contacts→ Help & Support page config stays
-- process_guides  → How-to guides stay
