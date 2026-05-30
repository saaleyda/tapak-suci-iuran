<?php
// 1. Memulai session di paling atas untuk mengamankan pesan alert setelah redirect
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/google-sheets-client.php';

$message = '';
// Ambil pesan dari session jika ada, lalu langsung hapus agar tidak muncul terus-menerus
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// ==========================================
// 2. PROSES POST: CRUD MASTER SISWA & IURAN
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // ACTION: TAMBAH SISWA (APPEND)
    if ($_POST['action'] === 'add_siswa') {
        $no_siswa = htmlspecialchars(trim($_POST['no_siswa']));
        $nama_siswa = htmlspecialchars(trim($_POST['nama_siswa']));
        if (!empty($no_siswa) && !empty($nama_siswa)) {
            if (sheets_append('Master_siswa', [$no_siswa, $nama_siswa], 'append')) {
                $_SESSION['flash_message'] = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
                                                <i class='bi bi-check-circle-fill me-2'></i> Siswa <b>$nama_siswa</b> berhasil didaftarkan.
                                                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                                              </div>";
            }
        }
        header("Location: index.php");
        exit();
    }
    
    // ACTION: EDIT DATA (UPDATE NAMA/ID)
    if ($_POST['action'] === 'edit_siswa') {
        $old_id = htmlspecialchars(trim($_POST['old_id']));
        $no_siswa = htmlspecialchars(trim($_POST['no_siswa']));
        $nama_siswa = htmlspecialchars(trim($_POST['nama_siswa']));
        if (!empty($old_id) && !empty($nama_siswa)) {
            if (sheets_append('Master_siswa', [$no_siswa, $nama_siswa], 'update', $old_id)) {
                $_SESSION['flash_message'] = "<div class='alert alert-info alert-dismissible fade show shadow-sm' role='alert'>
                                                <i class='bi bi-pencil-square me-2'></i> Data siswa berhasil diperbarui menjadi <b>$nama_siswa</b>.
                                                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                                              </div>";
            }
        }
        header("Location: index.php");
        exit();
    }
    
    // ACTION: HAPUS SISWA (DELETE)
    if ($_POST['action'] === 'delete_siswa') {
        $no_siswa = htmlspecialchars(trim($_POST['no_siswa']));
        if (!empty($no_siswa)) {
            if (sheets_append('Master_siswa', [], 'delete', $no_siswa)) {
                $_SESSION['flash_message'] = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>
                                                <i class='bi bi-trash3-fill me-2'></i> Siswa dengan ID <b>$no_siswa</b> telah dihapus.
                                                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                                              </div>";
            }
        }
        header("Location: index.php");
        exit();
    }

    // ACTION: QUICK BAYAR IURAN (3 OPSI)
    if ($_POST['action'] === 'quick_bayar') {
        $no_siswa = htmlspecialchars(trim($_POST['no_siswa_bayar']));
        $bulan = htmlspecialchars(trim($_POST['bulan_iuran']));
        $nominal = htmlspecialchars(trim($_POST['nominal_bayar']));
        $tahun = '2026';

        if (!empty($no_siswa) && !empty($bulan) && !empty($nominal)) {
            if (sheets_append('Transaksi_luran', [$no_siswa, $bulan, $tahun, $nominal], 'append')) {
                $_SESSION['flash_message'] = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
                                                <i class='bi bi-cash-coin me-2'></i> Setoran Bulan <b>$bulan</b> sebesar Rp " . number_format($nominal, 0, ',', '.') . " sukses disimpan.
                                                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                                              </div>";
            }
        }
        header("Location: index.php");
        exit();
    }
}

// ==========================================
// 3. MEMBACA DATA & MAPPING DARI GOOGLE SHEETS
// ==========================================
$siswa_raw = sheets_read('Master_siswa');
$transaksi_raw = sheets_read('Transaksi_luran');
$list_bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$pembayaran_map = [];
$total_kas_2026 = 0;

// Variabel Tambahan Informasi Nominal Unik
$total_40k = 0;
$total_50k = 0;
$total_60k = 0;

$siswa_data = [];

// Olah Transaksi Iuran (Mendukung Huruf Besar Maupun Kecil pada Nama Bulan)
if (!empty($transaksi_raw)) {
    foreach ($transaksi_raw as $index => $tx) {
        if ($index === 0 || empty($tx[0])) continue; // Lewati Header
        
        if (trim($tx[2]) == '2026') {
            $no_siw = trim($tx[0]);
            // Buat pencocokan bulan menjadi huruf kecil awal kapital (Spt: Januari)
            $bln = ucfirst(strtolower(trim($tx[1]))); 
            $nominal_clean = isset($tx[3]) ? (int)preg_replace('/[^0-9]/', '', $tx[3]) : 0;
            
            $pembayaran_map[$no_siw][$bln] = $nominal_clean;
            $total_kas_2026 += $nominal_clean;

            // Hitung akumulasi per jenis nominal iuran
            if ($nominal_clean == 40000) {
                $total_40k += $nominal_clean;
            } elseif ($nominal_clean == 50000) {
                $total_50k += $nominal_clean;
            } elseif ($nominal_clean == 60000) {
                $total_60k += $nominal_clean;
            }
        }
    }
}

// Olah Data Master Siswa
if (!empty($siswa_raw)) {
    foreach ($siswa_raw as $index => $s) {
        if ($index === 0 || empty($s[0])) continue; 
        
        $id_siswa = trim($s[0]);
        // Abaikan jika baris tersebut diisi teks "No_Siswa" atau bukan berupa angka ID asli
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
    <title>Manajemen Iuran Tapak Suci Galunggung</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        :root { --ts-maroon: #800000; --ts-gold: #FFD700; }
        .bg-maroon { background-color: var(--ts-maroon) !important; }
        .text-gold { color: var(--ts-gold) !important; }
        .btn-gold { background-color: var(--ts-gold); color: #1e293b; font-weight: 600; border: 1px solid var(--ts-gold); }
        .btn-gold:hover { background-color: #e6b800; color: #000; }
        .card-stat { border: none; border-left: 4px solid var(--ts-maroon); background: white; height: 100%; }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); overflow: hidden; }
        .table-header-maroon { background-color: var(--ts-maroon) !important; color: white !important; vertical-align: middle; text-align: center; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .cell-bulan { text-align: center; font-size: 11px; font-weight: 700; min-width: 65px; vertical-align: middle; }
        
        /* WARNA KOTAK TABEL & SUB STATISTIK KAS */
        .lunas-40k { 
            color: #166534; 
            background-color: #f0fdf4; 
            border: 1px solid #bbf7d0 !important; 
        }
        .lunas-50k { 
            color: #0369a1; 
            background-color: #f0f9ff; 
            border: 1px solid #bae6fd !important; 
        }
        .lunas-60k { 
            color: #6b21a8; 
            background-color: #faf5ff; 
            border: 1px solid #f3e8ff !important; 
        }
        .status-belum { color: #b91c1c; background-color: #fef2f2; border: 1px solid #fee2e2 !important; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-maroon shadow-sm border-bottom border-warning py-2">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <img src="https://upload.wikimedia.org/wikipedia/commons/3/30/Logo_Tapak_Suci_Putera_Muhammadiyah.png" alt="Logo" width="35" height="35">
                <span class="fw-bold text-gold" style="font-size: 15px;">KAS UTAMA TAPAK SUCI GALUNGGUNG</span>
            </a>
            <span class="badge bg-warning text-dark fw-bold px-3 py-2">Buku: 2026</span>
        </div>
    </nav>

    <div class="container-fluid px-4 my-4">
        <?= $message; ?>

        <div class="row mb-4 g-3">
            <div class="col-lg-3 col-md-6">
                <div class="card card-stat shadow-sm p-3">
                    <span class="text-muted d-block mb-1" style="font-size: 11px; font-weight: 600;">TOTAL KAS MASUK</span>
                    <h3 class="fw-bold m-0 text-success">Rp <?= number_format($total_kas_2026, 0, ',', '.'); ?></h3>
                </div>
            </div>
            
            <div class="col-lg-5 col-md-6">
                <div class="card shadow-sm p-3 bg-white border-0" style="border-radius: 6px;">
                    <span class="text-muted d-block mb-2 text-center font-monospace" style="font-size: 10px; font-weight: bold;">RINCIAN BERDASARKAN NOMINAL</span>
                    <div class="row text-center g-2">
                        <div class="col-4">
                            <div class="p-1 rounded lunas-40k">
                                <small class="d-block text-muted" style="font-size: 9px;">Kategori 40K</small>
                                <span class="fw-bold" style="font-size: 12px;">Rp <?= number_format($total_40k, 0, ',', '.'); ?></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-1 rounded lunas-50k">
                                <small class="d-block text-muted" style="font-size: 9px;">Kategori 50K</small>
                                <span class="fw-bold" style="font-size: 12px;">Rp <?= number_format($total_50k, 0, ',', '.'); ?></span>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-1 rounded lunas-60k">
                                <small class="d-block text-muted" style="font-size: 9px;">Kategori 60K</small>
                                <span class="fw-bold" style="font-size: 12px;">Rp <?= number_format($total_60k, 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-6">
                <div class="card card-stat shadow-sm p-3" style="border-left-color: #0ea5e9;">
                    <span class="text-muted d-block mb-1" style="font-size: 11px; font-weight: 600;">TOTAL ANGGOTA</span>
                    <h3 class="fw-bold m-0 text-dark"><?= count($siswa_data); ?> <span style="font-size: 14px;" class="text-muted">Orang</span></h3>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-6 d-flex flex-column justify-content-center gap-2">
                <button class="btn btn-danger bg-maroon py-2 shadow-sm fw-semibold w-100" data-bs-toggle="modal" data-bs-target="#modalBayarCepat">
                    <i class="bi bi-cash-coin me-1"></i> Input Iuran
                </button>
                <button class="btn btn-gold py-2 shadow-sm fw-semibold w-100" data-bs-toggle="modal" data-bs-target="#modalTambahSiswa">
                    <i class="bi bi-person-plus-fill me-1"></i> + Siswa Baru
                </button>
            </div>
        </div>

        <div class="card card-body border-0 shadow-sm mb-4 py-2">
            <input type="text" id="searchInput" class="form-control" placeholder="Ketik nama atau ID siswa untuk memfilter data...">
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" id="siswaTable">
                    <thead>
                        <tr>
                            <th class="table-header-maroon" style="width: 60px;">ID</th>
                            <th class="table-header-maroon text-start" style="min-width: 180px;">Nama Lengkap</th>
                            <?php foreach ($list_bulan as $b): ?>
                                <th class="table-header-maroon"><?= substr($b, 0, 3); ?></th>
                            <?php endforeach; ?>
                            <th class="table-header-maroon" style="width: 70px;">Menu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($siswa_data)): foreach ($siswa_data as $siswa): 
                                $id = $siswa['id']; 
                                $nama = $siswa['nama'];
                        ?>
                            <tr>
                                <td class="text-center fw-bold text-secondary bg-light" style="font-size: 13px;"><?= $id; ?></td>
                                <td class="fw-semibold text-dark" style="font-size: 13px;"><?= htmlspecialchars($nama); ?></td>
                                
                                <?php foreach ($list_bulan as $bulan): ?>
                                    <?php if (isset($pembayaran_map[$id][$bulan])): 
                                        $v = $pembayaran_map[$id][$bulan]; 
                                        
                                        // Pengecekan logika penentuan warna berdasarkan nominal bayar
                                        if ($v == 40000) {
                                            $bg_class = "lunas-40k";
                                            $icon_color = "text-success";
                                        } elseif ($v == 50000) {
                                            $bg_class = "lunas-50k";
                                            $icon_color = "text-info";
                                        } elseif ($v == 60000) {
                                            $bg_class = "lunas-60k";
                                            $icon_color = "text-primary";
                                        } else {
                                            $bg_class = "bg-light text-dark";
                                            $icon_color = "text-secondary";
                                        }
                                    ?>
                                        <td class="cell-bulan <?= $bg_class; ?>" title="Lunas: Rp <?= number_format($v,0,',','.'); ?>">
                                            <i class="bi bi-check-circle-fill <?= $icon_color; ?> fs-6"></i>
                                            <span class="d-block opacity-75" style="font-size: 8px; font-weight: bold;"><?= ($v/1000); ?>K</span>
                                        </td>
                                    <?php else: ?>
                                        <td class="cell-bulan status-belum">
                                            <i class="bi bi-dash-circle text-danger opacity-25"></i>
                                        </td>
                                    <?php endif; ?>
                                <?php endforeach; ?>

                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-light btn-sm border shadow-sm py-1 px-2" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-gear-fill text-secondary"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow">
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
                                <td colspan="15" class="text-center py-4 text-muted">Data kosong. Periksa URL Web App atau Spreadsheet Anda.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTambahSiswa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-maroon text-white">
                    <h6 class="modal-title fw-bold">Tambah Murid Baru</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_siswa">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label font-monospace">Nomor ID Siswa (Unik)</label>
                            <input type="text" name="no_siswa" class="form-control" required placeholder="Contoh: 15">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_siswa" class="form-control" required placeholder="Nama lengkap siswa">
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="submit" class="btn btn-danger bg-maroon w-100 py-2">Daftarkan Anggota</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalEditSiswa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square text-warning"></i> Edit Data Anggota</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit_siswa">
                    <input type="hidden" name="old_id" id="editOldId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-muted">ID Nomor Siswa</label>
                            <input type="text" name="no_siswa" id="editNoSiswa" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted">Ubah Nama Lengkap</label>
                            <input type="text" name="nama_siswa" id="editNamaSiswa" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDeleteSiswa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-danger">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="delete_siswa">
                    <input type="hidden" name="no_siswa" id="deleteNoSiswa">
                    <div class="modal-body text-center pt-4">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-1"></i>
                        <h6 class="fw-bold mt-2">Hapus Anggota Ini?</h6>
                        <p class="text-muted px-2 small" id="deleteTextLabel"></p>
                    </div>
                    <div class="d-flex border-top">
                        <button type="button" class="btn btn-light w-50 py-2.5 rounded-0 border-end" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger w-50 py-2.5 rounded-0 fw-bold">Ya, Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalBayarCepat" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-maroon text-white">
                    <h6 class="modal-title fw-bold">Input Setoran Iuran</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="quick_bayar">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Siswa</label>
                            <select name="no_siswa_bayar" class="form-select" required>
                                <option value="" selected disabled>-- Pilih Siswa --</option>
                                <?php foreach ($siswa_data as $sw): ?>
                                    <option value="<?= $sw['id']; ?>">[ID: <?= $sw['id']; ?>] <?= $sw['nama']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bulan</label>
                            <select name="bulan_iuran" class="form-select" required>
                                <option value="" selected disabled>-- Pilih Bulan --</option>
                                <?php foreach ($list_bulan as $b) { echo "<option value='$b'>$b</option>"; } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nominal Pembayaran</label>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check p-2 border rounded"><input class="form-check-input ms-1" type="radio" name="nominal_bayar" value="40000" checked><label class="form-check-label ms-3 fw-bold">Rp 40.000</label></div>
                                <div class="form-check p-2 border rounded"><input class="form-check-input ms-1" type="radio" name="nominal_bayar" value="50000"><label class="form-check-label ms-3 fw-bold">Rp 50.000</label></div>
                                <div class="form-check p-2 border rounded"><input class="form-check-input ms-1" type="radio" name="nominal_bayar" value="60000"><label class="form-check-label ms-3 fw-bold">Rp 60.000</label></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="submit" class="btn btn-gold w-100 py-2">Simpan Setoran</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Realtime Filter Pencarian Nama / ID
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#siswaTable tbody tr').forEach(row => {
                const id = row.cells[0]?.textContent.toLowerCase() || '';
                const name = row.cells[1]?.textContent.toLowerCase() || '';
                row.style.display = (id.includes(filter) || name.includes(filter)) ? "" : "none";
            });
        });

        // Trigger Data ke Modal Edit
        document.querySelectorAll('.btn-edit-trigger').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('editOldId').value = this.dataset.id;
                document.getElementById('editNoSiswa').value = this.dataset.id;
                document.getElementById('editNamaSiswa').value = this.dataset.nama;
                new bootstrap.Modal(document.getElementById('modalEditSiswa')).show();
            });
        });

        // Trigger Data ke Modal Delete
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