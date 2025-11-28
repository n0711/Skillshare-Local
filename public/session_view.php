<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_once "../includes/header.php";

$id = intval($_GET["id"] ?? 0);
if ($id <= 0) {
    echo "<div class='alert alert-danger'>Invalid session ID.</div>";
    require_once "../includes/footer.php";
    exit;
}

// fetch session + instructor + category
$stmt = $mysqli->prepare("
    SELECT 
        s.*,
        c.name AS category_name,
        u.name AS instructor_name
    FROM sessions s
    JOIN categories c ON s.category_id = c.id
    JOIN users u ON s.instructor_id = u.id
    WHERE s.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();

if (!$session) {
    echo "<div class='alert alert-danger'>Session not found.</div>";
    require_once "../includes/footer.php";
    exit;
}

// current bookings (pending + accepted)
$stmt = $mysqli->prepare("
    SELECT COUNT(*) AS cnt
    FROM bookings
    WHERE session_id = ? AND status IN ('pending','accepted')
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($currentCount);
$stmt->fetch();
$stmt->close();

$is_full = ($session["capacity"] > 0 && $currentCount >= $session["capacity"]);

// average rating
$stmt = $mysqli->prepare("
    SELECT AVG(r.rating) AS avg_rating, COUNT(*) AS rating_count
    FROM ratings r
    JOIN bookings b ON r.booking_id = b.id
    WHERE b.session_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($avg_rating, $rating_count);
$stmt->fetch();
$stmt->close();

$user = current_user();
$booking_message = "";
$booking_error   = "";

// is session in the past?
$is_past = (strtotime($session["start_datetime"]) < time());

// handle booking request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["book"])) {
    if (!$user) {
        $booking_error = "You must be logged in as a learner to book.";
    } elseif ($user["role"] !== "learner") {
        $booking_error = "Only learner accounts can book sessions.";
    } elseif ($is_past) {
        $booking_error = "This session has already taken place.";
    } elseif ($is_full) {
        $booking_error = "This session is already full.";
    } else {
        $learner_id = $user["id"];

        // prevent duplicate booking
        $stmt = $mysqli->prepare("
            SELECT id FROM bookings
            WHERE session_id = ? AND learner_id = ? AND status != 'cancelled'
        ");
        $stmt->bind_param("ii", $id, $learner_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $booking_error = "You have already requested a booking for this session.";
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO bookings (session_id, learner_id, status)
                VALUES (?, ?, 'pending')
            ");
            $stmt->bind_param("ii", $id, $learner_id);
            if ($stmt->execute()) {
                $booking_message = "Booking request sent! The instructor will review it.";
                // update counts
                $stmt2 = $mysqli->prepare("
                    SELECT COUNT(*) AS cnt
                    FROM bookings
                    WHERE session_id = ? AND status IN ('pending','accepted')
                ");
                $stmt2->bind_param("i", $id);
                $stmt2->execute();
                $stmt2->bind_result($currentCount);
                $stmt2->fetch();
                $stmt2->close();
                $is_full = ($session["capacity"] > 0 && $currentCount >= $session["capacity"]);
            } else {
                $booking_error = "Database error while creating booking.";
            }
        }
    }
}
?>

<h1><?= htmlspecialchars($session["title"]) ?></h1>
<p class="small text-muted mb-1">
  This session supports:
  <span class="badge bg-success text-light">
    SDG 4 – Quality Education
  </span>
  <span class="badge bg-success text-light">
    SDG 12 – Responsible Consumption
  </span>
</p>

<div class="row mb-3">
  <div class="col-md-4 mb-2">
    <?php if (!empty($session["photo_path"])): ?>
      <img src="/skillshare_local/<?= htmlspecialchars($session["photo_path"]) ?>"
           alt="Session photo"
           class="img-fluid rounded"
           style="max-height: 250px; object-fit: cover;">
    <?php else: ?>
      <div class="d-flex justify-content-center align-items-center bg-light text-muted"
           style="height: 200px;">
        No photo provided
      </div>
    <?php endif; ?>
  </div>
  <div class="col-md-8">
    <p class="text-muted">
      <?= htmlspecialchars($session["category_name"]) ?> •
      <?= htmlspecialchars($session["mode"]) ?>
      <?php if ($session["mode"] === "in-person" && $session["location_city"]): ?>
        • <?= htmlspecialchars($session["location_city"]) ?>, <?= htmlspecialchars($session["location_address"]) ?>
      <?php endif; ?>
      • with <?= htmlspecialchars($session["instructor_name"]) ?>
    </p>

    <?php if ($rating_count > 0): ?>
      <p>
        ⭐ <?= number_format($avg_rating, 1) ?>/5
        (<?= (int)$rating_count ?> rating<?= $rating_count > 1 ? "s" : "" ?>)
      </p>
    <?php else: ?>
      <p class="text-muted">Not rated yet.</p>
    <?php endif; ?>
  </div>
</div>

<p>
  <span class="badge bg-secondary">
    <?= date("d M Y H:i", strtotime($session["start_datetime"])) ?>
  </span>

  <?php if ($session["fee_type"] === "free"): ?>
    <span class="badge bg-success">Free</span>
  <?php else: ?>
    <span class="badge bg-primary"><?= number_format($session["fee_amount"], 2) ?> €</span>
  <?php endif; ?>

  <span class="badge bg-dark">Capacity: <?= (int)$session["capacity"] ?></span>
  <span class="badge bg-info">Booked: <?= (int)$currentCount ?></span>

  <?php if ($is_full): ?>
    <span class="badge bg-danger">Full</span>
  <?php endif; ?>

  <?php if ($is_past): ?>
    <span class="badge bg-secondary">Completed</span>
  <?php endif; ?>
</p>

<p><?= nl2br(htmlspecialchars($session["description"])) ?></p>

<?php if ($session["mode"] === "online" && $user && $user["id"] == $session["instructor_id"]): ?>
    <p><strong>Online link:</strong> <?= htmlspecialchars($session["online_link"]) ?></p>
<?php endif; ?>

<hr>

<?php if ($booking_message): ?>
  <div class="alert alert-success"><?= htmlspecialchars($booking_message) ?></div>
<?php endif; ?>

<?php if ($booking_error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($booking_error) ?></div>
<?php endif; ?>

<?php if ($is_past): ?>
  <div class="alert alert-warning">This session has already taken place. New bookings are closed.</div>
<?php elseif ($is_full): ?>
  <div class="alert alert-warning">This session is currently full.</div>
<?php elseif (!$user): ?>
  <div class="alert alert-info">
    Log in as a learner to request a booking.
    <a href="login.php" class="alert-link">Login</a>
  </div>
<?php elseif ($user["role"] !== "learner"): ?>
  <div class="alert alert-info">Only learner accounts can book sessions.</div>
<?php else: ?>
  <form method="post">
    <button name="book" value="1" class="btn btn-success">
      Request booking
    </button>
  </form>
<?php endif; ?>

<p class="mt-3">
  <a href="sessions_browse.php">&larr; Back to sessions</a>
</p>

<?php require_once "../includes/footer.php"; ?>
