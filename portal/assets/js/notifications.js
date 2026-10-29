// ===== Elements & settings =====
const notifBell = document.querySelector(".notif-bell");
const notifDropdown = document.querySelector("#notif-dropdown");
const notifBadge = document.querySelector(".notif-badge");
const notifList = document.querySelector(".notif-list");
const notifMarkAll = document.querySelector(".notif-mark-all");

const NOTIF_INDEX_URL = NOTIF_ROOT + "/notifications";
const NOTIF_MARKREAD_URL = NOTIF_ROOT + "/notifications/markread";
const NOTIF_MARKALLREAD_URL = NOTIF_ROOT + "/notifications/markallread";
const NOTIF_POLL_MS = 30000;

// ===== Time-ago label =====
function timeAgo(dateStr) {
  const seconds = Math.floor(
    (Date.now() - new Date(dateStr.replace(" ", "T"))) / 1000,
  );
  if (seconds < 60) return "just now";
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `${days}d ago`;
  return formatDate(dateStr.replace(" ", "T"));
}

// ===== Render badge & list =====
function renderBadge(count) {
  if (!notifBadge) return;
  if (count > 0) {
    notifBadge.textContent = count > 99 ? "99+" : String(count);
    notifBadge.hidden = false;
  } else {
    notifBadge.hidden = true;
  }
}

function renderList(items) {
  if (!notifList) return;

  if (!items.length) {
    notifList.innerHTML = `<div class="notif-empty">You're all caught up.</div>`;
    return;
  }

  notifList.innerHTML = items
    .map((item) => {
      const isUnread = item.is_read == 0;
      const href = item.link ? `${NOTIF_ROOT}/${item.link}` : "#";
      return `
        <div class="notif-item${isUnread ? " unread" : ""}" data-id="${item.id}">
          <span class="notif-item-icon notif-item-icon--${item.variant}">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <use href="#${item.icon}" />
            </svg>
          </span>
          <div class="notif-item-body">
            <div class="notif-item-title">
              <a href="${href}" class="notif-item-link">${escapeHtml(item.title)}</a>
            </div>
            <div class="notif-item-message">${item.message_html}</div>
            <div class="notif-item-time">${timeAgo(item.created_at)}</div>
          </div>
          <button type="button" class="notif-item-toggle-read" aria-label="${isUnread ? "Mark as read" : "Mark as unread"}" title="${isUnread ? "Mark as read" : "Mark as unread"}">
            <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <use href="#${isUnread ? "open-mail-icon" : "email-icon"}" />
            </svg>
          </button>
          <span class="notif-item-dot" aria-hidden="true"></span>
        </div>`;
    })
    .join("");
}

// ===== Email toggle sync =====
function syncEmailToggle(data, requestedAt) {
  const toggle = document.querySelector("#email_notifications");
  if (
    !toggle ||
    toggle.disabled ||
    Number(toggle.dataset.savedAt) > requestedAt
  )
    return;

  toggle.checked = data.email_notifications;
  document.querySelector(".provider-blocked-note").hidden =
    !data.provider_blocked || toggle.checked;
}

// ===== Load & mark as read =====
async function loadNotifications() {
  const requestedAt = Date.now();
  try {
    const res = await fetch(NOTIF_INDEX_URL, {
      headers: { Accept: "application/json" },
    });
    if (!res.ok) return;
    const data = await res.json();
    renderBadge(data.unread_count);
    renderList(data.items);
    syncEmailToggle(data, requestedAt);
  } catch (e) {}
}

async function markRead(id, read = true) {
  try {
    await fetch(NOTIF_MARKREAD_URL, {
      method: "POST",
      keepalive: true,
      headers: {
        "Content-Type": "application/json",
        ...csrfHeaders(),
      },
      body: JSON.stringify({ id, read }),
    });
  } catch (e) {}
}

function openNotification(event, item) {
  if (event.target.closest(".notif-item-link")) {
    markRead(item.dataset.id);
    return;
  }
  item.querySelector(".notif-item-link")?.click();
}

async function markAllRead() {
  try {
    await fetch(NOTIF_MARKALLREAD_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...csrfHeaders(),
      },
    });
    loadNotifications();
  } catch (e) {}
}

// ===== Bell dropdown & clicks =====
if (notifBell && notifDropdown) {
  function closeNotifDropdown() {
    if (!notifDropdown.classList.contains("active")) return;
    notifDropdown.classList.remove("active");
    notifBell.setAttribute("aria-expanded", "false");
    setBackdrop(false);
  }

  notifBell.addEventListener("click", () => {
    const isOpen = notifDropdown.classList.toggle("active");
    notifBell.setAttribute("aria-expanded", isOpen);
    if (isOpen) {
      loadNotifications();
      document.dispatchEvent(
        new CustomEvent("header-dropdown-open", { detail: "notif" }),
      );
      setBackdrop(true);
    } else {
      setBackdrop(false);
    }
  });

  document.addEventListener("header-dropdown-open", (event) => {
    if (event.detail !== "notif") closeNotifDropdown();
  });

  document.addEventListener("click", (event) => {
    const clickedInside =
      notifBell.contains(event.target) || notifDropdown.contains(event.target);

    if (!clickedInside) closeNotifDropdown();
  });

  notifList?.addEventListener("click", async (event) => {
    const item = event.target.closest(".notif-item");
    if (!item) return;
    if (!event.target.closest(".notif-item-toggle-read")) {
      openNotification(event, item);
      return;
    }
    await markRead(item.dataset.id, item.classList.contains("unread"));
    loadNotifications();
  });

  notifMarkAll?.addEventListener("click", markAllRead);

  // ===== Start polling =====
  loadNotifications();
  setInterval(loadNotifications, NOTIF_POLL_MS);
}
