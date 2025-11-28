<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_login();
if (!is_role("instructor")) {
    die("Access denied: instructors only.");
}
require_once "../includes/header.php";

$instructor_id = current_user()["id"];

$stmt = $mysqli->prepare("
    SELECT 
        s.*,
        c.name AS category_name,
        SUM(CASE WHEN b.status IN ('pending','accepted') THEN 1 ELSE 0 END) AS current_bookings
    FROM sessions s
    JOIN categories c ON s.category_id = c.id
    LEFT JOIN bookings b ON b.session_id = s.id
    WHERE s.instructor_id = ?
    GROUP BY 
        s.id, s.title, s.category_id, s.description, s.duration_minutes,
        s.fee_type, s.fee_amount, s.mode, s.location_city, s.location_address,
        s.online_link, s.photo_path, s.capacity, s.start_datetime, s.status,
        c.name
    ORDER BY s.start_datetime DESC
");
$stmt->bind_param("i", $instructor_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<h1>My sessions</h1>

<p class="text-muted">
  Manage your listings: edit details, publish / unpublish, and track bookings.
</p>

<?php if ($result->num_rows === 0): ?>
  <div class="alert alert-info">
    You haven’t created any sessions yet.
    <a href="session_create.php" class="alert-link">Create your first one.</a>
  </div>
<?php else: ?>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Title</th>
        <th>Category</th>
        <th>Date &amp; time</th>
        <th>Status</th>
        <th>Booked</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php
      $now = time();
      while ($row = $result->fetch_assoc()):
          $start_ts = strtotime($row["start_datetime"]);

          // Status logic:
          // - if status = inactive => Unpublished
          // - else if past date    => Completed
          // - else                 => Active
          if ($row["status"] === "inactive") {
              $status_label = "Unpublished";
              $status_class = "bg-secondary";
              $toggle_label = "Publish";
          } elseif ($start_ts < $now) {
              $status_label = "Completed";
              $status_class = "bg-dark";
              $toggle_label = "Unpublish";
          } else {
              $status_label = "Active";
              $status_class = "bg-success";
              $toggle_label = "Unpublish";
          }

          $booked   = (int)$row["current_bookings"];
          $capacity = (int)$row["capacity"];
    ?>
      <tr>
        <td>
          <strong><?= htmlspecialchars($row["title"]) ?></strong><br>
          <span class="small text-muted">
            <?= htmlspecialchars($row["mode"]) ?>
            <?php if ($row["mode"] === "in-person" && $row["location_city"]): ?>
              • <?= htmlspecialchars($row["location_city"]) ?>
            <?php endif; ?>
          </span>
        </td>
        <td><?= htmlspecialchars($row["category_name"]) ?></td>
        <td><?= date("d M Y H:i", $start_ts) ?></td>
        <td>
          <span class="badge <?= $status_class ?>">
            <?= $status_label ?>
          </span>
        </td>
        <td>
          <span class="badge bg-info">
            <?= $booked ?> / <?= $capacity ?>
          </span>
        </td>
        <td>
          <a href="session_edit.php?id=<?= $row["id"] ?>" class="btn btn-sm btn-outline-primary">
            Edit
          </a>
          <a href="session_toggle_status.php?id=<?= $row["id"] ?>"
             class="btn btn-sm btn-outline-secondary">
            <?= $toggle_label ?>
          </a>
          <a href="booking_manage.php?session_id=<?= $row["id"] ?>"
             class="btn btn-sm btn-outline-success">
            View bookings
          </a>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<?php endif; ?>

<?php require_once "../includes/footer.php"; ?>
