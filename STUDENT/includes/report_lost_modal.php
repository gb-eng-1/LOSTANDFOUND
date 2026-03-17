<?php
$categories = require dirname(dirname(__DIR__)) . '/config/categories.php';
$studentName = $_SESSION['student_name'] ?? '';
$studentEmail = $_SESSION['student_email'] ?? '';
$studentNumber = '';
if (!empty($_SESSION['student_id'])) {
    try {
        require_once dirname(dirname(__DIR__)) . '/config/database.php';
        $stmt = $pdo->prepare('SELECT student_id FROM students WHERE id = ?');
        $stmt->execute([(int)$_SESSION['student_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['student_id'])) $studentNumber = trim($row['student_id']);
    } catch (Exception $e) {}
}
$colors = ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Violet', 'Black', 'White', 'Brown', 'Rainbow', 'Multi', 'Other'];
?>
<div id="reportLostModal" class="report-lost-modal" role="dialog" aria-labelledby="reportLostModalTitle" aria-modal="true" aria-hidden="true">
  <div class="report-lost-modal-backdrop"></div>
  <div class="report-lost-modal-dialog">
    <div class="report-lost-modal-header">
      <h2 id="reportLostModalTitle" class="report-lost-modal-title">Item Lost Report</h2>
      <button type="button" class="report-lost-modal-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>

    <!-- Step 1: Form (no Upload Image) -->
    <div id="reportLostStep1" class="report-lost-step report-lost-step-active">
      <form id="reportLostForm" class="report-lost-form">
        <input type="hidden" name="student_email" value="<?= htmlspecialchars($studentEmail) ?>">
        <div class="report-lost-modal-body">
          <div class="report-lost-field">
            <label for="reportCategory">Category:</label>
            <select id="reportCategory" name="category">
              <option value="">Select category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="report-lost-field" id="reportDocTypeRow" style="display:none">
            <label for="reportDocType">Document Type:</label>
            <select id="reportDocType" name="document_type">
              <option value="">Select document type</option>
              <option>Student ID</option>
              <option>Driver's License</option>
              <option>Passport</option>
              <option>Person's With Disability (PWD) ID</option>
              <option>Voter's ID</option>
              <option>Company/Employee ID</option>
              <option>National ID</option>
              <option>Senior Citizen ID</option>
            </select>
          </div>
          <div class="report-lost-field">
            <label for="reportFullName">Full Name:</label>
            <input type="text" id="reportFullName" name="full_name" value="<?php echo htmlspecialchars($studentName); ?>" placeholder="">
          </div>
          <div class="report-lost-field">
            <label for="reportContact">Contact Number: <span class="required">*</span></label>
            <input type="text" id="reportContact" name="contact_number" placeholder="" required>
          </div>
          <div class="report-lost-field">
            <label for="reportDepartment">Department: <span class="required">*</span></label>
            <input type="text" id="reportDepartment" name="department" placeholder="" required>
          </div>
          <div class="report-lost-field">
            <label for="reportId">ID:</label>
            <input type="text" id="reportId" name="id" value="<?php echo htmlspecialchars($studentNumber); ?>" placeholder="">
          </div>
          <div class="report-lost-field">
            <label for="reportItem">Item:</label>
            <input type="text" id="reportItem" name="item" placeholder="">
          </div>
          <div class="report-lost-field">
            <label for="reportItemDesc">Item Description: <span class="required">*</span></label>
            <textarea id="reportItemDesc" name="item_description" rows="3" placeholder="" required></textarea>
          </div>
          <div class="report-lost-field">
            <label for="reportColor">Color:</label>
            <select id="reportColor" name="color">
              <option value="">Select</option>
              <?php foreach ($colors as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="report-lost-field">
            <label for="reportBrand">Brand:</label>
            <input type="text" id="reportBrand" name="brand" placeholder="">
          </div>
          <div class="report-lost-field">
            <label for="reportDateLost">Date Lost:</label>
            <input type="date" id="reportDateLost" name="date_lost" placeholder="Pick a Date" max="<?php echo date('Y-m-d'); ?>">
          </div>

          <!-- Inline image upload -->
          <div class="pp-photo-row">
            <label class="pp-photo-label">Photo <span class="pp-optional">(optional)</span></label>
            <div class="pp-wrap" id="rlmPhotoPicker">
              <div class="pp-idle">
                <i class="fa-regular fa-image pp-icon"></i>
                <p class="pp-hint">No photo yet</p>
                <div class="pp-btn-row">
                  <button type="button" class="pp-btn pp-btn--cam" data-pp="camera"><i class="fa-solid fa-camera"></i> Camera</button>
                  <button type="button" class="pp-btn pp-btn--upload" data-pp="upload"><i class="fa-solid fa-upload"></i> Upload</button>
                </div>
              </div>
              <div class="pp-preview" style="display:none">
                <img class="pp-preview-img" src="" alt="Photo preview">
                <div class="pp-preview-actions">
                  <button type="button" class="pp-btn pp-btn--sm" data-pp="camera"><i class="fa-solid fa-camera"></i> Retake</button>
                  <button type="button" class="pp-btn pp-btn--sm" data-pp="upload"><i class="fa-solid fa-upload"></i> Change</button>
                  <button type="button" class="pp-btn pp-btn--del" data-pp="remove"><i class="fa-solid fa-xmark"></i></button>
                </div>
              </div>
              <input type="file" class="pp-file" accept="image/*" style="display:none">
            </div>
          </div>
        </div>
        <div class="report-lost-modal-footer">
          <button type="button" class="report-lost-btn report-lost-btn-cancel">Cancel</button>
          <button type="button" class="report-lost-btn report-lost-btn-next" id="reportLostNext1">Next</button>
        </div>
      </form>
    </div>

    <!-- Step 2: Confirmation (formerly Step 3) -->
    <div id="reportLostStep3" class="report-lost-step">
      <div class="report-lost-modal-body report-lost-confirm-body">
        <div class="report-lost-confirm-grid" id="reportLostConfirmContent">
          <!-- Filled by JS -->
        </div>
        <div class="report-lost-field report-lost-checkbox-wrap">
          <label class="report-lost-checkbox-label">
            <input type="checkbox" id="reportLostAuthorize" name="authorize">
            <span>I hereby authorize that the above details are accurate and correct.</span>
          </label>
        </div>
      </div>
      <div class="report-lost-modal-footer">
        <button type="button" class="report-lost-btn report-lost-btn-back" id="reportLostBack3">Back</button>
        <button type="button" class="report-lost-btn report-lost-btn-submit" id="reportLostSubmit" disabled>Submit</button>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div id="reportLostSuccess" class="report-success-overlay" aria-hidden="true">
  <div class="report-success-dialog">
    <button type="button" class="report-success-close" id="reportLostSuccessClose" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    <div class="report-success-icon"><i class="fa-solid fa-check"></i></div>
    <h3 class="report-success-title">Success</h3>
    <p class="report-success-message">Report has been submitted successfully!</p>
    <p class="report-success-ticket" id="reportLostSuccessTicket"></p>
    <div class="report-success-footer">
      <button type="button" class="report-lost-btn report-lost-btn-cancel" id="reportLostSuccessCancel">Cancel</button>
      <a href="StudentsReport.php" class="report-lost-btn report-lost-btn-submit" id="reportLostSuccessConfirm">Confirm</a>
    </div>
  </div>
</div>

<!-- Custom Alert Modal -->
<div id="customAlert" class="report-success-overlay" aria-hidden="true">
  <div class="report-success-dialog">
    <div class="report-success-icon" style="background-color: #f44336;"><i class="fa-solid fa-exclamation-triangle"></i></div>
    <h3 class="report-success-title">Alert</h3>
    <p class="report-success-message" id="customAlertMessage"></p>
    <div class="report-success-footer">
      <button type="button" class="report-lost-btn report-lost-btn-submit" id="customAlertOk">OK</button>
    </div>
  </div>
</div>
