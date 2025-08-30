<?php
session_start();
require_once 'config.php';
requireAuth();

// Panggil library Dompdf
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Cek apakah ada data POST yang dikirim
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses tidak diizinkan.");
}

// Ambil dan decode data dari POST
$user_data = isset($_POST['user_data']) ? json_decode($_POST['user_data'], true) : null;
$gejala_terpilih = isset($_POST['gejala_terpilih']) ? json_decode($_POST['gejala_terpilih'], true) : [];
$hasil_diagnosa = isset($_POST['hasil_diagnosa']) ? json_decode($_POST['hasil_diagnosa'], true) : [];

if (!$user_data || empty($hasil_diagnosa)) {
    die("Data tidak lengkap untuk membuat laporan.");
}

// --- FUNGSI BANTU UNTUK FORMAT TANGGAL BAHASA INDONESIA ---

function getRomanMonth($month) {
    $romans = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
    return $romans[$month - 1];
}

function formatTanggalIndonesia($tanggal) {
    // Array nama bulan dalam bahasa Indonesia
    $nama_bulan = array(
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    );
    
    // Ubah string tanggal ke timestamp
    $timestamp = strtotime($tanggal);
    
    // Format tanggal: tanggal bulan tahun (contoh: 15 Agustus 2023)
    $tanggal_format = date('d', $timestamp);
    $bulan = $nama_bulan[date('n', $timestamp)];
    $tahun = date('Y', $timestamp);
    
    return $tanggal_format . ' ' . $bulan . ' ' . $tahun;
}

function getHariIniIndonesia() {
    // Array nama hari dalam bahasa Indonesia
    $nama_hari = array(
        'Minggu',
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu'
    );
    
    $hari_ini = $nama_hari[date('w')];
    $tanggal_hari_ini = formatTanggalIndonesia(date('Y-m-d'));
    
    return $hari_ini . ', ' . $tanggal_hari_ini;
}

// --- PENGOLAHAN DATA UNTUK SURAT ---

// 1. Generate Nomor Surat Otomatis (Contoh: 001/BK-UNP/VIII/2025)
$nomor_surat = sprintf("%03d/BK-UNP/%s/%d", $user_data['id'], getRomanMonth(date('n')), date('Y'));

// 2. Ambil Rekomendasi dari hasil CF tertinggi
$rekomendasi = [];
foreach ($hasil_diagnosa as $hasil) {
    if (!empty($hasil['solusi'])) {
        if (is_array($hasil['solusi'])) {
            foreach ($hasil['solusi'] as $solusi) {
                $rekomendasi[] = $solusi;
            }
        } else {
            $rekomendasi[] = $hasil['solusi'];
        }
    }
}
if (empty($rekomendasi)) {
    $rekomendasi[] = 'Tidak ada rekomendasi spesifik.';
}

// 3. Konversi gambar ke Base64 untuk disematkan di PDF
function imageToBase64($path) {
    if (!file_exists($path)) {
        return '';
    }
    $type = pathinfo($path, PATHINFO_EXTENSION);
    $data = file_get_contents($path);
    return 'data:image/' . $type . ';base64,' . base64_encode($data);
}

$kop_surat_base64 = imageToBase64('img/kop_surat.png');
$tanda_tangan_base64 = imageToBase64('img/tanda_tangan.png');

// Format tanggal lahir user ke bahasa Indonesia
$tanggal_lahir_indonesia = formatTanggalIndonesia($user_data['tanggal_lahir']);
$tanggal_surat_indonesia = getHariIniIndonesia();

// --- PEMBUATAN KONTEN HTML UNTUK PDF ---

$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Hasil Assasement</title>
    <style>
        @page {
            margin: 0.5cm 1.5cm;
        }
        body {
            font-family: Times, "Times New Roman", serif;
            font-size: 12pt;
            line-height: 1.5;
        }
        td {
            line-height: 0.8;
        }
        .kop-surat {
            text-align: center;
            margin-bottom: 20px;
        }
        .kop-surat img {
            width: 125%;
        }
        .konten {
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-info td {
            padding: 2px 0;
            vertical-align: top;
            line-height: 1.2;
        }
        .table-info td:first-child {
            width: 120px;
        }
        .table-info td:nth-child(2) {
            width: 15px;
        }
        .section-title {
            margin-top: 15px;
            margin-bottom: 5px;
        }
        .list {
            padding-left: 20px;
            margin: 0;
        }
        .tanda-tangan {
            margin-top: 20px;
            width: 300px;
            margin-left: auto;
            text-align: left;
        }
        .tanda-tangan-img {
            margin-top: -10px;
            width: 200px;
            height: 80px;
        }
    </style>
</head>
<body>
    <div class="kop-surat" style="margin-top:-10px;">
        ' . ($kop_surat_base64 ? '<img src="' . $kop_surat_base64 . '" alt="Kop Surat">' : '<h1>UNIVERSITAS NUSANTARA PGRI KEDIRI</h1><p>FAKULTAS KEGURUAN DAN ILMU PENDIDIKAN</p>') . '
    </div>

    <div class="konten">
        <table class="table-info" style="width: 50%;">
            <tr>
                <td>Nomor</td>
                <td>:</td>
                <td>' . htmlspecialchars($nomor_surat) . '</td>
            </tr>
            <tr>
                <td>Hal</td>
                <td>:</td>
                <td>Surat Keterangan Hasil Assasement</td>
            </tr>
             <tr>
                <td>Lampiran</td>
                <td>:</td>
                <td>-</td>
            </tr>
        </table>
        
        <p>Yang bertanda tangan di bawah ini:</p>
        <table class="table-info" style="margin-left: 20px;">
            <tr>
                <td>Nama</td>
                <td>:</td>
                <td><strong>Dr. Vivi Ratnawati, S.Pd., M. Psi.</strong></td>
            </tr>
            <tr>
                <td>NIDN</td>
                <td>:</td>
                <td>0728038306</td>
            </tr>
            <tr>
                <td>Jabatan</td>
                <td>:</td>
                <td>Dosen Psikolog</td>
            </tr>
             <tr>
                <td>Instansi</td>
                <td>:</td>
                <td>Universitas Nusantara PGRI Kediri</td>
            </tr>
        </table>

        <p>Dengan ini menyatakan bahwa:</p>
        <table class="table-info" style="margin-left: 20px;">
            <tr>
                <td>Nama Anak</td>
                <td>:</td>
                <td><strong>' . htmlspecialchars($user_data['nama_lengkap']) . '</strong></td>
            </tr>
            <tr>
                <td>Tanggal Lahir</td>
                <td>:</td>
                <td>' . $tanggal_lahir_indonesia . '</td>
            </tr>
             <tr>
                <td>Sekolah</td>
                <td>:</td>
                <td>SDM 1 Betet</td>
            </tr>
             <tr>
                <td>Keterangan</td>
                <td>:</td>
                <td>Anak dengan kebutuhan khusus (inklusi)</td>
            </tr>
        </table>
        
        <p>Telah dilakukan tes assasement sederhana terkait penggunaan dan potensi kecanduan HP pada tanggal <br> ' . $tanggal_surat_indonesia . ' dengan hasil sebagai berikut:</p>
        
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
    <!-- Halaman 1 selesai -->

    <div style="page-break-before: always;"></div>
    <!-- Halaman 2: Rekomendasi dan Penutup -->
    <div class="konten">
    <div class="section-title"><strong>Rekomendasi:</strong></div>
    <ul class="list">';
    foreach ($rekomendasi as $hasil) {
         $html .= '<li>' . htmlspecialchars($hasil) . '</li>';
    }
$html .= '
    </ul>

    <p>Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>

    <div class="tanda-tangan">
        <p>Kediri, ' . $tanggal_surat_indonesia . '</p>
        <p>Mengetahui,</p>
        ' . ($tanda_tangan_base64 ? '<img src="' . $tanda_tangan_base64 . '" alt="Tanda Tangan" class="tanda-tangan-img">' : '<br><br><br>') . '
        <p style="margin-top: -22px;"><strong>Dr. Vivi Ratnawati, S.Pd., M. Psi.</strong></p>
    </div>
    </div>
</body>
</html>';

// --- GENERATE PDF ---

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "laporan_assesment_" . strtolower(str_replace(' ', '_', $user_data['nama_lengkap'])) . ".pdf";

// --- OPSI SIMPLE: LANGSUNG DOWNLOAD FILE PDF ---
$dompdf->stream($filename, ["Attachment" => true]);

exit;
?>
