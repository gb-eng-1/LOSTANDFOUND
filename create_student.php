<?php
/**
 * One-time script: creates/updates student account (students@ub.edu.ph / Students).
 * Students table must already exist. Run once in browser then delete.
 */
require __DIR__ . '/config/database.php';

$email = 'students@ub.edu.ph';
$password = 'Students';
$name = 'Student';

$stmt = $pdo->prepare('SELECT id FROM students WHERE LOWER(email) = LOWER(?)');
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $upd = $pdo->prepare('UPDATE students SET password_hash = ?, name = ? WHERE id = ?');
    $upd->execute([$hash, $name, $existing['id']]);
    echo 'Student account updated. Email: ' . htmlspecialchars($email) . ', Password: Students.';
} else {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO students (email, password_hash, name) VALUES (?, ?, ?)');
    $stmt->execute([$email, $hash, $name]);
    echo 'Student account created. Email: ' . htmlspecialchars($email) . ', Password: Students.';
}
