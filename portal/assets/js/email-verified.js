// ===== "Email verified" page =====
// Tells the tab that asked for the link to reload, then closes this tab.
// Browsers only let a script close tabs it opened, so if closing is blocked
// the button takes the person to the site instead.
(() => {
  const button = document.querySelector("#close-verified-tab");
  if (!button) return;

  notifyEmailVerified();

  button.addEventListener("click", () => {
    window.close();
    setTimeout(() => {
      location.href = button.dataset.continueUrl;
    }, 300);
  });
})();
