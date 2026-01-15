<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RekapPresensiExport;

class RekapPresensiController extends Controller
{
    public function index(Request $request)
    {
        $bulan = $request->bulan ?? now()->format('Y-m');
        $kelasId = $request->kelas_id;

        $carbon = Carbon::parse($bulan);
        $jumlahHari = $carbon->daysInMonth;

        $kelas = Kelas::all();

        $siswa = Siswa::with(['presensi' => function ($q) use ($carbon) {
            $q->whereMonth('tanggal', $carbon->month)
                ->whereYear('tanggal', $carbon->year);
        }])
            ->when($kelasId, fn($q) => $q->where('kelas_id', $kelasId))
            ->orderBy('nama')
            ->get();

        return view('presensi.rekap', compact(
            'bulan',
            'jumlahHari',
            'siswa',
            'kelas',
            'kelasId'
        ));
    }

    public function export(Request $request)
    {
        return Excel::download(
            new RekapPresensiExport($request->bulan, $request->kelas_id),
            'rekap-presensi.xlsx'
        );
    }
}
