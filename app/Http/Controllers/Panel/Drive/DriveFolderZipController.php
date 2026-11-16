<?php

namespace App\Http\Controllers\Panel\Drive;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Services\Drive\DriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Unduh folder sebagai ZIP (hanya item yang boleh dilihat). Tidak berhak → 404. */
class DriveFolderZipController extends Controller
{
    public function __invoke(Request $request, int $folder, DriveService $drive): BinaryFileResponse|RedirectResponse
    {
        $record = Folder::query()->find($folder);
        abort_if($record === null || Gate::forUser($request->user())->denies('view', $record), 404);

        try {
            $path = $drive->zip($request->user(), $record);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return response()->download($path, (Str::slug($record->name) ?: 'folder').'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
