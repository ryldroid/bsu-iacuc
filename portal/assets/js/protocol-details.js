// ===== Protocol details toggle =====
document.addEventListener("click", (event) => {
  const toggle = event.target.closest(".protocol-details-toggle");
  if (!toggle) return;
  const open = toggle.getAttribute("aria-expanded") !== "true";
  toggle.setAttribute("aria-expanded", open);
  toggle.querySelector("span").textContent = open ? "Hide details" : "Details";
  document.getElementById(toggle.getAttribute("aria-controls")).hidden = !open;
});
