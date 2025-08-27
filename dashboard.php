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
                    // Formula CF kombinasi: CF_total + CF_new * (1 - CF_total)
                    $cf_total = $cf_total + $cf * (1 - abs($cf_total));
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
        
        $message = 'Diagnosa berhasil dilakukan! Ditemukan ' . count($hasil_diagnosa) . ' kemungkinan masalah.';
    } else {
        $message = 'Silakan pilih minimal satu gejala untuk diagnosa.';
    }
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
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-800">Dashboard Sistem Pakar</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-700">Halo, <?php echo htmlspecialchars($user['nama_lengkap']); ?>!</span>
                    <a href="logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if ($message): ?>
            <div class="mb-6 <?php echo strpos($message, 'berhasil') !== false ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'; ?> border px-4 py-3 rounded-lg fade-in">
                <?php echo htmlspecialchars($message); ?>
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
                    
                    <div class="grid grid-cols-3 gap-4">
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
        
        // Make notification relative and add close button
        notification.style.position = 'relative';
        notification.appendChild(closeBtn);
        
        // Auto hide after 5 seconds
        setTimeout(function() {
            notification.style.transition = 'opacity 0.5s ease-out';
            notification.style.opacity = '0';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 500);
        }, 5000); // 5000ms = 5 detik
    }
});
</script>
</body>
</html>