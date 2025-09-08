<?php
session_start();
require_once __DIR__ . '/../../../config.php';
requireAdmin();

header('Content-Type: application/json');

try {
    // Ambil total siswa (kurangi 1 untuk admin)
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_siswa = $total_users - 1;
    
    // Ambil total laporan
    $total_laporan = $pdo->query("SELECT COUNT(*) FROM history_diagnosa")->fetchColumn();
    
    // Ambil statistik tambahan
    $laporan_hari_ini = $pdo->query(
        "SELECT COUNT(*) FROM history_diagnosa WHERE DATE(tanggal) = CURDATE()"
    )->fetchColumn();
    
    $laporan_bulan_ini = $pdo->query(
        "SELECT COUNT(*) FROM history_diagnosa WHERE MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())"
    )->fetchColumn();
    
    // Response JSON
    echo json_encode([
        'status' => 'success',
        'total_siswa' => $total_siswa,
        'total_laporan' => $total_laporan,
        'laporan_hari_ini' => $laporan_hari_ini,
        'laporan_bulan_ini' => $laporan_bulan_ini,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}