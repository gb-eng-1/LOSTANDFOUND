(function () {
  var modal = document.getElementById('reportLostModal');
  var successOverlay = document.getElementById('reportLostSuccess');
  var successTicket = document.getElementById('reportLostSuccessTicket');
  var successClose = document.getElementById('reportLostSuccessClose');
  var successCancel = document.getElementById('reportLostSuccessCancel');
  var studentEmail = document.body.dataset.studentEmail || '';

  // Custom alert elements
  var customAlert = document.getElementById('customAlert');
  var customAlertMessage = document.getElementById('customAlertMessage');
  var customAlertOk = document.getElementById('customAlertOk');

  var form = document.getElementById('reportLostForm');
  var openTriggers = document.querySelectorAll('[data-open-report-lost]');
  var closeBtn = modal && modal.querySelector('.report-lost-modal-close');
  var cancelBtn = modal && modal.querySelector('.report-lost-btn-cancel');
  var backdrop = modal && modal.querySelector('.report-lost-modal-backdrop');
  var fileInput = modal && modal.querySelector('#reportImage');
  var fileDisplay = modal && modal.querySelector('.report-lost-file-display');
  var fileClear = modal && modal.querySelector('.report-lost-file-clear');
  var authorizeCheck = modal && modal.querySelector('#reportLostAuthorize');
  var submitBtn = modal && modal.querySelector('#reportLostSubmit');
  var confirmContent = modal && modal.querySelector('#reportLostConfirmContent');
  var dateLostInput = modal && modal.querySelector('#reportDateLost');

  var step1 = modal && modal.querySelector('#reportLostStep1');
  var step2 = modal && modal.querySelector('#reportLostStep2');
  var step3 = modal && modal.querySelector('#reportLostStep3');

  var formData = {}; // Store form data between steps
  var imageDataUrl = null;

  // Custom alert function
  function showAlert(message) {
    if (!customAlert || !customAlertMessage) {
      alert(message); // Fallback to browser alert
      return;
    }
    customAlertMessage.textContent = message;
    customAlert.classList.add('open');
    customAlert.setAttribute('aria-hidden', 'false');
  }

  function hideAlert() {
    if (!customAlert) return;
    customAlert.classList.remove('open');
    customAlert.setAttribute('aria-hidden', 'true');
  }

  // Custom alert OK button
  if (customAlertOk) {
    customAlertOk.addEventListener('click', hideAlert);
  }

  function openModal() {
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    goToStep(1);
    formData = {};
    imageDataUrl = null;
    if (form) form.reset();
    if (fileInput) fileInput.value = '';
    if (fileDisplay) {
      fileDisplay.querySelector('.report-lost-file-name').textContent = 'No file chosen';
      fileDisplay.classList.remove('has-file');
    }
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
    if (!step1 || !step2 || !step3) return;
    step1.classList.remove('report-lost-step-active');
    step2.classList.remove('report-lost-step-active');
    step3.classList.remove('report-lost-step-active');
    if (n === 1) step1.classList.add('report-lost-step-active');
    else if (n === 2) step2.classList.add('report-lost-step-active');
    else if (n === 3) step3.classList.add('report-lost-step-active');
  }

  function collectFormData() {
    if (!form) return {};
    var fd = new FormData(form);
    return {
      category: fd.get('category') || '',
      full_name: fd.get('full_name') || '',
      contact_number: fd.get('contact_number') || '',
      department: fd.get('department') || '',
      id: fd.get('id') || '',
      item: fd.get('item') || '',
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
    
    // Validate date if provided
    if (d.date_lost && d.date_lost.trim()) {
      var selectedDate = new Date(d.date_lost);
      var today = new Date();
      today.setHours(0, 0, 0, 0); // Reset time to start of day
      
      if (selectedDate > today) {
        return 'Date lost cannot be in the future. Please select today or a past date.';
      }
    }
    
    return null; // No validation errors
  }

  function renderConfirmation() {
    if (!confirmContent) return;
    var labels = {
      category: 'Category',
      full_name: 'Full Name',
      contact_number: 'Contact Number',
      department: 'Department',
      id: 'ID',
      item: 'Item',
      item_description: 'Item Description',
      color: 'Color',
      brand: 'Brand',
      date_lost: 'Date Lost'
    };
    var html = '';
    for (var key in labels) {
      var val = (formData[key] || '-').toString().trim() || '-';
      html += '<div class="report-lost-confirm-row"><span class="report-lost-confirm-label">' + escapeHtml(labels[key]) + ':</span><span class="report-lost-confirm-value">' + escapeHtml(val) + '</span></div>';
    }
    confirmContent.innerHTML = html;
  }

  function escapeHtml(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  openTriggers.forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      openModal();
    });
  });

  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
  if (backdrop) backdrop.addEventListener('click', closeModal);

  if (fileInput && fileDisplay) {
    fileInput.addEventListener('change', function () {
      var name = this.files && this.files[0] ? this.files[0].name : '';
      fileDisplay.querySelector('.report-lost-file-name').textContent = name || 'No file chosen';
      fileDisplay.classList.toggle('has-file', !!name);
    });
  }
  if (fileClear && fileInput) {
    fileClear.addEventListener('click', function (e) {
      e.preventDefault();
      fileInput.value = '';
      fileDisplay.querySelector('.report-lost-file-name').textContent = 'No file chosen';
      fileDisplay.classList.remove('has-file');
    });
  }

  if (modal) {
    var next1 = modal.querySelector('#reportLostNext1');
    var next2 = modal.querySelector('#reportLostNext2');
    var back2 = modal.querySelector('#reportLostBack2');
    var back3 = modal.querySelector('#reportLostBack3');

    if (next1) {
      next1.addEventListener('click', function () {
        var validationError = validateStep1();
        if (validationError) {
          showAlert(validationError);
          return;
        }
        formData = collectFormData();
        goToStep(2);
      });
    }

    if (next2) {
      next2.addEventListener('click', function () {
        var file = fileInput && fileInput.files && fileInput.files[0];
        if (file && file.size > 0) {
          var reader = new FileReader();
          reader.onload = function () {
            imageDataUrl = reader.result;
            renderConfirmation();
            goToStep(3);
          };
          reader.readAsDataURL(file);
        } else {
          imageDataUrl = null;
          renderConfirmation();
          goToStep(3);
        }
      });
    }

    if (back2) back2.addEventListener('click', function () { goToStep(1); });
    if (back3) back3.addEventListener('click', function () { goToStep(2); });

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
        data.student_email = (data.student_email && data.student_email.trim()) ? data.student_email.trim() : (studentEmail || '');

        fetch('../save_lost_report.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(data)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok) {
            closeModal();
            showSuccess(res.id || '');
          } else {
            showAlert(res.error || 'Failed to submit report.');
            submitBtn.disabled = false;
          }
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
