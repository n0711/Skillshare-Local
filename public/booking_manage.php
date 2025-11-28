<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_login();
require_once "../includes/header.php";

$user = current_user();
$role = $user["role"] ?? null;

if (!in_array($role, ["instructor", "learner"], true)) {
    echo "<div class='alert alert-info'>Only instructors or learners use this page. Admins should use the impact dashboard.</div>";
    require_once "../includes/footer.php";
    exit;
}

/* ========================
 *   Handle instructor actions
 * ======================== */
if ($role === "instructor" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $booking_id = intval($_POST["booking_id"] ?? 0);
    $action     = $_POST["action"] ?? "";

    if ($booking_id > 0 && in_array($action, ["accept", "decline", "toggle_attended"], true)) {

        if ($action === "toggle_attended") {
            // flip attended flag but only for this instructor's sessions
            $stmt = $mysqli->prepare("
                UPDATE bookings b
                JOIN sessions s ON b.session_id = s.id
                SET b.attended = 1 - b.attended
                WHERE b.id = ? AND s.instructor_id = ?
            ");
            $stmt->bind_param("ii", $booking_id, $user["id"]);
            $stmt->execute();
        } else {
            $newStatus = ($action === "accept") ? "accepted" : "declined";
            $stmt = $mysqli->prepare("
                UPDATE bookings b
                JOIN sessions s ON b.session_id = s.id
                SET b.status = ?
                WHERE b.id = ? AND s.instructor_id = ?
            ");
            $stmt->bind_param("sii", $newStatus, $booking_id, $user["id"]);
            $stmt->execute();
        }
    }
}

/* ========================
 *   Data for views
 * ======================== */

if ($role === "instructor") {
    // bookings for sessions taught by this instructor
    $stmt = $mysqli->prepare("
        SELECT 
            b.id,
            b.status,
            b.attended,
            b.booked_at,
            u.name AS learner_name,
            s.title AS session_title,
            s.start_datetime
        FROM bookings b
        JOIN sessions s ON b.session_id = s.id
        JOIN users u ON b.learner_id = u.id
        WHERE s.instructor_id = ?
        ORDER BY s.start_datetime DESC, b.booked_at DESC
    ");
    $stmt->bind_param("i", $user["id"]);
    $stmt->execute();
    $instructorBookings = $stmt->get_result();
} else {
    // learner's own bookings + rating (if exists)
    $stmt = $mysqli->prepare("
        SELECT 
            b.id,
            b.status,
            b.attended,
            b.booked_at,
            s.title AS session_title,
            s.start_datetime,
            r.rating
        FROM bookings b
        JOIN sessions s ON b.session_id = s.id
        LEFT JOIN ratings r ON r.booking_id = b.id
        WHERE b.learner_id = ?
        ORDER BY s.start_datetime DESC, b.booked_at DESC
    ");
    $stmt->bind_param("i", $user["id"]);
    $stmt->execute();
    $learnerBookings = $stmt->get_result();
}
?>

<h1>My bookings</h1>

<?php if ($role === "instructor"): ?>

    <p class="text-muted">Bookings for your sessions. You can accept / decline requests and mark attendance.</p>

    <?php if ($instructorBookings->num_rows === 0): ?>
        <div class="alert alert-info">No bookings yet.</div>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Session</th>
              <th>Learner</th>
              <th>Start</th>
              <th>Status</th>
              <th>Attended</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php while ($b = $instructorBookings->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($b["session_title"]) ?></td>
              <td><?= htmlspecialchars($b["learner_name"]) ?></td>
              <td><?= date("d M Y H:i", strtotime($b["start_datetime"])) ?></td>
              <td>
                <span class="badge 
                  <?= $b["status"] === "accepted" ? "bg-success" : 
                      ($b["status"] === "declined" ? "bg-danger" :
                      ($b["status"] === "cancelled" ? "bg-secondary" : "bg-warning text-dark")) ?>">
                  <?= htmlspecialchars($b["status"]) ?>
                </span>
              </td>
              <td>
                <?php if ($b["attended"]): ?>
                    <span class="badge bg-success">Yes</span>
                <?php else: ?>
                    <span class="badge bg-secondary">No</span>
                <?php endif; ?>
              </td>
              <td>
                <form method="post" class="d-inline">
                  <input type="hidden" name="booking_id" value="<?= $b["id"] ?>">
                  <button name="action" value="accept" class="btn btn-sm btn-outline-success">Accept</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="booking_id" value="<?= $b["id"] ?>">
                  <button name="action" value="decline" class="btn btn-sm btn-outline-danger">Decline</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="booking_id" value="<?= $b["id"] ?>">
                  <button name="action" value="toggle_attended" class="btn btn-sm btn-outline-secondary">
                    Toggle attended
                  </button>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        </div>
    <?php endif; ?>

<?php else: ?>

    <p class="text-muted">Your booking requests, attendance and ratings.</p>

    <?php if ($learnerBookings->num_rows === 0): ?>
        <div class="alert alert-info">You have not booked any sessions yet.</div>
    <?php else: ?>
        <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Session</th>
              <th>Start</th>
              <th>Status</th>
              <th>Attended</th>
              <th>Rating</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php while ($b = $learnerBookings->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($b["session_title"]) ?></td>
              <td><?= date("d M Y H:i", strtotime($b["start_datetime"])) ?></td>
              <td>
                <span class="badge 
                  <?= $b["status"] === "accepted" ? "bg-success" : 
                      ($b["status"] === "declined" ? "bg-danger" :
                      ($b["status"] === "cancelled" ? "bg-secondary" : "bg-warning text-dark")) ?>">
                  <?= htmlspecialchars($b["status"]) ?>
                </span>
              </td>
              <td>
                <?php if ($b["attended"]): ?>
                    <span class="badge bg-success">Yes</span>
                <?php else: ?>
                    <span class="badge bg-secondary">No</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($b["rating"] !== null): ?>
                    <?= (int)$b["rating"] ?>/5
                <?php elseif ($b["attended"]): ?>
                    <span class="text-muted">Not rated</span>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($b["attended"] && $b["rating"] === null): ?>
                    <a href="rating_form.php?booking_id=<?= $b["id"] ?>" class="btn btn-sm btn-outline-primary">
                      Rate
                    </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php require_once "../includes/footer.php"; ?>
