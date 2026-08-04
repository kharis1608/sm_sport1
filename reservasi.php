<?php
include 'koneksi.php';
include 'midtrans_config.php';
session_start();

// Set zona waktu agar sesuai (WIB)
date_default_timezone_set('Asia/Jakarta');
$tanggal_hari_ini = date('Y-m-d');

if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Endpoint AJAX internal untuk langsung mengubah status jadi Confirmed
if (isset($_GET['ajax_confirm']) && isset($_GET['id'])) {
    $id_res = (int)$_GET['id'];
    mysqli_query($koneksi, "UPDATE reservasi SET status = 'Confirmed' WHERE id_reservasi = '$id_res'");
    echo json_encode(["status" => "success"]);
    exit;
}

$error = "";
$current_id_reservasi = 0;
$snapToken = null;

// Menangkap input filter tanggal & waktu
$tanggal_filter = isset($_POST['tanggal_reservasi_filter']) ? $_POST['tanggal_reservasi_filter'] : $tanggal_hari_ini;
$jam_mulai_filter = isset($_POST['jam_mulai_filter']) ? $_POST['jam_mulai_filter'] : '00:00';
$jam_selesai_filter = isset($_POST['jam_selesai_filter']) ? $_POST['jam_selesai_filter'] : '00:00';
$pencarian_dilakukan = isset($_POST['cek_ketersediaan']);

// Proses saat tombol Booking diklik
if (isset($_POST['tambah_reservasi'])) {
    $id_pelanggan = $_SESSION['id_pelanggan'];
    $id_lapangan = $_POST['id_lapangan'];
    $tanggal = $_POST['tanggal_reservasi'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];

    $durasi = (strtotime($jam_selesai) - strtotime($jam_mulai)) / 3600;
    $durasi = max(1, $durasi);

    if (empty($id_lapangan) || empty($tanggal) || empty($jam_mulai) || empty($jam_selesai)) {
        $error = "Semua kolom form wajib diisi!";
    } else {
        $query_cek = "SELECT * FROM reservasi 
                     WHERE id_lapangan = '$id_lapangan' 
                     AND tanggal_reservasi = '$tanggal' 
                     AND status != 'Cancelled'
                     AND ('$jam_mulai' < jam_selesai AND '$jam_selesai' > jam_mulai)";
        
        if (mysqli_num_rows(mysqli_query($koneksi, $query_cek)) > 0) {
            $error = "Maaf, jadwal pada lapangan tersebut sudah terisi di jam yang sama!";
        } else {
            $q_lap = mysqli_query($koneksi, "SELECT * FROM lapangan WHERE id_lapangan = '$id_lapangan'");
            $d_lap = mysqli_fetch_assoc($q_lap);
            
            $harga_per_jam = isset($d_lap['harga_per_jam']) ? (int)$d_lap['harga_per_jam'] : 50000;
            $total_biaya = $durasi * $harga_per_jam;

            $query_insert = "INSERT INTO reservasi (id_pelanggan, id_lapangan, tanggal_reservasi, jam_mulai, jam_selesai, total_biaya, status) 
                            VALUES ('$id_pelanggan', '$id_lapangan', '$tanggal', '$jam_mulai', '$jam_selesai', '$total_biaya', 'Pending')";
            
            if (mysqli_query($koneksi, $query_insert)) {
                $order_id_db = mysqli_insert_id($koneksi);
                $current_id_reservasi = $order_id_db;

                $transaction_details = array(
                    'order_id' => 'SM-SPORT-' . $order_id_db . '-' . time(),
                    'gross_amount' => $total_biaya,
                );

                $q_pelanggan = mysqli_query($koneksi, "SELECT * FROM pelanggan WHERE id_pelanggan = '$id_pelanggan'");
                $d_pelanggan = mysqli_fetch_assoc($q_pelanggan);

                $customer_details = array(
                    'first_name' => $d_pelanggan['nama'],
                    'email' => $d_pelanggan['email'],
                    'phone' => $d_pelanggan['no_telepon'],
                );

                $item_details = array(
                    array(
                        'id' => 'FULL-' . $id_lapangan,
                        'price' => $total_biaya,
                        'quantity' => 1,
                        'name' => 'SM Sport'
                    )
                );

                $transaction = array(
                    'transaction_details' => $transaction_details,
                    'customer_details' => $customer_details,
                    'item_details' => $item_details,
                );

                try {
                    $snapToken = \Midtrans\Snap::getSnapToken($transaction);
                } catch (Exception $e) {
                    $error = "Gagal terhubung ke Midtrans: " . $e->getMessage();
                }
            } else {
                $error = "Terjadi kesalahan pada database!";
            }
        }
    }
}

// Query untuk menampilkan daftar lapangan yang kosong berdasarkan filter
$lapangan_tersedia = [];
if ($pencarian_dilakukan) {
    if ($jam_selesai_filter <= $jam_mulai_filter) {
        $error = "Jam selesai harus lebih besar dari jam mulai!";
    } else {
        $jam_selesai_filter_format = date('H:i:s', strtotime($jam_selesai_filter));
        $jam_mulai_filter_format = date('H:i:s', strtotime($jam_mulai_filter));

        $query_filter = "SELECT * FROM lapangan 
                         WHERE id_lapangan NOT IN (
                             SELECT id_lapangan FROM reservasi 
                             WHERE tanggal_reservasi = '$tanggal_filter' 
                             AND status != 'Cancelled'
                             AND ('$jam_mulai_filter_format' < jam_selesai AND '$jam_selesai_filter_format' > jam_mulai)
                         )";
        $lapangan_tersedia = mysqli_query($koneksi, $query_filter);
    }
}

$riwayat = mysqli_query($koneksi, "SELECT r.*, l.nama_lapangan, l.jenis_olahraga FROM reservasi r 
                            JOIN lapangan l ON r.id_lapangan = l.id_lapangan 
                            WHERE r.id_pelanggan = '{$_SESSION['id_pelanggan']}' 
                            ORDER BY r.id_reservasi DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservasi & Cek Lapangan - SM Sport Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Client Key Midtrans SM Sport -->
    <script type="text/javascript" src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="Mid-client-XL3bV6TSs5w9NlrI"></script>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">SM Sport Center</a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">
                    Halo, <strong><?php echo isset($_SESSION['nama']) ? htmlspecialchars($_SESSION['nama']) : 'User'; ?></strong>
                </span>
                <a href="index.php" class="btn btn-light btn-sm text-primary fw-bold">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error; ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Kolom Kiri: Cek Ketersediaan & Form Booking -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-4 p-4 mb-4">
                    <h4 class="fw-bold mb-3 text-primary">Cek Lapangan Kosong</h4>
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tanggal Main</label>
                            <input type="date" name="tanggal_reservasi_filter" class="form-control" value="<?= htmlspecialchars($tanggal_filter); ?>" min="<?= $tanggal_hari_ini; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Jam Mulai</label>
                            <input type="time" name="jam_mulai_filter" class="form-control" value="<?= htmlspecialchars($jam_mulai_filter); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Jam Selesai</label>
                            <input type="time" name="jam_selesai_filter" class="form-control" value="<?= htmlspecialchars($jam_selesai_filter); ?>" required>
                        </div>
                        <button type="submit" name="cek_ketersediaan" class="btn btn-dark w-100 fw-bold py-2">Cek Ketersediaan</button>
                    </form>
                </div>

                <?php if ($pencarian_dilakukan && empty($error)): ?>
                <div class="card shadow-sm border-0 rounded-4 p-4">
                    <h5 class="fw-bold mb-3 text-success">Pilih Lapangan Kosong</h5>
                    <?php if (mysqli_num_rows($lapangan_tersedia) > 0): ?>
                        <form action="" method="POST">
                            <input type="hidden" name="tanggal_reservasi" value="<?= htmlspecialchars($tanggal_filter); ?>">
                            <input type="hidden" name="jam_mulai" value="<?= htmlspecialchars($jam_mulai_filter); ?>">
                            <input type="hidden" name="jam_selesai" value="<?= htmlspecialchars($jam_selesai_filter); ?>">
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Lapangan Tersedia</label>
                                <select name="id_lapangan" class="form-select" required>
                                    <option value="">-- Pilih Lapangan --</option>
                                    <?php while($l = mysqli_fetch_assoc($lapangan_tersedia)): ?>
                                        <option value="<?= $l['id_lapangan']; ?>">
                                            <?= htmlspecialchars($l['nama_lapangan']); ?> (Rp <?= number_format($l['harga_per_jam'], 0, ',', '.'); ?>/jam)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <button type="submit" name="tambah_reservasi" class="btn btn-primary w-100 fw-bold py-2">Booking & Bayar Lunas</button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0 small">Maaf, tidak ada lapangan yang tersedia pada tanggal dan jam tersebut. Silakan cari waktu lain.</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Kolom Kanan: Riwayat Reservasi -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-4 p-4">
                    <h4 class="fw-bold mb-3 text-primary">Riwayat Reservasi Anda</h4>
                    <table class="table table-hover align-middle">
                        <thead class="table-primary">
                            <tr>
                                <th>No</th>
                                <th>Lapangan</th>
                                <th>Jadwal</th>
                                <th>Total Biaya</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; while($r = mysqli_fetch_assoc($riwayat)): ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($r['nama_lapangan']); ?></td>
                                <td><?= date('d-m-Y', strtotime($r['tanggal_reservasi'])); ?><br><small><?= date('H:i', strtotime($r['jam_mulai'])); ?> - <?= date('H:i', strtotime($r['jam_selesai'])); ?></small></td>
                                <td class="fw-bold text-primary">Rp <?= number_format($r['total_biaya'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php if($r['status'] == 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Confirmed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($snapToken) && $current_id_reservasi > 0): ?>
    <script type="text/javascript">
        window.onload = function() {
            window.snap.pay('<?= $snapToken; ?>', {
                onSuccess: function(result){
                    // Kirim request background ke halaman ini sendiri untuk ubah status jadi Confirmed
                    fetch('reservasi.php?ajax_confirm=1&id=<?= $current_id_reservasi; ?>')
                        .then(response => response.json())
                        .then(data => {
                            if(data.status === 'success') {
                                window.location.href = 'reservasi.php';
                            }
                        });
                },
                onPending: function(result){
                    alert("Menunggu pembayaran Anda.");
                    window.location.href = 'reservasi.php';
                },
                onError: function(result){
                    alert("Pembayaran gagal!");
                    window.location.href = 'reservasi.php';
                },
                onClose: function(){
                    alert('Anda menutup popup pembayaran sebelum selesai.');
                    window.location.href = 'reservasi.php';
                }
            });
        };
    </script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>