<?php
$notifications = [];
$newCount = 0;
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    try {
        if (!isset($pdo)) {
            require_once dirname(dirname(__DIR__)) . '/config/database.php';
        }
        $userType = $_SESSION['user_type'];
        $userId = $_SESSION['user_id'];
        
        $sql = "SELECT * FROM notifications 
                WHERE recipient_type = ? AND recipient_id = ?
                ORDER BY is_read ASC, created_at DESC LIMIT 10";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userType, $userId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($notifications as &$n) {
            $n['is_new'] = !$n['is_read'];
            $time = strtotime($n['created_at']);
            $diff = time() - $time;
            if ($diff < 60) {
                $n['time'] = 'Just now';
            } elseif ($diff < 3600) {
                $n['time'] = floor($diff / 60) . 'm ago';
            } elseif ($diff < 86400) {
                $n['time'] = floor($diff / 3600) . 'h ago';
            } else {
                $n['time'] = date('M d', $time);
            }

            // Simple link mapping based on type
            switch ($n['type']) {
                case 'match_approved':
                case 'match_rejected':
                    $n['link'] = 'StudentsReport.php?filter=matched';
                    break;
                case 'claim_approved':
                case 'claim_rejected':
                    $n['link'] = 'ClaimHistory.php';
                    break;
                default:
                    $n['link'] = '#';
            }
            $n['img'] = 'images/notif_placeholder.jpg';
        }
        unset($n);
        
        $newCount = count(array_filter($notifications, fn($n) => $n['is_new']));
        
    } catch (Exception $e) {
        // Silently fail if DB fails, so UI doesn't break
        $notifications = [];
        $newCount = 0;
    }
}
?>

<!-- Notifications Dropdown -->
<div class="notif-dropdown" id="notifDropdown">
  <button type="button" class="notif-trigger topbar-icon" id="notifTrigger" aria-expanded="false" aria-haspopup="true" title="Notifications">
    <i class="fa-regular fa-bell"></i>
    <?php if ($newCount > 0): ?>
      <span class="notif-badge"><?php echo $newCount; ?></span>
    <?php endif; ?>
  </button>

  <div class="notif-panel" id="notifPanel" role="dialog" aria-label="Notifications" aria-hidden="true">
    <div class="notif-panel-header">
      <span class="notif-panel-title">Notifications</span>
      <?php if ($newCount > 0): ?>
        <span class="notif-panel-count"><?php echo $newCount; ?> new</span>
      <?php endif; ?>
    </div>
    <div class="notif-list">
      <?php if (empty($notifications)): ?>
        <div class="notif-empty">No notifications yet.</div>
      <?php else: ?>
        <?php foreach ($notifications as $notif): ?>
          <div class="notif-item<?php echo $notif['is_new'] ? ' notif-item-new' : ''; ?>">
            <div class="notif-item-thumb">
              <img src="<?php echo htmlspecialchars($notif['img']); ?>"
                   alt="Item"
                   class="notif-thumb-img"
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
              <div class="notif-thumb-placeholder" style="display:none;"><i class="fa-solid fa-box-open"></i></div>
            </div>
            <div class="notif-item-body">
              <div class="notif-item-top">
                <span class="notif-item-title"><?php echo htmlspecialchars($notif['title']); ?></span>
                <?php if ($notif['is_new']): ?>
                  <span class="notif-item-new-badge">New</span>
                <?php endif; ?>
                <span class="notif-item-time"><?php echo htmlspecialchars($notif['time']); ?></span>
              </div>
              <div class="notif-item-message">
                <?php echo htmlspecialchars($notif['message']); ?>
                <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="notif-view-link">View Details</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <a href="StudentsReport.php?filter=matched" class="notif-panel-footer">View all matched reports</a>
  </div>
</div>
