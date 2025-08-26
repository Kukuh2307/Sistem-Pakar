<?php
require_once 'config.php';

// Jika user sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
?>