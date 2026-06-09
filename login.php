<?php
// Cek status login dari Cookie
$auth_role = isset($_COOKIE['auth_role']) ? $_COOKIE['auth_role'] : '';

if ($auth_role === 'admin') {
    header("Location: index.php");
    exit();
}

require_once __DIR__ . '/google-sheets-client.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_login') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $admin_user = getenv('ADMIN_USER') ?: 'adminglg';
    $admin_pass = getenv('ADMIN_PASS') ?: 'galunggung2026';

    if ($username === $admin_user && $password === $admin_pass) {
        // Set Cookie selama 1 hari (Stabil di Vercel)
        setcookie('auth_role', 'admin', [
            'expires' => time() + 86400,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        header("Location: index.php");
        exit();
    } else {
        $error_message = 'Akses ditolak. Username atau Password salah!';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'wali_access') {
    $siswa_id = trim($_POST['siswa_id']);
    setcookie('auth_role', 'wali', [
        'expires' => time() + 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    setcookie('wali_id', $siswa_id, [
        'expires' => time() + 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    header("Location: detail.php?no_siswa=" . urlencode($siswa_id));
    exit();
}

$search_results = [];
$search_keyword = '';
$has_searched = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'wali_search') {
    $search_keyword = htmlspecialchars(trim($_POST['keyword']));
    $has_searched = true;

    if (!empty($search_keyword)) {
        $siswa_raw = sheets_read('Master_siswa');
        if (!empty($siswa_raw)) {
            foreach ($siswa_raw as $index => $row) {
                if ($index === 0 || empty($row[0])) continue;
                
                $id_siswa = trim($row[0]);
                $nama_siswa = isset($row[1]) ? trim($row[1]) : '-';

                if (stripos($id_siswa, $search_keyword) !== false || stripos($nama_siswa, $search_keyword) !== false) {
                    $search_results[] = [
                        'id' => $id_siswa,
                        'nama' => $nama_siswa
                    ];
                }
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
    <title>E-System SPP Tapak Suci Galunggung</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/jpeg" href="assets/Logo_Tapak_Suci_Galunggung.jpeg">
    <style>
        body { background-color: #8B0000; font-family: 'Segoe UI', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .main-card { background: white; border-radius: 24px; box-shadow: 0 15px 35px rgba(0,0,0,0.3); max-width: 500px; width: 100%; overflow: hidden; border: 4px solid #FFD700; }
        .logo-clickable { cursor: pointer; transition: transform 0.3s ease; width: 100px; height: 100px; object-fit: cover; border: 3px solid #FFD700; }
        .logo-clickable:hover { transform: scale(1.1) rotate(8deg); }
        .btn-primary-custom { background-color: #8B0000; color: white; border: none; }
        .btn-primary-custom:hover { background-color: #a00000; color: white; }
        #adminLoginForm { display: none; }
        .list-btn-submit { background: none; border: none; padding: 0; width: 100%; text-align: left; }
    </style>
</head>
<body>
    <div class="main-card p-4">
        <div class="text-center mb-4 border-bottom pb-3">
            <img src="assets/Logo_Tapak_Suci_Galunggung.jpeg" id="eggLogo" alt="Logo Tapak Suci" class="rounded-circle logo-clickable mb-2 shadow">
            <h4 class="fw-bold text-dark m-0">SPP TAPAK SUCI</h4>
            <p class="text-muted small mb-0">Pusat Informasi & Cek Iuran Siswa - Cab. Galunggung</p>
        </div>

        <div id="waliPortal">
            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-search me-1"></i> Cek Status Pembayaran Siswa</h6>
            <form method="POST" action="">
                <input type="hidden" name="action" value="wali_search">
                <div class="input-group mb-3">
                    <input type="text" name="keyword" class="form-control" placeholder="Masukkan Nama Siswa / ID Urut..." value="<?= htmlspecialchars($search_keyword); ?>" required autocomplete="off">
                    <button class="btn btn-warning fw-semibold px-3" type="submit"><i class="bi bi-search"></i> Cari</button>
                </div>
            </form>

            <?php if ($has_searched): ?>
                <div class="mt-3">
                    <h6 class="text-muted small fw-bold text-uppercase mb-2">Hasil Pencarian:</h6>
                    <?php if (!empty($search_results)): ?>
                        <div class="list-group shadow-sm">
                            <?php foreach ($search_results as $siswa): ?>
                                <form method="POST" action="" class="m-0">
                                    <input type="hidden" name="action" value="wali_access">
                                    <input type="hidden" name="siswa_id" value="<?= $siswa['id']; ?>">
                                    <button type="submit" class="list-btn-submit list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                                        <div>
                                            <span class="badge bg-secondary me-2">ID #<?= $siswa['id']; ?></span>
                                            <strong class="text-dark"><?= $siswa['nama']; ?></strong>
                                        </div>
                                        <span class="badge bg-success rounded-pill px-3 py-1">Lihat Rapor SPP <i class="bi bi-arrow-right ms-1"></i></span>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border border-dashed text-center text-muted py-3" role="alert">
                            <i class="bi bi-person-x-fill fs-4 d-block mb-1 text-danger"></i>
                            Nama atau ID "<strong><?= htmlspecialchars($search_keyword); ?></strong>" tidak ditemukan.
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="adminLoginForm">
            <div class="text-center mb-3">
                <span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-1 fw-bold mb-2"><i class="bi bi-shield-lock-fill me-1"></i> Mode Administrator</span>
                <h5 class="fw-bold text-dark m-0">Masuk Sistem Utama</h5>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger border-0 small py-2 rounded-3 text-center mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= $error_message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="admin_login">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Username Admin</label>
                    <input type="text" name="username" class="form-control bg-light" placeholder="Username" required autocomplete="off">
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold text-secondary">Password</label>
                    <input type="password" name="password" class="form-control bg-light" placeholder="••••••••" required>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary-custom py-2 fw-semibold rounded-3 shadow-sm">Buka Akses Kontrol</button>
                    <button type="button" id="btnCancelAdmin" class="btn btn-light btn-sm text-muted rounded-pill mt-1">Kembali ke Pencarian</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const eggLogo = document.getElementById('eggLogo');
        const waliPortal = document.getElementById('waliPortal');
        const adminLoginForm = document.getElementById('adminLoginForm');
        const btnCancelAdmin = document.getElementById('btnCancelAdmin');

        <?php if (!empty($error_message)): ?>
            adminLoginForm.style.display = 'block';
            waliPortal.style.display = 'none';
        <?php endif; ?>

        eggLogo.addEventListener('click', function() {
            if (adminLoginForm.style.display === 'none' || adminLoginForm.style.display === '') {
                adminLoginForm.style.display = 'block';
                waliPortal.style.display = 'none';
            }
        });

        btnCancelAdmin.addEventListener('click', function() {
            adminLoginForm.style.display = 'none';
            waliPortal.style.display = 'block';
        });
    </script>
</body>
</html>