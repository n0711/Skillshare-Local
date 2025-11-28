<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_once "../includes/header.php";

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$name  = "";
$email = "";
$city  = "";
$role  = "learner";

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim($_POST["name"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $city     = trim($_POST["city"] ?? "");
    $role     = $_POST["role"] ?? "learner";
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm_password"] ?? "";

    // Server-side validation
    if ($name === "") {
        $errors[] = "Name is required.";
    }

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email address is required.";
    }

    if (!in_array($role, ["learner", "instructor"], true)) {
        $errors[] = "Invalid role selected.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if ($password !== $confirm) {
        $errors[] = "Password and confirmation do not match.";
    }

    // Email uniqueness check
    if (!$errors) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        if (!$stmt) {
            $errors[] = "Database error: " . $mysqli->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "An account with this email already exists.";
            }
            $stmt->close();
        }
    }

    // Create user
    if (!$errors) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $status        = "active";

        $stmt = $mysqli->prepare("
            INSERT INTO users (name, email, password_hash, role, city, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        if (!$stmt) {
            $errors[] = "Database error: " . $mysqli->error;
        } else {
            $stmt->bind_param("ssssss", $name, $email, $password_hash, $role, $city, $status);

            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                $stmt->close();

                // Fetch user (without password hash) into session
                $stmt = $mysqli->prepare("
                    SELECT id, name, email, role, city, status, created_at
                    FROM users
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $res  = $stmt->get_result();
                $user = $res->fetch_assoc();
                $stmt->close();

                $_SESSION["user"] = $user;

                header("Location: index.php");
                exit;
            } else {
                $errors[] = "Could not create account, please try again.";
            }
        }
    }
}
?>

<div class="row justify-content-center auth-wrapper">
  <div class="col-lg-6 col-xl-5">
    <div class="auth-card">
      <h1 class="auth-title mb-1">Create an account</h1>
      <p class="auth-subtitle mb-4">
        Join SkillShare Local as a learner or instructor and start sharing sustainable skills.
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

      <form method="post" id="registerForm" class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Full name</label>
          <input type="text"
                 name="name"
                 class="form-control"
                 required
                 value="<?= htmlspecialchars($name) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email"
                 name="email"
                 class="form-control"
                 required
                 autocomplete="email"
                 value="<?= htmlspecialchars($email) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">City (optional)</label>
          <input type="text"
                 name="city"
                 class="form-control"
                 value="<?= htmlspecialchars($city) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Register as</label>
          <select name="role" class="form-select">
            <option value="learner" <?= $role === "learner" ? "selected" : "" ?>>
              Learner
            </option>
            <option value="instructor" <?= $role === "instructor" ? "selected" : "" ?>>
              Instructor
            </option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Password</label>
          <input type="password"
                 name="password"
                 id="regPassword"
                 class="form-control"
                 required
                 minlength="8"
                 autocomplete="new-password">
          <div class="password-strength-bar">
            <div class="password-strength-bar-inner" id="passwordStrengthBarInner"></div>
          </div>
          <div class="form-text" id="passwordStrengthText">
            At least 8 characters.
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Confirm password</label>
          <input type="password"
                 name="confirm_password"
                 id="regPasswordConfirm"
                 class="form-control"
                 required
                 minlength="8"
                 autocomplete="new-password">
        </div>
        <div class="col-12 d-flex align-items-center gap-3 mt-2">
          <button class="btn btn-primary btn-auth">Create account</button>
          <span class="auth-meta">
            Already registered?
            <a href="login.php">Login instead</a>
          </span>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="/skillshare_local/assets/js/register.js"></script>
<?php require_once "../includes/footer.php"; ?>
