<?php

namespace App\Imports;

use App\Models\Pegawai;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class PegawaiImport implements ToCollection, WithHeadingRow
{
    protected $rowsProcessed = 0;
    protected $rowsSuccess = 0;
    protected $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $this->rowsProcessed++;

            try {
                $nama = trim($row['nama'] ?? '');
                if (empty($nama)) {
                    $this->errors[] = 'Baris ' . ($index + 2) . ': Nama wajib diisi.';
                    continue;
                }

                $jenis_kelamin = 'l';
                $jkVal = strtolower(trim($row['jenis_kelamin_lp'] ?? $row['jenis_kelamin'] ?? ''));
                if (!empty($jkVal)) {
                    if (in_array($jkVal, ['laki-laki', 'laki laki', 'pria', 'l', 'lk'])) {
                        $jenis_kelamin = 'l';
                    } elseif (in_array($jkVal, ['perempuan', 'wanita', 'p', 'pr'])) {
                        $jenis_kelamin = 'p';
                    }
                }

                $niy = trim((string) ($row['niy'] ?? '')) ?: null;
                $nuptk = trim((string) ($row['nuptk'] ?? '')) ?: null;
                $nik = trim((string) ($row['nik'] ?? '')) ?: null;
                $gelar_depan = trim($row['gelar_depan'] ?? '') ?: null;
                $gelar_belakang = trim($row['gelar_belakang'] ?? '') ?: null;
                $tempat_lahir = trim($row['tempat_lahir'] ?? '') ?: null;
                $alamat = trim($row['alamat'] ?? '') ?: null;
                $no_hp = trim((string) ($row['no_hp'] ?? '')) ?: null;

                $tmt = trim($row['tmt_yyyy_mm_dd'] ?? $row['tmt'] ?? '');
                if (is_numeric($tmt)) {
                    $tmt = Date::excelToDateTimeObject($tmt)->format('Y-m-d');
                } elseif (!empty($tmt)) {
                    $tmt = date('Y-m-d', strtotime($tmt));
                } else {
                    $tmt = null;
                }

                $tanggal_lahir = trim($row['tanggal_lahir_yyyy_mm_dd'] ?? $row['tanggal_lahir'] ?? '');
                if (is_numeric($tanggal_lahir)) {
                    $tanggal_lahir = Date::excelToDateTimeObject($tanggal_lahir)->format('Y-m-d');
                } elseif (!empty($tanggal_lahir)) {
                    $tanggal_lahir = date('Y-m-d', strtotime($tanggal_lahir));
                } else {
                    $tanggal_lahir = null;
                }

                $status = trim((string) ($row['status_1aktif_2cuti_3non_aktif_4mutasi_5pensiun_6keluar_7almarhum'] ?? $row['status_1aktif_2cuti_3non_aktif_4mutasi_5pensiun_6keluar'] ?? $row['status'] ?? '1')) ?: '1';

                $lembagaRaw = trim((string) ($row['lembaga_id'] ?? $row['lembaga'] ?? ''));
                $lembaga_id = 99;
                if (is_numeric($lembagaRaw) && (int) $lembagaRaw > 0) {
                    $lembaga_id = (int) $lembagaRaw;
                } elseif (!empty($lembagaRaw)) {
                    foreach (daftar_lembaga() as $lid => $lname) {
                        if (stripos($lname, $lembagaRaw) !== false || stripos($lembagaRaw, (string) $lname) !== false) {
                            $lembaga_id = $lid;
                            break;
                        }
                    }
                }

                // Cari apakah ada pegawai dengan NIY yang sama (jika NIY ada)
                $pegawai = null;
                if ($niy) {
                    $pegawai = Pegawai::where('niy', $niy)->first();
                } else {
                    $niy = Pegawai::generateNiy($tmt, $jenis_kelamin);
                }

                $payload = [
                    'nama' => $nama,
                    'niy' => $niy,
                    'nuptk' => $nuptk,
                    'gelar_depan' => $gelar_depan,
                    'gelar_belakang' => $gelar_belakang,
                    'jenis_kelamin' => $jenis_kelamin,
                    'nik' => $nik,
                    'tempat_lahir' => $tempat_lahir,
                    'tanggal_lahir' => $tanggal_lahir,
                    'tmt' => $tmt,
                    'alamat' => $alamat,
                    'no_hp' => $no_hp,
                    'lembaga_id' => $lembaga_id,
                    'status' => $status,
                ];

                if ($pegawai) {
                    $pegawai->update($payload);
                } else {
                    $pegawai = Pegawai::create($payload);
                }

                if ($pegawai && $lembaga_id) {
                    $existingSk = $pegawai->skPenugasan()->where('lembaga_id', $lembaga_id)->first();
                    if (!$existingSk) {
                        $hasInduk = $pegawai->skPenugasan()->where('is_induk', true)->exists();

                        $jabatanRaw = trim((string) ($row['jabatan'] ?? $row['jabatan_id'] ?? ''));
                        $jabatanId = null;
                        if (is_numeric($jabatanRaw) && (int) $jabatanRaw >= 1 && (int) $jabatanRaw <= 13) {
                            $jabatanId = (int) $jabatanRaw;
                        } elseif (!empty($jabatanRaw)) {
                            foreach (daftar_jabatan() as $jid => $jname) {
                                if (stripos($jname, $jabatanRaw) !== false || stripos($jabatanRaw, $jname) !== false) {
                                    $jabatanId = $jid;
                                    break;
                                }
                            }
                        }

                        if (!$jabatanId) {
                            $jabatanId = ($lembaga_id == 1) ? 3 : (($lembaga_id == 99) ? 12 : 4);
                        }

                        $pegawai->skPenugasan()->create([
                            'lembaga_id' => $lembaga_id,
                            'tmt_tugas' => $tmt,
                            'jabatan' => $jabatanId,
                            'is_induk' => !$hasInduk,
                            'status' => '1',
                        ]);
                    }
                }

                $this->rowsSuccess++;
            } catch (\Exception $e) {
                $this->errors[] = 'Baris ' . ($index + 2) . ': ' . $e->getMessage();
            }
        }
    }

    public function getResults(): array
    {
        return [
            'processed' => $this->rowsProcessed,
            'success' => $this->rowsSuccess,
            'errors' => $this->errors,
        ];
    }
}
