<?php
session_start();
require_once __DIR__ . '/../../../config.php';
requireAdmin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="text-red-500">ID tidak valid</div>';
    exit;
}

$id = (int)$_GET['id'];

try {
    $query = "
        SELECT 
            hd.*,
            u.nama_lengkap,
            u.jenis_kelamin,
            u.umur,
            u.status
        FROM history_diagnosa hd
        JOIN users u ON hd.user_id = u.id
        WHERE hd.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) {
        echo '<div class="text-red-500">Data tidak ditemukan</div>';
        exit;
    }
    
    $gejala_array = json_decode($data['gejala_terpilih'], true);
    $hasil_array = json_decode($data['hasil_diagnosa'], true);
    
} catch (PDOException $e) {
    echo '<div class="text-red-500">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="space-y-6">
    <!-- Informasi Siswa -->
    <div class="bg-gray-50 p-4 rounded-lg">
        <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
            <i class="fas fa-user mr-2 text-teal-600"></i>
            Informasi Siswa
        </h4>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-gray-600">Nama:</span>
                <span class="font-medium ml-2"><?php echo htmlspecialchars($data['nama_lengkap']); ?></span>
            </div>
            <div>
                <span class="text-gray-600">Jenis Kelamin:</span>
                <span class="font-medium ml-2"><?php echo ucfirst($data['jenis_kelamin']); ?></span>
            </div>
            <div>
                <span class="text-gray-600">Umur:</span>
                <span class="font-medium ml-2"><?php echo $data['umur'] ?? '-'; ?> tahun</span>
            </div>
            <div>
                <span class="text-gray-600">Status:</span>
                <span class="font-medium ml-2"><?php echo htmlspecialchars($data['status'] ?? '-'); ?></span>
            </div>
        </div>
    </div>

    <!-- Tanggal Diagnosa -->
    <div class="bg-blue-50 p-4 rounded-lg">
        <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
            <i class="fas fa-calendar-alt mr-2 text-blue-600"></i>
            Tanggal Diagnosa
        </h4>
        <p class="text-sm"><?php echo date('d F Y, H:i:s', strtotime($data['tanggal'])); ?></p>
    </div>

    <!-- Gejala yang Dipilih -->
    <div class="bg-yellow-50 p-4 rounded-lg">
        <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
            <i class="fas fa-list-ul mr-2 text-yellow-600"></i>
            Gejala yang Dipilih (<?php echo count($gejala_array); ?> gejala)
        </h4>
        <ul class="space-y-2">
            <?php foreach ($gejala_array as $index => $gejala): ?>
                <li class="flex items-start text-sm">
                    <span class="bg-yellow-200 text-yellow-800 px-2 py-1 rounded-full text-xs font-medium mr-3 mt-0.5">
                        <?php echo $index + 1; ?>
                    </span>
                    <span><?php echo htmlspecialchars($gejala); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Hasil Diagnosa -->
    <div class="bg-green-50 p-4 rounded-lg">
        <h4 class="font-semibold text-gray-700 mb-3 flex items-center">
            <i class="fas fa-stethoscope mr-2 text-green-600"></i>
            Hasil Diagnosa
        </h4>
        <div class="space-y-4">
            <?php foreach ($hasil_array as $index => $hasil): ?>
                <?php 
                $badgeClass = 'bg-gray-100 text-gray-800';
                $iconClass = 'fas fa-circle';
                
                if (strpos($hasil['penyakit'], 'Ringan') !== false) {
                    $badgeClass = 'bg-green-100 text-green-800 border-green-300';
                    $iconClass = 'fas fa-check-circle text-green-500';
                } elseif (strpos($hasil['penyakit'], 'Sedang') !== false) {
                    $badgeClass = 'bg-yellow-100 text-yellow-800 border-yellow-300';
                    $iconClass = 'fas fa-exclamation-triangle text-yellow-500';
                } elseif (strpos($hasil['penyakit'], 'Berat') !== false) {
                    $badgeClass = 'bg-red-100 text-red-800 border-red-300';
                    $iconClass = 'fas fa-times-circle text-red-500';
                }
                ?>
                <div class="border <?php echo $badgeClass; ?> p-3 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <i class="<?php echo $iconClass; ?> mr-2"></i>
                            <h5 class="font-semibold"><?php echo htmlspecialchars($hasil['penyakit']); ?></h5>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs">
                                CF: <?php echo number_format($hasil['cf'], 1); ?>%
                            </span>
                            <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs">
                                <?php echo $hasil['jumlah_gejala_cocok']; ?> gejala cocok
                            </span>
                        </div>
                    </div>
                    
                    <?php if (!empty($hasil['solusi'])): ?>
                        <div class="mt-2 p-2 bg-white rounded border-l-4 border-teal-400">
                            <p class="text-sm"><strong>Rekomendasi:</strong></p>
                            <p class="text-sm text-gray-700"><?php echo htmlspecialchars($hasil['solusi']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- File PDF -->
    <?php if (!empty($data['pdf_filename'])): ?>
        <div class="bg-teal-50 p-4 rounded-lg">
            <h4 class="font-semibold text-gray-700 mb-2 flex items-center">
                <i class="fas fa-file-pdf mr-2 text-teal-600"></i>
                File Laporan PDF
            </h4>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600"><?php echo htmlspecialchars($data['pdf_filename']); ?></span>
                <button 
                    onclick="downloadPDF('<?php echo htmlspecialchars($data['pdf_filename']); ?>')"
                    class="px-3 py-1 bg-teal-600 text-white rounded hover:bg-teal-700 transition flex items-center text-sm"
                >
                    <i class="fas fa-download mr-1"></i>
                    Download PDF
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="flex justify-end mt-6">
    <button 
        onclick="closeModal()" 
        class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition"
    >
        Tutup
    </button>
</div>