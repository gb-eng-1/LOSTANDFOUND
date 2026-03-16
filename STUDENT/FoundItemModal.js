(function () {
  function bindProtocolModal(modalId, triggerSelector) {
    var modal = document.getElementById(modalId);
    var openTriggers = document.querySelectorAll(triggerSelector);
    if (!modal || !openTriggers.length) return;

    var closeBtn = modal.querySelector('.found-item-modal-close');
    var backdrop = modal.querySelector('.found-item-modal-backdrop');

    function openModal() {
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }
    function closeModal() {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    openTriggers.forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
      });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (backdrop) backdrop.addEventListener('click', closeModal);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });
  }

  // Found + Claim protocol now share the same popup UI/behavior
  bindProtocolModal('foundItemModal', '[data-open-found-item]');
  bindProtocolModal('claimItemModal', '[data-open-claim-item]');
})();
