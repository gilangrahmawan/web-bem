<?php

namespace App\Services\Drive;

use App\Models\Document;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use Throwable;

/**
 * Export dokumen ke PDF (dompdf) & DOCX (PHPWord). Kop BEM (logo, nama kabinet, kontak dari settings)
 * ditambahkan bila dokumen dibuat dari template ber-kop. Isi selalu disanitasi sebelum dirender.
 */
class DocumentExporter
{
    public const LOGO = 'images/logo-bem-160.png';

    /** @return string path file sementara (hapus setelah dikirim) */
    public function pdf(Document $document): string
    {
        $html = view('documents.export', [
            'document' => $document,
            'content' => $document->safeContent(),
            'letterhead' => $document->has_letterhead ? $this->letterhead() : null,
        ])->render();

        $path = tempnam(sys_get_temp_dir(), 'doc-pdf-');
        Pdf::loadHTML($html)->setPaper('a4')->setOption('isRemoteEnabled', false)->save($path);

        return $path;
    }

    /** @return string path file sementara (hapus setelah dikirim) */
    public function docx(Document $document): string
    {
        $word = new PhpWord;
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);
        $section = $word->addSection(['marginTop' => 1134, 'marginBottom' => 1134, 'marginLeft' => 1418, 'marginRight' => 1134]);

        if ($document->has_letterhead) {
            $kop = $this->letterhead();
            $table = $section->addTable(['borderBottomSize' => 18, 'borderBottomColor' => '000000', 'cellMargin' => 40]);
            $table->addRow();

            if (is_file($kop['logo_path'])) {
                $table->addCell(1400)->addImage($kop['logo_path'], ['width' => 60, 'height' => 60]);
            }

            $cell = $table->addCell(8000);
            $center = ['alignment' => 'center', 'spaceAfter' => 0];
            $cell->addText($kop['title'], ['bold' => true, 'size' => 14], $center);
            $cell->addText($kop['subtitle'], ['bold' => true, 'size' => 12], $center);

            if ($kop['cabinet']) {
                $cell->addText($kop['cabinet'], ['italic' => true, 'size' => 11], $center);
            }

            if ($kop['contact']) {
                $cell->addText($kop['contact'], ['size' => 9], $center);
            }

            $section->addTextBreak();
        }

        $section->addTitle(htmlspecialchars($document->title, ENT_XML1), 1);

        try {
            Html::addHtml($section, $this->wordSafe($document->safeContent()));
        } catch (Throwable) {
            // HTML yang tidak didukung PHPWord → teks polos per paragraf.
            foreach (preg_split('/\R{2,}/', trim(strip_tags(str_replace(['</p>', '<br>', '<br />'], "\n\n", $document->safeContent())))) ?: [] as $paragraph) {
                $section->addText(htmlspecialchars(html_entity_decode($paragraph), ENT_XML1));
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'doc-docx-');
        IOFactory::createWriter($word, 'Word2007')->save($path);

        return $path;
    }

    public function fileName(Document $document, string $extension): string
    {
        return (Str::slug($document->title) ?: 'dokumen').'.'.$extension;
    }

    /** @return array{title: string, subtitle: string, cabinet: ?string, contact: ?string, logo_path: string, logo_data: ?string} */
    public function letterhead(): array
    {
        $logo = public_path(self::LOGO);
        $contact = collect([Setting::get('contact_address'), Setting::get('contact_email'), Setting::get('contact_phone')])->filter()->implode(' · ');

        return [
            'title' => 'BADAN EKSEKUTIF MAHASISWA',
            'subtitle' => 'UNIVERSITAS BHINNEKA NUSANTARA',
            'cabinet' => Setting::get('cabinet_name') ?: null,
            'contact' => $contact !== '' ? $contact : null,
            'logo_path' => $logo,
            'logo_data' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ];
    }

    /** PHPWord butuh XHTML rapi: tutup <br>, buang atribut style yang tidak dikenal. */
    private function wordSafe(string $html): string
    {
        $html = preg_replace('/<br\s*>/i', '<br/>', $html) ?? $html;
        $html = preg_replace('/<hr\s*>/i', '<hr/>', $html) ?? $html;

        return str_replace('&nbsp;', '&#160;', $html);
    }
}
