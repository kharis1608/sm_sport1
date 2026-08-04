<?php
include 'koneksi.php';

$error = "";
$sukses = "";

if (isset($_POST['daftar'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $email = mysqli_real_escape_string($koneksi, $_POST['email']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']);
    $no_telepon = mysqli_real_escape_string($koneksi, $_POST['no_telepon']);

    if (empty($nama) || empty($email) || empty($password) || empty($no_telepon)) {
        $error = "Semua kolom wajib diisi!";
    } else {
        // Cek apakah email sudah terdaftar
        $cek_email = mysqli_query($koneksi, "SELECT * FROM pelanggan WHERE email = '$email'");
        if (mysqli_num_rows($cek_email) > 0) {
            $error = "Email sudah terdaftar! Gunakan email lain.";
        } else {
            // Masukkan data ke database
            $query = "INSERT INTO pelanggan (nama, email, password, no_telepon) VALUES ('$nama', '$email', '$password', '$no_telepon')";
            if (mysqli_query($koneksi, $query)) {
                $sukses = "Registrasi berhasil! Silakan login.";
            } else {
                $error = "Terjadi kesalahan sistem, coba lagi.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - SM Sport Center</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="card shadow border-0 rounded-4 p-4" style="width: 100%; max-width: 420px;">
        <div class="card-body">
            <h3 class="fw-bold text-center text-primary mb-1">SM Sport Center</h3>
            <p class="text-center text-muted mb-4 small">Buat akun baru untuk reservasi</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= $error; ?></div>
            <?php endif; ?>
            <?php if ($sukses): ?>
                <div class="alert alert-success py-2 small"><?= $sukses; ?> <a href="index.php" class="fw-bold">Login disini</a></div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control rounded-3" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control rounded-3" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Password</label>
                    <input type="password" name="password" class="form-control rounded-3" required>
                </div>
                <div class="mb-4">
                    <label class="form-label small fw-semibold">No Telepon / WhatsApp</label>
                    <input type="text" name="no_telepon" class="form-control rounded-3" required>
                </div>
                <button type="submit" name="daftar" class="btn btn-primary w-100 py-2 rounded-3 fw-bold mb-3">Daftar Sekarang</button>
                <div class="text-center small">
                    Sudah punya akun? <a href="index.php" class="text-decoration-none fw-bold">Login disini</a>
                </div>
            </form>
        </div>
    </div>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>