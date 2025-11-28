<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_login();
require_once "../includes/header.php";

$user = current_user();
if ($user["role"] !== "admin") {
    echo "<div class='alert alert-danger'>Access denied: admins only.</div>";
    require_once "../includes/footer.php";
    exit;
}

/*
 * For impact we count bookings with attended = 1.
 * For each category:
 *   participants = number of attended bookings
 *   factor       = co2_saved_per_participant_kg (from impact_factors)
 *   saved_kg     = participants * factor
 */

$sql = "
    SELECT
        c.id   AS category_id,
        c.name AS category_name,
        COUNT(CASE WHEN b.attended = 1 THEN 1 END) AS participants,
        IFNULL(i.co2_saved_per_participant_kg, 0) AS factor
    FROM sessions s
    JOIN categories c ON s.category_id = c.id
    LEFT JOIN bookings b ON b.session_id = s.id
    LEFT JOIN impact_factors i ON i.category_id = c.id
    GROUP BY c.id, c.name, i.co2_saved_per_participant_kg
    ORDER BY c.name
";

$result = $mysqli->query($sql);

$total_kg = 0;
$rows = [];
$labels      = [];
$savedKgData = [];
$participantsData = [];

while ($row = $result->fetch_assoc()) {
    $participants = (int)$row["participants"];
    $factor       = (float)$row["factor"];
    $saved_kg     = $participants * $factor;

    $row["saved_kg"] = $saved_kg;
    $rows[] = $row;

    $total_kg  += $saved_kg;

    $labels[]           = $row["category_name"];
    $savedKgData[]      = $saved_kg;
    $participantsData[] = $participants;
}

$total_tons = $total_kg / 1000.0;

// prepare data for JS
$labels_json       = json_encode($labels);
$savedKg_json      = json_encode($savedKgData);
$participants_json = json_encode($participantsData);
?>

<h1>Impact dashboard</h1>

<p class="lead">
  Estimated carbon savings based on attended sessions and category impact factors.
</p>

<div class="row mb-4">
  <div class="col-md-4 mb-2">
    <div class="card">
      <div class="card-body text-center">
        <div class="h4 mb-1"><?= number_format($total_kg, 1) ?> kg</div>
        <div class="text-muted">Total CO₂ saved</div>
      </div>
    </div>
  </div>
  <div class="col-md-4 mb-2">
    <div class="card">
      <div class="card-body text-center">
        <div class="h4 mb-1"><?= number_format($total_tons, 3) ?> t</div>
        <div class="text-muted">Total CO₂ (tons)</div>
      </div>
    </div>
  </div>
</div>

<?php if (!$rows): ?>
    <div class="alert alert-info">No data yet. Mark some bookings as attended first.</div>
<?php else: ?>

    <!-- Graph area -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">CO₂ savings per category</h5>
        <canvas id="impactChart" height="120"></canvas>
      </div>
    </div>

    <!-- Table view -->
    <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>Category</th>
          <th>Participants (attended)</th>
          <th>CO₂ per participant (kg)</th>
          <th>Total saved (kg)</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r["category_name"]) ?></td>
          <td><?= (int)$r["participants"] ?></td>
          <td><?= number_format($r["factor"], 2) ?></td>
          <td><?= number_format($r["saved_kg"], 1) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <p class="small text-muted">
      These are rough estimates. Each category’s impact factor is an approximate average kg CO₂ avoided per learner,
      based on typical examples like food waste avoidance, cycling instead of driving, or home energy savings.
      Actual impact depends on how participants apply what they learn.
    </p>

<?php endif; ?>

<!-- Chart.js only on this page -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const labels       = <?= $labels_json ?>;
    const savedKgData  = <?= $savedKg_json ?>;
    const partData     = <?= $participants_json ?>;

    const ctx = document.getElementById('impactChart');
    if (!ctx || labels.length === 0) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'CO₂ saved (kg)',
                    data: savedKgData
                },
                {
                    type: 'line',
                    label: 'Participants (attended)',
                    data: partData,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'CO₂ (kg)'
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    title: {
                        display: true,
                        text: 'Participants'
                    }
                }
            }
        }
    });
})();
</script>

<?php require_once "../includes/footer.php"; ?>
