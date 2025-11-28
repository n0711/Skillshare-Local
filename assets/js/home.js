// assets/js/home.js
$(function () {
  // Animate stat numbers
  $(".stat-number").each(function () {
    const $this = $(this);
    const target = parseFloat($this.data("target")) || 0;
    const isFloat = target % 1 !== 0;
    let current = 0;
    const steps = 40;
    const inc = target / steps || 0;

    if (target === 0) {
      $this.text("0");
      return;
    }

    const interval = setInterval(function () {
      current += inc;
      if (current >= target) {
        current = target;
        clearInterval(interval);
      }

      if (isFloat) {
        $this.text(target < 1 ? current.toFixed(2) : current.toFixed(1));
      } else {
        $this.text(Math.round(current));
      }
    }, 30);
  });

  // Toggle impact explanation
  $("#impactMoreToggle").on("click", function (e) {
    e.preventDefault();
    $("#impactMore").slideToggle(150);
  });
});
