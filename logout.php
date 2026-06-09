<?php
// 1. Memulai atau mengaktifkan sesi yang sedang berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Menghapus semua variabel sesi yang terdaftar
$_SESSION = array();

// 3. Jika sistem menggunakan cookie untuk session (standar PHP), hapus cookie-nya juga
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 4. Hancurkan/destroy sesi sepenuhnya dari memori server
session_destroy();

// 5. Hapus Cookie Kustom (Stabil di Vercel)
setcookie('auth_role', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
setcookie('wali_id', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// 6. Alihkan pengguna secara otomatis kembali ke halaman login premium
header("Location: login.php");
exit();
?>