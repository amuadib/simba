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
            ->orderBy('jam_mulai')
            ->orderBy('hari')->get();
            $tahun_ajaran = $request->tahun_ajaran ?? \App\Models\TahunAjaran::where('aktif', 'y')->first()->nama ?? '';
        return view('livewire.jadwal.print', compact('jadwal','tahun_ajaran'));
    }
}
