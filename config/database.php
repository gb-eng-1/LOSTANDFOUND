<?php
/**
 * Database configuration for UB Lost and Found System (WAMP/MySQL)
 * Adjust host, dbname, user, password for your environment.
 */
$dbHost     = 'localhost';
$dbName     = 'lostandfound_db';
$dbUser     = 'root';
$dbPassword = '';
$dbCharset  = 'utf8mb4';

$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=$dbCharset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, $options);
} catch (PDOException $e) {
    // If database doesn't exist yet, show clear message
    if ($e->getCode() === 1049) {
        die('Database not found. Please create it first: run database/lostandfound.sql in phpMyAdmin.');
    }
    throw $e;
}

/**
 * Get items as associative arrays (keys match JSON: id, item_type, found_at, dateEncoded, imageDataUrl, etc.)
 * @param string|null $status 'Unclaimed Items' | 'Unresolved Claimants' | 'For Verification' or null for all
 * @return array
 */
function get_items(PDO $pdo, $status = null) {
    $sql = 'SELECT id, user_id, item_type, color, brand, found_at, found_by, date_encoded, date_lost, item_description, storage_location, image_data, status, created_at, updated_at FROM items';
    $params = [];
    if ($status !== null && $status !== '') {
        $sql .= ' WHERE status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY date_encoded DESC, created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'               => $r['id'],
            'user_id'          => $r['user_id'],
            'item_type'        => $r['item_type'],
            'color'            => $r['color'],
            'brand'            => $r['brand'],
            'found_at'         => $r['found_at'],
            'found_by'         => $r['found_by'],
            'dateEncoded'      => $r['date_encoded'] ?: null,
            'date_lost'        => $r['date_lost'],
            'item_description' => $r['item_description'],
            'storage_location' => $r['storage_location'],
            'imageDataUrl'     => $r['image_data'],
            'status'           => $r['status'],
            'created_at'       => $r['created_at'],
            'updated_at'       => $r['updated_at'],
        ];
    }
    return $out;
}
