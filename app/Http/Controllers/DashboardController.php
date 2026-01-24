<?php

namespace App\Http\Controllers;

use App\Models\Presensi;
use App\Models\Siswa;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $total = Cache::remember('total_presensi', now()->addMinutes(10), function () {
            $h = $s = $i = $a = 0;
            foreach (Presensi::where('tanggal', date('Y-m-d'))->get() as $p) {
                if ($p->status == 'A') {
                    $a++;
                }
                if ($p->status == 'H') {
                    $h++;
                }
                if ($p->status == 'I') {
                    $i++;
                }
                if ($p->status == 'S') {
                    $s++;
                }
            }

            return [
                'a' => $a,
                'h' => $h,
                's' => $s,
                'i' => $i,
            ];
        });


        $chart = Cache::remember('chart_presensi_30_hari', now()->addMinutes(10), function () {
            $dates = collect(range(29, 0))->map(
                fn($i) =>
                Carbon::today()->subDays($i)
            );
            $chart = [];
            foreach ($dates as $d) {
                $chart['labels'][] = $d->format('d M');
                $row = Presensi::whereDate('tanggal', $d)
                    ->selectRaw("
                    SUM(status = 'H') as H,
                    SUM(status = 'I') as I,
                    SUM(status = 'S') as S,
                    SUM(status = 'A') as A
                ")
                    ->first();
                $h[] = (int)$row->H;
                $i[] = (int)$row->I;
                $s[] = (int)$row->S;
                $a[] = (int)$row->A;
            }
            $chart['series']['H'] = $h;
            $chart['series']['S'] = $s;
            $chart['series']['I'] = $i;
            $chart['series']['A'] = $a;

            return $chart;
            // return $dates->map(function ($date) {
            //     $row = Presensi::whereDate('tanggal', $date)
            //         ->selectRaw("
            //         SUM(status = 'H') as H,
            //         SUM(status = 'I') as I,
            //         SUM(status = 'S') as S,
            //         SUM(status = 'A') as A
            //     ")
            //         ->first();

            //     return [
            //         'label' => $date->format('d M'),
            //         'H' => (int) $row->H,
            //         'I' => (int) $row->I,
            //         'S' => (int) $row->S,
            //         'A' => (int) $row->A,
            //     ];
            // });
        });

        return view('dashboard', [
            'totalSiswa' => Siswa::count(),
            'chartData' => $chart,
            'total' => $total,
        ]);
    }
}
