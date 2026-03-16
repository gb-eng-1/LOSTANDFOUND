/**
 * Item Details Modal Handler
 * Handles displaying item details in a modal popup
 */

(function() {
  'use strict';

  // Create modal HTML — circle close button to match new design
  const modalHTML = `
    <div id="itemDetailsOverlay" class="item-details-overlay" aria-hidden="true" role="dialog" aria-labelledby="itemDetailsTitle">
      <div class="item-details-dialog">
        <div class="item-details-header">
          <h2 id="itemDetailsTitle" class="item-details-title">Item Details</h2>
          <button type="button" class="item-details-close" aria-label="Close" id="itemDetailsClose">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div class="item-details-body" id="itemDetailsBody">
          <!-- Content will be loaded here -->
        </div>
        <div class="item-details-footer" id="itemDetailsFooter">
          <!-- Buttons will be loaded here -->
        </div>
      </div>
    </div>
  `;

  // Add modal to page
  function initModal() {
    if (!document.getElementById('itemDetailsOverlay')) {
      const container = document.createElement('div');
      container.innerHTML = modalHTML;
      document.body.appendChild(container.firstElementChild);
    }
  }

  // Show item details modal
  window.showItemDetailsModal = function(itemId, options = {}) {
    options = Object.assign({
      showClaimButton: false,
      onClaim: null,
      onClose: null
    }, options);

    initModal();

    const overlay   = document.getElementById('itemDetailsOverlay');
    const body      = document.getElementById('itemDetailsBody');
    const footer    = document.getElementById('itemDetailsFooter');

    if (!overlay || !body || !footer) return;

    // Show loading state
    body.innerHTML = '<div style="padding: 60px; text-align: center; width:100%;"><i class="fa-solid fa-spinner fa-spin" style="font-size: 32px; color: #8b0000;"></i><p style="margin-top:12px;color:#888;">Loading...</p></div>';
    footer.innerHTML = '';

    const isReport = itemId.startsWith('REF-') || itemId.startsWith('TIC-');
    const endpoint = `/LOSTANDFOUND/STUDENT/get_report.php?id=${encodeURIComponent(itemId)}`;

    fetch(endpoint, { method: 'GET', credentials: 'include' })
      .then(response => response.json())
      .then(data => {
        if (data.ok && data.data) {
          renderItemDetails(data.data, body, footer, options, isReport);
        } else {
          body.innerHTML = '<div style="padding: 60px; text-align: center; color: #999; width:100%;">Item not found</div>';
        }
      })
      .catch(error => {
        console.error('Error loading item details:', error);
        body.innerHTML = '<div style="padding: 60px; text-align: center; color: #999; width:100%;">Error loading item details</div>';
      });

    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
  };

  // Render item details in the new two-column layout
  function renderItemDetails(item, bodyElement, footerElement, options, isReport) {
    const imageData = item.photo || item.image_data || '';
    let imageSrc = '';
    if (imageData) {
      imageSrc = imageData.startsWith('data:') ? imageData : 'data:image/jpeg;base64,' + imageData;
    }

    // Build info rows depending on item type
    let infoRows = '';

    if (isReport) {
      // --- Lost report (REF- / TIC-) ---
      let studentNum = '', contact = '', dept = '', itemName = '', mainDesc = '', fullName = '';

      if (item.item_description) {
        const desc = item.item_description;
        const get = (key) => { const m = desc.match(new RegExp(key + ':\\s*(.+?)(?:\\n|$)')); return m ? m[1].trim() : ''; };
        studentNum = get('Student Number') || get('Student ID');
        contact    = get('Contact');
        dept       = get('Department');
        itemName   = get('Item Type') || get('Item Name');
        fullName   = get('Full Name') || get('Name');
        mainDesc   = desc
          .replace(/Student Number:[^\n]*\n?/g, '')
          .replace(/Student ID:[^\n]*\n?/g, '')
          .replace(/Item Type:[^\n]*\n?/g, '')
          .replace(/Item Name:[^\n]*\n?/g, '')
          .replace(/Contact:[^\n]*\n?/g, '')
          .replace(/Department:[^\n]*\n?/g, '')
          .replace(/Full Name:[^\n]*\n?/g, '')
          .replace(/Name:[^\n]*\n?/g, '')
          .trim();
      }

      const rows = [
        ['Category',        item.item_type || 'Miscellaneous'],
        ['Full Name',       fullName   || '-'],
        ['Contact Number',  contact    || '-'],
        ['Department',      dept       || '-'],
        ['ID',              studentNum || '-'],
        ['Item',            itemName   || item.item_type || '-'],
        ['Color',           item.color || '-'],
        ['Brand',           item.brand || '-'],
        ['Item Description', mainDesc  || '-'],
        ['Date Lost',       item.date_lost ? formatDate(item.date_lost) : '-'],
      ];
      infoRows = rows.map(([label, val]) => makeRow(label, val)).join('');

    } else {
      // --- Found item (UB-...) ---
      const rows = [
        ['Category',        item.item_type || 'Miscellaneous'],
        ['Color',           item.color     || '-'],
        ['Brand',           item.brand     || '-'],
        ['Location Found',  item.found_at  || '-'],
        ['Item Description', item.item_description || '-'],
        ['Date Found',      item.date_encoded || item.created_at ? formatDate(item.date_encoded || item.created_at) : '-'],
      ];
      infoRows = rows.map(([label, val]) => makeRow(label, val)).join('');
    }

    // Build full body HTML
    bodyElement.innerHTML = `
      <div class="item-details-left">
        <div class="item-details-image-wrap">
          ${imageSrc
            ? `<img src="${imageSrc}" alt="Item" class="item-details-image">`
            : `<div class="item-details-image-placeholder">
                 <i class="fa-regular fa-image"></i>
                 <span>No image</span>
               </div>`
          }
        </div>
        <p class="item-details-barcode-id">${escapeHtml(item.id || '')}</p>
      </div>
      <div class="item-details-right">
        <h3 class="item-details-info-title">General Information</h3>
        <hr class="item-details-divider">
        <dl class="item-details-info-list">
          ${infoRows}
        </dl>
      </div>
    `;

    // Footer — Close button removed (header × already closes).
    // Claim button is still shown for found items when the caller opts in.
    let footerHTML = '';
    if (options.showClaimButton && !isReport) {
      footerHTML = `<button type="button" class="item-details-btn item-details-btn-claim" id="itemDetailsClaim">Claim</button>`;
    }
    footerElement.innerHTML = footerHTML;
    // Hide the footer bar entirely when there are no buttons
    if (footerElement) footerElement.style.display = footerHTML ? '' : 'none';

    const claimBtn = document.getElementById('itemDetailsClaim');
    if (claimBtn && options.onClaim) {
      claimBtn.addEventListener('click', () => options.onClaim(item));
    }
  }

  function makeRow(label, value) {
    return `
      <div class="item-details-info-row">
        <dt>${escapeHtml(label)}:</dt>
        <dd>${escapeHtml(String(value))}</dd>
      </div>`;
  }

  // Close modal
  window.closeItemDetailsModal = function() {
    const overlay = document.getElementById('itemDetailsOverlay');
    if (overlay) {
      overlay.classList.remove('open');
      overlay.setAttribute('aria-hidden', 'true');
    }
  };

  // Close button click
  document.addEventListener('click', function(e) {
    if (e.target.id === 'itemDetailsClose' || e.target.closest('#itemDetailsClose')) {
      closeItemDetailsModal();
    }
  });

  // ESC key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      const overlay = document.getElementById('itemDetailsOverlay');
      if (overlay && overlay.classList.contains('open')) closeItemDetailsModal();
    }
  });

  // Click outside (backdrop)
  document.addEventListener('click', function(e) {
    const overlay = document.getElementById('itemDetailsOverlay');
    if (overlay && overlay.classList.contains('open') && e.target === overlay) {
      closeItemDetailsModal();
    }
  });

  // --- Helpers ---
  function escapeHtml(text) {
    const map = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
  }

  function formatDate(dateString) {
    try {
      return new Date(dateString).toISOString().split('T')[0];
    } catch (e) {
      return dateString;
    }
  }

})();