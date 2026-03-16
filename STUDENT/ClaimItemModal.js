/**
 * Claim Item Modal Handler
 * Handles displaying claim item form in a modal popup
 */

(function() {
  'use strict';

  // Create modal HTML
  const modalHTML = `
    <div id="claimItemOverlay" class="claim-item-overlay" aria-hidden="true" role="dialog" aria-labelledby="claimItemTitle">
      <div class="claim-item-dialog">
        <div class="claim-item-header">
          <h2 id="claimItemTitle" class="claim-item-title">Claim Item</h2>
          <button type="button" class="claim-item-close" aria-label="Close" id="claimItemClose">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div class="claim-item-body" id="claimItemBody">
          <!-- Content will be loaded here -->
        </div>
        <div class="claim-item-footer" id="claimItemFooter">
          <!-- Buttons will be loaded here -->
        </div>
      </div>
    </div>
  `;

  // Add modal to page
  function initModal() {
    if (!document.getElementById('claimItemOverlay')) {
      const container = document.createElement('div');
      container.innerHTML = modalHTML;
      document.body.appendChild(container.firstElementChild);
    }
  }

  // Show claim item modal
  window.showClaimItemModal = function(itemId, options = {}) {
    options = Object.assign({
      onSubmit: null,
      onClose: null
    }, options);

    initModal();

    const overlay = document.getElementById('claimItemOverlay');
    const body = document.getElementById('claimItemBody');
    const footer = document.getElementById('claimItemFooter');

    // Show loading state
    body.innerHTML = '<div style="padding: 40px; text-align: center;"><i class="fa-solid fa-spinner fa-spin" style="font-size: 32px; color: #8b0000;"></i><p>Loading...</p></div>';
    footer.innerHTML = '';

    // Fetch item details from API
    fetch(`/LOSTANDFOUND/api/student/items?limit=1&offset=0`, {
      method: 'GET',
      credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
      if (data.ok && data.data && data.data.length > 0) {
        const item = data.data.find(i => i.id === itemId);
        if (item) {
          renderClaimItemForm(item, body, footer, options);
        } else {
          body.innerHTML = '<div style="padding: 40px; text-align: center; color: #999;">Item not found</div>';
        }
      } else {
        body.innerHTML = '<div style="padding: 40px; text-align: center; color: #999;">Unable to load item details</div>';
      }
    })
    .catch(error => {
      console.error('Error loading item details:', error);
      body.innerHTML = '<div style="padding: 40px; text-align: center; color: #999;">Error loading item details</div>';
    });

    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
  };

  // Render claim item form
  function renderClaimItemForm(item, bodyElement, footerElement, options) {
    const imageData = item.photo || item.image_data || '';
    let imageSrc = '';
    if (imageData) {
      imageSrc = imageData.startsWith('data:') ? imageData : 'data:image/jpeg;base64,' + imageData;
    }

    const html = `
      <div class="claim-item-left">
        <div class="claim-item-image-wrap">
          ${imageSrc ? `<img src="${imageSrc}" alt="Item" class="claim-item-image">` : '<div class="claim-item-image-placeholder"><i class="fa-solid fa-image"></i><span>No image</span></div>'}
        </div>
        <p class="claim-item-barcode-id">${escapeHtml(item.id || '')}</p>
      </div>
      <div class="claim-item-right">
        <h3 class="claim-item-info-title">General Information</h3>
        <dl class="claim-item-info-list">
          <div class="claim-item-info-row">
            <dt>Category:</dt>
            <dd>${escapeHtml(item.category || item.item_type || 'Miscellaneous')}</dd>
          </div>
          <div class="claim-item-info-row">
            <dt>Item:</dt>
            <dd>${escapeHtml(item.brand || '-')}</dd>
          </div>
          <div class="claim-item-info-row">
            <dt>Color:</dt>
            <dd>${escapeHtml(item.color || '-')}</dd>
          </div>
          <div class="claim-item-info-row">
            <dt>Brand:</dt>
            <dd>${escapeHtml(item.brand || '-')}</dd>
          </div>
          <div class="claim-item-info-row">
            <dt>Date Found:</dt>
            <dd>${escapeHtml(item.date_encoded || item.created_at ? formatDate(item.date_encoded || item.created_at) : '-')}</dd>
          </div>
        </dl>

        <form class="claim-item-form" id="claimItemForm">
          <div class="claim-item-form-group">
            <label for="proofDescription" class="claim-item-form-label">Proof Description *</label>
            <textarea id="proofDescription" name="proof_description" class="claim-item-form-textarea" placeholder="Describe how you can identify this item..." required></textarea>
            <small style="color: #999; font-size: 12px;">Minimum 10 characters</small>
          </div>
        </form>
      </div>
    `;

    bodyElement.innerHTML = html;

    // Render footer buttons
    let footerHTML = '<button type="button" class="claim-item-btn claim-item-btn-cancel" id="claimItemCancel">Cancel</button>';
    footerHTML += '<button type="button" class="claim-item-btn claim-item-btn-submit" id="claimItemSubmit">Claim</button>';
    footerElement.innerHTML = footerHTML;

    // Attach event listeners
    const cancelBtn = document.getElementById('claimItemCancel');
    const submitBtn = document.getElementById('claimItemSubmit');
    const form = document.getElementById('claimItemForm');

    if (cancelBtn) {
      cancelBtn.addEventListener('click', closeClaimItemModal);
    }

    if (submitBtn && form) {
      submitBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const description = document.getElementById('proofDescription').value.trim();
        
        if (description.length < 10) {
          alert('Proof description must be at least 10 characters');
          return;
        }

        // Submit claim via API
        submitClaim(item.id, description, options);
      });
    }
  }

  // Submit claim via API
  function submitClaim(itemId, description, options) {
    const submitBtn = document.getElementById('claimItemSubmit');
    if (submitBtn) submitBtn.disabled = true;

    fetch(`/LOSTANDFOUND/api/student/claims`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        found_item_id: itemId,
        proof_description: description
      })
    })
    .then(response => response.json())
    .then(data => {
      if (data.ok) {
        alert('Claim submitted successfully!');
        closeClaimItemModal();
        if (options.onSubmit) {
          options.onSubmit(data.data);
        }
        // Reload the page to show updated data
        setTimeout(() => {
          window.location.reload();
        }, 500);
      } else {
        alert('Error submitting claim: ' + (data.error || 'Unknown error'));
        if (submitBtn) submitBtn.disabled = false;
      }
    })
    .catch(error => {
      console.error('Error submitting claim:', error);
      alert('Error submitting claim');
      if (submitBtn) submitBtn.disabled = false;
    });
  }

  // Close modal
  window.closeClaimItemModal = function() {
    const overlay = document.getElementById('claimItemOverlay');
    if (overlay) {
      overlay.classList.remove('open');
      overlay.setAttribute('aria-hidden', 'true');
    }
  };

  // Close button handler
  document.addEventListener('click', function(e) {
    if (e.target.id === 'claimItemClose') {
      closeClaimItemModal();
    }
  });

  // ESC key handler
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const overlay = document.getElementById('claimItemOverlay');
      if (overlay && overlay.classList.contains('open')) {
        closeClaimItemModal();
      }
    }
  });

  // Click outside modal to close
  document.addEventListener('click', function(e) {
    const overlay = document.getElementById('claimItemOverlay');
    if (overlay && overlay.classList.contains('open') && e.target === overlay) {
      closeClaimItemModal();
    }
  });

  // Helper functions
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }

  function formatDate(dateString) {
    try {
      const date = new Date(dateString);
      return date.toISOString().split('T')[0];
    } catch (e) {
      return dateString;
    }
  }
})();
