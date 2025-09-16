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
  <style>
    .animate-fadeIn {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
  </style>
</head>
<body class="bg-white min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-blue-50 to-green-100 to-teal-100">
<!-- Modal Popup -->
<div id="welcomeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl p-6 max-w-md w-full mx-4 animate-fadeIn overflow-hidden border-teal-600 border-4">
        <!-- Header -->
        <div class="text-center mb-6">
            <div class="w-16 h-16 p-2 bg-teal-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path style="fill:#008080" d="M598.1 139.4C608.8 131.6 611.2 116.6 603.4 105.9C595.6 95.2 580.6 92.8 569.9 100.6L495.4 154.8L485.5 148.2C465.8 135 442.6 128 418.9 128L359.7 128L359.3 128L215.7 128C189 128 163.2 136.9 142.3 153.1L70.1 100.6C59.4 92.8 44.4 95.2 36.6 105.9C28.8 116.6 31.2 131.6 41.9 139.4L129.9 203.4C139.5 210.3 152.6 209.3 161 201L164.9 197.1C178.4 183.6 196.7 176 215.8 176L262.1 176L170.4 267.7C154.8 283.3 154.8 308.6 170.4 324.3L171.2 325.1C218 372 294 372 340.9 325.1L368 298L465.8 395.8C481.4 411.4 481.4 436.7 465.8 452.4L456 462.2L425 431.2C415.6 421.8 400.4 421.8 391.1 431.2C381.8 440.6 381.7 455.8 391.1 465.1L419.1 493.1C401.6 503.5 381.9 509.8 361.5 511.6L313 463C303.6 453.6 288.4 453.6 279.1 463C269.8 472.4 269.7 487.6 279.1 496.9L294.1 511.9L290.3 511.9C254.2 511.9 219.6 497.6 194.1 472.1L65 343C55.6 333.6 40.4 333.6 31.1 343C21.8 352.4 21.7 367.6 31.1 376.9L160.2 506.1C194.7 540.6 241.5 560 290.3 560L342.1 560L343.1 561L344.1 560L349.8 560C398.6 560 445.4 540.6 479.9 506.1L499.8 486.2C501 485 502.1 483.9 503.2 482.7C503.9 482.2 504.5 481.6 505.1 481L609 377C618.4 367.6 618.4 352.4 609 343.1C599.6 333.8 584.4 333.7 575.1 343.1L521.3 396.9C517.1 384.1 510 372 499.8 361.8L385 247C375.6 237.6 360.4 237.6 351.1 247L307 291.1C280.5 317.6 238.5 319.1 210.3 295.7L309 197C322.4 183.6 340.6 176 359.6 175.9L368.1 175.9L368.3 175.9L419.1 175.9C433.3 175.9 447.2 180.1 459 188L482.7 204C491.1 209.6 502 209.3 510.1 203.4L598.1 139.4z"/></svg>
            </div>
            <h2 class="text-2xl font-bold text-teal-700 mb-2">Terima Kasih!</h2>
            <p class="text-gray-600">Terima kasih telah mengunjungi website kami.</p>
            <p class="text-gray-600">Silakan login untuk melanjutkan.</p>
        </div>

        <!-- Content -->
        <div class="border-t border-b border-gray-200 py-4 mb-6">
            <div class="text-center mb-4">
                <p class="text-sm text-gray-700 font-medium font-semibold mb-2">Terima kasih kepada:</p>
                
                <div class="space-y-3">
                    <div>
                        <p class="text-lg font-semibold">Kementerian Pendidikan, Kebudayaan, Riset, dan Teknologi</p>
                    </div>
                    
                    <div>
                        <p class="text-md font-medium">Direktorat Jenderal Pendidikan Tinggi, Riset, dan Teknologi</p>
                        <p class="text-sm text-gray-600">(Kemdiktisaintek)</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-700">atas dukungan pendanaan</p>
                        <p class="text-md font-medium">DPPM – Program Kemitraan kepada Masyarakat (PKM)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center">
            <p class="text-lg font-semibold mb-2">Universitas Nusantara PGRI Kediri</p>
            <!-- <div class="flex justify-center space-x-4 mb-6">
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <span class="text-red-700 font-bold text-sm">UN</span>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <span class="text-blue-700 font-bold text-sm">PGRI</span>
                </div>
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <span class="text-yellow-700 font-bold text-sm">KD</span>
                </div>
            </div> -->
        </div>

        <!-- Button -->
        <div class="flex justify-center">
            <button onclick="closeModal()" class="bg-teal-600 text-white px-6 py-2 rounded-lg hover:bg-teal-700 transition-colors duration-300 font-medium shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-transform">
                Tutup
            </button>
        </div>
    </div>
</div>
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
      Hibah PKM-Kemitraan Kemendikbudristek <br> Universitas Nusantara PGRI Kediri <br> 2025
    </p>
  </div>
</div>

<script>
          // Animasi sederhana
        document.head.insertAdjacentHTML("beforeend", `
            <style>
                @keyframes fadeIn {
                    from {opacity: 0; transform: translateY(20px);}
                    to {opacity: 1; transform: translateY(0);}
                }
                .animate-fadeIn { animation: fadeIn 0.4s ease-in-out; }
            </style>
        `);

        // Munculkan modal saat pertama kali buka
        window.addEventListener("DOMContentLoaded", function() {
            const modal = document.getElementById("welcomeModal");

            // Cek apakah sudah pernah ditampilkan
            if (!sessionStorage.getItem("welcomeShown")) {
                modal.classList.remove("hidden");
                modal.classList.add("flex");

                // Auto close setelah 5 detik
                setTimeout(() => {
                    closeModal();
                }, 50000);

                // Simpan state agar tidak muncul lagi saat reload
                // sessionStorage.setItem("welcomeShown", "true");
            }
        });

        function closeModal() {
            const modal = document.getElementById("welcomeModal");
            modal.classList.add("hidden");
        }
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
