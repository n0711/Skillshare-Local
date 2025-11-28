<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_once "../includes/header.php";

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$email  = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if ($password === "") {
        $errors[] = "Password is required.";
    }

    if (!$errors) {
        $stmt = $mysqli->prepare("
            SELECT id, name, email, password_hash, role, city, status, created_at
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        if (!$stmt) {
            die("Prepare failed: " . $mysqli->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res  = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $errors[] = "Invalid email or password.";
        } else {
            if ($user["status"] !== "active") {
                $errors[] = "Account is not active. Please contact the administrator.";
            } elseif (!password_verify($password, $user["password_hash"])) {
                $errors[] = "Invalid email or password.";
            } else {
                unset($user["password_hash"]);
                $_SESSION["user"] = $user;
                header("Location: index.php");
                exit;
            }
        }
    }
}
?>

<div class="row justify-content-center auth-wrapper">
  <div class="col-lg-6 col-xl-5">
    <div class="auth-card">
      <h1 class="auth-title mb-1">Login</h1>
      <p class="auth-subtitle mb-4">
        Welcome back. Sign in to manage your sustainable sessions and bookings.
      </p>

      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" class="row g-3">
        <div class="col-12">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required
                 autocomplete="email"
                 value="<?= htmlspecialchars($email) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required
                 autocomplete="current-password">
        </div>
        <div class="col-12 d-flex align-items-center gap-3 mt-2">
          <button class="btn btn-primary btn-auth">Login</button>
          <span class="auth-meta">
            New here?
            <a href="register.php">Create an account</a>
          </span>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once "../includes/footer.php"; ?>
