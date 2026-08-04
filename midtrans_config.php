<?php
require_once __DIR__ . '/vendor/autoload.php';

// Set Konfigurasi Midtrans (Mendukung Environment Variables untuk Hosting)
\Midtrans\Config::$serverKey = getenv('MIDTRANS_SERVER_KEY') ?: 'Mid-server-U6WjDhXKqAcEw3bpquSRv8Ud';
\Midtrans\Config::$clientKey = getenv('MIDTRANS_CLIENT_KEY') ?: 'Mid-client-XL3bV6TSs5w9NlrI';

// Set Production Status: set getenv('MIDTRANS_IS_PRODUCTION') = 'true' jika sudah di hosting production
$isProd = getenv('MIDTRANS_IS_PRODUCTION');
\Midtrans\Config::$isProduction = ($isProd === 'true' || $isProd === '1');

// Set sanitization & 3DS
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;
?>