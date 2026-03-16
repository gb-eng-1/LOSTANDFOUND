-- Lost and Found System - Stored Procedures
-- These procedures handle all core business logic

-- ============================================================
-- DASHBOARD PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS sp_get_dashboard_stats;
DELIMITER //
CREATE PROCEDURE sp_get_dashboard_stats()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM items WHERE status = 'Found') as found_count,
        (SELECT COUNT(*) FROM items WHERE status = 'Lost') as lost_count,
        (SELECT COUNT(*) FROM items WHERE status = 'Resolved') as resolved_count;
END //
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_get_category_distribution;
DELIMITER //
CREATE PROCEDURE sp_get_category_distribution()
BEGIN
    SELECT 
        item_type as category,
        COUNT(*) as count,
        ROUND((COUNT(*) / (SELECT COUNT(*) FROM items WHERE status IN ('Found', 'Matched', 'Claimed')) * 100), 2) as percentage
    FROM items 
    WHERE status IN ('Found', 'Matched', 'Claimed')
    GROUP BY item_type
    ORDER BY count DESC;
END //
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_get_activity_feed;
DELIMITER //
CREATE PROCEDURE sp_get_activity_feed(IN days_ahead INT)
BEGIN
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
END //
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_get_student_dashboard_stats;
DELIMITER //
CREATE PROCEDURE sp_get_student_dashboard_stats(IN student_email VARCHAR(255))
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM items WHERE user_id = student_email AND status IN ('Lost', 'Matched', 'Claimed', 'Resolved', 'Cancelled')) as lost_reports,
        (SELECT COUNT(*) FROM items WHERE status IN ('Found', 'Matched')) as available_items,
        (SELECT COUNT(*) FROM claims WHERE student_id = (SELECT id FROM students WHERE email = student_email)) as submitted_claims,
        (SELECT COUNT(*) FROM claims WHERE student_id = (SELECT id FROM students WHERE email = student_email) AND status = 'Resolved') as resolved_claims;
END //
DELIMITER ;

-- ============================================================
-- FOUND ITEMS PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS sp_create_found_item;
DELIMITER //
CREATE PROCEDURE sp_create_found_item(
    IN p_brand VARCHAR(100),
    IN p_color VARCHAR(100),
    IN p_barcode VARCHAR(50),
    IN p_storage_location VARCHAR(200),
    IN p_item_type VARCHAR(100),
    IN p_item_description TEXT,
    IN p_found_at VARCHAR(200),
    IN p_found_by VARCHAR(200),
    OUT p_item_id VARCHAR(50)
)
BEGIN
    DECLARE v_next_id INT DEFAULT 1;
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(id, 3) AS UNSIGNED)), 0) + 1 INTO v_next_id 
    FROM items WHERE id LIKE 'UB%';
    
    SET p_item_id = CONCAT('UB', LPAD(v_next_id, 5, '0'));
    
    INSERT INTO items (
        id, item_type, color, brand, found_at, found_by,
        date_encoded, item_description, storage_location,
        status, disposal_deadline, created_at, updated_at
    ) VALUES (
        p_item_id, p_item_type, p_color, p_brand, p_found_at, p_found_by,
        CURDATE(), p_item_description, p_storage_location,
        'Found', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NOW()
    );
    
    INSERT INTO activity_log (item_id, action, actor_type, details, created_at)
    VALUES (p_item_id, 'encoded', 'admin', JSON_OBJECT('brand', p_brand, 'color', p_color), NOW());
END //
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_get_found_items;
DELIMITER //
CREATE PROCEDURE sp_get_found_items(
    IN p_status VARCHAR(50),
    IN p_item_type VARCHAR(100),
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT * FROM items 
    WHERE (p_status IS NULL OR status = p_status)
    AND (p_item_type IS NULL OR item_type = p_item_type)
    AND status IN ('Found', 'Matched', 'Claimed')
    ORDER BY date_encoded DESC, created_at DESC
    LIMIT p_limit OFFSET p_offset;
END //
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_get_unclaimed_items;
DELIMITER //
CREATE PROCEDURE sp_get_unclaimed_items(IN p_limit INT, IN p_offset INT)
BEGIN
    SELECT * FROM items 
    WHERE status IN ('Found', 'Matched')
    ORDER BY date_encoded DESC, created_at DESC
    LIMIT p_limit OFFSET p_offset;
END //
DELIMITER ;

-- ============================================================
-- LOST REPORT PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS sp_create_lost_report;
DELIMITER //
CREATE PROCEDURE sp_create_lost_report(
    IN p_user_id VARCHAR(100),
    IN p_item_type VARCHAR(100),
    IN p_item_description TEXT,
    IN p_found_at VARCHAR(200),
    IN p_color VARCHAR(100),
    IN p_brand VARCHAR(100),
    OUT p_report_id VARCHAR(50)
)
BEGIN
    DECLARE v_next_id INT DEFAULT 1;
    DECLARE v_desc_len INT;
    
    SET v_desc_len = LENGTH(p_item_description);
    
    IF v_desc_len < 10 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Description must be at least 10 characters';
    END IF;
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(id, 5) AS UNSIGNED)), 0) + 1 INTO v_next_id 
    FROM items WHERE id LIKE 'REF-%';
    
    SET p_report_id = CONCAT('REF-', LPAD(v_next_id, 5, '0'));
    
    INSERT INTO items (
        id, user_id, item_type, color, brand, found_at,
        date_lost, item_description,
        status, created_at, updated_at
    ) VALUES (
        p_report_id, p_user_id, p_item_type, p_color, p_brand, p_found_at,
        CURDATE(), p_item_description,
        'Lost', NOW(), NOW()
    );
    
    INSERT INTO activity_log (item_id, action, actor_type, details, created_at)
    VALUES (p_report_id, 'reported', 'student', JSON_OBJECT('description', p_item_description), NOW());
END //
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_get_student_reports;
DELIMITER //
CREATE PROCEDURE sp_get_student_reports(IN p_user_id VARCHAR(100), IN p_limit INT, IN p_offset INT)
BEGIN
    SELECT * FROM items 
    WHERE user_id = p_user_id
    AND status IN ('Lost', 'Matched', 'Claimed', 'Resolved', 'Cancelled')
    ORDER BY date_lost DESC, created_at DESC
    LIMIT p_limit OFFSET p_offset;
END //
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_cancel_lost_report;
DELIMITER //
CREATE PROCEDURE sp_cancel_lost_report(IN p_report_id VARCHAR(50))
BEGIN
    DECLARE v_status VARCHAR(50);
    
    SELECT status INTO v_status FROM items WHERE id = p_report_id;
    
    IF v_status NOT IN ('Lost', 'Matched') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Report cannot be cancelled in current status';
    END IF;
    
    UPDATE items SET status = 'Cancelled', updated_at = NOW() WHERE id = p_report_id;
    
    INSERT INTO activity_log (item_id, action, actor_type, details, created_at)
    VALUES (p_report_id, 'cancelled', 'student', NULL, NOW());
END //
DELIMITER ;

-- ============================================================
-- MATCHING PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS sp_find_matches_for_item;
DELIMITER //
CREATE PROCEDURE sp_find_matches_for_item(IN p_found_item_id VARCHAR(50))
BEGIN
    DECLARE v_item_type VARCHAR(100);
    DECLARE v_found_at VARCHAR(200);
    
    SELECT item_type, found_at INTO v_item_type, v_found_at 
    FROM items WHERE id = p_found_item_id;
    
    SELECT 
        p_found_item_id as found_item_id,
        id as lost_report_id,
        CASE 
            WHEN item_type = v_item_type AND found_at = v_found_at THEN 70
            WHEN item_type = v_item_type THEN 40
            ELSE 0
        END as confidence_score,
        JSON_OBJECT(
            'category', item_type = v_item_type,
            'location', found_at = v_found_at
        ) as matching_criteria
    FROM items 
    WHERE status IN ('Lost', 'Matched')
    AND item_type = v_item_type
    ORDER BY confidence_score DESC;
END //
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_create_match;
DELIMITER //
CREATE PROCEDURE sp_create_match(
    IN p_lost_report_id VARCHAR(50),
    IN p_found_item_id VARCHAR(50),
    IN p_confidence_score DECIMAL(5,2),
    IN p_matching_criteria JSON
)
BEGIN
    INSERT INTO matches (
        lost_report_id, found_item_id, confidence_score, matching_criteria, status, created_at, updated_at
    ) VALUES (
        p_lost_report_id, p_found_item_id, p_confidence_score, p_matching_criteria, 'Pending_Review', NOW(), NOW()
    );
    
    INSERT INTO notifications (
        recipient_id, recipient_type, type, title, message, related_id, is_read, created_at
    ) SELECT 
        id, 'admin', 'match_found', 
        'New Match Found',
        CONCAT('Found item ', p_found_item_id, ' matches report ', p_lost_report_id, ' with ', p_confidence_score, '% confidence'),
        LAST_INSERT_ID(),
        false,
        NOW()
    FROM admins LIMIT 1;
    
    INSERT INTO activity_log (item_id, action, actor_type, details, created_at)
    VALUES (p_found_item_id, 'matched', 'system', JSON_OBJECT('report_id', p_lost_report_id, 'confidence', p_confidence_score), NOW());
END //
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_approve_match;
DELIMITER //
CREATE PROCEDURE sp_approve_match(IN p_match_id INT)
BEGIN
    DECLARE v_lost_report_id VARCHAR(50);
    DECLARE v_found_item_id VARCHAR(50);
    
    SELECT lost_report_id, found_item_id INTO v_lost_report_id, v_found_item_id 
    FROM matches WHERE id = p_match_id;
    
    UPDATE matches SET status = 'Approved', updated_at = NOW() WHERE id = p_match_id;
    UPDATE items SET status = 'Matched', updated_at = NOW() WHERE id = v_found_item_id;
    UPDATE items SET status = 'Matched', updated_at = NOW() WHERE id = v_lost_report_id;
    
    INSERT INTO activity_log (item_id, action, actor_type, details, created_at)
    VALUES (v_found_item_id, 'match_approved', 'admin', JSON_OBJECT('report_id', v_lost_report_id), NOW());
END //
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_reject_match;
DELIMITER //
CREATE PROCEDURE sp_reject_match(IN p_match_id INT)
BEGIN
    UPDATE matches SET status = 'Rejected', updated_at = NOW() WHERE id = p_match_id;
END //
DELIMITER ;

-- ============================================================
-- CLAIM PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS sp_create_claim;
DELIMITER //
CREATE PROCEDURE sp_create_claim(
    IN p_student_id INT,
    IN p_found_item_id VARCHAR(50),
    IN p_lost_report_id VARCHAR(50),
    IN p_proof_description TEXT,
    OUT p_reference_id VARCHAR(50)
)
BEGIN
    DECLARE v_next_id INT DEFAULT 1;
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(reference_id, 11) AS UNSIGNED)), 0) + 1 INTO v_next_id 
    FROM claims WHERE reference_id LIKE 'REF-CLAIM-%';
    
    SET p_reference_id = CONCAT('REF-CLAIM-', LPAD(v_next_id, 5, '0'));
    
    INSERT INTO claims (
        reference_id, student_id, found_item_id, lost_report_id, proof_description,
        status, claim_date, created_at, updated_at
    ) VALUES (
        p_reference_id, p_student_id, p_found_item_id, p_lost_report_id, p_proof_description,
        'Pending', NOW(), NOW(), NOW()
    );
    
    INSERT INTO notifications (
        recipient_id, recipient_type, type, title, message, related_id, is_read, created_at
    ) SELECT 
        id, 'admin', 'claim_submitted',
        'New Claim Submitted',
        CONCAT('Student submitted claim for item ', p_found_item_id),
        LAST_INSERT_ID(),
        false,
        NOW()
    FROM admins LIMIT 1;
    
    INSERT INTO activity_log (item_id, action, actor_type, details, created_at)
    VALUES (p_found_item_id, 'claimed', 'student', JSON_OBJECT('student_id', p_student_id), NOW());
END //
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_resolve_claim;
DELIMITER //
CREATE PROCEDURE sp_resolve_claim(IN p_claim_id INT)
BEGIN
    DECLARE v_reference_id VARCHAR(50);
    DECLARE v_student_id INT;
    DECLARE v_found_item_id VARCHAR(50);
    DECLARE v_student_name VARCHAR(100);
    DECLARE v_student_email VARCHAR(255);
    
    SELECT reference_id, student_id, found_item_id INTO v_reference_id, v_student_id, v_found_item_id
    FROM claims WHERE id = p_claim_id;
    
    SELECT name, email INTO v_student_name, v_student_email
    FROM students WHERE id = v_student_id;
    
    UPDATE claims SET status = 'Resolved', resolution_date = NOW(), updated_at = NOW() WHERE id = p_claim_id;
    
    INSERT INTO archives (
        reference_id, found_item_id, student_id, claimant_name, claimant_email,
        item_details, claim_date, resolution_date, created_at
    ) SELECT 
        v_reference_id, id, v_student_id, v_student_name, v_student_email,
        JSON_OBJECT('brand', brand, 'color', color, 'item_type', item_type, 'storage_location', storage_location),
        NOW(), NOW(), NOW()
    FROM items WHERE id = v_found_item_id;
    
    UPDATE items SET status = 'Archived', updated_at = NOW() WHERE id = v_found_item_id;
    
    INSERT INTO notifications (
        recipient_id, recipient_type, type, title, message, related_id, is_read, created_at
    ) VALUES (
        v_student_id, 'student', 'claim_resolved',
        'Claim Resolved',
        CONCAT('Your claim ', v_reference_id, ' has been resolved. Item is ready for pickup.'),
        p_claim_id,
        false,
        NOW()
    );
    
    INSERT INTO activity_log (item_id, action, actor_type, details, created_at)
    VALUES (v_found_item_id, 'resolved', 'admin', JSON_OBJECT('claim_id', p_claim_id), NOW());
END //
DELIMITER ;

-- ============================================================
-- ARCHIVE PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS sp_search_archives;
DELIMITER //
CREATE PROCEDURE sp_search_archives(
    IN p_reference_id VARCHAR(50),
    IN p_claimant_name VARCHAR(100),
    IN p_date_from DATE,
    IN p_date_to DATE,
    IN p_category VARCHAR(100),
    IN p_limit INT,
    IN p_offset INT
)
BEGIN
    SELECT * FROM archives 
    WHERE (p_reference_id IS NULL OR reference_id = p_reference_id)
    AND (p_claimant_name IS NULL OR claimant_name LIKE CONCAT('%', p_claimant_name, '%'))
    AND (p_date_from IS NULL OR resolution_date >= p_date_from)
    AND (p_date_to IS NULL OR resolution_date <= p_date_to)
    AND (p_category IS NULL OR JSON_EXTRACT(item_details, '$.item_type') = p_category)
    ORDER BY resolution_date DESC
    LIMIT p_limit OFFSET p_offset;
END //
DELIMITER ;
