<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_rider(): ?array {
    return $_SESSION['rider'] ?? null;
}

function rider_logged_in(): bool {
    return current_rider() !== null;
}

function require_rider_login(): void {
    if (!rider_logged_in()) {
        redirect('/delivery_rider/login.php');
    }
}
