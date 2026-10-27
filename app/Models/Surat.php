<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Surat extends Model
{
    protected $table = 'surats';

    protected $fillable = [
        'lembaga_id',
        'nomor_surat',
        'nomor_urut',
        'kode_surat',
        'singkatan_lembaga',
        'bulan_romawi',
        'tahun',
        'tanggal_surat',
        'perihal',
        'tujuan',
        'jenis_pembuatan',
        'surat_template_id',
        'konten',
        'file_path',
        'target_type',
        'target_id',
        'keterangan',
        'user_id',
    ];

    protected $casts = [
        'lembaga_id'    => 'integer',
        'nomor_urut'    => 'integer',
        'tahun'         => 'integer',
        'tanggal_surat' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SuratTemplate::class, 'surat_template_id');
    }

    public function targetSiswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'target_id');
    }

    public function targetPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'target_id');
    }

    public function getFileUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return str_starts_with($this->file_path, 'http')
            ? $this->file_path
            : Storage::url($this->file_path);
    }

    public function getNamaLembagaAttribute(): string
    {
        return nama_lembaga($this->lembaga_id);
    }

    public function getNamaKodeSuratAttribute(): string
    {
        $codes = daftar_kode_surat();
        return $codes[$this->kode_surat] ?? $this->kode_surat;
    }

    /**
     * Get the next sequence number for a given lembaga and year
     */
    public static function getNextNomorUrut($lembagaId, $tanggal = null): int
    {
        $date = $tanggal ? Carbon::parse($tanggal) : now();
        $year = (int) $date->year;

        $maxUrut = static::where('lembaga_id', $lembagaId)
            ->where('tahun', $year)
            ->max('nomor_urut') ?? 0;

        return (int) $maxUrut + 1;
    }

    /**
     * Format letter number based on components
     */
    public static function formatNomorSurat($nomorUrut, $kodeSurat, $lembagaId, $tanggal = null): string
    {
        $date = $tanggal ? Carbon::parse($tanggal) : now();
        $year = (int) $date->year;
        $month = (int) $date->month;
        $bulanRomawi = bulan_romawi($month);

        $singkatan = nama_lembaga($lembagaId, true);
        if (empty($singkatan) || $singkatan === '-') {
            $singkatan = 'LEMBAGA';
        }

        $nomorUrutPadded = str_pad((int) $nomorUrut, 3, '0', STR_PAD_LEFT);

        return "{$nomorUrutPadded}/{$kodeSurat}/{$singkatan}/{$bulanRomawi}/{$year}";
    }

    /**
     * Generate metadata and formatted string for a letter number
     * Format: <nomor_urut_surat>/<kode_surat_di_config>/<singkatan_lembaga>/<bulan_romawi>/<tahun>
     */
    public static function generateNomorSurat($lembagaId, $kodeSurat, $tanggal = null, $customUrut = null): array
    {
        $date = $tanggal ? Carbon::parse($tanggal) : now();
        $year = (int) $date->year;
        $month = (int) $date->month;
        $bulanRomawi = bulan_romawi($month);

        $singkatan = nama_lembaga($lembagaId, true);
        if (empty($singkatan) || $singkatan === '-') {
            $singkatan = 'LEMBAGA';
        }

        $nomorUrut = $customUrut !== null && (int) $customUrut > 0
            ? (int) $customUrut
            : static::getNextNomorUrut($lembagaId, $tanggal);

        $nomorUrutPadded = str_pad($nomorUrut, 3, '0', STR_PAD_LEFT);
        $nomorSurat = "{$nomorUrutPadded}/{$kodeSurat}/{$singkatan}/{$bulanRomawi}/{$year}";

        return [
            'nomor_surat'       => $nomorSurat,
            'nomor_urut'        => $nomorUrut,
            'nomor_urut_padded' => $nomorUrutPadded,
            'kode_surat'        => $kodeSurat,
            'singkatan_lembaga' => $singkatan,
            'bulan_romawi'      => $bulanRomawi,
            'tahun'             => $year,
        ];
    }

    /**
     * Replace tags in content with actual data
     */
    public static function renderTags(string $templateContent, array $tags = []): string
    {
        $search = [];
        $replace = [];

        foreach ($tags as $key => $value) {
            $cleanKey = trim($key, '{}');
            $search[] = '{' . $cleanKey . '}';
            $search[] = '{' . strtoupper($cleanKey) . '}';
            $search[] = '{' . strtolower($cleanKey) . '}';
            $replace[] = (string) $value;
            $replace[] = (string) $value;
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $templateContent);
    }
}
