<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/db.php";

function current_user(): ?array {
    return $_SESSION["user"] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION["user"]);
}

function require_login(): void {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

function is_role(string $role): bool {
    $u = current_user();
    return $u && isset($u["role"]) && $u["role"] === $role;
}
