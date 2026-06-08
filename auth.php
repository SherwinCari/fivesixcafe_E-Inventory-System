<?php
// auth.php  — include this at the TOP of every protected page

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Not logged in at all → back to login
if (empty($_SESSION['logged_in'])) {
    header('Location: Login.php');
    exit();
}

// Helper so any page can call:  requireRole('admin');
function requireRole(string $required): void {
    if ($_SESSION['role'] !== $required) {
        // Staff trying to reach an admin page → redirect to their home
        header('Location: Cashier.php');
        exit();
    }
}