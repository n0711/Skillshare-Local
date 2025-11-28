<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_once "../includes/header.php";

$user = current_user();

// Basic stats
$total_sessions = 0;
$res = $mysqli->query("SELECT COUNT(*) AS c FROM sessions");
if ($res) { $total_sessions = (int)$res->fetch_assoc()["c"]; }

$total_learners = 0;
$res = $mysqli->query("SELECT COUNT(*) AS c FROM users WHERE role = 'learner'");
if ($res) { $total_learners = (int)$res->fetch_assoc()["c"]; }

$total_attended = 0;
$res = $mysqli->query("SELECT COUNT(*) AS c FROM bookings WHERE attended = 1");
if ($res) { $total_attended = (int)$res->fetch_assoc()["c"]; }

// CO2 estimate
$total_kg = 0.0;
$sql = "
    SELECT
        COUNT(CASE WHEN b.attended = 1 THEN 1 END) AS participants,
        IFNULL(i.co2_saved_per_participant_kg, 0) AS factor
    FROM sessions s
    JOIN categories c ON s.category_id = c.id
    LEFT JOIN bookings b ON b.session_id = s.id
    LEFT JOIN impact_factors i ON i.category_id = c.id
    GROUP BY c.id, c.name, i.co2_saved_per_participant_kg
";
$res = $mysqli->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $total_kg += (float)$r["participants"] * (float)$r["factor"];
    }
}
$total_tons = $total_kg / 1000.0;
?>

<div class="row mb-4">
  <div class="col-lg-7 mb-3">
    <div class="p-4 hero-card shadow-sm h-100">
      <span class="hero-pill">
        🌍 Neighbour-powered climate learning
      </span>
      <h1 class="mb-3">SkillShare Local</h1>
      <p class="lead">
        A playful marketplace where neighbours teach each other sustainable skills:
        composting, bike repair, home energy saving, DIY water hacks, and more.
      </p>
      <p>
        Instructors publish short, hands-on sessions.
        Learners book a spot.
        The platform estimates and visualizes the climate impact of the knowledge shared.
      </p>

      <?php if (!$user): ?>
        <div class="mt-3">
          <a href="register.php" class="btn btn-success me-2">Join the community</a>
          <a href="sessions_browse.php" class="btn btn-outline-success">Browse sessions</a>
        </div>
      <?php elseif ($user["role"] === "instructor"): ?>
        <div class="mt-3">
          <a href="session_create.php" class="btn btn-success me-2">Create a new session</a>
          <a href="my_sessions.php" class="btn btn-outline-success">Manage my sessions</a>
        </div>
      <?php elseif ($user["role"] === "learner"): ?>
        <div class="mt-3">
          <a href="sessions_browse.php" class="btn btn-success me-2">Find a session</a>
          <a href="booking_manage.php" class="btn btn-outline-success">My bookings</a>
        </div>
      <?php elseif ($user["role"] === "admin"): ?>
        <div class="mt-3">
          <a href="impact_dashboard.php" class="btn btn-success me-2">Impact dashboard</a>
          <a href="admin_users.php" class="btn btn-outline-success">Manage users</a>
        </div>
      <?php endif; ?>

      <button type="button" class="btn btn-link px-0 mt-3 small" id="impactMoreToggle">
        More about how impact is calculated ↓
      </button>
      <div id="impactMore" class="small text-muted" style="display: none;">
        We use simple impact factors per participant in each category
        (e.g. kg CO₂ avoided from reduced food waste or energy use).
        These are based on public EU equivalence examples and are meant
        to be educational estimates &mdash; not exact carbon accounting.
      </div>
    </div>
  </div>

  <div class="col-lg-5 mb-3">
    <div class="row g-3">
      <div class="col-6">
        <div class="card text-center stat-card h-100">
          <div class="card-body">
            <div class="stat-icon">📚</div>
            <div class="h4 mb-1 stat-number" data-target="<?= $total_sessions ?>">
              <?= $total_sessions ?>
            </div>
            <div class="text-muted small">Sessions created</div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="card text-center stat-card h-100">
          <div class="card-body">
            <div class="stat-icon">🧑‍🎓</div>
            <div class="h4 mb-1 stat-number" data-target="<?= $total_learners ?>">
              <?= $total_learners ?>
            </div>
            <div class="text-muted small">Registered learners</div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="card text-center stat-card h-100">
          <div class="card-body">
            <div class="stat-icon">✅</div>
            <div class="h4 mb-1 stat-number" data-target="<?= $total_attended ?>">
              <?= $total_attended ?>
            </div>
            <div class="text-muted small">Seats attended</div>
          </div>
        </div>
      </div>
      <div class="col-6">
        <div class="card text-center stat-card h-100">
          <div class="card-body">
            <div class="stat-icon">🌱</div>
            <div class="h5 mb-1 stat-number" data-target="<?= $total_tons ?>">
              <?= number_format($total_tons, 2) ?>
            </div>
            <div class="text-muted small">Estimated tons of CO₂ saved</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<hr class="my-4">

<div class="row">
  <div class="col-md-4 mb-3">
    <h4 class="section-title">How it works</h4>
    <ol class="small">
      <li>Sign up as learner or instructor.</li>
      <li>Instructors create sessions with photos, capacity, and impact notes.</li>
      <li>Learners browse, book, and rate sessions after attending.</li>
      <li>The community impact dashboard tracks estimated CO₂ savings.</li>
    </ol>
  </div>
  <div class="col-md-4 mb-3">
    <h4 class="section-title">Sustainability focus</h4>
    <p class="small">
      Each session is linked to an impact factor per participant (e.g. kg CO₂ avoided).
      The platform aggregates these estimates to show how community learning supports
      SDG 4 (Quality Education) and SDG 12 (Responsible Consumption and Production).
    </p>
  </div>
  <div class="col-md-4 mb-3">
    <h4 class="section-title">Start exploring</h4>
    <p class="small mb-2">
      Use the session browser to filter by category, city or online, fee, and rating.
      Look for topics like composting, bike repair, solar setup, or home energy savings.
    </p>
    <a href="sessions_browse.php" class="btn btn-outline-success btn-sm">
      Browse all sessions
    </a>
  </div>
</div>

<script>
// jQuery: animated stats + toggle extra impact explanation
$(function() {
    // animate numbers in stat cards
    $(".stat-number").each(function() {
        const $this   = $(this);
        const target  = parseFloat($this.data("target")) || 0;
        const isFloat = (target % 1 !== 0);
        let current   = 0;
        const steps   = 40;
        const inc     = target / steps || 0;

        if (target === 0) {
            $this.text("0");
            return;
        }

        const interval = setInterval(function() {
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

    // show/hide extra impact explanation
    $("#impactMoreToggle").on("click", function(e) {
        e.preventDefault();
        $("#impactMore").slideToggle(150);
    });
});
</script>
<script src="/skillshare_local/assets/js/home.js"></script>

<?php require_once "../includes/footer.php"; ?>
