
# 🛒 TOKOKU
### Sistem Informasi Penjualan Online Berbasis PHP Native & MySQL

TokoKu merupakan aplikasi website penjualan online yang dibuat menggunakan PHP Native, MySQL, HTML, CSS, dan JavaScript. Aplikasi ini memiliki fitur pengelolaan produk, transaksi penjualan, keranjang belanja, pembayaran, pengiriman, dan laporan.

---

## 📌 Fitur Utama

### 1. Admin
- Login dan logout admin.
- Dashboard statistik penjualan.
- Mengelola data produk.
- Mengelola kategori produk.
- Mengelola pengguna.
- Mengelola pesanan pelanggan.
- Memverifikasi pembayaran.
- Mengelola voucher.
- Melihat laporan penjualan.
- Mengelola pengaturan toko.

### 2. Kasir
- Login sebagai kasir.
- Dashboard kasir.
- Mesin kasir (POS).
- Menambahkan produk ke transaksi.
- Memproses pembayaran.
- Melihat riwayat transaksi.
- Mengelola pesanan online.
- Memeriksa stok barang.
- Upload foto produk.
- Mengelola shift kasir.
- Melihat laporan transaksi.

### 3. Pembeli
- Melihat katalog produk.
- Mencari produk.
- Memfilter kategori.
- Mengurutkan produk.
- Melihat detail produk.
- Melihat foto produk.
- Menambahkan produk ke keranjang.
- Menggunakan wishlist.
- Menggunakan voucher.
- Melakukan checkout.
- Memilih metode pembayaran.
- Memilih jasa pengiriman.
- Melihat ulasan produk.
- Melacak pesanan.
- Melihat invoice.

---

## 🛠️ Teknologi

| Teknologi | Keterangan |
|---|---|
| PHP Native | Backend aplikasi |
| MySQL / MariaDB | Database |
| HTML5 | Struktur halaman |
| CSS3 | Tampilan website |
| JavaScript | Interaksi website |
| Apache | Web server |
| XAMPP | Local development |

---

## 📂 Struktur Folder

```text
TOKOKU/
│
├── admin/
│   └── Halaman pengelolaan admin
│
├── akun/
│   └── Login dan registrasi
│
├── kasir/
│   └── Mesin kasir dan pengelolaan transaksi
│
├── uploads/
│   └── produk/
│       └── Foto produk
│
├── includes/
│   └── File pendukung aplikasi
│
├── config/
│   └── Konfigurasi aplikasi
│
├── lib.php
├── index.php
├── struk.php
├── koneksi.php
└── README.md
```

*Catatan: Struktur folder dapat disesuaikan dengan file yang tersedia di proyek.*

---

## 💻 Persyaratan Sistem

- Windows 10 atau Windows 11.
- XAMPP.
- PHP 8.2 atau lebih baru.
- MySQL atau MariaDB.
- Browser Google Chrome, Firefox, atau Microsoft Edge.
- Text editor Visual Studio Code (disarankan).

---

## 🚀 Cara Instalasi

### 1. Instal XAMPP

Download dan instal XAMPP melalui:

https://www.apachefriends.org/

Jalankan:
- Apache
- MySQL

### 2. Masukkan Folder Proyek

Salin folder `TOKOKU` ke:

```text
C:\xampp\htdocs\TOKOKU
```

Jika menggunakan lokasi XAMPP berbeda, sesuaikan dengan folder instalasi masing-masing.

### 3. Buat Database

1. Buka browser.
2. Masuk ke:

```text
http://localhost/phpmyadmin
```

3. Buat database baru dengan nama:

```text
db_tokoku
```

4. Pilih database tersebut.
5. Import file SQL database proyek jika tersedia.

### 4. Konfigurasi Database

Sesuaikan konfigurasi koneksi database pada file `koneksi.php` atau file konfigurasi yang digunakan proyek.

Contoh konfigurasi:

```php
<?php

$host = '127.0.0.1';
$db   = 'db_tokoku';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die('Koneksi database gagal: '.$e->getMessage());
}
```

Sesuaikan file ini dengan konfigurasi koneksi yang sudah digunakan dalam proyek agar tidak membuat koneksi ganda.

### 5. Buat Folder Upload

Pastikan folder berikut tersedia:

```text
uploads/produk/
```

Folder tersebut digunakan untuk menyimpan foto produk yang diunggah melalui halaman kasir.

### 6. Jalankan Website

Buka browser dan akses:

```text
http://localhost/TOKOKU
```

---

## 🖼️ Fitur Upload Foto Produk

TokoKu mendukung upload foto produk melalui menu Foto Produk pada halaman kasir.

Ketentuan upload:
- Format JPG.
- Format JPEG.
- Format PNG.
- Format WEBP.
- Ukuran maksimal 3 MB.

Foto disimpan dalam folder:

```text
uploads/produk/
```

Lokasi foto disimpan pada kolom `image_path` di tabel `products`.

Jika kolom tersebut belum tersedia, jalankan:

```sql
ALTER TABLE products
ADD COLUMN image_path VARCHAR(255) NULL DEFAULT NULL;
```

---

## 🛍️ Alur Transaksi

```text
Pembeli
   |
   v
Memilih Produk
   |
   v
Menambahkan ke Keranjang
   |
   v
Mengisi Data Penerima
   |
   v
Memilih Pengiriman
   |
   v
Memilih Pembayaran
   |
   v
Checkout
   |
   v
Admin Memverifikasi Pembayaran
   |
   v
Pesanan Diproses
   |
   v
Pesanan Dikirim
   |
   v
Pesanan Selesai
```

---

## 💳 Metode Pembayaran

TokoKu menyediakan beberapa metode pembayaran:

1. Transfer bank.
2. QRIS.
3. COD (Cash on Delivery).

## 🚚 Metode Pengiriman

Pilihan pengiriman yang tersedia:

| Pengiriman | Biaya |
|---|---:|
| Ambil di toko | Gratis |
| JNE Reguler | Rp15.000 |
| J&T Express | Rp18.000 |
| SiCepat | Rp14.000 |

Biaya dapat diubah melalui konfigurasi aplikasi.

---

## 🔐 Keamanan Aplikasi

Fitur keamanan yang digunakan:
- Password hashing.
- Session login.
- Pemeriksaan hak akses berdasarkan role.
- CSRF token.
- Prepared statements PDO.
- Validasi input.
- Validasi file upload.
- Pembatasan akses halaman berdasarkan role.

---

## 🗄️ Database

Database utama:

```text
db_tokoku
```

Tabel yang digunakan aplikasi dapat mencakup:

| Tabel | Fungsi |
|---|---|
| users | Menyimpan akun pengguna |
| products | Menyimpan data produk |
| categories | Menyimpan kategori |
| orders | Menyimpan transaksi |
| order_items | Menyimpan detail transaksi |
| wishlists | Menyimpan produk favorit |
| reviews | Menyimpan ulasan |
| vouchers | Menyimpan kode voucher |
| shifts | Menyimpan data shift kasir |

Tabel tambahan dapat digunakan sesuai kebutuhan fitur aplikasi.

---

## 👥 Hak Akses Pengguna

| Role | Hak Akses |
|---|---|
| Admin | Mengelola seluruh sistem |
| Kasir | Mengelola transaksi dan pesanan |
| Pembeli | Melakukan pembelian produk |

---

## 📝 Catatan Pengembangan

- Aplikasi dibuat menggunakan PHP Native.
- Database menggunakan MySQL/MariaDB.
- Website dijalankan melalui XAMPP.
- Foto produk disimpan secara lokal.
- Keranjang belanja menggunakan localStorage browser.
- Sistem transaksi menggunakan database MySQL.
- Struktur folder mengikuti implementasi proyek.

---

## 👨‍💻 Pengembang

**Nama Proyek:** TokoKu  
**Jenis Aplikasi:** Sistem Informasi Penjualan Online  
**Backend:** PHP Native  
**Database:** MySQL  
**Server Lokal:** XAMPP

---

## 📄 Lisensi

Proyek ini dibuat untuk keperluan pembelajaran dan pengembangan aplikasi sistem informasi penjualan.

---
**TokoKu – Belanja Mudah, Cepat, dan Praktis! 🛒**
```

Simpan dengan nama **`README.md`** di folder utama `TOKOKU`, sejajar dengan `index.php` dan `lib.php`.
