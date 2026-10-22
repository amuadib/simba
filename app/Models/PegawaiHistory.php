<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PegawaiHistory extends Model
{
    protected $table = 'pegawai_histories';

    protected $fillable = [
        'pegawai_id',
        'user_id',
        'action',
        'old_values',
        'new_values',
        'changed_fields',
        'description',
        'is_reverted',
        'reverted_at',
        'reverted_by',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_fields' => 'array',
        'is_reverted' => 'boolean',
        'reverted_at' => 'datetime',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reverter()
    {
        return $this->belongsTo(User::class, 'reverted_by');
    }

    /**
     * Map field name to human readable Indonesian label.
     */
    public static function formatFieldName(string $field): string
    {
        return match ($field) {
            'nama' => 'Nama Lengkap',
            'niy' => 'NIY',
            'nuptk' => 'NUPTK',
            'tmt' => 'TMT (Mulai Bertugas)',
            'jenis_kelamin' => 'Jenis Kelamin',
            'tempat_lahir' => 'Tempat Lahir',
            'tanggal_lahir' => 'Tanggal Lahir',
            'nik' => 'NIK',
            'alamat' => 'Alamat',
            'no_hp' => 'No. HP / WA',
            'gelar_depan' => 'Gelar Depan',
            'gelar_belakang' => 'Gelar Belakang',
            'foto' => 'Foto Pegawai',
            'lembaga_id' => 'Lembaga',
            'status' => 'Status',
            'nomor_sk' => 'Nomor SK',
            'tanggal_sk' => 'Tanggal SK',
            'tmt_tugas' => 'TMT Tugas',
            'tmt_selesai' => 'TMT Selesai',
            'jabatan' => 'Jabatan',
            'is_induk' => 'Lembaga Induk (Satminkal)',
            'keterangan' => 'Keterangan SK',
            'file_sk' => 'Berkas Dokumen SK',
            default => ucwords(str_replace('_', ' ', $field)),
        };
    }

    /**
     * Format a value into a human readable string.
     */
    public static function formatFieldValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return '<em class="text-slate-400 dark:text-slate-500">(kosong)</em>';
        }

        if ($field === 'is_induk') {
            return $value ? 'Ya (Induk)' : 'Bukan Induk';
        }

        if ($field === 'jabatan') {
            return function_exists('nama_jabatan') ? nama_jabatan($value) : (string) $value;
        }

        if ($field === 'file_sk') {
            return $value ? basename($value) : '<em class="text-slate-400 dark:text-slate-500">(tidak ada file)</em>';
        }

        if ($field === 'status') {
            if ($value === '0' || $value === 0) {
                return 'Non-Aktif / Selesai';
            }
            $statuses = config('local.status_pegawai', [
                1 => 'Aktif',
                2 => 'Cuti',
                3 => 'Non-Aktif',
                4 => 'Mutasi',
                5 => 'Pensiun',
                6 => 'Keluar',
            ]);

            return $statuses[$value] ?? (string) $value;
        }

        if ($field === 'jenis_kelamin') {
            $v = strtolower((string) $value);
            if ($v === 'l') {
                return 'Laki-laki (L)';
            }
            if ($v === 'p') {
                return 'Perempuan (P)';
            }

            return (string) $value;
        }

        if ($field === 'lembaga_id') {
            return function_exists('nama_lembaga') ? nama_lembaga($value) : (string) $value;
        }

        if (in_array($field, ['tanggal_lahir', 'tmt', 'tanggal_sk', 'tmt_tugas', 'tmt_selesai']) && $value) {
            try {
                return Carbon::parse($value)->translatedFormat('d F Y');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) $value;
    }
}
