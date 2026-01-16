<?php

namespace App\Http\Controllers;

use App\Models\Pembelajaran;
use Illuminate\Http\Request;
use App\Models\Presensi;
use App\Models\Siswa;
use Illuminate\Support\Str;

class PresensiController extends Controller
{
    public function index(Request $request)
    {
        $rekap = [];
        $tglList = [];
        $bulan = $request->bulan ?? date('Y-m');

        if ($request->has('pembelajaran_id')) {
            $start = "$bulan-01";
            $end = date('Y-m-t', strtotime($start));

            $period = new \DatePeriod(new \DateTime($start), new \DateInterval('P1D'), (new \DateTime($end))->modify('+1 day'));
            foreach ($period as $d) {
                $tglList[] = $d->format('Y-m-d');
            }
            foreach (
                Siswa::leftJoin('presensi', 'presensi.siswa_id', '=', 'siswa.id')
                    ->where('presensi.pembelajaran_id', $request->pembelajaran_id)
                    ->where('presensi.tanggal', 'like', $bulan . '-%')
                    ->orderBy('siswa.nama')
                    ->get() as $p
            ) {
                $id = $p->siswa_id;
                $rekap[$p->siswa_id]['nama'] = $p->nama;
                $rekap[$p->siswa_id]['id'] = $p->siswa_id;
                foreach (['H', 'I', 'S', 'A'] as $s) {
                    $rekap[$id][$s] = $rekap[$id][$s] ?? 0;
                }
                if ($p->tanggal != '') {
                    $rekap[$id]['tgl'][$p->tanggal] = $p->status;
                    $rekap[$id][$p->status]++;
                }
            }
        }

        return view('presensi.index', [
            'pembelajaran_list' => Pembelajaran::join('tahun_ajaran', 'tahun_ajaran.id', '=', 'pembelajaran.tahun_ajaran_id')
                ->where('tahun_ajaran.aktif', 'y')
                ->select('pembelajaran.*')
                ->orderBy('pembelajaran.keterangan')
                ->get(),
            'tglList' => $tglList,
            'rekap' => $rekap,
            'statusColor' => [
                '-' => 'table-default',
                'H' => 'table-success',
                'I' => 'table-warning',
                'S' => 'table-info',
                'A' => 'table-danger'
            ],
            'bulan' => $bulan,
        ]);
    }
    public function create(Pembelajaran $pembelajaran)
    {
        return view('presensi.create', [
            'pembelajaran' => $pembelajaran,
        ]);
    }

    public function load(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'pembelajaran_id' => 'required'
        ]);

        $pembelajaran = Pembelajaran::findOrFail($request->pembelajaran_id);

        $presensi = Presensi::where('pembelajaran_id', $request->pembelajaran_id)
            ->where('tanggal', $request->tanggal)
            ->get()
            ->keyBy('siswa_id');

        $data = collect($pembelajaran->anggota)->map(function ($anggota) use ($presensi) {
            return [
                'id' => $anggota->siswa->id,
                'nama' => $anggota->siswa->nama,
                'status' => $presensi[$anggota->siswa->id]->status ?? 'A', // DEFAULT A
                'keterangan' => $presensi[$anggota->siswa->id]->keterangan ?? '',
            ];
        })
            ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json($data);
    }

    public function store(Request $r, Pembelajaran $pembelajaran)
    {
        $validated = $r->validate([
            'tanggal' => 'required|date',
            'data' => 'required|array',
            'data.*.status' => 'required|in:H,I,S,A',
            'data.*.keterangan' => 'nullable|string',
        ]);

        foreach ($validated['data'] as $siswa_id => $item) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'pembelajaran_id' => $pembelajaran->id,
                'siswa_id' => $siswa_id,
                'tanggal' => $validated['tanggal'],
                'status' => $item['status'],
                'keterangan' => $item['keterangan'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Presensi::upsert(
            $rows,
            ['siswa_id', 'pembelajaran_id', 'tanggal'], // UNIQUE KEY
            ['status', 'keterangan', 'updated_at']      // UPDATE FIELD
        );


        //     collect($validated['data'])->map(function ($item, $siswa_id) use ($pembelajaran, $validated) {
        //         return [
        //             'id' => \Illuminate\Support\Str::uuid(),
        //             'pembelajaran_id' => $pembelajaran->id,
        //             'siswa_id' => $siswa_id,
        //             'tanggal' => $validated['tanggal'],
        //             'status' => $item['status'],
        //             'keterangan' => $item['keterangan'],
        //             'created_at' => now(),
        //             'updated_at' => now(),
        //         ];
        //     })->toArray()

        return redirect()->route('pembelajaran.index')
            ->with('success', 'Presensi berhasil disimpan.');
    }
    public function updateCell(Request $request)
    {
        $data = $request->validate([
            'siswa_id' => 'required|exists:siswa,id',
            'pembelajaran_id' => 'required|exists:pembelajaran,id',
            'tanggal' => 'required|date',
            'status' => 'required|in:-,H,I,S,A',
        ]);

        if ($data['status'] == '-') {
            Presensi::where('siswa_id', $data['siswa_id'])
                ->where('pembelajaran_id', $data['pembelajaran_id'])
                ->where('tanggal', $data['tanggal'])
                ->delete();

            return response()->json(['success' => true]);
        }
        Presensi::upsert(
            [
                [
                    'id' => (string) Str::uuid(),
                    'siswa_id' => $data['siswa_id'],
                    'pembelajaran_id' => $data['pembelajaran_id'],
                    'tanggal' => $data['tanggal'],
                    'status' => $data['status'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ],
            ['siswa_id', 'pembelajaran_id', 'tanggal'],
            ['status', 'updated_at']
        );

        return response()->json(['success' => true]);
    }
}
