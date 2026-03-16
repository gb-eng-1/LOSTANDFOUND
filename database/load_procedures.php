<?php
/**
 * Load Stored Procedures
 * 
 * Loads all stored procedures into the database
 * Run this after migrations are complete
 */

$dbHost     = 'localhost';
$dbName     = 'lostandfound_db';
$dbUser     = 'root';
$dbPassword = '';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   Lost and Found System - Load Stored Procedures           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

try {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✅ Connected to database\n\n";
    
    // Read stored procedures file
    $proceduresFile = __DIR__ . '/stored_procedures.sql';
    
    if (!file_exists($proceduresFile)) {
        echo "❌ Stored procedures file not found: $proceduresFile\n";
        exit(1);
    }
    
    $sql = file_get_contents($proceduresFile);
    
    // Split procedures by DROP PROCEDURE statements
    $procedures = preg_split('/(?=DROP PROCEDURE)/', $sql);
    
    $count = 0;
    $errors = 0;
    
    foreach ($procedures as $procedure) {
        $procedure = trim($procedure);
        
        if (empty($procedure)) {
            continue;
        }
        
        // Extract just the procedure code without DELIMITER declarations
        // Remove DELIMITER lines
        $procedure = preg_replace('/DELIMITER\s+[^;]*;/', '', $procedure);
        $procedure = trim($procedure);
        
        if (empty($procedure)) {
            continue;
        }
        
        try {
            // Execute the procedure
            $pdo->exec($procedure);
            $count++;
            echo "✅ Procedure loaded\n";
        } catch (PDOException $e) {
            $errors++;
            // Check if it's a "procedure already exists" warning
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠️  Procedure already exists (skipped)\n";
            } else {
                echo "❌ Error: " . substr($e->getMessage(), 0, 100) . "...\n";
            }
        }
    }
    
    echo "\n✅ Stored procedures loaded!\n";
    echo "   Loaded: $count\n";
    echo "   Errors: $errors\n\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
