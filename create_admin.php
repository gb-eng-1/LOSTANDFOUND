<?php
/**
 * One-time script: creates admin user (admin@ub.edu.ph / Admin) if not already in database.
 * Run once in browser (e.g. http://localhost/LOSTANDFOUND/create_admin.php) then delete this file.
 */
require __DIR__ . '/config/database.php';

$email = 'admin@ub.edu.ph';
$password = 'Admin';
$name = 'Admin';

$stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    die('Admin already exists. No change made.');
}

$hash = password_hash($password, PASSWORD_DEFAULT);
try {
    $stmt = $pdo->prepare('INSERT INTO admins (email, password_hash, name, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $hash, $name, 'Admin']);
} catch (PDOException $e) {
    $stmt = $pdo->prepare('INSERT INTO admins (email, password_hash, name) VALUES (?, ?, ?)');
    $stmt->execute([$email, $hash, $name]);
}

echo 'Admin created successfully. Email: ' . htmlspecialchars($email) . ', Password: Admin. You can delete this file (create_admin.php) now.';
