<?php
require_once 'config.php';
requireGuest();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            redirect('dashboard.php');
        } else {
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - User Management</title>
  <link rel="shortcut icon" type="image/x-icon" href="img/unp.jpeg">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-blue-50 to-green-100 to-teal-100">

<div class="w-full max-w-5xl rounded-2xl shadow-2xl overflow-hidden grid md:grid-cols-2">
  <!-- Bagian Kiri (Tagline / Ilustrasi) -->
<div class="hidden md:flex bg-gradient-to-br from-teal-600 to-[#065084] text-white flex-col items-center justify-center p-8">
    <div class="text-center space-y-4 max-w-md">
      <h2 class="text-lg font-semibold">PKM-KM</h2>
      <p class="text-xl font-bold leading-relaxed">
        "Edukasi Digital Sehat bagi Anak Berkebutuhan Khusus"
      </p>
      <p class="text-md opacity-90 leading-relaxed">
        Implementasi Certainty Factor dalam Mendukung<br>
        Sekolah Dasar Inklusi Ramah Anak di Kota Kediri
      </p>
    </div>
  </div>
  <!-- Bagian Kanan (Form Login) -->
  <div class="p-8 flex flex-col justify-center pb-20 bg-white">
    <div class="text-center mb-6">
      <img src="img/logo.jpeg" alt="Logo" class="mx-auto w-38 h-28 object-contain">
      <h1 class="text-2xl font-bold text-gray-800">Selamat Datang</h1>
      <p class="text-gray-600 text-sm">Silakan login ke akun Anda</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <!-- Username -->
      <div>
        <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
        <input type="text" id="username" name="username"
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent transition"
               placeholder="Masukkan username Anda" required>
      </div>

      <!-- Password -->
      <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <div class="relative">
          <input type="password" id="password" name="password"
                 class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-transparent transition pr-12"
                 placeholder="Masukkan password Anda" required>
          <button type="button" id="togglePassword" tabindex="-1"
                  class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 focus:outline-none">
            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
        </div>
      </div>

      <button type="submit"
              class="w-full bg-teal-600 text-white py-3 px-6 rounded-lg hover:bg-teal-700 focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 font-medium">
        Login
      </button>
    </form>

    <p class="text-md font-medium mt-10 text-center">
      Hibah PKM-Kemitraan Kemendikbudristek <br> Universitas PGRI Kediri <br> 2025
    </p>
  </div>
</div>

<script>
  // Toggle password
  document.getElementById("togglePassword").addEventListener("click", function () {
    const pwd = document.getElementById("password");
    const eye = document.getElementById("eyeIcon");
    if (pwd.type === "password") {
      pwd.type = "text";
      eye.setAttribute("stroke", "yellow");
    } else {
      pwd.type = "password";
      eye.setAttribute("stroke", "currentColor");
    }
  });
</script>

</body>
</html>
