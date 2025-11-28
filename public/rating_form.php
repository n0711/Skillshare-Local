<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_login();
require_once "../includes/header.php";

$user = current_user();
if ($user["role"] !== "learner") {
    echo "<div class='alert alert-danger'>Only learners can submit ratings.</div>";
    require_once "../includes/footer.php";
    exit;
}

$booking_id = intval($_GET["booking_id"] ?? $_POST["booking_id"] ?? 0);
if ($booking_id <= 0) {
    echo "<div class='alert alert-danger'>Invalid booking.</div>";
    require_once "../includes/footer.php";
    exit;
}

// make sure this booking belongs to this learner and attended = 1
$stmt = $mysqli->prepare("
    SELECT 
        b.id,
        b.attended,
        b.status,
        s.title AS session_title
    FROM bookings b
    JOIN sessions s ON b.session_id = s.id
    WHERE b.id = ? AND b.learner_id = ?
");
$stmt->bind_param("ii", $booking_id, $user["id"]);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo "<div class='alert alert-danger'>Booking not found.</div>";
    require_once "../includes/footer.php";
    exit;
}

if (!$booking["attended"]) {
    echo "<div class='alert alert-warning'>You can only rate sessions you attended.</div>";
    require_once "../includes/footer.php";
    exit;
}

// check if rating already exists
$stmt = $mysqli->prepare("SELECT id, rating, comment FROM ratings WHERE booking_id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$existingRating = $stmt->get_result()->fetch_assoc();

$rating  = $existingRating["rating"]  ?? "";
$comment = $existingRating["comment"] ?? "";
$errors  = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $rating  = intval($_POST["rating"] ?? 0);
    $comment = trim($_POST["comment"] ?? "");

    if ($rating < 1 || $rating > 5) {
        $errors[] = "Rating must be between 1 and 5.";
    }

    if (!$errors) {
        if ($existingRating) {
            // update
            $stmt = $mysqli->prepare("
                UPDATE ratings
                SET rating = ?, comment = ?, created_at = NOW()
                WHERE booking_id = ?
            ");
            $stmt->bind_param("isi", $rating, $comment, $booking_id);
        } else {
            // insert
            $stmt = $mysqli->prepare("
                INSERT INTO ratings (booking_id, rating, comment)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $booking_id, $rating, $comment);
        }

        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = "Database error while saving rating.";
        }
    }
}
?>

<h1>Rate session: <?= htmlspecialchars($booking["session_title"]) ?></h1>

<?php if ($success): ?>
    <div class="alert alert-success">
        Thank you for your feedback!
        <a href="booking_manage.php" class="alert-link">Back to my bookings</a>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach;
