<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rombel;
use App\Models\Siswa; // Sesuaikan dengan nama model di Akademik
use App\Models\TahunAjaran;      // Sesuaikan dengan nama model di Akademik
use Illuminate\Http\Request;

class SiswaSyncController extends Controller
{
    public function sync(Request $request)
    {
        $validToken = config('local.saku.key', 'secret-token-123');

        if ($request->bearerToken() !== $validToken) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 1. Ambil Data Tahun Ajaran (Periode) Aktif
        $periode = TahunAjaran::select(['id', 'nama', 'aktif'])->where('aktif', 'y')->get();

        // 2. Ambil Data Rombel (Kelas)
        $kelas = Rombel::select(['id', 'nama', 'tingkat', 'lembaga_id'])
            ->whereIn('tahun_ajaran_id', $periode->pluck('id'))
            ->get();

        // 3. Ambil Data Siswa
        $students = Siswa::with(['tags:id,nama', 'rombel:id,nama'])->select([
            'id', 'nama', 'nis', 'nisn', 'status', 'lembaga_id', 'rombel_id', // kelas_id = rombel_id
            'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'telepon',
        ])
            ->whereIn('rombel_id', $kelas->pluck('id'))
            ->get()
            ->map(function ($siswa) {
                $data = $siswa->toArray();
                $data['rombel_nama'] = $siswa->rombel ? $siswa->rombel->nama : null;
                unset($data['rombel_id'], $data['rombel']);
                return $data;
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'periode' => $periode,
                'kelas' => $kelas,
                'siswa' => $students,
            ],
        ], 200);
    }
}
