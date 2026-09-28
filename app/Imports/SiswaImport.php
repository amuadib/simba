<?php

namespace App\Imports;

use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

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
                    $this->errors[] = 'Baris '.($index + 2).': Nama wajib diisi.';

                    continue;
                }

                $nisn = trim($row['nisn'] ?? '');
                if (! empty($nisn) && strlen($nisn) != 10) {
                    $this->errors[] = 'Baris '.($index + 2).': NISN harus 10 digit.';

                    continue;
                }
                $jenis_kelamin = 'L';
                $jkVal = strtolower($row['jenis_kelamin'] ?? '');
                if (! empty($jkVal)) {
                    if (in_array($jkVal, ['laki-laki', 'laki laki', 'pria', 'l', 'lk'])) {
                        $jenis_kelamin = 'L';
                    } elseif (in_array($jkVal, ['perempuan', 'wanita', 'p', 'pr'])) {
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

                $nik = trim($row['nik'] ?? '');
                $nis = trim($row['nis'] ?? '');
                $no_akte = trim($row['no_akte'] ?? '');
                $no_kk = trim($row['no_kk'] ?? '');
                $tempat_lahir = trim($row['tempat_lahir'] ?? '');

                $tanggal_lahir = trim($row['tanggal_lahir'] ?? '');
                if (is_numeric($tanggal_lahir)) {
                    $tanggal_lahir = Date::excelToDateTimeObject($tanggal_lahir)->format('Y-m-d');
                } elseif (! empty($tanggal_lahir)) {
                    $tanggal_lahir = date('Y-m-d', strtotime($tanggal_lahir));
                } else {
                    $tanggal_lahir = null;
                }

                $lembaga_id = trim($row['lembaga_id'] ?? '') ?: null;
                $alamat = trim($row['alamat'] ?? '');
                $telepon = trim($row['telepon'] ?? '');
                $ayah = trim($row['ayah'] ?? '');
                $ibu = trim($row['ibu'] ?? '');

                // Check jika NISN sudah ada (Validasi UPSERT manual)
                $existingSiswa = null;
                if (! empty($nisn) && strlen($nisn) === 10) {
                    $existingSiswa = Siswa::where('nisn', $nisn)->first();
                }

                $data = [
                    'nama' => $nama,
                    'nisn' => $nisn,
                    'jenis_kelamin' => $jenis_kelamin,
                    'status' => 1,
                    'rombel_id' => $rombel_id,
                    'nik' => $nik,
                    'nis' => $nis,
                    'no_akte' => $no_akte,
                    'no_kk' => $no_kk,
                    'tempat_lahir' => $tempat_lahir,
                    'tanggal_lahir' => $tanggal_lahir,
                    'lembaga_id' => $lembaga_id,
                    'alamat' => $alamat,
                    'telepon' => $telepon,
                    'ayah' => $ayah,
                    'ibu' => $ibu,
                ];

                if ($existingSiswa) {
                    $updateData = [];
                    foreach ($data as $key => $value) {
                        if ($value !== '' && $value !== null) {
                            $updateData[$key] = $value;
                        }
                    }

                    // Biarkan status tetap seperti aslinya jika di DB status > 1
                    if ($existingSiswa->status > 1) {
                        unset($updateData['status']);
                    }

                    $existingSiswa->update($updateData);
                    $siswa = $existingSiswa;
                    $action = 'updated';
                } else {
                    $siswa = Siswa::create($data);
                    $action = 'created';
                }

                // Handle Tags
                if (! empty($tagString)) {
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
                $this->errors[] = 'Baris '.($index + 2).': Error tidak dapat diproses. '.$e->getMessage();
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
