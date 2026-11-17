# Spesifikasi Fase 11 — Mesin Form, Acara & Pendaftaran, Ujian

Status: **spesifikasi saja, dikerjakan setelah website online.**
Pemilik: Ketua Umum BEM UBHINUS. Berlaku semua aturan di AGENTS.md (keamanan B4, hak akses, grafik dashboard, jaring pengaman pengaju, BAGIAN G), dengan amandemen yang dicatat di bagian 0b.

Urutan pengerjaan: **11A → 11C → 11B**, masing-masing dengan rencana dan persetujuan pemilik.

---

## 0. Keputusan final

1. Satu **mesin form** dipakai bersama oleh: pendaftaran acara, exit ticket harian, ujian awal oprec, pre-test/post-test acara. (Survei umum BEM boleh menyusul.)
2. Acara selalu terhubung ke **Kegiatan** yang proposalnya sudah disetujui.
3. **Panitia disimpan di level Kegiatan** (`student_activity_committee_members`), diinput penanggung jawab (PJ) saat mengajukan proposal. Acara memakai panitia kegiatannya; tidak ada tabel panitia terpisah per acara. Semua anggota panitia **wajib akun terdaftar**: akun pengaju dari organisasi yang sama, atau pengurus BEM untuk kegiatan BEM. **Kegiatan lama** (sebelum Fase 11): PJ wajib mengisi panitia sebelum bisa membuat acara.
4. **Akun anggota panitia**: PJ mengajukan akun anggota panitia (nama, email) → Sekretaris **menyetujui satu klik** → akun `pengaju_kegiatan` dibuat di organisasi yang sama (wajib ganti password saat login pertama) → **kredensial tampil sekali** ke PJ. Pengurus BEM tidak perlu diajukan (sudah punya akun).
5. **Uang tiket tidak masuk kas BEM.** Keuangan acara dicatat terpisah dan dilaporkan setelah acara selesai.
6. **Kehadiran dihitung dari pengisian exit ticket** per hari (bukan dari check-in). Check-in QR di pintu masuk bersifat opsional.
7. Exit ticket dibuka lewat **QR dinamis** yang ditampilkan panitia di ruangan dan berganti berkala, untuk mencegah titip absen.
8. **Sertifikat PDF otomatis** (dompdf, tanpa biaya) untuk peserta yang memenuhi minimal kehadiran (dan pembayaran terverifikasi untuk acara berbayar).
9. **Email peserta dikumpulkan**; pengiriman email otomatis belum dibuat (bisa ditambah nanti dengan SMTP).
10. Ujian online adalah **penyaring awal**, bukan penentu tunggal; penilaian utama oprec tetap interview.

## 0b. Amandemen terhadap AGENTS.md (keputusan pemilik)

| # | Aturan AGENTS.md | Amandemen Fase 11 |
|---|---|---|
| 1 | C1 — pengaju hanya: Kegiatan Saya, Profil, Kalender publik, Kotak Masuk, Chat | Pengaju mendapat **satu menu tambahan "Acara Saya"**, hanya berisi acara dari kegiatan di mana ia tercatat sebagai panitia (scope query **dan** policy). Menu/modul lain tetap terlarang. Jaring pengaman diperbarui: pengaju **bukan panitia** tetap 403/404 ke semua halaman acara. |
| 2 | B3 — nama tabel bahasa Inggris, jamak | Semua tabel acara memakai awalan **`activity_event_*`** (jamak), agar tidak bentrok dengan `events` (kalender) dan `programs` (program kerja). |
| 3 | B4 — upload | **Gambar (termasuk bukti transfer) ≤2 MB → WebP di disk privat**; PDF/dokumen ≤10 MB; **poster** mengikuti pengecualian cover Medpub (asli ≤10 MB di disk privat + versi WebP publik). Berlaku juga untuk pertanyaan upload di mesin form. |
| 4 | B4 — form publik: Turnstile + 5 kirim/IP/jam | Form acara: batas **per email / kode tiket** + batas **per IP yang longgar** (angka diusulkan di rencana 11B, karena peserta satu kampus berbagi IP). Pendaftaran tetap memakai **Turnstile**. **Exit ticket tanpa Turnstile** (dilindungi token QR dinamis + satu kali per hari per tiket). |
| 5 | B4 — security headers (`Permissions-Policy: camera=()`) | **`camera=(self)` hanya untuk halaman check-in**; semua halaman lain tetap `camera=()`. |
| 6 | D3 — Super Admin "K" | Data peserta acara: Super Admin **hanya lihat** (`EnforcedForSuperAdmin`), sama seperti Pembina. |
| 7 | Retensi oprec (Fase 7B) | Ujian oprec: **nilai total** disimpan bersama rekap oprec; **isi jawaban** dihapus mengikuti retensi 6 bulan. |

---

## 11A — Mesin Form

### A1. Data

| Tabel | Kolom penting |
|---|---|
| `forms` | owner_type, owner_id (polimorfik: acara, hari acara, gelombang oprec, …), division_id (nullable, keputusan A6 #1), public_id (acak), purpose (registration / exit_ticket / exam / pretest / posttest / survey), title, description, is_quiz, settings (JSON: acak soal, acak pilihan, batas waktu menit, nilai lulus, tampilkan nilai: never/after_submit/after_grading, satu kali isi, butuh login/tidak), opens_at, closes_at, status (draft/published/closed), created_by |
| `form_sections` | form_id, title, description, position |
| `form_questions` | form_id, section_id, type, label, help_text, is_required, position, options (JSON), validation (JSON), points (untuk kuis), correct_answer (JSON, tersimpan aman, tidak pernah dikirim ke browser peserta) |
| `form_responses` | form_id, respondent_type, respondent_id (peserta acara / pendaftar oprec / user), resume_token (hash), respondent_email, tab_switches, started_at, submitted_at, expires_at (ujian berbatas waktu), score, max_score, graded_at, graded_by, question_order (JSON, urutan acak per peserta) — IP tidak disimpan |
| `form_answers` | response_id, question_id, value (JSON), files (JSON: path, nama asli, MIME, ukuran — disk privat, maks. 3), points_awarded, grader_note (internal) |

### A2. Jenis pertanyaan

Jawaban singkat · Paragraf · Pilihan ganda (radio) · Kotak centang · Dropdown · Angka · Email · Nomor HP · Tanggal · Waktu · Skala linear (1–5 / 1–10, label ujung bisa diisi) · Upload file (jenis diatur; **gambar ≤2 MB → WebP, PDF/dokumen ≤10 MB**, disk privat — amandemen #3) · Teks/judul pemisah (bukan pertanyaan).

Pengaturan per pertanyaan: wajib/tidak, teks bantuan, validasi (panjang min/maks, rentang angka, regex sederhana untuk NIM), opsi "Lainnya" (isian bebas) untuk pilihan ganda/kotak centang, poin & kunci jawaban (mode kuis).

### A3. Mode kuis

- Penilaian otomatis: pilihan ganda (benar/salah), kotak centang (benar jika semua tepat; opsi nilai parsial), dropdown, jawaban singkat dengan daftar jawaban benar (abaikan huruf besar/kecil & spasi).
- Paragraf/esai/upload: penilaian manual oleh penilai yang berhak, dengan catatan internal.
- Acak urutan soal dan urutan pilihan **per peserta** (urutan disimpan di response supaya konsisten saat dimuat ulang).
- Batas waktu dihitung di server: `expires_at = started_at + durasi`. Jawaban tersimpan otomatis berkala; lewat batas waktu, response dianggap terkirim dengan jawaban yang sudah tersimpan. Jawaban yang dikirim setelah `expires_at` ditolak.
- Kunci jawaban tidak pernah dikirim ke browser peserta (diuji).

### A4. Frontend — Pembuat Form (panel, halaman Filament kustom + Livewire/Alpine)

Tata letak mirip Google Form, satu kolom di tengah (maks ±760 px), warna brand:

- **Header form**: kartu dengan garis atas tebal `#232361`, judul besar (bisa diedit langsung), deskripsi.
- **Tab di atas**: `Pertanyaan` · `Jawaban (n)` · `Pengaturan`.
- **Kartu pertanyaan**:
  - Label pertanyaan (input), pemilih jenis (dropdown dengan ikon), area isian sesuai jenis (daftar opsi yang bisa ditambah/dihapus/diurutkan, pengaturan skala, jenis file).
  - Kartu yang sedang dipilih diberi garis kiri kuning `#FFDB1A`.
  - Footer kartu: ikon gandakan, hapus, saklar **Wajib**, menu ⋮ (teks bantuan, validasi, acak pilihan, opsi "Lainnya").
  - Mode kuis: tombol **Kunci jawaban** → pilih jawaban benar + isi poin.
- **Toolbar melayang di kanan** (desktop) / **bar bawah** (HP): tambah pertanyaan, tambah teks/judul, tambah bagian.
- Urutkan pertanyaan dan bagian dengan **drag & drop** (pegangan ⋮⋮ di atas kartu).
- Simpan otomatis (indikator "Tersimpan" / "Menyimpan…"), tombol **Pratinjau** (membuka tampilan peserta) dan **Terbitkan**.
- Form yang sudah punya jawaban: perubahan yang merusak data (hapus pertanyaan, ubah jenis) memunculkan peringatan.
- **Tab Jawaban**: ringkasan per pertanyaan (grafik donat untuk pilihan, batang untuk skala, daftar untuk teks), tabel per responden, tombol **Export Excel** (aman formula injection), filter. Untuk kuis: distribusi nilai, rata-rata, soal yang paling banyak salah, antrean penilaian esai.
- **Tab Pengaturan**: jadwal buka/tutup, satu kali isi, mode kuis (durasi, nilai lulus, acak, kapan nilai terlihat), pesan konfirmasi setelah kirim.
- Responsif: di HP kartu memenuhi lebar, toolbar menjadi bar bawah.

### A5. Frontend — Tampilan isi form (publik/peserta)

- Kartu header (garis atas brand, logo kecil BEM/organisasi, judul, deskripsi, keterangan "* wajib").
- Satu kartu per pertanyaan, label jelas, tanda `*` merah untuk wajib, pesan galat di bawah kartu berbahasa Indonesia.
- Form panjang: dibagi per **bagian** dengan tombol `Kembali` / `Berikutnya` dan bar progres.
- Upload file: area seret & lepas, nama file + ukuran, tombol hapus; gambar diperkecil di browser sebelum dikirim (seperti foto oprec).
- Turnstile sebelum tombol **Kirim** untuk form publik — **kecuali exit ticket** (amandemen #4).
- Setelah kirim: halaman konfirmasi dengan ikon centang, pesan, dan tindakan berikutnya (mis. e-tiket / link ujian).
- Mobile-first, mudah dipakai satu tangan, kontras ≥ 4,5:1, ukuran sentuh ≥ 44 px, fokus keyboard terlihat.

### A6. Keputusan pemilik saat implementasi 11A (selesai)

| # | Topik | Keputusan |
|---|---|---|
| 1 | Hak kelola (`form.manage`) | Super Admin, Sekretaris, dan koordinator divisi. Kolom `forms.division_id` (nullable = BEM umum). Koordinator hanya melihat/mengelola form divisinya (form baru otomatis milik divisinya); Super Admin & Sekretaris melihat semua; Pembina hanya lihat (tanpa ubah, export, atau menilai); anggota divisi, Bendahara, dan pengaju tidak punya akses. Form berjawaban tidak bisa dihapus, juga oleh Super Admin. |
| 2 | URL | `/form/{public_id}` acak 10 karakter (tanpa 0/O/1/I), tanpa daftar form publik, halaman `noindex`. Pembuat form punya tombol **Salin link** dan **Unduh QR (PNG)**. |
| 3 | Satu kali isi tanpa login | Cookie per form + email unik bila form punya pertanyaan email wajib. |
| 4 | Wajib login | Hanya pengurus BEM aktif (bukan pengaju, bukan Pembina). Tamu diarahkan ke halaman masuk; satu kali isi dicek per akun; tanpa Turnstile. |
| 5 | Retensi | Per form, bawaan **12 bulan setelah ditutup** (tanggal tutup manual, atau `closes_at` bila lewat), opsi **simpan selamanya**. Dijalankan `forms:purge-responses` harian 02:45 WIB lewat scheduler yang ada; draf jawaban yang tidak pernah dikirim dibuang setelah 30 hari. |
| 6 | Nilai parsial kotak centang | (benar dipilih − salah dipilih) ÷ jumlah jawaban benar × poin, minimal 0. |
| 7 | Upload | Maks. **3 berkas** per pertanyaan; jenis gambar, PDF, Word, Excel, PowerPoint (pembuat bisa mempersempit). **ZIP tidak diizinkan.** Gambar ≤2 MB → WebP privat (diperkecil dulu di browser), dokumen ≤10 MB disimpan asli di disk privat; validasi ekstensi + MIME hasil deteksi isi. Unduh lewat controller berotorisasi (404 bila tidak berhak). |
| 8 | Setelah kirim | Jawaban **terkunci** (tidak bisa diubah). |
| 9 | Pratinjau | Memakai komponen yang sama dengan halaman publik, memvalidasi semua jawaban, tetapi **tidak pernah membuat `form_responses`** atau menyimpan berkas. |
| 10 | CSP | Halaman `/form` berjalan di bawah CSP global yang sudah ada (Livewire/Alpine, Turnstile dimuat eksplisit dari `challenges.cloudflare.com`) — **tidak ada perubahan CSP**; test memastikan header CSP & Permissions-Policy halaman lain sama dengan sebelumnya. |
| 11 | Rate limit | Kirim 5/IP/jam (form publik), simpan otomatis 120/IP/menit; halaman form 120 permintaan/IP/menit. |

Catatan implementasi: pola validasi teks dipilih dari daftar aman (NIM, hanya angka, hanya huruf), bukan regex bebas. Perpindahan tab peserta kuis dicatat (`tab_switches`) sebagai informasi bagi penilai, bukan diskualifikasi otomatis. Ringkasan jawaban di panel memakai grafik batang horizontal per opsi/skala dan daftar jawaban terbaru untuk teks.

---

## 11B — Acara & Pendaftaran

### B1. Data

| Tabel | Kolom penting |
|---|---|
| `student_activity_committee_members` | student_activity_id, user_id, role (ketua panitia / anggota), added_by — **panitia di level Kegiatan**, dipakai semua acara kegiatan itu |
| `committee_account_requests` | student_activity_id, requested_by (PJ), name, email, status (pending/approved/rejected), decided_by, decided_at, user_id (akun yang dibuat), reason |
| `activity_events` | student_activity_id, slug, title, description, poster (WebP publik) + poster_original_path (privat, ≤10 MB), location, is_paid, price, payment_info (teks), payment_qris_image, payment_deadline_hours, quota, registration_opens_at, registration_closes_at, min_attendance_percent, status (draft/published/ongoing/finished/cancelled), certificate_template (gambar latar + posisi field JSON) |
| `activity_event_days` | activity_event_id, day_number, date, starts_at, ends_at, exit_ticket_open (bool), exit_ticket_opened_at |
| `activity_event_participants` | activity_event_id, ticket_code (unik, mis. TKT-XXXX-XXXX, tanpa karakter ambigu), qr_token (acak panjang, tidak bisa ditebak), name, email, phone, institution, status (registered / awaiting_payment / payment_review / active / rejected / expired / cancelled), consent_at, data_purged_at |
| `activity_event_payments` | activity_event_participant_id, amount, proof_path (WebP privat, ≤2 MB), submitted_at, verified_by, verified_at, decision, reason |
| `activity_event_checkins` | activity_event_participant_id, activity_event_day_id, checked_in_by, checked_in_at (opsional, untuk pintu masuk) |
| `activity_event_attendances` | activity_event_participant_id, activity_event_day_id, form_response_id (exit ticket), attended_at |
| `activity_event_certificates` | activity_event_participant_id, number (unik, mis. BEM/2027/WS-001/0001), verification_code (acak), issued_at, pdf_path |
| `activity_event_finances` | activity_event_id, type (income/expense), category, amount, description, activity_event_payment_id (untuk tiket), proof_path (opsional, privat), recorded_by, recorded_at — **terpisah dari kas BEM** |

### B2. Alur

0. **Panitia kegiatan**: PJ mengisi panitia saat mengajukan proposal (kegiatan lama: wajib diisi sebelum membuat acara). Anggota yang belum punya akun diajukan lewat **"Ajukan akun panitia"** → Sekretaris setujui satu klik → akun pengaju dibuat (organisasi yang sama, wajib ganti password) → kredensial tampil **sekali** ke PJ.
1. Kegiatan disetujui → PJ/panitia membuat **Acara**: data acara, jadwal hari, kuota, berbayar/gratis, form pendaftaran (dari mesin form, ada pertanyaan bawaan: nama, email *wajib*, HP, asal instansi/NIM), form exit ticket per hari (bisa disalin ke semua hari), pre/post-test opsional, template sertifikat.
2. Acara diterbitkan → halaman publik `/acara/{slug}` aktif.
3. Peserta mendaftar → **gratis**: status `active`, e-tiket terbit. **Berbayar**: status `awaiting_payment`, peserta mengunggah bukti (gambar ≤2 MB → WebP privat; batas waktu jam diatur), status `payment_review` → panitia **Verifikasi** (`active`, pemasukan tercatat otomatis di keuangan acara) atau **Tolak** (alasan wajib, tampil ke peserta). Tidak mengunggah sampai batas waktu → `expired`, kuota dilepas.
4. Hari acara: (opsional) panitia scan QR e-tiket di pintu masuk.
5. Akhir sesi tiap hari: panitia membuka **Mode Exit Ticket** → layar menampilkan **QR dinamis** (token berganti tiap 60 detik, berlaku 90 detik) → peserta memindai dengan HP → memasukkan kode tiket (atau otomatis kalau membuka dari halaman e-tiket di HP yang sama) → mengisi evaluasi hari itu → **tercatat hadir hari itu**. Satu kali per hari per peserta. Tanpa Turnstile.
6. Acara selesai → sistem menghitung kehadiran (`hari hadir / total hari ≥ min_attendance_percent`, dibulatkan ke atas dalam jumlah hari) → sertifikat terbit untuk yang memenuhi syarat → **Laporan Acara** final.

### B3. Hak akses

- Panitia acara (= anggota `student_activity_committee_members` kegiatan itu): kelola acara, form, verifikasi pembayaran, check-in, buka/tutup exit ticket, sertifikat, keuangan acara, export.
- Ketua panitia / PJ: menambah/menghapus anggota panitia (hanya akun terdaftar dari organisasi yang sama, atau pengurus BEM untuk kegiatan BEM) dan mengajukan akun anggota panitia.
- Sekretaris (dan Super Admin): menyetujui/menolak pengajuan akun panitia (`applicant.manage`).
- Super Admin & Pembina: lihat semua acara dan laporannya; data peserta **hanya lihat** (`EnforcedForSuperAdmin`, amandemen #6).
- Pengurus BEM lain dan pengaju dari organisasi lain / bukan panitia: tidak bisa melihat data peserta.
- Bukti pembayaran, data peserta, dan file jawaban: disk privat, controller berotorisasi, 404 untuk yang tidak berhak.
- **Amandemen C1**: pengaju mendapat menu **"Acara Saya"** (hanya acara dari kegiatan di mana ia panitia). Jaring pengaman: pengaju bukan panitia tetap 403/404 ke semua halaman acara; menu lain tetap terlarang.

### B4. Keuangan acara (terpisah dari kas BEM)

- Pemasukan tiket tercatat otomatis saat pembayaran diverifikasi (dibatalkan otomatis jika verifikasi dibatalkan).
- Panitia boleh menambah pemasukan lain (sponsor) dan pengeluaran dengan bukti (opsional; gambar ≤2 MB → WebP, PDF ≤10 MB).
- Tidak ada satu pun data keuangan acara yang masuk ke tabel/halaman Kas BEM (diuji).
- **Laporan Acara** (PDF + Excel), otomatis tersedia di halaman LPJ kegiatan:
  jumlah pendaftar, terverifikasi, ditolak/kedaluwarsa, kehadiran per hari, jumlah lulus sertifikat, total pemasukan tiket, pemasukan lain, pengeluaran, saldo, rekap evaluasi per hari (rata-rata skala + kutipan jawaban teks), hasil pre/post-test.

### B5. Frontend — Halaman publik

- **`/acara`**: daftar acara terbuka (kartu poster, judul, tanggal, lokasi, badge **Gratis** / harga, sisa kuota, tombol Daftar).
- **`/acara/{slug}`**: hero dengan poster, judul, penyelenggara (logo organisasi), tanggal & jadwal per hari, lokasi (peta opsional), deskripsi, harga, sisa kuota (bar), syarat sertifikat (mis. "Hadir minimal 2 dari 3 hari"), tombol **Daftar Sekarang** menempel di bawah layar pada HP. Status "Pendaftaran ditutup / Kuota penuh" jelas.
- **Form pendaftaran**: tampilan isi form (A5) + persetujuan data + Turnstile + rate limit per email & IP longgar (amandemen #4).
- **Setelah daftar (gratis)**: halaman **E-tiket**.
- **Setelah daftar (berbayar)**: halaman instruksi pembayaran (nominal, rekening, gambar QRIS besar, batas waktu dengan hitung mundur), area unggah bukti (gambar diperkecil di browser, ≤2 MB), status "Menunggu verifikasi".
- **E-tiket** (`/tiket` → masukkan kode tiket + email): kartu tiket bergaya tiket acara (poster kecil, nama peserta, nama acara, tanggal, kode tiket, **QR besar**), tombol **Unduh tiket (PNG/PDF)**, daftar hari dengan status kehadiran (✓ hadir / belum), tombol **Isi exit ticket hari ini** (aktif saat exit ticket dibuka), tombol **Unduh sertifikat** (setelah memenuhi syarat). Tersedia mode terang yang cocok untuk dipindai di bawah cahaya ruangan. Rate limit per kode tiket + IP longgar.
- **Exit ticket** (dari QR dinamis): judul "Evaluasi Hari ke-N — {acara}", input kode tiket (terisi otomatis bila dari halaman e-tiket), form evaluasi, kirim → layar sukses "Kehadiran hari ke-N tercatat". Tanpa Turnstile; dilindungi token QR yang masih berlaku + satu kali per hari per tiket.
- **Verifikasi sertifikat** (`/sertifikat/{kode}` atau scan QR di sertifikat): menampilkan Valid/Tidak valid, nama peserta, nama acara, tanggal, nomor sertifikat. Tanpa data pribadi lain.

### B6. Frontend — Panel panitia

- **Dasbor acara** (per acara): kartu statistik (pendaftar, terverifikasi, menunggu verifikasi, hadir hari ini, pemasukan), grafik pendaftar per hari dan kehadiran per hari, tombol aksi cepat.
- **Panitia kegiatan**: daftar anggota, tambah dari akun terdaftar, tombol **Ajukan akun panitia** (status pengajuan; kredensial tampil sekali setelah disetujui Sekretaris).
- **Peserta**: tabel dengan tab status, pencarian nama/kode/email, aksi Verifikasi/Tolak (pratinjau bukti transfer di modal, zoom), export Excel.
- **Check-in (opsional)**: halaman pemindai kamera layar penuh di HP panitia, hasil scan berupa kartu hijau (nama, status valid, "Sudah check-in pukul …" bila dobel) atau merah (tiket tidak valid/belum aktif), pencarian manual sebagai cadangan. Butuh HTTPS. **Hanya halaman ini** yang mengirim `Permissions-Policy: camera=(self)` (amandemen #5).
- **Mode Exit Ticket (layar proyektor)**: halaman layar penuh untuk laptop/proyektor: nama acara, "Hari ke-N", QR besar yang berganti tiap 60 detik dengan bar hitung mundur, jumlah peserta yang sudah mengisi (real-time), tombol Tutup exit ticket.
- **Keuangan acara**: daftar pemasukan/pengeluaran, total, tombol tambah, unduh Laporan Acara.
- **Sertifikat**: editor template sederhana (unggah latar dari Canva, atur posisi nama/nomor/QR dengan geser atau koordinat, pratinjau), tombol terbitkan massal, daftar sertifikat terbit.
- **Sekretaris**: antrean **Pengajuan akun panitia** (setujui/tolak satu klik).

### B7. Data pribadi

Persetujuan data wajib di form pendaftaran. Data pribadi peserta (email, HP, jawaban form, bukti transfer) dihapus otomatis **6 bulan setelah acara selesai** (setting `activity_event_retention_months`), kecuali yang dibutuhkan untuk rekap angka dan verifikasi sertifikat (nama + nomor sertifikat).

---

## 11C — Ujian

### C1. Ujian awal oprec

- Diatur per gelombang oprec: aktif/tidak, form kuis (mesin form), durasi, waktu buka/tutup ujian.
- Setelah mendaftar, halaman konfirmasi oprec menampilkan tombol **Mulai ujian** (atau nanti lewat `/oprec/ujian` dengan kode REG + NIM).
- Satu kali kesempatan; batas waktu dihitung di server; acak soal & pilihan; simpan otomatis berkala.
- Pilihan ganda dinilai otomatis; esai dinilai koordinator divisi pilihan 1/2 (`recruitment.interview`) atau Sekretaris.
- Nilai tampil di halaman seleksi pendaftar (kolom **Nilai ujian** di samping **Nilai interview**) dan di export.
- Nilai tidak ditampilkan ke pendaftar (default); hasil seleksi tetap lewat "Umumkan hasil".
- Retensi (amandemen #7): **nilai total** disimpan bersama rekap oprec; **isi jawaban** dihapus mengikuti retensi data oprec (6 bulan setelah gelombang ditutup, untuk pendaftar yang tidak diterima).

### C2. Pre-test & post-test acara

Opsional per acara; laporan perbandingan nilai rata-rata sebelum/sesudah di Laporan Acara.

### C3. Frontend — Halaman ujian

- **Halaman sebelum mulai**: judul ujian, jumlah soal, durasi, aturan (satu kali kesempatan, waktu berjalan terus walau halaman ditutup, jawaban tersimpan otomatis), tombol **Mulai ujian** dengan konfirmasi.
- **Halaman ujian**: bar atas menempel berisi judul dan **timer hitung mundur** (berubah kuning di 5 menit terakhir, merah di 1 menit terakhir); satu soal per halaman atau semua soal (diatur di form); panel navigasi nomor soal (terjawab/belum/ditandai ragu); indikator "Tersimpan"; tombol **Kirim jawaban** dengan ringkasan soal yang belum dijawab.
- **Setelah kirim / waktu habis**: halaman konfirmasi; nilai tampil hanya jika diatur.
- Mobile-first; tidak ada library berat; tetap berfungsi bila koneksi putus sebentar (antrean simpan, coba ulang).
- Catatan: deteksi pindah tab boleh dicatat sebagai informasi untuk panitia, bukan pemblokiran otomatis.

---

## Widget dashboard (aturan grafik)

- Panitia: pendaftar per hari, status pembayaran, kehadiran per hari, rata-rata evaluasi per hari, pemasukan acara.
- Super Admin/Pembina: acara per status, total peserta per bulan.
- Sekretaris/koordinator (oprec): distribusi nilai ujian, rata-rata per soal (soal paling banyak salah).
- Sekretaris: pengajuan akun panitia menunggu persetujuan.

## Test minimal

- Kunci jawaban tidak pernah ada di respons ke peserta; jawaban setelah batas waktu ditolak; satu kali kesempatan.
- Validasi setiap jenis pertanyaan; upload file privat dan berotorisasi; gambar ≤2 MB → WebP, PDF ≤10 MB (mimes + mimetypes).
- Kuota & pelepasan kuota; alur pembayaran (verifikasi/tolak/kedaluwarsa); pemasukan tiket tidak masuk kas BEM.
- Exit ticket: hanya dengan token QR yang masih berlaku, hanya tiket aktif, satu kali per hari; tanpa Turnstile; kehadiran dan syarat sertifikat dihitung benar.
- Rate limit form acara per email/kode tiket + IP longgar; Turnstile pada pendaftaran.
- Sertifikat hanya untuk yang memenuhi syarat; halaman verifikasi tidak membocorkan data pribadi.
- Panitia hanya dari akun terdaftar yang sah (organisasi sama / pengurus BEM); kegiatan tanpa panitia tidak bisa membuat acara; pengajuan akun panitia hanya disetujui Sekretaris/Super Admin, kredensial tampil sekali.
- Pengaju bukan panitia tidak bisa membuka acara (403/404); menu "Acara Saya" hanya berisi acara panitia; jaring pengaman tetap lulus.
- Super Admin tidak bisa mengubah data peserta (hanya lihat).
- `Permissions-Policy: camera=(self)` hanya di halaman check-in; halaman lain `camera=()`.
- Penghapusan data otomatis sesuai setting (acara: data peserta; oprec: isi jawaban, nilai total tetap).
