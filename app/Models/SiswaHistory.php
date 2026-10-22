<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiswaHistory extends Model
{
    protected $table = 'siswa_histories';

    protected $fillable = [
        'siswa_id',
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

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
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
            'panggilan' => 'Nama Panggilan',
            'jenis_kelamin' => 'Jenis Kelamin',
            'nisn' => 'NISN',
            'nis' => 'NIS',
            'nik' => 'NIK',
            'no_akte' => 'No. Akta Kelahiran',
            'no_kk' => 'No. Kartu Keluarga',
            'tempat_lahir' => 'Tempat Lahir',
            'tanggal_lahir' => 'Tanggal Lahir',
            'status' => 'Status Siswa',
            'rombel_id' => 'Kelas / Rombel',
            'lembaga_id' => 'Lembaga',
            'alamat' => 'Alamat',
            'telepon' => 'Telepon / HP',
            'ayah' => 'Nama Ayah',
            'ibu' => 'Nama Ibu',
            'foto' => 'Foto Siswa',
            'tags' => 'Tag Siswa',
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

        if ($field === 'tags') {
            $tags = is_array($value) ? $value : explode(',', (string) $value);
            $badges = array_map(function($t) {
                $t = trim((string)$t);
                if ($t === '') return '';
                return '<span class="badge bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-600 font-normal me-1" style="font-size: 0.7rem;">' . e($t) . '</span>';
            }, $tags);
            $rendered = implode('', array_filter($badges));
            return $rendered ?: '<em class="text-slate-400 dark:text-slate-500">(tidak ada tag)</em>';
        }

        if ($field === 'status') {
            $statuses = config('local.status_siswa', []);
            return $statuses[$value] ?? (string) $value;
        }

        if ($field === 'rombel_id') {
            $rombel = Rombel::find($value);
            return $rombel ? $rombel->nama : (string) $value;
        }

        if ($field === 'jenis_kelamin') {
            $v = strtolower((string) $value);
            if ($v === 'l') return 'Laki-laki (L)';
            if ($v === 'p') return 'Perempuan (P)';
            return (string) $value;
        }

        if ($field === 'lembaga_id') {
            return function_exists('nama_lembaga') ? nama_lembaga($value) : (string) $value;
        }

        if ($field === 'tanggal_lahir' && $value) {
            try {
                return \Carbon\Carbon::parse($value)->translatedFormat('d F Y');
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
