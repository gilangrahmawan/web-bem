<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Simpan/hapus langganan Web Push perangkat milik user yang login (dipanggil JS halaman Notifikasi).
 * Endpoint wajib HTTPS dari layanan push browser; satu endpoint hanya milik satu user.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->updatePushSubscription($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'], $data['contentEncoding'] ?? 'aes128gcm');

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);

        /** @var User $user */
        $user = $request->user();
        $user->deletePushSubscription($data['endpoint']);

        return response()->json(['ok' => true]);
    }
}
