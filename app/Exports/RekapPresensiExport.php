<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class RekapPresensiExport implements FromView
{
    protected $bulan, $kelasId;

    public function __construct($bulan, $kelasId)
    {
        $this->bulan = $bulan;
        $this->kelasId = $kelasId;
    }

    public function view(): View
    {
        $carbon = Carbon::parse($this->bulan);

        $siswa = Siswa::with(['presensi' => function ($q) use ($carbon) {
            $q->whereMonth('tanggal', $carbon->month)
                ->whereYear('tanggal', $carbon->year);
        }])->when(
            $this->kelasId,
            fn($q) => $q->where('kelas_id', $this->kelasId)
        )->get();

        return view('presensi.export', compact('siswa', 'carbon'));
    }
}
