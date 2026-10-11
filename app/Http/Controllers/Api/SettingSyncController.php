<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingSyncController extends Controller
{
    public function sync(Request $request)
    {
        $validToken = config('local.saku.key', env('SAKU_KEY', 'secret-token-123'));

        if ($request->bearerToken() !== $validToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Invalid Bearer Token',
            ], 401);
        }

        $lembaga = config('local.lembaga') ?? [];

        // Format logo dan kop ke absolute URL agar client (SAKU-v2) bisa langsung menggunakannya
        if (is_array($lembaga)) {
            foreach ($lembaga as $id => &$item) {
                if (! empty($item['logo']) && ! str_starts_with($item['logo'], 'http')) {
                    $item['logo_url'] = asset($item['logo']);
                } else {
                    $item['logo_url'] = $item['logo'] ?? null;
                }

                if (! empty($item['kop']) && ! str_starts_with($item['kop'], 'http')) {
                    $item['kop_url'] = asset($item['kop']);
                } else {
                    $item['kop_url'] = $item['kop'] ?? null;
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'lembaga' => $lembaga,
            ],
            'server_time' => now()->toIso8601String(),
        ], 200);
    }
}
