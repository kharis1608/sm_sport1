<?php
include 'koneksi.php';
session_start();

// Set zona waktu agar sesuai (WIB)
date_default_timezone_set('Asia/Jakarta');

$error = "";

// Inisialisasi variabel awal agar tidak ada warning undefined variable
$lapangan_tersedia = 0;
$total_lapangan = 0;
$total_pendapatan_hari_ini = 0;
$bulan_pilih = date('m');
$tahun_pilih = date('Y');
$pendapatan_bulanan = 0;
$rekap_pendapatan = null;
$reservasi = null;

// PROSES LOGIN (Jika tombol login ditekan)
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Cek user ke database
    $stmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE username = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    // Verifikasi password
    if ($user && ($password === $user['password'])) {
        $_SESSION['id_user']        = $user['id_user'];
        $_SESSION['username']       = $user['username'];
        $_SESSION['nama_lengkap']   = $user['nama_lengkap'];
        $_SESSION['role']           = $user['role'];
        $_SESSION['status_login']   = "aktif";

        header("Location: admin.php");
        exit();
    } else {
        $error = "Username atau password salah!";
    }
}

// PROSES LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

// Cek apakah admin sudah login atau belum
$is_logged_in = isset($_SESSION['status_login']) && $_SESSION['status_login'] == "aktif";

// Jika sudah login, jalankan logika dashboard
if ($is_logged_in) {
    // Fitur Hapus Reservasi
    if (isset($_GET['hapus'])) {
        $id_reservasi = (int)$_GET['hapus'];
        mysqli_query($koneksi, "DELETE FROM reservasi WHERE id_reservasi = '$id_reservasi'");
        header("Location: admin.php");
        exit;
    }

    $tanggal_hari_ini = date('Y-m-d');
    $jam_sekarang = date('H:i:s');

    // 1. Hitung Total Lapangan & Lapangan Terpakai Saat Ini
    $query_lapangan = mysqli_query($koneksi, "SELECT * FROM lapangan");
    $lapangan_terpakai = 0;

    while ($lap = mysqli_fetch_assoc($query_lapangan)) {
        $total_lapangan++;
        $id_lap = $lap['id_lapangan'];

        $cek_pakai = mysqli_query($koneksi, "SELECT * FROM reservasi 
                                        WHERE id_lapangan = '$id_lap' 
                                        AND tanggal_reservasi = '$tanggal_hari_ini' 
                                        AND status = 'Confirmed' 
                                        AND ('$jam_sekarang' >= jam_mulai AND '$jam_sekarang' <= jam_selesai)");
        
        if (mysqli_num_rows($cek_pakai) > 0) {
            $lapangan_terpakai++;
        }
    }

    $lapangan_tersedia = $total_lapangan - $lapangan_terpakai;
    if ($lapangan_tersedia < 0) { $lapangan_tersedia = 0; }

    // 2. Hitung Total Pendapatan HARI INI
    $query_pendapatan = mysqli_query($koneksi, "SELECT SUM(total_biaya) as pendapatan FROM reservasi WHERE status = 'Confirmed' AND tanggal_reservasi = '$tanggal_hari_ini'");
    $data_pendapatan = mysqli_fetch_assoc($query_pendapatan);
    $total_pendapatan_hari_ini = $data_pendapatan['pendapatan'] ? $data_pendapatan['pendapatan'] : 0;

    // 3. Filter Rekap Pendapatan Bulan & Tahun
    $bulan_pilih = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
    $tahun_pilih = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

    $rekap_pendapatan = mysqli_query($koneksi, "SELECT tanggal_reservasi, SUM(total_biaya) as total_harian, COUNT(*) as jumlah_transaksi 
                                           FROM reservasi 
                                           WHERE status = 'Confirmed' 
                                           AND MONTH(tanggal_reservasi) = '$bulan_pilih' 
                                           AND YEAR(tanggal_reservasi) = '$tahun_pilih'
                                           GROUP BY tanggal_reservasi 
                                           ORDER BY tanggal_reservasi DESC");

    $query_total_bulan = mysqli_query($koneksi, "SELECT SUM(total_biaya) as total_bulanan 
                                              FROM reservasi 
                                              WHERE status = 'Confirmed' 
                                              AND MONTH(tanggal_reservasi) = '$bulan_pilih' 
                                              AND YEAR(tanggal_reservasi) = '$tahun_pilih'");
    $data_total_bulan = mysqli_fetch_assoc($query_total_bulan);
    $pendapatan_bulanan = $data_total_bulan['total_bulanan'] ? $data_total_bulan['total_bulanan'] : 0;

    // Ambil daftar reservasi masuk
    $reservasi = mysqli_query($koneksi, "SELECT r.*, p.nama as nama_pelanggan, l.nama_lapangan, u.nama_lengkap as nama_admin 
                                    FROM reservasi r 
                                    JOIN pelanggan p ON r.id_pelanggan = p.id_pelanggan 
                                    JOIN lapangan l ON r.id_lapangan = l.id_lapangan 
                                    LEFT JOIN users u ON r.id_user = u.id_user 
                                    ORDER BY r.id_reservasi DESC");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_logged_in ? 'Dashboard Admin' : 'Login Admin'; ?> - SM Sport Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">

    <?php if (!$is_logged_in): ?>
        <!-- TAMPILAN HALAMAN LOGIN -->
        <div class="container d-flex justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="card shadow-sm border-0 rounded-4 p-4 bg-white">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold text-dark"><i class="fas fa-user-shield text-success"></i> Admin Login</h3>
                        <p class="text-muted small">SM Sport Center Management System</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 text-center small"><?= $error; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-user"></i></span>
                                <input type="text" name="username" class="form-control" placeholder="Masukkan username" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                            </div>
                        </div>
                        <button type="submit" name="login" class="btn btn-success w-100 py-2 fw-bold shadow-sm">
                            <i class="fas fa-sign-in-alt"></i> Masuk Dashboard
                        </button>
                    </form>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- TAMPILAN DASHBOARD ADMIN -->
        <nav class="navbar navbar-dark bg-dark shadow-sm">
            <div class="container">
                <span class="navbar-brand fw-bold mb-0 h1"><i class="fas fa-user-shield"></i> Admin - SM Sport Center</span>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">Halo, <strong><?= htmlspecialchars($_SESSION['nama_lengkap']); ?></strong></span>
                    <a href="admin.php?logout=true" class="btn btn-outline-light btn-sm" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </nav>

        <div class="container py-5">
            <div class="mb-4">
                <h2 class="fw-bold m-0">Dashboard Admin</h2>
                <p class="text-muted">Tanggal hari ini: <?= date('d-m-Y'); ?></p>
            </div>

            <!-- Kotak Statistik (Cards) -->
            <div class="row g-4 mb-5">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase fw-semibold mb-1">Lapangan Tersedia</h6>
                                <h2 class="fw-bold mb-0"><?= $lapangan_tersedia; ?> <small style="font-size: 15px;">dari <?= $total_lapangan; ?> total</small></h2>
                            </div>
                            <div class="fs-1 text-white-50"><i class="fas fa-futbol"></i></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm rounded-4 bg-success text-white p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-uppercase fw-semibold mb-1">Pendapatan Hari Ini</h6>
                                <h2 class="fw-bold mb-0" style="font-size: 24px;">Rp <?= number_format($total_pendapatan_hari_ini, 0, ',', '.'); ?></h2>
                            </div>
                            <div class="fs-1 text-white-50"><i class="fas fa-wallet"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rekap Pendapatan Bulanan -->
            <div class="card shadow-sm border-0 rounded-4 p-4 mb-5">
                <h4 class="fw-bold mb-3 text-dark"><i class="fas fa-calendar-alt text-success"></i> Rekap Pendapatan Bulanan</h4>
                
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Pilih Bulan</label>
                        <select name="bulan" class="form-select">
                            <?php 
                            $namabulan = ['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'];
                            foreach($namabulan as $k => $v): 
                            ?>
                                <option value="<?= $k; ?>" <?= ($bulan_pilih == $k) ? 'selected':''; ?>><?= $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Pilih Tahun</label>
                        <select name="tahun" class="form-select">
                            <?php 
                            $tahun_sekarang = date('Y');
                            for($t = $tahun_sekarang; $t >= $tahun_sekarang - 3; $t--): 
                            ?>
                                <option value="<?= $t; ?>" <?= ($tahun_pilih == $t) ? 'selected':''; ?>><?= $t; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100 fw-bold"><i class="fas fa-filter"></i> Filter Pendapatan</button>
                    </div>
                </form>

                <div class="alert alert-success d-flex justify-content-between align-items-center mb-4">
                    <span>Total Pendapatan Bulan Terpilih:</span>
                    <strong class="fs-5">Rp <?= number_format($pendapatan_bulanan, 0, ',', '.'); ?></strong>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-success">
                            <tr>
                                <th>Tanggal</th>
                                <th>Jumlah Transaksi Lunas</th>
                                <th>Total Pendapatan Harian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($rekap_pendapatan && mysqli_num_rows($rekap_pendapatan) > 0): ?>
                                <?php while($rek = mysqli_fetch_assoc($rekap_pendapatan)): ?>
                                <tr>
                                    <td class="fw-semibold"><?= date('d-m-Y', strtotime($rek['tanggal_reservasi'])); ?></td>
                                    <td><?= $rek['jumlah_transaksi']; ?> Transaksi</td>
                                    <td class="fw-bold text-success">Rp <?= number_format($rek['total_harian'], 0, ',', '.'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Tidak ada data pendapatan pada bulan ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel Daftar Reservasi -->
            <div class="card shadow-sm border-0 rounded-4 p-4">
                <h4 class="fw-bold mb-3 text-dark">Daftar Semua Reservasi Masuk</h4>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>No</th>
                                <th>Pelanggan</th>
                                <th>Lapangan</th>
                                <th>Jadwal</th>
                                <th>Total Biaya</th>
                                <th>Dinput Oleh Admin</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($reservasi): ?>
                                <?php $no = 1; while($r = mysqli_fetch_assoc($reservasi)): ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($r['nama_pelanggan']); ?></td>
                                    <td><?= htmlspecialchars($r['nama_lapangan']); ?></td>
                                    <td><?= date('d-m-Y', strtotime($r['tanggal_reservasi'])); ?><br><small><?= date('H:i', strtotime($r['jam_mulai'])); ?> - <?= date('H:i', strtotime($r['jam_selesai'])); ?></small></td>
                                    <td class="fw-bold text-success">Rp <?= number_format($r['total_biaya'], 0, ',', '.'); ?></td>
                                    <td>
                                        <?php if(!empty($r['nama_admin'])): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($r['nama_admin']); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border">Mandiri (Online)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($r['status'] == 'Pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php elseif($r['status'] == 'Confirmed'): ?>
                                            <span class="badge bg-success">Confirmed</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Cancelled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="admin.php?hapus=<?= $r['id_reservasi']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus data reservasi ini?')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>