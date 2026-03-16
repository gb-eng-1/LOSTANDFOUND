<!-- CLAIM ITEM PROTOCOL Modal (uses FOUND modal UI) -->
<div id="claimItemModal" class="found-item-modal" role="dialog" aria-labelledby="claimItemModalTitle" aria-modal="true" aria-hidden="true">
  <div class="found-item-modal-backdrop"></div>
  <div class="found-item-modal-dialog">
    <header class="found-item-modal-header">
      <h2 id="claimItemModalTitle" class="found-item-modal-title">CLAIM ITEM PROTOCOL</h2>
      <button type="button" class="found-item-modal-close" aria-label="Close" title="Close"><i class="fa-solid fa-xmark"></i></button>
    </header>
    <div class="found-item-modal-body">
      <div class="found-item-illustration">
        <img src="images/FoundItem.jpg" alt="Claim item protocol illustration" class="found-item-illustration-img" onerror="this.style.display='none'; this.nextElementSibling.classList.add('show');">
        <div class="found-item-illustration-placeholder" aria-hidden="true">
          <i class="fa-solid fa-bag-shopping"></i>
          <span>Claim your item at security</span>
        </div>
      </div>
      <div class="found-item-steps">
        <div class="found-item-step">
          <div class="found-item-step-icon"><i class="fa-solid fa-file-lines"></i></div>
          <div class="found-item-step-content">
            <h3 class="found-item-step-title">1. REPORT LOST ITEM</h3>
            <p class="found-item-step-desc">Once you have reported a lost item, a unique Ticket ID will be generated.</p>
          </div>
        </div>
        <div class="found-item-step">
          <div class="found-item-step-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
          <div class="found-item-step-content">
            <h3 class="found-item-step-title">2. BROWSE &amp; LOCATE</h3>
            <p class="found-item-step-desc">Browse available items and check for potential matches.</p>
          </div>
        </div>
        <div class="found-item-step">
          <div class="found-item-step-icon"><i class="fa-solid fa-clipboard-check"></i></div>
          <div class="found-item-step-content">
            <h3 class="found-item-step-title">3. VERIFY &amp; CLAIM</h3>
            <p class="found-item-step-desc">Once verified, sign the logbook and retrieve your item.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
