// Admin Notifications Dropdown - live, polling /api/notifications
(function () {
  const trigger = document.getElementById('notifTrigger');
  const panel   = document.getElementById('notifPanel');
  const wrapper = document.getElementById('notifDropdown');
  const list    = panel ? panel.querySelector('.notif-list') : null;
  const panelCount = panel ? panel.querySelector('.notif-panel-count') : null;

  if (!trigger || !panel || !list) return;

  let lastUnreadCount = -1;

  function mapAdminLink(n) {
    const t = (n.type || '').toLowerCase();
    if (t.includes('lost') || t.includes('report')) return 'AdminReports.php';
    if (t.includes('match')) return 'ItemMatchedAdmin.php';
    if (t.includes('claim')) return 'HistoryAdmin.php';
    return 'AdminDashboard.php';
  }

  function renderNotifications(notifications) {
    list.innerHTML = '';
    if (!notifications || notifications.length === 0) {
      list.innerHTML = '<div class="notif-empty">No notifications yet.</div>';
      return;
    }

    notifications.forEach(n => {
      const isNew = !n.is_read;
      const time = new Date(n.created_at);
      const now = new Date();
      const diff = Math.floor((now - time) / 1000);
      let timeAgo = '';
      if (diff < 60) timeAgo = 'Just now';
      else if (diff < 3600) timeAgo = Math.floor(diff / 60) + 'm ago';
      else if (diff < 86400) timeAgo = Math.floor(diff / 3600) + 'h ago';
      else timeAgo = time.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

      const link = mapAdminLink(n);
      const itemHtml = `
        <div class="notif-item ${isNew ? 'notif-item-new' : ''}" data-id="${n.id}">
          <div class="notif-item-thumb">
            <img src="images/notif_placeholder.jpg"
                 alt="Item"
                 class="notif-thumb-img"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
            <div class="notif-thumb-placeholder" style="display:none;"><i class="fa-solid fa-box-open"></i></div>
          </div>
          <div class="notif-item-body">
            <div class="notif-item-top">
              <span class="notif-item-title">${n.title || ''}</span>
              ${isNew ? '<span class="notif-item-new-badge">New</span>' : ''}
              <span class="notif-item-time">${timeAgo}</span>
            </div>
            <div class="notif-item-message">
              ${n.message || ''}
              <a href="${link}" class="notif-view-link">View Details</a>
            </div>
          </div>
        </div>
      `;
      list.insertAdjacentHTML('beforeend', itemHtml);
    });
  }

  function fetchNotifications() {
    fetch('/LOSTANDFOUND/api/notifications')
      .then(res => res.json())
      .then(json => {
        if (json.ok) {
          renderNotifications(json.data);
        }
      })
      .catch(err => console.error('Failed to fetch notifications', err));
  }

  function updateUnreadCount(count) {
    let badge = trigger.querySelector('.notif-badge');
    if (count > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'notif-badge';
        trigger.appendChild(badge);
      }
      badge.textContent = String(count);
      if (panelCount) panelCount.textContent = `${count} new`;
    } else {
      if (badge) badge.remove();
      if (panelCount) panelCount.textContent = '';
    }
  }

  function pollForUnreadCount() {
    fetch('/LOSTANDFOUND/api/notifications/count')
      .then(res => res.json())
      .then(json => {
        if (json.ok) {
          const count = json.data.unread_count;
          if (count !== lastUnreadCount) {
            updateUnreadCount(count);
            lastUnreadCount = count;
          }
        }
      })
      .catch(err => console.error('Failed to poll notification count', err));
  }

  function markNotificationsAsRead() {
    const unreadItems = list.querySelectorAll('.notif-item-new');
    unreadItems.forEach(item => {
      const notifId = item.getAttribute('data-id');
      if (!notifId) return;
      fetch(`/LOSTANDFOUND/api/notifications/${notifId}/read`, { method: 'PUT' })
        .then(res => res.json())
        .then(json => {
          if (json.ok) {
            item.classList.remove('notif-item-new');
            const badge = item.querySelector('.notif-item-new-badge');
            if (badge) badge.remove();
          }
        })
        .catch(() => {});
    });
    updateUnreadCount(0);
  }

  function open() {
    panel.classList.add('open');
    trigger.setAttribute('aria-expanded', 'true');
    panel.setAttribute('aria-hidden', 'false');
  }

  function close() {
    panel.classList.remove('open');
    trigger.setAttribute('aria-expanded', 'false');
    panel.setAttribute('aria-hidden', 'true');
  }

  trigger.addEventListener('click', function (e) {
    e.stopPropagation();
    const isOpen = panel.classList.contains('open');
    if (!isOpen) {
      open();
      fetchNotifications();
      setTimeout(markNotificationsAsRead, 2000);
    } else {
      close();
    }
  });

  document.addEventListener('click', function (e) {
    if (wrapper && !wrapper.contains(e.target)) close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') close();
  });

  // Initial poll + interval for live badge updates
  pollForUnreadCount();
  setInterval(pollForUnreadCount, 15000);
})();
