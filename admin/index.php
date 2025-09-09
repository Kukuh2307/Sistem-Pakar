<?php
session_start();
require_once __DIR__ . '/../config.php';

// Redirect berdasarkan status login
if (isset($_SESSION['admin_logged_in'])) {
    // Jika sudah login sebagai admin, redirect ke dashboard admin
    header('Location: ' . base_url('admin/view/dashboard.php'));
    exit();
} else {
    // Jika belum login sebagai admin, redirect ke login admin
    header('Location: ' . base_url('/view/login.php'));
    exit();
}
?>