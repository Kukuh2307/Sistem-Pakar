<?php
session_start();

$host = 'localhost';
$dbname = 'dbwebsession';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
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
?>