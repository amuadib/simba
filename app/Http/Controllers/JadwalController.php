<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\Request;

class JadwalController extends Controller
{
    public function print(Request $request)
    {
        $tahunAjaranId = session('tahun_ajaran_id') ?? TahunAjaran::where('aktif', 'y')->value('id');
        $tahun_ajaran = session('tahun_ajaran_nama') ?? TahunAjaran::where('id', $tahunAjaranId)->value('nama') ?? '';

        $query = Jadwal::with(['pembelajaran.pelajaran', 'pembelajaran.tahunAjaran', 'user'])
            ->when($tahunAjaranId, function ($q) use ($tahunAjaranId) {
                $q->whereHas('pembelajaran', fn($sub) => $sub->where('tahun_ajaran_id', $tahunAjaranId));
            })
            ->orderBy('jam_mulai')
            ->orderBy('hari');

        $guru = null;
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
            $guru = User::find($request->user_id);
        }

        $jadwal = $query->get();

        return view('livewire.jadwal.print', compact('jadwal', 'tahun_ajaran', 'guru'));
    }
}
