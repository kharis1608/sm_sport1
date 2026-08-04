<?php
include 'koneksi.php';
session_start();

$error = "";
if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($koneksi, $_POST['email']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']);

    $result = mysqli_query($koneksi, "SELECT * FROM pelanggan WHERE email = '$email'");
    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        if ($password === $row['password']) {
            $_SESSION['login'] = true;
            $_SESSION['id_pelanggan'] = $row['id_pelanggan'];
            $_SESSION['nama'] = $row['nama'];
            header("Location: reservasi.php");
            exit;
        }
    }
    $error = "Email atau Password salah!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SM Sport Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center py-5" style="min-height: 100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold text-primary">SM Sport Center</h3>
                            <p class="text-muted">Silakan login untuk reservasi lapangan</p>
                        </div>
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?= $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control rounded-3" placeholder="nama@gmail.com" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Password</label>
                                <input type="password" name="password" class="form-control rounded-3" placeholder="••••••••" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100 py-2 rounded-3 fw-bold shadow-sm">Login Sekarang</button>
                        </form>
                        
                        <div class="text-center mt-3 small">
                            Belum punya akun? <a href="register.php" class="text-decoration-none fw-bold">Daftar di sini</a>
                        </div>

                        <hr class="my-4">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>