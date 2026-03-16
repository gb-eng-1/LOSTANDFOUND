<?php
/**
 * Notification Helper
 * Provides utility functions for creating different types of notifications
 */

class NotificationHelper {
    
    /**
     * Create a notification
     */
    public static function createNotification($pdo, $recipientId, $recipientType, $type, $title, $message, $relatedId = null) {
        try {
            $sql = "INSERT INTO notifications (recipient_id, recipient_type, type, title, message, related_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$recipientId, $recipientType, $type, $title, $message, $relatedId]);
            
            return $pdo->lastInsertId();
        } catch (Exception $e) {
            error_log('Failed to create notification: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create match found notification for admin
     */
    public static function createMatchFoundNotification($pdo, $matchId, $lostReportId, $foundItemId) {
        $title = 'New Match Found';
        $message = "A potential match has been found between lost report $lostReportId and found item $foundItemId. Please review.";
        
        return self::createNotification($pdo, 1, 'admin', 'match_found', $title, $message, $matchId);
    }
    
    /**
     * Create match approved notification for student
     */
    public static function createMatchApprovedNotification($pdo, $studentId, $matchId, $foundItemId) {
        $title = 'Match Approved';
        $message = "Your lost item report has been matched with found item $foundItemId. You can now submit a claim.";
        
        return self::createNotification($pdo, $studentId, 'student', 'match_approved', $title, $message, $matchId);
    }
    
    /**
     * Create match rejected notification for student
     */
    public static function createMatchRejectedNotification($pdo, $studentId, $matchId) {
        $title = 'Match Rejected';
        $message = 'A potential match for your lost item was reviewed but not confirmed. We will continue looking for matches.';
        
        return self::createNotification($pdo, $studentId, 'student', 'match_rejected', $title, $message, $matchId);
    }
    
    /**
     * Create claim approved notification for student
     */
    public static function createClaimApprovedNotification($pdo, $studentId, $claimId, $foundItemId) {
        $title = 'Claim Approved';
        $message = "Your claim for found item $foundItemId has been approved. Please check your claim details for pickup instructions.";
        
        return self::createNotification($pdo, $studentId, 'student', 'claim_approved', $title, $message, $claimId);
    }
    
    /**
     * Create claim rejected notification for student
     */
    public static function createClaimRejectedNotification($pdo, $studentId, $claimId, $reason = null) {
        $title = 'Claim Rejected';
        $message = 'Your claim has been rejected.' . ($reason ? " Reason: $reason" : ' Please contact support for more information.');
        
        return self::createNotification($pdo, $studentId, 'student', 'claim_rejected', $title, $message, $claimId);
    }
    
    /**
     * Create item disposal warning notification for admin
     */
    public static function createDisposalWarningNotification($pdo, $itemCount) {
        $title = 'Item Disposal Warning';
        $message = "$itemCount item(s) are approaching their disposal deadline. Please review items that may need to be disposed of.";
        
        return self::createNotification($pdo, 1, 'admin', 'item_disposal_warning', $title, $message);
    }
    
    /**
     * Get student ID from email
     */
    public static function getStudentIdFromEmail($pdo, $email) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
            $stmt->execute([$email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['id'] : null;
        } catch (Exception $e) {
            error_log('Failed to get student ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get student ID from a lost report ID
     */
    public static function getStudentIdFromReportId($pdo, $reportId) {
        try {
            // First, get the user_id (email) from the items table
            $itemStmt = $pdo->prepare("SELECT user_id FROM items WHERE id = ?");
            $itemStmt->execute([$reportId]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item || !$item['user_id']) {
                return null;
            }
            
            // Then, get the student id from the students table using the email
            return self::getStudentIdFromEmail($pdo, $item['user_id']);
        } catch (Exception $e) {
            error_log('Failed to get student ID from report ID: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check for items approaching disposal and create warnings
     */
    public static function checkDisposalWarnings($pdo) {
        try {
            // Get items approaching disposal (within 3 days)
            $sql = "SELECT COUNT(*) as count FROM items 
                    WHERE status IN ('Found', 'Matched') 
                    AND disposal_deadline IS NOT NULL 
                    AND disposal_deadline <= DATE_ADD(NOW(), INTERVAL 3 DAY)
                    AND disposal_deadline > NOW()";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] > 0) {
                // Check if we already sent a warning today
                $checkSql = "SELECT COUNT(*) as count FROM notifications 
                            WHERE type = 'item_disposal_warning' 
                            AND DATE(created_at) = CURDATE()";
                $checkStmt = $pdo->prepare($checkSql);
                $checkStmt->execute();
                $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($checkResult['count'] == 0) {
                    // Create disposal warning notification
                    return self::createDisposalWarningNotification($pdo, $result['count']);
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Failed to check disposal warnings: ' . $e->getMessage());
            return false;
        }
    }
}
?>