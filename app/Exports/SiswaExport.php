<?php

namespace App\Exports;

use App\Models\Siswa;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SiswaExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $ids;

    public function __construct(array $ids = [])
    {
        $this->ids = $ids;
    }

    public function collection()
    {
        return Siswa::with('rombel')
            ->when($this->ids, fn($q) => $q->whereIn('id', $this->ids))
            ->orderBy('nama')
            ->get();
    }

    public function headings(): array
    {
        return ['No', 'NISN', 'Nama', 'Rombel'];
    }

    public function map($siswa): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $siswa->nisn ?? '-',
            $siswa->nama,
            $siswa->rombel?->nama ?? '-',
        ];
    }
}
