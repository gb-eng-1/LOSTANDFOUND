-- Lost and Found System - Stored Procedures (Simplified)
-- Import this file directly in phpMyAdmin

-- Dashboard: Get statistics
DROP PROCEDURE IF EXISTS sp_get_dashboard_stats;
CREATE PROCEDURE sp_get_dashboard_stats()
SELECT 
    (SELECT COUNT(*) FROM items WHERE status = 'Found') as found_count,
    (SELECT COUNT(*) FROM items WHERE status = 'Lost') as lost_count,
    (SELECT COUNT(*) FROM items WHERE status = 'Resolved') as resolved_count;

-- Dashboard: Get category distribution
DROP PROCEDURE IF EXISTS sp_get_category_distribution;
CREATE PROCEDURE sp_get_category_distribution()
SELECT 
    item_type as category,
    COUNT(*) as count,
    ROUND((COUNT(*) / (SELECT COUNT(*) FROM items WHERE status IN ('Found', 'Matched', 'Claimed')) * 100), 2) as percentage
FROM items 
WHERE status IN ('Found', 'Matched', 'Claimed')
GROUP BY item_type
ORDER BY count DESC;

-- Dashboard: Get activity feed
DROP PROCEDURE IF EXISTS sp_get_activity_feed;
CREATE PROCEDURE sp_get_activity_feed(IN days_ahead INT)
SELECT 
    id,
    brand,
    color,
    item_type,
    status,
    disposal_deadline,
    DATEDIFF(disposal_deadline, NOW()) as days_remaining,
    created_at
FROM items 
WHERE status IN ('Found', 'Matched')
AND disposal_deadline IS NOT NULL
AND disposal_deadline <= DATE_ADD(NOW(), INTERVAL days_ahead DAY)
AND disposal_deadline > NOW()
ORDER BY disposal_deadline ASC;

-- Dashboard: Get student stats
DROP PROCEDURE IF EXISTS sp_get_student_dashboard_stats;
CREATE PROCEDURE sp_get_student_dashboard_stats(IN student_email VARCHAR(255))
SELECT 
    (SELECT COUNT(*) FROM items WHERE user_id = student_email AND status IN ('Lost', 'Matched', 'Claimed', 'Resolved', 'Cancelled')) as lost_reports,
    (SELECT COUNT(*) FROM items WHERE status IN ('Found', 'Matched')) as available_items,
    (SELECT COUNT(*) FROM claims WHERE student_id = (SELECT id FROM students WHERE email = student_email)) as submitted_claims,
    (SELECT COUNT(*) FROM claims WHERE student_id = (SELECT id FROM students WHERE email = student_email) AND status = 'Resolved') as resolved_claims;

-- Found Items: Get all with filters
DROP PROCEDURE IF EXISTS sp_get_found_items;
CREATE PROCEDURE sp_get_found_items(
    IN p_status VARCHAR(50),
    IN p_item_type VARCHAR(100),
    IN p_limit INT,
    IN p_offset INT
)
SELECT * FROM items 
WHERE (p_status IS NULL OR status = p_status)
AND (p_item_type IS NULL OR item_type = p_item_type)
AND status IN ('Found', 'Matched', 'Claimed')
ORDER BY date_encoded DESC, created_at DESC
LIMIT p_limit OFFSET p_offset;

-- Found Items: Get unclaimed
DROP PROCEDURE IF EXISTS sp_get_unclaimed_items;
CREATE PROCEDURE sp_get_unclaimed_items(IN p_limit INT, IN p_offset INT)
SELECT * FROM items 
WHERE status IN ('Found', 'Matched')
ORDER BY date_encoded DESC, created_at DESC
LIMIT p_limit OFFSET p_offset;

-- Lost Reports: Get by student
DROP PROCEDURE IF EXISTS sp_get_student_reports;
CREATE PROCEDURE sp_get_student_reports(IN p_user_id VARCHAR(100), IN p_limit INT, IN p_offset INT)
SELECT * FROM items 
WHERE user_id = p_user_id
AND status IN ('Lost', 'Matched', 'Claimed', 'Resolved', 'Cancelled')
ORDER BY date_lost DESC, created_at DESC
LIMIT p_limit OFFSET p_offset;

-- Matches: Get all
DROP PROCEDURE IF EXISTS sp_get_all_matches;
CREATE PROCEDURE sp_get_all_matches(IN p_limit INT, IN p_offset INT)
SELECT * FROM matches 
ORDER BY created_at DESC
LIMIT p_limit OFFSET p_offset;

-- Matches: Get by report
DROP PROCEDURE IF EXISTS sp_get_matches_by_report;
CREATE PROCEDURE sp_get_matches_by_report(IN p_lost_report_id VARCHAR(50))
SELECT * FROM matches 
WHERE lost_report_id = p_lost_report_id 
ORDER BY confidence_score DESC;

-- Matches: Get by item
DROP PROCEDURE IF EXISTS sp_get_matches_by_item;
CREATE PROCEDURE sp_get_matches_by_item(IN p_found_item_id VARCHAR(50))
SELECT * FROM matches 
WHERE found_item_id = p_found_item_id 
ORDER BY confidence_score DESC;

-- Claims: Get by student
DROP PROCEDURE IF EXISTS sp_get_student_claims;
CREATE PROCEDURE sp_get_student_claims(IN p_student_id INT, IN p_limit INT, IN p_offset INT)
SELECT * FROM claims 
WHERE student_id = p_student_id
ORDER BY claim_date DESC
LIMIT p_limit OFFSET p_offset;

-- Claims: Get all (admin)
DROP PROCEDURE IF EXISTS sp_get_all_claims;
CREATE PROCEDURE sp_get_all_claims(IN p_limit INT, IN p_offset INT)
SELECT * FROM claims 
ORDER BY claim_date DESC
LIMIT p_limit OFFSET p_offset;

-- Archive: Search
DROP PROCEDURE IF EXISTS sp_search_archives;
CREATE PROCEDURE sp_search_archives(
    IN p_reference_id VARCHAR(50),
    IN p_claimant_name VARCHAR(100),
    IN p_date_from DATE,
    IN p_date_to DATE,
    IN p_category VARCHAR(100),
    IN p_limit INT,
    IN p_offset INT
)
SELECT * FROM archives 
WHERE (p_reference_id IS NULL OR reference_id = p_reference_id)
AND (p_claimant_name IS NULL OR claimant_name LIKE CONCAT('%', p_claimant_name, '%'))
AND (p_date_from IS NULL OR resolution_date >= p_date_from)
AND (p_date_to IS NULL OR resolution_date <= p_date_to)
AND (p_category IS NULL OR JSON_EXTRACT(item_details, '$.item_type') = p_category)
ORDER BY resolution_date DESC
LIMIT p_limit OFFSET p_offset;

-- Archive: Get by student
DROP PROCEDURE IF EXISTS sp_get_student_archives;
CREATE PROCEDURE sp_get_student_archives(IN p_student_id INT, IN p_limit INT, IN p_offset INT)
SELECT * FROM archives 
WHERE student_id = p_student_id
ORDER BY resolution_date DESC
LIMIT p_limit OFFSET p_offset;

-- Support: Get contacts
DROP PROCEDURE IF EXISTS sp_get_support_contacts;
CREATE PROCEDURE sp_get_support_contacts()
SELECT * FROM support_contacts ORDER BY department, name;

-- Support: Get guides
DROP PROCEDURE IF EXISTS sp_get_process_guides;
CREATE PROCEDURE sp_get_process_guides(IN p_section VARCHAR(100))
SELECT * FROM process_guides 
WHERE (p_section IS NULL OR section = p_section)
ORDER BY step_number ASC;
