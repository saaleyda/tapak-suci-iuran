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
            $error_message = "Username atau Password Admin salah!";
        }
    } else {
        // Logika Login Wali Murid menggunakan ID / No Siswa
        $siswa_id_input = isset($_POST['siswa_id']) ? htmlspecialchars(trim($_POST['siswa_id'])) : '';
        
        if (empty($siswa_id_input)) {
            $error_message = "Silakan masukkan Nomor / ID Siswa Anda!";
        } else {
            $siswa_raw = sheets_read('Master_siswa');
            $id_ditemukan = false;
            
            if (!empty($siswa_raw)) {
                foreach ($siswa_raw as $siswa) {
                    if (!empty($siswa[0]) && trim($siswa[0]) == $siswa_id_input) {
                        $id_ditemukan = true;
                        $_SESSION['role'] = 'wali';
                        $_SESSION['siswa_id'] = trim($siswa[0]);
                        
                        header("Location: detail.php?no_siswa=" . trim($siswa[0]));
                        exit();
                    }
                }
            }
            
            if (!$id_ditemukan) {
                $error_message = "Nomor / ID Siswa tidak terdaftar di sistem!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Iuran Tapak Suci</title>
    <link rel="icon" type="image/jpeg" href="assets/Logo_Tapak_Suci_Galunggung.jpeg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: #1e0000; /* Background gelap bernuansa maroon tua */
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        :root { 
            --ts-maroon: #800000; 
            --ts-gold: #FFD700; 
        }
        .login-card {
            background: #2d0000;
            border: 2px solid var(--ts-gold);
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 420px;
            padding: 2.5rem 2rem;
        }
        .btn-ts {
            background-color: var(--ts-gold);
            color: #000;
            font-weight: 600;
            border: none;
            transition: all 0.2s;
        }
        .btn-ts:hover {
            background-color: #e6c200;
            transform: translateY(-1px);
        }
        .form-control {
            background-color: #4a0000;
            border: 1px solid #660000;
            color: #fff;
        }
        .form-control:focus {
            background-color: #590000;
            border-color: var(--ts-gold);
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.25);
            color: #fff;
        }

        /* Style kursor saat mengarah ke logo agar terindikasi bisa diklik */
        .clickable-logo {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .clickable-logo:active {
            transform: scale(0.95);
        }

        /* Menghilangkan tombol panah atas-bawah input number */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
</head>
<body>

    <div class="login-card text-center">
        <!-- EASTER EGG: Logo jika diklik akan memicu fungsi toggleAdminMode() -->
        <img src="assets/Logo_Tapak_Suci_Galunggung.jpeg" alt="Logo Tapak Suci" width="110" height="110" class="mb-3 clickable-logo" onclick="toggleAdminMode()" title="Easter Egg Admin" onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/3/30/Logo_Tapak_Suci_Putera_Muhammadiyah.png';">
        
        <!-- Judul Dinamis yang akan berganti jika mode admin aktif -->
        <h4 class="fw-bold text-gold mb-1" id="login-title">E-System SPP Tapak Suci Galunggung</h4>
        <p class="small mb-4" id="login-subtitle" style="color: #ffe066; font-weight: 500; opacity: 0.95;">Tapak Suci Putera Muhammadiyah cab. Galunggung</p>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger py-2 px-3 small text-start mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= $error_message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="mainLoginForm">
            <!-- Hidden input penentu role -->
            <input type="hidden" name="role" id="active_role" value="wali">

            <!-- FORM LOGIN WALI MURID (DEFAULT TAMPIL) -->
            <div id="container-wali">
                <div class="mb-4 text-start">
                    <label class="form-label small fw-medium text-gold">NOMOR / ID SISWA</label>
                    <div class="input-group">
                        <span class="input-group-text bg-maroon border-0 text-white"><i class="bi bi-hash"></i></span>
                        <input type="number" name="siswa_id" id="siswa_id_field" class="form-control" placeholder="Contoh: 99" autocomplete="off" required>
                    </div>
                    <div class="form-text mt-2" style="font-size: 11px; color: #cbd5e1; letter-spacing: 0.3px;">
                        <i class="bi bi-info-circle me-1 text-gold"></i> Ketikkan nomor pendaftaran / ID siswa.
                    </div>
                </div>
            </div>

            <!-- FORM LOGIN ADMIN (TERSEMBUNYI, HANYA MUNCUL LEWAT KLIK LOGO) -->
            <div id="container-admin" style="display: none;">
                <div class="mb-3 text-start">
                    <label class="form-label small fw-medium text-gold">USERNAME ADMIN</label>
                    <div class="input-group">
                        <span class="input-group-text bg-maroon border-0 text-white"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" id="username_field" class="form-control" placeholder="Username admin">
                    </div>
                </div>
                <div class="mb-3 text-start">
                    <label class="form-label small fw-medium text-gold">PASSWORD ADMIN</label>
                    <div class="input-group">
                        <span class="input-group-text bg-maroon border-0 text-white"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="password_field" class="form-control" placeholder="••••••••">
                    </div>
                </div>
                <div class="text-end mb-4">
                    <a href="javascript:void(0)" onclick="toggleAdminMode()" class="small" style="color: #ffe066; text-decoration: none;"><i class="bi bi-arrow-left"></i> Kembali ke Halaman Wali</a>
                </div>
            </div>

            <button type="submit" class="btn btn-ts w-100 py-2.5 mt-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> MASUK KE SISTEM
            </button>
        </form>
    </div>

    <script>
        // Fungsi Rahasia/Easter Egg untuk merubah form saat logo diklik
        function toggleAdminMode() {
            const activeRole = document.getElementById('active_role');
            const containerWali = document.getElementById('container-wali');
            const containerAdmin = document.getElementById('container-admin');
            const title = document.getElementById('login-title');
            const subtitle = document.getElementById('login-subtitle');
            
            const fieldSiswa = document.getElementById('siswa_id_field');
            const fieldUser = document.getElementById('username_field');
            const fieldPass = document.getElementById('password_field');

            if (activeRole.value === 'wali') {
                // Berubah ke Mode Admin
                activeRole.value = 'admin';
                containerWali.style.display = 'none';
                containerAdmin.style.display = 'block';
                title.innerText = 'CONTROL PANEL';
                subtitle.innerText = 'Administrator System Log';
                
                fieldSiswa.required = false;
                fieldUser.required = true;
                fieldPass.required = true;
            } else {
                // Kembali ke Mode Wali Murid
                activeRole.value = 'wali';
                containerWali.style.display = 'block';
                containerAdmin.style.display = 'none';
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