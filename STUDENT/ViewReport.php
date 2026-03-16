<?php
require_once __DIR__ . '/auth_check.php';
$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if (!$id) {
    header('Location: StudentsReport.php');
    exit;
}
require_once dirname(__DIR__) . '/config/database.php';
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ? AND id LIKE 'REF-%'");
$stmt->execute([$id]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    header('Location: StudentsReport.php');
    exit;
}

$desc = $report['item_description'] ?? '';
$studentNum = $contact = $dept = $itemName = $mainDesc = '';
if (preg_match('/Student Number:\s*(.+?)(?:\n|$)/m', $desc, $m)) $studentNum = trim($m[1]);
if (preg_match('/Item Type:\s*(.+?)(?:\n|$)/m', $desc, $m)) $itemName = trim($m[1]);
if (preg_match('/Contact:\s*(.+?)(?:\n|$)/m', $desc, $m)) $contact = trim($m[1]);
if (preg_match('/Department:\s*(.+?)(?:\n|$)/m', $desc, $m)) $dept = trim($m[1]);
// Main description: remove prefixed lines (Student Number, Item Type) and suffix (Contact, Department)
$mainDesc = preg_replace('/\nContact:[^\n]*(?:\nDepartment:\s*[^\n]*)?$/s', '', $desc);
$mainDesc = trim(preg_replace('/^(?:Student Number:|Item Type:)[^\n]*\n?/m', '', $mainDesc));
$mainDesc = trim($mainDesc);

$studentName = $_SESSION['student_name'] ?? '';
$studentEmail = $_SESSION['student_email'] ?? '';
$isOwnReport = (strtolower(trim($report['user_id'] ?? '')) === strtolower($studentEmail))
    || (strpos(strtolower(trim($report['user_id'] ?? '')), '@ub.edu.ph') !== false && $studentEmail !== '');
$fullName = $isOwnReport ? $studentName : '—';
if ($fullName === '') $fullName = '—';

$ticketId = $report['id'];
$category = $report['item_type'] ?? 'Miscellaneous';
$color = $report['color'] ?? '—';
$brand = $report['brand'] ?? '—';
$dateLost = $report['date_lost'] ? date('Y-m-d', strtotime($report['date_lost'])) : '—';
$imageData = $report['image_data'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Item Details - <?php echo htmlspecialchars($ticketId); ?> - UB Lost and Found</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="ViewReport.css?v=<?php echo time(); ?>">
</head>
<body>
  <div class="view-report-card">
    <header class="view-report-header">
      <h1 class="view-report-title">Item Details</h1>
      <a href="StudentsReport.php" class="view-report-close" aria-label="Close"><i class="fa-solid fa-xmark"></i></a>
    </header>
    <div class="view-report-body">
      <div class="view-report-left">
        <div class="view-report-image-wrap">
          <?php if ($imageData): ?>
            <?php
              $src = $imageData;
              if (strpos($src, 'data:') !== 0) $src = 'data:image/jpeg;base64,' . $src;
            ?>
            <img src="<?php echo htmlspecialchars($src); ?>" alt="Item" class="view-report-image">
          <?php else: ?>
            <div class="view-report-image-placeholder"><i class="fa-solid fa-image"></i><span>No image</span></div>
          <?php endif; ?>
        </div>
        <p class="view-report-ticket-id"><?php echo htmlspecialchars($ticketId); ?></p>
      </div>
      <div class="view-report-right">
        <h2 class="view-report-info-title">General Information</h2>
        <dl class="view-report-info-list">
          <div class="view-report-info-row">
            <dt>Category</dt>
            <dd><?php echo htmlspecialchars($category); ?></dd>
          </div>
          <div class="view-report-info-row">
            <dt>Full Name</dt>
            <dd><?php echo htmlspecialchars($fullName); ?></dd>
          </div>
          <div class="view-report-info-row">
            <dt>Contact Number</dt>
            <dd><?php echo htmlspecialchars($contact ?: '—'); ?></dd>
          </div>
          <div class="view-report-info-row">
            <dt>Department</dt>
            <dd><?php echo htmlspecialchars($dept ?: '—'); ?></dd>
          </div>
          <div class="view-report-info-row">
            <dt>ID</dt>
            <dd><?php echo htmlspecialchars($studentNum ?: '—'); ?></dd>
          </div>
          <div class="view-report-info-row">
            <dt>Item</dt>
            <dd><?php echo htmlspecialchars($itemName ?: '—'); ?></dd>
          </div>
          <div class="view-report-info-row">
            <dt>Color</dt>
            <dd><?php echo htmlspecialchars($color); ?></dd>
          </div>
          <div class="view-report-info-row">
            <dt>Brand</dt>
            <dd><?php echo htmlspecialchars($brand); ?></dd>
          </div>
          <div class="view-report-info-row">
            <dt>Item Description</dt>
            <dd><?php echo htmlspecialchars($mainDesc ?: '—'); ?></dd>
          </div>
          <div class="view-report-info-row">
            <dt>Date Lost</dt>
            <dd><?php echo htmlspecialchars($dateLost); ?></dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</body>
</html>
