<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$auth_role = isset($_COOKIE['auth_role']) ? $_COOKIE['auth_role'] : '';
$wali_id = isset($_COOKIE['wali_id']) ? $_COOKIE['wali_id'] : '';

if (empty($auth_role)) {
    header("Location: login.php");
    exit();
}

$no_siswa = isset($_GET['no_siswa']) ? trim($_GET['no_siswa']) : '';

if ($auth_role === 'wali') {
    if ($no_siswa !== $wali_id) {
        header("Location: logout.php");
        exit();
    }
}

require_once __DIR__ . '/google-sheets-client.php';

$tahun_aktif = date('Y');
$list_bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$siswa_raw = sheets_read('Master_siswa');
$nama_siswa = '';
$siswa_ditemukan = false;

if (!empty($siswa_raw)) {
    foreach ($siswa_raw as $index => $row) {
        if ($index === 0 || empty($row[0])) continue;
        if (trim($row[0]) === $no_siswa) {
            $nama_siswa = isset($row[1]) ? trim($row[1]) : '-';
            $siswa_ditemukan = true;
            break;
        }
    }
}

if (!$siswa_ditemukan) {
    header("Location: " . ($auth_role === 'admin' ? 'index.php' : 'logout.php'));
    exit();
}

$transaksi_raw = sheets_read('Transaksi_luran');
$payment_history = [];
$total_dibayar = 0;

if (!empty($transaksi_raw)) {
    foreach ($transaksi_raw as $index => $col) {
        if ($index === 0 || empty($col[0])) continue;
        
        $s_id = trim($col[0]);
        $bln  = trim($col[1]);
        $thn  = trim($col[2]);
        $nom  = (int)trim($col[3]);
        $time = isset($col[4]) ? trim($col[4]) : '-';
        
        if ($s_id === $no_siswa && $thn == $tahun_aktif) {
            $payment_history[$bln] = [
                'lunas' => true,
                'nominal' => $nom,
                'tanggal' => $time
            ];
            $total_dibayar += $nom;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu SPP Digital - <?= htmlspecialchars($nama_siswa); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/jpeg" href="assets/Logo_Tapak_Suci_Galunggung.jpeg">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background-color: #8B0000; border-bottom: 4px solid #FFD700; }
        .card-spp { border: none; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); overflow: hidden; }
        .badge-status { font-size: 11px; padding: 6px 12px; border-radius: 50px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark navbar-custom py-3 shadow-sm">
        <div class="container d-flex justify-content-between align-items-center">
            <span class="navbar-brand mb-0 h1 fw-bold text-uppercase d-flex align-items-center" style="font-size: 15px;">
                <img src="assets/Logo_Tapak_Suci_Galunggung.jpeg" alt="Logo" class="rounded-circle me-2" style="width: 30px; height: 30px; object-fit: cover;">
                KARTU SPP DIGITAL SISWA
            </span>
            <?php if ($auth_role === 'admin'): ?>
                <a href="index.php" class="btn btn-warning btn-sm fw-semibold rounded-pill px-3"><i class="bi bi-arrow-left me-1"></i> Dashboard Admin</a>
            <?php else: ?>
                <a href="logout.php" class="btn btn-light btn-sm fw-semibold rounded-pill px-3 text-danger border-danger"><i class="bi bi-box-arrow-left me-1"></i> Kembali ke Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container my-4" style="max-width: 700px;">
        <div class="card card-spp bg-white p-4 mb-4 border-start border-4 border-warning">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1">Nama Lengkap Siswa</span>
                    <h3 class="fw-bold text-dark mb-1"><?= htmlspecialchars($nama_siswa); ?></h3>
                    <span class="badge bg-dark rounded-pill px-3">Nomor ID Urut: #<?= htmlspecialchars($no_siswa); ?></span>
                </div>
                <div class="text-end">
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1">Total Dana Masuk (<?= $tahun_aktif; ?>)</span>
                    <h4 class="fw-bold text-success m-0">Rp <?= number_format($total_dibayar, 0, ',', '.'); ?></h4>
                </div>
            </div>
        </div>

        <div class="card card-spp bg-white shadow-sm border">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="table-dark">
                        <tr>
                            <th class="py-3" style="width: 150px;">BULAN</th>
                            <th>STATUS BAYAR</th>
                            <th class="text-end pe-4">NOMINAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_bulan as $bulan): 
                            $is_lunas = isset($payment_history[$bulan]);
                        ?>
                            <tr>
                                <td class="fw-bold text-secondary text-uppercase py-3" style="font-size: 13px;"><?= $bulan; ?></td>
                                <td>
                                    <?php if ($is_lunas): ?>
                                        <span class="badge-status bg-success-subtle text-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i> LUNAS</span>
                                        <small class="text-muted d-block" style="font-size:10px; margin-top:2px;">Diterima: <?= date('d/m/Y', strtotime($payment_history[$bulan]['tanggal'])); ?></small>
                                    <?php else: ?>
                                        <span class="badge-status bg-danger-subtle text-danger fw-bold"><i class="bi bi-x-circle-fill me-1"></i> BELUM BAYAR</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4 fw-bold <?= $is_lunas ? 'text-dark' : 'text-muted-50'; ?>">
                                    Rp <?= $is_lunas ? number_format($payment_history[$bulan]['nominal'], 0, ',', '.') : '0'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-center text-muted small mt-4">Sistem Pencatatan Otomatis Real-time Terintegrasi Core Cloud Sheets</p>
    </div>
</body>
</html>