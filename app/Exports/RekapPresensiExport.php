<?php

namespace App\Exports;

use App\Models\Presensi;
use App\Models\Pembelajaran;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\{
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize
};

class RekapPresensiExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $pembelajaranId, $bulan, $dates;

    public function __construct($pembelajaranId, $bulan)
    {
        $this->pembelajaranId = $pembelajaranId;
        $this->bulan = $bulan;

        $this->dates = collect(range(1, date('t', strtotime($bulan))))
            ->map(fn($d) => sprintf('%02d', $d));
    }

    public function collection()
    {
        $pembelajaran = Pembelajaran::with('anggota.siswa')->findOrFail($this->pembelajaranId);

        $presensi = Presensi::where('pembelajaran_id', $this->pembelajaranId)
            ->whereMonth('tanggal', substr($this->bulan, 5, 2))
            ->whereYear('tanggal', substr($this->bulan, 0, 4))
            ->get()
            ->groupBy('siswa_id');

        return $pembelajaran->anggota->map(function ($anggota, $index) use ($presensi) {

            $row = [
                'no' => $index + 1,
                'nama' => $anggota->siswa->nama,
            ];

            $countH = 0;
            $countI = 0;
            $countS = 0;
            $countA = 0;

            foreach ($this->dates as $day) {
                $tanggal = date("{$this->bulan}-$day");

                $status = $presensi[$anggota->siswa->id]
                    ->firstWhere('tanggal', $tanggal)
                    ->status ?? '-';

                $row[$day] = $status;

                switch ($status) {
                    case 'H':
                        $countH++;
                        break;
                    case 'I':
                        $countI++;
                        break;
                    case 'S':
                        $countS++;
                        break;
                    case 'A':
                        $countA++;
                        break;
                }
            }

            return array_merge($row, [
                'H' => $countH,
                'I' => $countI,
                'S' => $countS,
                'A' => $countA,
            ]);
        });
    }

    public function headings(): array
    {
        return array_merge(
            ['No', 'Nama'],
            $this->dates->toArray(),
            ['H', 'I', 'S', 'A']
        );
    }

    public function map($row): array
    {
        return array_values($row);
    }
}
