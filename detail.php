<?php
// 1. Inisialisasi Sesi Secara Aman (Mencegah Duplikasi Error)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Proteksi Akses Halaman Sesuai Hak Akses Role
if (!isset($_SESSION['role'])) { 
    header("Location: login.php"); 
    exit(); 
}
if ($_SESSION['role'] === 'wali' && $_SESSION['siswa_id'] !== $_GET['no_siswa']) { 
    header("Location: detail.php?no_siswa=" . $_SESSION['siswa_id']); 
    exit();
}

// 3. Hubungkan ke API Google Sheets
require_once __DIR__ . '/google-sheets-client.php';

// =================================================================
// FUNGSIONAL UTAMA: PENYERAGAMAN FORMAT TANGGAL UNTUK VISUAL MANUSIA
// =================================================================
function tampilkanTanggal($tanggal_db) {
    if (empty($tanggal_db) || $tanggal_db === '-') return '-';
    
    // Konversi string tanggal dari database/sheet secara aman ke timestamp PHP
    $timestamp = strtotime(str_replace('/', '-', $tanggal_db));
    
    if ($timestamp === false) return $tanggal_db; // Jaga-jaga jika format gagal diconvert
    
    // Mengembalikan format dd/mm/yyyy
    return date('d/m/Y', $timestamp);
}
// =================================================================

$no_siswa = isset($_GET['no_siswa']) ? htmlspecialchars(trim($_GET['no_siswa'])) : '';

if (empty($no_siswa)) {
    echo "ID Siswa Tidak Valid.";
    exit();
}

$siswa_raw = sheets_read('Master_siswa');
$transaksi_raw = sheets_read('Transaksi_luran');

$nama_siswa = 'Tidak Ditemukan';
if (!empty($siswa_raw)) {
    foreach ($siswa_raw as $index => $row) {
        if ($index === 0) continue;
        if (trim($row[0]) === $no_siswa) {
            $nama_siswa = trim($row[1]);
            break;
        }
    }
}

$list_bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$payment_log = [];
$total_dana_terbayar = 0;
$jumlah_bulan_lunas = 0;
$tahun_aktif = date('Y');

if (!empty($transaksi_raw)) {
    foreach ($transaksi_raw as $index => $col) {
        if ($index === 0 || empty($col[0])) continue;
        
        if (trim($col[0]) === $no_siswa && trim($col[2]) == $tahun_aktif) {
            $bulan_nama = trim($col[1]);
            $nominal_cash = (int)trim($col[3]);
            $waktu_log = isset($col[4]) ? trim($col[4]) : '-';
            
            $payment_log[$bulan_nama] = [
                'nominal' => $nominal_cash,
                'timestamp' => $waktu_log
            ];
            $total_dana_terbayar += $nominal_cash;
            $jumlah_bulan_lunas++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Kendali SPP - <?= $nama_siswa; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/jpeg" href="assets/Logo_Tapak_Suci_Galunggung.jpeg">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; color: #333; }
        .header-card { background: linear-gradient(135deg, #8B0000 0%, #B22222 100%); color: white; border-radius: 20px; border: none; box-shadow: 0 10px 25px rgba(139,0,0,0.15); }
        .card-stat { border: none; border-radius: 16px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); background-color: #ffffff; }
        .table-premium { background: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); overflow: hidden; border: 1px solid #eef2f5; }
        .status-lunas { background-color: #d1e7dd; color: #0f5132; font-weight: 700; font-size: 11px; border-radius: 30px; tracking-wider: 0.5px; display: inline-block; }
        .status-belum { background-color: #f8d7da; color: #842029; font-weight: 700; font-size: 11px; border-radius: 30px; tracking-wider: 0.5px; display: inline-block; }
    </style>
</head>
<body>

    <div class="container py-4" style="max-width: 850px;">
        
        <div class="d-flex align-items-center justify-content-between mb-4 px-2">
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="index.php" class="btn btn-white shadow-sm border rounded-pill px-3 fw-semibold text-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali ke Dashboard</a>
            <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary py-2 px-3 rounded-pill fw-bold"><i class="bi bi-person-workspace me-1"></i> Mode Siswa Terautentikasi</span>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3"><i class="bi bi-box-arrow-right me-1"></i> Keluar Aplikasi</a>
        </div>

        <div class="card header-card p-4 mb-4 text-center text-md-start">
            <div class="row align-items-center g-3">
                <div class="col-12 col-md-2 text-center">
                    <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 80px; height: 80px;">
                        <img src="assets/Logo_Tapak_Suci_Galunggung.jpeg" alt="Logo" style="width: 60px; height: 60px; object-fit: contain;">
                    </div>
                </div>
                <div class="col-12 col-md-10 ps-md-3">
                    <span class="badge bg-warning text-dark fw-bold mb-1 text-uppercase tracking-wider px-2 py-1" style="font-size: 10px;">KARTU KENDALI IURAN KAS</span>
                    <h3 class="fw-bold m-0 text-uppercase tracking-wide"><?= $nama_siswa; ?></h3>
                    <p class="text-white-50 m-0 mt-1" style="font-size: 14px;"><i class="bi bi-person-badge-fill me-1"></i> Nomor Registrasi Absen Induk: <strong class="text-white">#<?= $no_siswa; ?></strong> • Periode Pembukuan: <?= $tahun_aktif; ?></p>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6">
                <div class="card-stat p-3 text-center">
                    <span class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 10px;">Total Dana Masuk</span>
                    <h3 class="fw-bold text-success m-0">Rp <?= number_format($total_dana_terbayar, 0, ',', '.'); ?></h3>
                </div>
            </div>
            <div class="col-6">
                <div class="card-stat p-3 text-center">
                    <span class="text-muted d-block mb-1 text-uppercase fw-bold" style="font-size: 10px;">Status Lunas</span>
                    <h3 class="fw-bold text-primary m-0"><?= $jumlah_bulan_lunas; ?> <small class="fs-6 text-muted fw-normal">Bulan</small></h3>
                </div>
            </div>
        </div>

        <div class="table-premium">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light border-bottom">
                        <tr class="text-muted" style="font-size: 12px; font-weight: 700;">
                            <th class="ps-4 py-3" style="width: 250px;">PERIODE BULAN</th>
                            <th class="text-center py-3" style="width: 150px;">STATUS</th>
                            <th class="text-end py-3" style="width: 180px;">NOMINAL BAYAR</th>
                            <th class="text-center ps-4 py-3" style="width: 220px;">TANGGAL BAYAR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_bulan as $bulan): ?>
                            <tr class="border-bottom">
                                <td class="ps-4 py-3 fw-semibold text-dark"><i class="bi bi-calendar-event text-muted me-2"></i><?= $bulan; ?></td>
                                <td class="text-center py-3">
                                    <?php if (isset($payment_log[$bulan])): ?>
                                        <span class="status-lunas py-1 px-3"><i class="bi bi-check-circle-fill me-1"></i>LUNAS</span>
                                    <?php else: ?>
                                        <span class="status-belum py-1 px-3"><i class="bi bi-x-circle me-1"></i>BELUM</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold py-3 <?= isset($payment_log[$bulan]) ? 'text-success' : 'text-muted'; ?>">
                                    <?= isset($payment_log[$bulan]) ? 'Rp ' . number_format($payment_log[$bulan]['nominal'], 0, ',', '.') : '-'; ?>
                                </td>
                                <td class="text-center font-monospace text-muted ps-4 py-3" style="font-size: 13px;">
                                    <?= isset($payment_log[$bulan]) ? tampilkanTanggal($payment_log[$bulan]['timestamp']) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <p class="text-center text-muted mt-4" style="font-size: 12px;">E-System SPP Tapak Suci Galunggung • Sinkronisasi Otomatis Google Sheets • Active</p>
    </div>
</body>
</html>