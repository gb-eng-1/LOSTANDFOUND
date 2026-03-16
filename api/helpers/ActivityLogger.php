<?php

class ActivityLogger {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log an activity to the activity_log table
     * 
     * @param string $itemId - The item ID (barcode or reference ID)
     * @param string $action - The action performed (item_encoded, item_matched, etc.)
     * @param int|null $actorId - The ID of the actor (admin/student ID)
     * @param string $actorType - The type of actor (admin, student, system)
     * @param array|null $details - Additional details about the operation
     */
    public function log($itemId, $action, $actorId = null, $actorType = 'system', $details = null) {
        try {
            $sql = "INSERT INTO activity_log (item_id, action, actor_id, actor_type, details, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $itemId,
                $action,
                $actorId,
                $actorType,
                $details ? json_encode($details) : null
            ]);
            
            return true;
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Activity logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log item encoded action
     */
    public function logItemEncoded($itemId, $adminId, $details = []) {
        return $this->log($itemId, 'item_encoded', $adminId, 'admin', $details);
    }
    
    /**
     * Log item matched action
     */
    public function logItemMatched($itemId, $actorId = null, $actorType = 'system', $details = []) {
        return $this->log($itemId, 'item_matched', $actorId, $actorType, $details);
    }
    
    /**
     * Log claim submitted action
     */
    public function logClaimSubmitted($itemId, $studentId, $details = []) {
        return $this->log($itemId, 'claim_submitted', $studentId, 'student', $details);
    }
    
    /**
     * Log claim approved action
     */
    public function logClaimApproved($itemId, $adminId, $details = []) {
        return $this->log($itemId, 'claim_approved', $adminId, 'admin', $details);
    }
    
    /**
     * Log claim resolved action
     */
    public function logClaimResolved($itemId, $adminId, $details = []) {
        return $this->log($itemId, 'claim_resolved', $adminId, 'admin', $details);
    }
    
    /**
     * Log item archived action
     */
    public function logItemArchived($itemId, $actorId = null, $actorType = 'system', $details = []) {
        return $this->log($itemId, 'item_archived', $actorId, $actorType, $details);
    }
    
    /**
     * Get activity logs for an item
     */
    public function getItemActivity($itemId, $limit = 50) {
        try {
            $limit = (int)$limit; // Ensure it's an integer
            $sql = "SELECT al.*, 
                           CASE 
                               WHEN al.actor_type = 'admin' THEN a.name
                               WHEN al.actor_type = 'student' THEN s.name
                               ELSE 'System'
                           END as actor_name
                    FROM activity_log al
                    LEFT JOIN admins a ON al.actor_id = a.id AND al.actor_type = 'admin'
                    LEFT JOIN students s ON al.actor_id = s.id AND al.actor_type = 'student'
                    WHERE al.item_id = ?
                    ORDER BY al.created_at DESC
                    LIMIT $limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$itemId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get item activity: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent activity across all items
     */
    public function getRecentActivity($limit = 100, $actions = null) {
        try {
            $limit = (int)$limit; // Ensure it's an integer
            $sql = "SELECT al.*, 
                           CASE 
                               WHEN al.actor_type = 'admin' THEN a.name
                               WHEN al.actor_type = 'student' THEN s.name
                               ELSE 'System'
                           END as actor_name,
                           i.brand, i.color, i.item_type
                    FROM activity_log al
                    LEFT JOIN admins a ON al.actor_id = a.id AND al.actor_type = 'admin'
                    LEFT JOIN students s ON al.actor_id = s.id AND al.actor_type = 'student'
                    LEFT JOIN items i ON al.item_id = i.id
                    WHERE 1=1";
            
            $params = [];
            
            if ($actions && is_array($actions)) {
                $placeholders = str_repeat('?,', count($actions) - 1) . '?';
                $sql .= " AND al.action IN ($placeholders)";
                $params = array_merge($params, $actions);
            }
            
            $sql .= " ORDER BY al.created_at DESC LIMIT $limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get recent activity: " . $e->getMessage());
            return [];
        }
    }
}