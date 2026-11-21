# Preloved UNNES — Marketplace Barang Bekas Mahasiswa

Website jual-beli barang bekas khusus mahasiswa UNNES (tugas Technopreneurship).
Stack: **PHP 8.3 (native, PDO) + MariaDB + HTML/CSS/JS + Docker**. Ada mode terang/gelap.

## Menjalankan

```bash
cd preloved-unnes
nano .env                     # ganti password admin & database dulu
docker compose up -d --build
```

Buka **http://localhost:8080** (atau IP VPS:8080). Login admin: isi `ADMIN_EMAIL` dan `ADMIN_PASSWORD` di `.env`
(akun admin dibuat otomatis saat pertama kali jalan).

Perintah berguna:

```bash
docker compose logs -f web      # lihat log
docker compose down             # stop (data tetap aman di volume)
docker compose down -v          # stop + HAPUS semua data
```

## Kesesuaian dengan PPT

| Di PPT | Di aplikasi |
|---|---|
| Autentikasi: akun terverifikasi mahasiswa UNNES | Daftar hanya dengan email `@students.unnes.ac.id` (atur di `ALLOWED_DOMAINS`) + verifikasi lewat tautan |
| Profil pengguna kampus | Nama, jurusan, angkatan tampil di kartu barang, halaman barang, dan `profile.php` |
| Basis data: profil, katalog, riwayat transaksi | Tabel `users`, `items`, `item_images`, `favorites`, `conversations`, `messages`, `promo_requests` (`db/init.sql`) |
| Pencarian & filter: kategori, mata kuliah, harga | Pencarian judul/deskripsi, 6 kategori, filter kondisi, rentang harga, mata kuliah, urutan |
| Chat penjual-pembeli | `chat.php` (polling 3 detik) + tombol WhatsApp opsional |
| Fitur unggulan / biaya promosi | `promo.php` (paket 3/7/14 hari) → admin aktifkan di `admin.php` → barang naik ke atas + tag “Unggulan” |
| Mudah diakses dari HP dan laptop | Tata letak responsif, sidebar jadi menu geser di HP |

## Alur verifikasi email

- `VERIFY_MODE=link` (default): tautan verifikasi muncul di halaman login setelah daftar. Cocok untuk demo.
- `VERIFY_MODE=mail`: tautan dikirim lewat `mail()` PHP. Perlu SMTP/sendmail di server.
- Untuk uji coba dengan email biasa, tambahkan domain di `.env`, mis. `ALLOWED_DOMAINS=students.unnes.ac.id,gmail.com` (hapus lagi saat produksi).
- Admin juga bisa memverifikasi pengguna manual di tab **Pengguna**.

## Promosi (tanpa payment gateway)

Pembayaran dikonfirmasi manual: penjual mengajukan paket, transfer sesuai `PROMO_PAYMENT_INFO`,
lalu admin menekan **Aktifkan** di `admin.php?tab=promo`. Harga paket ada di `PROMO_PACKAGES` (`app/inc/bootstrap.php`).

## Struktur

```
docker-compose.yml  Dockerfile  .env
db/init.sql         skema + kategori awal (jalan otomatis saat database pertama kali dibuat)
docker/             php.ini, aturan Apache (folder upload tidak bisa menjalankan PHP)
app/inc/            bootstrap.php (DB, session, CSRF, helper), layout.php (header, sidebar, kartu)
app/public/         index, item, sell, login, register, verify, logout, profile, chat, api, promo, admin
app/public/assets/  style.css (tema terang/gelap), app.js
```

## Keamanan yang sudah ada

Prepared statements (anti SQL injection), `htmlspecialchars` (anti XSS), token CSRF di semua form/aksi POST,
`password_hash`, validasi tipe file upload dengan `finfo` (JPG/PNG/WebP, maks 4 MB, nama acak),
folder upload tanpa eksekusi PHP, cek kepemilikan barang/percakapan di server.

## Sebelum dipakai publik

1. Ganti semua password di `.env`. Jangan commit `.env` ke Git.
2. Pasang HTTPS (Caddy/Nginx/Cloudflare di depan container) dan atur `APP_URL` ke domain asli.
3. Atur SMTP lalu ubah `VERIFY_MODE=mail`.
4. Backup rutin volume `dbdata` dan `uploads`.
5. Pengembangan lanjutan: batas percobaan login, kompres foto otomatis, WebSocket untuk chat realtime, payment gateway.
# RE-YUSE-
