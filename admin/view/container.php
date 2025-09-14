<?php
session_start();
require_once __DIR__ . '/../../config.php';
requireAdmin();
// Default page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Validasi halaman yang diizinkan
$allowed_pages = ['dashboard', 'laporan', 'diagnosa', 'pengaturan'];
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Set current page untuk navigation highlight
$current_page = $page;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin</title>
      <link rel="shortcut icon" type="image/x-icon" href="<?php echo base_url('img/unp.jpeg') ?>">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex flex-col min-h-screen">

  <!-- Navbar -->
  <?php require_once __DIR__ . '/partials/navbar.php'; ?>

  <div class="flex flex-1">
      <!-- Sidebar -->
      <aside id="sidebar" 
        class="bg-white w-64 fixed inset-y-0 left-0 z-50 transform -translate-x-full md:translate-x-0 md:relative md:block shadow-lg transition-transform duration-300 ease-in-out">
        <div class="p-4 space-y-4 flex flex-col justify-between h-full">
          <?php include __DIR__ . '/partials/sidebar.php'; ?>
          <div class="flex pl-4 items-center hover:bg-teal-100 text-gray-700 border-t-4 border-teal-600 <?php echo ($current_page == 'logout') ? 'bg-teal-100 font-medium' : ''; ?>">
              <i class="fa-solid fa-right-from-bracket"></i>
              <a href="<?php echo base_url('admin/view/logout.php') ?>" class="block px-3 py-2 rounded-lg">Logout</a>
          </div>
        </div>
      </aside>

      <!-- Overlay untuk mobile -->
      <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40 md:hidden"></div>

      <!-- Konten Utama -->
      <main class="flex-1 transition-all duration-300">
                  <?php
          switch ($page) {
              case 'dashboard':
                  include 'pages/dashboard.php';
                  break;
              case 'laporan':
                  include 'pages/laporan.php';
                  break;
              case 'diagnosa':
                  include 'pages/diagnosa.php';
                  break;
              case 'pengaturan':
                  include 'pages/pengaturan.php';
                  break;
              default:
                  include 'pages/dashboard.php';
                  break;
          }
          ?>
        <?php require_once __DIR__ . '/partials/footer.php'; ?>
      </main>
  </div>

  <script>
    const menuBtn = document.getElementById('menuBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if(menuBtn){
      menuBtn.addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
      });
    }

    // Tutup sidebar jika klik overlay
    if(overlay){
      overlay.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
      });
    }
  </script>
</body>
</html>
