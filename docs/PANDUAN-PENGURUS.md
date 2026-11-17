# Panduan Pengurus — Dashboard BEM UBHINUS

Panduan singkat memakai dashboard pengurus. Alamat dashboard: **`https://<domain-bem>/panel-bem`**.
Akun dibuat oleh Ketua/Wakil (pengurus) atau Sekretaris (akun pengaju kegiatan); tidak ada pendaftaran mandiri.

---

## 1. Login pertama

1. Buka dashboard, masukkan **email** dan **password sementara** yang diberikan Ketua/Sekretaris.
2. Anda langsung diminta **mengganti password**. Syaratnya minimal 10 karakter, berisi huruf, angka, dan simbol (contoh pola: `Kopi#Pagi2026`). Jangan memakai tanggal lahir atau NIM.
3. **Khusus Ketua/Wakil, Sekretaris, dan Bendahara: wajib mengatur 2FA** (autentikasi dua langkah):
   1. Pasang aplikasi **Google Authenticator**, **Microsoft Authenticator**, atau **Authy** di HP.
   2. Di layar *Atur autentikasi dua langkah*, pindai kode QR dengan aplikasi tersebut.
   3. Masukkan 6 digit kode dari aplikasi.
   4. **Simpan 8 kode pemulihan** (foto/tulis, simpan di tempat aman). Kode ini dipakai bila HP hilang; tiap kode hanya bisa dipakai sekali.
4. Pengurus lain **boleh** mengaktifkan 2FA secara sukarela (sangat disarankan): menu foto profil → **Profil Saya** → *Autentikasi dua langkah*.

Setiap login berikutnya (bila 2FA aktif): masukkan password, lalu kode 6 digit dari aplikasi.

> Sesi otomatis berakhir setelah 30 menit tidak aktif. Salah password 5× akan mengunci login selama 15 menit.

---

## 2. Pasang dashboard sebagai aplikasi di HP

- **Android (Chrome):** buka dashboard → menu ⋮ → *Tambahkan ke layar utama* / *Instal aplikasi*.
- **iPhone/iPad (Safari, iOS 16.4+):** buka dashboard di **Safari** → tombol **Bagikan** → *Tambahkan ke Layar Utama* → *Tambah*. Setelah itu selalu buka dari ikon **BEM UBHINUS** di layar utama.

## 3. Aktifkan notifikasi

1. Menu foto profil → **Notifikasi** → **Aktifkan di perangkat ini** → *Izinkan*.
2. Tekan **Kirim notifikasi uji**. Bila muncul di HP, berarti berhasil.
3. Ulangi di setiap HP/laptop yang Anda pakai.
4. Di bagian *Jenis notifikasi push*, matikan jenis yang tidak Anda perlukan. Notifikasi di **lonceng** dashboard tetap selalu masuk.

Notifikasi tidak muncul? Cek izin notifikasi browser/HP, pastikan tidak mode hemat baterai ekstrem, dan khusus iPhone pastikan dibuka dari ikon Layar Utama.

---

## 4. Fitur untuk semua pengurus

| Menu | Kegunaan |
|---|---|
| **Dashboard** | Ringkasan & grafik sesuai jabatan (tugas, kas, pesan, dll.) |
| **Kotak Masuk** | Pengumuman Sekretaris & surat Humas untuk Anda; tandai *Sudah ditindaklanjuti* / balas |
| **Chat** | Chat personal, grup divisi otomatis, grup *Semua Pengurus*, grup buatan sendiri; kirim foto/berkas, balas, mute |
| **Kalender** | Agenda BEM, kampus, UKM, rapat; ⚠ menandai tanggal bentrok |
| **Tugas Saya** | Tugas konten dari Medpub: ubah status, unggah hasil |
| **Drive** | Folder yang dibagikan kepada Anda: unggah, unduh, dokumen bersama |
| **Kas Saya** (widget) | Status kas mingguan & iuran Anda |
| **Profil Saya** | Foto profil, nomor HP, ganti password, 2FA |

Isi chat personal **tidak bisa dibaca** siapa pun selain anggota percakapan, termasuk Ketua/Admin.

---

## 5. Panduan per jabatan

### Ketua & Wakil (Super Admin)
- **User**: buat akun pengurus (nama, email, divisi, jabatan, role), reset password, nonaktifkan (akun tidak pernah dihapus), **Reset 2FA** bila HP pengurus hilang.
- **Role & Permission**: atur hak akses; role Super Admin terkunci.
- **Pengaturan Website**, **Periode**, **Struktur Kepengurusan**, **Divisi**, **Log Aktivitas**.
- **Persetujuan konten** (cukup salah satu Ketua/Wakil) dan **keputusan akhir proposal/LPJ**.
- **Aspirasi tab Eskalasi**: teruskan ke Kajian.
- **Backup** (*Pengaturan → Backup*): pantau backup; **tiap tanggal 1** unduh backup terbaru dan simpan di tempat lain (ada pengingat otomatis).

### Sekretaris
- **Drive & Dokumen**: kelola semua folder, bagikan folder ke divisi/role/pengurus (lihat/unggah/kelola), buat dokumen dari template ber-kop, export PDF/DOCX. Folder **Surat** berisi arsip otomatis surat Humas.
- **Pengumuman**: kirim ke semua/role/divisi/pengurus/organisasi; lihat siapa sudah membaca.
- **Unduhan publik**, **Kalender** (hari besar, kampus, BEM, UKM, rapat).
- **Kegiatan**: verifikasi kelengkapan proposal/LPJ (tahap pertama).
- **Organisasi & Akun Pengaju**: buat akun pengaju kegiatan untuk UKM/HMJ/panitia.
- **Oprec**: buka gelombang, seleksi pendaftar, jadwal interview, umumkan hasil, jadikan akun pengurus.

### Bendahara
- **Kas Mingguan**: klik sel untuk mencatat/membatalkan pembayaran; baris merah = menunggak; export Excel/CSV; *Pengaturan kas* (tanggal mulai & nominal).
- **Iuran Tambahan**: buat iuran (PDH, tabungan, dll.), catat cicilan.
- **Kegiatan**: tinjau anggaran proposal & laporan keuangan LPJ (tahap kedua).
- Pengurus otomatis menerima notifikasi "terima kasih" setelah pembayaran dicatat dan pengingat tunggakan tiap Senin.

### Divisi Kajian
- **Koordinator**: kelola tabel pembahasan, isi *Link Docs*, terima aspirasi yang perlu dikaji, kirim hasil kajian; nilai pendaftar oprec divisi Kajian.
- **Anggota**: tambah pembahasan, edit milik sendiri.

### Divisi Media & Publikasi
- **Koordinator**: tugaskan konten di Kalender (penanggung jawab + deadline), review konten anggota, **publikasikan** ke website setelah disetujui Ketua/Wakil, salin caption & unduh gambar asli untuk medsos, tandai sudah diposting; nilai pendaftar oprec divisi Medpub.
- **Anggota**: buat draft konten (artikel/postingan), kerjakan tugas di *Tugas Saya*, revisi sesuai catatan.

### Divisi Humas
- **Koordinator**: kelola **Aspirasi** (tanggapi, selesaikan, naikkan ke Ketua bila perlu), kirim **Surat** (lampiran otomatis diarsipkan ke Drive), input agenda UKM; nilai pendaftar oprec divisi Humas.
- **Anggota**: bantu menanggapi aspirasi, buat draft surat & press release.

### Pembina
- **Hanya melihat**: dashboard, kalender, kas, proker, kegiatan, aspirasi, Drive, laporan. Tidak memakai chat dan tidak bisa mengubah data.

### Pengaju kegiatan (UKM/HMJ/Panitia — bukan pengurus BEM)
- **Kegiatan Saya**: buat kegiatan & unggah proposal (PDF ≤ 10 MB), pantau status & catatan revisi, unggah **LPJ** paling lambat **14 hari** setelah kegiatan selesai (pengingat H-3, hari H, lalu mingguan).
- **Chat**: personal dengan pengurus/pengaju lain dan grup organisasi otomatis.
- **Kotak Masuk** (pesan untuk organisasi Anda), **Kalender** publik, **Profil Saya**, **Notifikasi**.

### Form & Survei (Super Admin, Sekretaris, koordinator divisi)
- Menu *Komunikasi → Form & Survei*. Koordinator hanya melihat & mengelola form divisinya; Pembina hanya melihat.
- **Buat form** → tab *Pertanyaan*: ketik pertanyaan, pilih jenis (13 jenis, termasuk upload berkas), seret ⠿ untuk mengurutkan, tombol samping/bawah untuk menambah pertanyaan, judul, atau bagian. Semua perubahan tersimpan otomatis.
- **Kuis**: aktifkan di *Pengaturan*, lalu beri poin & tandai jawaban benar. Esai/upload dinilai manual di tab *Jawaban* (tombol *Lihat* → *Beri nilai*).
- **Pratinjau** untuk mencoba tanpa menyimpan jawaban; **Terbitkan** agar link aktif; bagikan lewat **Salin link** atau **Unduh QR (PNG)**; **Tutup form** bila selesai.
- Tab *Jawaban*: ringkasan, daftar responden, **Export Excel/CSV**. Jawaban yang sudah dikirim tidak bisa diubah peserta.
- Jawaban dihapus otomatis **12 bulan setelah form ditutup** (bisa diubah per form, termasuk *Simpan selamanya*). Form yang sudah berjawaban tidak bisa dihapus.

---

## 6. Pertanyaan umum

| Pertanyaan | Jawaban |
|---|---|
| Lupa password | Hubungi Ketua/Wakil untuk **Reset password**. Anda mendapat password sementara dan wajib menggantinya. |
| HP hilang / ganti HP (2FA) | Login memakai salah satu **kode pemulihan**, lalu atur ulang 2FA di Profil Saya. Tidak punya kode? Minta Ketua/Wakil **Reset 2FA**. |
| Notifikasi tidak muncul | Lihat bagian 3; pastikan perangkat ada di daftar *Perangkat terdaftar*. |
| Foto dari HP gagal diunggah | Foto otomatis diperkecil; bila tetap gagal, coba simpan ulang sebagai JPG. |
| Salah kirim pesan chat | Tekan **Hapus** pada pesan Anda; isi & lampirannya benar-benar terhapus. |
| Akun tidak bisa login | Mungkin dinonaktifkan atau terkunci 15 menit karena salah password 5×. Hubungi Ketua/Wakil. |
| Data pribadi | Nomor HP & email hanya terlihat oleh yang berwenang. Jangan membagikan isi dashboard ke luar BEM. |
