# GPS Trip Monitor — Cordova + Leaflet

Aplikasi mobile untuk memantau perjalanan secara **real-time** menggunakan GPS, dengan peta interaktif berbasis **Leaflet** dan tema dark studio dengan aksen aurora gradient.

## Fitur

- **Wajib login** sebelum bisa memakai aplikasi — mendukung banyak user sekaligus, tiap user datanya terpisah di server
- **Offline-first**: setiap perjalanan disimpan ke **SQLite lokal di HP** (`cordova-sqlite-storage`) — tetap tercatat walau HP mati total sinyal/data internet
- **Auto-sync otomatis**: begitu HP kembali online, semua trip yang tertunda otomatis diupload ke server tanpa perlu tindakan manual (pill status di pojok kanan atas menunjukkan status: Offline / Menyinkronkan / Tersinkron)
- **Tetap melacak saat aplikasi diminimize/background**, lewat plugin `cordova-background-geolocation-lt` (gratis, open-source) yang menjalankan foreground service Android dengan notifikasi persisten
- Pelacakan posisi real-time (`navigator.geolocation.watchPosition`, dipatch oleh `cordova-plugin-geolocation`) — dipakai sebagai fallback jika plugin background belum terpasang (mis. saat uji coba di browser)
- Jejak rute (trail) digambar langsung di peta sebagai polyline
- Panel HUD: kecepatan saat ini, jarak tempuh, durasi, kecepatan rata-rata, akurasi GPS, koordinat
- Kontrol Mulai / Jeda / Selesai
- Penyaringan noise GPS sederhana (lompatan kecil di bawah radius akurasi diabaikan)
- Riwayat perjalanan tersimpan di `localStorage`, bisa dibuka kembali di peta atau dihapus
- Marker posisi dengan animasi radar-pulse
- Peta otomatis mengikuti (pan) posisi pengguna

## Struktur proyek

```
gps-tracker/
├── config.xml          # Konfigurasi Cordova (izin lokasi, plugin, dst.)
├── package.json
└── www/
    ├── index.html
    ├── css/style.css
    └── js/app.js        # Semua logika tracking & UI
```

## Cara menjalankan

### 1. Persiapan

```bash
npm install -g cordova
cd gps-tracker
```

### 2. Tambahkan platform

```bash
cordova platform add android
# atau untuk iOS (butuh macOS + Xcode)
cordova platform add ios
```

### 3. Pasang plugin (otomatis terbaca dari config.xml, tapi bisa manual)

```bash
cordova plugin add cordova-plugin-geolocation
cordova plugin add cordova-plugin-whitelist
cordova plugin add cordova-plugin-device
cordova plugin add cordova-plugin-statusbar
```

### 4. Jalankan di emulator / perangkat

```bash
cordova run android
# atau build saja
cordova build android
```

### 5. Uji cepat di browser (opsional, sebelum build native)

Karena `www/index.html` memuat `cordova.js` yang hanya ada setelah `cordova prepare`, untuk uji coba cepat di browser:

```bash
cordova prepare android   # ini akan menghasilkan www/cordova.js
cordova serve
```

Lalu buka `http://localhost:8000/android/www/` di browser desktop (izinkan akses lokasi saat diminta). Akurasi GPS di desktop browser biasanya rendah/simulasi.

## Setup server backend — pilih SQLite atau MySQL

Ada 2 versi backend di proyek ini, pilih salah satu (fungsinya identik, cuma database-nya beda):

- **`server/`** — pakai SQLite (tidak perlu buat database manual, otomatis dibuat sebagai file)
- **`server-mysql/`** — pakai MySQL lewat phpMyAdmin XAMPP (kalau Anda lebih terbiasa lihat/edit data lewat GUI)

### Opsi A — SQLite (`server/`)

1. Salin folder `server/` ke `C:\xampp\htdocs\gps-tracker-server`
2. Jalankan Apache dari XAMPP Control Panel (MySQL tidak perlu dinyalakan untuk opsi ini)
3. Cek di browser laptop: `http://localhost/gps-tracker-server/register.php` → harus muncul JSON error "Metode tidak diizinkan" (tandanya PHP jalan)

### Opsi B — MySQL (`server-mysql/`)

1. Nyalakan **Apache** dan **MySQL** dari XAMPP Control Panel
2. Buka `http://localhost/phpmyadmin` → **New** (sidebar kiri) → nama database: `gpstracker` → collation `utf8mb4_general_ci` → **Create**
3. Salin folder `server-mysql/` ke `C:\xampp\htdocs\gps-tracker-server`
4. Kalau MySQL XAMPP Anda pakai password (bukan default kosong), edit `$DB_USER` / `$DB_PASS` di `server-mysql/config.php`
5. Tabel (`users`, `tokens`, `trips`) otomatis dibuat sendiri saat endpoint pertama kali diakses — tidak perlu import SQL manual

### Setelah pilih salah satu — set alamat server di app

1. Cari **alamat IP lokal laptop** (`ipconfig` di Windows, cari IPv4, contoh `192.168.1.10`)
2. Di `www/js/api.js`, ubah baris:
   ```js
   var API_BASE_URL = 'http://192.168.1.10/gps-tracker-server';
   ```
   Ganti dengan IP laptop Anda. **HP dan laptop harus terhubung ke WiFi yang sama.**
3. Push perubahan ke GitHub supaya APK di-build ulang dengan alamat server yang baru.

### Deploy ke hosting (untuk dipakai beneran di luar rumah)

1. Upload folder `server/` atau `server-mysql/` ke hosting Anda (cPanel File Manager, FTP, dll) — pastikan support PHP 7.4+ (dan MySQL kalau pakai opsi B)
2. Set `API_BASE_URL` di `www/js/api.js` ke `https://domainanda.com/gps-tracker-server`
3. Untuk opsi SQLite, pastikan folder `server/data/` writable (`chmod 775 server/data`)

## Panel admin (lihat semua user & riwayat perjalanan lewat browser)

Folder `admin/` (ada di dalam `server/` maupun `server-mysql/`, isinya sama) adalah dashboard web sederhana untuk memantau semua user dan perjalanan mereka — dibuka lewat browser laptop, bukan bagian dari app HP.

### Setup

1. Buka `server/admin/config_admin.php` (atau `server-mysql/admin/config_admin.php`, sesuai backend yang Anda pakai)
2. **WAJIB ganti** baris ini dengan password Anda sendiri:
   ```php
   define('ADMIN_PASSWORD', 'ganti-password-ini-sekarang');
   ```
   Panel ini menampilkan data pribadi semua user (username, rute perjalanan dengan koordinat GPS) — jangan biarkan pakai password default.
3. Akses lewat: `http://192.168.x.x/gps-tracker-server/admin/` (ganti sesuai alamat server Anda) → akan diarahkan ke halaman login

### Yang bisa dilihat

- **Dashboard**: total user, total perjalanan, total jarak gabungan semua user
- **Daftar user**: username, tanggal daftar, jumlah trip, total jarak, trip terakhir
- **Riwayat per user**: klik "Lihat Riwayat" pada satu user → daftar semua perjalanannya
- **Detail trip**: klik "Lihat Rute" pada satu trip → peta Leaflet menampilkan rute lengkap perjalanan tersebut (titik awal & akhir ditandai beda warna)
- **Kolom "Login Terakhir" & "Sesi Aktif"** di tabel user — menunjukkan kapan terakhir tiap user login dan berapa banyak sesi login yang masih aktif (token belum dicabut)
- **Monitoring Hari Ini** (tombol 📍 di dashboard): peta menampilkan SEMUA user yang mencatat perjalanan **hari ini**, tiap user dapat warna rute berbeda supaya mudah dibedakan, plus daftar user aktif hari ini dengan jumlah trip & total jaraknya. "Hari ini" dihitung berdasarkan zona waktu Indonesia (WIB), bukan zona waktu server.

Panel ini pakai autentikasi sesi PHP standar (bukan token API app) — cukup 1 password admin, terpisah dari akun-akun user biasa.

## Bagikan Status (foto + teks)

Tombol kamera 📷 di kontrol bawah peta membuka form: ambil/pilih foto (otomatis dikompresi di HP sebelum diupload, maks ~1024px & kualitas 60% supaya ringan) + tulis status singkat (maks 200 karakter) → tekan Bagikan.

- Status tersimpan dengan koordinat lokasi saat itu, terlihat sebagai **marker foto bulat** di peta untuk **semua user lain yang login**
- Klik marker → muncul popup foto + nama user + teks status + waktu
- Status otomatis kadaluarsa dari peta setelah **24 jam**
- Peta refresh status user lain otomatis tiap 45 detik selama app terbuka
- Status milik diri sendiri tidak ditampilkan sebagai marker (karena sudah jadi marker posisi Anda sendiri)

Endpoint terkait: `upload_status.php` (kirim status), `get_statuses.php` (ambil status terbaru semua user, hanya yang masih dalam 24 jam terakhir).

## Checklist debugging "Failed to fetch" dari dalam app

Kalau error ini muncul saat login/daftar, penyebabnya **bukan soal SQLite vs MySQL** — itu error koneksi jaringan yang terjadi sebelum request sempat sampai ke PHP. Cek satu-satu ini secara berurutan:

1. **APK yang dites benar-benar yang terbaru?** Uninstall total APK lama dari HP dulu, baru install APK hasil build paling akhir (setelah `usesCleartextTraffic="true"` ditambahkan ke `config.xml`). Cache/versi lama adalah penyebab paling sering "sudah diperbaiki tapi masih error".
2. **`API_BASE_URL` di `www/js/api.js` sudah diganti** dari default `10.0.2.2` ke IP asli laptop Anda? Harus persis sama dengan alamat yang berhasil diakses lewat browser HP.
3. **HP dan laptop masih di WiFi yang sama?** IP laptop bisa berubah tiap kali WiFi disconnect/reconnect — cek ulang dengan `ipconfig`.
4. **Windows Firewall memblokir Apache?** Ini sering jadi biang keladi tersembunyi — browser HP bisa akses tapi app tetap gagal karena firewall Windows kadang cuma memblokir jenis koneksi tertentu. Coba matikan sementara **Control Panel → Windows Defender Firewall → Turn off**, lalu tes app lagi. Kalau langsung berhasil, buatkan exception rule khusus untuk Apache/port 80 alih-alih mematikan firewall selamanya.
5. **Minta pesan error yang lebih detail** — pesan generik "Failed to fetch" biasanya menyembunyikan error asli seperti `net::ERR_CLEARTEXT_NOT_PERMITTED` atau `net::ERR_CONNECTION_REFUSED`, yang masing-masing beda penyebab dan solusinya. Kabari saya kalau butuh versi app dengan pesan error lebih detail untuk didiagnosa.

## Catatan penting

- **Izin lokasi**: Android akan meminta izin lokasi saat pertama kali `Mulai Perjalanan` ditekan. Pastikan `ACCESS_FINE_LOCATION` **dan** `ACCESS_BACKGROUND_LOCATION` diizinkan.
- **Tile peta**: Aplikasi memakai tile OpenStreetMap via internet (`https://{s}.tile.openstreetmap.org`). Untuk pemakaian produksi dengan volume tinggi, pertimbangkan menyediakan tile server sendiri atau layanan berbayar (Mapbox, MapTiler) sesuai kebijakan penggunaan OSM.
- **Filter noise GPS**: saat ini sederhana (mengabaikan pergeseran < radius akurasi). Bisa ditingkatkan dengan filter Kalman jika presisi jadi masalah di lapangan.
- **Konsumsi baterai**: tracking latar belakang otomatis lebih boros baterai dibanding tracking foreground biasa — ini trade-off yang tidak terhindarkan untuk fitur ini.
- **CSP**: `index.html` sudah menyertakan `Content-Security-Policy` yang mengizinkan Leaflet dari `unpkg.com` dan tile dari `openstreetmap.org`. Jika ingin membundel Leaflet secara lokal (offline-ready), unduh `leaflet.js`/`leaflet.css` ke `www/lib/leaflet/` dan ubah referensi di `index.html`.

## Kustomisasi cepat

| Ingin ubah... | Di mana |
|---|---|
| Warna aksen (aurora teal/violet) | `www/css/style.css` → variabel `--aurora-1`, `--aurora-2` |
| Ambang noise GPS | `www/js/app.js` → fungsi `onPosition`, baris `Math.max(4, ...)` |
| Titik peta awal (sebelum GPS didapat) | `www/js/app.js` → `initMap()`, `setView([...])` |
| Ikon aplikasi | `res/android/icon-*.png` (belum disertakan, tambahkan sendiri) |
#   g p s - t r i p - m o n i t o r 2  
 