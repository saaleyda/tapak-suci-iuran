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

$no_siswa_req = isset($_GET['no_siswa']) ? htmlspecialchars(trim($_GET['no_siswa'])) : '';

if (empty($no_siswa_req)) {
    die("ID Siswa tidak ditemukan.");
}

$siswa_raw = sheets_read('Master_siswa');
$transaksi_raw = sheets_read('Transaksi_luran');
$list_bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$bulan_angka = (int)date('m'); 
$bulan_sekarang = $list_bulan[$bulan_angka - 1]; 

// Menentukan Tahun Aktif Secara Dinamis Mengikuti Tahun Berjalan
$tahun_aktif = date('Y');

$profil_siswa = null;
if (!empty($siswa_raw)) {
    foreach ($siswa_raw as $siswa) {
        if (!empty($siswa[0]) && trim($siswa[0]) == $no_siswa_req) {
            $profil_siswa = ['id' => trim($siswa[0]), 'nama' => isset($siswa[1]) ? trim($siswa[1]) : 'Tanpa Nama'];
            break;
        }
    }
}

if (!$profil_siswa) { 
    die("Siswa tidak terdaftar."); 
}

$payment_log = [];
$total_terbayar_keseluruhan = 0;
$total_terbayar_bulan_ini = 0;

if (!empty($transaksi_raw)) {
    foreach ($transaksi_raw as $index => $tx) {
        if ($index === 0 || empty($tx[0])) continue;
        
        if (trim($tx[0]) == $no_siswa_req && trim($tx[2]) == $tahun_aktif) {
            $bulan_db = ucfirst(strtolower(trim($tx[1])));
            $nominal_clean = isset($tx[3]) ? (int)preg_replace('/[^0-9]/', '', $tx[3]) : 0;
            
            $ts_clean = isset($tx[4]) ? trim($tx[4]) : '-';

            $payment_log[$bulan_db] = [
                'nominal'   => $nominal_clean,
                'timestamp' => $ts_clean
            ];
            
            $total_terbayar_keseluruhan += $nominal_clean;

            if ($bulan_db === $bulan_sekarang) {
                $total_terbayar_bulan_ini = $nominal_clean;
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
    <title>Kartu Iuran - <?= htmlspecialchars($profil_siswa['nama']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; font-size: 14px; color: #334155; }
        :root { --ts-maroon: #800000; --ts-gold: #FFD700; }
        .bg-maroon { background-color: var(--ts-maroon) !important; }
        .text-gold { color: var(--ts-gold) !important; }
        
        .card-profile { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); overflow: hidden; }
        
        .status-lunas { color: #166534; background-color: #f0fdf4; border: 1px solid #bbf7d0; padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; display: inline-block; }
        .status-belum { color: #b91c1c; background-color: #fef2f2; border: 1px solid #fee2e2; padding: 6px 16px; border-radius: 50px; font-size: 13px; font-weight: 600; display: inline-block; }

        .sticky-profile {
            position: -webkit-sticky;
            position: sticky;
            top: 0;
            z-index: 1020;
            background-color: #f8fafc;
            padding-top: 1.5rem;
            padding-bottom: 0.5rem;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-maroon shadow-sm border-bottom border-warning py-3">
        <div class="container px-4 d-flex justify-content-between align-items-center">
            
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                    <i class="bi bi-arrow-left fs-5 text-gold"></i>
                    <span class="fw-bold text-gold" style="font-size: 15px; letter-spacing: 0.5px;">KEMBALI KE HALAMAN UTAMA</span>
                </a>
            <?php else: ?>
                <div class="navbar-brand d-flex align-items-center gap-2">
                    <img src="assets/Logo_Tapak_Suci_Galunggung.jpeg" alt="Logo" width="35" height="35" onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/3/30/Logo_Tapak_Suci_Putera_Muhammadiyah.png';">
                    <span class="fw-bold text-gold" style="font-size: 15px; letter-spacing: 0.5px;">E-System SPP TS</span>
                </div>
            <?php endif; ?>

            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-warning text-dark fw-bold px-3 py-2 fs-6">Tahun Buku: <?= $tahun_aktif; ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm fw-medium px-3 border-secondary" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>

        </div>
    </nav>

    <div class="container px-4 my-4" style="max-width: 850px;">
        
        <div class="sticky-profile mb-4">
            <div class="card-profile p-4 border-0 shadow-sm">
                <div class="row align-items-center g-3">
                    <div class="col-sm-7">
                        <span class="text-muted small font-monospace d-block" style="font-size: 13px;">ID SISWA: #<?= $profil_siswa['id']; ?></span>
                        <h2 class="fw-bold text-dark m-0 mt-1" style="font-size: 24px;"><?= htmlspecialchars($profil_siswa['nama']); ?></h2>
                        <p class="text-muted m-0 mt-2" style="font-size: 13px;"><i class="bi bi-info-circle me-1"></i> Riwayat penyerahan SPP bulanan mandiri.</p>
                    </div>
                    <div class="col-sm-5 text-sm-end border-start-sm">
                        <span class="text-muted d-block mb-1.5" style="font-size: 12px; font-weight: 600; letter-spacing: 0.5px;">STATUS BULAN INI (<?= strtoupper($bulan_sekarang); ?>)</span>
                        <?php if (isset($payment_log[$bulan_sekarang])): ?>
                            <span class="status-lunas fs-5 py-2 px-4 mb-1"><i class="bi bi-check-circle-fill me-1"></i>LUNAS (<?= number_format($total_terbayar_bulan_ini/1000, 0); ?>K)</span>
                        <?php else: ?>
                            <span class="status-belum fs-5 py-2 px-4 mb-1"><i class="bi bi-exclamation-circle-fill me-1"></i>BELUM BAYAR</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <hr class="my-3 opacity-25">
                
                <div class="row g-2">
                    <div class="col-6">
                        <div class="bg-light p-3 rounded text-center">
                            <small class="text-secondary d-block fw-medium mb-1" style="font-size: 12px;">Setoran Bulan Ini (<?= $bulan_sekarang; ?>):</small>
                            <span class="fw-bold text-dark fs-5">Rp <?= number_format($total_terbayar_bulan_ini, 0, ',', '.'); ?></span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded text-center" style="background-color: #f0fdf4;">
                            <small class="text-success d-block fw-medium mb-1" style="font-size: 12px;">Total Terbayar (Semua Bulan):</small>
                            <span class="fw-bold text-success fs-5">Rp <?= number_format($total_terbayar_keseluruhan, 0, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-container border-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0" style="font-size: 14px;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 py-3 text-secondary" style="font-size: 12px; font-weight: 600; text-transform: uppercase;">Bulan Kategori</th>
                            <th class="text-center text-secondary" style="font-size: 12px; font-weight: 600; text-transform: uppercase; width: 150px;">Status Buku</th>
                            <th class="text-end text-secondary" style="font-size: 12px; font-weight: 600; text-transform: uppercase; width: 160px;">Nominal</th>
                            <th class="text-center text-secondary ps-4" style="font-size: 12px; font-weight: 600; text-transform: uppercase; min-width: 180px;">Tanggal Input</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_bulan as $bulan): 
                            $is_current = ($bulan === $bulan_sekarang) ? 'table-warning bg-opacity-10' : '';
                        ?>
                            <tr class="<?= $is_current; ?>">
                                <td class="fw-bold ps-4 text-dark py-3">
                                    <?= $bulan; ?>
                                    <?php if($bulan === $bulan_sekarang): ?>
                                        <span class="badge bg-danger ms-1 px-2 py-1" style="font-size: 10px;">Bulan Ini</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
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
                                    <!-- PENERAPAN: Mengonversi format agar seragam DD/MM/YYYY saat ditampilkan -->
                                    <?= isset($payment_log[$bulan]) ? tampilkanTanggal($payment_log[$bulan]['timestamp']) : '-'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <p class="text-center text-muted mt-4" style="font-size: 12px;">E-System SPP Tapak Suci Galunggung • Sinkronisasi Otomatis Google Sheets • A</p>
    </div>
</body>
</html>