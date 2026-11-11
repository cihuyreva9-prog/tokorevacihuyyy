CREATE DATABASE IF NOT EXISTS tokoku CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tokoku;
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS reviews,wishlists,stock_movements,held_transactions,shifts,vouchers,order_items,orders,products,categories,users,settings;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,email VARCHAR(120) NOT NULL UNIQUE,password VARCHAR(255) NOT NULL,role ENUM('admin','kasir','pembeli') NOT NULL DEFAULT 'pembeli',phone VARCHAR(30),address TEXT,points INT NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE categories(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80) NOT NULL);
CREATE TABLE products(id INT AUTO_INCREMENT PRIMARY KEY,category_id INT NULL,name VARCHAR(150) NOT NULL,sku VARCHAR(40) NOT NULL UNIQUE,barcode VARCHAR(40),price INT NOT NULL,stock INT NOT NULL DEFAULT 0,min_stock INT NOT NULL DEFAULT 5,icon VARCHAR(16) DEFAULT '📦',description TEXT,active TINYINT(1) NOT NULL DEFAULT 1,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL);
CREATE TABLE shifts(id INT AUTO_INCREMENT PRIMARY KEY,cashier_id INT NOT NULL,opened_at DATETIME NOT NULL,closed_at DATETIME NULL,opening_cash INT NOT NULL DEFAULT 0,closing_cash INT NULL,expected_cash INT NULL,FOREIGN KEY(cashier_id) REFERENCES users(id));
CREATE TABLE orders(id INT AUTO_INCREMENT PRIMARY KEY,code VARCHAR(30) NOT NULL UNIQUE,source ENUM('online','pos') NOT NULL,user_id INT NULL,cashier_id INT NULL,shift_id INT NULL,status ENUM('belum_bayar','diproses','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'belum_bayar',payment_method ENUM('tunai','transfer','qris','debit','cod') NOT NULL,subtotal INT NOT NULL,discount INT NOT NULL DEFAULT 0,tax INT NOT NULL DEFAULT 0,shipping INT NOT NULL DEFAULT 0,rounding INT NOT NULL DEFAULT 0,total INT NOT NULL,amount_paid INT NULL,change_amount INT NOT NULL DEFAULT 0,name VARCHAR(100),phone VARCHAR(30),address TEXT,courier VARCHAR(50),tracking_no VARCHAR(60),voucher_code VARCHAR(30),void_requested TINYINT(1) NOT NULL DEFAULT 0,print_count INT NOT NULL DEFAULT 0,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX(status),INDEX(created_at),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL);
CREATE TABLE order_items(id INT AUTO_INCREMENT PRIMARY KEY,order_id INT NOT NULL,product_id INT NULL,name VARCHAR(150) NOT NULL,price INT NOT NULL,qty INT NOT NULL,FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE);
CREATE TABLE vouchers(id INT AUTO_INCREMENT PRIMARY KEY,code VARCHAR(30) NOT NULL UNIQUE,type ENUM('percent','fixed') NOT NULL,value INT NOT NULL,min_total INT NOT NULL DEFAULT 0,active TINYINT(1) NOT NULL DEFAULT 1);
CREATE TABLE held_transactions(id INT AUTO_INCREMENT PRIMARY KEY,cashier_id INT NOT NULL,label VARCHAR(60),data LONGTEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE stock_movements(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,qty INT NOT NULL,type ENUM('masuk','keluar','penjualan','retur','opname') NOT NULL,note VARCHAR(150),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE wishlists(user_id INT NOT NULL,product_id INT NOT NULL,PRIMARY KEY(user_id,product_id));
CREATE TABLE reviews(id INT AUTO_INCREMENT PRIMARY KEY,product_id INT NOT NULL,user_id INT NOT NULL,rating TINYINT NOT NULL,comment TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE(product_id,user_id));
CREATE TABLE settings(k VARCHAR(40) PRIMARY KEY,v TEXT);

INSERT INTO settings VALUES('store_name','Tokoku'),('store_address','Jl. Contoh No. 12, Jakarta'),('store_phone','021-1234567'),('tax_percent','11'),('rounding','100'),('receipt_footer','Terima kasih sudah berbelanja!'),('paper_width','58'),('auto_print','1'),('bank_info','BCA 1234567890 a.n. Tokoku');
INSERT INTO users(name,email,password,role,phone,address,points) VALUES
('Admin Tokoku','admin@tokoku.test','$2y$10$4EVgjE71abthY4IErj/me.hgBP1yDrfPtC6erQoi7TT2Wy1Fi7Fpq','admin','0811000001',NULL,0),
('Rina Kasir','kasir@tokoku.test','$2y$10$V1s4szaLNNjvvlrLFj4FtevwDhgKuID4Gu2bOFOZNxT93KEzt..uW','kasir','0811000002',NULL,0),
('Budi Santoso','budi@tokoku.test','$2y$10$/aZHXj0c9fxrSozHWrbNxuckO91BE63yCg9W0.VUgLKm1p1tXo5V.','pembeli','0811000003','Jl. Melati 5, Bandung',120);
INSERT INTO categories(name) VALUES('Minuman'),('Makanan'),('Kebutuhan Rumah'),('Alat Tulis');
INSERT INTO products(category_id,name,sku,barcode,price,stock,icon,description) VALUES
(1,'Kopi Arabica 250g','KOP-001','8991001000011',45000,40,'☕','Biji kopi arabica sangrai medium, aroma buah dan cokelat.'),
(1,'Teh Hijau Celup (25)','TEH-001','8991001000028',18000,60,'🍵','Teh hijau celup, ringan dan segar.'),
(1,'Susu UHT 1L','SUS-001','8991001000035',19500,80,'🥛','Susu UHT full cream kemasan 1 liter.'),
(1,'Air Mineral 600ml','AIR-001','8991001000042',4000,200,'💧','Air mineral kemasan botol 600 ml.'),
(2,'Mi Instan Goreng','MIE-001','8991001000059',3500,300,'🍜','Mi instan goreng rasa original.'),
(2,'Roti Tawar','ROT-001','8991001000066',16000,25,'🍞','Roti tawar lembut, dibuat setiap pagi.'),
(2,'Cokelat Batang','COK-001','8991001000073',12500,70,'🍫','Cokelat batang 80g, kakao 55%.'),
(2,'Madu Murni 250ml','MAD-001','8991001000080',55000,18,'🍯','Madu murni tanpa campuran.'),
(3,'Gula Pasir 1kg','GUL-001','8991001000097',16000,90,'🧂','Gula pasir putih 1 kg.'),
(3,'Sabun Cuci Piring','SAB-001','8991001000103',11000,50,'🧼','Sabun cuci piring lemon 400ml.'),
(3,'Tisu Wajah','TIS-001','8991001000110',14000,3,'🧻','Tisu wajah 250 lembar.'),
(4,'Buku Tulis A5','BUK-001','8991001000127',9000,100,'📒','Buku tulis A5 isi 40 lembar.'),
(4,'Pulpen Gel Hitam','PUL-001','8991001000134',5000,150,'🖊️','Pulpen gel 0,5 mm, tinta hitam.');
INSERT INTO vouchers(code,type,value,min_total) VALUES('HEMAT10','percent',10,50000),('POTONG15K','fixed',15000,100000);
