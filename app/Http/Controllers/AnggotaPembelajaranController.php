<?php

namespace App\Http\Controllers;

use App\Models\AnggotaPembelajaran;
use Illuminate\Http\Request;
use App\Models\Pembelajaran;
use App\Models\Siswa;
use App\Models\Rombel;

class AnggotaPembelajaranController extends Controller
{

    public function index(Request $r, Pembelajaran $pembelajaran)
    {
        $rombel_id = $r->get('rombel_id') ?? null;
        return view('pembelajaran.anggota.index', [
            'pembelajaran' => $pembelajaran,
            'rombel' => Rombel::orderBy('tingkat')->get(),
            'siswa' => Siswa::orderBy('nama')->get(),
            'anggota_kelas' => $rombel_id !== null ? Siswa::where('rombel_id', $rombel_id)->orderBy('nama')->get() : [],
            'anggota_selected' => $pembelajaran->anggota->pluck('siswa_id')->toArray(),
        ]);
    }

    public function update(Request $request, Pembelajaran $pembelajaran, String $mode = 'add')
    {
        $validated = $request->validate([
            'anggota' => 'nullable|array',
            'anggota.*' => 'exists:siswa,id',
        ]);

        if ($mode === 'remove') {
            AnggotaPembelajaran::where('pembelajaran_id', $pembelajaran->id)
                ->whereIn('siswa_id', $validated['anggota'])
                ->delete();
        } else {
            if (!empty($validated['anggota'])) {
                AnggotaPembelajaran::insert(
                    collect($validated['anggota'])->map(function ($siswa_id) use ($pembelajaran) {
                        return [
                            'id' => \Illuminate\Support\Str::uuid(),
                            'pembelajaran_id' => $pembelajaran->id,
                            'siswa_id' => $siswa_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })->toArray()
                );
            }
        }
        return response()->json([
            'status' => 'ok'
        ]);
    }
}
