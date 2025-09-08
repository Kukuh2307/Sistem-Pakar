<?php
session_start();

$host = 'localhost';
$dbname = 'dbwebsession';
$username = 'root';
$password = '';

// NOTE : Login admin
$admin_username = 'admin';
$admin_password = password_hash('sistempakar12345', PASSWORD_DEFAULT);


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fungsi Base Url
function base_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host     = $_SERVER['HTTP_HOST'];

    // Ambil folder project secara dinamis
    $script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $script_name = rtrim(str_replace('/admin/view', '', $script_name), '/');

    $url = $protocol . $host . $script_name;

    if ($path) {
        $url .= '/' . ltrim($path, '/');
    }

    return $url;
}
// Function untuk redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Function untuk cek login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function untuk cek jika user sudah login, redirect ke dashboard
function requireGuest() {
    if (isLoggedIn()) {
        redirect('dashboard.php');
    }
}

// Function untuk memerlukan user login
function requireAuth() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

// Fungction require admin login
function requireAdmin() {
    if (!isset($_SESSION['admin_logged_in'])) {
        redirect(base_url('admin/view/login.php'));
    }
}
?>