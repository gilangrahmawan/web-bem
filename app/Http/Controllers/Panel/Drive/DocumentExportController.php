<?php

namespace App\Http\Controllers\Panel\Drive;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Drive\DocumentExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Export dokumen ke PDF/DOCX (dengan kop BEM bila dari template ber-kop). Tidak berhak → 404. */
class DocumentExportController extends Controller
{
    public function __invoke(Request $request, int $document, string $format, DocumentExporter $exporter): BinaryFileResponse
    {
        $record = Document::query()->find($document);
        abort_if($record === null || Gate::forUser($request->user())->denies('view', $record), 404);

        $path = $format === 'pdf' ? $exporter->pdf($record) : $exporter->docx($record);
        $mime = $format === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        return response()->download($path, $exporter->fileName($record, $format), ['Content-Type' => $mime])
            ->deleteFileAfterSend();
    }
}
