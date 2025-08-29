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
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-green-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <img src="img/logo.jpeg" alt="Logo" class="mx-auto w-54 h-32 object-contain">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Selamat Datang</h1>
                <p class="text-gray-600">Silakan login ke akun Anda</p>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" id="username" name="username" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                           placeholder="Masukkan username Anda" required>
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" id="password" name="password" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-colors"
                           placeholder="Masukkan password Anda" required>
                </div>
                
                <button type="submit" 
                        class="w-full bg-teal-600 text-white py-3 px-6 rounded-lg hover:bg-teal-700 focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 transition-colors font-medium">
                    Login
                </button>
            </form>
            
            <div class="text-center mt-6">
                <p class="text-gray-600">
                    Belum punya akun? 
                    <a href="register.php" class="text-teal-600 hover:text-teal-700 font-medium">Daftar di sini</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>