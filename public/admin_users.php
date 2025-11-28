<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_login();
require_once "../includes/header.php";

$user = current_user();

// only admin
if ($user["role"] !== "admin") {
    echo "<div class='alert alert-danger'>Access denied: admins only.</div>";
    require_once "../includes/footer.php";
    exit;
}

/* ========================
 * Handle status changes
 * ======================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $target_id = intval($_POST["user_id"] ?? 0);
    $action    = $_POST["action"] ?? "";

    // do not let admin suspend themself by accident
    if ($target_id > 0 && $target_id !== $user["id"]) {
        if ($action === "suspend") {
            $newStatus = "suspended";
        } elseif ($action === "activate") {
            $newStatus = "active";
        } else {
            $newStatus = null;
        }

        if ($newStatus !== null) {
            $stmt = $mysqli->prepare("
                UPDATE users
                SET status = ?
                WHERE id = ?
            ");
            $stmt->bind_param("si", $newStatus, $target_id);
            $stmt->execute();
        }
    }
}

/* ========================
 * Load all users
 * ======================== */

$result = $mysqli->query("
    SELECT id, name, email, role, city, status, created_at
    FROM users
    ORDER BY created_at DESC
");
?>

<h1>Users administration</h1>

<p class="text-muted">
  View all accounts in the community and suspend/activate them in case of problematic activity.
</p>

<?php if ($result->num_rows === 0): ?>
    <div class="alert alert-info">No users found.</div>
<?php else: ?>
    <div class="table-responsive">
    <table class="table table-striped table-sm align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>City</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php while ($u = $result->fetch_assoc()): ?>
        <?php
          $badgeClass = $u["status"] === "active" ? "bg-success" : "bg-danger";
          $isSelf     = ($u["id"] == $user["id"]);
        ?>
        <tr>
          <td><?= (int)$u["id"] ?></td>
          <td><?= htmlspecialchars($u["name"]) ?></td>
          <td><?= htmlspecialchars($u["email"]) ?></td>
          <td><?= htmlspecialchars($u["role"]) ?></td>
          <td><?= htmlspecialchars($u["city"]) ?></td>
          <td>
            <span class="badge <?= $badgeClass ?>">
              <?= htmlspecialchars($u["status"]) ?>
            </span>
          </td>
          <td><?= htmlspecialchars($u["created_at"]) ?></td>
          <td>
            <?php if ($isSelf): ?>
              <span class="text-muted small">This is you</span>
            <?php else: ?>
              <?php if ($u["status"] === "active"): ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="user_id" value="<?= $u["id"] ?>">
                  <button name="action" value="suspend"
                          class="btn btn-sm btn-outline-danger">
                    Suspend
                  </button>
                </form>
              <?php else: ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="user_id" value="<?= $u["id"] ?>">
                  <button name="action" value="activate"
                          class="btn btn-sm btn-outline-success">
                    Activate
                  </button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
    </div>
<?php endif; ?>

<p class="small text-muted mt-3">
  Note: Suspended users will not be able to log in (their status is checked during authentication).
</p>

<?php require_once "../includes/footer.php"; ?>
