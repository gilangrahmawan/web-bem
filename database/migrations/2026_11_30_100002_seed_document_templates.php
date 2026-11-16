<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration DATA (hanya menambah): 5 template dokumen bawaan Fase 8. Kop BEM ditambahkan saat export.
 * Template yang sudah ada (nama sama) tidak disentuh, sehingga hasil edit Sekretaris aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->templates() as $name => $content) {
            if (! DB::table('document_templates')->where('name', $name)->exists()) {
                DB::table('document_templates')->insert([
                    'name' => $name,
                    'content' => $content,
                    'has_letterhead' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('document_templates')->whereIn('name', array_keys($this->templates()))->delete();
    }

    /** @return array<string, string> */
    private function templates(): array
    {
        $signature = '<p style="text-align: right">Malang, [tanggal]</p>'
            .'<p style="text-align: right">Hormat kami,<br>Badan Eksekutif Mahasiswa UBHINUS</p>'
            .'<p style="text-align: right"><br><br><strong>[Nama Ketua]</strong><br>Ketua BEM</p>';

        $header = '<p>Nomor&#160;&#160;&#160;: [nomor surat]<br>Lampiran : [jumlah] berkas<br>Perihal&#160;&#160;&#160;: <strong>[perihal]</strong></p>'
            .'<p>Kepada Yth.<br>[Nama/Jabatan penerima]<br>di tempat</p>';

        return [
            'Surat Undangan' => $header
                .'<p>Dengan hormat,</p>'
                .'<p>Sehubungan dengan akan dilaksanakannya kegiatan <strong>[nama kegiatan]</strong>, kami bermaksud mengundang Bapak/Ibu/Saudara untuk hadir pada:</p>'
                .'<table><tbody><tr><td>Hari, tanggal</td><td>[hari, tanggal]</td></tr><tr><td>Waktu</td><td>[jam] WIB – selesai</td></tr><tr><td>Tempat</td><td>[tempat]</td></tr><tr><td>Acara</td><td>[acara]</td></tr></tbody></table>'
                .'<p>Demikian undangan ini kami sampaikan. Atas perhatian dan kehadirannya kami ucapkan terima kasih.</p>'
                .$signature,

            'Surat Peminjaman' => $header
                .'<p>Dengan hormat,</p>'
                .'<p>Dalam rangka kegiatan <strong>[nama kegiatan]</strong>, kami bermaksud meminjam sarana/prasarana berikut:</p>'
                .'<table><thead><tr><th>No</th><th>Nama barang/ruang</th><th>Jumlah</th><th>Keterangan</th></tr></thead><tbody><tr><td>1</td><td>[nama]</td><td>[jumlah]</td><td>[keterangan]</td></tr></tbody></table>'
                .'<p>Waktu peminjaman: [tanggal mulai] s.d. [tanggal selesai]. Kami bertanggung jawab penuh atas barang/ruang yang dipinjam.</p>'
                .'<p>Demikian permohonan ini kami sampaikan. Atas perhatian dan izinnya kami ucapkan terima kasih.</p>'
                .$signature,

            'Proposal Kegiatan' => '<h2 style="text-align: center">PROPOSAL KEGIATAN<br>[NAMA KEGIATAN]</h2>'
                .'<h3>A. Latar Belakang</h3><p>[uraian latar belakang]</p>'
                .'<h3>B. Tujuan</h3><ol><li><p>[tujuan 1]</p></li><li><p>[tujuan 2]</p></li></ol>'
                .'<h3>C. Waktu dan Tempat</h3><p>[hari, tanggal] · [tempat]</p>'
                .'<h3>D. Sasaran Peserta</h3><p>[sasaran]</p>'
                .'<h3>E. Susunan Panitia</h3><p>[susunan panitia]</p>'
                .'<h3>F. Rencana Anggaran</h3><table><thead><tr><th>No</th><th>Uraian</th><th>Jumlah</th><th>Harga satuan</th><th>Total</th></tr></thead><tbody><tr><td>1</td><td>[uraian]</td><td>[jml]</td><td>Rp[...]</td><td>Rp[...]</td></tr></tbody></table>'
                .'<h3>G. Penutup</h3><p>Demikian proposal ini kami susun. Besar harapan kami atas dukungan semua pihak.</p>'
                .$signature,

            'Laporan Pertanggungjawaban (LPJ)' => '<h2 style="text-align: center">LAPORAN PERTANGGUNGJAWABAN<br>[NAMA KEGIATAN]</h2>'
                .'<h3>A. Pendahuluan</h3><p>[ringkasan kegiatan]</p>'
                .'<h3>B. Pelaksanaan</h3><p>Waktu: [tanggal] · Tempat: [tempat] · Jumlah peserta: [jumlah]</p>'
                .'<h3>C. Hasil Kegiatan</h3><p>[hasil dan capaian]</p>'
                .'<h3>D. Realisasi Anggaran</h3><table><thead><tr><th>No</th><th>Uraian</th><th>Anggaran</th><th>Realisasi</th><th>Selisih</th></tr></thead><tbody><tr><td>1</td><td>[uraian]</td><td>Rp[...]</td><td>Rp[...]</td><td>Rp[...]</td></tr></tbody></table>'
                .'<h3>E. Kendala dan Evaluasi</h3><p>[kendala, evaluasi, rekomendasi]</p>'
                .'<h3>F. Penutup</h3><p>Demikian laporan ini kami sampaikan.</p>'
                .$signature,

            'Notulen Rapat' => '<h2 style="text-align: center">NOTULEN RAPAT<br>[NAMA RAPAT]</h2>'
                .'<table><tbody><tr><td>Hari, tanggal</td><td>[hari, tanggal]</td></tr><tr><td>Waktu</td><td>[jam mulai – selesai] WIB</td></tr><tr><td>Tempat</td><td>[tempat/link]</td></tr><tr><td>Pimpinan rapat</td><td>[nama]</td></tr><tr><td>Notulis</td><td>[nama]</td></tr><tr><td>Peserta</td><td>[daftar hadir]</td></tr></tbody></table>'
                .'<h3>Agenda</h3><ol><li><p>[agenda 1]</p></li></ol>'
                .'<h3>Pembahasan</h3><p>[ringkasan pembahasan]</p>'
                .'<h3>Keputusan</h3><ol><li><p>[keputusan 1]</p></li></ol>'
                .'<h3>Tindak Lanjut</h3><table><thead><tr><th>Tugas</th><th>Penanggung jawab</th><th>Tenggat</th></tr></thead><tbody><tr><td>[tugas]</td><td>[nama]</td><td>[tanggal]</td></tr></tbody></table>',
        ];
    }
};
