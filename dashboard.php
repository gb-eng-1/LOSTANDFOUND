<?php
// Dashboard layout (static sample data) - replace with real data as needed.

require_once __DIR__ . '/config/database.php';

// --- Dynamic Data for Recently Matched Items ---

// For demonstration, we'll use a hardcoded student email. 
// In a real application, this would come from a session variable after login.
$studentEmail = 'students@ub.edu.ph'; 
$recentlyMatchedItems = [];
$stats = [
    'reports' => 0,
    'matches' => 0,
    'pickup' => 0
];

try {
    // 1. Get My Reports Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE user_id = ? AND id LIKE 'REF-%'");
    $stmt->execute([$studentEmail]);
    $stats['reports'] = (int) $stmt->fetchColumn();

    // 2. Get Potential Matches Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE user_id = ? AND status = 'Matched' AND id LIKE 'REF-%'");
    $stmt->execute([$studentEmail]);
    $stats['matches'] = (int) $stmt->fetchColumn();

    // 3. Get Ready for Pickup Count (Approved Claims)
    $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
    $stmt->execute([$studentEmail]);
    $studentId = $stmt->fetchColumn();

    if ($studentId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE student_id = ? AND status = 'Approved'");
        $stmt->execute([$studentId]);
        $stats['pickup'] = (int) $stmt->fetchColumn();
    }

    // Find the 5 most recent lost reports by this student that have been matched to a found item.
    $stmt_reports = $pdo->prepare(
        "SELECT matched_barcode_id FROM items 
         WHERE user_id = ? AND status = 'Matched' AND id LIKE 'REF-%' AND matched_barcode_id IS NOT NULL
         ORDER BY updated_at DESC LIMIT 5"
    );
    $stmt_reports->execute([$studentEmail]);
    $matched_ids = $stmt_reports->fetchAll(PDO::FETCH_COLUMN);

    // --- DEMO DATA INJECTION ---
    // If no matched items are found for the demo student, this block injects sample data
    // to demonstrate the feature, as requested. This will only run once.
    if (empty($matched_ids)) {
        $stmt_check = $pdo->prepare("SELECT 1 FROM items WHERE id = 'UB-DEMO-MATCH-1'");
        $stmt_check->execute();
        if ($stmt_check->fetchColumn() === false) {
            // Insert a sample "found" item
            $pdo->exec("INSERT INTO items (id, item_type, brand, color, item_description, found_at, date_encoded, status, created_at, updated_at) VALUES ('UB-DEMO-MATCH-1', 'Tumbler', 'HydroFlask', 'Blue', 'Blue, with a small dent', 'Building H', '2024-02-23', 'Matched', NOW(), NOW())");
            $pdo->exec("INSERT INTO items (id, item_type, brand, color, item_description, found_at, date_encoded, status, created_at, updated_at) VALUES ('UB-DEMO-MATCH-2', 'Tumbler', 'Civago', 'Blue', 'Blue', 'Building J', '2024-03-01', 'Matched', NOW(), NOW())");
            
            // Insert corresponding "lost" reports for the student and link them
            $pdo->exec("INSERT INTO items (id, user_id, item_type, status, matched_barcode_id, created_at, updated_at) VALUES ('REF-DEMO-MATCH-1', 'students@ub.edu.ph', 'Tumbler', 'Matched', 'UB-DEMO-MATCH-1', NOW(), NOW())");
            $pdo->exec("INSERT INTO items (id, user_id, item_type, status, matched_barcode_id, created_at, updated_at) VALUES ('REF-DEMO-MATCH-2', 'students@ub.edu.ph', 'Tumbler', 'Matched', 'UB-DEMO-MATCH-2', NOW(), NOW())");

            // Re-fetch matched IDs after injection
            $stmt_reports->execute([$studentEmail]);
            $matched_ids = $stmt_reports->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    // --- END DEMO DATA INJECTION ---

    if (!empty($matched_ids)) {
        $placeholders = rtrim(str_repeat('?,', count($matched_ids)), ',');
        $stmt_items = $pdo->prepare(
            "SELECT id, item_type, brand, item_description, found_at, date_encoded 
             FROM items WHERE id IN ($placeholders) ORDER BY date_encoded DESC, created_at DESC"
        );
        $stmt_items->execute($matched_ids);
        $recentlyMatchedItems = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // In a production app, log this error instead of displaying it.
    // error_log('Dashboard Error: ' . $e->getMessage());
    // To prevent breaking the page, we'll ensure the array is empty on failure.
    $recentlyMatchedItems = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>UB Lost and Found System</title>
  <link rel="stylesheet" href="dashboard.css">
  <style>
    /* Recently Matched Item Styles */
    .recent-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .recent-header h3 { margin: 0; color: #666; font-size: 20px; font-weight: bold; }
    .see-all-btn { color: #8b0000; text-decoration: none; font-weight: bold; cursor: pointer; font-size: 16px; }
    
    .matched-items-row { display: flex; gap: 20px; overflow-x: auto; padding-bottom: 10px; }
    .matched-item-card {
        background: white; border: 1px solid #eee; border-radius: 8px; padding: 15px;
        min-width: 260px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        display: flex; flex-direction: column; gap: 8px;
    }
    .matched-item-card h4 { margin: 0; font-size: 16px; display: flex; align-items: center; gap: 10px; font-weight: bold; }
    .matched-item-card .desc { font-style: italic; color: #333; font-size: 14px; }
    .matched-item-card .meta { display: flex; align-items: center; gap: 8px; color: #555; font-size: 14px; }
    .matched-item-card .view-btn {
        background: #007bff; color: white; border: none; padding: 8px 20px;
        border-radius: 6px; cursor: pointer; align-self: flex-end; margin-top: 10px;
        font-weight: 500;
    }

    /* Modal Styles */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
    .modal-container { background: white; width: 90%; max-width: 700px; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; max-height: 90vh; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
    .modal-header { background: #a61c1c; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
    .modal-header h2 { margin: 0; font-size: 18px; font-weight: bold; }
    .modal-close { cursor: pointer; font-size: 24px; color: white; background: none; border: none; }
    .modal-body { padding: 20px; overflow-y: auto; background: #f9f9f9; }
    
    .match-comparison { display: flex; flex-direction: column; gap: 15px; }
    .comparison-card { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 15px; display: flex; gap: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .comp-img-container { width: 120px; height: 140px; flex-shrink: 0; background: #eee; border-radius: 4px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
    .comp-img { width: 100%; height: 100%; object-fit: cover; }
    .comp-details { flex: 1; }
    .comp-header { font-weight: bold; border-bottom: 2px solid #eee; margin-bottom: 10px; padding-bottom: 5px; font-size: 16px; color: #333; }
    .comp-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 14px; }
    .comp-label { color: #777; }
    .comp-val { font-weight: 600; color: #333; text-align: right; }
    .barcode-id { text-align: center; margin-top: 5px; font-size: 12px; font-weight: bold; }
    
    .modal-footer { padding: 15px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; background: white; }
    .btn-cancel { padding: 10px 20px; border: 1px solid #ccc; background: white; border-radius: 4px; cursor: pointer; font-size: 14px; }
    .btn-claim { padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: bold; }
    .btn-claim:hover { background: #45a049; }
  </style>
  <script>
    function openModal() {
        document.getElementById('matchModal').style.display = 'flex';
    }
    function closeModal() {
        document.getElementById('matchModal').style.display = 'none';
    }
    // Close modal when clicking outside
    window.onclick = function(event) {
        var modal = document.getElementById('matchModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
  </script>
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-logo">UB</div>
        <h1>UB Lost and<br>Found System</h1>
      </div>
      <nav class="nav">
        <a class="active" href="#">
          <svg class="icon" viewBox="0 0 24 24"><path d="M12 3 3 10v11h6v-6h6v6h6V10z"/></svg>
          Dashboard
        </a>
        <a href="#">
          <svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16v4H4zm0 6h16v10H4z"/></svg>
          Report Lost Item
        </a>
        <a href="#">
          <svg class="icon" viewBox="0 0 24 24"><path d="M7 4h10v2H7zm0 4h10v12H7z"/></svg>
          Found Items
        </a>
        <a href="#">
          <svg class="icon" viewBox="0 0 24 24"><path d="M6 3h12v18H6z"/></svg>
          My Reports
        </a>
        <a href="#">
          <svg class="icon" viewBox="0 0 24 24"><path d="M5 4h14v2H5zm0 4h14v12H5z"/></svg>
          Claim History
        </a>
        <a href="#">
          <svg class="icon" viewBox="0 0 24 24"><path d="M12 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm1 5h-2v5h5v-2h-3z"/></svg>
          Help and Support
        </a>
      </nav>
    </aside>

    <main class="main">
      <div class="topbar">
        <form class="search" action="#" method="get">
          <input type="text" name="q" placeholder="Search" aria-label="Search">
          <button class="clear-btn" type="reset" aria-label="Clear search">×</button>
          <button class="search-btn" type="submit" aria-label="Search">
            <svg viewBox="0 0 24 24"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12zm9.7 15.3-4-4 1.4-1.4 4 4z"/></svg>
          </button>
        </form>
        <div class="top-icons">
          <a href="#" aria-label="Settings">
            <svg viewBox="0 0 24 24"><path d="M12 4a4 4 0 0 1 4 4v2h2v2h-2v2a4 4 0 0 1-8 0v-2H6v-2h2V8a4 4 0 0 1 4-4z"/></svg>
          </a>
          <a href="#" aria-label="Notifications">
            <svg viewBox="0 0 24 24"><path d="M12 22a2.5 2.5 0 0 0 2.5-2.5h-5A2.5 2.5 0 0 0 12 22zm6-6V11a6 6 0 0 0-5-5.9V4a1 1 0 0 0-2 0v1.1A6 6 0 0 0 6 11v5l-2 2v1h16v-1z"/></svg>
          </a>
          <a class="user" href="#" aria-label="Profile">
            <div class="avatar">S</div>
            <span>Lance Lenard Panopio</span>
          </a>
        </div>
      </div>

      <div class="content">
        <h2>Dashboard</h2>

        <div class="main-grid">
          <div class="left-column">
            <div class="cards-row">
              <div class="card">
                <h3>My Reports</h3>
                <div class="value"><?= number_format($stats['reports']) ?></div>
              </div>
              <div class="card">
                <h3>Potential Matches</h3>
                <div class="value"><?= number_format($stats['matches']) ?></div>
              </div>
              <div class="card">
                <h3>Ready for Pickup</h3>
                <div class="value"><?= number_format($stats['pickup']) ?></div>
              </div>
            </div>

            <div class="action-row">
              <button class="report-btn">+ Report Lost Item</button>
            </div>

            <div class="table-card">
              <div class="table-title">My Reports</div>
              <table>
                <thead>
                  <tr>
                    <th>Item Type<br><span style="font-weight: 400;">Barcode ID</span></th>
                    <th>Last Seen</th>
                    <th>Date Found</th>
                    <th>Retention End</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>UB0019</td>
                    <td>H102</td>
                    <td>2024-02-23</td>
                    <td>2026-02-23</td>
                    <td><button class="view-btn">View</button></td>
                  </tr>
                  <tr>
                    <td>UB0049</td>
                    <td>Canteen</td>
                    <td>2023-05-12</td>
                    <td>2024-02-11</td>
                    <td><button class="view-btn">View</button></td>
                  </tr>
                  <tr>
                    <td>UB0234</td>
                    <td>H205</td>
                    <td>2023-07-12</td>
                    <td>2024-02-11</td>
                    <td><button class="view-btn">View</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="recent">
            <div class="recent-header">
              <h3>Recently Matched Item</h3>
              <a class="see-all-btn" onclick="openModal()">see all</a>
            </div>
            
            <div class="matched-items-row">
              <?php if (empty($recentlyMatchedItems)): ?>
                <p>No recently matched items found.</p>
              <?php else: ?>
                <?php foreach ($recentlyMatchedItems as $item): ?>
                  <div class="matched-item-card">
                    <h4>
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg> 
                      <?= htmlspecialchars(trim(($item['brand'] ?? '') . ' ' . ($item['item_type'] ?? ''))) ?>
                    </h4>
                    <div class="desc"><?= htmlspecialchars($item['item_description'] ?? 'No description.') ?></div>
                    <div class="meta">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> 
                      <?= htmlspecialchars($item['found_at'] ?? 'N/A') ?>
                    </div>
                    <div class="meta">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> 
                      <?= htmlspecialchars($item['date_encoded'] ?? 'N/A') ?>
                    </div>
                    <button class="view-btn" onclick="openModal()">View</button>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Item Details Modal -->
  <div id="matchModal" class="modal-overlay">
    <div class="modal-container">
      <div class="modal-header">
        <h2>Item Details</h2>
        <button class="modal-close" onclick="closeModal()">&times;</button>
      </div>
      <div class="modal-body">
        <div class="match-comparison">
          <!-- Found Item -->
          <div class="comparison-card">
            <div class="comp-img-container">
              <!-- Placeholder image -->
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
            </div>
            <div class="comp-details">
              <div class="comp-header">General Information</div>
              <div class="comp-row"><span class="comp-label">Category:</span><span class="comp-val">Miscellaneous</span></div>
              <div class="comp-row"><span class="comp-label">Item:</span><span class="comp-val">Tumbler</span></div>
              <div class="comp-row"><span class="comp-label">Color:</span><span class="comp-val">Blue</span></div>
              <div class="comp-row"><span class="comp-label">Brand:</span><span class="comp-val">Hydroflask</span></div>
              <div class="comp-row"><span class="comp-label">Date Found:</span><span class="comp-val">2024-02-23</span></div>
              <div class="barcode-id">Barcode ID: UB0019</div>
            </div>
          </div>
          <!-- Lost Item -->
          <div class="comparison-card">
            <div class="comp-img-container">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
            </div>
            <div class="comp-details">
              <div class="comp-header">General Information</div>
              <div class="comp-row"><span class="comp-label">Category:</span><span class="comp-val">Miscellaneous</span></div>
              <div class="comp-row"><span class="comp-label">Item:</span><span class="comp-val">Tumbler</span></div>
              <div class="comp-row"><span class="comp-label">Color:</span><span class="comp-val">Blue</span></div>
              <div class="comp-row"><span class="comp-label">Brand:</span><span class="comp-val">Hydroflask</span></div>
              <div class="comp-row"><span class="comp-label">Date Lost:</span><span class="comp-val">2024-02-23</span></div>
              <div class="barcode-id">TIC-9982213434</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-cancel" onclick="closeModal()">Cancel</button>
        <button class="btn-claim">Claim</button>
      </div>
    </div>
  </div>
</body>
</html>
