<?php
session_start();
require_once 'config.php';
// requireAuth();

require_once 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

// Cek request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses tidak diizinkan.");
}
if (!isset($_POST['id'])) {
    die("ID history tidak ditemukan.");
}

$history_id = intval($_POST['id']);

// --- Ambil data dari database ---
$stmt = $pdo->prepare("SELECT * FROM history_diagnosa WHERE id = ?");
$stmt->execute([$history_id]);
$history = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$history) {
    die("Data tidak ditemukan.");
}

// Decode JSON gejala & hasil
$gejala_terpilih = json_decode($history['gejala_terpilih'], true);
$hasil_diagnosa  = json_decode($history['hasil_diagnosa'], true);

// Data siswa
$user_data = [
    'id'            => $history['id'],
    'nik'           => $history['nik'],
    'nama_lengkap'  => $history['nama_lengkap'],
    'tanggal_lahir' => $history['tanggal_lahir'],
    'jenis_kelamin' => $history['jenis_kelamin'],
    'status'        => $history['status'],
    'alamat'        => $history['alamat'],
];
if($user_data['jenis_kelamin'] == 'L') {
    $user_data['jenis_kelamin'] = 'Laki-laki';
} elseif($user_data['jenis_kelamin'] == 'P') {
    $user_data['jenis_kelamin'] = 'Perempuan';
}

// --- Fungsi bantu format tanggal ---
function getRomanMonth($month) {
    $romans = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
    return $romans[$month-1];
}
function formatTanggalIndonesia($tanggal) {
    $nama_bulan = [
        1=>'Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'
    ];
    $t = strtotime($tanggal);
    return date('d',$t) . ' ' . $nama_bulan[date('n',$t)] . ' ' . date('Y',$t);
}

// Nomor surat otomatis
$nomor_surat = sprintf("%03d/BK-UNP/%s/%d", $user_data['id'], getRomanMonth(date('n')), date('Y'));

// Ambil rekomendasi
$rekomendasi = [];
foreach ($hasil_diagnosa as $hasil) {
    if (!empty($hasil['solusi'])) {
        if (is_array($hasil['solusi'])) {
            $rekomendasi = array_merge($rekomendasi, $hasil['solusi']);
        } else {
            $rekomendasi[] = $hasil['solusi'];
        }
    }
}
if (empty($rekomendasi)) {
    $rekomendasi[] = 'Tidak ada rekomendasi spesifik.';
}

// Fungsi gambar ke Base64
function imageToBase64($path) {
    if (!file_exists($path)) return '';
    $type = pathinfo($path, PATHINFO_EXTENSION);
    return 'data:image/'.$type.';base64,'.base64_encode(file_get_contents($path));
}
$kop_surat_base64    = imageToBase64('img/kop_surat.png');
$tanda_tangan_base64 = imageToBase64('img/tanda_tangan.png');

// Tanggal lahir & surat
$tanggal_lahir_indonesia = formatTanggalIndonesia($user_data['tanggal_lahir']);
$tanggal_surat_indonesia = formatTanggalIndonesia(date('Y-m-d'));


$footer_logo_base64 = imageToBase64('img/logo.jpeg'); 

// --- Konten HTML ---
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Assesment</title>
    <link rel="shortcut icon" type="image/x-icon" href="img/unp.jpeg">
    <style>
        @page { margin: 0.5cm 1.5cm; }
        body { font-family: Times, "Times New Roman", serif; font-size: 12pt; line-height: 1.5; }
        td { line-height: 0.8; }
        .kop-surat { text-align: center; margin-bottom: 20px; }
        .kop-surat img { width: 125%; }
        .konten { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        .table-info td { padding: 2px 0; vertical-align: top; line-height: 1.2; }
        .table-info td:first-child { width: 120px; }
        .table-info td:nth-child(2) { width: 15px; }
        .section-title { margin-top: 15px; margin-bottom: 5px; }
        .list { padding-left: 20px; margin: 0; }
        .tanda-tangan { margin-top: 20px; width: 300px; margin-left: auto; text-align: left; z-index: 1; position: relative; }
        .tanda-tangan-img { margin-top: -30px; width: 200px; height: 80px; }
        /* Footer */
        .footer-logo { 
            position: fixed; 
            bottom: 5px; 
            left: 0; 
            right: 0; 
            text-align: center; 
        }
        .footer-logo img { 
            width: 150px; 
            opacity: 0.8; 
        }
    </style>
</head>
<body>
    <!-- Footer Logo -->
    <div class="footer-logo">
        ' . ($footer_logo_base64 ? '<img src="' . $footer_logo_base64 . '" alt="Footer Logo">' : '') . '
    </div>
    <div class="kop-surat" style="margin-top:-10px;">
        ' . ($kop_surat_base64 ? '<img src="' . $kop_surat_base64 . '" alt="Kop Surat">' : '<h1>UNIVERSITAS NUSANTARA PGRI KEDIRI</h1><p>FAKULTAS KEGURUAN DAN ILMU PENDIDIKAN</p>') . '
    </div>

    <div class="konten">
        <table class="table-info" style="width: 50%;">
            <tr><td>Nomor</td><td>:</td><td>' . htmlspecialchars($nomor_surat) . '</td></tr>
            <tr><td>Hal</td><td>:</td><td>Surat Keterangan Hasil Assesment</td></tr>
            <tr><td>Lampiran</td><td>:</td><td>-</td></tr>
        </table>
        
        <p>Yang bertanda tangan di bawah ini:</p>
        <table class="table-info" style="margin-left: 20px;">
            <tr><td>Nama</td><td>:</td><td><strong>Dr. Vivi Ratnawati, S.Pd., M. Psi.</strong></td></tr>
            <tr><td>NIDN</td><td>:</td><td>0728038306</td></tr>
            <tr><td>Jabatan</td><td>:</td><td>Dosen Psikolog</td></tr>
            <tr><td>Instansi</td><td>:</td><td>Universitas Nusantara PGRI Kediri</td></tr>
        </table>

        <p>Dengan ini menyatakan bahwa:</p>
        <table class="table-info" style="margin-left: 20px;">
            <tr><td>Nama Anak</td><td>:</td><td><strong>' . htmlspecialchars($user_data['nama_lengkap']) . '</strong></td></tr>
            <tr><td>NIK</td><td>:</td><td>' . htmlspecialchars($user_data['nik']) . '</td></tr>
            <tr><td>Tanggal Lahir</td><td>:</td><td>' . $tanggal_lahir_indonesia . '</td></tr>
            <tr><td>Jenis Kelamin</td><td>:</td><td>' . htmlspecialchars($user_data['jenis_kelamin']) . '</td></tr>
            <tr><td>Status</td><td>:</td><td>' . htmlspecialchars($user_data['status']) . '</td></tr>
            <tr><td>Alamat</td><td>:</td><td>' . htmlspecialchars($user_data['alamat']) . '</td></tr>
        </table>
        
        <p>Telah dilakukan tes assesment sederhana terkait penggunaan dan potensi kecanduan HP pada tanggal <br> ' . $tanggal_surat_indonesia . ' dengan hasil sebagai berikut:</p>
        
        <div class="section-title"><strong>Gejala yang teramati:</strong></div>
        <ul class="list">';
        foreach ($gejala_terpilih as $gejala) {
            $html .= '<li>' . htmlspecialchars($gejala) . '</li>';
        }
$html .= '
        </ul>

        <div class="section-title"><strong>Dengan hasil tingkat kecenderungan kecanduan:</strong></div>
        <ul class="list">';
        foreach ($hasil_diagnosa as $hasil) {
            $html .= '<li>' . htmlspecialchars($hasil['penyakit']) . ': <strong>' . $hasil['cf'] . '%</strong></li>';
        }
        
$html .= '
        </ul>
    </div>

    <div style="page-break-before: always;"></div>

    <div class="konten">
        <div class="section-title"><strong>Rekomendasi:</strong></div>
        <ul class="list">';
        foreach ($rekomendasi as $r) {
            $html .= '<li>' . htmlspecialchars($r) . '</li>';
        }
$html .= '
        </ul>

        <p>Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>

        <div class="tanda-tangan">
            <p>Kediri, ' . $tanggal_surat_indonesia . '</p>
            <p style="margin-top:-10px; z-index: 10;">Mengetahui,</p>
            ' . ($tanda_tangan_base64 ? '<img src="' . $tanda_tangan_base64 . '" alt="Tanda Tangan" class="tanda-tangan-img">' : '<br><br><br>') . '
            <p style="margin-top: -32px;"><strong>Dr. Vivi Ratnawati, S.Pd., M. Psi.</strong></p>
        </div>
    </div>
</body>
</html>';

// --- Generate PDF ---
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "laporan_assesment_" . strtolower(str_replace(' ', '_', $user_data['nama_lengkap'])) . ".pdf";

$dompdf->stream($filename, ["Attachment" => true]);
exit;
?>
