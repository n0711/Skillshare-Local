// assets/js/browse_sessions.js
$(function () {
  function applyFilters() {
    const qTitle = $("#searchTitle").val().toLowerCase();
    const cat = $("#filterCategory").val();
    const city = $("#filterCity").val().toLowerCase();
    const fee = $("#filterFee").val();

    let visibleCount = 0;

    $(".card-session").each(function () {
      const t = $(this).data("title");
      const c = $(this).data("category");
      const ct = $(this).data("city");
      const f = $(this).data("fee");

      let show = true;

      if (qTitle && t.indexOf(qTitle) === -1) show = false;
      if (cat && c !== cat) show = false;
      if (city && (!ct || ct.indexOf(city) === -1)) show = false;
      if (fee && f !== fee) show = false;

      $(this).toggle(show);
      if (show) visibleCount++;
    });

    $("#sessionCount").text(visibleCount);
  }

  $("#searchTitle, #filterCategory, #filterCity, #filterFee")
    .on("input change", applyFilters);

  // Initial count
  applyFilters();
});
