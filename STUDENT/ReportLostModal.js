(function () {
  var modal = document.getElementById('reportLostModal');
  var successOverlay = document.getElementById('reportLostSuccess');
  var successTicket = document.getElementById('reportLostSuccessTicket');
  var successClose = document.getElementById('reportLostSuccessClose');
  var successCancel = document.getElementById('reportLostSuccessCancel');
  var studentEmail = document.body.dataset.studentEmail || '';

  var customAlert = document.getElementById('customAlert');
  var customAlertMessage = document.getElementById('customAlertMessage');
  var customAlertOk = document.getElementById('customAlertOk');

  var form = document.getElementById('reportLostForm');
  var openTriggers = document.querySelectorAll('[data-open-report-lost]');
  var closeBtn = modal && modal.querySelector('.report-lost-modal-close');
  var cancelBtn = modal && modal.querySelector('.report-lost-btn-cancel');
  var backdrop = modal && modal.querySelector('.report-lost-modal-backdrop');
  var authorizeCheck = modal && modal.querySelector('#reportLostAuthorize');
  var submitBtn = modal && modal.querySelector('#reportLostSubmit');
  var confirmContent = modal && modal.querySelector('#reportLostConfirmContent');

  /* Step 1 = form (with inline photo), Step 3 = confirmation review */
  var step1 = modal && modal.querySelector('#reportLostStep1');
  var step3 = modal && modal.querySelector('#reportLostStep3');

  var formData = {};
  var imageDataUrl = null;

  /* ── Photo picker ─────────────────────────────────────────────────── */
  var _rlmPP = PhotoPicker.init({
    el: modal && modal.querySelector('.pp-wrap'),
    onChange: function (dataUrl) { imageDataUrl = dataUrl || null; }
  });

  /* ── Document & Identification sub-dropdown ───────────────────────── */
  var catSelect  = modal && modal.querySelector('#reportCategory');
  var docTypeRow = modal && modal.querySelector('#reportDocTypeRow');
  var docTypeSel = modal && modal.querySelector('#reportDocType');
  var itemInput  = modal && modal.querySelector('#reportItem');

  function syncDocType() {
    if (!catSelect || !docTypeRow) return;
    var isDoc = catSelect.value === 'Document & Identification';
    docTypeRow.style.display = isDoc ? '' : 'none';
    if (!isDoc && docTypeSel) docTypeSel.value = '';
  }
  if (catSelect) catSelect.addEventListener('change', syncDocType);
  if (docTypeSel) {
    docTypeSel.addEventListener('change', function () {
      if (itemInput) itemInput.value = this.value;
    });
  }

  function showAlert(message) {
    if (!customAlert || !customAlertMessage) { alert(message); return; }
    customAlertMessage.textContent = message;
    customAlert.classList.add('open');
    customAlert.setAttribute('aria-hidden', 'false');
  }
  function hideAlert() {
    if (!customAlert) return;
    customAlert.classList.remove('open');
    customAlert.setAttribute('aria-hidden', 'true');
  }
  if (customAlertOk) customAlertOk.addEventListener('click', hideAlert);

  function openModal() {
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    goToStep(1);
    formData = {};
    _rlmPP.clear();
    if (form) form.reset();
    if (docTypeRow) docTypeRow.style.display = 'none';
    if (docTypeSel) docTypeSel.value = '';
    if (authorizeCheck) authorizeCheck.checked = false;
    if (submitBtn) submitBtn.disabled = true;
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function goToStep(n) {
    if (step1) step1.classList.remove('report-lost-step-active');
    if (step3) step3.classList.remove('report-lost-step-active');
    if (n === 1 && step1) step1.classList.add('report-lost-step-active');
    if (n === 3 && step3) step3.classList.add('report-lost-step-active');
  }

  function collectFormData() {
    if (!form) return {};
    var fd = new FormData(form);
    var docType = (docTypeSel && docTypeSel.value) ? docTypeSel.value : '';
    var item = fd.get('item') || '';
    if (docType && !item) item = docType;
    return {
      category: fd.get('category') || '',
      document_type: docType,
      full_name: fd.get('full_name') || '',
      contact_number: fd.get('contact_number') || '',
      department: fd.get('department') || '',
      id: fd.get('id') || '',
      item: item,
      item_description: fd.get('item_description') || '',
      color: fd.get('color') || '',
      brand: fd.get('brand') || '',
      date_lost: fd.get('date_lost') || '',
      student_email: fd.get('student_email') || ''
    };
  }

  function validateStep1() {
    var d = collectFormData();
    if (!d.contact_number || !d.contact_number.trim()) return 'Please enter your contact number.';
    if (!d.department || !d.department.trim()) return 'Please enter your department.';
    if (!d.item_description || !d.item_description.trim()) return 'Please enter the item description.';
    if (d.date_lost && d.date_lost.trim()) {
      var parts = d.date_lost.split('-');
      var selectedDate = new Date(+parts[0], +parts[1] - 1, +parts[2]);
      var today = new Date(); today.setHours(0, 0, 0, 0);
      if (selectedDate > today) return 'Date lost cannot be in the future. Please select today or a past date.';
    }
    return null;
  }

  function renderConfirmation() {
    if (!confirmContent) return;
    var labels = {
      category: 'Category', document_type: 'Document Type', full_name: 'Full Name', contact_number: 'Contact Number',
      department: 'Department', id: 'ID', item: 'Item', item_description: 'Item Description',
      color: 'Color', brand: 'Brand', date_lost: 'Date Lost'
    };
    var html = '';
    for (var key in labels) {
      var val = (formData[key] || '-').toString().trim() || '-';
      html += '<div class="report-lost-confirm-row"><span class="report-lost-confirm-label">'
        + escapeHtml(labels[key]) + ':</span><span class="report-lost-confirm-value">'
        + escapeHtml(val) + '</span></div>';
    }
    if (imageDataUrl) {
      html += '<div class="report-lost-confirm-row"><span class="report-lost-confirm-label">Photo:</span>'
        + '<span class="report-lost-confirm-value"><img src="' + escapeHtml(imageDataUrl)
        + '" alt="Item photo" style="max-width:120px;max-height:90px;object-fit:contain;border-radius:6px;border:1px solid #e5e7eb;"></span></div>';
    }
    confirmContent.innerHTML = html;
  }

  function escapeHtml(s) {
    if (!s) return '';
    var d = document.createElement('div'); d.textContent = s; return d.innerHTML;
  }

  openTriggers.forEach(function (el) {
    el.addEventListener('click', function (e) { e.preventDefault(); openModal(); });
  });

  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
  if (backdrop) backdrop.addEventListener('click', closeModal);

  if (modal) {
    var next1 = modal.querySelector('#reportLostNext1');
    var back3 = modal.querySelector('#reportLostBack3');

    if (next1) {
      next1.addEventListener('click', function () {
        var err = validateStep1();
        if (err) { showAlert(err); return; }
        formData = collectFormData();
        renderConfirmation();
        goToStep(3);
      });
    }

    if (back3) back3.addEventListener('click', function () { goToStep(1); });

    if (authorizeCheck && submitBtn) {
      authorizeCheck.addEventListener('change', function () {
        submitBtn.disabled = !authorizeCheck.checked;
      });
    }

    if (submitBtn) {
      submitBtn.addEventListener('click', function () {
        if (!authorizeCheck || !authorizeCheck.checked) return;
        submitBtn.disabled = true;

        var data = Object.assign({}, formData);
        data.imageDataUrl = imageDataUrl || '';
        data.student_email = (data.student_email && data.student_email.trim())
          ? data.student_email.trim() : (studentEmail || '');

        fetch('../save_lost_report.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok) { closeModal(); showSuccess(res.id || ''); }
          else { showAlert(res.error || 'Failed to submit report.'); submitBtn.disabled = false; }
        })
        .catch(function () {
          showAlert('Failed to submit report. Please try again.');
          submitBtn.disabled = false;
        });
      });
    }
  }

  function showSuccess(ticketId) {
    if (!successOverlay) return;
    if (successTicket) successTicket.textContent = 'TIC- ' + ticketId;
    successOverlay.classList.add('open');
    successOverlay.setAttribute('aria-hidden', 'false');
    var autoClose = setTimeout(function () {
      hideSuccess();
      location.reload();
    }, 3000);

    function dismissAndReload() {
      clearTimeout(autoClose);
      hideSuccess();
      location.reload();
    }

    if (successClose) successClose.onclick = dismissAndReload;
    if (successCancel) successCancel.onclick = dismissAndReload;
  }

  function hideSuccess() {
    if (!successOverlay) return;
    successOverlay.classList.remove('open');
    successOverlay.setAttribute('aria-hidden', 'true');
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (modal && modal.classList.contains('open')) {
        closeModal();
      } else if (customAlert && customAlert.classList.contains('open')) {
        hideAlert();
      }
    }
  });
})();
