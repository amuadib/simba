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
    protected $template;

    public function __construct(array $ids = [], string $template = null)
    {
        $this->ids = $ids;
        $this->template = $template;
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
        if ($this->template == 'cash_out') {
            return ['No', 'Nis', 'Nama', 'Keterangan', 'Nominal'];
        }
        return ['No', 'NISN', 'Nama', 'Rombel'];
    }

    public function map($siswa): array
    {
        static $no = 0;
        $no++;

        if ($this->template == 'cash_out') {
            return [
                $no,
                $siswa->nisn,
                str_replace("'", '', $siswa->nama), // tanda petik menyebabkan cashout PSP tidak valid
                '',
                '',
            ];
        }
        return [
            $no,
            $siswa->nisn ?? '-',
            $siswa->nama,
            $siswa->rombel?->nama ?? '-',
        ];
    }
}
