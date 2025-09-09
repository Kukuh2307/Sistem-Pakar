<?php
session_start();
require_once __DIR__ . '/../../../config.php';
requireAdmin();

try {
    // Konfigurasi pagination
    $limit = 10;
    $hal = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
    $hal = max($hal, 1);
    $offset = ($hal - 1) * $limit;

    // Hitung total data
    $countQuery = "SELECT COUNT(*) as total FROM history_diagnosa";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute();
    $totalData = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = max(1, ceil($totalData / $limit));

    // Query data
    $query = "
      SELECT 
        hd.id,
        u.nama_lengkap,
        hd.tanggal,
        hd.gejala_terpilih,
        hd.hasil_diagnosa,
        hd.pdf_filename,
        hd.user_id
      FROM history_diagnosa hd
      JOIN users u ON hd.user_id = u.id
      ORDER BY hd.tanggal DESC
      LIMIT $limit OFFSET $offset
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $laporan_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $laporan_data = [];
    $totalData = 0;
    $totalPages = 1;
}

function hitungJumlahGejala($gejala_json) {
    $arr = json_decode($gejala_json, true);
    return is_array($arr) ? count($arr) : 0;
}
function getHasilDiagnosaUtama($hasil_json) {
    $arr = json_decode($hasil_json, true);
    return (is_array($arr) && !empty($arr)) ? ($arr[0]['penyakit'] ?? 'Tidak diketahui') : 'Tidak diketahui';
}
function formatTanggal($tanggal) { return date('d/m/Y H:i', strtotime($tanggal)); }

// Base URL untuk pagination (tetap di menu laporan)
$baseUrl = "index.php?page=laporan";
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Admin - Sistem Diagnosa</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<div class="bg-white rounded-xl shadow mx-3 my-2">
  <div class="p-4 border-b bg-gradient-to-r from-teal-50 to-teal-100 flex flex-col md:flex-row md:items-center md:justify-between">
    <h2 class="text-lg font-semibold text-teal-700"><i class="fas fa-clipboard-list mr-2"></i>Daftar Laporan Diagnosa Siswa</h2>
    <div class="flex items-center space-x-4 mt-3 md:mt-0">
      <div class="relative">
        <input id="searchInput" placeholder="Cari nama siswa atau diagnosa..."
          class="px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 w-64">
        <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
      </div>
      <button id="refreshBtn" onclick="location.reload()"
        class="px-4 py-2 bg-[#065084] text-white rounded-lg hover:bg-teal-700 flex items-center">
        <i class="fas fa-sync-alt mr-2"></i>Refresh
      </button>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full text-left">
      <thead class="bg-teal-600 text-white">
        <tr>
          <th class="py-3 px-4">No</th>
          <th class="py-3 px-4">Nama Siswa</th>
          <th class="py-3 px-4">Tanggal</th>
          <th class="py-3 px-4">Jumlah Gejala</th>
          <th class="py-3 px-4">Hasil Diagnosa</th>
          <th class="py-3 px-4">Aksi</th>
        </tr>
      </thead>
      <tbody id="tableBody" class="divide-y divide-gray-200">
      <?php if (empty($laporan_data)): ?>
        <tr><td colspan="6" class="py-8 text-center text-gray-500">Belum ada data laporan</td></tr>
      <?php else: foreach ($laporan_data as $i => $row): ?>
        <tr class="data-row">
          <td class="py-3 px-4"><?php echo $offset + $i + 1; ?></td>
          <td class="py-3 px-4"><span class="nama-siswa"><?php echo htmlspecialchars($row['nama_lengkap']); ?></span></td>
          <td class="py-3 px-4"><?php echo formatTanggal($row['tanggal']); ?></td>
          <td class="py-3 px-4"><?php echo hitungJumlahGejala($row['gejala_terpilih']); ?> Gejala</td>
          <td class="py-3 px-4">
            <?php
            $result = '';
            if (getHasilDiagnosaUtama($row['hasil_diagnosa']) === 'Pemakaian HP Berlebihan Berat') {
                $result = '<span class="bg-red-100 text-red-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">Pemakaian HP Berlebihan Berat</span>';
            } else if(getHasilDiagnosaUtama($row['hasil_diagnosa']) === 'Pemakaian HP Berlebihan Sedang') {
                $result = '<span class="bg-yellow-100 text-yellow-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">Pemakaian HP Berlebihan Sedang</span>';
            } else if(getHasilDiagnosaUtama($row['hasil_diagnosa']) === 'Pemakaian HP Berlebihan Ringan') {
                $result = '<span class="bg-green-100 text-green-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">Pemakaian HP Berlebihan Ringan</span>';
            } else {
                $result = '<span class="bg-gray-100 text-gray-800 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">' . htmlspecialchars(getHasilDiagnosaUtama($row['hasil_diagnosa'])) . '</span>';
            }
            ?>
          <span class="diagnosa-text"><?php echo $result; ?></span></td>
          <td class="py-3 px-4">
            <button href="<?php echo $base_url; ?>/report.php?id=<?php echo $row['id']; ?>" class="px-2 py-1 bg-[#065084] text-white rounded text-sm">Detail</button>
            <?php if ($row['pdf_filename']): ?>
              <!-- Form untuk generate PDF real-time -->
              <form action="<?php echo base_url('report.php'); ?>" method="POST" target="_blank" class="inline-block">
                <?php
                // Ambil data user lengkap untuk dikirim ke report.php
                $user_query = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $user_query->execute([$row['user_id']]);
                $user_data = $user_query->fetch(PDO::FETCH_ASSOC);
                
                // Decode data gejala dan hasil diagnosa dari JSON
                $gejala_terpilih = json_decode($row['gejala_terpilih'], true);
                $hasil_diagnosa = json_decode($row['hasil_diagnosa'], true);
                ?>
                
                <!-- Data user (harus sama dengan dashboard.php) -->
                <input type="hidden" name="user_data" value="<?php echo htmlspecialchars(json_encode($user_data)); ?>">
                
                <!-- Data gejala (harus sama dengan dashboard.php) -->
                <input type="hidden" name="gejala_terpilih" value="<?php echo htmlspecialchars(json_encode($gejala_terpilih)); ?>">
                
                <!-- Data hasil diagnosa (harus sama dengan dashboard.php) -->
                <input type="hidden" name="hasil_diagnosa" value="<?php echo htmlspecialchars(json_encode($hasil_diagnosa)); ?>">
                
                <button type="submit" class="px-2 py-1 bg-teal-600 text-white rounded text-sm">
                  PDF
                </button>
              </form>
            <?php endif; ?>
          </td> 
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div class="p-4 border-t">
    <div class="flex justify-center space-x-1">
      <?php if ($hal > 1): ?>
        <a href="<?php echo $baseUrl; ?>&hal=1" class="px-3 py-1 bg-gray-200 rounded">&laquo;</a>
        <a href="<?php echo $baseUrl; ?>&hal=<?php echo $hal-1; ?>" class="px-3 py-1 bg-gray-200 rounded">Prev</a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $hal): ?>
          <span class="px-3 py-1 bg-teal-600 text-white rounded"><?php echo $i; ?></span>
        <?php else: ?>
          <a href="<?php echo $baseUrl; ?>&hal=<?php echo $i; ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-teal-600 hover:text-white"><?php echo $i; ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($hal < $totalPages): ?>
        <a href="<?php echo $baseUrl; ?>&hal=<?php echo $hal+1; ?>" class="px-3 py-1 bg-gray-200 rounded">Next</a>
        <a href="<?php echo $baseUrl; ?>&hal=<?php echo $totalPages; ?>" class="px-3 py-1 bg-gray-200 rounded">&raquo;</a>
      <?php endif; ?>
    </div>
    <p class="text-center text-sm text-gray-600 mt-2">
      Menampilkan <?php echo ($offset+1); ?> - <?php echo min($offset+$limit,$totalData); ?> dari <?php echo $totalData; ?> data
    </p>
  </div>
</div>

<script>
// Searching
document.getElementById('searchInput').addEventListener('keyup', function() {
  const searchTerm = this.value.toLowerCase();
  const rows = document.querySelectorAll('.data-row');
  const noResults = document.getElementById('noResults');
  let visibleCount = 0;

  rows.forEach(function(row) {
    const nama = row.querySelector('.nama-siswa').textContent.toLowerCase();
    const diagnosa = row.querySelector('.diagnosa-text').textContent.toLowerCase();
    if (nama.includes(searchTerm) || diagnosa.includes(searchTerm)) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });
});
</script>

</body>
</html>
