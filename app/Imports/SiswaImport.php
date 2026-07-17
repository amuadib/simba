<?php

namespace App\Imports;

use App\Models\Siswa;
use App\Models\Tag;
use App\Models\Rombel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SiswaImport implements ToCollection, WithHeadingRow
{
    protected $rowsProcessed = 0;
    protected $rowsSuccess = 0;
    protected $successDetails = [];
    protected $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $this->rowsProcessed++;

            try {
                $nama = trim($row['nama'] ?? '');

                if (empty($nama)) {
                    $this->errors[] = "Baris " . ($index + 2) . ": Nama wajib diisi.";
                    continue;
                }

                $nisn = trim($row['nisn'] ?? '');
                if (!empty($nisn) && strlen($nisn) != 10) {
                    $this->errors[] = "Baris " . ($index + 2) . ": NISN harus 10 digit.";
                    continue;
                }
                $jenis_kelamin = 'L';
                $jkVal = strtolower($row['jenis_kelamin'] ?? '');
                if (!empty($jkVal)) {
                    if (in_array($jkVal, ['laki-laki', 'laki laki', 'pria', 'l', 'lk'])) {
                        $jenis_kelamin = 'L';
                    } else if (in_array($jkVal, ['perempuan', 'wanita', 'p', 'pr'])) {
                        $jenis_kelamin = 'P';
                    }
                }


                // Get Rombel ID
                $kelas = trim($row['kelas'] ?? '');
                $rombel_id = null;

                if ($kelas) {
                    $rombel = Rombel::where('tahun_ajaran_id', session('tahun_ajaran_id'))->where('nama', $kelas)->first();
                    if ($rombel) {
                        $rombel_id = $rombel->id;
                    }
                }

                $tagString = trim($row['tag'] ?? '');

                // Check jika NISN sudah ada (Validasi UPSERT manual)
                $existingSiswa = null;
                if (!empty($nisn) && strlen($nisn) === 10) {
                    $existingSiswa = Siswa::where('nisn', $nisn)->first();
                }

                $data = [
                    'nama' => $nama,
                    'nisn' => $nisn,
                    'jenis_kelamin' => $jenis_kelamin,
                    'status' => 1,
                    'rombel_id' => $rombel_id,
                ];

                if ($existingSiswa) {
                    $existingSiswa->update($data);
                    $siswa = $existingSiswa;
                    $action = 'updated';
                } else {
                    $siswa = Siswa::create($data);
                    $action = 'created';
                }

                // Handle Tags
                if (!empty($tagString)) {
                    $tagNames = explode(',', $tagString);
                    $tagIds = [];
                    foreach ($tagNames as $tagName) {
                        $tagName = trim($tagName);
                        if ($tagName) {
                            $tag = Tag::firstOrCreate(['nama' => $tagName]);
                            $tagIds[] = $tag->id;
                        }
                    }
                    $siswa->tags()->sync($tagIds);
                } else {
                    $siswa->tags()->detach();
                }

                $this->rowsSuccess++;
                $this->successDetails[] = [
                    'row' => $index + 2,
                    'nisn' => $nisn,
                    'nama' => $nama,
                    'action' => $action,
                ];
            } catch (\Exception $e) {
                $this->errors[] = "Baris " . ($index + 2) . ": Error tidak dapat diproses. " . $e->getMessage();
            }
        }
    }

    public function getResults()
    {
        return [
            'processed' => $this->rowsProcessed,
            'success' => $this->rowsSuccess,
            'failed' => $this->rowsProcessed - $this->rowsSuccess,
            'successDetails' => $this->successDetails,
            'errors' => $this->errors,
        ];
    }
}
