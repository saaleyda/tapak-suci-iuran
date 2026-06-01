<?php
// 1. Inisialisasi Sesi Secara Aman
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, langsung lempar ke halaman yang sesuai
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: index.php");
        exit();
    } else if ($_SESSION['role'] === 'wali') {
        header("Location: detail.php?no_siswa=" . $_SESSION['siswa_id']);
        exit();
    }
}

// 2. Hubungkan ke API Google Sheets
require_once __DIR__ . '/google-sheets-client.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_input = isset($_POST['role']) ? trim($_POST['role']) : 'wali';
    
    if ($role_input === 'admin') {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        // PENTING: Silakan sesuaikan Username & Password Admin kamu di sini
        if ($username === 'adminglg' && $password === 'galunggung2026') {
            $_SESSION['role'] = 'admin';
            header("Location: index.php");
            exit();
        } else {
            $error_message = "Username atau password Administrator salah!";
        }
    } else if ($role_input === 'wali') {
        $siswa_id = isset($_POST['siswa_id']) ? trim($_POST['siswa_id']) : '';
        
        if (!empty($siswa_id)) {
            $siswa_data = sheets_read('Master_siswa');
            $ditemukan = false;
            
            if (!empty($siswa_data)) {
                foreach ($siswa_data as $index => $row) {
                    if ($index === 0) continue; // Lewati baris judul tabel
                    if (trim($row[0]) === $siswa_id) {
                        $ditemukan = true;
                        $_SESSION['role'] = 'wali';
                        $_SESSION['siswa_id'] = $siswa_id;
                        header("Location: detail.php?no_siswa=" . $siswa_id);
                        exit();
                    }
                }
            }
            
            if (!$ditemukan) {
                $error_message = "Nomor ID Siswa tidak terdaftar di sistem pusat!";
            }
        } else {
            $error_message = "Silakan masukkan nomor urut/ID siswa dengan benar!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-System SPP Tapak Suci</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/jpeg" href="assets/Logo_Tapak_Suci_Galunggung.jpeg">
    <style>
        body { background: linear-gradient(135deg, #8B0000 0%, #B22222 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .login-card { border: none; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.3); background: #ffffff; overflow: hidden; max-width: 450px; width: 100%; }
        .brand-header { background: #FFD700; padding: 30px; text-align: center; border-bottom: 5px solid #DAA520; }
        .brand-logo { width: 85px; height: 85px; object-fit: contain; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.15)); }
        .btn-primary-custom { background: #8B0000; border: none; color: white; padding: 12px; border-radius: 10px; font-weight: 600; width: 100%; transition: all 0.3s ease; }
        .btn-primary-custom:hover { background: #A00000; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(139,0,0,0.3); }
        .btn-toggle-role { background: none; border: 2px solid #8B0000; color: #8B0000; padding: 10px; border-radius: 10px; font-weight: 600; width: 100%; transition: all 0.3s ease; }
        .btn-toggle-role:hover { background: #8B0000; color: white; }
    </style>
</head>
<body>
    <div class="login-card p-0 m-3">
        <div class="brand-header">
            <img src="assets/Logo_Tapak_Suci_Galunggung.jpeg" alt="Logo Tapak Suci" class="brand-logo mb-2">
            <h5 class="fw-bold text-dark m-0" id="login-title">E-System SPP Tapak Suci Galunggung</h5>
            <small class="text-muted d-block mt-1" id="login-subtitle">Tapak Suci Putera Muhammadiyah cab. Galunggung</small>
        </div>
        
        <div class="card-body p-4 pb-3">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div><?= $error_message; ?></div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" id="mainLoginForm">
                <input type="hidden" name="role" id="active_role" value="wali">
                
                <div id="siswa_id_field" class="mb-4">
                    <label class="form-label fw-semibold text-secondary"><i class="bi bi-hash me-1"></i>Masukkan ID / No Urut Siswa</label>
                    <input type="number" name="siswa_id" class="form-control form-control-lg bg-light border-0" placeholder="Contoh: 12" style="border-radius: 10px;" required>
                    <div class="form-text text-muted mt-2" style="font-size: 12px;">Masukkan nomor urut anak didik sesuai daftar absen induk Tapak Suci.</div>
                </div>

                <div id="admin_fields" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary"><i class="bi bi-person-fill me-1"></i>Username</label>
                        <input type="text" name="username" id="username_field" class="form-control form-control-lg bg-light border-0" placeholder="Admin Username" style="border-radius: 10px;">
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-secondary"><i class="bi bi-lock-fill me-1"></i>Password</label>
                        <input type="password" name="password" id="password_field" class="form-control form-control-lg bg-light border-0" placeholder="••••••••" style="border-radius: 10px;">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary-custom mb-3 shadow-sm">MASUK KE SISTEM <i class="bi bi-arrow-right-short ms-1"></i></button>
                <div class="text-center my-2 text-muted" style="font-size: 12px;">Atau login sebagai entitas berbeda</div>
                <button type="button" onclick="toggleRoleMode()" class="btn btn-toggle-role mb-2 shadow-sm" id="btnToggleLabel"><i class="bi bi-shield-lock me-1"></i>LOGIN ADMINISTRATOR</button>
            </form>
        </div>
    </div>

    <script>
        function toggleRoleMode() {
            const activeRole = document.getElementById('active_role');
            const containerWali = document.getElementById('siswa_id_field');
            const containerAdmin = document.getElementById('admin_fields');
            const btnToggle = document.getElementById('btnToggleLabel');
            const title = document.getElementById('login-title');
            const subtitle = document.getElementById('login-subtitle');
            
            const fieldSiswa = document.querySelector('input[name="siswa_id"]');
            const fieldUser = document.getElementById('username_field');
            const fieldPass = document.getElementById('password_field');

            if (activeRole.value === 'wali') {
                activeRole.value = 'admin';
                containerWali.style.display = 'none';
                containerAdmin.style.display = 'block';
                btnToggle.innerHTML = '<i class="bi bi-people me-1"></i>LOGIN WALI MURID';
                title.innerText = 'CONTROL PANEL';
                subtitle.innerText = 'Administrator System Log';
                
                fieldSiswa.required = false;
                fieldUser.required = true;
                fieldPass.required = true;
            } else {
                activeRole.value = 'wali';
                containerWali.style.display = 'block';
                containerAdmin.style.display = 'none';
                btnToggle.innerHTML = '<i class="bi bi-shield-lock me-1"></i>LOGIN ADMINISTRATOR';
                title.innerText = 'E-System SPP Tapak Suci Galunggung';
                subtitle.innerText = 'Tapak Suci Putera Muhammadiyah cab. Galunggung';
                
                fieldSiswa.required = true;
                fieldUser.required = false;
                fieldPass.required = false;
            }
        }
    </script>
</body>
</html>