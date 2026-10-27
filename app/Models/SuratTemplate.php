<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuratTemplate extends Model
{
    protected $table = 'surat_templates';

    protected $fillable = [
        'nama',
        'kode_surat',
        'lembaga_id',
        'deskripsi',
        'konten',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'lembaga_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function surats(): HasMany
    {
        return $this->hasMany(Surat::class, 'surat_template_id');
    }

    public function getNamaLembagaAttribute(): string
    {
        return $this->lembaga_id ? nama_lembaga($this->lembaga_id) : 'Semua Lembaga';
    }

    public function getNamaKodeSuratAttribute(): string
    {
        $codes = daftar_kode_surat();
        return $codes[$this->kode_surat] ?? $this->kode_surat;
    }
}
