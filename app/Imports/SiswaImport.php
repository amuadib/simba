<?php

namespace App\Imports;

use App\Models\Siswa;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithUpserts;

class SiswaImport implements ToModel, WithHeadingRow, WithValidation, WithUpserts
{
    protected $rombel_id;
    public function __construct($rombel_id)
    {
        $this->rombel_id = $rombel_id;
    }
    public function rules(): array
    {
        return [
            '*.nama' => 'required|string',
            '*.nisn' => 'nullable|digits:10'
        ];
    }
    public function uniqueBy()
    {
        return 'nisn';
    }
    public function model(array $row)
    {
        return new Siswa([
            'nama' => $row['nama'],
            'rombel_id' => $this->rombel_id,
            'nisn' => $row['nisn'] ?? null,
        ]);
    }
}
