// assets/js/app.js
// Global behaviour shared by all pages
$(function () {
  // Enable Bootstrap tooltips if any are present
  if (typeof bootstrap !== "undefined") {
    const tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(function (el) {
      return new bootstrap.Tooltip(el);
    });
  }
});
