<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user = $_SESSION["user"] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SkillShare Local</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Google font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">

       <!-- Bootstrap & jQuery CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom styles -->
    <link rel="stylesheet" href="/skillshare_local/assets/css/style.css">

    <!-- Global JS -->
    <script src="/skillshare_local/assets/js/app.js"></script>

</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-light bg-white mb-3">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      SKILLSHARE LOCAL
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <!-- left / center nav links -->
      <ul class="navbar-nav ms-4 me-auto align-items-lg-center">
        <li class="nav-item">
          <a class="nav-link" href="sessions_browse.php">Find Sessions</a>
        </li>

        <?php if ($user && $user["role"] === "instructor"): ?>
          <li class="nav-item"><a class="nav-link" href="session_create.php">Create Session</a></li>
          <li class="nav-item"><a class="nav-link" href="my_sessions.php">My Sessions</a></li>
          <li class="nav-item"><a class="nav-link" href="booking_manage.php">Bookings</a></li>
        <?php elseif ($user && $user["role"] === "learner"): ?>
          <li class="nav-item"><a class="nav-link" href="booking_manage.php">My Bookings</a></li>
        <?php endif; ?>

        <?php if ($user && $user["role"] === "admin"): ?>
          <li class="nav-item"><a class="nav-link" href="impact_dashboard.php">Impact Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="admin_users.php">Users Admin</a></li>
        <?php endif; ?>
      </ul>

      <!-- right side: user greeting + logout, perfectly aligned -->
      <div class="d-flex align-items-center ms-auto gap-3 navbar-user">
        <?php if ($user): ?>
          <span class="navbar-text small text-muted">
            Hello, <strong><?= htmlspecialchars($user["name"]); ?></strong>
          </span>
          <a href="/skillshare_local/public/logout.php"
   class="btn btn-sm btn-outline-secondary nav-logout">
    Logout
</a>

        <?php else: ?>
          <a class="nav-link" href="login.php">Login</a>
          <a class="btn btn-sm btn-success" href="register.php">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<div class="container flex-grow-1 mb-5">

