<?php
// 1. Inisialisasi Sesi di Baris Paling Atas (Cukup Satu Kali)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi Halaman Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: login.php"); 
    exit(); 
}

// Hubungkan ke API Google Sheets
require_once __DIR__ . '/google-sheets-client.php';

// Ambil Flash Message jika ada
$message = '';
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

$list_bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Menentukan Bulan Default Saat Ini secara otomatis
$bulan_angka = (int)date('m'); 
$bulan_default = $list_bulan[$bulan_angka - 1]; 

// FITUR FILTER BULAN
$bulan_pilihan = isset($_GET['filter_bulan']) ? htmlspecialchars(trim($_GET['filter_bulan'])) : $bulan_default;
// Mengamankan tahun secara dinamis
$tahun_aktif = date('Y'); 

// ==========================================
// 2. PROSES POST: CRUD MASTER SISWA & IURAN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: TAMBAH SISWA
    if ($_POST['action'] === 'add_siswa') {
        $no_siswa = htmlspecialchars(trim($_POST['no_siswa']));
        $nama_siswa = htmlspecialchars(trim($_POST['nama_siswa']));
        if (!empty($no_siswa) && !empty($nama_siswa)) {
            if (sheets_append('Master_siswa', [$no_siswa, $nama_siswa], 'append')) {
                $_SESSION['flash_message'] = "<div class='alert alert-success alert-dismissible fade show shadow-sm fs-6' role='alert'>
                                                <i class='bi bi-check-circle-fill me-2'></i> Siswa <b>$nama_siswa</b> berhasil didaftarkan.
                                                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                                              </div>";
            }
        }
        header("Location: index.php?filter_bulan=" . urlencode($bulan_pilihan));
        exit();
    }
    
    // ACTION: EDIT DATA
    if ($_POST['action'] === 'edit_siswa') {
        $old_id = htmlspecialchars(trim($_POST['old_id']));
        $no_siswa = htmlspecialchars(trim($_POST['no_siswa']));
        $nama_siswa = htmlspecialchars(trim($_POST['nama_siswa']));
        if (!empty($old_id) && !empty($nama_siswa)) {
            if (sheets_append('Master_siswa', [$no_siswa, $nama_siswa], 'update', $old_id)) {
                $_SESSION['flash_message'] = "<div class='alert alert-info alert-dismissible fade show shadow-sm fs-6' role='alert'>
                                                <i class='bi bi-pencil-square me-2'></i> Data siswa berhasil diperbarui menjadi <b>$nama_siswa</b>.
                                                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                                              </div>";
            }
        }
        header("Location: index.php?filter_bulan=" . urlencode($bulan_pilihan));
        exit();
    }
    
    // ACTION: HAPUS SISWA
    if ($_POST['action'] === 'delete_siswa') {
        $no_siswa = htmlspecialchars(trim($_POST['no_siswa']));
        if (!empty($no_siswa)) {
            if (sheets_append('Master_siswa', [], 'delete', $no_siswa)) {
                $_SESSION['flash_message'] = "<div class='alert alert-danger alert-dismissible fade show shadow-sm fs-6' role='alert'>
                                                <i class='bi bi-trash3-fill me-2'></i> Siswa dengan ID <b>$no_siswa</b> telah dihapus.
                                                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                                              </div>";
            }
        }
        header("Location: index.php?filter_bulan=" . urlencode($bulan_pilihan));
        exit();
    }

    // ACTION: QUICK BAYAR IURAN
    if ($_POST['action'] === 'quick_bayar') {
        $no_siswa = htmlspecialchars(trim($_POST['no_siswa_bayar']));
        $bulan = htmlspecialchars(trim($_POST['bulan_iuran']));
        $nominal = htmlspecialchars(trim($_POST['nominal_bayar']));
        
        // REVISI: Menggunakan format penulisan tanggal internasional untuk database/sheets
        $timestamp = date('Y-m-d H:i:s'); 

        if (!empty($no_siswa) && !empty($bulan) && !empty($nominal)) {
            if (sheets_append('Transaksi_luran', [$no_siswa, $bulan, $tahun_aktif, $nominal, $timestamp], 'append')) {
                $_SESSION['flash_message'] = "<div class='alert alert-success alert-dismissible fade show shadow-sm fs-6' role='alert'>
                                                <i class='bi bi-cash-coin me-2'></i> Setoran Bulan <b>$bulan</b> sebesar Rp " . number_format($nominal, 0, ',', '.') . " sukses disimpan.
                                                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                                              </div>";
            }
        }
        header("Location: index.php?filter_bulan=" . urlencode($bulan_pilihan));
        exit();
    }
}

// ==========================================
// 3. MEMBACA DATA & MAPPING DARI GOOGLE SHEETS
// ==========================================
$siswa_raw = sheets_read('Master_siswa');
$transaksi_raw = sheets_read('Transaksi_luran');

$pembayaran_map = [];
$total_kas_bulan_pilihan = 0;
$bulan_40k = 0; $bulan_50k = 0; $bulan_60k = 0;

$total_kas_semua = 0;
$semua_40k = 0; $semua_50k = 0; $semua_60k = 0;

$siswa_data = [];

if (!empty($transaksi_raw)) {
    foreach ($transaksi_raw as $index => $tx) {
        if ($index === 0 || empty($tx[0])) continue;
        
        if (trim($tx[2]) == $tahun_aktif) {
            $no_siw = trim($tx[0]);
            $bln = ucfirst(strtolower(trim($tx[1]))); 
            $nominal_clean = isset($tx[3]) ? (int)preg_replace('/[^0-9]/', '', $tx[3]) : 0;
            
            $pembayaran_map[$no_siw][$bln] = $nominal_clean;

            $total_kas_semua += $nominal_clean;
            if ($nominal_clean == 40000) $semua_40k += $nominal_clean;
            elseif ($nominal_clean == 50000) $semua_50k += $nominal_clean;
            elseif ($nominal_clean == 60000) $semua_60k += $nominal_clean;

            if ($bln === $bulan_pilihan) {
                $total_kas_bulan_pilihan += $nominal_clean;
                if ($nominal_clean == 40000) $bulan_40k += $nominal_clean;
                elseif ($nominal_clean == 50000) $bulan_50k += $nominal_clean;
                elseif ($nominal_clean == 60000) $bulan_60k += $nominal_clean;
            }
        }
    }
}

if (!empty($siswa_raw)) {
    foreach ($siswa_raw as $index => $s) {
        if ($index === 0 || empty($s[0])) continue; 
        
        $id_siswa = trim($s[0]);
        if (!is_numeric($id_siswa)) continue; 

        $siswa_data[] = [
            'id' => $id_siswa,
            'nama' => isset($s[1]) ? trim($s[1]) : 'Tanpa Nama'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-System SPP Tapak Suci Galunggung</title>
    <link rel="icon" type="image/jpeg" href="assets/Logo_Tapak_Suci_Galunggung.jpeg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; font-size: 14px; color: #334155; }
        :root { --ts-maroon: #800000; --ts-gold: #FFD700; }
        .bg-maroon { background-color: var(--ts-maroon) !important; }
        .text-gold { color: var(--ts-gold) !important; }
        
        .btn-gold { background-color: var(--ts-gold); color: #1e293b; font-weight: 600; border: 1px solid var(--ts-gold); font-size: 14px; }
        .btn-gold:hover { background-color: #e6b800; color: #000; }
        
        .card-stat-kontras { 
            border: 2px solid var(--ts-gold); 
            background: linear-gradient(135deg, var(--ts-maroon) 0%, #5a0000 100%); 
            color: white;
            height: auto; 
            box-shadow: 0 4px 12px rgba(128, 0, 0, 0.15);
        }
        
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); overflow: hidden; }
        .table-header-maroon { background-color: var(--ts-maroon) !important; color: white !important; vertical-align: middle; text-align: center; font-size: 13px; font-weight: 600; text-transform: uppercase; padding: 12px 8px; }
        .cell-bulan { text-align: center; font-size: 13px; font-weight: 700; min-width: 75px; vertical-align: middle; }
        
        .lunas-40k { color: #166534; background-color: #f0fdf4; border: 1px solid #bbf7d0 !important; }
        .lunas-50k { color: #0369a1; background-color: #f0f9ff; border: 1px solid #bae6fd !important; }
        .lunas-60k { color: #6b21a8; background-color: #faf5ff; border: 1px solid #f3e8ff !important; }
        .status-belum { color: #b91c1c; background-color: #fef2f2; border: 1px solid #fee2e2 !important; }
        .custom-link-nama:hover { color: var(--ts-maroon) !important; text-decoration: underline !important; }

        .sticky-dashboard {
            position: -webkit-sticky;
            position: sticky;
            top: 0;
            z-index: 1020;
            background-color: #f8fafc;
            padding-top: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }
        
        .form-control, .form-select { font-size: 14px; padding: 8px 12px; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-maroon shadow-sm border-bottom border-warning py-3">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <img src="assets/Logo_Tapak_Suci_Galunggung.jpeg" alt="Logo" width="40" height="40" onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/3/30/Logo_Tapak_Suci_Putera_Muhammadiyah.png';">
                <span class="fw-bold text-gold" style="font-size: 16px; letter-spacing: 0.5px;">SPP TAPAK SUCI GALUNGGUNG</span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-warning text-dark fw-bold px-3 py-2 fs-6">Buku: <?= $tahun_aktif; ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm fw-medium px-3 border-secondary" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem Admin?');">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 my-4">
        <?= $message; ?>

        <div class="sticky-dashboard">
            <div class="row g-3 mb-3">
                
                <div class="col-lg-5">
                    <div class="card p-3 shadow-sm border-0 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted fw-bold" style="font-size: 12px; letter-spacing: 0.5px;">REKAPITULASI SPP BULANAN</span>
                            
                            <form method="GET" action="" id="formFilterBulan">
                                <select name="filter_bulan" class="form-select form-select-sm fw-bold border-success text-success py-1 px-2" style="font-size: 13px; width: 140px; height: 32px;" onchange="document.getElementById('formFilterBulan').submit();">
                                    <?php foreach ($list_bulan as $b): 
                                        $selected = ($b === $bulan_pilihan) ? 'selected' : '';
                                        $label_tambahan = ($b === $bulan_default) ? ' (Kini)' : '';
                                    ?>
                                        <option value="<?= $b; ?>" <?= $selected; ?>><?= $b . $label_tambahan; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <h2 class="fw-bold text-success mb-3" style="font-size: 26px;">Rp <?= number_format($total_kas_bulan_pilihan, 0, ',', '.'); ?></h2>
                        
                        <div class="row text-center g-2">
                            <div class="col-4">
                                <div class="p-2 rounded lunas-40k"><small class="d-block text-muted fw-semibold" style="font-size: 10px;">40K</small><span class="fw-bold" style="font-size: 13px;">Rp <?= number_format($bulan_40k, 0, ',', '.'); ?></span></div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded lunas-50k"><small class="d-block text-muted fw-semibold" style="font-size: 10px;">50K</small><span class="fw-bold" style="font-size: 13px;">Rp <?= number_format($bulan_50k, 0, ',', '.'); ?></span></div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded lunas-60k"><small class="d-block text-muted fw-semibold" style="font-size: 10px;">60K</small><span class="fw-bold" style="font-size: 13px;">Rp <?= number_format($bulan_60k, 0, ',', '.'); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card p-3 shadow-sm border-0 bg-white h-100" style="border-left: 4px solid #475569;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted fw-bold" style="font-size: 12px; letter-spacing: 0.5px;">TOTAL SPP KESELURUHAN (AKUMULASI)</span>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary font-monospace" style="font-size: 11px;">Jan - Des <?= $tahun_aktif; ?></span>
                        </div>
                        <h2 class="fw-bold text-dark mb-3" style="font-size: 26px;">Rp <?= number_format($total_spp_semua, 0, ',', '.'); ?></h2>

                        <div class="row text-center g-2">
                            <div class="col-4">
                                <div class="p-2 rounded lunas-40k"><small class="d-block text-muted fw-semibold" style="font-size: 10px;">Total 40K</small><span class="fw-bold" style="font-size: 13px;">Rp <?= number_format($semua_40k, 0, ',', '.'); ?></span></div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded lunas-50k"><small class="d-block text-muted fw-semibold" style="font-size: 10px;">Total 50K</small><span class="fw-bold" style="font-size: 13px;">Rp <?= number_format($semua_50k, 0, ',', '.'); ?></span></div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 rounded lunas-60k"><small class="d-block text-muted fw-semibold" style="font-size: 10px;">Total 60K</small><span class="fw-bold" style="font-size: 13px;">Rp <?= number_format($semua_60k, 0, ',', '.'); ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-2 d-flex flex-column justify-content-between gap-2">
                    <div class="card card-stat-kontras shadow-sm p-2 text-center rounded-3">
                        <span class="text-gold d-block fw-bold small" style="font-size: 11px; letter-spacing: 0.5px;">TOTAL SISWA</span>
                        <h4 class="fw-bold m-0 text-white mt-1" style="font-size: 22px;"><?= count($siswa_data); ?> <span style="font-size: 13px;" class="text-light opacity-75">Siswa</span></h4>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <a href="transaksi.php" class="btn btn-dark py-1.5 shadow-sm fw-semibold btn-sm w-100" style="font-size: 13px;">
                            <i class="bi bi-clock-history me-1"></i> Log Transaksi
                        </a>
                        <button class="btn btn-danger bg-maroon py-1.5 shadow-sm fw-semibold btn-sm w-100" style="font-size: 13px;" data-bs-toggle="modal" data-bs-target="#modalBayarCepat">
                            <i class="bi bi-cash-coin me-1"></i> Input Transaksi
                        </button>
                        <button class="btn btn-gold py-1.5 shadow-sm fw-semibold btn-sm w-100" style="font-size: 13px;" data-bs-toggle="modal" data-bs-target="#modalTambahSiswa">
                            <i class="bi bi-person-plus-fill me-1"></i> + Siswa Baru
                        </button>
                    </div>
                </div>
            </div>

            <div class="card card-body border-0 shadow-sm py-2">
                <input type="text" id="searchInput" class="form-control form-control-lg" placeholder="Cari nama siswa atau ID nomor urut..." style="font-size: 15px;">
            </div>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" id="siswaTable">
                    <thead>
                        <tr>
                            <th class="table-header-maroon" style="width: 70px;">ID</th>
                            <th class="table-header-maroon text-start" style="min-width: 200px; font-size: 14px;">Nama Lengkap</th>
                            <?php foreach ($list_bulan as $b): ?>
                                <th class="table-header-maroon" style="font-size: 13px;"><?= substr($b, 0, 3); ?></th>
                            <?php endforeach; ?>
                            <th class="table-header-maroon" style="width: 80px; font-size: 13px;">Menu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($siswa_data)): foreach ($siswa_data as $siswa): 
                                $id = $siswa['id']; 
                                $nama = $siswa['nama'];
                        ?>
                            <tr>
                                <td class="text-center fw-bold text-secondary bg-light" style="font-size: 14px; padding: 12px 8px;"><?= $id; ?></td>
                                <td style="font-size: 14px; padding: 12px 8px;">
                                    <a href="detail.php?no_siswa=<?= urlencode($id); ?>" class="fw-semibold text-dark text-decoration-none d-block custom-link-nama" title="Klik untuk lihat Kartu SPP">
                                        <?= htmlspecialchars($nama); ?> <i class="bi bi-arrow-right-short opacity-50"></i>
                                    </a>
                                </td>
                                
                                <?php foreach ($list_bulan as $bulan): ?>
                                    <?php if (isset($pembayaran_map[$id][$bulan])): 
                                        $v = $pembayaran_map[$id][$bulan]; 
                                        if ($v == 40000) { $bg_class = "lunas-40k"; $icon_color = "text-success"; } 
                                        elseif ($v == 50000) { $bg_class = "lunas-50k"; $icon_color = "text-info"; } 
                                        elseif ($v == 60000) { $bg_class = "lunas-60k"; $icon_color = "text-primary"; } 
                                        else { $bg_class = "bg-light text-dark"; $icon_color = "text-secondary"; }
                                    ?>
                                        <td class="cell-bulan <?= $bg_class; ?>" title="Lunas: Rp <?= number_format($v,0,',','.'); ?>" style="padding: 10px 4px;">
                                            <i class="bi bi-check-circle-fill <?= $icon_color; ?> fs-5"></i>
                                            <span class="d-block opacity-75" style="font-size: 9px; font-weight: bold; margin-top: 1px;"><?= ($v/1000); ?>K</span>
                                        </td>
                                    <?php else: ?>
                                        <td class="cell-bulan status-belum" style="padding: 10px 4px;">
                                            <i class="bi bi-dash-circle text-danger opacity-25 fs-5"></i>
                                        </td>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm border shadow-sm py-1 px-2.5" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-gear-fill text-secondary fs-6"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow fs-6">
                                            <li><a class="dropdown-item fw-medium py-2" href="detail.php?no_siswa=<?= urlencode($id); ?>"><i class="bi bi-file-text text-primary me-2"></i>Riwayat Kartu</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item fw-medium py-2 text-warning btn-edit-trigger" data-id="<?= $id; ?>" data-nama="<?= htmlspecialchars($nama); ?>"><i class="bi bi-pencil-square me-2"></i>Edit Nama</button></li>
                                            <li><button class="dropdown-item fw-medium py-2 text-danger btn-delete-trigger" data-id="<?= $id; ?>" data-nama="<?= htmlspecialchars($nama); ?>"><i class="bi bi-trash3-fill me-2"></i>Hapus Murid</button></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="15" class="text-center py-4 text-muted fs-6">Data kosong. Periksa Spreadsheet Anda.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTambahSiswa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content fs-6">
                <div class="modal-header bg-maroon text-white">
                    <h5 class="modal-title fw-bold">Tambah Siswa Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_siswa">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nomor ID Siswa (Unik)</label>
                            <input type="text" name="no_siswa" class="form-control form-control-lg" required placeholder="Contoh: 15" style="font-size:14px;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama_siswa" class="form-control form-control-lg" required placeholder="Nama lengkap siswa" style="font-size:14px;">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="submit" class="btn btn-danger bg-maroon w-100 py-2 fs-6">Daftarkan Siswa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditSiswa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content fs-6">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-warning"></i> Edit Data Siswa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit_siswa">
                    <input type="hidden" name="old_id" id="editOldId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-muted fw-semibold">ID Nomor Siswa</label>
                            <input type="text" name="no_siswa" id="editNoSiswa" class="form-control" required style="font-size:14px;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted fw-semibold">Ubah Nama Lengkap</label>
                            <input type="text" name="nama_siswa" id="editNamaSiswa" class="form-control" required style="font-size:14px;">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary px-3 py-2 fs-6" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4 py-2 fs-6">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDeleteSiswa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-danger fs-6">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="delete_siswa">
                    <input type="hidden" name="no_siswa" id="deleteNoSiswa">
                    <div class="modal-body text-center pt-4 pb-3">
                        <i class="bi bi-exclamation-triangle-fill text-danger" style="font-size: 40px;"></i>
                        <h5 class="fw-bold mt-2">Hapus Siswa Ini?</h5>
                        <p class="text-muted px-3 fs-6" id="deleteTextLabel"></p>
                    </div>
                    <div class="d-flex border-top">
                        <button type="button" class="btn btn-light w-50 py-3 rounded-0 border-end fs-6" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger w-50 py-3 rounded-0 fw-bold fs-6">Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalBayarCepat" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content fs-6">
                <div class="modal-header bg-maroon text-white">
                    <h5 class="modal-title fw-bold">Input Setoran SPP</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="quick_bayar">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Siswa</label>
                            <select name="no_siswa_bayar" class="form-select form-control-lg" required style="font-size:14px;">
                                <option value="" selected disabled>-- Pilih Siswa --</option>
                                <?php foreach ($siswa_data as $sw): ?>
                                    <option value="<?= $sw['id']; ?>">[ID: <?= $sw['id']; ?>] <?= $sw['nama']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bulan</label>
                            <select name="bulan_iuran" class="form-select form-control-lg" required style="font-size:14px;">
                                <option value="" selected disabled>-- Pilih Bulan --</option>
                                <?php foreach ($list_bulan as $b) { echo "<option value='$b'>$b</option>"; } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nominal Pembayaran</label>
                            <div class="d-flex flex-column gap-2 fs-6">
                                <div class="form-check p-2.5 border rounded"><input class="form-check-input ms-1" type="radio" name="nominal_bayar" value="40000" checked><label class="form-check-label ms-3 fw-bold">Rp 40.000</label></div>
                                <div class="form-check p-2.5 border rounded"><input class="form-check-input ms-1" type="radio" name="nominal_bayar" value="50000"><label class="form-check-label ms-3 fw-bold">Rp 50.000</label></div>
                                <div class="form-check p-2.5 border rounded"><input class="form-check-input ms-1" type="radio" name="nominal_bayar" value="60000"><label class="form-check-label ms-3 fw-bold">Rp 60.000</label></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="submit" class="btn btn-gold w-100 py-2.5 fs-6">Simpan Setoran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#siswaTable tbody tr').forEach(row => {
                const id = row.cells[0]?.textContent.toLowerCase() || '';
                const name = row.cells[1]?.textContent.toLowerCase() || '';
                row.style.display = (id.includes(filter) || name.includes(filter)) ? "" : "none";
            });
        });

        document.querySelectorAll('.btn-edit-trigger').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('editOldId').value = this.dataset.id;
                document.getElementById('editNoSiswa').value = this.dataset.id;
                document.getElementById('editNamaSiswa').value = this.dataset.nama;
                new bootstrap.Modal(document.getElementById('modalEditSiswa')).show();
            });
        });

        document.querySelectorAll('.btn-delete-trigger').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('deleteNoSiswa').value = this.dataset.id;
                document.getElementById('deleteTextLabel').innerText = "Tindakan ini akan menghapus '" + this.dataset.nama + "' dari database.";
                new bootstrap.Modal(document.getElementById('modalDeleteSiswa')).show();
            });
        });
    </script>
</body>
</html>