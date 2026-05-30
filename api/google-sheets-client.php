<?php
// ==============================================================================
// KONFIGURASI UTAMA KONEKSI GOOGLE SHEETS
// ==============================================================================
define('WEB_APP_URL', 'https://script.google.com/macros/s/AKfycbxx-ZEhbDgFvx8wqTPIkKHiD1CkMzPy98-Dhafw9zpPNfNgK2IeBZecp0ZW2obnBbU8KQ/exec');

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch,  CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    
    $resData = json_decode($response, true);
    return isset($resData['success']) && $resData['success'] === true;
}