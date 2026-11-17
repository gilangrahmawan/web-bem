# Catatan Produksi & Lingkungan — Website BEM UBHINUS

Catatan teknis yang **wajib dibaca sebelum deploy** dan saat mengelola server. Panduan deploy lengkap: [`DEPLOY.md`](DEPLOY.md) (dua jalur: dengan/tanpa SSH, checklist go-live).

---

## 1. ⚠️ APP_KEY wajib dibackup — jangan pernah di-generate ulang

Isi **pesan chat disimpan terenkripsi** di database memakai `APP_KEY` (keputusan Fase 9). Kolom lain yang juga terenkripsi: `two_factor_secret` (2FA, Fase 10).

- Setelah situs produksi dipakai, **jangan menjalankan `php artisan key:generate` lagi** di server produksi.
- Bila `APP_KEY` hilang atau berganti, **semua pesan chat lama tidak bisa dibaca lagi** (tidak ada cara memulihkannya) dan 2FA harus diatur ulang.
- Simpan salinan `APP_KEY` produksi di tempat aman yang terpisah dari server (mis. pengelola kata sandi milik Ketua & Sekretaris).
- Saat pindah hosting atau restore backup, salin `APP_KEY` yang **sama** ke `.env` server baru.
- `APP_KEY` tidak boleh di-commit ke Git dan tidak boleh dibagikan lewat chat.
- **`BACKUP_ARCHIVE_PASSWORD`** (Fase 10) mengunci ZIP backup: simpan bersama `APP_KEY`. Tanpa password ini backup tidak bisa dibuka; tanpa `APP_KEY` yang sama, chat di backup tidak terbaca.

## 2. Chat real-time (Pusher Channels)

- Isi di `.env`: `BROADCAST_CONNECTION=pusher`, `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER=ap1`. Tulis tanpa spasi di sekitar `=`.
- Server Pusher **tidak pernah menerima isi pesan**: sinyal real-time hanya berisi nomor percakapan; browser mengambil isi lewat server sendiri setelah lolos pemeriksaan hak akses.
- Bila Pusher kosong/bermasalah, set `BROADCAST_CONNECTION=log` — chat tetap jalan dengan polling 5 detik.
- Paket Sandbox gratis: 100 koneksi bersamaan & 200 ribu pesan/hari — cukup untuk ±40 pengurus.

## 3. Push notification (Web Push / PWA)

- Buat kunci sekali: `php artisan webpush:vapid` → mengisi `VAPID_PUBLIC_KEY` & `VAPID_PRIVATE_KEY` di `.env`.
- Isi `VAPID_SUBJECT=mailto:<email resmi BEM>` (Apple/iPhone menolak subject selain `mailto:` atau `https://`).
- **Jangan mengganti kunci VAPID** setelah dipakai: semua perangkat yang sudah mengaktifkan notifikasi harus mengaktifkan ulang.
- Push butuh **HTTPS** di produksi (localhost dikecualikan oleh browser).
- iPhone/iPad: iOS 16.4+, situs harus ditambahkan ke Layar Utama lalu dibuka dari ikonnya (panduan ada di halaman *Notifikasi*).

### Windows lokal (Herd/Laragon): OpenSSL butuh `OPENSSL_CONF`

Pembuatan kunci VAPID dan pengiriman push memakai OpenSSL (kurva EC). Di Windows, PHP sering tidak menemukan `openssl.cnf`, muncul galat *"Unable to create the key"*. Di hosting Linux hal ini tidak terjadi.

Solusi (set **sebelum** PHP dijalankan):

1. Cari berkasnya, contoh Herd: `C:\Users\<nama>\.config\herd\bin\php85\extras\ssl\openssl.cnf`.
2. Windows → *Edit environment variables for your account* → tambah variabel `OPENSSL_CONF` berisi path tersebut.
3. Tutup lalu buka ulang terminal, Herd, dan editor.

Sekali jalan saja (Git Bash): `OPENSSL_CONF="C:/Users/<nama>/.config/herd/bin/php85/extras/ssl/openssl.cnf" php artisan webpush:vapid`.

## 4. Antrean (queue) tanpa Supervisor

- `QUEUE_CONNECTION=database`. Push notification dikirim lewat antrean agar halaman tidak lambat; lonceng dashboard tetap langsung.
- Scheduler menjalankan `queue:work --stop-when-empty --max-time=50` **setiap menit** — cukup satu cron di hosting:
  `* * * * * cd /path/ke/proyek && php artisan schedule:run >> /dev/null 2>&1`
- Lokal: jalankan `php artisan schedule:work` (atau `php artisan queue:work`) di terminal terpisah agar push terkirim.

## 5. Penyimpanan berkas & data pribadi

- Berkas privat (Drive, lampiran chat, CV/foto oprec, proposal/LPJ) di disk `local` (`storage/app/private`), tidak bisa diakses langsung dari URL.
- Lampiran chat dihapus otomatis setelah `chat_attachment_retention_months` (12 bulan); teks pesan tetap.
- Data pribadi pendaftar oprec yang tidak diterima dihapus otomatis setelah `recruitment_retention_months` (6 bulan sejak gelombang ditutup).
- Sampah Drive dihapus permanen setelah `drive_trash_days` (30 hari).
- Backup otomatis (Fase 10): database harian & berkas mingguan ke `storage/backups` (ZIP AES-256), siap Cloudflare R2 lewat `BACKUP_DISK=r2`. Selama belum R2, unduh backup terbaru tiap bulan ke tempat lain (pengingat otomatis tanggal 1). Lihat DEPLOY.md bagian 12.

## 6. Uji dari HP di jaringan yang sama (lokal)

Di `APP_ENV=local`, semua URL (CSS/JS Vite, aset Filament, Livewire termasuk upload, foto di disk `public`, manifest PWA, service worker, `/broadcasting/auth`) **mengikuti alamat yang sedang dibuka**, bukan `APP_URL` (middleware `ConfigureRequestUrls`). Di `APP_ENV=production` semua URL tetap dikunci ke `APP_URL` + HTTPS.

1. Laptop dan HP tersambung ke **Wi-Fi yang sama**.
2. Cari IP laptop: `ipconfig` → baris *IPv4 Address* adaptor Wi-Fi (mis. `192.168.1.20`).
3. Build aset: `npm run build`. Bila `npm run dev` sedang berjalan, CSS/JS dari server Vite dev otomatis diabaikan saat dibuka lewat IP (memakai hasil build), jadi pastikan build terbaru ada.
4. Jalankan server yang bisa diakses jaringan: `php artisan serve --host=0.0.0.0 --port=8000`.
5. Izinkan port di Windows Firewall bila HP tidak bisa membuka: *Windows Defender Firewall → Advanced settings → Inbound Rules → New Rule → Port → TCP 8000 → Allow → Private*. Hapus aturan ini setelah selesai uji.
6. Di HP buka `http://192.168.1.20:8000` (publik) atau `http://192.168.1.20:8000/panel-bem` (dashboard).

Batasan alamat IP + HTTP:
- **Push notification, pemasangan PWA, dan service worker hanya berjalan di HTTPS** (browser menganggap `http://IP` tidak aman; `localhost` dikecualikan). Untuk menguji fitur ini dari HP, pakai alamat HTTPS sementara, mis. `herd share` (Herd) atau Cloudflare Tunnel, lalu buka alamat `https://…` yang diberikan.
- Kamera/upload foto, chat (Pusher), Turnstile uji, dan fitur lain tetap bisa diuji lewat `http://IP:8000`.
- Setelah selesai, hentikan server `--host=0.0.0.0` agar dashboard lokal tidak terbuka untuk perangkat lain di jaringan.
