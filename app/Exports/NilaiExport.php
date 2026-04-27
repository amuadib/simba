<?php

namespace App\Exports;

use App\Models\Nilai;
use App\Models\Presensi;
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

        // Get all presensi for this pembelajaran
        $allPresensi = Presensi::where('pembelajaran_id', $this->pembelajaranId)->get();
        $presensiMap = [];
        foreach ($allPresensi as $p) {
            $dateKey = \Carbon\Carbon::parse($p->tanggal)->format('Y-m-d');
            $presensiMap[$p->siswa_id][$dateKey] = $p->status;
        }

        return $pembelajaran->anggota->sortBy(fn($a) => $a->siswa->nama)->values()->map(function ($anggota, $index) use ($nilaiMap, $presensiMap) {
            $siswa = $anggota->siswa;
            $row = [
                'no' => $index + 1,
                'nama' => $siswa->nama . ' (' . ($siswa->rombel->nama ?? '-') . ')',
            ];

            $totalScore = 0;
            $meetingCount = 0;
            foreach ($this->jurnals as $jurnal) {
                $val = $nilaiMap[$siswa->id][$jurnal->id] ?? null;
                
                $dateKey = \Carbon\Carbon::parse($jurnal->tanggal)->format('Y-m-d');
                $status = $presensiMap[$siswa->id][$dateKey] ?? 'H';

                if ($val !== null) {
                    $row['jurnal_' . $jurnal->id] = $val;
                } elseif (in_array($status, ['S', 'I'])) {
                    $row['jurnal_' . $jurnal->id] = $status;
                } else {
                    $row['jurnal_' . $jurnal->id] = '-';
                }

                if (in_array($status, ['I', 'S'])) {
                    continue;
                }

                if (in_array($status, ['H', 'A'])) {
                    $meetingCount++;
                    $totalScore += (float) ($val ?? 0);
                }
            }

            $row['nilai_akhir'] = $meetingCount > 0 ? round($totalScore / $meetingCount, 1) : '-';

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
