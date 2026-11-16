# Audit Keamanan — Fase 10

Tanggal audit: 17 November 2026 · Cakupan: seluruh kode Fase 1–10 · Metode: pemindaian kode, pemeriksaan konfigurasi, `composer audit`, `npm audit`, dan test otomatis (Pest).

## Ringkasan

| Area | Hasil |
|---|---|
| Otorisasi (policy) | ✅ 21/21 resource punya policy; 13 halaman kustom punya `canAccess` (Dashboard/Profil/Ganti Password memang untuk semua user login) |
| Upload berkas | ✅ mimes + MIME hasil deteksi isi, batas ukuran, nama UUID, disk privat untuk berkas sensitif |
| Output HTML (`{!! !!}`) | ✅ 10 pemakaian, semuanya konten statis, `e()`, atau HTML yang sudah disanitasi purifier |
| SQL mentah | ✅ 27 pemakaian `selectRaw/whereRaw/orderByRaw`, semuanya statis tanpa input pengguna |
| Mass assignment | ✅ tidak ada `$guarded = []`; semua model memakai `$fillable` |
| Form publik | ✅ Turnstile (fail-closed) + rate limit (kirim 5/IP/jam, cek status 20/IP/jam) |
| Dependensi | ✅ `composer audit`: 0 kerentanan · `npm audit`: 0 kerentanan |
| Temuan diperbaiki | 5 (lihat tabel di bawah) |

## Temuan & perbaikan

| # | Temuan | Risiko | File | Perbaikan |
|---|---|---|---|---|
| 1 | 2FA belum ada untuk akun berhak tinggi (Ketua/Wakil, Sekretaris, Bendahara) | Tinggi — password bocor = akses penuh | `app/Providers/Filament/AdminPanelProvider.php`, `app/Http/Middleware/RequireTwoFactorForPrivilegedRoles.php`, `app/Models/User.php` | 2FA TOTP bawaan Filament + 8 kode pemulihan terenkripsi; wajib untuk 3 role (juga di route berkas panel); reset oleh Super Admin / `bem:reset-2fa`; test `tests/Feature/Auth/TwoFactorTest.php` |
| 2 | Cookie session tidak otomatis `Secure` di HTTPS | Sedang — cookie bisa terkirim lewat HTTP | `config/session.php` | `secure` otomatis bila `APP_URL` https (bisa dipaksa `SESSION_SECURE_COOKIE`) |
| 3 | Payload session tersimpan tanpa enkripsi di database | Rendah–sedang — isi session terbaca bila DB bocor | `config/session.php`, `.env.example` | Default `SESSION_ENCRYPT=true` |
| 4 | Belum ada HSTS | Rendah (setelah HTTPS stabil) | `app/Http/Middleware/SecurityHeaders.php`, `config/bem.php` | Header HSTS lewat `HSTS_ENABLED` (default **mati**, nyalakan setelah HTTPS stabil beberapa hari) |
| 5 | Backup belum ada; bila dibuat, berisi data pribadi | Tinggi — kehilangan data / kebocoran arsip | `config/backup.php`, `app/Listeners/NotifyBackupProblems.php` | Backup DB harian + berkas mingguan, ZIP terenkripsi AES-256 (`BACKUP_ARCHIVE_PASSWORD`), disk terpisah, notifikasi gagal ke Super Admin |

Temuan dari fase sebelumnya yang sudah diperbaiki saat itu (dicatat untuk kelengkapan): CSP `frame-src` tanpa `'self'` memblokir pratinjau PDF Drive (Fase 9), anggota grup organisasi tidak berpindah saat organisasi berubah (Fase 9), orientasi EXIF memutar bitmap penuh sehingga memori habis (perbaikan foto).

## Rincian pemeriksaan

### Autentikasi & sesi
- Tanpa registrasi; akun hanya dibuat Super Admin/Sekretaris (pengaju) atau `bem:create-admin`.
- Password min. 10 karakter, huruf + angka + simbol; wajib ganti saat login pertama/setelah reset; session lain diakhiri saat ganti password/reset.
- Login gagal 5× → kunci 15 menit; login & gagal login dicatat `activity_logs`.
- Session 30 menit, cookie `http_only`, `same_site=lax`, `secure` otomatis di HTTPS, payload terenkripsi.
- User nonaktif ditolak `canAccessPanel` dan dikeluarkan saat sedang login.

### Otorisasi
- Semua aksi diperiksa policy di backend; aturan keras juga berlaku untuk super_admin (`EnforcedForSuperAdmin`): role super_admin terkunci, folder sistem Drive, isi chat personal, dokumen terkunci, reset 2FA diri sendiri.
- IDOR: semua unduhan berkas (proposal/LPJ, Drive, lampiran surat & chat, CV/foto oprec, backup) lewat controller berotorisasi → 404 bila tidak berhak (diuji dengan menebak ID).
- Jaring pengaman pengaju (`ApplicantIsolationTest`) memeriksa SEMUA route panel.

### Data sensitif
- Terenkripsi dengan `APP_KEY`: isi chat, rahasia & kode pemulihan 2FA, payload session. **APP_KEY wajib dibackup** (lihat `docs/DEPLOY.md`).
- Aspirasi anonim tanpa IP/identitas; data pribadi pendaftar oprec yang tidak diterima dihapus otomatis 6 bulan; lampiran chat 12 bulan.
- Nomor HP/email pendaftar hanya untuk pengelola oprec; Super Admin tidak bisa membaca chat personal (halaman, lampiran, maupun channel Pusher); Pusher tidak pernah menerima isi pesan.

### Header keamanan
`X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`, CSP dasar (sumber skrip/style/font/koneksi terbatas; `object-src 'none'`, `frame-ancestors 'self'`), HSTS opsional.

### Catatan yang diterima (bukan celah)
- CSP masih memuat `'unsafe-inline'` & `'unsafe-eval'` — dibutuhkan Filament/Livewire/Alpine. Risiko XSS ditekan dengan escaping Blade + sanitasi HTML.
- Paket Pusher Sandbox: kunci publik Pusher memang terlihat di browser (by design); otorisasi channel tetap di server.
