<?php
/**
 * Database Migration Runner
 * 
 * This script runs all database migrations in order to set up the Lost and Found system schema.
 * Run this script once to initialize the database with all required tables and columns.
 * 
 * Usage: php database/run_migrations.php
 * 
 * IMPORTANT: Make sure MySQL/XAMPP is running before executing this script!
 */

// Database configuration
$dbHost     = 'localhost';
$dbName     = 'lostandfound_db';
$dbUser     = 'root';
$dbPassword = '';
$dbCharset  = 'utf8mb4';

// List of migrations to run in order
$migrations = [
    '001_enhance_items_table.sql',
    '002_enhance_admins_table.sql',
    '003_create_students_table.sql',
    '004_create_matches_table.sql',
    '005_create_claims_table.sql',
    '006_create_archives_table.sql',
    '007_create_notifications_table.sql',
    '008_create_support_contacts_table.sql',
    '009_create_process_guides_table.sql',
    '010_enhance_activity_log_table.sql',
];

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   Lost and Found System - Database Migration Runner        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// Check if MySQL is running
echo "Checking MySQL connection...\n";
echo "Host: $dbHost\n";
echo "Database: $dbName\n";
echo "User: $dbUser\n\n";

try {
    $dsn = "mysql:host=$dbHost;charset=$dbCharset";
    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✅ MySQL connection successful!\n\n";
    
    // Check if database exists, if not create it
    try {
        $pdo->exec("USE $dbName");
        echo "✅ Database '$dbName' exists\n\n";
    } catch (PDOException $e) {
        echo "⚠️  Database '$dbName' not found. Creating...\n";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE $dbName");
        echo "✅ Database '$dbName' created\n\n";
    }
    
    echo "Running migrations...\n";
    echo "─────────────────────────────────────────────────────────\n\n";
    
    $successCount = 0;
    $warningCount = 0;
    
    foreach ($migrations as $migration) {
        $filePath = __DIR__ . '/' . $migration;
        
        if (!file_exists($filePath)) {
            echo "❌ $migration - File not found\n";
            $warningCount++;
            continue;
        }
        
        $sql = file_get_contents($filePath);
        
        try {
            // Split by semicolon and execute each statement
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }
            
            echo "✅ $migration\n";
            $successCount++;
        } catch (PDOException $e) {
            // Check if it's a "column already exists" warning (not critical)
            if (strpos($e->getMessage(), 'Duplicate column') !== false || 
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠️  $migration - " . $e->getMessage() . "\n";
                $warningCount++;
            } else {
                echo "❌ $migration - " . $e->getMessage() . "\n";
                $warningCount++;
            }
        }
    }
    
    echo "\n─────────────────────────────────────────────────────────\n";
    echo "\n📊 Migration Summary:\n";
    echo "   ✅ Successful: $successCount\n";
    echo "   ⚠️  Warnings: $warningCount\n";
    echo "\n✅ All migrations completed!\n";
    echo "\n📝 Database schema is now ready for the Lost and Found system.\n";
    echo "\nNext steps:\n";
    echo "   1. Verify tables exist: SHOW TABLES;\n";
    echo "   2. Proceed to Task 2: Create core model classes\n\n";
    
} catch (PDOException $e) {
    echo "\n❌ FATAL ERROR: Database connection failed\n";
    echo "─────────────────────────────────────────────────────────\n\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    echo "TROUBLESHOOTING:\n";
    echo "1. Make sure MySQL/XAMPP is running:\n";
    echo "   - Windows: Start XAMPP Control Panel and click 'Start' for MySQL\n";
    echo "   - Mac: Start XAMPP from Applications\n";
    echo "   - Linux: sudo service mysql start\n\n";
    
    echo "2. Verify database credentials in this script:\n";
    echo "   - Host: $dbHost\n";
    echo "   - User: $dbUser\n";
    echo "   - Password: " . (empty($dbPassword) ? "(empty)" : "***") . "\n\n";
    
    echo "3. If MySQL is running but still fails:\n";
    echo "   - Check if port 3306 is in use\n";
    echo "   - Try connecting with: mysql -u $dbUser -h $dbHost\n\n";
    
    exit(1);
}
?>
