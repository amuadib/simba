<?php

namespace App\Exports;

use App\Models\Nilai;
use App\Models\Jurnal;
use App\Models\Pembelajaran;
use Maatwebsite\Excel\Concerns\{FromCollection, WithHeadings, WithMapping, ShouldAutoSize};

class NilaiExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $pembelajaranId;
    protected $jurnals;

    public function __construct($pembelajaranId)
    {
        $this->pembelajaranId = $pembelajaranId;
        $this->jurnals = Jurnal::where('pembelajaran_id', $this->pembelajaranId)
            ->orderBy('tanggal')
            ->get();
    }

    public function collection()
    {
        $pembelajaran = Pembelajaran::with(['anggota.siswa.rombel'])->findOrFail($this->pembelajaranId);
        
        $allNilai = Nilai::whereIn('jurnal_id', $this->jurnals->pluck('id'))->get();
        $nilaiMap = [];
        foreach ($allNilai as $n) {
            $nilaiMap[$n->siswa_id][$n->jurnal_id] = $n->nilai;
        }

        return $pembelajaran->anggota->sortBy(fn($a) => $a->siswa->nama)->values()->map(function ($anggota, $index) use ($nilaiMap) {
            $siswa = $anggota->siswa;
            $row = [
                'no' => $index + 1,
                'nama' => $siswa->nama . ' (' . ($siswa->rombel->nama ?? '-') . ')',
            ];

            $scores = [];
            foreach ($this->jurnals as $jurnal) {
                $val = $nilaiMap[$siswa->id][$jurnal->id] ?? null;
                $row['jurnal_' . $jurnal->id] = $val ?? '-';
                if ($val !== null) {
                    $scores[] = (float) $val;
                }
            }

            $row['nilai_akhir'] = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : '-';

            return $row;
        });
    }

    public function headings(): array
    {
        $headings = ['No', 'Nama Siswa / Rombel'];
        
        foreach ($this->jurnals as $jurnal) {
            $headings[] = $jurnal->materi . ' (' . \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/y') . ')';
        }

        $headings[] = 'Nilai Akhir';

        return $headings;
    }

    public function map($row): array
    {
        return array_values($row);
    }
}
