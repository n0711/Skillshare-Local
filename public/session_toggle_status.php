<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_login();
if (!is_role("instructor")) {
    die("Access denied: instructors only.");
}

$instructor_id = current_user()["id"];
$id = intval($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: my_sessions.php");
    exit;
}

// Load session to ensure ownership
$stmt = $mysqli->prepare("
    SELECT id, instructor_id, status
    FROM sessions
    WHERE id = ? AND instructor_id = ?
");
$stmt->bind_param("ii", $id, $instructor_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) {
    header("Location: my_sessions.php");
    exit;
}

// Toggle status between active / inactive
$new_status = ($session["status"] === "inactive") ? "active" : "inactive";

$stmt = $mysqli->prepare("
    UPDATE sessions
    SET status = ?
    WHERE id = ? AND instructor_id = ?
");
$stmt->bind_param("sii", $new_status, $id, $instructor_id);
$stmt->execute();
$stmt->close();

header("Location: my_sessions.php");
exit;
