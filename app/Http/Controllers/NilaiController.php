<?php

namespace App\Http\Controllers;

use App\Models\Jurnal;
use App\Models\Nilai;
use App\Models\Pembelajaran;
use App\Models\Presensi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NilaiController extends Controller
{
    public function index(Pembelajaran $pembelajaran)
    {
        $nilai = [];
        $jurnals = [];
        $anggota = $pembelajaran->anggota;
        foreach ($pembelajaran->jurnal as $j) {
            $jurnals[$j->id] = ['tanggal' => $j->tanggal, 'materi' => $j->materi];
            foreach ($j->nilai as $n) {
                $nilai[$n->siswa_id][$n->jurnal_id] = $n->nilai;
            }
        }

        return view('pembelajaran.jurnal.nilai.index', compact('pembelajaran', 'nilai', 'jurnals', 'anggota'));
    }


    public function create(Pembelajaran $pembelajaran, Jurnal $jurnal)
    {
        $nilai = Nilai::where('jurnal_id', $jurnal->id)->pluck('nilai', 'siswa_id')->toArray();
        $presensi_siswa = Presensi::where('pembelajaran_id', $pembelajaran->id)->where('tanggal', date('Y-m-d', strtotime($jurnal->tanggal)))->pluck('status', 'siswa_id')->toArray();

        return view('pembelajaran.jurnal.nilai.create', compact('pembelajaran', 'jurnal', 'nilai', 'presensi_siswa'));
    }
    // public function store(Request $r, Pembelajaran $pembelajaran, Jurnal $jurnal)
    // {
    //     foreach ($r->nilai as $siswaId => $nilai) {
    //         Nilai::updateOrCreate(
    //             [
    //                 'jurnal_id' => $jurnal->id,
    //                 'siswa_id' => $siswaId,
    //                 'jenis_nilai_id' => 1, //UH -> config('local.jenis_nilai')
    //             ],
    //             [
    //                 'nilai' => (int) $nilai,
    //             ]
    //         );
    //     }
    //     return back()->with('success', 'Nilai tersimpan');
    // }

    public function update(Request $request)
    {
        $data = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'jurnal_id' => 'required|exists:jurnal,id',
            'jenis_nilai_id' => 'required',
            'nilai' => 'required|min:0|max:100',
        ]);

        if ($data['nilai'] == '') {
            Nilai::where('siswa_id', $data['siswa_id'])
                ->where('jurnal_id', $data['jurnal_id'])
                ->where('jenis_nilai_id', $data['jenis_nilai_id'])
                ->delete();

            return response()->json(['success' => true]);
        }
        Nilai::upsert(
            [
                [
                    'id' => (string) Str::uuid(),
                    'siswa_id' => $data['siswa_id'],
                    'jurnal_id' => $data['jurnal_id'],
                    'jenis_nilai_id' => $data['jenis_nilai_id'],
                    'nilai' => $data['nilai'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ],
            ['siswa_id', 'jurnal_id', 'jenis_nilai_id'],
            ['nilai', 'updated_at']
        );

        return response()->json(['success' => true]);
    }
}
