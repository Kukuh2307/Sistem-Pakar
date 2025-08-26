<?php
session_start();
require_once 'config.php';
requireAuth();

// Ambil data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$message = '';

// Debugging: Log POST data for troubleshooting
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_checklist') {
    error_log('POST data received: ' . print_r($_POST, true));
    
    // Hapus checklist lama user ini
    $stmt = $pdo->prepare("DELETE FROM checklist_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Simpan checklist baru jika ada item yang dipilih
    if (!empty($_POST['checklist_items'])) {
        foreach ($_POST['checklist_items'] as $item) {
            // Pastikan item tidak kosong
            if (!empty(trim($item))) {
                $stmt = $pdo->prepare("INSERT INTO checklist_items (user_id, item_name, is_checked) VALUES (?, ?, 1)");
                $stmt->execute([$_SESSION['user_id'], htmlspecialchars(trim($item))]);
            }
        }
        $message = 'Checklist berhasil disimpan!';
    } else {
        // Jika tidak ada item yang dipilih, tetap beri pesan sukses
        $message = 'Checklist berhasil diperbarui!';
        error_log('No checklist items selected');
    }
}

// Ambil checklist yang sudah tersimpan
$stmt = $pdo->prepare("SELECT item_name FROM checklist_items WHERE user_id = ? AND is_checked = 1");
$stmt->execute([$_SESSION['user_id']]);
$saved_checklist = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Ambil data gejala dari database
$stmt = $pdo->query("SELECT nama_gejala FROM gejala");
$gejala_options = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-800">Dashboard</h1>
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
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
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
                        <p class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($user['jenis_kelamin']); ?></p>
                        <p class="text-sm text-gray-600">Umur</p>
                        <p class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($user['umur']); ?> tahun</p>
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
        
        <!-- Checklist Form -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 px-6 py-4">
                <h3 class="text-lg font-medium text-white">Checklist Gejala</h3>
                <p class="text-indigo-100 text-sm">Pilih gejala yang sudah Anda alami</p>
            </div>
            <div class="px-6 py-6">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="save_checklist">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php if (!empty($gejala_options)): ?>
                            <?php foreach ($gejala_options as $option): ?>
                                <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors cursor-pointer">
                                    <input type="checkbox" name="checklist_items[]" value="<?php echo htmlspecialchars($option); ?>" 
                                           <?php echo in_array($option, $saved_checklist) ? 'checked' : ''; ?>
                                           class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                    <span class="ml-3 text-gray-700 font-medium"><?php echo htmlspecialchars($option); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-span-2 text-center py-8">
                                <p class="text-gray-500">Tidak ada data gejala tersedia.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pt-4 border-t border-gray-200">
                        <button type="submit" 
                                class="bg-indigo-600 text-white px-8 py-3 rounded-lg hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors font-medium">
                            Simpan Checklist
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="mt-8 bg-white shadow-lg rounded-lg">
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-4">
                <h3 class="text-lg font-medium text-white">Hasil Diagnosa</h3>
            </div>
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-orange-600"><?php echo count($saved_checklist); ?></div>
                        <div class="text-gray-600">Gejala yang dialami</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600"><?php echo count($gejala_options); ?></div>
                        <div class="text-gray-600">Total Gejala</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600">
                            <?php 
                            if (count($gejala_options) > 0) {
                                echo round((count($saved_checklist) / count($gejala_options)) * 100);
                            } else {
                                echo 0;
                            }
                            ?>%
                        </div>
                        <div class="text-gray-600">Persentase Indikator</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // JavaScript untuk debugging
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('input[name="checklist_items[]"]:checked');
            console.log('Checkboxes checked:', checkboxes.length);
            checkboxes.forEach(function(checkbox) {
                console.log('Selected:', checkbox.value);
            });
        });
    });
    </script>
</body>
</html>