<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\TahunAjaran; // Sesuaikan dengan nama model di Akademik
use App\Models\Rombel;      // Sesuaikan dengan nama model di Akademik
use Illuminate\Http\Request;

class SiswaSyncController extends Controller
{
    public function sync(Request $request)
    {
        $validToken = config('local.saku.key', 'secret-token-123'); 
        
        if ($request->bearerToken() !== $validToken) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 1. Ambil Data Tahun Ajaran (Periode)
        $periode = TahunAjaran::select(['id', 'nama', 'aktif'])->get();

        // 2. Ambil Data Rombel (Kelas)
        $kelas = Rombel::select(['id', 'nama', 'tahun_ajaran_id', 'lembaga_id', 'tingkat'])->get();

        // 3. Ambil Data Siswa
        $students = Siswa::select([
            'id', 'nama', 'nis', 'nisn', 'status', 'lembaga_id', 'kelas_id', // kelas_id = rombel_id
            'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'telepon'
        ])->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'periode' => $periode,
                'kelas' => $kelas,
                'siswa' => $students
            ]
        ], 200);
    }
}
