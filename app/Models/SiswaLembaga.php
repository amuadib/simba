<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiswaLembaga extends Model
{
    use HasUuids;

    protected $table = 'siswa_lembaga';

    protected $fillable = [
        'siswa_id',
        'lembaga_id',
        'rombel_id',
        'nis_lokal',
        'is_induk',
        'status',
        'tmt_masuk',
        'tmt_keluar',
        'keterangan',
    ];

    protected $casts = [
        'lembaga_id' => 'integer',
        'is_induk' => 'boolean',
        'status' => 'integer',
        'tmt_masuk' => 'date',
        'tmt_keluar' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (SiswaLembaga $sl) {
            // Jika status bukan aktif (lulus, mutasi, non-aktif, almarhum, keluar) dan tmt_keluar kosong, otomatis isi hari ini
            if (in_array((int) $sl->status, [2, 3, 4, 5, 6]) && empty($sl->tmt_keluar)) {
                $sl->tmt_keluar = now()->toDateString();
            }
        });

        static::saved(function (SiswaLembaga $sl) {
            if (empty($sl->siswa_id)) {
                return;
            }

            // Jika dijadikan keanggotaan induk, selaraskan is_induk record lain dan lembaga_id & rombel_id siswa
            if ($sl->is_induk) {
                static::withoutEvents(function () use ($sl) {
                    static::where('siswa_id', $sl->siswa_id)
                        ->where('id', '!=', $sl->id)
                        ->where('is_induk', true)
                        ->update(['is_induk' => false]);
                });

                Siswa::withoutEvents(function () use ($sl) {
                    $updateData = ['lembaga_id' => $sl->lembaga_id];
                    if (!empty($sl->rombel_id)) {
                        $updateData['rombel_id'] = $sl->rombel_id;
                    }
                    Siswa::where('id', $sl->siswa_id)->update($updateData);
                });
            }
        });

        static::deleted(function (SiswaLembaga $sl) {
            if ($sl->is_induk && !empty($sl->siswa_id)) {
                // Cari keanggotaan lain yang masih ada untuk dipromosikan menjadi induk
                $nextInduk = static::where('siswa_id', $sl->siswa_id)
                    ->orderByRaw("CASE WHEN status = 1 THEN 0 ELSE 1 END")
                    ->orderBy('created_at')
                    ->first();

                if ($nextInduk) {
                    $nextInduk->update(['is_induk' => true]);
                }
            }
        });
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }

    public function getNamaLembagaAttribute(): string
    {
        return function_exists('nama_lembaga') ? nama_lembaga($this->lembaga_id) : (string) $this->lembaga_id;
    }

    public function getSingkatanLembagaAttribute(): string
    {
        return function_exists('nama_lembaga') ? nama_lembaga($this->lembaga_id, true) : (string) $this->lembaga_id;
    }

    public function getStatusLabelAttribute(): string
    {
        return function_exists('status_siswa') ? status_siswa($this->status) : (string) $this->status;
    }
}
