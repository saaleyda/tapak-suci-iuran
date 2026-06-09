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
        $nominal = isset($tx[3]) ? (int)trim($tx[3]) : 0;
        $waktu = isset($tx[4]) ? trim($tx[4]) : '-';

        // Format visual tanggal transaksi
        $tanggal_format = '-';
        if ($waktu !== '-') {
            $ts = strtotime(str_replace('/', '-', $waktu));
            $tanggal_format = ($ts !== false) ? date('d M Y • H:i', $ts) . ' WIB' : $waktu;
        }

        $list_transaksi[] = [
            'tanggal' => $tanggal_format,
            'id_siswa' => $id_siswa,
            'nama_siswa' => isset($siswa_map[$id_siswa]) ? $siswa_map[$id_siswa] : 'Siswa Non-Aktif',
            'periode' => "$bulan $tahun",
            'nominal' => $nominal
        ];
    }
    // Urutkan transaksi terbaru di paling atas (Descending)
    $list_transaksi = array_reverse($list_transaksi);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Transaksi Iuran - Tapak Suci</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="icon" type="image/jpeg" href="assets/Logo_Tapak_Suci_Galunggung.jpeg">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .navbar-tx { background-color: #212529; border-bottom: 4px solid #FFD700; }
        .table-card { background: white; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #eef2f5; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark navbar-tx py-3 shadow-sm">
        <div class="container-fluid px-4">
            <span class="navbar-brand mb-0 h1 fw-bold text-uppercase tracking-wider"><i class="bi bi-journal-text text-warning me-2"></i>JURNAL BUKU KAS TRANSAKSI</span>
            <a href="index.php" class="btn btn-outline-light rounded-pill px-3"><i class="bi bi-grid-1x2-fill me-1"></i> Dashboard Admin</a>
        </div>
    </nav>

    <div class="container py-4">
        
        <div class="mb-4">
            <div class="input-group shadow-sm rounded-pill overflow-hidden">
                <span class="input-group-text bg-white border-end-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="txSearchInput" class="form-control border-start-0 py-2.5" placeholder="Cari berdasarkan tanggal, ID, nama siswa, atau periode iuran...">
            </div>
        </div>

        <div class="table-card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center" id="txTable">
                    <thead class="table-dark">
                        <tr>
                            <th class="py-3" style="width: 220px;">WAKTU TRANSAKSI</th>
                            <th style="width: 90px;">ID SISWA</th>
                            <th class="text-start ps-4">NAMA LENGKAP SISWA</th>
                            <th style="width: 160px;">PERIODE IURAN</th>
                            <th class="text-end pe-4" style="width: 150px;">NOMINAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($list_transaksi)): foreach ($list_transaksi as $tx): ?>
                            <tr class="border-bottom">
                                <td class="font-monospace text-muted py-3" style="font-size: 13px;"><?= $tx['tanggal']; ?></td>
                                <td class="fw-bold text-secondary">#<?= $tx['id_siswa']; ?></td>
                                <td class="text-start fw-semibold text-dark ps-4"><?= $tx['nama_siswa']; ?></td>
                                <td><span class="badge bg-light text-dark border px-3 py-1.5 rounded-pill fw-medium"><?= $tx['periode']; ?></span></td>
                                <td class="text-end fw-bold text-success pe-4">
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