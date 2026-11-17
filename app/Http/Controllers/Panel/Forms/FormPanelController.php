<?php

namespace App\Http\Controllers\Panel\Forms;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormAnswer;
use App\Services\ActivityLogger;
use App\Services\Forms\FormExport;
use App\Support\SpreadsheetExport;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Aksi form di luar Livewire: pratinjau (tanpa menyimpan respons), QR PNG, export, unduh berkas jawaban.
 * Tidak berhak → 404 (cegah IDOR).
 */
class FormPanelController extends Controller
{
    private function find(int $id, string $ability): Form
    {
        $form = Form::query()->find($id);
        abort_if($form === null || Gate::denies($ability, $form), 404);

        return $form;
    }

    public function preview(int $form): View
    {
        $record = $this->find($form, 'view');

        return view('public.forms.show', ['publicId' => $record->public_id, 'preview' => true]);
    }

    public function qr(int $form): Response
    {
        $record = $this->find($form, 'view');

        $png = (new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'scale' => 12,
            'quietzoneSize' => 4,
        ])))->render($record->publicUrl());

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="qr-form-'.Str::slug($record->title ?: 'form').'.png"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function export(int $form, string $format, ActivityLogger $logger): BinaryFileResponse
    {
        $record = $this->find($form, 'export');
        $path = SpreadsheetExport::write(FormExport::rows($record), $format);
        $logger->log('form.exported', $record, ['format' => $format]);

        return response()->download($path, 'jawaban-'.Str::slug($record->title ?: 'form').'-'.now()->format('Ymd-His').'.'.$format)->deleteFileAfterSend();
    }

    public function answerFile(int $answer, int $index): StreamedResponse
    {
        $record = FormAnswer::query()->with('response.form')->find($answer);
        $form = $record?->response?->form;

        abort_if($record === null || $form === null || ! $record->response->isSubmitted() || Gate::denies('viewResponses', $form), 404);

        $file = ($record->files ?? [])[$index] ?? null;
        $disk = Storage::disk(FormAnswer::DISK);
        abort_unless($file && $disk->exists($file['path']), 404);

        return $disk->download($file['path'], $file['name'], [
            'Content-Type' => $file['mime'] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
