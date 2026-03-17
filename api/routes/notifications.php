<?php
/**
 * Notification Routes
 * 
 * GET /api/notifications
 * PUT /api/notifications/:id/read
 * DELETE /api/notifications/:id
 */

require_once __DIR__ . '/../helpers/Response.php';

function handleNotificationRoute($method, $parts, $pdo) {
    // Resolve session — admin uses admin_id, students use user_id + user_type
    $userId   = null;
    $userType = null;
    if (!empty($_SESSION['admin_id'])) {
        $userId   = (int) $_SESSION['admin_id'];
        $userType = 'admin';
    } elseif (!empty($_SESSION['user_id']) && !empty($_SESSION['user_type'])) {
        $userId   = $_SESSION['user_id'];
        $userType = $_SESSION['user_type'];
    } else {
        Response::unauthorized();
        return;
    }

    if ($method === 'GET' && count($parts) === 1) {
        // GET /api/notifications
        getNotifications($pdo, $userId, $userType);
    }
    elseif ($method === 'GET' && count($parts) === 2 && $parts[1] === 'count') {
        // GET /api/notifications/count
        getNotificationCount($pdo, $userId, $userType);
    }
    elseif ($method === 'PUT' && count($parts) === 3 && $parts[2] === 'read') {
        // PUT /api/notifications/:id/read
        markNotificationAsRead($pdo, $parts[1], $userId, $userType);
    }
    elseif ($method === 'DELETE' && count($parts) === 2) {
        // DELETE /api/notifications/:id
        deleteNotification($pdo, $parts[1], $userId, $userType);
    }
    else {
        Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    }
}

/**
 * GET /api/notifications/count
 * Returns count of unread notifications
 */
function getNotificationCount($pdo, $userId, $userType) {
    try {
        
        // Get count of unread notifications
        $sql = "SELECT COUNT(*) as unread_count FROM notifications 
                WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userType, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        Response::success(['unread_count' => (int)$result['unread_count']], 200);
    } catch (Exception $e) {
        Response::error('Failed to get notification count: ' . $e->getMessage(), 'QUERY_ERROR', 500);
    }
}

/**
 * GET /api/notifications
 * Returns unread notifications ordered by creation date (newest first)
 */
function getNotifications($pdo, $userId, $userType) {
    try {
        
        // Get user's notifications (unread first, then read, ordered by creation date)
        $sql = "SELECT * FROM notifications 
                WHERE recipient_type = ? AND recipient_id = ?
                ORDER BY is_read ASC, created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userType, $userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert is_read to boolean for JSON response
        foreach ($notifications as &$notification) {
            $notification['is_read'] = (bool)$notification['is_read'];
        }
        
        Response::success($notifications, 200);
    } catch (Exception $e) {
        Response::error('Failed to get notifications: ' . $e->getMessage(), 'QUERY_ERROR', 500);
    }
}

/**
 * PUT /api/notifications/:id/read
 * Mark a notification as read
 */
function markNotificationAsRead($pdo, $notificationId, $userId, $userType) {
    try {
        
        // Check if notification exists and belongs to user
        $checkStmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND recipient_type = ? AND recipient_id = ?");
        $checkStmt->execute([$notificationId, $userType, $userId]);
        $notification = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notification) {
            Response::error('Notification not found', 'NOT_FOUND', 404);
            return;
        }
        
        // Mark as read
        $updateStmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $updateStmt->execute([$notificationId]);
        
        // Get updated notification
        $getStmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
        $getStmt->execute([$notificationId]);
        $updatedNotification = $getStmt->fetch(PDO::FETCH_ASSOC);
        $updatedNotification['is_read'] = (bool)$updatedNotification['is_read'];
        
        Response::success($updatedNotification, 200);
    } catch (Exception $e) {
        Response::error('Failed to mark notification as read: ' . $e->getMessage(), 'UPDATE_ERROR', 500);
    }
}

/**
 * DELETE /api/notifications/:id
 * Delete a notification
 */
function deleteNotification($pdo, $notificationId, $userId, $userType) {
    try {
        
        // Check if notification exists and belongs to user
        $checkStmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND recipient_type = ? AND recipient_id = ?");
        $checkStmt->execute([$notificationId, $userType, $userId]);
        $notification = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$notification) {
            Response::error('Notification not found', 'NOT_FOUND', 404);
            return;
        }
        
        // Delete notification
        $deleteStmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $deleteStmt->execute([$notificationId]);
        
        Response::success(['message' => 'Notification deleted successfully'], 200);
    } catch (Exception $e) {
        Response::error('Failed to delete notification: ' . $e->getMessage(), 'DELETE_ERROR', 500);
    }
}
?>
