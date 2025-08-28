<?php
session_start();
require_once 'config.php';
requireAuth();

if (!isset($_GET['file']) || empty($_GET['file'])) {
    die('File parameter missing');
}

$filename = basename($_GET['file']); // Security: prevent directory traversal
$pdf_dir = 'pdf_reports';
$filepath = $pdf_dir . '/' . $filename;

// Verify file belongs to current user
$stmt = $pdo->prepare("SELECT * FROM history_diagnosa WHERE user_id = ? AND pdf_filename = ?");
$stmt->execute([$_SESSION['user_id'], $filename]);
$record = $stmt->fetch();

if (!$record) {
    die('File not found or access denied');
}

if (!file_exists($filepath)) {
    die('PDF file not found on server');
}

// Set headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file
readfile($filepath);
exit();
?>