// assets/js/register.js
$(function () {
  const $form = $("#registerForm");
  const $pass = $("#regPassword");
  const $confirm = $("#regPasswordConfirm");
  const $bar = $("#passwordStrengthBarInner");
  const $text = $("#passwordStrengthText");

  function updateStrength() {
    const val = $pass.val();
    let score = 0;

    if (val.length >= 8) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    let width = (score / 4) * 100;
    let color = "#ef4444"; // red
    let label = "Weak password";

    if (score === 0) {
      width = 0;
      color = "#e5e7eb";
      label = "At least 8 characters.";
    } else if (score === 1) {
      color = "#f97316"; // orange
      label = "Okay – add numbers & symbols.";
    } else if (score === 2) {
      color = "#f59e0b"; // amber
      label = "Decent – can be stronger.";
    } else if (score >= 3) {
      color = "#22c55e"; // green
      label = "Strong password.";
    }

    $bar.css({ width: width + "%", backgroundColor: color });
    $text.text(label);
  }

  $pass.on("input", updateStrength);

  $form.on("submit", function (e) {
    const p1 = $pass.val();
    const p2 = $confirm.val();

    if (p1.length < 8) {
      alert("Password must be at least 8 characters.");
      e.preventDefault();
      return false;
    }
    if (p1 !== p2) {
      alert("Password and confirmation do not match.");
      e.preventDefault();
      return false;
    }
    return true;
  });
});
