<?php
/**
 * Cron job script to check for items approaching disposal deadline
 * Run this script daily to create disposal warning notifications
 * 
 * Usage: php cron_disposal_warnings.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/api/helpers/NotificationHelper.php';

echo "=== Disposal Warning Check ===\n";
echo "Checking for items approaching disposal deadline...\n";

try {
    $result = NotificationHelper::checkDisposalWarnings($pdo);
    
    if ($result) {
        echo "✓ Disposal warning check completed successfully\n";
    } else {
        echo "✗ Disposal warning check failed\n";
    }
    
    // Also check for items that are past disposal deadline
    $pastDueSql = "SELECT COUNT(*) as count FROM items 
                   WHERE status IN ('Found', 'Matched') 
                   AND disposal_deadline IS NOT NULL 
                   AND disposal_deadline < NOW()";
    
    $stmt = $pdo->prepare($pastDueSql);
    $stmt->execute();
    $pastDueResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pastDueResult['count'] > 0) {
        echo "⚠️  Warning: {$pastDueResult['count']} item(s) are past their disposal deadline\n";
        
        // Create urgent disposal notification
        $title = 'Urgent: Items Past Disposal Deadline';
        $message = "{$pastDueResult['count']} item(s) are past their disposal deadline and should be processed immediately.";
        
        NotificationHelper::createNotification($pdo, 1, 'admin', 'item_disposal_urgent', $title, $message);
        echo "✓ Urgent disposal notification created\n";
    } else {
        echo "✓ No items past disposal deadline\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Disposal Warning Check Complete ===\n";
?>