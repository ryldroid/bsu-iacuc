const notifBell = document.querySelector(".notif-bell");
const notifDropdown = document.querySelector("#notif-dropdown");
const notifBadge = document.querySelector(".notif-badge");
const notifList = document.querySelector(".notif-list");
const notifMarkAll = document.querySelector(".notif-mark-all");

const NOTIF_INDEX_URL = NOTIF_ROOT + "/notifications";
const NOTIF_MARKREAD_URL = NOTIF_ROOT + "/notifications/markread";
const NOTIF_MARKALLREAD_URL = NOTIF_ROOT + "/notifications/markallread";
const NOTIF_POLL_MS = 30000;

function escapeHtml(str) {
  const div = document.createElement("div");
  div.textContent = str ?? "";
  return div.innerHTML;
}

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
  return new Date(dateStr.replace(" ", "T")).toLocaleDateString();
}

const NOTIF_TYPE_STYLES = {
  account_verified: { icon: "shield-check-icon", variant: "success" },
  protocol_submitted: { icon: "upload-icon", variant: "info" },
  new_submission_admin: { icon: "upload-icon", variant: "info" },
  protocol_resubmitted: { icon: "upload-icon", variant: "info" },
  protocol_renamed: { icon: "edit-icon", variant: "info" },
  protocol_status_changed: { icon: "review-icon", variant: "info" },
  protocol_deletion_requested: {
    icon: "alert-triangle-icon",
    variant: "warning",
  },
  protocol_deletion_rejected: { icon: "close-icon", variant: "danger" },
  protocol_deleted: { icon: "trash-icon", variant: "danger" },
};
const NOTIF_TYPE_DEFAULT = { icon: "bell-icon", variant: "info" };

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
      const { icon, variant } =
        NOTIF_TYPE_STYLES[item.type] ?? NOTIF_TYPE_DEFAULT;
      return `
        <a href="${href}" class="notif-item${isUnread ? " unread" : ""}" data-id="${item.id}">
          <span class="notif-item-icon notif-item-icon--${variant}">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <use href="#${icon}" />
            </svg>
          </span>
          <div class="notif-item-body">
            <div class="notif-item-title">${escapeHtml(item.title)}</div>
            <div class="notif-item-message">${escapeHtml(item.message)}</div>
            <div class="notif-item-time">${timeAgo(item.created_at)}</div>
          </div>
          ${isUnread ? '<span class="notif-item-dot" aria-hidden="true"></span>' : ""}
        </a>`;
    })
    .join("");
}

async function loadNotifications() {
  try {
    const res = await fetch(NOTIF_INDEX_URL, {
      headers: { Accept: "application/json" },
    });
    if (!res.ok) return;
    const data = await res.json();
    renderBadge(data.unread_count);
    renderList(data.items);
  } catch (e) {
    /* silent:  bell just stays as-is */
  }
}

async function markRead(id) {
  try {
    await fetch(NOTIF_MARKREAD_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": NOTIF_CSRF_TOKEN,
      },
      body: JSON.stringify({ id }),
    });
  } catch (e) {}
}

async function markAllRead() {
  try {
    await fetch(NOTIF_MARKALLREAD_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": NOTIF_CSRF_TOKEN,
      },
    });
    loadNotifications();
  } catch (e) {}
}

if (notifBell && notifDropdown) {
  notifBell.addEventListener("click", () => {
    const isOpen = notifDropdown.classList.toggle("active");
    notifBell.setAttribute("aria-expanded", isOpen);
    if (isOpen) loadNotifications();
  });

  document.addEventListener("click", (event) => {
    const clickedInside =
      notifBell.contains(event.target) || notifDropdown.contains(event.target);

    if (!clickedInside) {
      notifDropdown.classList.remove("active");
      notifBell.setAttribute("aria-expanded", "false");
    }
  });

  notifList?.addEventListener("click", (event) => {
    const item = event.target.closest(".notif-item");
    if (!item) return;
    markRead(item.dataset.id);
  });

  notifMarkAll?.addEventListener("click", markAllRead);

  loadNotifications();
  setInterval(loadNotifications, NOTIF_POLL_MS);
}
