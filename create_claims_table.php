<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=lostandfound_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "CREATE TABLE IF NOT EXISTS `claims` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `reference_id` varchar(50) NOT NULL COMMENT 'Unique claim identifier',
      `student_id` int(11) unsigned NOT NULL,
      `found_item_id` varchar(50) NOT NULL,
      `lost_report_id` varchar(50) DEFAULT NULL,
      `proof_photo` longtext COMMENT 'Photo path or base64 data URL',
      `proof_description` text COMMENT 'Student description of item',
      `status` enum('Pending','Approved','Rejected','Resolved') NOT NULL DEFAULT 'Pending',
      `claim_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `resolution_date` datetime DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_reference_id` (`reference_id`),
      KEY `idx_student_id` (`student_id`),
      KEY `idx_found_item_id` (`found_item_id`),
      KEY `idx_status` (`status`),
      FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    echo "✅ Claims table created successfully\n";
    
} catch (Exception $e) {
    echo "❌ Error creating claims table: " . $e->getMessage() . "\n";
}
?>