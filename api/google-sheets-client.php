<?php
// ==============================================================================
// KONFIGURASI UTAMA KONEKSI GOOGLE SHEETS
// ==============================================================================
// Prioritas 1: Mencari di Environment Variable Vercel
// Prioritas 2: Mencari di Environment Variable System
// Prioritas 3: Manual URL (Nilai Default)
$script_url = getenv('GOOGLE_SCRIPT_URL') ?: $_ENV['GOOGLE_SCRIPT_URL'] ?: 'https://script.google.com/macros/s/AKfycbxx-ZEhbDgFvx8wqTPIkKHiD1CkMzPy98-Dhafw9zpPNfNgK2IeBZecp0ZW2obnBbU8KQ/exec';

define('WEB_APP_URL', $script_url);

/**
 * Fungsi untuk membaca data dari sheet (DILENGKAPI PENGAMAN ANTI-CACHE)
 */
function sheets_read($range) {
    // Menambahkan &time= agar Google terpaksa memberikan data paling baru dan tidak macet
    $url = WEB_APP_URL . '?sheet=' . urlencode($range) . '&time=' . time();
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return [];
    }
    curl_close($ch);
    
    $data = json_decode($response, true);
    return isset($data['values']) ? $data['values'] : [];
}

/**
 * Fungsi untuk Tambah, Ubah, dan Hapus Data
 */
function sheets_append($range, $data, $action = 'append', $keyId = null) {
    $payload = json_encode([
        'sheet'  => $range,
        'action' => $action,
        'keyId'  => $keyId,
        'values' => $data
    ]);
    
    $ch = curl_init(WEB_APP_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
?>