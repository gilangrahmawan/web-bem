# Panduan Deploy — Website BEM UBHINUS

Panduan memasang website di **shared hosting** (cPanel atau sejenisnya). Ada dua jalur:

- **Jalur A — dengan SSH/Terminal** (disarankan, lebih cepat dan aman).
- **Jalur B — tanpa SSH** (hanya File Manager cPanel + Cron Jobs).

Bacaan pendamping: [`CATATAN-PRODUKSI.md`](CATATAN-PRODUKSI.md) (Pusher, VAPID, OpenSSL, antrean) dan [`AUDIT-KEAMANAN.md`](AUDIT-KEAMANAN.md).

---

## 0. ⚠️ Tiga rahasia yang WAJIB disimpan aman

Simpan ketiganya di **pengelola kata sandi** milik Ketua **dan** Sekretaris (misalnya Bitwarden), terpisah dari server. Jangan dikirim lewat chat dan jangan pernah di-commit ke Git.

| Rahasia | Kenapa penting | Bila hilang/berganti |
|---|---|---|
| `APP_KEY` | Mengenkripsi isi chat, rahasia 2FA, dan session | **Semua pesan chat lama tidak bisa dibaca lagi, selamanya.** 2FA semua pengurus harus diatur ulang. |
| `BACKUP_ARCHIVE_PASSWORD` | Mengunci (AES-256) berkas ZIP backup | **Semua backup tidak bisa dibuka**, jadi tidak bisa dipulihkan. |
| `VAPID_PUBLIC_KEY` + `VAPID_PRIVATE_KEY` | Kunci push notification | Semua pengurus harus mengaktifkan ulang notifikasi di tiap perangkat. |

Aturan keras:

- **`php artisan key:generate` hanya dijalankan SEKALI** saat pemasangan pertama di produksi. Jangan diulang setelah situs dipakai, termasuk saat update, pindah hosting, atau restore.
- Saat pindah hosting atau restore backup, salin `APP_KEY` dan `BACKUP_ARCHIVE_PASSWORD` yang **sama** ke `.env` server baru.
- Restore database tanpa `APP_KEY` yang sama tetap jalan, tetapi isi chat dan 2FA tidak terbaca.

---

## 1. Syarat hosting

| Kebutuhan | Keterangan |
|---|---|
| PHP **8.4** (minimal 8.3) | Ekstensi: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`; `gmp` disarankan (push) |
| `memory_limit` ≥ 128M, `upload_max_filesize` ≥ 12M, `post_max_size` ≥ 25M | Atur lewat cPanel → *Select PHP Version* → *Options* / *MultiPHP INI Editor* |
| MySQL 8 / MariaDB 10.6+ | Satu database + user khusus |
| **Cron Jobs** | Wajib (scheduler tiap menit: antrean push, backup, pengingat) |
| `mysqldump` | Untuk backup database (umumnya tersedia; bila tidak, isi `DB_DUMP_BINARY_PATH`) |
| HTTPS (SSL) | Wajib untuk push notification dan login aman; AutoSSL cPanel atau Cloudflare |
| Ruang disk | ±1 GB aplikasi + Drive (kuota 2 GB) + backup (±2 GB) |
| Fungsi PHP `proc_open`, `symlink` | Dipakai backup & `storage:link`; tanyakan ke hosting bila dinonaktifkan |

Node.js **tidak** dibutuhkan di server: aset dibuild di laptop (`npm run build`).

---

## 2. Persiapan di laptop (sebelum upload)

```bash
git pull                               # kode terbaru
composer install                       # termasuk dev (untuk menjalankan test)
php artisan test                       # semua harus lulus
npm ci && npm run build                # hasil: public/build
php artisan bem:export-data            # paket data penting (lihat bagian 8)
```

Untuk **Jalur B** (tanpa SSH), siapkan juga paket berisi `vendor/` tanpa paket dev:

```bash
composer install --no-dev --optimize-autoloader
# ZIP seluruh folder proyek KECUALI: .env, node_modules/, storage/app/*, storage/backups/,
# storage/logs/*, storage/framework/{cache,sessions,views}/* (biarkan foldernya ada), _aset/, .git/
# Setelah itu kembalikan dev: composer install
```

> Jangan pernah meng-upload `.env` lokal, isi `storage/app`, atau `storage/backups` lokal ke server.

---

## 3. Struktur folder & document root

Simpan proyek **di luar** folder web, misalnya `/home/akunanda/bemweb`. Hanya folder `public` yang boleh bisa diakses dari internet.

### 3A. (Disarankan) Arahkan domain/subdomain ke `bemweb/public`

cPanel → *Domains* (atau *Subdomains* / *Addon Domains*) → ubah **Document Root** menjadi `bemweb/public`. Selesai; `PUBLIC_PATH` dibiarkan kosong.

### 3B. Alternatif: domain utama terkunci di `public_html`

1. Upload proyek ke `/home/akunanda/bemweb` (bukan di dalam `public_html`).
2. Pindahkan **isi** folder `bemweb/public/` ke `public_html/` (termasuk `.htaccess`, `build/`, `images/`, `index.php`, `sw.js`, `manifest.webmanifest`, `offline.html`, `favicon.png`, `robots.txt`).
3. Edit `public_html/index.php`: ganti setiap `__DIR__.'/../` menjadi `__DIR__.'/../bemweb/`:

   ```php
   if (file_exists($maintenance = __DIR__.'/../bemweb/storage/framework/maintenance.php')) {
       require $maintenance;
   }
   require __DIR__.'/../bemweb/vendor/autoload.php';
   $app = require_once __DIR__.'/../bemweb/bootstrap/app.php';
   ```

4. Di `bemweb/.env` isi `PUBLIC_PATH=/home/akunanda/public_html`. Dengan ini `storage:link`, aset build, dan URL berkas memakai `public_html`.
5. Setiap update, ulangi langkah 2 untuk folder `build/` (dan berkas publik lain yang berubah).

---

## 4. Jalur A — dengan SSH / Terminal cPanel

```bash
cd ~
git clone git@github.com:<akun>/<repo-private>.git bemweb     # atau upload ZIP lalu ekstrak
cd bemweb
composer install --no-dev --optimize-autoloader
cp .env.example .env && nano .env                            # isi sesuai bagian 6
php artisan key:generate                                     # ⚠️ SEKALI SAJA, lalu simpan APP_KEY (bagian 0)
php artisan migrate --force
php artisan db:seed --force                                  # divisi, role/permission, setting, hari libur; TANPA akun demo
php artisan storage:link
php artisan bem:create-admin                                 # akun Ketua (bagian 7)
php artisan bem:import-data ~/bem-data-....zip --dry-run     # cek dulu (bagian 8)
php artisan bem:import-data ~/bem-data-....zip
php artisan webpush:vapid                                    # kunci push BARU untuk produksi (bagian 9)
php artisan optimize && php artisan filament:optimize
```

Upload `public/build` hasil `npm run build` dari laptop (folder ini tidak ada di Git). Lalu pasang cron (bagian 10).

---

## 5. Jalur B — tanpa SSH (File Manager + Cron Jobs)

1. **Database:** cPanel → *MySQL Databases* → buat database, user, dan beri **ALL PRIVILEGES**.
2. **Upload:** File Manager → upload ZIP proyek (bagian 2, dengan `vendor/` dan `public/build/`) ke `/home/akunanda/` → *Extract* → ganti nama folder menjadi `bemweb`. Atur document root (bagian 3).
3. **`.env`:** salin `.env.example` → `.env`, edit lewat File Manager (bagian 6).
4. **`APP_KEY`:** buat di laptop dengan `php artisan key:generate --show` (hanya menampilkan, tidak mengubah apa pun), salin ke `APP_KEY=` di `.env` server. ⚠️ Simpan nilainya (bagian 0). Lakukan **sekali saja**.
5. **Menjalankan perintah artisan tanpa SSH** memakai *Cron Jobs* sekali jalan:
   - cPanel → *Cron Jobs* → buat cron **setiap menit** berisi perintah di bawah, tunggu 1–2 menit, lalu **hapus cron itu**. Hasilnya bisa dilihat di `bemweb/storage/logs/deploy.log`.

   ```
   cd /home/akunanda/bemweb && php artisan migrate --force >> storage/logs/deploy.log 2>&1 && php artisan db:seed --force >> storage/logs/deploy.log 2>&1 && php artisan storage:link >> storage/logs/deploy.log 2>&1
   ```

   - Ganti `php` dengan path PHP 8.4 dari hosting bila perlu, misalnya `/usr/local/bin/ea-php84` atau `/opt/cpanel/ea-php84/root/usr/bin/php`.
6. **Akun Ketua tanpa SSH:** `bem:create-admin` butuh mengetik, jadi pakai cara sekali pakai:
   1. Isi sementara di `.env`: `SUPERADMIN_NAME`, `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD` (password sementara yang kuat).
   2. Cron sekali jalan: `cd /home/akunanda/bemweb && php artisan db:seed --class=SuperAdminSeeder --force >> storage/logs/deploy.log 2>&1`
   3. **Segera kosongkan kembali** ketiga baris `SUPERADMIN_*` di `.env`.
   4. Login, lalu ganti password dan atur 2FA (wajib).
7. **Import data:** upload paket `bem-data-....zip` ke `bemweb/storage/app/private/`, lalu cron sekali jalan:
   - Cek dulu: `cd /home/akunanda/bemweb && php artisan bem:import-data storage/app/private/bem-data-....zip --dry-run >> storage/logs/deploy.log 2>&1`
   - Bila sesuai, jalankan lagi dengan `--force` sebagai pengganti `--dry-run`. Setelah itu hapus paket ZIP dari server.
8. **Kunci VAPID produksi:** buat di laptop dengan `php artisan webpush:vapid --show`, lalu salin `VAPID_PUBLIC_KEY` dan `VAPID_PRIVATE_KEY` ke `.env` server.
9. **Cache:** cron sekali jalan: `cd /home/akunanda/bemweb && php artisan optimize >> storage/logs/deploy.log 2>&1 && php artisan filament:optimize >> storage/logs/deploy.log 2>&1`
10. Pasang **cron permanen** (bagian 10).

> Bila `storage:link` gagal karena symlink dinonaktifkan hosting, minta hosting mengaktifkannya. Tanpa link ini, foto, cover berita, dan galeri tidak tampil.

---

## 6. Isi `.env` produksi

```dotenv
APP_NAME="BEM UBHINUS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda           # HTTPS, tanpa garis miring di akhir
APP_KEY=                              # dari key:generate — SEKALI SAJA, simpan aman
APP_LOCALE=id
APP_FAKER_LOCALE=id_ID

LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=akun_bem                  # nama database dari cPanel
DB_USERNAME=akun_bemuser
DB_PASSWORD=...                       # password database (kuat)

SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

# Akun Ketua: KOSONGKAN, lalu pakai php artisan bem:create-admin (bagian 7)
SUPERADMIN_NAME=
SUPERADMIN_EMAIL=
SUPERADMIN_PASSWORD=
DEMO_PASSWORD=                        # kosong di produksi

TURNSTILE_SITE_KEY=                   # kunci ASLI Cloudflare untuk domain ini
TURNSTILE_SECRET_KEY=

BROADCAST_CONNECTION=pusher           # atau log (tanpa real-time, chat polling 5 detik)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=ap1

VAPID_SUBJECT=mailto:email-resmi-bem@domain
VAPID_PUBLIC_KEY=                     # kunci BARU untuk produksi
VAPID_PRIVATE_KEY=

BACKUP_ARCHIVE_PASSWORD=              # WAJIB diisi & disimpan aman bersama APP_KEY
BACKUP_DISK=backups                   # r2 bila sudah memakai Cloudflare R2
DB_DUMP_BINARY_PATH=                  # kosong bila mysqldump ada di PATH

HSTS_ENABLED=false                    # nyalakan nanti (lihat checklist)
PUBLIC_PATH=                          # hanya untuk alternatif public_html (bagian 3B)
```

Setelah `.env` diubah dan cache aktif, jalankan `php artisan optimize` ulang (atau `optimize:clear` lalu `optimize`).

---

## 7. Akun Ketua pertama

```bash
php artisan bem:create-admin
# Nama lengkap, email, password sementara (diketik tersembunyi, tidak tersimpan di .env)
```

- Akun langsung menjadi **Super Admin** dan aktif.
- Login pertama: **wajib ganti password**, lalu **wajib mengatur 2FA** (aplikasi Google Authenticator / Microsoft Authenticator / Authy). **Simpan 8 kode pemulihan** di tempat aman.
- Wakil Ketua: buat dari menu *User* oleh Ketua (role Super Admin), atau jalankan `bem:create-admin --position="Wakil Ketua"`.
- Bila Ketua dan Wakil sama-sama kehilangan HP dan kode pemulihan: `php artisan bem:reset-2fa email@...` (lewat SSH/cron).

---

## 8. Memindahkan data dari laptop ke produksi

Yang ikut: pengaturan website, divisi, periode, struktur kepengurusan (+foto), proker, agenda kalender, organisasi, template dokumen, berita/kajian/press release yang **sudah terbit** (+cover/lampiran), galeri (+foto), dan berkas unduhan.

Yang **tidak** ikut: semua akun (termasuk akun demo `@demo.test`), konten "Contoh"/`contoh-*`, kas, aspirasi, chat, Drive, oprec, notifikasi, log, dan pengaturan teknis seperti `kas_start_date`, kuota, dan masa simpan. Pengaturan teknis diatur ulang di produksi.

1. Di laptop: `php artisan bem:export-data`. Hasilnya `storage/app/private/exports/bem-data-YYYYMMDD-HHMMSS.zip`. Paket ini berisi foto pengurus, jadi perlakukan sebagai data pribadi.
2. Upload ke server (di luar folder publik).
3. **Buat akun Ketua dulu** (bagian 7): berita impor dicatat atas nama Super Admin pertama.
4. `php artisan bem:import-data <paket.zip> --dry-run`: periksa tabel "Baru/Diperbarui".
5. `php artisan bem:import-data <paket.zip>`. Aman diulang; data yang sama diperbarui, tidak digandakan.
6. Hapus paket ZIP dari server dan laptop setelah selesai.
7. Di dashboard produksi: atur **Kas Mingguan → Pengaturan kas** (tanggal mulai & nominal) dan cek *Pengaturan Website*.

---

## 9. Kunci layanan produksi (buat BARU, jangan pakai kunci lokal)

| Layanan | Langkah |
|---|---|
| **Cloudflare Turnstile** | dash.cloudflare.com → Turnstile → *Add site* → domain produksi → mode *Managed* → salin Site Key & Secret Key ke `.env` |
| **Pusher Channels** | dashboard.pusher.com → buat app **baru** khusus produksi (cluster `ap1`) → *App Keys* → isi `PUSHER_*` → *App Settings*: pastikan *Enable client events* **mati** |
| **VAPID (push)** | `php artisan webpush:vapid --show` (di laptop atau server) → isi `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY`; isi `VAPID_SUBJECT=mailto:<email BEM>`; simpan aman (bagian 0) |
| **Password backup** | Buat password acak ≥ 24 karakter → `BACKUP_ARCHIVE_PASSWORD`; simpan aman bersama `APP_KEY` |
| **Cloudflare R2** (opsional, nanti) | Buat bucket + API token → isi `R2_*`, ubah `BACKUP_DISK=r2` → uji `php artisan backup:run --only-db` |

Kunci lokal (Turnstile uji `1x000…`, Pusher sandbox lokal, VAPID lokal) **tidak boleh** dipakai di produksi.

---

## 10. Cron (wajib)

cPanel → *Cron Jobs* → *Every Minute* (`* * * * *`):

```
cd /home/akunanda/bemweb && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler ini menjalankan antrean push setiap menit, pengingat tugas/kas/LPJ, pembersihan Sampah Drive, data oprec, lampiran chat, serta backup (DB harian 01.00, berkas Minggu 01.30, pembersihan 04.00, pemantauan 05.00) dan pengingat backup bulanan.

Uji: `php artisan schedule:list`. Setelah 2–3 menit, halaman *Backup* akan menampilkan backup berikutnya sesuai jadwal; tekan juga *Backup database sekarang* sekali.

---

## 11. Update versi (setelah go-live)

```bash
php artisan down --render="errors::503"     # opsional, mode perawatan
git pull                                   # atau upload berkas baru (Jalur B)
composer install --no-dev --optimize-autoloader
php artisan migrate --force                # JANGAN migrate:fresh / key:generate
php artisan optimize:clear && php artisan optimize && php artisan filament:optimize
php artisan up
```

Upload ulang `public/build` bila tampilan/JS berubah. **Jangan** menjalankan `db:seed` ulang tanpa perlu; perubahan permission selalu lewat migration yang hanya menambah.

---

## 12. Backup & restore

- Otomatis: DB harian, berkas mingguan (Drive, lampiran, foto) di `storage/backups/bem-ubhinus/`, ZIP terenkripsi.
- Retensi: semua backup 7 hari terakhir, 1 per minggu selama 4 minggu, 1 per bulan selama 3 bulan; total maks. ±2 GB.
- Bila backup gagal atau tidak sehat, Super Admin mendapat notifikasi lonceng + push.
- **Selama belum memakai R2:** setiap tanggal 1, Super Admin mendapat pengingat. Buka *Pengaturan → Backup*, unduh berkas terbaru, simpan di **tempat lain** (Google Drive akun BEM / flashdisk Sekretariat). Backup di server yang sama tidak menolong bila server rusak.

**Restore (darurat):**

1. Ekstrak ZIP dengan `BACKUP_ARCHIVE_PASSWORD` (7-Zip/WinRAR/`unzip -P`).
2. Database: impor `db-dumps/mysql-<nama>.sql` lewat phpMyAdmin (database kosong) atau `mysql -u user -p nama_db < berkas.sql`.
3. Berkas: salin isi `storage/app/private` dan `storage/app/public` dari backup berkas ke server.
4. Pastikan `.env` memakai **`APP_KEY` yang sama** dengan saat backup dibuat → `php artisan optimize:clear`.

---

## 13. Checklist go-live (langkah demi langkah)

**A. Sebelum hari H**
- [ ] Hosting memenuhi bagian 1 (versi PHP, ekstensi, cron, SSL aktif).
- [ ] Domain/subdomain mengarah ke server; HTTPS (AutoSSL/Cloudflare) aktif.
- [ ] Repository GitHub **private**; `.env`, `storage/`, `_aset/` tidak ikut ter-commit (cek `git status`).
- [ ] `php artisan test` lulus; `npm run build` sudah dibuat.
- [ ] `php artisan bem:export-data` → paket data siap.
- [ ] Siapkan kunci produksi baru (bagian 9) dan password backup.

**B. Pemasangan**
- [ ] Upload/clone proyek di luar `public_html`; document root ke `public` (atau alternatif 3B + `PUBLIC_PATH`).
- [ ] `.env` produksi (bagian 6): `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`.
- [ ] `key:generate` **sekali** → `APP_KEY` disimpan di pengelola kata sandi Ketua & Sekretaris.
- [ ] `BACKUP_ARCHIVE_PASSWORD` diisi → disimpan bersama `APP_KEY`.
- [ ] `migrate --force`, `db:seed --force` (tanpa akun demo), `storage:link`.
- [ ] `bem:create-admin` → login → ganti password → atur 2FA → simpan kode pemulihan.
- [ ] `bem:import-data --dry-run` lalu import; hapus paket ZIP.
- [ ] Kunci Turnstile asli, Pusher produksi, VAPID baru diisi; `php artisan optimize`.
- [ ] Cron `schedule:run` setiap menit terpasang.

**C. Uji setelah pasang**
- [ ] Halaman publik terbuka dengan HTTPS; gambar/berita/struktur tampil (storage link OK).
- [ ] Form aspirasi: Turnstile tampil & kirim berhasil; cek status dengan kode.
- [ ] Login Ketua dengan 2FA; buat akun Sekretaris & Bendahara → mereka juga diminta atur 2FA.
- [ ] Halaman Notifikasi → *Aktifkan di perangkat ini* → *Kirim notifikasi uji* diterima di HP.
- [ ] Chat dua akun: pesan muncul real-time (bila Pusher aktif).
- [ ] *Pengaturan → Backup* → *Backup database sekarang* berhasil; unduh & buka ZIP dengan password.
- [ ] *Kas Mingguan → Pengaturan kas*: tanggal mulai & nominal diatur.
- [ ] `APP_DEBUG=false` terbukti: buka URL tidak ada → halaman 404 BEM (bukan pesan error teknis).

**D. Beberapa hari setelah go-live**
- [ ] HTTPS berjalan normal **minimal 3–7 hari** tanpa masalah sertifikat → baru set `HSTS_ENABLED=true` → `php artisan optimize`. (HSTS membuat browser menolak HTTP; bila sertifikat bermasalah setelah HSTS aktif, situs tidak bisa dibuka sampai sertifikat diperbaiki.)
- [ ] Backup harian muncul di halaman Backup; unduh satu salinan ke tempat lain.
- [ ] Bagikan `docs/PANDUAN-PENGURUS.md` ke semua pengurus.

---

## 14. Masalah umum

| Gejala | Penyebab | Solusi |
|---|---|---|
| Halaman putih / 500 | `.env` salah, cache lama | Cek `storage/logs/laravel.log`; `php artisan optimize:clear` |
| CSS/JS hilang, error "Vite manifest" | `public/build` belum di-upload | Upload hasil `npm run build` ke `public/build` (atau `public_html/build`) |
| Gambar tidak tampil | `storage:link` belum/gagal | Jalankan `php artisan storage:link`; minta hosting mengizinkan symlink |
| Push tidak sampai | Cron tidak jalan / VAPID kosong / bukan HTTPS | Cek cron & `php artisan schedule:list`; isi VAPID; gunakan HTTPS |
| Chat tidak real-time | Kredensial Pusher salah | Cek `PUSHER_*`; sementara set `BROADCAST_CONNECTION=log` (polling 5 detik) |
| Backup gagal | `mysqldump` tidak ditemukan / password backup kosong | Isi `DB_DUMP_BINARY_PATH`; isi `BACKUP_ARCHIVE_PASSWORD` |
| Pesan chat lama jadi rusak | `APP_KEY` berganti | Kembalikan `APP_KEY` lama dari pengelola kata sandi |
| Ketua terkunci 2FA | HP & kode pemulihan hilang | `php artisan bem:reset-2fa email@...` |
