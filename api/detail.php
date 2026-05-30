<?php
require_once __DIR__ . '/google-sheets-client.php';

$no_siswa_req = isset($_GET['no_siswa']) ? htmlspecialchars(trim($_GET['no_siswa'])) : '';

if (empty($no_siswa_req)) {
    die("ID Siswa tidak ditemukan.");
}

$siswa_raw = sheets_read('Master_siswa');
$transaksi_raw = sheets_read('Transaksi_luran');

$profil_siswa = null;
if (!empty($siswa_raw)) {
    foreach ($siswa_raw as $siswa) {
        if (!empty($siswa[0]) && trim($siswa[0]) == $no_siswa_req) {
            $profil_siswa = ['id' => trim($siswa[0]), 'nama' => isset($siswa[1]) ? trim($siswa[1]) : 'Tanpa Nama'];
            break;
        }
    }
}

if (!$profil_siswa) { die("Siswa tidak terdaftar."); }

$riwayat = [];
$total_terbayar = 0;

if (!empty($transaksi_raw)) {
    foreach ($transaksi_raw as $index => $tx) {
        if ($index === 0 || empty($tx[0])) continue;
        if (trim($tx[0]) == $no_siswa_req) {
            $nominal = isset($tx[3]) ? (int)preg_replace('/[^0-9]/', '', $tx[3]) : 0;
            $riwayat[] = ['bulan' => $tx[1], 'tahun' => $tx[2], 'nominal' => $nominal];
            $total_terbayar += $nominal;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Iuran - <?= htmlspecialchars($profil_siswa['nama']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-5" style="max-width: 700px;">
        <a href="index.php" class="btn btn-sm btn-secondary mb-3">Kembali ke Dashboard</a>
        
        <div class="card p-4 mb-4 border-0 shadow-sm" style="border-top: 4px solid #800000 !important;">
            <h5>Kartu Iuran Siswa</h5>
            <h3 class="fw-bold"><?= htmlspecialchars($profil_siswa['nama']); ?></h3>
            <p class="text-muted m-0">ID Register: #<?= $profil_siswa['id']; ?></p>
            <hr>
            <span class="small text-muted">TOTAL SETORAN:</span>
            <h4 class="text-success fw-bold">Rp <?= number_format($total_terbayar, 0, ',', '.'); ?></h4>
        </div>

        <div class="card border-0 shadow-sm p-3">
            <h6>Log Riwayat Pembayaran</h6>
            <table class="table table-striped small">
                <thead><tr><th>No</th><th>Bulan</th><th>Tahun</th><th>Nominal</th></tr></thead>
                <tbody>
                    <?php if(!empty($riwayat)): $no=1; foreach($riwayat as $r): ?>
                        <tr><td><?= $no++; ?></td><td><?= $r['bulan']; ?></td><td><?= $r['tahun']; ?></td><td>Rp <?= number_format($r['nominal'], 0, ',', '.'); ?></td></tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada riwayat pembayaran.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>