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

// Menentukan Bulan Default Saat Ini secara otomatis berdasarkan penanggalan server
$bulan_angka = (int)date('m'); 
$bulan_default = $list_bulan[$bulan_angka - 1]; 

// FITUR FILTER BULAN
$bulan_pilihan = isset($_GET['filter_bulan']) ? htmlspecialchars(trim($_GET['filter_bulan'])) : $bulan_default;
// Mengamankan tahun secara dinamis
$tahun_aktif = date('Y'); 

// ==========================================
// 2. PROSES POST: CRUD MASTER SISWA & PEMBAYARAN QUICK BAYAR
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ACTION A: TAMBAH SISWA BARU
    if (isset($_POST['action']) && $_POST['action'] === 'add_siswa') {
        $no_urut = trim($_POST['no_siswa']);
        $nama_siswa = trim($_POST['nama_siswa']);
        
        if (!empty($no_urut) && !empty($nama_siswa)) {
            sheets_append('Master_siswa', [$no_urut, $nama_siswa]);
            $_SESSION['flash_message'] = "Siswa baru '$nama_siswa' berhasil ditambahkan ke sistem pusat!";
        }
        header("Location: index.php?filter_bulan=" . urlencode($bulan_pilihan));
        exit();
    }
    
    // ACTION B: EDIT SISWA
    if (isset($_POST['action']) && $_POST['action'] === 'edit_siswa') {
        $old_id = trim($_POST['old_id']);
        $new_id = trim($_POST['no_siswa']);
        $new_nama = trim($_POST['nama_siswa']);
        
        if (!empty($old_id) && !empty($new_nama)) {
            sheets_append('Master_siswa', [$new_id, $new_nama], 'update', $old_id);
            $_SESSION['flash_message'] = "Biodata siswa berhasil diperbarui!";
        }
        header("Location: index.php?filter_bulan=" . urlencode($bulan_pilihan));
        exit();
    }

    // ACTION C: HAPUS SISWA
    if (isset($_POST['action']) && $_POST['action'] === 'delete_siswa') {
        $id_hapus = trim($_POST['no_siswa']);
        if (!empty($id_hapus)) {
            sheets_append('Master_siswa', [], 'delete', $id_hapus);
            $_SESSION['flash_message'] = "Siswa dengan ID #$id_hapus berhasil dihapus.";
        }
        header("Location: index.php?filter_bulan=" . urlencode($bulan_pilihan));
        exit();
    }

    // ACTION D: QUICK BAYAR (DARI TOMBOL MINUS DI TABEL)
    if (isset($_POST['action']) && $_POST['action'] === 'quick_bayar') {
        $id_siswa = trim($_POST['siswa_id']);
        $bulan_bayar = trim($_POST['bulan']);
        $nominal = trim($_POST['nominal']);
        $timestamp = date('Y-m-d H:i:s');
        
        if (!empty($id_siswa) && !empty($bulan_bayar) && !empty($nominal)) {
            sheets_append('Transaksi_luran', [$id_siswa, $bulan_bayar, $tahun_aktif, $nominal, $timestamp]);
            $_SESSION['flash_message'] = "Transaksi iuran siswa #$id_siswa bulan $bulan_bayar berhasil diproses!";
        }
        header("Location: index.php?filter_bulan=" . urlencode($bulan_pilihan));
        exit();
    }
}

// ==========================================
// 3. READ DATA DARI CORE GOOGLE SHEETS
// ==========================================
$siswa_raw = sheets_read('Master_siswa');
$transaksi_raw = sheets_read('Transaksi_luran');

// Filter & parsing master data siswa
$list_siswa = [];
if (!empty($siswa_raw)) {
    foreach ($siswa_raw as $index => $row) {
        if ($index === 0 || empty($row[0])) continue;
        $list_siswa[] = [
            'id' => trim($row[0]),
            'nama' => isset($row[1]) ? trim($row[1]) : '-'
        ];
    }
    // Mengurutkan nomor urut terkecil ke terbesar secara rapi
    usort($list_siswa, function($a, $b) { return (int)$a['id'] - (int)$b['id']; });
}

// Rekapitulasi Pembayaran Log
$payment_matrix = []; 
$total_kas_semua = 0; 
$rekap_bulan_pilihan = ['total_uang' => 0, 'total_lunas' => 0];
$akumulasi_nominal = ['40k' => 0, '50k' => 0, '60k' => 0];

if (!empty($transaksi_raw)) {
    foreach ($transaksi_raw as $index => $col) {
        if ($index === 0 || empty($col[0])) continue;
        
        $s_id = trim($col[0]);
        $bln  = trim($col[1]);
        $thn  = trim($col[2]);
        $nom  = (int)trim($col[3]);
        
        if ($thn == $tahun_aktif) {
            $payment_matrix[$s_id][$bln] = true;
            $total_kas_semua += $nom;
            
            if ($bln === $bulan_pilihan) {
                $rekap_bulan_pilihan['total_uang'] += $nom;
                $rekap_bulan_pilihan['total_lunas']++;
            }
            
            if ($nom === 40000) $akumulasi_nominal['40k'] += $nom;
            if ($nom === 50000) $akumulasi_nominal['50k'] += $nom;
            if ($nom === 60000) $akumulasi_nominal['60k'] += $nom;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - E-System SPP Tapak Suci</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/jpeg" href="assets/Logo_Tapak_Suci_Galunggung.jpeg">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-custom { background-color: #8B0000; border-bottom: 4px solid #FFD700; }
        .card-summary { border: none; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); background-color: white; }
        .table-container { background: white; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; }
        .btn-quick-pay { width: 30px; height: 30px; border-radius: 50%; padding: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 14px; border: 2px solid transparent; transition: all 0.2s; }
        .btn-quick-pay.paid { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; cursor: default; }
        .btn-quick-pay.unpaid { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }
        .btn-quick-pay.unpaid:hover { background-color: #8B0000; color: white; border-color: #8B0000; transform: scale(1.15); }
    </style>
</head>
<body>

    <!-- NAVBAR PREMIUM WITH NEW EMBEDDED LOGO -->
    <nav class="navbar navbar-dark navbar-custom py-3 shadow-sm">
        <div class="container-fluid px-4">
            <span class="navbar-brand mb-0 h1 fw-bold text-uppercase tracking-wider d-flex align-items-center">
                <img src="assets/Logo_Tapak_Suci_Galunggung.jpeg" alt="Logo" class="rounded-circle me-2" style="width: 35px; height: 35px; object-fit: cover; border: 2px solid #FFD700;">
                SPP TAPAK SUCI GALUNGGUNG
            </span>
            <div class="d-flex gap-2">
                <a href="transaksi.php" class="btn btn-warning fw-semibold rounded-pill px-3 shadow-sm"><i class="bi bi-clock-history me-1"></i> Buku: 2026</a>
                <a href="logout.php" class="btn btn-outline-light rounded-pill px-3"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid p-4">
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 px-4 py-3 mb-4" role="alert">
                <i class="bi bi-check-circle-fill fs-5 me-2 align-middle"></i>
                <span class="align-middle fw-medium"><?= $message; ?></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card-summary p-4 h-100 position-relative">
                    <span class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 11px;">Rekapitulasi SPP Bulanan</span>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h2 class="fw-bold text-success m-0" style="font-size: 30px;">Rp <?= number_format($rekap_bulan_pilihan['total_uang'], 0, ',', '.'); ?></h2>
                        <form method="GET" action="" id="formFilterBulan">
                            <select name="filter_bulan" onchange="document.getElementById('formFilterBulan').submit()" class="form-select form-select-sm border-secondary-subtle rounded-3 fw-semibold text-success bg-success-subtle px-3 py-1">
                                <?php foreach ($list_bulan as $b): ?>
                                    <option value="<?= $b; ?>" <?= ($b === $bulan_pilihan) ? 'selected' : ''; ?>>
                                        <?= $b; ?><?= ($b === $bulan_default) ? ' (Kini)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="row g-2 mt-2 border-top pt-3">
                        <div class="col-4 text-center"><small class="text-muted d-block" style="font-size:10px;">40K</small><span class="badge text-success bg-success-subtle rounded-pill px-2">Rp 0</span></div>
                        <div class="col-4 text-center"><small class="text-muted d-block" style="font-size:10px;">50K</small><span class="badge text-primary bg-primary-subtle rounded-pill px-2">Rp 0</span></div>
                        <div class="col-4 text-center"><small class="text-muted d-block" style="font-size:10px;">60K</small><span class="badge text-purple bg-purple-subtle rounded-pill px-2" style="color:#6f42c1; background:#efebf7">Rp 0</span></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-5">
                <div class="card-summary p-4 h-100">
                    <span class="text-uppercase text-muted fw-bold d-block mb-1" style="font-size: 11px;">Total SPP Keseluruhan (Akumulasi)</span>
                    <h2 class="fw-bold text-dark mb-3" style="font-size: 26px;">Rp <?= number_format($total_kas_semua, 0, ',', '.'); ?></h2>
                    <div class="row g-2 border-top pt-3">
                        <div class="col-4 text-center"><small class="text-muted d-block" style="font-size:11px;">Total 40K</small><span class="fw-semibold text-success">Rp <?= number_format($akumulasi_nominal['40k'], 0, ',', '.'); ?></span></div>
                        <div class="col-4 text-center"><small class="text-muted d-block" style="font-size:11px;">Total 50K</small><span class="fw-semibold text-primary">Rp <?= number_format($akumulasi_nominal['50k'], 0, ',', '.'); ?></span></div>
                        <div class="col-4 text-center"><small class="text-muted d-block" style="font-size:11px;">Total 60K</small><span class="fw-semibold" style="color:#6f42c1;">Rp <?= number_format($akumulasi_nominal['60k'], 0, ',', '.'); ?></span></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="card p-4 h-100 text-white shadow-sm border-0" style="background-color: #8B0000; border-radius: 15px;">
                    <span class="text-uppercase text-white-50 fw-bold d-block mb-1" style="font-size: 11px;">Total Siswa</span>
                    <div class="d-flex align-items-baseline gap-2 mb-3">
                        <h1 class="fw-bold m-0" style="font-size: 45px;"><?= count($list_siswa); ?></h1>
                        <span class="fs-5 text-white-50">Siswa</span>
                    </div>
                    <div class="d-grid mt-auto">
                        <button class="btn btn-warning fw-semibold rounded-pill" data-bs-toggle="modal" data-bs-target="#modalAddSiswa"><i class="bi bi-person-plus-fill me-1"></i> + Siswa Baru</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-3 g-2">
            <div class="col-12 col-md-9">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 rounded-start-pill ps-3"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0 rounded-end-pill py-2" placeholder="Cari nama siswa atau ID nomor urut...">
                </div>
            </div>
            <div class="col-12 col-md-3">
                <a href="transaksi.php" class="btn btn-dark w-100 rounded-pill py-2 fw-semibold"><i class="bi bi-list-stars me-1"></i> Log Transaksi</a>
            </div>
        </div>

        <div class="table-container shadow-sm border">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-nowrap text-center" id="siswaTable">
                    <thead class="table-dark">
                        <tr class="align-middle">
                            <th style="width: 50px;" class="py-3">ID</th>
                            <th class="text-start ps-3">NAMA LENGKAP</th>
                            <?php foreach ($list_bulan as $b): ?>
                                <th style="font-size: 12px;" class="text-uppercase"><?= substr($b, 0, 3); ?></th>
                            <?php endforeach; ?>
                            <th style="width: 80px;" class="pe-3">MENU</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($list_siswa)): foreach ($list_siswa as $siswa): ?>
                            <tr>
                                <td class="fw-bold text-muted py-3"><?= $siswa['id']; ?></td>
                                <td class="text-start fw-semibold text-dark ps-3">
                                    <a href="detail.php?no_siswa=<?= $siswa['id']; ?>" class="text-decoration-none text-dark"><?= $siswa['nama']; ?> <i class="bi bi-arrow-up-right-short text-muted" style="font-size:12px;"></i></a>
                                </td>
                                
                                <?php foreach ($list_bulan as $bulan): 
                                    $is_paid = isset($payment_matrix[$siswa['id']][$bulan]);
                                ?>
                                    <td>
                                        <?php if ($is_paid): ?>
                                            <span class="btn-quick-pay paid"><i class="bi bi-check-lg"></i></span>
                                        <?php else: ?>
                                            <button type="button" class="btn-quick-pay unpaid" data-id="<?= $siswa['id']; ?>" data-nama="<?= $siswa['nama']; ?>" data-bulan="<?= $bulan; ?>"><i class="bi bi-dash"></i></button>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>

                                <td class="pe-3">
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm border shadow-sm py-1 px-2" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-gear-fill text-secondary"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                                            <li><a class="dropdown-item btn-edit-trigger" href="#" data-id="<?= $siswa['id']; ?>" data-nama="<?= $siswa['nama']; ?>"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Nama</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item btn-delete-trigger text-danger" href="#" data-id="<?= $siswa['id']; ?>" data-nama="<?= $siswa['nama']; ?>"><i class="bi bi-trash-fill me-2"></i>Hapus Permanen</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="15" class="text-center text-muted py-5 fs-5">Data induk siswa kosong, silakan tambah siswa baru.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL POPUP SYSTEM -->
    <div class="modal fade" id="modalAddSiswa" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content border-0 rounded-4 shadow" method="POST" action=""><input type="hidden" name="action" value="add_siswa"><div class="modal-header bg-dark text-white rounded-top-4"><h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Registrasi Siswa Baru</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><div class="mb-3"><label class="form-label fw-semibold">Nomor ID Urut</label><input type="number" name="no_siswa" class="form-control" placeholder="Contoh: 119" required></div><div class="mb-2"><label class="form-label fw-semibold">Nama Lengkap Siswa</label><input type="text" name="nama_siswa" class="form-control" placeholder="Masukkan nama resmi siswa" required></div></div><div class="modal-footer border-0 p-3 bg-light rounded-bottom-4"><button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success rounded-pill px-4 fw-semibold">Simpan Data</button></div></form></div></div>
    <div class="modal fade" id="modalEditSiswa" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form class="modal-content border-0 rounded-4 shadow" method="POST" action=""><input type="hidden" name="action" value="edit_siswa"><input type="hidden" name="old_id" id="editOldId"><div class="modal-header bg-primary text-white rounded-top-4"><h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Perbarui Informasi Siswa</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><div class="mb-3"><label class="form-label fw-semibold">Nomor ID Urut</label><input type="number" name="no_siswa" id="editNoSiswa" class="form-control" required></div><div class="mb-2"><label class="form-label fw-semibold">Nama Lengkap Siswa</label><input type="text" name="nama_siswa" id="editNamaSiswa" class="form-control" required></div></div><div class="modal-footer border-0 p-3 bg-light rounded-bottom-4"><button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">Simpan Perubahan</button></div></form></div></div>
    <div class="modal fade" id="modalDeleteSiswa" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><form class="modal-content border-0 rounded-4 shadow" method="POST" action=""><input type="hidden" name="action" value="delete_siswa"><input type="hidden" name="no_siswa" id="deleteNoSiswa"><div class="modal-body p-4 text-center"><i class="bi bi-exclamation-octagon text-danger display-4 mb-3 d-block"></i><h5 class="fw-bold mb-2 text-dark">Hapus Data Permanen?</h5><p class="text-muted mb-0 px-2" style="font-size:13px;" id="deleteTextLabel"></p></div><div class="modal-footer border-0 p-3 bg-light d-flex justify-content-center gap-2 rounded-bottom-4"><button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-danger btn-sm rounded-pill px-3 fw-semibold">Ya, Hapus!</button></div></form></div></div>
    <div class="modal fade" id="modalQuickBayar" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><form class="modal-content border-0 rounded-4 shadow" method="POST" action=""><input type="hidden" name="action" value="quick_bayar"><input type="hidden" name="siswa_id" id="qbSiswaId"><input type="hidden" name="bulan" id="qbBulan"><div class="modal-header bg-success text-white rounded-top-4 py-2"><h6 class="modal-title fw-bold"><i class="bi bi-wallet2 me-1"></i> Input Iuran SPP</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-3 text-center"><p class="mb-1 text-muted text-uppercase fw-bold" style="font-size:10px;">Konfirmasi Pembayaran</p><h6 class="fw-bold text-dark mb-1" id="qbNamaLabel">-</h6><span class="badge bg-secondary mb-3 px-3 rounded-pill" id="qbBulanLabel">-</span><label class="form-label fw-semibold d-block text-start text-secondary mb-2" style="font-size:12px;">Pilih Besaran Nominal Kas:</label><div class="d-grid gap-2"><button type="submit" name="nominal" value="40000" class="btn btn-outline-success text-start px-3 py-2 border-2 rounded-3"><i class="bi bi-check-circle me-2"></i> Rp 40.000</button><button type="submit" name="nominal" value="50000" class="btn btn-outline-primary text-start px-3 py-2 border-2 rounded-3"><i class="bi bi-check-circle me-2"></i> Rp 50.000</button><button type="submit" name="nominal" value="60000" class="btn btn-outline-dark text-start px-3 py-2 border-2 rounded-3"><i class="bi bi-check-circle me-2"></i> Rp 60.000</button></div></div></form></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.btn-quick-pay.unpaid').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('qbSiswaId').value = this.getAttribute('data-id');
                document.getElementById('qbBulan').value = this.getAttribute('data-bulan');
                document.getElementById('qbNamaLabel').innerText = this.getAttribute('data-nama');
                document.getElementById('qbBulanLabel').innerText = "Bulan " + this.getAttribute('data-bulan') + " (<?= $tahun_aktif; ?>)";
                new bootstrap.Modal(document.getElementById('modalQuickBayar')).show();
            });
        });

        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#siswaTable tbody tr').forEach(row => {
                const id = row.cells[0]?.textContent.toLowerCase() || '';
                const name = row.cells[1]?.textContent.toLowerCase() || '';
                row.style.display = (id.includes(filter) || name.includes(filter)) ? "" : "none";
            });
        });

        document.querySelectorAll('.btn-edit-trigger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('editOldId').value = this.getAttribute('data-id');
                document.getElementById('editNoSiswa').value = this.getAttribute('data-id');
                document.getElementById('editNamaSiswa').value = this.getAttribute('data-nama');
                new bootstrap.Modal(document.getElementById('modalEditSiswa')).show();
            });
        });

        document.querySelectorAll('.btn-delete-trigger').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                document.getElementById('deleteNoSiswa').value = this.getAttribute('data-id');
                document.getElementById('deleteTextLabel').innerText = "Tindakan ini akan menghapus '" + this.getAttribute('data-nama') + "' dari database.";
                new bootstrap.Modal(document.getElementById('modalDeleteSiswa')).show();
            });
        });
    </script>
</body>
</html>