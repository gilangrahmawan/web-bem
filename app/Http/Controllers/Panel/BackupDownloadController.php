<?php

namespace App\Http\Controllers\Panel;

use App\Filament\Pages\Backups;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\BackupInventory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh berkas backup — hanya Super Admin aktif (route berlapis 2FA wajib). Selain itu 404. */
class BackupDownloadController extends Controller
{
    public function __invoke(Request $request, BackupInventory $inventory, ActivityLogger $logger): StreamedResponse
    {
        abort_unless(Backups::canAccess(), 404);

        $path = $inventory->resolve((string) $request->query('file'));
        abort_if($path === null, 404);

        $logger->log('backup.downloaded', null, ['file' => basename($path)]);

        return $inventory->disk()->download($path, basename($path), ['Content-Type' => 'application/zip']);
    }
}
