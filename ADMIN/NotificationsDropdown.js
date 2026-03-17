// Admin Notifications Dropdown
// Handles: toggle open/close, live fetch of notifications, mark-as-read, badge polling.
// Structure mirrors the student-side dropdown; admin-specific link mapping is handled in PHP.
(function () {
  var trigger  = document.getElementById('notifTrigger');
  var panel    = document.getElementById('notifPanel');
  var wrapper  = document.getElementById('notifDropdown');
  var list     = panel  ? panel.querySelector('.notif-list')        : null;
  var countEl  = panel  ? panel.querySelector('.notif-panel-count') : null;

  if (!trigger || !panel || !list) return;

  var lastUnreadCount = -1;

  /* ── Admin link mapping (mirrors PHP switch in notifications_dropdown.php) ── */
  function mapAdminLink(type) {
    type = (type || '').toLowerCase();
    if (type === 'lost_report_created' || type === 'report_updated')      return 'AdminReports.php';
    if (type === 'match_found' || type.startsWith('match_'))              return 'ItemMatchedAdmin.php';
    if (type === 'claim_submitted' || type.startsWith('claim_'))          return 'HistoryAdmin.php';
    if (type === 'disposal_warning' || type === 'expiry_warning')         return 'FoundAdmin.php';
    return 'AdminDashboard.php';
  }

  /* ── Render notifications into the panel list ── */
  function renderNotifications(notifications) {
    list.innerHTML = '';
    if (!notifications || notifications.length === 0) {
      list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
      return;
    }

    notifications.forEach(function (n) {
      var isNew = !n.is_read;
      var now   = new Date();
      var d     = new Date(n.created_at);
      var diff  = Math.floor((now - d) / 1000);
      var timeAgo;
      if      (diff < 60)    timeAgo = 'Just now';
      else if (diff < 3600)  timeAgo = Math.floor(diff / 60)   + 'm ago';
      else if (diff < 86400) timeAgo = Math.floor(diff / 3600) + 'h ago';
      else                   timeAgo = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

      var link = mapAdminLink(n.type);
      list.insertAdjacentHTML('beforeend',
        '<div class="notif-item' + (isNew ? ' notif-item-new' : '') + '" data-id="' + n.id + '">'
        + '<div class="notif-item-thumb">'
        +   '<img src="images/notif_placeholder.jpg" alt="Item" class="notif-thumb-img"'
        +        ' onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">'
        +   '<div class="notif-thumb-placeholder" style="display:none;"><i class="fa-solid fa-box-open"></i></div>'
        + '</div>'
        + '<div class="notif-item-body">'
        +   '<div class="notif-item-top">'
        +     '<span class="notif-item-title">' + escHtml(n.title || '') + '</span>'
        +     (isNew ? '<span class="notif-item-new-badge">New</span>' : '')
        +     '<span class="notif-item-time">' + timeAgo + '</span>'
        +   '</div>'
        +   '<div class="notif-item-message">'
        +     escHtml(n.message || '')
        +     ' <a href="' + link + '" class="notif-view-link">View Details</a>'
        +   '</div>'
        + '</div>'
        + '</div>'
      );
    });
  }

  function escHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
    });
  }

  /* ── Fetch latest notifications from API ── */
  function fetchNotifications() {
    fetch('/LOSTANDFOUND/api/notifications', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (json) { if (json.ok) renderNotifications(json.data); })
      .catch(function () {});
  }

  /* ── Update badge number ── */
  function updateBadge(count) {
    var badge = trigger.querySelector('.notif-badge');
    if (count > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'notif-badge';
        trigger.appendChild(badge);
      }
      badge.textContent = String(count);
      if (countEl) countEl.textContent = count + ' new';
    } else {
      if (badge) badge.remove();
      if (countEl) countEl.textContent = '';
    }
  }

  /* ── Poll unread count every 5 s ── */
  function pollCount() {
    fetch('/LOSTANDFOUND/api/notifications/count', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json.ok && json.data) {
          var count = json.data.unread_count || 0;
          if (count !== lastUnreadCount) {
            updateBadge(count);
            // If new notifications appeared and panel is open, refresh the list immediately
            if (count > lastUnreadCount && lastUnreadCount >= 0 && panel.classList.contains('open')) {
              fetchNotifications();
            }
            lastUnreadCount = count;
          }
        }
      })
      .catch(function () {});
  }

  /* ── Mark visible unread items as read ── */
  function markVisibleAsRead() {
    list.querySelectorAll('.notif-item-new').forEach(function (item) {
      var id = item.getAttribute('data-id');
      if (!id) return;
      fetch('/LOSTANDFOUND/api/notifications/' + id + '/read', {
        method: 'PUT', credentials: 'include'
      })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json.ok) {
            item.classList.remove('notif-item-new');
            var badge = item.querySelector('.notif-item-new-badge');
            if (badge) badge.remove();
          }
        })
        .catch(function () {});
    });
    updateBadge(0);
    lastUnreadCount = 0;
  }

  /* ── Open / close ── */
  function openPanel() {
    panel.classList.add('open');
    trigger.setAttribute('aria-expanded', 'true');
    panel.setAttribute('aria-hidden', 'false');
  }
  function closePanel() {
    panel.classList.remove('open');
    trigger.setAttribute('aria-expanded', 'false');
    panel.setAttribute('aria-hidden', 'true');
  }

  trigger.addEventListener('click', function (e) {
    e.stopPropagation();
    var isOpen = panel.classList.contains('open');
    if (!isOpen) {
      openPanel();
      fetchNotifications();
      setTimeout(markVisibleAsRead, 2000);
    } else {
      closePanel();
    }
  });

  document.addEventListener('click', function (e) {
    if (wrapper && !wrapper.contains(e.target)) closePanel();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closePanel();
  });

  /* ── Init ── */
  pollCount();
  setInterval(pollCount, 5000);
})();
