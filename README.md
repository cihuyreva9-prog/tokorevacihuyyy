# Tokoku - Toko online + kasir (POS) + dashboard admin

PHP 8+ dan MySQL/MariaDB. Cocok untuk XAMPP / Laragon / hosting biasa.

## Pasang
1. Ekstrak folder `tokoku` ke `htdocs` (XAMPP) atau root web server.
2. Buka phpMyAdmin, impor `database.sql` (otomatis membuat database `tokoku` beserta data contoh).
3. Cek `config.php` (user/password MySQL, dan `BASE` = nama folder di URL, mis. `/tokoku`).
4. Buka http://localhost/tokoku/

## Akun demo
| Peran | Email | Password |
|---|---|---|
| Admin | admin@tokoku.test | admin123 |
| Kasir | kasir@tokoku.test | kasir123 |
| Pembeli | budi@tokoku.test | budi123 |
**Ganti semua password demo sebelum dipakai sungguhan.**

## Area
- `/index.php` toko satu halaman: pilih produk, keranjang, data pengiriman, pembayaran, dan pesan di satu layar (tanpa wajib daftar), plus lacak pesanan
- `/akun/login.php` (masuk/daftar pembeli), `/kasir/login.php`, `/admin/login.php`: tiap peran punya halaman masuk dan tombol Keluar sendiri
- `/akun/` dashboard pembeli
- `/kasir/` mesin kasir: scan barcode, tahan transaksi, tunai + kembalian otomatis, cetak struk, shift
- `/admin/` produk, stok, pesanan, pengguna, voucher, laporan (CSV), pengaturan struk
- `/struk.php` struk thermal 58/80 mm dan invoice (cetak ulang ditandai SALINAN)

## Catatan
Semua nominal dihitung ulang di server. Stok berkurang saat transaksi selesai, dikembalikan saat pesanan dibatalkan. Pembayaran online (transfer/QRIS) diverifikasi manual oleh admin; integrasi Midtrans/Xendit dan ongkir otomatis belum disertakan.
