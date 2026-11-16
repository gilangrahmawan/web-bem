# AGENTS.md — Website BEM UBHINUS (file tunggal: persiapan + aturan + spesifikasi + fase)

> **Cara pakai singkat:** taruh file ini (nama harus persis `AGENTS.md`) di **folder utama proyek** yang dibuka di Antigravity. Jangan dikirim sebagai lampiran ZIP ke chat agent. Antigravity otomatis membaca `AGENTS.md` dari folder kerja. Setelah itu ketik di panel agent: **"Kerjakan Fase 0 sesuai AGENTS.md"**.

Daftar isi
- BAGIAN A — Persiapan (dibaca & dikerjakan oleh pemilik proyek / manusia)
- BAGIAN B — Aturan untuk agent
- BAGIAN C — Spesifikasi produk
- BAGIAN D — Hak akses (RBAC)
- BAGIAN E — Database
- BAGIAN F — Fase pengerjaan (Fase 0 – Fase 10)
- BAGIAN G — Checklist selesai tugas
- BAGIAN H — Pemecahan masalah

---

# BAGIAN A — PERSIAPAN (untuk pemilik proyek)

Agent **tidak bisa** membangun website kalau komputer belum punya PHP, Composer, MySQL, Node.js, dan Git — agent hanya menjalankan perintah di terminal komputer Anda. Ikuti langkah ini **sebelum** meminta agent bekerja.

## A1. Install software (Windows — disarankan pakai Laragon)

| No | Software | Fungsi | Catatan |
|---|---|---|---|
| 1 | **Laragon** (edisi Full) | Paket PHP + MySQL + Apache/Nginx | Unduh dari situs resmi Laragon. Pastikan PHP **8.2 atau lebih baru**. Kalau PHP bawaannya lebih lama, tambahkan versi PHP baru lewat menu Laragon → PHP → Version. |
| 2 | **Composer** | Pengelola package PHP | Biasanya sudah termasuk di Laragon Full. Kalau belum, install Composer for Windows dan arahkan ke `php.exe` milik Laragon. |
| 3 | **Node.js LTS** | Build Tailwind/Vite | Unduh versi **LTS** dari nodejs.org. |
| 4 | **Git** | Simpan versi kode | Git for Windows. Saat instalasi pilih opsi "Git from the command line and also from 3rd-party software". |
| 5 | **Antigravity** | IDE + agent | Sudah terpasang. |
| 6 | (opsional) **HeidiSQL** | Lihat isi database | Sudah termasuk di Laragon. |

Pengguna **macOS**: pakai **Laravel Herd** (PHP + Composer) + **DBngin** (MySQL) + Node.js LTS + Git.

## A2. Aktifkan ekstensi PHP yang dibutuhkan

Laravel + Filament membutuhkan ekstensi berikut aktif: `intl`, `zip`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `curl`, `bcmath`, `exif`.

Di Laragon: klik kanan ikon Laragon → **PHP** → **Extensions** → centang yang belum aktif (terutama **intl**, **zip**, **gd**) → Laragon akan restart.

## A3. Pastikan perintah bisa dipanggil dari terminal

1. Di Laragon klik **Start All** (Apache/Nginx & MySQL harus hijau).
2. Klik kanan Laragon → **Tools → Path → Add Laragon to Path** (supaya `php`, `composer`, `mysql` dikenali di terminal mana pun, termasuk terminal Antigravity).
3. **Tutup lalu buka ulang Antigravity** (supaya PATH baru terbaca).
4. Buka terminal di Antigravity (Terminal → New Terminal) dan jalankan satu per satu:

```
php -v
composer -V
node -v
npm -v
git --version
mysql --version
php -m
```

Hasil yang diharapkan:
- `php -v` → versi 8.2 atau lebih tinggi.
- `php -m` → daftar memuat `intl`, `zip`, `gd`, `pdo_mysql`, `mbstring`, `fileinfo`.
- Semua perintah lain menampilkan versi, **bukan** "is not recognized as an internal or external command".

Kalau ada yang gagal, lihat **BAGIAN H**.

## A4. Siapkan folder proyek & database

1. Buat folder kosong, contoh: `C:\laragon\www\bem-ubhinus` (atau di mana saja).
2. **Simpan file `AGENTS.md` ini di dalam folder tersebut** (bukan di dalam subfolder, bukan dalam ZIP).
3. Buat database kosong:
   - Laragon → **Database** (HeidiSQL) → koneksi default (user `root`, password kosong) → klik kanan → Create new → Database → nama: `bem_ubhinus`, collation `utf8mb4_unicode_ci`.
   - Atau lewat terminal: `mysql -u root -e "CREATE DATABASE bem_ubhinus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"`
4. Siapkan data akun Super Admin pertama (akan dimasukkan ke `.env` oleh agent): email Ketua dan password sementara yang kuat.

## A5. Atur Antigravity

1. **File → Open Folder** → pilih folder `bem-ubhinus`.
2. Di panel agent pilih model **Claude Opus 5.5**.
3. Pilih mode **Planning** (agent membuat rencana dulu, Anda setujui, baru dikerjakan).
4. Di pengaturan agent, izinkan agent **menjalankan perintah terminal** (Terminal execution / auto-run). Pilihan aman: "minta persetujuan" — Anda akan diminta menyetujui tiap perintah. Jangan memilih mode yang memblokir semua perintah, karena agent butuh menjalankan `composer`, `php artisan`, dan `npm`.
5. Pastikan agent boleh membaca/menulis file di folder proyek.
6. Cek bahwa `AGENTS.md` terbaca (menu Customizations / Rules biasanya menampilkannya). Kalau tidak tampil, tetap bisa: di awal chat tulis *"Baca file AGENTS.md di root proyek terlebih dahulu."*

## A6. Kalimat pertama untuk agent

Salin dan kirim:

```
Baca AGENTS.md di root proyek sampai selesai. Lalu kerjakan Fase 0 (cek lingkungan).
Laporkan hasilnya dalam tabel. Jangan lanjut ke Fase 1 sebelum saya setujui.
```

Setelah Fase 0 lulus:

```
Kerjakan Fase 1 sesuai AGENTS.md. Buat rencana dulu, tunggu persetujuan saya.
```

Lanjutkan fase demi fase. **Satu fase per sesi**, coba hasilnya sendiri sebelum lanjut.

## A7. Akun layanan yang disiapkan menjelang fasenya

| Kapan | Layanan | Yang dibutuhkan |
|---|---|---|
| Sebelum Fase 2 | Identitas BEM | Logo (PNG/SVG), kode warna utama & sekunder, nama kabinet, visi-misi, sambutan Ketua |
| Sebelum Fase 5 | Cloudflare Turnstile (gratis) | Site key & secret key (CAPTCHA form publik) |
| Sebelum Fase 7 | Data | Daftar organisasi (UKM, HMJ, panitia) & akun pengaju kegiatan pertama (dibuat Sekretaris) |
| Sebelum Fase 9 | Pusher Channels (paket Sandbox gratis) | app_id, key, secret, cluster (pilih `ap1` Singapura) |
| Sebelum Fase 10 | Hosting + domain | Shared hosting dengan PHP 8.2+, MySQL, SSH, cron; atau subdomain kampus |
| Sebelum Fase 10 | GitHub | Repository **private** untuk kode |

---

# BAGIAN B — ATURAN UNTUK AGENT

Kamu adalah agent pengembang website **BEM Universitas Bhinneka Nusantara (UBHINUS), Malang**. Pemilik proyek adalah Ketua Umum BEM (mahasiswa Informatika). **Komunikasi dengan pemilik dalam Bahasa Indonesia.**

## B1. Cara kerja

1. **Baca seluruh file ini** sebelum mengerjakan apa pun. BAGIAN C–E adalah sumber kebenaran.
2. **Kerjakan satu fase saja** sesuai permintaan pemilik. Jangan melompat ke fase lain.
3. **Rencana dulu**: tulis daftar file yang akan dibuat/diubah, migration, policy, test, dan perintah terminal yang akan dijalankan. Tunggu persetujuan pemilik.
4. Jika lingkungan belum siap (perintah tidak dikenal, ekstensi PHP kurang, MySQL mati), **berhenti** dan beri tahu pemilik langkah perbaikannya dengan merujuk BAGIAN A/H. Jangan mencoba menginstal software sistem sendiri.
5. Pola setiap fitur: **migration → model (relasi, casts, fillable) → enum → service → policy → resource Filament / controller + Blade → notifikasi → seeder/factory → test**.
6. Kalau spesifikasi ambigu, **tanya** pemilik — terutama untuk struktur database dan hak akses.
7. Jangan menghapus migration yang sudah dijalankan; buat migration baru untuk perubahan.
8. Jangan pernah menulis kredensial asli ke file yang di-commit. Pakai `.env` (di-ignore Git) dan perbarui `.env.example` tanpa nilai rahasia.
9. Akhiri setiap fase dengan **BAGIAN G (checklist selesai tugas)**.

## B2. Tech stack (wajib)

| Bagian | Pilihan |
|---|---|
| Backend | Laravel versi stabil terbaru, PHP 8.2+ |
| Database | MySQL / MariaDB, charset utf8mb4 |
| Halaman publik | Blade + Tailwind CSS (Vite) |
| Dashboard pengurus | **Filament** (panel id `admin`, path **`/panel-bem`**) |
| Role & permission | `spatie/laravel-permission` + `bezhansalleh/filament-shield` |
| Kalender | FullCalendar (publik & dashboard) |
| Editor dokumen | Rich editor (TipTap / RichEditor Filament); export PDF `barryvdh/laravel-dompdf`, DOCX `phpoffice/phpword` |
| Sanitasi HTML | `mews/purifier` |
| Gambar | `intervention/image` (kompres ke WebP) |
| Notifikasi | Database notification (lonceng Filament) + Web Push (`laravel-notification-channels/webpush`) + PWA |
| Chat real-time | Laravel Broadcasting + Pusher (fallback polling) |
| CAPTCHA | Cloudflare Turnstile |
| Backup | `spatie/laravel-backup` |
| API (nanti) | Laravel Sanctum, prefix `/api/v1` |
| Testing | Pest |

**Batasan hosting:** target shared hosting murah (PHP, MySQL, cron; tanpa proses background permanen). Queue driver `database`, diproses lewat scheduler. Jangan mewajibkan Redis, Supervisor, atau server Node. Semua akses berkas lewat `Storage::disk(...)` supaya bisa pindah ke Cloudflare R2 (driver S3) hanya dengan mengubah `.env`.

Ikuti dokumentasi resmi versi package yang benar-benar terpasang; jangan mengandalkan sintaks versi lama.

## B3. Konvensi kode

- Nama class, tabel, kolom, route name: **bahasa Inggris**. Teks yang tampil ke pengguna: **Bahasa Indonesia**.
- Route publik ramah Bahasa Indonesia: `/berita`, `/kalender`, `/unduhan`, `/aspirasi`, `/pengajuan`, `/oprec`.
- `APP_LOCALE=id`, timezone `Asia/Jakarta`, file bahasa validasi `lang/id`.
- Model: selalu `$fillable` (jangan `$guarded = []`), `casts()` untuk tanggal/boolean/JSON/enum, relasi dengan return type, `SoftDeletes` untuk data ber-trash.
- Status/kategori/tipe = **string backed enum** di `app/Enums` dengan method `label()` (Indonesia) dan `color()` (badge Filament).
- Logika bisnis lintas model di `app/Services` (bukan di controller/resource). Perpindahan status di dalam **DB transaction** + tabel log.
- Validasi: Form Request (publik) atau rules Filament.
- Hindari N+1 (`with()`), pakai pagination.
- Konfigurasi yang bisa berubah (nominal kas, nama kabinet, kontak, sambutan) di tabel `settings`, dikelola dari dashboard.
- Job terjadwal didaftarkan di `routes/console.php`.
- Format tanggal Indonesia ("15 November 2026"), mata uang "Rp5.000".
- Struktur folder:

```
app/Enums  app/Filament  app/Http/Controllers  app/Models
app/Notifications  app/Policies  app/Services
database/migrations  database/seeders  database/factories
resources/views/public   routes/web.php  routes/console.php  routes/channels.php
tests/Feature
```

## B4. Aturan keamanan (wajib, tanpa pengecualian)

**Autentikasi**
- Tidak ada halaman registrasi. Akun pengurus hanya dibuat Super Admin.
- Password minimal 10 karakter: huruf, angka, simbol.
- Kolom `must_change_password`: wajib ganti password saat login pertama / setelah reset.
- Rate limit login 5 gagal → kunci 15 menit. Session timeout 30 menit. Session lain diinvalidasi saat ganti password.
- User `is_active=false` tidak bisa login. User tidak pernah dihapus permanen.
- Panel di `/panel-bem`.
- 2FA (TOTP) wajib untuk super_admin, sekretaris, bendahara (Fase 10).

**Otorisasi**
- Setiap aksi dicek lewat **Policy** di backend (bukan hanya menyembunyikan tombol).
- Cek kepemilikan (cegah IDOR): koordinator hanya data divisinya, anggota hanya data miliknya.
- Role `super_admin` terkunci: tidak bisa dihapus/diedit; hanya Super Admin yang bisa memberikannya.

**Upload**
- Validasi `mimes` **dan** `mimetypes`. PDF/DOCX ≤ 10 MB, gambar ≤ 2 MB (lalu kompres WebP).
- Nama file disimpan sebagai UUID, nama asli di kolom `original_name`.
- Berkas privat (proposal, LPJ, drive, lampiran surat, CV, chat) di disk privat dan diunduh lewat controller berotorisasi. Hanya unduhan publik & gambar konten di disk `public`.

**Form publik** (aspirasi, oprec, cek status). Proposal/LPJ **tidak** lewat form publik — pengaju wajib login (keputusan Fase 7).
- Cloudflare Turnstile + rate limit (kirim 5/IP/jam, cek status 20/IP/jam).
- Kode tracking acak tak berurutan, contoh `ASP-8K2X-QM7D`, `REG-…` (tanpa karakter ambigu 0, O, 1, I).
- Aspirasi anonim tidak menyimpan IP/identitas.

**Celah web**
- Tidak ada raw SQL dengan input pengguna.
- Output Blade `{{ }}`; HTML dari editor disanitasi saat disimpan & ditampilkan.
- CSRF aktif. Security headers: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, CSP dasar.
- `APP_DEBUG=false` di produksi. `.env` tidak pernah di-commit.

**Data**
- `activity_logs` mencatat: login/gagal login, perubahan user & role, persetujuan konten, perubahan status aspirasi/pengajuan, hapus berkas, pencatatan kas.
- Nomor HP & email pengurus hanya terlihat oleh Super Admin dan role berwenang.
- **Isi chat personal tidak bisa dibaca Super Admin** (tidak ada halaman admin untuk itu).

## B5. Aturan tampilan

- Publik: mobile-first, cepat, gambar lazy-load + WebP, meta SEO & Open Graph per halaman. Warna `brand-primary`, `brand-secondary` sebagai variabel Tailwind (placeholder netral sampai pemilik memberi warna).
- Dashboard: navigasi dikelompokkan **BPH, Kajian, Medpub, Humas, Komunikasi, Pengaturan**. Menu tanpa izin tidak tampil.
- Status tampil sebagai badge berwarna. Aksi berbahaya pakai modal konfirmasi.
- Beranda dashboard per role: Tugas Saya, Kas Saya, Kotak Masuk belum dibaca, Konten menunggu persetujuan (Medpub/Super Admin), Aspirasi baru (Humas), Eskalasi (Super Admin).
- Setiap role punya widget grafik di beranda dashboard (lihat C1). Widget mengikuti hak akses, punya tampilan kosong yang rapi, dan memakai warna brand.

---

# BAGIAN C — SPESIFIKASI PRODUK

## C1. Ringkasan & keputusan final

Satu aplikasi Laravel berisi **website publik** (tanpa login) dan **dashboard pengurus** (login, menu sesuai role). Tahapan platform: Web → PWA → aplikasi HP/desktop (backend sama). Target ±40 pengurus.

Keputusan final:
- Divisi: **BPH** (Ketua, Wakil, Sekretaris, Bendahara), **Kajian**, **Medpub**, **Humas**. Tidak ada divisi Akademik.
- Super Admin dipegang **Ketua dan Wakil**.
- Persetujuan konten oleh Ketua/Wakil cukup **salah satu**.
- **Pembina** hanya melihat (read-only), tidak ikut chat.
- Notifikasi: **push notification (PWA) + lonceng dashboard**. Tidak memakai WhatsApp.
- **Chat personal privat** (Super Admin tidak bisa membaca).
- Agenda UKM **boleh bentrok**, tapi wajib peringatan + konfirmasi.
- **Drive internal mirip Google Drive** (rincian di Fase 8): sidebar "+ Baru", Drive Saya, Dibagikan kepada saya, Terbaru, Berbintang, Sampah, bar pemakaian penyimpanan; kartu dengan thumbnail; grid/daftar; menu titik tiga; drag & drop; bintang per user. Berkas Unduhan publik (Fase 2) tetap terpisah dari Drive.
- **Semua role punya dashboard dengan grafik** (Filament `ChartWidget` / `StatsOverviewWidget`). Mulai Fase 4, setiap fase wajib menambah widget grafik untuk modulnya. Data grafik mengikuti hak akses (anggota hanya datanya sendiri, koordinator hanya divisinya); tampilan kosong rapi bila belum ada data; warna mengikuti warna brand. Fase 10 memeriksa ulang setiap role punya minimal satu grafik.

- **Kegiatan sebagai pusat** (keputusan Fase 7): entitas utama adalah *Kegiatan*; proposal & LPJ adalah dua tahap di dalam satu kegiatan. Alur: buat kegiatan + ajukan proposal → review → disetujui → kegiatan berlangsung → unggah LPJ → review LPJ → selesai/arsip. Rantai review proposal & LPJ sama: **Sekretaris** (kelengkapan) → **Bendahara** (anggaran/laporan keuangan) → **Ketua/Wakil** (keputusan). Penyetuju ≠ pengaju; catatan wajib saat revisi/tolak.
- **Pengaju wajib login**: tidak ada form proposal/LPJ publik. Akun per orang dengan role **`pengaju_kegiatan`**, terhubung ke satu **organisasi** (UKM, HMJ, BEM, Panitia, Lainnya). Akun dibuat Super Admin atau Sekretaris (Sekretaris hanya boleh mengelola akun `pengaju_kegiatan`). Pengurus BEM boleh mengajukan kegiatan internal dengan akunnya sendiri (organisasi BEM).
- **Pengaju kegiatan bukan pengurus BEM**: tidak wajib kas, tidak masuk target pengumuman "semua pengurus"/per divisi, tidak masuk struktur, tidak masuk grup chat divisi BEM, tidak melihat kalender internal, aspirasi, kas, drive, konten, atau data internal lain. Yang bisa diakses: Kegiatan Saya, Profil Saya, Kalender publik, Kotak Masuk (hanya pesan yang ditujukan kepadanya), Chat (Fase 9).
- **Pengumuman/surat ke organisasi**: pengaju tidak pernah masuk target pengurus (semua pengurus, per role, per divisi, user tertentu). Ada jenis target terpisah **"Semua organisasi"** dan **"Organisasi tertentu"** (penerima = akun pengaju aktif di organisasi itu), hanya untuk Surat Humas (`letter.create`) dan Pengumuman Sekretaris (`announcement.create`).
- **Tenggat LPJ** = 14 hari setelah acara selesai (setting `lpj_deadline_days`). Pengingat H-3, hari tenggat, lalu mingguan selama terlambat; LPJ terlambat ditandai merah. **Belum ada sanksi** (mis. blokir proposal baru) — kode disiapkan agar mudah ditambahkan.

## C2. Halaman publik

| Halaman | Route | Isi |
|---|---|---|
| Beranda | `/` | Banner kegiatan, sambutan Ketua, 6 berita terbaru, agenda terdekat, tombol Kirim Aspirasi & Ajukan Proposal |
| Profil | `/profil` | Visi-misi, sejarah, logo & filosofi, nama kabinet |
| Struktur | `/struktur` | Bagan BPH & divisi dari periode aktif (foto, nama, jabatan, prodi) |
| Divisi | `/divisi/{slug}` | Deskripsi, proker, anggota |
| Berita | `/berita`, `/berita/{slug}` | Post `published`, kategori, pencarian, pagination |
| Kajian & pernyataan sikap | `/kajian` | Press release & kajian publik (artikel/PDF) |
| Kalender | `/kalender` | Kalender eksternal (hari besar, kampus, BEM, UKM), ikon ⚠ tanggal bentrok |
| Program kerja | `/proker` | Proker per divisi: Rencana/Berjalan/Selesai |
| Galeri | `/galeri`, `/galeri/{album}` | Foto per kegiatan |
| Unduhan | `/unduhan` | Template Proposal, Template LPJ, Form Peminjaman Barang Kampus, Form Peminjaman Barang BEM, SOP, Surat, Lainnya; filter & cari |
| Pengajuan | `/pengajuan` | Halaman informasi: alur pengajuan kegiatan, link template proposal/LPJ dari Pusat Unduhan, cara meminta akun pengaju (hubungi Sekretaris BEM). Pengajuan dilakukan setelah login. |
| Aspirasi | `/aspirasi`, `/aspirasi/status` | Form (boleh anonim) → kode tracking; cek status & tanggapan |
| Oprec | `/oprec`, `/oprec/hasil` | Form pendaftaran saat dibuka; pengumuman hasil |
| Kontak | `/kontak` | Alamat sekre, email, medsos, peta |

## C3. Modul dashboard per peran

**Super Admin (Ketua & Wakil)**
- Manajemen user: buat akun (nama, email, HP, divisi, jabatan, role, foto), reset password, nonaktifkan.
- Manajemen role & permission (Filament Shield): buat role baru, centang permission. `super_admin` terkunci.
- Periode kepengurusan, data struktur, pengaturan website (`settings`), log aktivitas.
- Persetujuan konten (salah satu Ketua/Wakil), keputusan akhir proposal/LPJ.
- Menerima eskalasi aspirasi dari Humas → meneruskan ke Kajian.
- Kalender lengkap (semua agenda).

**Sekretaris**
- **Drive internal**: folder bertingkat, upload multi-file drag & drop, preview PDF/gambar, rename/pindah/hapus, unduh folder ZIP, cari, trash 30 hari, indikator kuota.
- **Berbagi folder** ke role/divisi/user dengan akses `view` / `upload` / `manage` (diwariskan ke subfolder).
- **Editor dokumen**: buat dokumen di folder, auto-save, riwayat versi, kunci saat diedit (kedaluwarsa 15 menit), template (surat undangan, surat peminjaman, proposal, LPJ, notulen) dengan kop BEM, export PDF & DOCX.
- **Pengumuman**: judul, isi, lampiran; target semua / per role / per divisi / user tertentu; push + lonceng; daftar siapa sudah membaca.
- Kelola berkas unduhan publik; verifikasi kelengkapan proposal/LPJ; input kalender (hari besar, kampus, BEM, UKM, rapat).

**Bendahara**
- **Kas mingguan**: grid pengurus × minggu pada periode aktif; default **Rp5.000/minggu** (dari `settings`); klik sel = bayar/batal; total bayar, tunggakan, baris menunggak merah; filter divisi; export Excel/CSV.
- **Iuran tambahan**: nama (PDH, tabungan jalan-jalan, dll.), nominal, tipe sekali/bulanan, tenggat, peserta (semua/dipilih), pembayaran boleh dicicil, sisa tagihan.
- Notifikasi "terima kasih" otomatis setelah pembayaran dicatat; pengingat mingguan untuk yang menunggak; rekap bulanan ke Bendahara.
- Semua pengurus melihat kas miliknya; Super Admin & Pembina melihat semua.
- Review anggaran proposal / laporan keuangan LPJ.

**Kajian (Koordinator & Anggota)**
- **Tabel pembahasan**: judul, foto, link referensi (bisa banyak), link Docs, isi pembahasan (rich text), status Draft/Dibahas/Selesai.
- Anggota & koordinator bisa menambah; anggota hanya mengedit miliknya. **Link Docs hanya diisi Koordinator Kajian** (dan Super Admin).
- Menerima aspirasi dari Super Admin, mengisi hasil kajian, mengembalikan ke Humas; hasil kajian otomatis membuat/menautkan entri pembahasan.

**Media & Publikasi (Koordinator & Anggota)**
- **Penugasan konten** di kalender (kategori `content`): pilih penanggung jawab (bisa beberapa orang), deadline; pengingat push **H-2 dan H-1**; status Belum Dikerjakan → Dikerjakan → Diserahkan → Selesai; upload hasil.
- **Konten**: tipe artikel / postingan / press release; judul, isi, cover, **caption medsos terpisah**, lampiran.
- Alur: draft → review Koordinator Medpub → review Ketua/Wakil (salah satu) → publikasi ke website oleh Koordinator Medpub → posting medsos (tombol salin caption & unduh gambar, simpan link postingan, tandai sudah diposting). Revisi kembali ke pembuat dengan catatan.
- Akun medsos hanya dipegang Koordinator Medpub; semua konten wajib lewat website dulu.

**Humas (Koordinator & Anggota)**
- **Aspirasi**: baca, tanggapi, selesaikan; atau naikkan ke Super Admin bila perlu tindak lanjut; terima hasil kajian; tanggapi pengirim → Selesai.
- **Surat & masukan**: judul, isi, lampiran; diteruskan ke semua / role / divisi / user; status dibaca; penerima bisa membalas atau menandai "sudah ditindaklanjuti"; arsip otomatis ke folder Drive "Surat".
- Input agenda UKM (Koordinator); draft press release (masuk alur konten Medpub).

**Pembina**
- Read-only: dashboard ringkasan, kalender, kas, proker, kegiatan (proposal/LPJ), aspirasi, laporan.

**Pengaju kegiatan (`pengaju_kegiatan`, bukan pengurus BEM)**
- Kegiatan Saya (semua kegiatan organisasinya): buat kegiatan + ajukan proposal, unggah LPJ setelah proposal disetujui, lihat riwayat & catatan revisi, ajukan pindah penanggung jawab.
- Profil Saya, Kalender publik, Kotak Masuk (pesan untuknya saja), Chat (Fase 9). Tidak ada akses ke modul internal BEM.

## C4. Kalender (internal & eksternal)

Satu tabel `events`, dua tampilan.

| Kategori (`EventCategory`) | Internal | Eksternal (publik) | Yang boleh input |
|---|---|---|---|
| `holiday` hari besar | ✓ | ✓ | super_admin, sekretaris |
| `campus` UTS, UAS, dll. | ✓ | ✓ | super_admin, sekretaris |
| `bem` PKKMB, LKMM, bukber, dll. | ✓ | ✓ | super_admin, sekretaris |
| `ukm` kegiatan UKM | ✓ | ✓ | super_admin, sekretaris, koordinator_humas |
| `meeting` rapat | ✓ | ✗ | super_admin, sekretaris, koordinator (divisinya) |
| `content` konten/tugas Medpub | ✓ | ✗ | super_admin, koordinator_medpub |
| `internal` lainnya | ✓ | ✗ | super_admin, koordinator (divisinya) |

- `visibility` otomatis dari kategori.
- **Deteksi bentrok**: saat menyimpan agenda `ukm`/`bem`, cari agenda `ukm`/`bem` lain yang tanggalnya beririsan → modal peringatan berisi daftar agenda + tombol **Batal** / **Tetap simpan**. Kolom `has_conflict` ditandai ⚠ di kalender (diperbarui saat agenda diubah/dihapus).
- Super Admin melihat semua agenda. Filter kategori & divisi; warna per kategori/divisi; tampilan bulan, minggu, daftar.

## C5. Notifikasi

- Channel: `database` (lonceng) + `webpush`.
- Pemicu: tugas ditugaskan / H-2 / H-1; kas dibayar (terima kasih); tunggakan; pengumuman; surat; konten menunggu persetujuan / revisi / terbit; aspirasi baru / dieskalasi / diteruskan / hasil kajian; rapat besok; pesan chat; status kegiatan berubah (reviewer & penanggung jawab); pengingat tenggat LPJ (H-3, hari tenggat, mingguan saat terlambat); pindah penanggung jawab.
- PWA: `manifest.json`, service worker, tombol "Aktifkan notifikasi", `push_subscriptions` per perangkat, panduan iPhone (Add to Home Screen), preferensi notifikasi per user.

## C6. Chat

- `personal` (1:1), `division` (otomatis: Kajian, Medpub, Humas, BPH, Semua Pengurus — anggota disinkronkan saat user dibuat/pindah divisi/nonaktif), `custom` (dibuat pengurus, pembuat = admin grup).
- Teks, gambar, file; reply; tanda terbaca; jumlah belum dibaca; mute; cari; hapus pesan sendiri.
- Pusher private channel `conversation.{id}`; fallback polling 5 detik.
- Super Admin bisa membuat/membubarkan grup apa pun, **tidak bisa membaca chat personal**. Pembina tidak punya akses chat.
- **Pengaju kegiatan** (keputusan Fase 7, dikerjakan Fase 9): tetap bisa memakai chat; chat personal dengan semua akun terdaftar (pengurus BEM & pengaju lain); **tidak** masuk grup divisi BEM otomatis (Kajian, Medpub, Humas, BPH, Semua Pengurus); masuk **grup organisasi** otomatis (mis. "UKM Futsal") berisi semua akun aktif organisasi itu — anggota disinkronkan saat akun dibuat, dipindah organisasi, atau dinonaktifkan; boleh membuat grup sendiri dengan anggota akun aktif terdaftar saja (tanpa undangan luar). Pembina tetap tidak ikut chat.

## C7. Alur status

- **Konten** (`PostStatus`): `draft` → `pending_coordinator` → `pending_leader` → `approved` → `published`; dari pending bisa `revision` (kembali ke pembuat) atau `rejected`.
- **Aspirasi** (`AspirationStatus`): `new` → `in_review` → `resolved`, atau `in_review` → `escalated` (Super Admin) → `in_study` (Kajian) → `returned` (Humas) → `resolved`. Bisa `rejected` dengan alasan.
- **Kegiatan** (`ActivityStatus`): `draft` → `proposal_submitted` → `proposal_verified` (Sekretaris) → `proposal_budget_reviewed` (Bendahara) → `approved` (Ketua/Wakil; kegiatan masuk kalender publik) → `lpj_submitted` → `lpj_verified` → `lpj_budget_reviewed` → `completed`. Revisi di tiap tahap (`proposal_revision` / `lpj_revision`) → unggah ulang → mulai lagi dari Sekretaris. Proposal bisa `rejected`; kegiatan bisa `cancelled` (agenda kalender ikut dihapus). "Unggah LPJ" hanya untuk kegiatan `approved`.
- **Tugas konten** (`TaskStatus`): `todo` → `in_progress` → `submitted` → `done` (bisa kembali ke `in_progress`).
- **Pembahasan** (`DiscussionStatus`): `draft` → `discussed` → `done`.
- **Pendaftar oprec** (`ApplicantStatus`): `registered` → `document_passed` → `interview` → `accepted` / `rejected`.

---

# BAGIAN D — HAK AKSES (RBAC)

User → Role → Permission (`spatie/laravel-permission`). Permission didefinisikan di seeder; role & pemetaannya dikelola Super Admin lewat Filament Shield.

## D1. Role bawaan

| Slug | Nama tampilan | Divisi |
|---|---|---|
| `super_admin` | Super Admin (Ketua & Wakil) | BPH |
| `sekretaris` | Sekretaris | BPH |
| `bendahara` | Bendahara | BPH |
| `koordinator_kajian` | Koordinator Kajian | Kajian |
| `anggota_kajian` | Anggota Kajian | Kajian |
| `koordinator_medpub` | Koordinator Medpub | Medpub |
| `anggota_medpub` | Anggota Medpub | Medpub |
| `koordinator_humas` | Koordinator Humas | Humas |
| `anggota_humas` | Anggota Humas | Humas |
| `pembina` | Pembina | — |
| `pengaju_kegiatan` | Pengaju Kegiatan (bukan pengurus BEM; wajib terhubung ke organisasi) | — |

- `super_admin` melewati semua cek via `Gate::before` — **kecuali** membaca chat personal orang lain (selalu ditolak).
- `pembina` hanya permission `view`/`view_any`; tidak ada create/update/delete; tidak ada chat.

## D2. Permission kustom (format `modul.aksi`)

`user.manage`, `role.manage`, `setting.manage`, `event.create_ukm`, `event.create_public`, `event.create_meeting`, `event.assign_task`, `event.manage_all`, `kas.manage`, `kas.view_all`, `due.manage`, `discussion.create`, `discussion.set_docs_link`, `discussion.manage_all`, `post.create`, `post.create_press_release`, `post.approve_coordinator`, `post.approve_leader`, `post.publish`, `aspiration.handle`, `aspiration.escalate`, `aspiration.forward_to_kajian`, `aspiration.study`, `announcement.create`, `letter.create`, `folder.manage`, `download.manage`, `submission.verify`, `submission.review_budget`, `submission.decide`, `recruitment.manage`, `chat.use`, `report.view`.

Ditambahkan kemudian (lewat migration data yang hanya menambah): `aspiration.view` (Fase 5), `post.create_study` (Fase 6), `activity.submit` (ajukan kegiatan — pengurus BEM kecuali pembina, dan `pengaju_kegiatan`), `applicant.manage` (kelola akun pengaju & organisasi — super_admin, sekretaris), `recruitment.interview` (Fase 7B — koordinator Kajian/Medpub/Humas: melihat pendaftar yang memilih divisinya & memberi nilai interview). Review kegiatan memakai `submission.verify` / `submission.review_budget` / `submission.decide`.

Resource Filament memakai permission bawaan Shield (`view_any_x`, `create_x`, …) yang dipetakan sesuai matriks.

## D3. Matriks akses

K = kelola penuh · T = tambah/draft · S = setujui · L = lihat · L-own = hanya miliknya · — = tidak ada

| Modul | super_admin | sekretaris | bendahara | koor_kajian | angg_kajian | koor_medpub | angg_medpub | koor_humas | angg_humas | pembina |
|---|---|---|---|---|---|---|---|---|---|---|
| User & role | K | — | — | — | — | — | — | — | — | — |
| Drive & dokumen | K | K | L* | L* | L* | L* | L* | L* | L* | L |
| Pengumuman | K | K | L | L | L | L | L | L | L | L |
| Kas & iuran | K | L-own | K | L-own | L-own | L-own | L-own | L-own | L-own | L |
| Pembahasan Kajian | K | L | L | K | T | L | L | L | L | L |
| Konten website | S+K | L | L | L | L | S+K | T | T (press release) | T (press release) | L |
| Kalender internal | K | T | L | T (divisi) | L | T (divisi + tugas) | L | T (divisi + UKM) | L | L |
| Aspirasi | K | L | — | K (kajian) | L | — | — | K | T | L |
| Surat Humas | K | L | L | L | L | L | L | K | T | L |
| Kegiatan (proposal/LPJ) | S | K (verifikasi) | T (review anggaran) | T (internal) | T (internal) | T (internal) | T (internal) | T (internal) | T (internal) | L |
| Berkas unduhan | K | K | — | — | — | — | — | — | — | L |
| Oprec | K | K | — | L+nilai (divisi) | — | L+nilai (divisi) | — | L+nilai (divisi) | — | L |
| Chat | ya | ya | ya | ya | ya | ya | ya | ya | ya | — |

\* Hanya folder yang dibagikan (`folder_permissions`).

## D4. Aturan kepemilikan (wajib diuji)

- Koordinator hanya mengubah/menghapus `events` dengan `division_id` divisinya.
- Agenda kategori `ukm` hanya oleh `event.create_ukm` (default super_admin, sekretaris, koordinator_humas).
- Anggota Kajian hanya mengedit `discussions` miliknya. `docs_link` hanya dari `discussion.set_docs_link`; dari role lain field disabled **dan** diabaikan di backend.
- Anggota Medpub/Humas hanya mengedit `posts` miliknya saat `draft`/`revision`.
- Konten terbit hanya jika sudah disetujui koordinator_medpub **dan** salah satu super_admin; publikasi hanya `post.publish`.
- Kas: pengurus biasa hanya melihat baris kasnya sendiri.
- Chat: hanya anggota percakapan yang bisa membaca & subscribe channel-nya.

---

# BAGIAN E — DATABASE

Semua tabel: `id`, `timestamps`. (SD) = soft delete.

| Tabel | Kolom penting |
|---|---|
| `users` | name, email, phone, password, division_id, position, photo, is_active, must_change_password, two_factor_secret, last_login_at |
| `divisions` | name, slug, color, description |
| `periods` | name (nama kabinet), starts_on, ends_on, is_active |
| `settings` | key (unique), value (JSON) |
| `activity_logs` | user_id, action, subject_type, subject_id, properties (JSON), ip |
| tabel spatie | roles, permissions, model_has_roles, model_has_permissions, role_has_permissions |
| `events` | title, description, category, visibility, division_id, starts_at, ends_at, all_day, location, organizer, registration_url, has_conflict, created_by |
| `event_assignees` | event_id, user_id, status, result_path, submitted_at, reminded_h2_at, reminded_h1_at |
| `folders` (SD) | name, parent_id, is_system, created_by |
| `files` (SD) | folder_id, original_name, stored_name, disk, mime_type, size, uploaded_by |
| `folder_permissions` | folder_id, grantee_type (role/division/user), grantee_id, access (view/upload/manage) |
| `documents` (SD) | folder_id, title, content (longtext), locked_by, locked_at, created_by, updated_by |
| `document_versions` | document_id, content, saved_by |
| `document_templates` | name, content, created_by |
| `announcements` | type (announcement/letter), title, body, attachment_path, target_all, created_by |
| `announcement_targets` | announcement_id, target_type (role/division/user), target_id |
| `announcement_recipients` | announcement_id, user_id, read_at, followed_up_at, reply |
| `kas_weekly_payments` | period_id, user_id, week_number, week_start, amount, paid_at, recorded_by (unique: period_id+user_id+week_number) |
| `dues` | name, amount, type (once/monthly), due_date, applies_to_all, created_by |
| `due_members` | due_id, user_id |
| `due_payments` | due_id, user_id, amount, paid_at, note, recorded_by |
| `discussions` (SD) | title, photo, reference_links (JSON), docs_link, body, status, aspiration_id, created_by |
| `posts` (SD) | type, title, slug, body, cover, social_caption, status, author_id, event_assignee_id, published_by, published_at, social_posted_at, social_post_url |
| `post_approvals` | post_id, user_id, stage (coordinator/leader), decision, note |
| `aspirations` | tracking_code (unique), name, contact, is_anonymous, category, body, status, response, handled_by |
| `aspiration_logs` | aspiration_id, from_status, to_status, note, user_id |
| `organizations` | name, type (ukm/hmj/bem/panitia/other), is_active |
| `users` (tambahan Fase 7) | organization_id (wajib untuk `pengaju_kegiatan`) |
| `student_activities` | organization_id, name, pic_user_id, created_by, starts_on, ends_on, location, description, budget_estimate, proposal_path, proposal_name, budget_realization, lpj_path, lpj_name, status, review_round, event_id, proposal_approved_at, lpj_submitted_at, completed_at, kolom pengingat LPJ |
| `student_activity_logs` | student_activity_id, stage (proposal/lpj/pic/system), from_status, to_status, note, user_id |
| `activity_pic_transfers` | student_activity_id, from_user_id, to_user_id, requested_by, status (pending/approved/rejected), decided_by, decided_at, note |
| `downloads` | title, category, description, file_path, size, download_count, is_active |
| `galleries` / `gallery_photos` | album (title, slug, event_date) / foto (gallery_id, path, caption) |
| `programs` | division_id, period_id, title, description, status |
| `recruitments` | name, description, opens_at, closes_at, open_divisions (JSON), is_active, results_published_at, created_by |
| `recruitment_applicants` | recruitment_id, registration_code (unique), name, nim (unique per gelombang), prodi, batch_year, phone, email, first_choice_division_id, second_choice_division_id, motivation, experience, cv_path, cv_name, photo_path, status, interview_at, interview_location, accepted_division_id, user_id, consent_at, data_purged_at |
| `recruitment_interviews` | recruitment_applicant_id, interviewer_id, score (1–5), note (internal) — unik per pendaftar + pewawancara |
| `recruitment_applicant_logs` | recruitment_applicant_id, from_status, to_status, note, user_id |
| `notifications` | bawaan Laravel |
| `push_subscriptions` | bawaan package webpush |
| `conversations` | type, name, division_id, created_by |
| `conversation_members` | conversation_id, user_id, role (admin/member), last_read_at, muted |
| `messages` (SD) | conversation_id, user_id, body, attachment_path, reply_to_id |

Tambahkan index pada kolom yang sering difilter: `status`, `tracking_code`, `starts_at`, `division_id`, `category`.

---

# BAGIAN F — FASE PENGERJAAN

Pemilik memanggil dengan kalimat: **"Kerjakan Fase N sesuai AGENTS.md."** Setiap fase: rencana → persetujuan → kerjakan → BAGIAN G.

## Fase 0 — Cek lingkungan (tanpa menulis kode)

1. Jalankan: `php -v`, `php -m`, `composer -V`, `node -v`, `npm -v`, `git --version`, `mysql --version`.
2. Pastikan PHP ≥ 8.2 dan ekstensi `intl`, `zip`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `fileinfo`, `curl`, `bcmath` ada.
3. Cek koneksi MySQL: `mysql -u root -e "SHOW DATABASES;"` dan pastikan database `bem_ubhinus` ada (jika belum, tawarkan perintah pembuatannya ke pemilik).
4. Cek isi folder proyek: harus berisi `AGENTS.md` saja (atau proyek Laravel yang sudah ada).
5. Laporkan dalam tabel: item · hasil · status (OK/GAGAL) · cara memperbaiki (rujuk BAGIAN H).
6. **Jangan lanjut** sampai semua OK dan pemilik menyetujui.

## Fase 1 — Fondasi

1. Buat proyek Laravel di folder ini. Karena folder sudah berisi `AGENTS.md`, buat di folder sementara lalu pindahkan isinya ke root, **atau** gunakan `composer create-project laravel/laravel temp-app` lalu pindahkan semua file (termasuk tersembunyi) ke root dan hapus `temp-app`. Pastikan `AGENTS.md` tidak tertimpa.
2. `.env`: `APP_NAME="BEM UBHINUS"`, `APP_LOCALE=id`, `APP_FAKER_LOCALE=id_ID`, `DB_CONNECTION=mysql`, `DB_DATABASE=bem_ubhinus`, `DB_USERNAME=root`, `DB_PASSWORD=` (sesuai Laragon), `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD`, `QUEUE_CONNECTION=database`. Timezone `Asia/Jakarta`. Perbarui `.env.example`.
3. File bahasa validasi Indonesia (`lang/id`).
4. Install Filament (panel `admin`, path `/panel-bem`), `spatie/laravel-permission`, `bezhansalleh/filament-shield`, Pest.
5. Migration & model: `divisions`, `periods`, `settings`, `activity_logs`; kolom tambahan `users` (BAGIAN E).
6. Seeder: `DivisionSeeder` (BPH, Kajian, Media & Publikasi/slug `medpub`, Humas), `SettingSeeder` (`kas_weekly_amount=5000`, `cabinet_name`, kontak, visi-misi, sambutan), `RolePermissionSeeder` (10 role + permission BAGIAN D), `SuperAdminSeeder` (dari `.env`), `DemoSeeder` terpisah (1 akun uji per role, password sama untuk lokal, tidak untuk produksi).
7. Keamanan dasar: tanpa registrasi; `canAccessPanel` menolak user nonaktif; middleware wajib ganti password; rate limit login; session 30 menit; middleware security headers; `Gate::before` super_admin; log login/gagal login.
8. Resource Filament (super_admin): `UserResource` (tanpa hapus permanen; reset password; toggle aktif), Role dari Shield (`super_admin` terkunci), `DivisionResource`, `PeriodResource`, halaman Pengaturan, `ActivityLogResource` (read-only).
9. Grup navigasi BPH, Kajian, Medpub, Humas, Komunikasi, Pengaturan.
10. Test: hanya super_admin akses User/Role; pembina tak bisa mengubah apa pun; user nonaktif ditolak; user baru diarahkan ganti password; role super_admin tak bisa dihapus.
11. `git init`, `.gitignore` memuat `.env`, commit awal.

**Selesai bila:** Ketua login di `http://localhost:8000/panel-bem` (atau domain Laragon `http://bem-ubhinus.test/panel-bem`), bisa membuat user & role baru, semua test lulus.

## Fase 2 — Website publik

1. Layout publik: header + navigasi (Beranda, Profil, Struktur, Berita, Kajian, Kalender, Unduhan, Layanan ▾ [Aspirasi, Pengajuan, Oprec], Kontak), footer, menu mobile, Tailwind `brand-*`.
2. Migration & model: `posts` (lengkap), `downloads`, `galleries`, `gallery_photos`, `programs`.
3. Halaman BAGIAN C2 kecuali kalender, aspirasi, pengajuan, oprec (placeholder "segera hadir").
4. Resource: `DownloadResource` (super_admin, sekretaris), `GalleryResource` (super_admin, koordinator_medpub), `ProgramResource` (super_admin; koordinator divisinya), pengaturan profil BEM.
5. Unduhan publik lewat route yang menambah `download_count`; berkas nonaktif 404.
6. SEO: meta + Open Graph, `sitemap.xml`, `robots.txt`. Gambar dikompres WebP, lazy-load.
7. Test: halaman publik 200; post draft tidak tampil; unduhan nonaktif tidak bisa diakses.

## Fase 3 — Kalender

1. Enum `EventCategory` (label, color, isPublic), `TaskStatus`.
2. Migration & model `events`, `event_assignees`.
3. `EventConflictService` (deteksi & pembaruan `has_conflict`).
4. `EventPolicy` sesuai C4 & D4.
5. Dashboard: halaman kalender FullCalendar (filter, warna, klik tanggal untuk tambah; kategori sesuai izin); modal peringatan bentrok (Batal / Tetap simpan); agenda `content` dengan penanggung jawab & deadline; halaman **Tugas Saya** (ubah status, upload hasil).
6. Publik `/kalender` + endpoint JSON per rentang tanggal (hanya kategori publik); beranda menampilkan agenda terdekat.
7. Seeder hari besar tahun berjalan (data manual, tanpa API eksternal).
8. Command `tasks:remind` (harian 07:00 WIB) untuk H-2 & H-1, mencatat `reminded_*_at` agar tidak dobel. Channel `database` dulu.
9. Test: koordinator tak bisa edit agenda divisi lain; agenda internal tak muncul di endpoint publik; bentrok terdeteksi; pengingat tidak dobel.

## Fase 4 — Kas & iuran

1. Migration & model `kas_weekly_payments`, `dues`, `due_members`, `due_payments`.
2. `KasService`: daftar minggu periode aktif (Senin awal minggu), catat/batal bayar (nominal dari settings saat dicatat), hitung bayar & tunggakan, pembayaran iuran (cicil) & sisa; transaction + activity log.
3. Halaman **Kas Mingguan** (grid, klik sel, total, tunggakan merah, filter divisi, export), resource **Iuran Tambahan**, pengaturan nominal.
4. Widget **Kas Saya** untuk semua pengurus.
5. Notifikasi: `KasPaidNotification` ("Terima kasih, {nama}! Pembayaran kas minggu ke-{n} sebesar Rp5.000 sudah kami terima."), pengingat tunggakan (Senin 08:00 WIB), rekap bulanan ke Bendahara.
6. Widget grafik: Bendahara & Super Admin — kartu ringkasan (total kas terkumpul, total tunggakan, jumlah pengurus lunas), kas masuk per minggu (batang), lunas vs menunggak (donat), tunggakan per divisi (batang), progres tiap iuran tambahan. Pembina — grafik yang sama, read-only. Semua pengurus — "Kas Saya" (progres minggu dibayar & sisa tunggakan).
7. Test: tunggakan benar; anggota tak bisa melihat kas orang lain; pembayaran ganda minggu yang sama ditolak.

## Fase 5 — Humas, Kajian, Pengumuman & Surat

1. **Aspirasi**: migration/model + enum + `TrackingCode` helper; form publik (anonim opsional, Turnstile, rate limit) & cek status; `AspirationService` (review, respond, escalate, forwardToKajian, submitStudy, resolve, reject) dengan log; resource per peran (Inbox Humas, tab Eskalasi Super Admin, tab Perlu Dikaji Kajian); notifikasi tiap perpindahan.
2. **Pembahasan Kajian**: migration/model `discussions`; resource dengan repeater link referensi, rich editor tersanitasi; aturan `docs_link`; hasil kajian aspirasi membuat entri bertaut.
3. **Pengumuman & surat**: migration/model announcements + targets + recipients; `AnnouncementService::send()` (resolve target ke user aktif); resource Pengumuman (sekretaris) & Surat (Humas); halaman **Kotak Masuk** (otomatis dibaca, tombol sudah ditindaklanjuti + balasan); hook arsip surat ke Drive (TODO jelas sampai Fase 8).
4. Test: alur aspirasi lengkap; anggota Kajian tak bisa mengisi `docs_link` walau mengirim field; pengumuman hanya sampai target, tidak ke user nonaktif.

## Fase 6 — Konten Medpub

1. Lengkapi `posts`, tambah `post_approvals`; enum `PostType`, `PostStatus`.
2. `PostWorkflowService`: submit, approveByCoordinator, requestRevision, reject, approveByLeader (cukup satu super_admin), publish, markSocialPosted — semua tercatat + notifikasi.
3. Resource **Konten**: draft milik sendiri (Humas hanya press release), caption medsos terpisah, tab Draft Saya / Menunggu Saya / Disetujui / Terbit, riwayat persetujuan, tombol Salin caption, Unduh gambar, input link postingan.
4. Dari tugas konten berstatus `submitted`, koordinator bisa membuat draft konten bertaut.
5. Test: anggota tak bisa publish; tak bisa terbit tanpa dua persetujuan; pembuat tak bisa mengedit saat pending.

## Fase 7 — Kegiatan (Proposal/LPJ) & Oprec

Konsep sudah diputuskan (C1, C7): **kegiatan sebagai pusat**, pengaju wajib login. Boleh dipecah menjadi **7A** (kegiatan, proposal, LPJ, akun pengaju) dan **7B** (oprec).

1. **7A — Organisasi & akun pengaju**: tabel `organizations`, kolom `users.organization_id`; role `pengaju_kegiatan` + permission `activity.submit`, `applicant.manage` lewat migration data yang hanya menambah; resource Organisasi & Akun Pengaju (Super Admin & Sekretaris; Sekretaris hanya akun `pengaju_kegiatan`, diuji termasuk manipulasi request); akun baru wajib ganti password. Pengecualian pengaju dari modul internal (kas, pengumuman, struktur, kalender internal, aspirasi, drive, konten, dll.) diuji satu per satu. Akun demo `pengaju.futsal@demo.test` (organisasi contoh "UKM Futsal") hanya lokal.
1b. **7A — Kegiatan**: `student_activities` + log + pindah penanggung jawab; `ActivityWorkflowService` (submitProposal, verify, reviewBudget, decide, submitLpj, cancel, pindah penanggung jawab) dalam transaction + log + notifikasi; penyetuju ≠ pengaju; cek bentrok kalender saat proposal diajukan (peringatan, boleh lanjut); proposal disetujui → agenda kalender publik (ukm untuk organisasi luar, bem untuk BEM) tertaut, dihapus bila dibatalkan/ditolak; berkas PDF ≤10 MB di disk privat lewat controller berotorisasi; tenggat LPJ dari setting `lpj_deadline_days` (14) + command pengingat harian; titik kait sanksi LPJ terlambat (belum aktif). Halaman publik `/pengajuan` = informasi + template.
2. **7B — Oprec** (selesai): `recruitments`, `recruitment_applicants`, `recruitment_interviews`, `recruitment_applicant_logs`; satu gelombang aktif dalam satu waktu, divisi dibuka (Kajian/Medpub/Humas) diatur per gelombang; form publik aktif hanya saat dibuka (pilihan 1 & 2, CV PDF ≤2 MB, foto ≤2 MB → WebP privat, pernyataan persetujuan data, Turnstile, 5/IP/jam); NIM dinormalisasi & sekali per gelombang; kode `REG-…`; cek hasil dengan kode + NIM (20/IP/jam) menampilkan jadwal interview langsung, keputusan diterima/ditolak baru setelah "Umumkan hasil", nilai & catatan interview tidak pernah tampil; koordinator (`recruitment.interview`) hanya melihat & menilai pendaftar yang memilih divisinya; divisi diterima hanya pilihan 1/2; export Excel/CSV (nilai rata-rata tanpa catatan, aman formula injection); "Jadikan akun pengurus" membuat user nonaktif tanpa role di divisi diterima; `recruitment:purge-personal-data` harian menghapus CV, foto, WA, email pendaftar tidak diterima setelah `recruitment_retention_months` (6) bulan sejak gelombang ditutup.
3. Widget: BEM (Super Admin, Sekretaris, Bendahara, Pembina) — kegiatan per status, antrean review per tahap, LPJ terlambat, kegiatan per organisasi, rata-rata lama proses; Pengaju — kegiatan saya per status, tenggat LPJ terdekat; Oprec — pendaftar per divisi & per status.
4. Test alur kegiatan termasuk revisi di tiap tahap, LPJ hanya setelah proposal disetujui, pindah penanggung jawab, pengingat tidak dobel, integrasi kalender; form oprec tertutup di luar jadwal.

## Fase 8 — Drive & Dokumen

1. Migration/model `folders`, `files`, `folder_permissions`; `DriveService` (CRUD folder, cegah pindah ke turunan sendiri, upload, trash, restore, hapus permanen, kuota, ZIP streaming); policy dengan pewarisan izin.
2. Halaman kustom **Drive** bergaya Google Drive:
   - Sidebar: tombol **"+ Baru"** (folder baru, upload, dokumen baru), **Drive Saya**, **Dibagikan kepada saya**, **Terbaru**, **Berbintang**, **Sampah**, dan bar pemakaian penyimpanan.
   - Halaman utama: **"Folder yang disarankan"** dan **"File terbaru"** berupa kartu dengan pratinjau thumbnail (gambar & halaman pertama PDF) atau ikon jenis file.
   - Tombol tampilan grid/daftar, pencarian, breadcrumb, drag & drop upload multi-file, preview modal.
   - Menu titik tiga per item: buka, unduh, ganti nama, pindah, bagikan, beri bintang, hapus. **Bintang disimpan per user** (tabel tersendiri).
   - Tetap mengikuti aturan keamanan B4 (disk privat, unduh lewat controller berotorisasi, validasi mimes + mimetypes). Berkas Unduhan publik (Fase 2) tetap terpisah dari Drive.
   - Scheduler hapus permanen trash > 30 hari. Folder sistem "Surat" + sambungkan hook Fase 5.
3. **Dokumen**: `documents`, `document_versions`, `document_templates`; editor tersanitasi; auto-save ±10 detik (versi maks 1/menit/user); kunci dokumen 15 menit; riwayat versi & pulihkan; template kop BEM; export PDF & DOCX; tampil sebagai item di Drive.
4. Test: tak bisa unduh berkas tanpa izin walau menebak ID; folder tak bisa dipindah ke dirinya; dokumen terkunci tak bisa disimpan user lain.

## Fase 9 — PWA, Push, Chat

1. **PWA**: `manifest.json` (ikon, warna, `display: standalone`, `start_url: /panel-bem`), service worker (cache aset + `push` + `notificationclick`).
2. **Push**: package webpush, kunci VAPID di `.env`, tombol Aktifkan notifikasi, simpan subscription, tambahkan channel `webpush` ke semua notifikasi, halaman preferensi notifikasi, panduan iPhone.
3. **Chat**: migration/model conversations/members/messages; `ConversationSyncService` (grup divisi otomatis, tanpa pembina & tanpa `pengaju_kegiatan`; **grup organisasi** otomatis untuk pengaju — lihat C6); broadcasting Pusher + otorisasi `routes/channels.php`; fallback polling; halaman kustom **Chat** (Livewire) dengan fitur C6; rate limit 30 pesan/menit; push untuk pesan baru kecuali mute.
4. Test: non-anggota tak bisa baca/subscribe; Super Admin tak bisa membuka chat personal orang lain; user nonaktif keluar dari grup divisi.

## Fase 10 — Penyempurnaan & Deploy

1. 2FA TOTP wajib untuk super_admin, sekretaris, bendahara.
2. Backup (`spatie/laravel-backup`): database harian, berkas mingguan, ke disk terpisah (siap R2), notifikasi gagal ke super_admin.
3. Audit keamanan (laporkan dalam tabel temuan/file/perbaikan): policy & test di setiap resource; aturan upload; tak ada `{!! !!}` tanpa sanitasi; Turnstile & rate limit; security headers; cookie secure/httponly; `composer audit` & `npm audit`.
4. Performa: index, eager loading, `config:cache`/`route:cache`/`view:cache`.
4a. Cek ulang dashboard: setiap role (termasuk pembina) punya minimal satu widget grafik yang mengikuti hak aksesnya; tambahkan bila belum.
5. Tulis `docs/DEPLOY.md` (syarat hosting; clone; `composer install --no-dev --optimize-autoloader`; `npm run build` di lokal lalu upload `public/build` bila hosting tanpa Node; `.env` produksi; `key:generate`; `migrate --force`; seeder tanpa DemoSeeder; `storage:link`; document root ke `public/`; cron `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`; checklist `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, Cloudflare, kredensial Pusher/VAPID/Turnstile).
6. Tulis `docs/PANDUAN-PENGURUS.md` (login pertama, aktifkan notifikasi, panduan per role).

---

# BAGIAN G — CHECKLIST SELESAI TUGAS (setiap akhir fase)

1. `php artisan migrate:fresh --seed` bersih tanpa error (lokal).
2. `php artisan test` — semua lulus.
3. `./vendor/bin/pint` bila tersedia.
4. Cek keamanan perubahan: policy + test untuk role tak berhak; aturan upload; tidak ada rahasia di `git diff --cached`.
5. Commit berbahasa Indonesia, contoh `feat(kalender): deteksi bentrok agenda UKM`.
6. Laporan ke pemilik (Bahasa Indonesia):
   - Apa yang dibuat.
   - Cara mencoba: URL, akun uji per role (dari DemoSeeder).
   - Keputusan yang dibutuhkan / TODO tertunda.
   - Fase berikutnya.

---

# BAGIAN H — PEMECAHAN MASALAH (untuk pemilik & agent)

| Gejala | Penyebab umum | Solusi |
|---|---|---|
| Agent hanya membaca/menjelaskan ZIP, tidak membuat apa-apa | File diberikan sebagai lampiran chat, bukan berada di folder kerja | Ekstrak/simpan `AGENTS.md` langsung di folder proyek yang dibuka di Antigravity, lalu kirim kalimat di A6 |
| Agent tidak menjalankan perintah apa pun | Izin menjalankan terminal dimatikan | Aktifkan izin eksekusi terminal di pengaturan agent (minimal "minta persetujuan") |
| `'php' is not recognized…` | PHP belum masuk PATH | Laragon → Tools → Path → Add Laragon to Path, lalu restart Antigravity |
| `'composer' is not recognized…` | Composer belum terpasang/di PATH | Install Composer for Windows (pilih `php.exe` Laragon) lalu restart Antigravity |
| `npm`/`node` tidak dikenal | Node.js belum terpasang | Install Node.js LTS, restart Antigravity |
| `ext-intl` / `ext-zip` / `ext-gd` missing saat `composer require` | Ekstensi PHP belum aktif | Laragon → PHP → Extensions → aktifkan, lalu restart |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL belum jalan | Laragon → Start All; cek `DB_HOST=127.0.0.1`, `DB_PORT=3306` |
| `SQLSTATE[HY000] [1049] Unknown database` | Database belum dibuat | Buat `bem_ubhinus` (A4) |
| `Access denied for user 'root'` | Password MySQL berbeda | Sesuaikan `DB_PASSWORD` di `.env` (Laragon default kosong) |
| `composer create-project` gagal karena folder tidak kosong | Folder sudah berisi `AGENTS.md` | Buat di folder sementara lalu pindahkan isinya (Fase 1 langkah 1) |
| Halaman tanpa CSS / error Vite manifest | Aset belum dibuild | Jalankan `npm install` lalu `npm run dev` (lokal) atau `npm run build` |
| `Port 8000 is already in use` | Port dipakai aplikasi lain | `php artisan serve --port=8080`, atau pakai domain Laragon `http://bem-ubhinus.test` |
| Login panel ditolak terus | User nonaktif / belum punya role / password salah | Jalankan ulang `SuperAdminSeeder`, cek `is_active` dan role di database |
| `Memory exhausted` saat composer | Batas memori PHP kecil | `php -d memory_limit=-1 C:\path\composer.phar …` atau naikkan `memory_limit` di php.ini |
| Proses composer sangat lama / timeout | Koneksi internet lambat | Ulangi; gunakan jaringan stabil; `composer config --global process-timeout 2000` |
| Antigravity tidak menampilkan AGENTS.md di Rules | Nama/lokasi file salah | Nama harus persis `AGENTS.md` (huruf besar), di root folder yang dibuka; atau minta agent membacanya secara eksplisit |
