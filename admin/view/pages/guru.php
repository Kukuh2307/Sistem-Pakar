<?php
session_start();
require_once __DIR__ . '/../../../config.php';
requireAdmin();

// --- Tambah User ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {
    try {
        $nama = trim($_POST['nama_lengkap']);
        $username = trim($_POST['username']);
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        $jk = $_POST['jenis_kelamin'];
        $umur = $_POST['umur'] ?: null;
        $status = trim($_POST['status']);
        $tgl = $_POST['tanggal_lahir'];
        $alamat = trim($_POST['alamat']);

        $insertQuery = "INSERT INTO users (nama_lengkap, username, password, jenis_kelamin, umur, status, tanggal_lahir, alamat) 
                        VALUES (?,?,?,?,?,?,?,?)";
        $stmt = $pdo->prepare($insertQuery);
        $stmt->execute([$nama, $username, $password, $jk, $umur, $status, $tgl, $alamat]);

        header("Location: ".$_SERVER['PHP_SELF']."?page=users&success=1");
        exit();
    } catch (PDOException $e) {
        $error = "Gagal menambahkan user: ".$e->getMessage();
    }
}

// --- Reset Password Admin ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    try {
        $id = $_POST['id'];
        $old_pass = $_POST['old_password'];
        $new_pass = $_POST['new_password'];
        $repeat_pass = $_POST['repeat_password'];

        // Ambil data admin
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND username='admin'");
        $stmt->execute([$id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin) {
            $error = "User admin tidak ditemukan.";
        } elseif (!password_verify($old_pass, $admin['password'])) {
            $error = "Password lama salah.";
        } elseif ($new_pass !== $repeat_pass) {
            $error = "Password baru tidak sama.";
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password=? WHERE id=? AND username='admin'");
            $update->execute([$hashed, $id]);

            header("Location: ".$_SERVER['PHP_SELF']."?page=users&success=4");
            exit();
        }
    } catch (PDOException $e) {
        $error = "Gagal reset password: ".$e->getMessage();
    }
}

// --- Update User ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    try {
        $id = $_POST['id'];
        $nama = trim($_POST['nama_lengkap']);
        $username = trim($_POST['username']);
        $jk = $_POST['jenis_kelamin'];
        $umur = $_POST['umur'] ?: null;
        $status = trim($_POST['status']);
        $tgl = $_POST['tanggal_lahir'];
        $alamat = trim($_POST['alamat']);

        if (!empty($_POST['password'])) {
            $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
            $updateQuery = "UPDATE users SET nama_lengkap=?, username=?, password=?, jenis_kelamin=?, umur=?, status=?, tanggal_lahir=?, alamat=? WHERE id=?";
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([$nama, $username, $password, $jk, $umur, $status, $tgl, $alamat, $id]);
        } else {
            $updateQuery = "UPDATE users SET nama_lengkap=?, username=?, jenis_kelamin=?, umur=?, status=?, tanggal_lahir=?, alamat=? WHERE id=?";
            $stmt = $pdo->prepare($updateQuery);
            $stmt->execute([$nama, $username, $jk, $umur, $status, $tgl, $alamat, $id]);
        }

        header("Location: ".$_SERVER['PHP_SELF']."?page=users&success=2");
        exit();
    } catch (PDOException $e) {
        $error = "Gagal mengupdate user: ".$e->getMessage();
    }
}

// --- Hapus User ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    try {
        $id = $_POST['id'];
        $deleteQuery = "DELETE FROM users WHERE id=? AND username!='admin'";
        $stmt = $pdo->prepare($deleteQuery);
        $stmt->execute([$id]);

        header("Location: ".$_SERVER['PHP_SELF']."?page=users&success=3");
        exit();
    } catch (PDOException $e) {
        $error = "Gagal menghapus user: ".$e->getMessage();
    }
}

// --- Pagination & Ambil Data ---
$limit = 8;
$hal = isset($_GET['hal']) ? (int)$_GET['hal'] : 1;
$hal = max($hal, 1);
$offset = ($hal-1)*$limit;

// $countQuery = "SELECT COUNT(*) as total FROM users WHERE username!='admin'";
$countQuery = "SELECT COUNT(*) as total FROM users";
$totalData = $pdo->query($countQuery)->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = max(1, ceil($totalData/$limit));

// $query = "SELECT * FROM users WHERE username!='admin' ORDER BY id DESC LIMIT $limit OFFSET $offset";
$query = "SELECT * FROM users ORDER BY id ASC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute();
$user_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseUrl = "container.php?page=users";

function formatTanggal($tgl){ return date('d/m/Y', strtotime($tgl)); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola User - Sistem Diagnosa</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
  .password-wrapper { position: relative; }
  .toggle-password {
    position: absolute;
    top: 50%; right: 10px;
    transform: translateY(-50%);
    cursor: pointer;
    color: #555;
  }
</style>
</head>
<body class="bg-gray-100">

<div class="bg-white rounded-xl shadow mx-3 mt-2 mb-20">
  <div class="p-4 border-b bg-gradient-to-r from-teal-50 to-teal-100 flex flex-col md:flex-row md:items-center md:justify-between">
    <h2 class="text-lg font-semibold text-teal-700"><i class="fas fa-users mr-2"></i>Daftar Guru</h2>
    <button onclick="openTambahModal()" class="px-4 py-2 bg-[#065084] text-white rounded-lg hover:bg-teal-700 mt-3 md:mt-0">
      <i class="fas fa-plus mr-2"></i> Tambah Guru
    </button>
  </div>

  <!-- Notifikasi -->
  <?php if(isset($_GET['success'])): ?>
    <div id="notifBox" class="m-4 p-3 rounded 
      <?php echo $_GET['success']==1?'bg-green-100 text-green-700':
               ($_GET['success']==2?'bg-teal-100 text-teal-700':'bg-red-100 text-red-700'); ?>">
      <?php echo $_GET['success']==1?'Guru berhasil ditambahkan':
               ($_GET['success']==2?'Guru berhasil diupdate':
               ($_GET['success']==3?'Guru berhasil dihapus':'Password Admin berhasil direset')); ?>
    </div>
  <?php endif; ?>
  <?php if(isset($error)): ?><div id="notifBox" class="m-4 p-3 rounded bg-red-100 text-red-700"><?php echo $error; ?></div><?php endif; ?>

  <!-- Table Desktop -->
  <div class="overflow-x-auto hidden md:block">
    <table class="min-w-full text-left">
      <thead class="bg-teal-600 text-white">
        <tr>
          <th class="py-3 px-4">No</th>
          <th class="py-3 px-4">Nama</th>
          <th class="py-3 px-4">Jenis Kelamin</th>
          <th class="py-3 px-4">Umur</th>
          <th class="py-3 px-4">Tanggal Lahir</th>
          <th class="py-3 px-4">Alamat</th>
          <th class="py-3 px-4">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-200">
        <?php if(empty($user_data)): ?>
          <tr><td colspan="8" class="py-8 text-center text-gray-500">Belum ada user</td></tr>
        <?php else: foreach($user_data as $i=>$row): ?>
          <tr>
            <td class="py-3 px-4"><?php echo $offset+$i+1; ?></td>
                      <td class="py-3 px-4 flex items-center">
            <div class="w-8 h-8 bg-teal-100 rounded-full flex items-center justify-center mr-3">
              <i class="fas fa-user text-teal-600 text-xs"></i>
            </div>  
          <span class="nama-siswa"><?php echo htmlspecialchars($row['nama_lengkap']); ?></span>
          </td>
            <td class="py-3 px-4"><?php echo ucfirst($row['jenis_kelamin']); ?></td>
            <td class="py-3 px-4"><?php echo $row['umur']??'-'; ?></td>
            <td class="py-3 px-4"><?php echo formatTanggal($row['tanggal_lahir']); ?></td>
            <td class="py-3 px-4"><?php echo htmlspecialchars($row['alamat']); ?></td>
            <td class="py-3 px-4 flex space-x-2">
              <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="px-2 py-1 bg-yellow-600 text-white rounded text-sm">Edit</button>
              <?php if($row['username']=='admin'): ?><button onclick="openResetModal('<?php echo $row['id']; ?>')" class="px-2 py-1 bg-teal-600 text-white rounded text-sm">Reset Password</button><?php endif; ?>
              <button style="<?php if($row['username']=='admin'): ?>display:none;<?php endif; ?>" onclick="openDeleteModal('<?php echo $row['id']; ?>')" class="px-2 py-1 bg-red-600 text-white rounded text-sm">Hapus</button>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <!-- Mobile Cards -->
<div class="md:hidden p-2">
  <?php if (empty($user_data)): ?>
    <div class="py-8 text-center text-gray-500">Belum ada user</div>
  <?php else: foreach ($user_data as $i => $row): ?>
    <div class="border rounded-lg p-4 mb-3 bg-white shadow">
      <div class="mb-2"><strong>No:</strong> <?php echo $offset + $i + 1; ?></div>
      <div class="mb-2"><strong>Nama:</strong> <?php echo htmlspecialchars($row['nama_lengkap']); ?></div>
      <div class="mb-2"><strong>Username:</strong> <?php echo htmlspecialchars($row['username']); ?></div>
      <div class="mb-2"><strong>Jenis Kelamin:</strong> <?php echo ucfirst($row['jenis_kelamin']); ?></div>
      <div class="mb-2"><strong>Umur:</strong> <?php echo $row['umur'] ?? '-'; ?></div>
      <div class="mb-2"><strong>Tanggal Lahir:</strong> <?php echo formatTanggal($row['tanggal_lahir']); ?></div>
      <div class="mb-2"><strong>Alamat:</strong> <?php echo htmlspecialchars($row['alamat']); ?></div>
      <div class="flex space-x-2 mt-3">
        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
                class="flex-1 px-3 py-2 bg-yellow-600 text-white rounded text-sm">Edit</button>
        <?php if($row['username']=='admin'): ?><button onclick="openResetModal('<?php echo $row['id']; ?>')" 
                class="flex-1 px-3 py-2 bg-teal-600 text-white rounded text-sm">Reset Password</button><?php endif; ?>
        <button style="<?php if($row['username']=='admin') : ?>display:none;<?php endif; ?>" onclick="openDeleteModal('<?php echo $row['id']; ?>')" 
                class="flex-1 px-3 py-2 bg-red-600 text-white rounded text-sm">Hapus</button>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>


  <!-- Pagination -->
  <div class="p-4 border-t flex justify-between">
    <p>Menampilkan <?php echo $offset+1; ?> - <?php echo min($offset+$limit,$totalData); ?> dari <?php echo $totalData; ?> user</p>
    <div>
      <?php if($hal>1): ?><a href="<?php echo $baseUrl; ?>&hal=<?php echo $hal-1; ?>" class="px-2">Prev</a><?php endif; ?>
      <?php if($hal<$totalPages): ?><a href="<?php echo $baseUrl; ?>&hal=<?php echo $hal+1; ?>" class="px-2">Next</a><?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal Tambah -->
<div id="tambahModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex justify-center items-center">
  <div class="bg-white p-6 rounded w-full max-w-md">
    <h3 class="mb-4 font-bold">Tambah Guru</h3>
    <form method="POST">
      <input type="text" name="nama_lengkap" placeholder="Nama Lengkap" class="w-full border p-2 mb-2" required>
      <input type="text" name="username" placeholder="Username" class="w-full border p-2 mb-2" required>
      <div class="password-wrapper mb-2">
        <input type="password" name="password" placeholder="Password" class="w-full border p-2 pr-10" id="tambah_password" required>
        <i class="fas fa-eye toggle-password" onclick="togglePassword('tambah_password', this)"></i>
      </div>
      <select name="jenis_kelamin" class="w-full border p-2 mb-2" required>
        <option value="laki-laki">Laki-laki</option>
        <option value="perempuan">Perempuan</option>
      </select>
      <input type="number" name="umur" placeholder="Umur" class="w-full border p-2 mb-2">
      <input type="date" name="tanggal_lahir" class="w-full border p-2 mb-2" required>
      <textarea name="alamat" placeholder="Alamat" class="w-full border p-2 mb-2"></textarea>
      <textarea name="status" placeholder="Status" class="w-full border p-2 mb-2"></textarea>
      <div class="flex justify-end space-x-2">
        <button type="button" onclick="closeTambahModal()" class="px-4 py-2 bg-gray-300 rounded">Batal</button>
        <button type="submit" name="tambah_user" class="px-4 py-2 bg-teal-600 text-white rounded">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Reset Password -->
<div id="resetModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex justify-center items-center">
  <div class="bg-white p-6 rounded w-full max-w-md">
    <h3 class="mb-4 font-bold">Reset Password Admin</h3>
    <form method="POST">
      <input type="hidden" name="id" id="reset_id">
      
      <!-- Password Lama -->
      <div class="mb-3 relative">
        <label class="block text-sm">Password Lama</label>
        <input type="password" name="old_password" id="reset_old" class="w-full border p-2 rounded pr-10" required>
        <button type="button" onclick="togglePassword('reset_old')" class="absolute right-2 top-7 text-gray-500">
          <i class="fas fa-eye"></i>
        </button>
      </div>

      <!-- Password Baru -->
      <div class="mb-3 relative">
        <label class="block text-sm">Password Baru</label>
        <input type="password" name="new_password" id="reset_new" class="w-full border p-2 rounded pr-10" required>
        <button type="button" onclick="togglePassword('reset_new')" class="absolute right-2 top-7 text-gray-500">
          <i class="fas fa-eye"></i>
        </button>
      </div>

      <!-- Ulangi Password Baru -->
      <div class="mb-3 relative">
        <label class="block text-sm">Ulangi Password Baru</label>
        <input type="password" name="repeat_password" id="reset_repeat" class="w-full border p-2 rounded pr-10" required>
        <button type="button" onclick="togglePassword('reset_repeat')" class="absolute right-2 top-7 text-gray-500">
          <i class="fas fa-eye"></i>
        </button>
      </div>

      <div class="flex justify-end space-x-2">
        <button type="button" onclick="closeResetModal()" class="px-4 py-2 bg-gray-300 rounded">Batal</button>
        <button type="submit" name="reset_password" class="px-4 py-2 bg-teal-600 text-white rounded">Reset</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Delete -->
<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex justify-center items-center">
  <div class="bg-white p-6 rounded w-full max-w-md">
    <h3 class="mb-4 font-bold">Hapus Guru</h3>
    <p class="mb-4">Yakin ingin menghapus guru ini?</p>
    <form method="POST">
      <input type="hidden" name="id" id="delete_id">
      <div class="flex justify-end space-x-2">
        <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 rounded">Batal</button>
        <button type="submit" name="delete_user" class="px-4 py-2 bg-red-600 text-white rounded">Hapus</button>
      </div>
    </form>
  </div>
</div>

<script>
function openTambahModal(){ document.getElementById('tambahModal').classList.remove('hidden'); }
function closeTambahModal(){ document.getElementById('tambahModal').classList.add('hidden'); }
function openEditModal(data){
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_nama').value = data.nama_lengkap;
  document.getElementById('edit_username').value = data.username;
  document.getElementById('edit_jk').value = data.jenis_kelamin;
  document.getElementById('edit_umur').value = data.umur;
  document.getElementById('edit_tgl').value = data.tanggal_lahir;
  document.getElementById('edit_alamat').value = data.alamat;
  document.getElementById('edit_status').value = data.status;
  document.getElementById('edit_password').value = "";
  document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal(){ document.getElementById('editModal').classList.add('hidden'); }
function openDeleteModal(id){ document.getElementById('delete_id').value=id; document.getElementById('deleteModal').classList.remove('hidden'); }
function closeDeleteModal(){ document.getElementById('deleteModal').classList.add('hidden'); }

function togglePassword(id) {
  const input = document.getElementById(id);
  const icon = input.nextElementSibling.querySelector("i");
  if (input.type === "password") {
    input.type = "text";
    icon.classList.remove("fa-eye");
    icon.classList.add("fa-eye-slash");
  } else {
    input.type = "password";
    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
  }
} 

// Toggle lihat password
function togglePassword(inputId, icon){
  const input = document.getElementById(inputId);
  if(input.type === "password"){
    input.type = "text";
    icon.classList.remove("fa-eye");
    icon.classList.add("fa-eye-slash");
  } else {
    input.type = "password";
    icon.classList.remove("fa-eye-slash");
    icon.classList.add("fa-eye");
  }
}

// Auto-hide notif
setTimeout(()=>{
  const notif = document.getElementById('notifBox');
  if(notif){
    notif.style.transition = "opacity 0.5s ease";
    notif.style.opacity = "0";
    setTimeout(()=>notif.remove(), 500);
  }
},3000);

function openResetModal(id){
  document.getElementById('reset_id').value = id;
  document.getElementById('resetModal').classList.remove('hidden');
}
function closeResetModal(){
  document.getElementById('resetModal').classList.add('hidden');
}

// Auto close notifikasi 3 detik
setTimeout(() => {
  const notif = document.querySelector('.m-4.p-3.rounded');
  if (notif) {
    notif.style.opacity = '0';
    setTimeout(() => notif.remove(), 500);
  }
}, 3000);
</script>
</body>
</html>
