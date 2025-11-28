<?php
require_once "../config/db.php";
require_once "../includes/auth.php";

// only logged-in instructors
require_login();
if (!is_role("instructor")) {
    die("Access denied: instructors only.");
}

require_once "../includes/header.php";

$errors = [];
$success = false;

// get categories for the dropdown
$categories = $mysqli->query("SELECT id, name FROM categories ORDER BY name");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title       = trim($_POST["title"] ?? "");
    $category_id = intval($_POST["category_id"] ?? 0);
    $description = trim($_POST["description"] ?? ""); // include impact text here
    $duration    = intval($_POST["duration_minutes"] ?? 0);
    $fee_type    = $_POST["fee_type"] ?? "free";         // free / paid
    $fee_amount  = floatval($_POST["fee_amount"] ?? 0);
    $mode        = $_POST["mode"] ?? "online";           // online / in-person
    $city        = trim($_POST["location_city"] ?? "");
    $address     = trim($_POST["location_address"] ?? "");
    $online_link = trim($_POST["online_link"] ?? "");
    $capacity    = intval($_POST["capacity"] ?? 0);
    $start_raw   = $_POST["start_datetime"] ?? "";
    $photo_path  = ""; // relative path like "uploads/xyz.jpg"

    // convert HTML datetime-local value to MySQL DATETIME
    $start_datetime = null;
    if ($start_raw !== "") {
        $ts = strtotime($start_raw);
        if ($ts !== false) {
            $start_datetime = date("Y-m-d H:i:s", $ts);
        }
    }

    // basic validation
    if ($title === "") $errors[] = "Title is required.";
    if ($category_id <= 0) $errors[] = "Category is required.";
    if ($duration <= 0) $errors[] = "Duration must be greater than 0 minutes.";
    if ($capacity <= 0) $errors[] = "Capacity must be greater than 0.";
    if (!$start_datetime) $errors[] = "Valid date and time are required.";

    if (!in_array($fee_type, ["free","paid"], true)) {
        $errors[] = "Invalid fee type.";
    }
    if ($fee_type === "free") {
        $fee_amount = 0;
    }

    if (!in_array($mode, ["online","in-person"], true)) {
        $errors[] = "Invalid mode.";
    }
    if ($mode === "online") {
        if ($online_link === "") $errors[] = "Online link is required for online sessions.";
        $city = $address = ""; // clear
    } else {
        if ($city === "" || $address === "") {
            $errors[] = "City and address are required for in-person sessions.";
        }
        $online_link = "";
    }

    // handle photo upload (optional)
    if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES["photo"];

        if ($file["error"] === UPLOAD_ERR_OK) {
            if ($file["size"] > 5 * 1024 * 1024) { // 5MB
                $errors[] = "Photo is too large (max 5MB).";
            } else {
                $info = getimagesize($file["tmp_name"]);
                if ($info === false) {
                    $errors[] = "Uploaded file is not a valid image.";
                } else {
                    $uploadDir = realpath(__DIR__ . "/..") . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR;
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }
                    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
                    if ($ext === "") {
                        $ext = "jpg";
                    }
                    $instructor_id = current_user()["id"];
                    $filename = "session_" . $instructor_id . "_" . time() . "." . $ext;
                    $target   = $uploadDir . $filename;

                    if (move_uploaded_file($file["tmp_name"], $target)) {
                        // store relative to project root
                        $photo_path = "uploads/" . $filename;
                    } else {
                        $errors[] = "Failed to save uploaded photo.";
                    }
                }
            }
        } else {
            $errors[] = "Error uploading photo.";
        }
    }

    if (!$errors) {
        $instructor_id = current_user()["id"];

        $stmt = $mysqli->prepare("
            INSERT INTO sessions
            (instructor_id, title, category_id, description,
             duration_minutes, fee_type, fee_amount,
             mode, location_city, location_address, online_link,
             photo_path, capacity, start_datetime, status)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");

        if (!$stmt) {
            die('Prepare failed: ' . $mysqli->error);
        }

        // everything bound as string; MySQL will cast
        $stmt->bind_param(
            "ssssssssssssss",
            $instructor_id,
            $title,
            $category_id,
            $description,
            $duration,
            $fee_type,
            $fee_amount,
            $mode,
            $city,
            $address,
            $online_link,
            $photo_path,
            $capacity,
            $start_datetime
        );

        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = "Database error creating session.";
        }
    }
}
?>

<h1>Create a new session</h1>

<?php if ($success): ?>
    <div class="alert alert-success">
        Session created successfully.
        <a href="sessions_browse.php" class="alert-link">View all sessions</a>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="row g-3">
  <div class="col-md-8">
    <label class="form-label">Title</label>
    <input type="text" name="title" class="form-control" required>
  </div>

  <div class="col-md-4">
    <label class="form-label">Category</label>
    <select name="category_id" class="form-select" required>
      <option value="">-- choose --</option>
      <?php while ($cat = $categories->fetch_assoc()): ?>
        <option value="<?= $cat["id"] ?>">
          <?= htmlspecialchars($cat["name"]) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </div>

  <div class="col-12">
    <label class="form-label">Description &amp; sustainability impact</label>
    <textarea name="description" class="form-control" rows="3"
              placeholder="Describe what you teach and how it helps the environment (e.g. reduces food waste, saves energy, etc.)"></textarea>
  </div>

  <div class="col-md-4">
    <label class="form-label">Duration (minutes)</label>
    <input type="number" name="duration_minutes" class="form-control" min="10" value="60">
  </div>

  <div class="col-md-4">
    <label class="form-label">Capacity</label>
    <input type="number" name="capacity" class="form-control" min="1" value="10">
  </div>

  <div class="col-md-4">
    <label class="form-label">Start date &amp; time</label>
    <input type="datetime-local" name="start_datetime" class="form-control" required>
  </div>

  <div class="col-md-4">
    <label class="form-label">Fee type</label>
    <select name="fee_type" class="form-select" id="feeType">
      <option value="free">Free</option>
      <option value="paid">Paid</option>
    </select>
  </div>

  <div class="col-md-4">
    <label class="form-label">Fee amount (€)</label>
    <input type="number" step="0.01" name="fee_amount" id="feeAmount" class="form-control" value="0">
  </div>

  <div class="col-md-4">
    <label class="form-label">Mode</label>
    <select name="mode" class="form-select" id="modeSelect">
      <option value="online">Online</option>
      <option value="in-person">In-person</option>
    </select>
  </div>

  <div class="col-md-6 mode-inperson">
    <label class="form-label">City (for in-person)</label>
    <input type="text" name="location_city" class="form-control">
  </div>

  <div class="col-md-6 mode-inperson">
    <label class="form-label">Address (for in-person)</label>
    <input type="text" name="location_address" class="form-control">
  </div>

  <div class="col-12 mode-online">
    <label class="form-label">Online link (Zoom/Meet etc.)</label>
    <input type="text" name="online_link" class="form-control">
  </div>

  <div class="col-12">
    <label class="form-label">Session photo (optional)</label>
    <input type="file" name="photo" class="form-control" accept="image/*">
  </div>

  <div class="col-12">
    <button class="btn btn-primary">Create session</button>
  </div>
</form>

<script>
// toggle fee amount + mode fields with jQuery
$(function(){
    function updateFee() {
        const type = $("#feeType").val();
        if (type === "free") {
            $("#feeAmount").prop("disabled", true).val("0");
        } else {
            $("#feeAmount").prop("disabled", false);
        }
    }
    function updateMode() {
        const m = $("#modeSelect").val();
        if (m === "online") {
            $(".mode-online").show();
            $(".mode-inperson").hide();
        } else {
            $(".mode-online").hide();
            $(".mode-inperson").show();
        }
    }

    $("#feeType").on("change", updateFee);
    $("#modeSelect").on("change", updateMode);

    updateFee();
    updateMode();
});
</script>

<?php require_once "../includes/footer.php"; ?>
