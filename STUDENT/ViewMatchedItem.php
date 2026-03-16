<?php
require_once __DIR__ . '/auth_check.php';
$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if (!$id) {
    header('Location: StudentDashboard.php');
    exit;
}
require_once dirname(__DIR__) . '/config/database.php';
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ? AND id NOT LIKE 'REF-%'");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>View Item - UB Lost and Found</title></head>
<body>
<?php if ($item): ?>
<h1>Item Details: <?php echo htmlspecialchars($item['id']); ?></h1>
<p>Type: <?php echo htmlspecialchars($item['item_type'] ?? '-'); ?></p>
<p>Color: <?php echo htmlspecialchars($item['color'] ?? '-'); ?></p>
<p>Found At: <?php echo htmlspecialchars($item['found_at'] ?? '-'); ?></p>
<?php else: ?>
<p>Item not found.</p>
<?php endif; ?>
<a href="StudentDashboard.php">Back to Dashboard</a>
</body>
</html>
