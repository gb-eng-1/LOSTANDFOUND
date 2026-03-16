<?php
/**
 * UB Lost and Found — Test Data Setup
 * Place in /LOSTANDFOUND/ root, visit once in browser, then DELETE.
 */
require_once __DIR__ . '/config/database.php';

$steps = [];
function ok($msg)  { global $steps; $steps[] = ['ok',  $msg]; }
function err($msg) { global $steps; $steps[] = ['err', $msg]; }
function info($msg){ global $steps; $steps[] = ['info',$msg]; }

function runSql(PDO $pdo, string $sql, string $label): void {
    try { $pdo->exec($sql); ok($label); }
    catch (PDOException $e) { err("$label → " . $e->getMessage()); }
}

// ── Upsert a student (email is unique key; student_id collision → pick new one) ──
function upsertStudent(PDO $pdo, string $prefStudentId, string $email, string $name, string $dept): void {
    $hash = password_hash('Password123', PASSWORD_DEFAULT);

    // Check if student_id is already taken by a DIFFERENT email
    $s = $pdo->prepare("SELECT email FROM students WHERE student_id = ? LIMIT 1");
    $s->execute([$prefStudentId]);
    $owner = $s->fetchColumn();
    $studentId = $prefStudentId;
    if ($owner && strtolower($owner) !== strtolower($email)) {
        // Generate a safe fallback student_id
        $studentId = 'TST-' . strtoupper(substr(md5($email), 0, 6));
        ok("student_id $prefStudentId already owned by $owner — using $studentId for $email");
    }

    // Now upsert by email
    $check = $pdo->prepare("SELECT id FROM students WHERE LOWER(email) = LOWER(?) LIMIT 1");
    $check->execute([$email]);
    $existingId = $check->fetchColumn();

    try {
        if ($existingId) {
            $pdo->prepare("UPDATE students SET student_id=?, password_hash=?, name=?, department=? WHERE id=?")
                ->execute([$studentId, $hash, $name, $dept, $existingId]);
            ok("Student updated: $email (student_id=$studentId)");
        } else {
            $pdo->prepare("INSERT INTO students (student_id, email, password_hash, name, department) VALUES (?,?,?,?,?)")
                ->execute([$studentId, $email, $hash, $name, $dept]);
            ok("Student inserted: $email (student_id=$studentId)");
        }
    } catch (PDOException $e) {
        err("Student $email → " . $e->getMessage());
    }
}

// ── Upsert an item (delete + re-insert to avoid partial-update issues) ────────
function upsertItem(PDO $pdo, array $f): void {
    try {
        $pdo->prepare("DELETE FROM items WHERE id = ?")->execute([$f['id']]);
        $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($f)));
        $vals = implode(',', array_fill(0, count($f), '?'));
        $pdo->prepare("INSERT INTO items ($cols) VALUES ($vals)")->execute(array_values($f));
        ok("Item upserted: {$f['id']}");
    } catch (PDOException $e) {
        err("Item {$f['id']} → " . $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════════
// STEP 1 — Schema
// ════════════════════════════════════════════════════════════════════
info("── STEP 1: Schema ──");

runSql($pdo, "ALTER TABLE `students` ADD COLUMN IF NOT EXISTS `department` VARCHAR(100) DEFAULT NULL",
    "students.department column");

runSql($pdo, "ALTER TABLE `items` ADD COLUMN IF NOT EXISTS `matched_barcode_id` VARCHAR(50) DEFAULT NULL",
    "items.matched_barcode_id column");

runSql($pdo,
    "ALTER TABLE `items` MODIFY COLUMN `status`
        ENUM('Unclaimed Items','Unresolved Claimants','For Verification','Matched','Claimed')
        NOT NULL DEFAULT 'Unclaimed Items'",
    "items.status enum extended");

// ════════════════════════════════════════════════════════════════════
// STEP 2 — Clean up stale test data from previous runs
// ════════════════════════════════════════════════════════════════════
info("── STEP 2: Cleanup Previous Test Data ──");

// Remove any REF- reports left over from previous setup runs for our 4 emails
$oldRefs = ['REF-100001','REF-100002','REF-100003','REF-100004','REF-100005'];
$ph = implode(',', array_fill(0, count($oldRefs), '?'));
try {
    $pdo->prepare("DELETE FROM items WHERE id IN ($ph)")->execute($oldRefs);
    ok("Cleared old REF- reports");
} catch (PDOException $e) { err("Clear REF- → " . $e->getMessage()); }

// Remove old found items
$oldFound = ['UB0001','UB0002','UB0003','UB0004','UB0005','UB0006','UB0007'];
$ph2 = implode(',', array_fill(0, count($oldFound), '?'));
try {
    $pdo->prepare("DELETE FROM items WHERE id IN ($ph2)")->execute($oldFound);
    ok("Cleared old UB found items");
} catch (PDOException $e) { err("Clear UB items → " . $e->getMessage()); }

// Remove old test claims (using reference_id pattern)
try {
    // Delete claims linked to our test found items
    $pdo->prepare(
        "DELETE FROM claims WHERE found_item_id IN ('UB0003','UB0004','UB0006')"
    )->execute();
    ok("Cleared old test claims");
} catch (PDOException $e) { err("Clear claims → " . $e->getMessage()); }

// ════════════════════════════════════════════════════════════════════
// STEP 3 — Students
// ════════════════════════════════════════════════════════════════════
info("── STEP 3: Students (password = Password123) ──");

upsertStudent($pdo, '2200001', 'maria.santos@ub.edu.ph',  'Maria Santos',  'CICT');
upsertStudent($pdo, '2200002', 'juan.delacruz@ub.edu.ph', 'Juan dela Cruz', 'CITE');
upsertStudent($pdo, '2200003', 'ana.reyes@ub.edu.ph',     'Ana Reyes',      'CBA');
upsertStudent($pdo, '2200004', 'carlo.mendoza@ub.edu.ph', 'Carlo Mendoza',  'CAS');

// Fetch their actual student_id values (may differ if collision happened)
$getStudentId = function(string $email) use ($pdo): string {
    return (string) $pdo->prepare("SELECT student_id FROM students WHERE LOWER(email)=LOWER(?)")
        ->execute([$email]) ? $pdo->prepare("SELECT student_id FROM students WHERE LOWER(email)=LOWER(?)")
        ->execute([$email]) && ($s = $pdo->prepare("SELECT student_id FROM students WHERE LOWER(email)=LOWER(?)"))
        ->execute([$email]) ? ($s->fetchColumn() ?: '') : '' : '';
};

// Simpler fetch helper
function getField(PDO $pdo, string $sql, array $params = []): string {
    $s = $pdo->prepare($sql); $s->execute($params); return (string)($s->fetchColumn() ?: '');
}

$mariaStudentId = getField($pdo, "SELECT student_id FROM students WHERE LOWER(email)=LOWER(?)", ['maria.santos@ub.edu.ph']);
$juanStudentId  = getField($pdo, "SELECT student_id FROM students WHERE LOWER(email)=LOWER(?)", ['juan.delacruz@ub.edu.ph']);
$anaStudentId   = getField($pdo, "SELECT student_id FROM students WHERE LOWER(email)=LOWER(?)", ['ana.reyes@ub.edu.ph']);
$carloStudentId = getField($pdo, "SELECT student_id FROM students WHERE LOWER(email)=LOWER(?)", ['carlo.mendoza@ub.edu.ph']);

info("Resolved student_ids: Maria=$mariaStudentId, Juan=$juanStudentId, Ana=$anaStudentId, Carlo=$carloStudentId");

// ════════════════════════════════════════════════════════════════════
// STEP 4 — Found Items (UB- prefix)
// ════════════════════════════════════════════════════════════════════
info("── STEP 4: Found Items ──");

upsertItem($pdo, [
    'id'=>'UB0001','item_type'=>'Miscellaneous','brand'=>'HydroFlask','color'=>'Blue',
    'item_description'=>'Blue tumbler with a small dent on the bottom.',
    'found_at'=>'Building H','date_encoded'=>'2026-01-15',
    'status'=>'Unclaimed Items','storage_location'=>'Cabinet A','created_at'=>'2026-01-15 09:00:00',
]);
upsertItem($pdo, [
    'id'=>'UB0002','item_type'=>'Personal Belongings','brand'=>'','color'=>'Black',
    'item_description'=>'Black leather bifold wallet. Contains no cash.',
    'found_at'=>'Library 2nd Floor','date_encoded'=>'2026-01-20',
    'status'=>'Unclaimed Items','storage_location'=>'Cabinet A','created_at'=>'2026-01-20 11:30:00',
]);
// UB0003 — matched to Juan
upsertItem($pdo, [
    'id'=>'UB0003','item_type'=>'Electronics & Gadgets','brand'=>'Samsung','color'=>'White',
    'item_description'=>'White Samsung Galaxy phone. Cracked screen protector. No SIM.',
    'found_at'=>'Cafeteria near Building A','date_encoded'=>'2026-01-25',
    'status'=>'Unclaimed Items','storage_location'=>'Cabinet B','created_at'=>'2026-01-25 13:00:00',
]);
// UB0004 — matched to Ana + claimed
upsertItem($pdo, [
    'id'=>'UB0004','item_type'=>'Personal Belongings','brand'=>'','color'=>'Black',
    'item_description'=>'Black compact umbrella with floral lining inside.',
    'found_at'=>'Gym Entrance','date_encoded'=>'2026-02-01',
    'status'=>'For Verification','storage_location'=>'Cabinet A','created_at'=>'2026-02-01 08:45:00',
]);
upsertItem($pdo, [
    'id'=>'UB0005','item_type'=>'Document & Identification','brand'=>'','color'=>'Blue',
    'item_description'=>'Blue spiral notebook with stickers on the cover.',
    'found_at'=>'Building C Room 301','date_encoded'=>'2026-02-05',
    'status'=>'Unclaimed Items','storage_location'=>'Cabinet B','created_at'=>'2026-02-05 10:00:00',
]);
// UB0006 — matched to Carlo AirPods
upsertItem($pdo, [
    'id'=>'UB0006','item_type'=>'Electronics & Gadgets','brand'=>'Apple','color'=>'White',
    'item_description'=>'White AirPods Pro case. No AirPods inside. Green keychain attached.',
    'found_at'=>'Hallway near CICT Office','date_encoded'=>'2026-02-10',
    'status'=>'Unclaimed Items','storage_location'=>'Cabinet B','created_at'=>'2026-02-10 14:20:00',
]);
upsertItem($pdo, [
    'id'=>'UB0007','item_type'=>'Apparel & Accessories','brand'=>'','color'=>'Gray',
    'item_description'=>'Gray hoodie jacket, size M. "UB" text on left chest.',
    'found_at'=>'Basketball Court','date_encoded'=>'2026-02-14',
    'status'=>'Unclaimed Items','storage_location'=>'Cabinet A','created_at'=>'2026-02-14 16:00:00',
]);

// ════════════════════════════════════════════════════════════════════
// STEP 5 — Lost Reports (REF- prefix)
// user_id must exactly match students.email
// item_description keys: Full Name, Student Number, Contact, Department, Item Type
// ════════════════════════════════════════════════════════════════════
info("── STEP 5: Lost Reports ──");

// Maria — no match
upsertItem($pdo, [
    'id'=>'REF-100001','user_id'=>'maria.santos@ub.edu.ph',
    'item_type'=>'Electronics & Gadgets','date_lost'=>'2026-01-28',
    'item_description'=>"Full Name: Maria Santos\nStudent Number: $mariaStudentId\nContact: 09171234567\nDepartment: CICT\nItem Type: Smartphone\nBlack phone case with a sunflower sticker. Lost near the library entrance.",
    'status'=>'Unclaimed Items','matched_barcode_id'=>null,'created_at'=>'2026-01-28 10:00:00',
]);

// Juan — matched to UB0003
upsertItem($pdo, [
    'id'=>'REF-100002','user_id'=>'juan.delacruz@ub.edu.ph',
    'item_type'=>'Electronics & Gadgets','date_lost'=>'2026-01-24',
    'item_description'=>"Full Name: Juan dela Cruz\nStudent Number: $juanStudentId\nContact: 09281234567\nDepartment: CITE\nItem Type: Smartphone\nWhite Samsung, cracked screen protector. Lost near the cafeteria.",
    'status'=>'Unclaimed Items','matched_barcode_id'=>'UB0003','created_at'=>'2026-01-24 15:30:00',
]);

// Ana — matched to UB0004
upsertItem($pdo, [
    'id'=>'REF-100003','user_id'=>'ana.reyes@ub.edu.ph',
    'item_type'=>'Personal Belongings','date_lost'=>'2026-01-31',
    'item_description'=>"Full Name: Ana Reyes\nStudent Number: $anaStudentId\nContact: 09391234567\nDepartment: CBA\nItem Type: Umbrella\nBlack compact umbrella, floral pattern inside. Lost near the gym.",
    'status'=>'For Verification','matched_barcode_id'=>'UB0004','created_at'=>'2026-01-31 09:15:00',
]);

// Carlo — AirPods matched to UB0006
upsertItem($pdo, [
    'id'=>'REF-100004','user_id'=>'carlo.mendoza@ub.edu.ph',
    'item_type'=>'Electronics & Gadgets','date_lost'=>'2026-02-09',
    'item_description'=>"Full Name: Carlo Mendoza\nStudent Number: $carloStudentId\nContact: 09451234567\nDepartment: CAS\nItem Type: AirPods / Earphones\nWhite AirPods Pro case with a green keychain. Lost near the CICT hallway.",
    'status'=>'Unclaimed Items','matched_barcode_id'=>'UB0006','created_at'=>'2026-02-09 11:00:00',
]);

// Carlo — school ID, no match
upsertItem($pdo, [
    'id'=>'REF-100005','user_id'=>'carlo.mendoza@ub.edu.ph',
    'item_type'=>'Document & Identification','date_lost'=>'2026-02-12',
    'item_description'=>"Full Name: Carlo Mendoza\nStudent Number: $carloStudentId\nContact: 09451234567\nDepartment: CAS\nItem Type: School ID\nUB student ID card. Lost between CAS building and the parking lot.",
    'status'=>'Unclaimed Items','matched_barcode_id'=>null,'created_at'=>'2026-02-12 13:45:00',
]);

// ════════════════════════════════════════════════════════════════════
// STEP 6 — Claim for Ana (Approved) using actual claims table schema:
// columns: reference_id, student_id, found_item_id, lost_report_id,
//          proof_description, status, claim_date, resolution_date
// ════════════════════════════════════════════════════════════════════
info("── STEP 6: Claims ──");

try {
    $anaDbId = (int) getField($pdo, "SELECT id FROM students WHERE LOWER(email)=LOWER(?)", ['ana.reyes@ub.edu.ph']);
    if ($anaDbId) {
        // Remove any leftover claims for UB0004 by Ana
        $pdo->prepare("DELETE FROM claims WHERE student_id=? AND found_item_id='UB0004'")->execute([$anaDbId]);

        // Generate a unique reference_id
        $refId = 'REF-CLAIM-TEST01';
        $pdo->prepare("DELETE FROM claims WHERE reference_id=?")->execute([$refId]);

        $pdo->prepare("
            INSERT INTO claims
                (reference_id, student_id, found_item_id, lost_report_id, proof_description, status, claim_date, resolution_date, created_at)
            VALUES (?, ?, 'UB0004', 'REF-100003', 'Black compact umbrella with floral lining. Lost near the gym.', 'Approved', '2026-02-02 09:00:00', '2026-02-03 10:00:00', '2026-02-02 09:00:00')
        ")->execute([$refId, $anaDbId]);
        ok("Claim inserted: Ana Reyes → UB0004 (Approved)");
    } else {
        err("Ana Reyes not found in students — claim skipped");
    }
} catch (PDOException $e) {
    err("Claims → " . $e->getMessage());
}

// ════════════════════════════════════════════════════════════════════
// STEP 7 — Verify
// ════════════════════════════════════════════════════════════════════
info("── STEP 7: Verification ──");

$checks = [
    ["SELECT COUNT(*) FROM students WHERE LOWER(email) IN ('maria.santos@ub.edu.ph','juan.delacruz@ub.edu.ph','ana.reyes@ub.edu.ph','carlo.mendoza@ub.edu.ph')", 4, "4 test students present"],
    ["SELECT COUNT(*) FROM items WHERE id IN ('UB0001','UB0002','UB0003','UB0004','UB0005','UB0006','UB0007')", 7, "7 found items present"],
    ["SELECT COUNT(*) FROM items WHERE id IN ('REF-100001','REF-100002','REF-100003','REF-100004','REF-100005')", 5, "5 lost reports present"],
    ["SELECT COUNT(*) FROM items WHERE id IN ('REF-100002','REF-100003','REF-100004') AND matched_barcode_id IS NOT NULL", 3, "3 matched reports (Juan, Ana, Carlo-AirPods)"],
    ["SELECT COUNT(*) FROM claims WHERE reference_id='REF-CLAIM-TEST01' AND status='Approved'", 1, "1 approved claim for Ana"],
];

foreach ($checks as [$sql, $expected, $label]) {
    try {
        $actual = (int) $pdo->query($sql)->fetchColumn();
        if ($actual === $expected) ok("$label ✓ (got $actual)");
        else err("$label ✗ (expected $expected, got $actual)");
    } catch (PDOException $e) { err("$label → " . $e->getMessage()); }
}

// Password check for each account
foreach ([
    'maria.santos@ub.edu.ph','juan.delacruz@ub.edu.ph','ana.reyes@ub.edu.ph','carlo.mendoza@ub.edu.ph'
] as $em) {
    try {
        $hash = getField($pdo, "SELECT password_hash FROM students WHERE LOWER(email)=LOWER(?)", [$em]);
        if ($hash && password_verify('Password123', $hash))
            ok("Password OK: $em ✓");
        else
            err("Password FAILED: $em");
    } catch (PDOException $e) { err("Password check $em → " . $e->getMessage()); }
}

// ════════════════════════════════════════════════════════════════════
// OUTPUT
// ════════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Setup — UB Lost and Found</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #f3f4f6; padding: 32px; }
  h1 { color: #8b0000; font-size: 22px; margin-bottom: 24px; }
  .card { background: #fff; border-radius: 10px; padding: 24px; max-width: 740px; box-shadow: 0 2px 12px rgba(0,0,0,.1); }
  .step { font-weight: 700; color: #374151; font-size: 12px; margin: 18px 0 4px; text-transform: uppercase; letter-spacing: .06em; border-top: 1px solid #e5e7eb; padding-top: 14px; }
  .row { display: flex; align-items: flex-start; gap: 10px; padding: 4px 0; font-size: 13px; }
  .ok   { color: #16a34a; }
  .err  { color: #dc2626; font-weight: 600; }
  .info { color: #6b7280; font-style: italic; }
  .summary { margin-top: 24px; padding: 14px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; }
  .good { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; }
  .bad  { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
  table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
  th { background: #8b0000; color: #fff; padding: 8px 12px; text-align: left; }
  td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; }
  tr:nth-child(even) td { background: #f9fafb; }
  .warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; padding: 12px 16px; border-radius: 6px; margin-top: 20px; font-size: 13px; }
  code { background: #f3f4f6; padding: 1px 5px; border-radius: 3px; font-size: 12px; }
</style>
</head>
<body>
<div class="card">
  <h1>🛠 UB Lost and Found — Setup</h1>
  <?php
  $errors = 0;
  foreach ($steps as [$type, $msg]):
      if ($type === 'info'):
          echo "<div class='step'>$msg</div>";
      else:
          if ($type === 'err') $errors++;
          $icon = $type === 'ok' ? '✅' : ($type === 'err' ? '❌' : 'ℹ️');
          echo "<div class='row'><span>$icon</span><span class='$type'>".htmlspecialchars($msg)."</span></div>";
      endif;
  endforeach;
  ?>

  <?php if ($errors === 0): ?>
    <div class="summary good">✅ All steps completed — ready to test!</div>
  <?php else: ?>
    <div class="summary bad">⚠️ <?= $errors ?> error(s). See red lines above.</div>
  <?php endif; ?>

  <table>
    <thead><tr><th>Email</th><th>Password</th><th>What to test</th></tr></thead>
    <tbody>
      <tr><td>maria.santos@ub.edu.ph</td><td><code>Password123</code></td><td>1 report filed, no match yet</td></tr>
      <tr><td>juan.delacruz@ub.edu.ph</td><td><code>Password123</code></td><td>Report matched to UB0003 (Samsung phone)</td></tr>
      <tr><td>ana.reyes@ub.edu.ph</td><td><code>Password123</code></td><td>Matched + claim Approved (UB0004)</td></tr>
      <tr><td>carlo.mendoza@ub.edu.ph</td><td><code>Password123</code></td><td>2 reports: AirPods matched, ID card open</td></tr>
    </tbody>
  </table>

  <div class="warn">⚠️ <strong>Delete this file</strong> from your server after setup is complete. It bypasses all authentication.</div>
</div>
</body>
</html>