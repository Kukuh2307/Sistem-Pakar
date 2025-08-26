<?php
require_once 'config.php';

// Hapus semua session
session_unset();
session_destroy();

// Redirect ke halaman login
redirect('login.php');
?>