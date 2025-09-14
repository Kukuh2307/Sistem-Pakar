<?php
session_start();
require_once __DIR__ . '/../../../config.php';
requireAdmin();

// Tambah data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_penyakit'])) {
    try {
        $kategori = trim($_POST['kategori']);
        $rekomendasi = trim($_POST['rekomendasi']);
        $nama_penyakit = $kategori . ' ||REKOMENDASI|| Rekomendasi: ' . $rekomendasi . '||';
        $id_penyakit = trim($_POST['id_penyakit']);

        $insertQuery = "INSERT INTO penyakit (id_penyakit, nama_penyakit) VALUES (?, ?)";
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->execute([$id_penyakit, $nama_penyakit]);

        header("Location: " . $_SERVER['PHP_SELF'] . "?page=diagnosa&success=1");
        exit();
    } catch (PDOException $e) {
        $error = "Gagal menambahkan data: " . $e->getMessage();
    }
}

// Update data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_penyakit'])) {
    try {
        $id_penyakit = trim($_POST['id_penyakit']);
        $kategori = trim($_POST['kategori']);
        $rekomendasi = trim($_POST['rekomendasi']);
        $nama_penyakit = $kategori . ' ||REKOMENDASI|| Rekomendasi: ' . $rekomendasi . '||';

        $updateQuery = "UPDATE penyakit SET nama_penyakit = ? WHERE id_penyakit = ?";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([$nama_penyakit, $id_penyakit]);

        header("Location: " . $_SERVER['PHP_SELF'] . "?page=diagnosa&success=2");
        exit();
    } catch (PDOException $e) {
        $error = "Gagal mengupdate data: " . $e->getMessage();
    }
}

// Delete data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_penyakit'])) {
    try {
        $id_penyakit = trim($_POST['id_penyakit']);
        $deleteQuery = "DELETE FROM penyakit WHERE id_penyakit = ?";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute([$id_penyakit]);

        header("Location: " . $_SERVER['PHP_SELF'] . "?page=diagnosa&success=3");
        exit();
    } catch (PDOException $e) {
        $error = "Gagal menghapus data: " . $e->getMessage();
    }
}

// Konfigurasi pagination
$limit = 5;
$hal = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
$hal = max($hal, 1);
$offset = ($hal - 1) * $limit;

// Hitung total data
$countQuery = "SELECT COUNT(*) as total FROM penyakit";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute();
$totalData = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = max(1, ceil($totalData / $limit));

// Query data penyakit
$query = "
SELECT
  id_penyakit,
  CASE
    WHEN nama_penyakit LIKE '%||%||%' THEN TRIM(SUBSTRING_INDEX(nama_penyakit, '||', 1))
    ELSE TRIM(nama_penyakit)
  END AS kategori,
  CASE
    WHEN nama_penyakit LIKE '%||%||%' THEN
      TRIM(BOTH '|' FROM
        CASE
          WHEN LOWER(LEFT(
                 TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(nama_penyakit, '||', 3), '||', -1)),
                 CHAR_LENGTH('Rekomendasi:')
               )) = LOWER('Rekomendasi:')
          THEN TRIM(SUBSTRING(
                 TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(nama_penyakit, '||', 3), '||', -1)),
                 CHAR_LENGTH('Rekomendasi:') + 1
               ))
          ELSE TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(nama_penyakit, '||', 3), '||', -1))
        END
      )
    ELSE NULL
  END AS rekomendasi
FROM penyakit
ORDER BY id_penyakit
LIMIT $limit OFFSET $offset;
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$penyakit_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = "container.php?page=diagnosa";
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Daftar Penyakit - Sistem Diagnosa</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<div class="bg-white rounded-xl shadow mx-3 mt-2 mb-20">
  <div class="p-4 border-b bg-gradient-to-r from-teal-50 to-teal-100 flex flex-col md:flex-row md:items-center md:justify-between">
    <h2 class="text-lg font-semibold text-teal-700">
      <i class="fas fa-virus mr-2"></i> Daftar Diagnosa Penyakit dan Rekomendasi
    </h2>
    <button onclick="openTambahModal()" class="mt-3 md:mt-0 px-4 py-2 bg-[#065084] text-white rounded-lg hover:bg-teal-700 flex items-center">
      <i class="fas fa-plus mr-2"></i> Tambah Data
    </button>
  </div>

  <!-- Notifikasi -->
  <?php if (isset($_GET['success'])): ?>
    <?php if ($_GET['success'] == 1): ?>
      <div id="successNotification" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative m-4">Data diagnosa berhasil ditambahkan.</div>
    <?php elseif ($_GET['success'] == 2): ?>
      <div id="successNotification" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative m-4">Data diagnosa berhasil diupdate.</div>
    <?php elseif ($_GET['success'] == 3): ?>
      <div id="successNotification" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative m-4">Data diagnosa berhasil dihapus.</div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (isset($error)): ?>
    <div id="errorNotification" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative m-4"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <!-- Tabel Desktop -->
  <div class="overflow-x-auto hidden md:block">
    <table class="min-w-full text-left">
      <thead class="bg-teal-600 text-white">
        <tr>
          <th class="py-3 px-4">No</th>
          <th class="py-3 px-4">Kategori</th>
          <th class="py-3 px-4">Rekomendasi</th>
          <th class="py-3 px-4">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php if (empty($penyakit_data)): ?>
          <tr><td colspan="4" class="py-8 text-center text-gray-500">Belum ada data</td></tr>
        <?php else: foreach ($penyakit_data as $i => $row): ?>
          <tr>
            <td class="py-3 px-4"><?php echo $offset + $i + 1; ?></td>
            <td class="py-3 px-4 font-medium text-gray-800"><?php echo htmlspecialchars($row['kategori']); ?></td>
            <td class="py-3 px-4 text-gray-700"><?php echo htmlspecialchars($row['rekomendasi']); ?></td>
            <td class="py-3 px-4 flex space-x-2">
              <button onclick="openEditModal('<?php echo $row['id_penyakit']; ?>','<?php echo htmlspecialchars($row['kategori'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($row['rekomendasi'], ENT_QUOTES); ?>')" class="px-2 py-1 bg-yellow-600 text-white rounded text-sm">Edit</button>
              <button onclick="openDeleteModal('<?php echo $row['id_penyakit']; ?>')" class="px-2 py-1 bg-red-600 text-white rounded text-sm">Hapus</button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Mobile Cards -->
  <div class="md:hidden">
    <?php if (empty($penyakit_data)): ?>
      <div class="py-8 text-center text-gray-500">Belum ada data</div>
    <?php else: foreach ($penyakit_data as $i => $row): ?>
      <div class="mobile-card border rounded p-4 mb-3 bg-white">
        <div class="mb-2"><strong>No:</strong> <?php echo $offset + $i + 1; ?></div>
        <div class="mb-2"><strong>Kategori:</strong> <?php echo htmlspecialchars($row['kategori']); ?></div>
        <div class="mb-2"><strong>Rekomendasi:</strong> <?php echo htmlspecialchars($row['rekomendasi']); ?></div>
        <div class="flex space-x-2 mt-2">
          <button onclick="openEditModal('<?php echo $row['id_penyakit']; ?>','<?php echo htmlspecialchars($row['kategori'], ENT_QUOTES); ?>','<?php echo htmlspecialchars($row['rekomendasi'], ENT_QUOTES); ?>')" class="flex-1 px-2 py-1 bg-yellow-600 text-white rounded text-sm">Edit</button>
          <button onclick="openDeleteModal('<?php echo $row['id_penyakit']; ?>')" class="flex-1 px-2 py-1 bg-red-600 text-white rounded text-sm">Hapus</button>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Pagination -->
  <div class="p-4 border-t">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
      <p class="text-center md:text-left text-sm text-gray-600 mb-2 md:mb-0">
        Menampilkan <?php echo ($offset+1); ?> - <?php echo min($offset+$limit,$totalData); ?> dari <?php echo $totalData; ?> data
      </p>
      <div class="flex justify-center space-x-1 flex-wrap">
        <?php if ($hal > 1): ?>
          <a href="<?php echo $baseUrl; ?>&hal=1" class="px-3 py-1 bg-gray-200 rounded text-sm">&laquo;</a>
          <a href="<?php echo $baseUrl; ?>&hal=<?php echo $hal-1; ?>" class="px-3 py-1 bg-gray-200 rounded text-sm">Prev</a>
        <?php endif; ?>

        <?php 
        $start_page = max(1, $hal - 2);
        $end_page = min($totalPages, $start_page + 4);
        if ($end_page - $start_page < 4) {
            $start_page = max(1, $end_page - 4);
        }
        for ($i = $start_page; $i <= $end_page; $i++): 
        ?>
          <?php if ($i == $hal): ?>
            <span class="px-3 py-1 bg-teal-600 text-white rounded text-sm"><?php echo $i; ?></span>
          <?php else: ?>
            <a href="<?php echo $baseUrl; ?>&hal=<?php echo $i; ?>" class="px-3 py-1 bg-gray-200 rounded text-sm hover:bg-teal-600 hover:text-white"><?php echo $i; ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($hal < $totalPages): ?>
          <a href="<?php echo $baseUrl; ?>&hal=<?php echo $hal+1; ?>" class="px-3 py-1 bg-gray-200 rounded text-sm">Next</a>
          <a href="<?php echo $baseUrl; ?>&hal=<?php echo $totalPages; ?>" class="px-3 py-1 bg-gray-200 rounded text-sm">&raquo;</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal Tambah -->
<div id="tambahModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
  <div class="bg-white rounded-lg max-w-md w-full p-6">
    <h3 class="text-lg mb-4">Tambah Data Penyakit</h3>
    <form method="POST">
      <label class="block mb-1">ID Penyakit</label>
      <input type="text" name="id_penyakit" placeholder="Contoh: P04" required class="w-full mb-3 border p-2 rounded">
      <label class="block mb-1">Kategori</label>
      <select name="kategori" required class="w-full mb-3 border p-2 rounded">
        <option value="">Pilih Kategori</option>
        <option value="Pemakaian HP Berlebihan Ringan">Ringan</option>
        <option value="Pemakaian HP Berlebihan Sedang">Sedang</option>
        <option value="Pemakaian HP Berlebihan Berat">Berat</option>
      </select>
      <textarea name="rekomendasi" rows="3" placeholder="Rekomendasi" required class="w-full mb-3 border p-2 rounded"></textarea>
      <div class="flex justify-end space-x-2">
        <button type="button" onclick="closeTambahModal()" class="px-4 py-2 bg-gray-300 rounded">Batal</button>
        <button type="submit" name="tambah_penyakit" class="px-4 py-2 bg-teal-600 text-white rounded">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit -->
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
  <div class="bg-white rounded-lg max-w-md w-full p-6">
    <h3 class="text-lg mb-4">Edit Data Penyakit</h3>
    <form method="POST">
      <input type="hidden" name="id_penyakit" id="edit_id">
      <label class="block mb-1">Kategori</label>
      <select name="kategori" required class="w-full mb-3 border p-2 rounded" id="edit_kategori">
        <option value="Pemakaian HP Berlebihan Ringan">Ringan</option>
        <option value="Pemakaian HP Berlebihan Sedang">Sedang</option>
        <option value="Pemakaian HP Berlebihan Berat">Berat</option>
      </select>
      <textarea name="rekomendasi" id="edit_rekomendasi" rows="3" required class="w-full mb-3 border p-2 rounded"></textarea>
      <div class="flex justify-end space-x-2">
        <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 rounded">Batal</button>
        <button type="submit" name="update_penyakit" class="px-4 py-2 bg-teal-600 text-white rounded">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Delete -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
  <div class="bg-white rounded-lg max-w-md w-full p-6">
    <h3 class="text-lg mb-4">Konfirmasi Hapus</h3>
    <p class="mb-4">Apakah Anda yakin ingin menghapus data ini?</p>
    <form method="POST">
      <input type="hidden" name="id_penyakit" id="delete_id">
      <div class="flex justify-end space-x-2">
        <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 rounded">Batal</button>
        <button type="submit" name="delete_penyakit" class="px-4 py-2 bg-red-600 text-white rounded">Hapus</button>
      </div>
    </form>
  </div>
</div>

<script>
function openTambahModal() { document.getElementById('tambahModal').classList.remove('hidden'); }
function closeTambahModal() { document.getElementById('tambahModal').classList.add('hidden'); }

function openEditModal(id,kategori,rekomendasi) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_kategori').value = kategori;
  document.getElementById('edit_rekomendasi').value = rekomendasi;
  document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }

function openDeleteModal(id) {
  document.getElementById('delete_id').value = id;
  document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.add('hidden'); }

// Auto close notif
setTimeout(() => {
  const notif = document.getElementById('successNotification');
  if (notif) { notif.style.opacity='0'; setTimeout(()=>notif.remove(),500); }
}, 3000);
</script>

</body>
</html>
