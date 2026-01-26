<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\Nilai;
use App\Models\Pembelajaran;
use Illuminate\Http\Request;

class NilaiController extends Controller
{
    public function create(Pembelajaran $pembelajaran, Jurnal $jurnal)
    {
        $nilai = Nilai::where('jurnal_id', $jurnal->id)->pluck('nilai', 'siswa_id')->toArray();
        return view('pembelajaran.jurnal.nilai.create', compact('pembelajaran', 'jurnal', 'nilai'));
    }
    public function store(Request $r, Pembelajaran $pembelajaran, Jurnal $jurnal)
    {
        foreach ($r->nilai as $siswaId => $nilai) {
            Nilai::updateOrCreate(
                [
                    'jurnal_id' => $jurnal->id,
                    'siswa_id' => $siswaId,
                    'jenis_nilai_id' => 1, //UH -> config('local.jenis_nilai')
                ],
                [
                    'nilai' => (int) $nilai,
                ]
            );
        }
        return back()->with('success', 'Nilai tersimpan');
    }
}
