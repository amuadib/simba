<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SkPenugasan extends Model
{
    use HasUuids;

    protected $table = 'sk_penugasan';

    protected $fillable = [
        'pegawai_id',
        'lembaga_id',
        'nomor_sk',
        'tanggal_sk',
        'tmt_tugas',
        'tmt_selesai',
        'jabatan',
        'is_induk',
        'status',
        'file_sk',
        'keterangan',
    ];

    protected $casts = [
        'jabatan' => 'integer',
        'tanggal_sk' => 'date',
        'tmt_tugas' => 'date',
        'tmt_selesai' => 'date',
        'is_induk' => 'boolean',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    /**
     * Get nama jabatan penugasan dari config.
     */
    public function getNamaJabatanAttribute(): string
    {
        return nama_jabatan($this->jabatan);
    }

    /**
     * Get nama lengkap lembaga penugasan.
     */
    public function getNamaLembagaAttribute(): string
    {
        return nama_lembaga($this->lembaga_id);
    }

    /**
     * Get singkatan lembaga penugasan.
     */
    public function getSingkatanLembagaAttribute(): string
    {
        return nama_lembaga($this->lembaga_id, true);
    }

    /**
     * Get label status penugasan.
     */
    public function getStatusLabelAttribute(): string
    {
        return (string) $this->status === '1' ? 'Aktif' : 'Non-Aktif / Selesai';
    }

    /**
     * Get URL file SK penugasan jika ada.
     */
    public function getFileUrlAttribute(): ?string
    {
        if ($this->file_sk && Storage::disk('public')->exists($this->file_sk)) {
            return asset('storage/' . $this->file_sk);
        }
        return null;
    }
}
