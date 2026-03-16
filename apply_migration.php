<?php
require 'config/database.php';

$sql = file_get_contents('database/011_add_actor_type_to_activity_log.sql');
try {
    $pdo->exec($sql);
    echo "Migration applied successfully\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
