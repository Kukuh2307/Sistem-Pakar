<?php
session_start();
require_once 'config.php';
requireAuth();

// Include Dompdf library
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'generate_pdf') {
    $diagnosa_id = $_POST['diagnosa_id'];
    
    // Ambil data diagnosa dari database
    $stmt = $pdo->prepare("SELECT h.*, u.nama_lengkap, u.username 
                          FROM history_diagnosa h 
                          JOIN users u ON h.user_id = u.id 
                          WHERE h.id = ? AND h.user_id = ?");
    $stmt->execute([$diagnosa_id, $_SESSION['user_id']]);
    $diagnosa = $stmt->fetch();
    
    if ($diagnosa) {
        $hasil_diagnosa = json_decode($diagnosa['hasil_diagnosa'], true);
        $gejala_terpilih = json_decode($diagnosa['gejala_terpilih'], true);
        
        // Konfigurasi Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        
        $dompdf = new Dompdf($options);
        
        // Buat konten HTML untuk PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Laporan Diagnosa Penggunaan HP Berlebihan</title>
            <style>
                body { font-family: Helvetica, Arial, sans-serif; line-height: 1.6; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .section { margin-bottom: 20px; }
                .section-title { background-color: #f0f0f0; padding: 8px; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .hasil-diagnosa { margin-top: 20px; }
                .tinggi { color: #28a745; }
                .sedang { color: #ffc107; }
                .rendah { color: #dc3545; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Laporan Diagnosa Penggunaan HP Berlebihan</h1>
                <p>Sistem Pakar dengan Metode Certainty Factor</p>
                <p>Tanggal: ' . date('d F Y H:i', strtotime($diagnosa['tanggal'])) . '</p>
            </div>
            
            <div class="section">
                <div class="section-title">Informasi Pengguna</div>
                <p><strong>Nama:</strong> ' . htmlspecialchars($diagnosa['nama_lengkap']) . '</p>
                <p><strong>Username:</strong> ' . htmlspecialchars($diagnosa['username']) . '</p>
            </div>
            
            <div class="section">
                <div class="section-title">Gejala yang Dipilih</div>
                <ol>';
        
        foreach ($gejala_terpilih as $gejala) {
            $html .= '<li>' . htmlspecialchars($gejala) . '</li>';
        }
        
        $html .= '
                </ol>
            </div>
            
            <div class="section hasil-diagnosa">
                <div class="section-title">Hasil Diagnosa</div>';
        
        foreach ($hasil_diagnosa as $index => $hasil) {
            $tingkat = ($hasil['cf'] >= 70) ? 'tinggi' : (($hasil['cf'] >= 40) ? 'sedang' : 'rendah');
            
            $html .= '
                <div style="margin-bottom: 20px; padding: 10px; border-left: 4px solid ' . (($index === 0) ? '#28a745' : '#6c757d') . '; background-color: #f8f9fa;">
                    <h3>' . ($index + 1) . '. ' . htmlspecialchars($hasil['penyakit']) . ' ' . (($index === 0) ? '<span style="color: #28a745;">(Diagnosa Utama)</span>' : '') . '</h3>
                    <p><strong>Tingkat Kepercayaan:</strong> <span class="' . $tingkat . '">' . $hasil['cf'] . '%</span></p>
                    <p><strong>Kategori:</strong> ' . (($hasil['cf'] >= 70) ? 'Tinggi' : (($hasil['cf'] >= 40) ? 'Sedang' : 'Rendah')) . '</p>
                    <p><strong>Jumlah Gejala Cocok:</strong> ' . $hasil['jumlah_gejala_cocok'] . ' dari ' . count($gejala_terpilih) . ' gejala</p>';
            
            if (!empty($hasil['solusi'])) {
                $html .= '
                    <div style="margin-top: 10px;">
                        <h4>Rekomendasi Penanganan:</h4>
                        <p>' . htmlspecialchars($hasil['solusi']) . '</p>
                    </div>';
            }
            
            $html .= '
                </div>';
        }
        
        $html .= '
            </div>
            
            <div class="footer">
                <p>Laporan ini dibuat secara otomatis oleh Sistem Pakar Diagnosa Penggunaan HP Berlebihan</p>
                <p>Hasil diagnosa bersifat prediktif dan disarankan untuk dikonsultasikan dengan guru BK atau konselor</p>
            </div>
        </body>
        </html>';
        
        // Render PDF
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Simpan PDF ke server (opsional)
        $output = $dompdf->output();
        $filename = 'diagnosa_report_' . $diagnosa_id . '_' . date('Ymd_His') . '.pdf';
        
        // Simpan informasi file ke database
        $updateStmt = $pdo->prepare("UPDATE history_diagnosa SET pdf_filename = ? WHERE id = ?");
        $updateStmt->execute([$filename, $diagnosa_id]);
        
        // Langsung download PDF
        $dompdf->stream($filename, array("Attachment" => true));
        exit;
    } else {
        $_SESSION['error'] = 'Data diagnosa tidak ditemukan.';
        header('Location: dashboard.php');
        exit;
    }
} else {
    header('Location: dashboard.php');
    exit;
}
?>