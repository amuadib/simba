<?php

namespace App\Exports;

use App\Models\Pegawai;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PegawaiExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
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
        if ($this->template == 'template-import') {
            return collect([
                (object) [
                    'niy' => '198701012020011001',
                    'nuptk' => '1234567890123456',
                    'nama' => 'Ahmad Fauzi',
                    'gelar_depan' => 'Drs.',
                    'gelar_belakang' => 'M.Pd.',
                    'jenis_kelamin' => 'L',
                    'nik' => '3309012345678901',
                    'tempat_lahir' => 'Boyolali',
                    'tanggal_lahir' => '1985-05-15',
                    'tmt' => '2015-07-01',
                    'alamat' => 'Jl. Merbabu No. 12, Boyolali',
                    'no_hp' => '081234567890',
                    'lembaga_id' => 99,
                    'status' => '1',
                ]
            ]);
        }

        $query = Pegawai::query()->when($this->ids, fn($q) => $q->whereIn('id', $this->ids));
        if ($this->ids) {
            return $query->get()->sortBy(fn($p) => array_search($p->id, $this->ids));
        }

        return $query->orderBy('nama')->get();
    }

    public function headings(): array
    {
        if ($this->template == 'template-import') {
            return [
                'No',
                'NIY',
                'NUPTK',
                'Nama',
                'Gelar Depan',
                'Gelar Belakang',
                'Jenis Kelamin (L/P)',
                'NIK',
                'Tempat Lahir',
                'Tanggal Lahir (YYYY-MM-DD)',
                'TMT (YYYY-MM-DD)',
                'Alamat',
                'No HP',
                'Lembaga ID',
                'Status (1=Aktif, 2=Cuti, 3=Non-Aktif, 4=Mutasi, 5=Pensiun, 6=Keluar, 7=Almarhum)',
            ];
        }

        return [
            'No',
            'NIY',
            'NUPTK',
            'Nama Lengkap',
            'Gelar Depan',
            'Nama',
            'Gelar Belakang',
            'Jenis Kelamin',
            'NIK',
            'Tempat Lahir',
            'Tanggal Lahir',
            'TMT',
            'Alamat',
            'No HP',
            'Lembaga',
            'Status',
        ];
    }

    public function map($pegawai): array
    {
        static $no = 0;
        $no++;

        if ($this->template == 'template-import') {
            return [
                $no,
                $pegawai->niy,
                $pegawai->nuptk,
                $pegawai->nama,
                $pegawai->gelar_depan,
                $pegawai->gelar_belakang,
                $pegawai->jenis_kelamin,
                $pegawai->nik,
                $pegawai->tempat_lahir,
                $pegawai->tanggal_lahir,
                $pegawai->tmt,
                $pegawai->alamat,
                $pegawai->no_hp,
                $pegawai->lembaga_id,
                $pegawai->status,
            ];
        }

        return [
            $no,
            $pegawai->niy ?: '-',
            $pegawai->nuptk ?: '-',
            $pegawai->nama_lengkap,
            $pegawai->gelar_depan ?: '-',
            $pegawai->nama,
            $pegawai->gelar_belakang ?: '-',
            strtolower($pegawai->jenis_kelamin) === 'l' ? 'Laki-laki' : 'Perempuan',
            $pegawai->nik ?: '-',
            $pegawai->tempat_lahir ?: '-',
            $pegawai->tanggal_lahir ? $pegawai->tanggal_lahir->format('d/m/Y') : '-',
            $pegawai->tmt ? $pegawai->tmt->format('d/m/Y') : '-',
            $pegawai->alamat ?: '-',
            $pegawai->no_hp ?: '-',
            nama_lembaga($pegawai->lembaga_id),
            $pegawai->status_label,
        ];
    }
}
