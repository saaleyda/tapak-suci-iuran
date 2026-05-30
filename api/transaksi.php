<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Memaksa PHP menggunakan Zona Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta'); 

require_once __DIR__ . '/google-sheets-client.php';

// 1. MEMBACA DATA DARI GOOGLE SHEETS
$siswa_raw = sheets_read('Master_siswa');
$transaksi_raw = sheets_read('Transaksi_luran');

// 2. BUAT MAPPING NAMA SISWA (Agar kita bisa memunculkan nama siswa, bukan cuma ID)
$siswa_map = [];
if (!empty($siswa_raw)) {
    foreach ($siswa_raw as $index => $s) {
        if ($index === 0 || empty($s[0])) continue;
        $siswa_map[trim($s[0])] = isset($s[1]) ? trim($s[1]) : 'Tanpa Nama';
    }
}

// 3. OLAH DATA TRANSAKSI
$list_transaksi = [];
if (!empty($transaksi_raw)) {
    foreach ($transaksi_raw as $index => $tx) {
        if ($index === 0 || empty($tx[0])) continue; // Lewati Header

        $id_siswa = trim($tx[0]);
        $bulan = isset($tx[1]) ? trim($tx[1]) : '-';
        $tahun = isset($tx[2]) ? trim($tx[2]) : '-';
        $nominal = isset($tx[3]) ? (int)preg_replace('/[^0-9]/', '', $tx[3]) : 0;
        
        // Perbaikan format Tanggal (Timestamp)
        $ts_raw = isset($tx[4]) ? trim($tx[4]) : '-';
        if (strpos($ts_raw, 'T') !== false || strlen($ts_raw) > 12) {
            $timestamp_seconds = strtotime($ts_raw);
            $ts_clean = $timestamp_seconds ? date('d-m-Y H:i:s', $timestamp_seconds) : $ts_raw;
        } else {
            $ts_clean = $ts_raw;
        }

        // Ambil nama berdasarkan ID dari map siswa
        $nama_siswa = isset($siswa_map[$id_siswa]) ? $siswa_map[$id_siswa] : "ID tidak dikenal ($id_siswa)";

        // Simpan ke array penampung
        $list_transaksi[] = [
            'id_siswa' => $id_siswa,
            'nama' => $nama_siswa,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'nominal' => $nominal,
            'timestamp' => $ts_clean
        ];
    }
}

// 4. URUTKAN DARI TRANSAKSI TERBARU KE TERLAMA (Reverse Array)
$list_transaksi = array_reverse($list_transaksi);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Riwayat Transaksi Terbaru</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        :root { --ts-maroon: #800000; --ts-gold: #FFD700; }
        .bg-maroon { background-color: var(--ts-maroon) !important; }
        .text-gold { color: var(--ts-gold) !important; }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05); overflow: hidden; }
        .table-header-dark { background-color: #1e293b !important; color: white !important; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        
        .nominal-40 { color: #166534; font-weight: bold; }
        .nominal-50 { color: #0369a1; font-weight: bold; }
        .nominal-60 { color: #6b21a8; font-weight: bold; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-maroon shadow-sm border-bottom border-warning py-2">
        <div class="container px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <i class="bi bi-arrow-left fs-5 text-gold"></i>
                <span class="fw-bold text-gold" style="font-size: 14px;">KEMBALI KE BERANDA</span>
            </a>
            <span class="badge bg-light text-dark fw-bold px-3 py-2">LOG AKTIVITAS KAS</span>
        </div>
    </nav>

    <div class="container px-4 my-4">
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold m-0 text-dark"><i class="bi bi-clock-history me-2 text-danger"></i>Riwayat Transaksi Masuk</h4>
                <p class="text-muted small m-0">Menampilkan seluruh data setoran iuran dimulai dari urutan yang paling baru diinput.</p>
            </div>
            <span class="badge bg-dark px-3 py-2 font-monospace">Total: <?= count($list_transaksi); ?> Transaksi</span>
        </div>

        <div class="card card-body border-0 shadow-sm mb-4 py-2">
            <input type="text" id="txSearchInput" class="form-control" placeholder="Cari berdasarkan nama siswa, ID, atau bulan...">
        </div>

        <div class="table-container border-0">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0" id="txTable">
                    <thead>
                        <tr>
                            <th class="table-header-dark text-center" style="width: 180px;">Waktu Input</th>
                            <th class="table-header-dark" style="width: 80px; text-align: center;">ID</th>
                            <th class="table-header-dark">Nama Siswa</th>
                            <th class="table-header-dark text-center" style="width: 140px;">Periode Buku</th>
                            <th class="table-header-dark text-end" style="width: 160px; padding-right: 20px;">Nominal Masuk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($list_transaksi)): foreach ($list_transaksi as $index => $tx): 
                            // Tentukan warna teks nominal agar menarik sesuai kategori
                            if ($tx['nominal'] == 40000) $nom_class = "nominal-40";
                            elseif ($tx['nominal'] == 50000) $nom_class = "nominal-50";
                            else $nom_class = "nominal-60";
                            
                            // Baris pertama (paling baru) diberi efek highlight soft green
                            $row_bg = ($index === 0) ? 'table-success bg-opacity-25' : '';
                        ?>
                            <tr class="<?= $row_bg; ?>">
                                <td class="text-center font-monospace text-secondary small fw-medium"><?= $tx['timestamp']; ?></td>
                                <td class="text-center bg-light font-monospace small fw-bold"><?= $tx['id_siswa']; ?></td>
                                <td>
                                    <a href="detail.php?no_siswa=<?= urlencode($tx['id_siswa']); ?>" class="text-dark fw-semibold text-decoration-none">
                                        <?= htmlspecialchars($tx['nama']); ?> <i class="bi bi-box-arrow-in-right opacity-25 small"></i>
                                    </a>
                                </td>
                                <td class="text-center fw-medium text-muted">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= $tx['bulan']; ?> <?= $tx['tahun']; ?></span>
                                </td>
                                <td class="text-end <?= $nom_class; ?> pe-4">
                                    Rp <?= number_format($tx['nominal'], 0, ',', '.'); ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat transaksi iuran yang tercatat.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Fitur Pencarian Realtime Log Transaksi
        document.getElementById('txSearchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('#txTable tbody tr').forEach(row => {
                const date = row.cells[0]?.textContent.toLowerCase() || '';
                const id = row.cells[1]?.textContent.toLowerCase() || '';
                const name = row.cells[2]?.textContent.toLowerCase() || '';
                const periode = row.cells[3]?.textContent.toLowerCase() || '';
                
                if (date.includes(filter) || id.includes(filter) || name.includes(filter) || periode.includes(filter)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        });
    </script>
</body>
</html>