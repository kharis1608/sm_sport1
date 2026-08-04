<?php
include 'koneksi.php';
include 'midtrans_config.php';

// Mengambil raw input POST dari Midtrans untuk mengantisipasi data JSON mentah
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input);

$order_id = null;
$transaction_status = null;
$fraud = null;

// Jika menggunakan notifikasi standar Midtrans PHP Library
try {
    $notif = new \Midtrans\Notification();
    $order_id = $notif->order_id;
    $transaction_status = $notif->transaction_status;
    $fraud = $notif->fraud_status ?? null;
} catch (Exception $e) {
    // Fallback jika dibaca langsung dari raw json payload
    if ($data) {
        $order_id = $data->order_id ?? null;
        $transaction_status = $data->transaction_status ?? null;
        $fraud = $data->fraud_status ?? null;
    }
}

if ($order_id) {
    // Ekstrak ID Reservasi dari format order_id (Contoh: SM-SPORT-30-1785316623)
    // Bagian ke-2 (index 2) adalah id_reservasi
    $parts = explode('-', $order_id);
    $id_reservasi = isset($parts[2]) ? intval($parts[2]) : 0;

    if ($id_reservasi > 0) {
        $status_baru = 'Pending';

        if ($transaction_status == 'capture') {
            if ($fraud == 'accept') {
                $status_baru = 'Confirmed';
            }
        } elseif ($transaction_status == 'settlement') {
            $status_baru = 'Confirmed';
        } elseif ($transaction_status == 'pending') {
            $status_baru = 'Pending';
        } elseif (in_array($transaction_status, ['deny', 'expire', 'cancel'])) {
            $status_baru = 'Cancelled';
        }

        // Eksekusi update status ke database menggunakan prepared statement agar aman
        $stmt = $koneksi->prepare("UPDATE reservasi SET status = ? WHERE id_reservasi = ?");
        $stmt->bind_param("si", $status_baru, $id_reservasi);
        $stmt->execute();
        $stmt->close();
        
        // Berikan respons HTTP 200 OK ke server Midtrans
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Status reservasi $id_reservasi diubah menjadi $status_baru"]);
        exit();
    }
}

http_response_code(400);
echo json_encode(["status" => "error", "message" => "Invalid notification data"]);
?>