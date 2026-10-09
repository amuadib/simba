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
        if ($this->template == 'template-import') {
            return collect([
                (object) [
                    'nisn' => '1234567890',
                    'nama' => 'Ahmad Fauzi',
                    'kelas' => 'IX A',
                    'jenis_kelamin' => 'L',
                    'tags' => collect([])
                ]
            ]);
        }

        $query = Siswa::with('rombel')
                ->when($this->ids, fn($q) => $q->whereIn('id', $this->ids));
        if ($this->ids) {
            return $query->get()->sortBy(fn($s) => array_search($s->id, $this->ids));
        }
        return 
            $query
            ->orderBy('nama')
            ->get();
    }

    public function headings(): array
    {
        if ($this->template == 'template-import') {
            return ['No', 'NISN', 'NIK', 'NIS', 'No Akte', 'No KK', 'Nama', 'Tempat Lahir', 'Tanggal Lahir', 'Kelas', 'Jenis Kelamin', 'Lembaga ID', 'Alamat', 'Telepon', 'Ayah', 'Ibu', 'Tag'];
        }
        if ($this->template == 'cashout-template') {
            return ['No', 'Nis', 'Nama', 'Panggilan', 'Keterangan', 'Nominal'];
        }
        return ['No', 'NISN', 'Nama', 'Rombel'];
    }

    public function map($siswa): array
    {
        static $no = 0;
        $no++;

        if ($this->template == 'cashout-template') {
            return [
                $no,
                $siswa->nisn,
                $siswa->nama,
                $siswa->panggilan,
                '',
                ''
            ];
        }

        if ($this->template == 'template-import') {
            $faker = fake('id_ID');
            $gender = $faker->randomElement(['male', 'female']);
            return [
                $no,
                $faker->unique()->numerify('##########'),
                $faker->unique()->numerify('################'), // NIK
                $faker->unique()->numerify('######'), // NIS
                'AKTE-'.$faker->numerify('######'), // No Akte
                $faker->unique()->numerify('################'), // No KK
                $faker->name($gender), // Nama
                $faker->city(), // Tempat Lahir
                $faker->date(), // Tanggal Lahir
                'IX A', // Kelas
                $gender == 'male' ? 'L' : 'P', // Jenis Kelamin
                99, // Lembaga ID
                $faker->address(), // Alamat
                $faker->phoneNumber(), // Telepon
                $faker->name('male'), // Ayah
                $faker->name('female'), // Ibu
                implode(',', $faker->words(3)), // Tag
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
