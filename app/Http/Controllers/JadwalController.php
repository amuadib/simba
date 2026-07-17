<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Jadwal;

class JadwalController extends Controller
{
    //
    public function print(Request $request)
    {
        $jadwal = Jadwal::with(['pembelajaran.pelajaran', 'pembelajaran.tahunAjaran', 'user'])
            ->where('tahun_ajaran_id', session('tahun_ajaran_id'))
            ->orderBy('jam_mulai')
            ->orderBy('hari')->get();
        $tahun_ajaran = session('tahun_ajaran_nama');
        return view('livewire.jadwal.print', compact('jadwal', 'tahun_ajaran'));
    }
}
