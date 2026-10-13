<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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

        $lembaga = Setting::getValue('lembaga') ?? config('local.lembaga') ?? [];

        // Format logo, kop, dan ttd ke absolute URL agar client (SAKU-v2) bisa langsung menggunakannya
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

                if (! empty($item['ttd']) && ! str_starts_with($item['ttd'], 'http')) {
                    $item['ttd_url'] = asset($item['ttd']);
                } else {
                    $item['ttd_url'] = $item['ttd'] ?? null;
                }

                if (! empty($item['stempel']) && ! str_starts_with($item['stempel'], 'http')) {
                    $item['stempel_url'] = asset($item['stempel']);
                } else {
                    $item['stempel_url'] = $item['stempel'] ?? null;
                }

                $item['email'] = $item['email'] ?? null;
                $item['lat'] = $item['lat'] ?? null;
                $item['lon'] = $item['lon'] ?? null;
                $item['maps_embed'] = $item['maps_embed'] ?? null;
                $item['maps_embed_url'] = gmaps_embed_url($id);
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
