<?php
session_start();
require_once 'config.php';
requireAuth();

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$message = '';
$hasil_diagnosa = [];
$gejala_terpilih_names = [];
$pdf_generated = false;
$pdf_filename = '';

// Proses diagnosa dengan algoritma Certainty Factor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'diagnosa') {
    error_log('POST data received: ' . print_r($_POST, true));
    
    // Lakukan diagnosa jika ada gejala yang dipilih
    if (!empty($_POST['gejala'])) {
        $gejala_terpilih = $_POST['gejala'];
        
        // Ambil nama gejala yang dipilih untuk ditampilkan
        $placeholders_gejala = str_repeat('?,', count($gejala_terpilih) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id_gejala, nama_gejala FROM gejala WHERE id_gejala IN ($placeholders_gejala)");
        $stmt->execute($gejala_terpilih);
        $selected_gejala = $stmt->fetchAll();
        
        foreach ($selected_gejala as $gejala) {
            $gejala_terpilih_names[] = $gejala['nama_gejala'];
        }
        
        // Lakukan perhitungan CF - Ambil penyakit beserta deskripsi dan solusi
        $query = "SELECT DISTINCT p.id_penyakit, p.nama_penyakit
                  FROM penyakit p 
                  JOIN basis_pengetahuan b ON p.id_penyakit = b.id_penyakit
                  WHERE b.id_gejala IN ($placeholders_gejala)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($gejala_terpilih);
        $penyakit_list = $stmt->fetchAll();
        
        foreach ($penyakit_list as $penyakit) {
            $id_penyakit = $penyakit['id_penyakit'];
            
            // Parse nama penyakit untuk mendapatkan deskripsi dan solusi
            $nama_lengkap = $penyakit['nama_penyakit'];
            $parts = explode('||', $nama_lengkap);
            $nama_penyakit = trim($parts[0]);
            $deskripsi = '';
            $solusi = '';
            
            // Extract deskripsi dan solusi dari nama penyakit
            for ($i = 1; $i < count($parts); $i++) {
                $part = trim($parts[$i]);
                if (stripos($part, 'REKOMENDASI') !== false || stripos($part, 'Rekomendasi') !== false) {
                    // Ambil bagian setelah "REKOMENDASI" atau "Rekomendasi"
                    if ($i + 1 < count($parts)) {
                        $solusi = trim($parts[$i + 1]);
                        break;
                    }
                }
            }
            
            // Ambil semua basis pengetahuan untuk penyakit ini dengan gejala yang dipilih
            $query_cf = "SELECT * FROM basis_pengetahuan 
                         WHERE id_penyakit = ? AND id_gejala IN ($placeholders_gejala)";
            $params = array_merge([$id_penyakit], $gejala_terpilih);
            
            $stmt_cf = $pdo->prepare($query_cf);
            $stmt_cf->execute($params);
            $cf_data = $stmt_cf->fetchAll();
            
            $cf_total = 0;
            $first = true;
            
            foreach ($cf_data as $data) {
                $cf = $data['mb'] - $data['md'];
                
                if ($first) {
                    $cf_total = $cf;
                    $first = false;
                } else {
                    // formula CF Old
                    $cf_total = $cf_total + $cf * (1 - $cf_total);
                }
            }
            
            $hasil_diagnosa[] = [
                'id_penyakit' => $id_penyakit,
                'penyakit' => $nama_penyakit,
                'deskripsi' => $deskripsi,
                'solusi' => $solusi,
                'cf' => round(abs($cf_total) * 100, 2),
                'jumlah_gejala_cocok' => count($cf_data)
            ];
        }
        
        // Urutkan berdasarkan nilai CF tertinggi
        usort($hasil_diagnosa, function ($a, $b) {
            return $b['cf'] <=> $a['cf'];
        });
        
        // Simpan hasil diagnosa ke history dan generate PDF
        if (!empty($hasil_diagnosa) && !empty($gejala_terpilih_names)) {
            $gejala_json = json_encode($gejala_terpilih_names);
            $hasil_json = json_encode($hasil_diagnosa);
            
            try {
                // Generate filename PDF yang unik
                $pdf_filename = 'diagnosa_report_' . $_SESSION['user_id'] . '_' . date('Ymd_His') . '.pdf';
                
                // Simpan ke database dengan filename PDF
                $stmt = $pdo->prepare("INSERT INTO history_diagnosa (user_id, tanggal, gejala_terpilih, hasil_diagnosa, pdf_filename) VALUES (?, NOW(), ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $gejala_json, $hasil_json, $pdf_filename]);
                $last_history_id = $pdo->lastInsertId();
                
                // Generate PDF
                $pdf_content = generatePDFContent($user, $gejala_terpilih_names, $hasil_diagnosa);
                
                // Simpan PDF ke direktori
                $pdf_dir = 'pdf_reports';
                if (!file_exists($pdf_dir)) {
                    mkdir($pdf_dir, 0755, true);
                }
                
                // Gunakan library PDF sederhana atau Dompdf
                if (class_exists('Dompdf\Dompdf')) {
                    require_once 'vendor/autoload.php';
                    
                    $options = new \Dompdf\Options();
                    $options->set('isHtml5ParserEnabled', true);
                    $options->set('isRemoteEnabled', true);
                    $options->set('defaultFont', 'Helvetica');
                    
                    $dompdf = new \Dompdf\Dompdf($options);
                    $dompdf->loadHtml($pdf_content);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    
                    // Simpan PDF
                    file_put_contents($pdf_dir . '/' . $pdf_filename, $dompdf->output());
                    $pdf_generated = true;
                }
                
            } catch (PDOException $e) {
                error_log("Error saving diagnosis history: " . $e->getMessage());
                $message = 'Diagnosa berhasil dilakukan namun terjadi error saat menyimpan laporan.';
            }
        }
        
        if ($pdf_generated) {
            $message = 'Diagnosa berhasil dilakukan! Ditemukan ' . count($hasil_diagnosa) . ' kemungkinan masalah. Laporan PDF telah dibuat dan siap didownload.';
        } else {
            $message = 'Diagnosa berhasil dilakukan! Ditemukan ' . count($hasil_diagnosa) . ' kemungkinan masalah.';
        }
        
    } else {
        $message = 'Silakan pilih minimal satu gejala untuk diagnosa.';
    }
}

// Fungsi untuk generate konten PDF
function generatePDFContent($user, $gejala_terpilih, $hasil_diagnosa) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Laporan Diagnosa Penggunaan HP Berlebihan</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
            .section { margin-bottom: 25px; }
            .section-title { background-color: #f0f0f0; padding: 10px; font-weight: bold; margin-bottom: 10px; }
            .hasil-item { margin-bottom: 20px; padding: 15px; border-left: 4px solid #007bff; background-color: #f8f9fa; }
            .hasil-utama { border-left-color: #28a745; }
            .cf-tinggi { color: #28a745; font-weight: bold; }
            .cf-sedang { color: #ffc107; font-weight: bold; }
            .cf-rendah { color: #dc3545; font-weight: bold; }
            .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; }
            ol li { margin-bottom: 5px; }
            .rekomendasi { background-color: #e7f3ff; padding: 10px; border-radius: 5px; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Laporan Diagnosa Penggunaan HP Berlebihan</h1>
            <p><strong>Sistem Pakar dengan Metode Certainty Factor</strong></p>
            <p>Tanggal: ' . date('d F Y, H:i') . ' WIB</p>
        </div>
        
        <div class="section">
            <div class="section-title">Informasi Pengguna</div>
            <p><strong>Nama Lengkap:</strong> ' . htmlspecialchars($user['nama_lengkap']) . '</p>
            <p><strong>Username:</strong> ' . htmlspecialchars($user['username']) . '</p>
            <p><strong>Jenis Kelamin:</strong> ' . ucfirst(htmlspecialchars($user['jenis_kelamin'])) . '</p>
            <p><strong>Umur:</strong> ' . ($user['umur'] ? htmlspecialchars($user['umur']) . ' tahun' : 'Tidak diisi') . '</p>
        </div>
        
        <div class="section">
            <div class="section-title">Gejala yang Dipilih (' . count($gejala_terpilih) . ' gejala)</div>
            <ol>';
    
    foreach ($gejala_terpilih as $gejala) {
        $html .= '<li>' . htmlspecialchars($gejala) . '</li>';
    }
    
    $html .= '
            </ol>
        </div>
        
        <div class="section">
            <div class="section-title">Hasil Diagnosa</div>';
    
    foreach ($hasil_diagnosa as $index => $hasil) {
        $cf_class = ($hasil['cf'] >= 70) ? 'cf-tinggi' : (($hasil['cf'] >= 40) ? 'cf-sedang' : 'cf-rendah');
        $tingkat = ($hasil['cf'] >= 70) ? 'Tinggi' : (($hasil['cf'] >= 40) ? 'Sedang' : 'Rendah');
        
        $html .= '
            <div class="hasil-item ' . ($index === 0 ? 'hasil-utama' : '') . '">
                <h3>' . ($index + 1) . '. ' . htmlspecialchars($hasil['penyakit']) . 
                ($index === 0 ? ' <span style="color: #28a745;">(Diagnosa Utama)</span>' : '') . '</h3>
                <p><strong>Tingkat Kepercayaan:</strong> <span class="' . $cf_class . '">' . $hasil['cf'] . '%</span> (' . $tingkat . ')</p>
                <p><strong>Jumlah Gejala Cocok:</strong> ' . $hasil['jumlah_gejala_cocok'] . ' dari ' . count($gejala_terpilih) . ' gejala yang dipilih</p>';
        
        if (!empty($hasil['solusi'])) {
            $html .= '
                <div class="rekomendasi">
                    <h4>� Rekomendasi Penanganan:</h4>
                    <p>' . htmlspecialchars($hasil['solusi']) . '</p>
                </div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '
        </div>
        
        <div class="section">
            <div class="section-title">Catatan Penting</div>
            <p>• Hasil diagnosa ini bersifat prediktif berdasarkan gejala yang dipilih menggunakan metode Certainty Factor.</p>
            <p>• Rekomendasi di atas dapat diterapkan sesuai dengan tingkat keparahan yang terdeteksi.</p>
            <p>• Untuk penanganan yang lebih komprehensif, disarankan melibatkan guru BK, orang tua, dan konselor sekolah.</p>
            <p>• Tingkat kepercayaan: Tinggi (≥70%), Sedang (40-69%), Rendah (<40%)</p>
        </div>
        
        <div class="footer">
            <p><strong>Sistem Pakar Diagnosa Penggunaan HP Berlebihan</strong></p>
            <p>Laporan ini dibuat secara otomatis pada ' . date('d F Y, H:i') . ' WIB</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Ambil history diagnosa user
try {
    $stmt = $pdo->prepare("SELECT * FROM history_diagnosa WHERE user_id = ? ORDER BY tanggal DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $history_diagnosa = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching diagnosis history: " . $e->getMessage());
    $history_diagnosa = [];
}

// Ambil data gejala dari database
$stmt = $pdo->query("SELECT id_gejala, nama_gejala FROM gejala ORDER BY nama_gejala");
$gejala_options = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Pakar Diagnosa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg fixed top-0 left-0 right-0 z-10">
        <div class="max-w-7xl mx-auto px-8 sm:px-6 lg:px-12">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-800">Dashboard Sistem Pakar</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">Halo, <?php echo htmlspecialchars($user['username']); ?>!</span>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8 mt-14">
        <?php if ($message): ?>
            <div class="mb-6 <?php echo strpos($message, 'berhasil') !== false ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> border px-4 py-3 rounded-lg fade-in relative">
                <?php echo htmlspecialchars($message); ?>
                <?php if ($pdf_generated && !empty($pdf_filename)): ?>
                    <div class="mt-3">
                        <a href="download_pdf.php?file=<?php echo urlencode($pdf_filename); ?>" 
                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Download Laporan PDF
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- User Information Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white overflow-hidden shadow-lg rounded-lg">
                <div class="bg-teal-600 px-6 py-4">
                    <h3 class="text-lg font-medium text-white">Informasi Personal</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="space-y-2">
                        <p class="text-sm text-gray-600">Nama Lengkap</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($user['nama_lengkap']); ?></p>
                        <p class="text-sm text-gray-600">Username</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow-lg rounded-lg">
                <div class="bg-teal-600 px-6 py-4">
                    <h3 class="text-lg font-medium text-white">Data Diri</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="space-y-2">
                        <p class="text-sm text-gray-600">Jenis Kelamin</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo ucfirst(htmlspecialchars($user['jenis_kelamin'])); ?></p>
                        <p class="text-sm text-gray-600">Umur</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($user['umur'] ?: 'Tidak diisi'); ?> <?php echo $user['umur'] ? 'tahun' : ''; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white overflow-hidden shadow-lg rounded-lg">
                <div class="bg-teal-600 px-6 py-4">
                    <h3 class="text-lg font-medium text-white">Kontak</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="space-y-2">
                        <p class="text-sm text-gray-600">Tanggal Lahir</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo date('d F Y', strtotime($user['tanggal_lahir'])); ?></p>
                        <p class="text-sm text-gray-600">Alamat</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($user['alamat']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Diagnosa -->
        <div class="bg-white shadow-lg rounded-lg mb-8">
            <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 px-6 py-4">
                <h3 class="text-lg font-medium text-white">Pilih Gejala untuk Diagnosa</h3>
                <p class="text-indigo-100 text-sm">Pilih gejala yang dialami siswa untuk mendapatkan diagnosa penggunaan HP berlebihan menggunakan metode Certainty Factor</p>
            </div>
            <div class="px-6 py-6">
                <form method="POST" class="space-y-4" id="diagnosisForm">
                    <input type="hidden" name="action" value="diagnosa">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <?php if (!empty($gejala_options)): ?>
                            <?php foreach ($gejala_options as $gejala): ?>
                                <label class="flex items-start p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                                    <input type="checkbox" name="gejala[]" value="<?php echo $gejala['id_gejala']; ?>" 
                                           class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 mt-1">
                                    <span class="ml-3 text-gray-700 font-medium leading-relaxed"><?php echo htmlspecialchars($gejala['nama_gejala']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <p class="text-gray-500">Tidak ada data gejala tersedia.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-600">
                                <span id="selectedCount">0</span> gejala dipilih
                            </div>
                            <button type="submit" 
                                    class="bg-indigo-600 text-white px-8 py-3 rounded-lg hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors font-medium">
                                <span class="inline-flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    Lakukan Diagnosa
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Gejala yang Dipilih -->
        <?php if (!empty($gejala_terpilih_names)): ?>
        <div class="bg-white shadow-lg rounded-lg mb-8 fade-in">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                <h3 class="text-lg font-medium text-white">Gejala yang Dipilih</h3>
            </div>
            <div class="px-6 py-4">
                <div class="space-y-2">
                    <?php foreach ($gejala_terpilih_names as $index => $gejala_name): ?>
                        <div class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium bg-blue-100 text-blue-800 mr-2 mb-2">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="mr-1"><?php echo ($index + 1); ?>.</span>
                            <?php echo htmlspecialchars($gejala_name); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Hasil Diagnosa -->
        <?php if (!empty($hasil_diagnosa)): ?>
        <div class="bg-white shadow-lg rounded-lg mb-8 fade-in">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                <h3 class="text-lg font-medium text-white">Hasil Diagnosa Penggunaan HP Berlebihan</h3>
                <p class="text-green-100 text-sm">Berdasarkan <?php echo count($gejala_terpilih_names); ?> gejala yang dipilih, berikut adalah hasil diagnosa menggunakan metode Certainty Factor</p>
            </div>
            <div class="px-6 pt-4 border-b border-gray-200">
        <form action="report.php" method="POST" target="_blank">
            <input type="hidden" name="user_data" value="<?php echo htmlspecialchars(json_encode($user)); ?>">
            <input type="hidden" name="gejala_terpilih" value="<?php echo htmlspecialchars(json_encode($gejala_terpilih_names)); ?>">
            <input type="hidden" name="hasil_diagnosa" value="<?php echo htmlspecialchars(json_encode($hasil_diagnosa)); ?>">
            
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                <span class="inline-flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Cetak Laporan Lengkap
                </span>
            </button>
        </form>
    </div>
            <div class="px-6 py-6">
                <div class="space-y-6">
                    <?php foreach ($hasil_diagnosa as $index => $hasil): ?>
                        <div class="bg-gray-50 p-6 rounded-lg border-l-4 <?php echo $index === 0 ? 'border-green-500' : 'border-gray-300'; ?>">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center mb-3">
                                        <h4 class="text-xl font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($hasil['penyakit']); ?>
                                        </h4>
                                        <?php if ($index === 0): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 ml-3">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                Diagnosa Utama
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text-sm text-gray-500 mb-4">
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <?php echo $hasil['jumlah_gejala_cocok']; ?> gejala yang cocok dari <?php echo count($gejala_terpilih_names); ?> yang dipilih
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($hasil['solusi'])): ?>
                                    <div class="bg-white p-4 rounded-lg border border-gray-200 mt-4">
                                        <h5 class="font-medium text-gray-800 mb-2 flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                            </svg>
                                            Rekomendasi Penanganan:
                                        </h5>
                                        <p class="text-gray-600 text-sm leading-relaxed"><?php echo htmlspecialchars($hasil['solusi']); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-right ml-6">
                                    <div class="text-3xl font-bold <?php echo $hasil['cf'] >= 70 ? 'text-green-600' : ($hasil['cf'] >= 40 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                        <?php echo $hasil['cf']; ?>%
                                    </div>
                                    <div class="text-sm text-gray-500 mb-2">Tingkat Kepercayaan</div>
                                    <div class="w-24 bg-gray-200 rounded-full h-3">
                                        <div class="<?php echo $hasil['cf'] >= 70 ? 'bg-green-600' : ($hasil['cf'] >= 40 ? 'bg-yellow-600' : 'bg-red-600'); ?> h-3 rounded-full transition-all duration-500" 
                                             style="width: <?php echo min($hasil['cf'], 100); ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1">
                                        <?php 
                                        if ($hasil['cf'] >= 70) echo 'Tinggi';
                                        elseif ($hasil['cf'] >= 40) echo 'Sedang';
                                        else echo 'Rendah';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h5 class="font-medium text-blue-800 mb-1">Catatan Penting</h5>
                            <p class="text-sm text-blue-700">
                                Hasil diagnosa ini bersifat prediktif berdasarkan gejala yang dipilih menggunakan metode Certainty Factor. 
                                Rekomendasi di atas dapat diterapkan sesuai dengan tingkat keparahan yang terdeteksi. Untuk penanganan yang lebih komprehensif, 
                                disarankan melibatkan guru BK, orang tua, dan konselor sekolah.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- History Diagnosa -->
        <div class="bg-white shadow-lg rounded-lg mb-8 fade-in">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                <h3 class="text-lg font-medium text-white">Riwayat Diagnosa</h3>
                <p class="text-purple-100 text-sm">Berikut adalah history hasil diagnosa yang telah Anda lakukan</p>
            </div>
            <div class="px-6 py-6">
                <?php if (!empty($history_diagnosa)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Gejala</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hasil Diagnosa</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($history_diagnosa as $history): ?>
                                    <?php 
                                    $gejala_data = json_decode($history['gejala_terpilih'], true);
                                    $hasil_data = json_decode($history['hasil_diagnosa'], true);
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('d F Y', strtotime($history['tanggal'])); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo date('H:i:s', strtotime($history['tanggal'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo count($gejala_data); ?> gejala</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php if (!empty($hasil_data)): ?>
                                                    <?php echo htmlspecialchars($hasil_data[0]['penyakit']); ?>
                                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                          <?php echo $hasil_data[0]['cf'] >= 70 ? 'bg-green-100 text-green-800' : ($hasil_data[0]['cf'] >= 40 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                                        <?php echo $hasil_data[0]['cf']; ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-400">Tidak ada hasil</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="showDetails(<?php echo htmlspecialchars(json_encode($history)); ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 inline-flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                Detail
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">Belum ada riwayat diagnosa</h3>
                        <p class="mt-1 text-sm text-gray-500">Hasil diagnosa Anda akan muncul di sini setelah melakukan tes.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal untuk detail history -->
    <div id="detailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-lg max-w-4xl w-full max-h-screen overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Detail Hasil Diagnosa</h3>
                        <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div id="modalContent" class="px-6 py-4">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('diagnosisForm');
        const submitButton = form.querySelector('button[type="submit"]');
        const checkboxes = document.querySelectorAll('input[name="gejala[]"]');
        const selectedCount = document.getElementById('selectedCount');
        
        // Update counter gejala yang dipilih
        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('input[name="gejala[]"]:checked');
            selectedCount.textContent = checkedBoxes.length;
        }
        
        // Event listener untuk setiap checkbox
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', updateSelectedCount);
        });
        
        // Form submission
        form.addEventListener('submit', function(e) {
            const checkedBoxes = document.querySelectorAll('input[name="gejala[]"]:checked');
            
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Silakan pilih minimal satu gejala untuk melakukan diagnosa.');
                return;
            }
            
            // Show loading state
            const originalHTML = submitButton.innerHTML;
            submitButton.innerHTML = `
                <span class="inline-flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Memproses Diagnosa...
                </span>
            `;
            submitButton.disabled = true;
            
            // Reset button after a delay if form submission fails
            setTimeout(function() {
                submitButton.innerHTML = originalHTML;
                submitButton.disabled = false;
            }, 10000);
        });
        
        // Initial count update
        updateSelectedCount();
    });

    // Function to show detail modal
    function showDetails(data) {
        const modal = document.getElementById('detailModal');
        const modalContent = document.getElementById('modalContent');
        
        const gejalaData = JSON.parse(data.gejala_terpilih);
        const hasilData = JSON.parse(data.hasil_diagnosa);
        
        let content = `
            <div class="space-y-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-3">Informasi Diagnosa</h4>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p><strong>Tanggal:</strong> ${new Date(data.tanggal).toLocaleDateString('id-ID', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}</p>
                        <p><strong>Jumlah Gejala:</strong> ${gejalaData.length} gejala</p>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-3">Gejala yang Dipilih</h4>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <ol class="list-decimal list-inside space-y-1">`;
        
        gejalaData.forEach(function(gejala) {
            content += `<li class="text-sm">${gejala}</li>`;
        });
        
        content += `
                        </ol>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-3">Hasil Diagnosa</h4>
                    <div class="space-y-4">`;
        
        hasilData.forEach(function(hasil, index) {
            const cfColor = hasil.cf >= 70 ? 'text-green-600' : (hasil.cf >= 40 ? 'text-yellow-600' : 'text-red-600');
            const borderColor = index === 0 ? 'border-green-500' : 'border-gray-300';
            
            content += `
                <div class="bg-gray-50 p-4 rounded-lg border-l-4 ${borderColor}">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h5 class="font-semibold text-gray-800">${hasil.penyakit}</h5>
                            ${index === 0 ? '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-1">Diagnosa Utama</span>' : ''}
                            <p class="text-sm text-gray-600 mt-2">Gejala cocok: ${hasil.jumlah_gejala_cocok} dari ${gejalaData.length}</p>
                            ${hasil.solusi ? `<div class="mt-3 p-3 bg-white rounded border"><h6 class="font-medium text-sm">Rekomendasi:</h6><p class="text-sm text-gray-600 mt-1">${hasil.solusi}</p></div>` : ''}
                        </div>
                        <div class="text-right ml-4">
                            <div class="text-2xl font-bold ${cfColor}">${hasil.cf}%</div>
                            <div class="text-xs text-gray-500">Kepercayaan</div>
                        </div>
                    </div>
                </div>`;
        });
        
        content += `
                    </div>
                </div>
            </div>`;
        
        modalContent.innerHTML = content;
        modal.classList.remove('hidden');
    }

    function closeModal() {
        const modal = document.getElementById('detailModal');
        modal.classList.add('hidden');
    }

    // Auto hide notification after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const notification = document.querySelector('.fade-in');
        if (notification && notification.textContent.includes('Diagnosa berhasil')) {
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.className = 'absolute top-2 right-2 text-xl font-bold hover:opacity-75';
            closeBtn.onclick = function() {
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 200);
            };
            
            // Make notification relative if not already
            if (!notification.style.position) {
                notification.style.position = 'relative';
            }
            notification.appendChild(closeBtn);
            
            // Auto hide after 8 seconds (lebih lama karena ada tombol download)
            setTimeout(function() {
                notification.style.transition = 'opacity 0.5s ease-out';
                notification.style.opacity = '0';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 500);
            }, 8000);
        }
    });
    </script>
</body>
</html>