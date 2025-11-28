<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_login();
if (!is_role("instructor")) {
    die("Access denied: instructors only.");
}
require_once "../includes/header.php";

$user = current_user();
$instructor_id = $user["id"];

// read id from GET or POST
$id = intval($_GET["id"] ?? $_POST["id"] ?? 0);
if ($id <= 0) {
    echo "<div class='alert alert-danger'>Invalid session ID.</div>";
    require_once "../includes/footer.php";
    exit;
}

// load session, make sure it belongs to this instructor
$stmt = $mysqli->prepare("SELECT * FROM sessions WHERE id = ? AND instructor_id = ?");
$stmt->bind_param("ii", $id, $instructor_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();

if (!$session) {
    echo "<div class='alert alert-danger'>Session not found or you are not the owner.</div>";
    require_once "../includes/footer.php";
    exit;
}

// load categories for select box
$categories = $mysqli->query("SELECT id, name FROM categories ORDER BY name");

$errors  = [];
$success = false;

// Helper to keep form values after POST
function fval($name, $session) {
    return $_POST[$name] ?? $session[$name] ?? "";
}

// current photo path
$current_photo = $session["photo_path"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title       = trim(fval("title", $session));
    $category_id = intval(fval("category_id", $session));
    $description = trim(fval("description", $session));
    $duration    = intval(fval("duration_minutes", $session));
    $fee_type    = fval("fee_type", $session) ?: "free";
    $fee_amount  = floatval(fval("fee_amount", $session));
    $mode        = fval("mode", $session) ?: "online";
    $city        = trim(fval("location_city", $session));
    $address     = trim(fval("location_address", $session));
    $online_link = trim(fval("online_link", $session));
    $capacity    = intval(fval("capacity", $session));
    $start_raw   = fval("start_datetime", $session);

    // convert datetime-local to MySQL format
    $start_datetime = null;
    if ($start_raw !== "") {
        $ts = strtotime($start_raw);
        if ($ts !== false) {
            $start_datetime = date("Y-m-d H:i:s", $ts);
        }
    }

    // validation
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
        $city = $address = "";
    } else {
        if ($city === "" || $address === "") {
            $errors[] = "City and address are required for in-person sessions.";
        }
        $online_link = "";
    }

    // handle new photo (optional)
    $photo_path = $current_photo;
    if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES["photo"];

        if ($file["error"] === UPLOAD_ERR_OK) {
            if ($file["size"] > 5 * 1024 * 1024) {
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
                    $filename = "session_" . $instructor_id . "_" . time() . "." . $ext;
                    $target   = $uploadDir . $filename;

                    if (move_uploaded_file($file["tmp_name"], $target)) {
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
        $stmt = $mysqli->prepare("
            UPDATE sessions
            SET
              title = ?,
              category_id = ?,
              description = ?,
              duration_minutes = ?,
              fee_type = ?,
              fee_amount = ?,
              mode = ?,
              location_city = ?,
              location_address = ?,
              online_link = ?,
              photo_path = ?,
              capacity = ?,
              start_datetime = ?
            WHERE id = ? AND instructor_id = ?
        ");

        if (!$stmt) {
            die('Prepare failed: ' . $mysqli->error);
        }

        $stmt->bind_param(
            "ssssssssssssss",
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
            $start_datetime,
            $id,
            $instructor_id
        );

        if ($stmt->execute()) {
            $success  = true;
            // refresh $session
            $session["title"]            = $title;
            $session["category_id"]      = $category_id;
            $session["description"]      = $description;
            $session["duration_minutes"] = $duration;
            $session["fee_type"]         = $fee_type;
            $session["fee_amount"]       = $fee_amount;
            $session["mode"]             = $mode;
            $session["location_city"]    = $city;
            $session["location_address"] = $address;
            $session["online_link"]      = $online_link;
            $session["photo_path"]       = $photo_path;
            $session["capacity"]         = $capacity;
            $session["start_datetime"]   = $start_datetime;
            $current_photo               = $photo_path;
        } else {
            $errors[] = "Database error updating session.";
        }
    }
}
?>

<h1>Edit session</h1>

<?php if ($success): ?>
  <div class="alert alert-success">
    Session updated successfully.
    <a href="my_sessions.php" class="alert-link">Back to My Sessions</a>
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
  <input type="hidden" name="id" value="<?= $id ?>">

  <div class="col-md-8">
    <label class="form-label">Title</label>
    <input type="text" name="title" class="form-control"
           value="<?= htmlspecialchars(fval("title", $session)) ?>" required>
  </div>

  <div class="col-md-4">
    <label class="form-label">Category</label>
    <select name="category_id" class="form-select" required>
      <option value="">-- choose --</option>
      <?php while ($cat = $categories->fetch_assoc()): ?>
        <option value="<?= $cat["id"] ?>"
          <?= (intval(fval("category_id", $session)) === intval($cat["id"]) ? "selected" : "") ?>>
          <?= htmlspecialchars($cat["name"]) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </div>

  <div class="col-12">
    <label class="form-label">Description &amp; sustainability impact</label>
    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars(fval("description", $session)) ?></textarea>
  </div>

  <div class="col-md-4">
    <label class="form-label">Duration (minutes)</label>
    <input type="number" name="duration_minutes" class="form-control" min="10"
           value="<?= htmlspecialchars(fval("duration_minutes", $session)) ?>">
  </div>

  <div class="col-md-4">
    <label class="form-label">Capacity</label>
    <input type="number" name="capacity" class="form-control" min="1"
           value="<?= htmlspecialchars(fval("capacity", $session)) ?>">
  </div>

  <div class="col-md-4">
    <label class="form-label">Start date &amp; time</label>
    <?php
      $dt_value = fval("start_datetime", $session);
      if ($dt_value && strpos($dt_value, "T") === false) {
          $dt_value = date("Y-m-d\TH:i", strtotime($dt_value));
      }
    ?>
    <input type="datetime-local" name="start_datetime" class="form-control"
           value="<?= htmlspecialchars($dt_value) ?>" required>
  </div>

  <div class="col-md-4">
    <label class="form-label">Fee type</label>
    <?php $feeTypeVal = fval("fee_type", $session) ?: "free"; ?>
    <select name="fee_type" class="form-select" id="feeType">
      <option value="free"  <?= $feeTypeVal === "free"  ? "selected" : "" ?>>Free</option>
      <option value="paid"  <?= $feeTypeVal === "paid"  ? "selected" : "" ?>>Paid</option>
    </select>
  </div>

  <div class="col-md-4">
    <label class="form-label">Fee amount (€)</label>
    <input type="number" step="0.01" name="fee_amount" id="feeAmount" class="form-control"
           value="<?= htmlspecialchars(fval("fee_amount", $session)) ?>">
  </div>

  <div class="col-md-4">
    <label class="form-label">Mode</label>
    <?php $modeVal = fval("mode", $session) ?: "online"; ?>
    <select name="mode" class="form-select" id="modeSelect">
      <option value="online"    <?= $modeVal === "online"    ? "selected" : "" ?>>Online</option>
      <option value="in-person" <?= $modeVal === "in-person" ? "selected" : "" ?>>In-person</option>
    </select>
  </div>

  <div class="col-md-6 mode-inperson">
    <label class="form-label">City (for in-person)</label>
    <input type="text" name="location_city" class="form-control"
           value="<?= htmlspecialchars(fval("location_city", $session)) ?>">
  </div>

  <div class="col-md-6 mode-inperson">
    <label class="form-label">Address (for in-person)</label>
    <input type="text" name="location_address" class="form-control"
           value="<?= htmlspecialchars(fval("location_address", $session)) ?>">
  </div>

  <div class="col-12 mode-online">
    <label class="form-label">Online link (Zoom/Meet etc.)</label>
    <input type="text" name="online_link" class="form-control"
           value="<?= htmlspecialchars(fval("online_link", $session)) ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">Current photo</label><br>
    <?php if ($current_photo): ?>
      <img src="/skillshare_local/<?= htmlspecialchars($current_photo) ?>" alt="Session photo"
           style="max-width: 180px; max-height: 120px; object-fit: cover;" class="img-thumbnail">
    <?php else: ?>
      <span class="text-muted">No photo uploaded.</span>
    <?php endif; ?>
  </div>

  <div class="col-md-6">
    <label class="form-label">Upload new photo (optional)</label>
    <input type="file" name="photo" class="form-control" accept="image/*">
  </div>

  <div class="col-12">
    <button class="btn btn-primary">Save changes</button>
    <a href="my_sessions.php" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>

<script>
// same JS toggles as create page
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
