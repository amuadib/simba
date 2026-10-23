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

    /**
     * Optional flag to suppress SK history recording when needed.
     */
    public static bool $suppressHistory = false;

    protected static function booted(): void
    {
        static::saving(function (SkPenugasan $sk) {
            // Jika status penugasan diset 0 (selesai) dan tmt_selesai belum diisi, isi otomatis tanggal hari ini
            if ((string) $sk->status === '0' && empty($sk->tmt_selesai)) {
                $sk->tmt_selesai = now()->toDateString();
            }
        });

        static::saved(function (SkPenugasan $sk) {
            if (empty($sk->pegawai_id)) {
                return;
            }

            // Jika dijadikan penugasan induk, selaraskan is_induk SK lain dan lembaga_id pegawai
            if ($sk->is_induk) {
                static::withoutEvents(function () use ($sk) {
                    static::where('pegawai_id', $sk->pegawai_id)
                        ->where('id', '!=', $sk->id)
                        ->where('is_induk', true)
                        ->update(['is_induk' => false]);
                });

                Pegawai::where('id', $sk->pegawai_id)
                    ->where('lembaga_id', '!=', $sk->lembaga_id)
                    ->update(['lembaga_id' => $sk->lembaga_id]);
            }

            // Jika SK penugasan diubah menjadi aktif (1) dan status pegawai saat ini Non-Aktif (3), otomatis aktifkan pegawai
            if ($sk->wasChanged('status') && (string) $sk->status === '1') {
                $pegawai = Pegawai::find($sk->pegawai_id);
                if ($pegawai && (int) $pegawai->status === 3) {
                    $pegawai->update(['status' => '1']);
                }
            }
        });

        static::created(function (SkPenugasan $sk) {
            if (static::$suppressHistory || empty($sk->pegawai_id)) {
                return;
            }

            $attributes = $sk->getAttributes();
            unset($attributes['created_at'], $attributes['updated_at']);

            $lembaga = function_exists('nama_lembaga') ? nama_lembaga($sk->lembaga_id, true) : 'Lembaga #' . $sk->lembaga_id;
            $noSk = $sk->nomor_sk ? ' (No: ' . $sk->nomor_sk . ')' : '';

            $userId = (auth()->check() && \App\Models\User::where('id', auth()->id())->exists()) ? auth()->id() : null;

            PegawaiHistory::create([
                'pegawai_id' => $sk->pegawai_id,
                'user_id' => $userId,
                'action' => 'sk_create',
                'old_values' => null,
                'new_values' => $attributes,
                'changed_fields' => array_keys($attributes),
                'description' => "Penambahan SK Penugasan: {$lembaga}{$noSk}",
            ]);
        });

        static::updating(function (SkPenugasan $sk) {
            if (static::$suppressHistory || empty($sk->pegawai_id)) {
                return;
            }

            $dirty = $sk->getDirty();
            unset($dirty['updated_at']);

            if (empty($dirty)) {
                return;
            }

            $oldValues = [];
            $newValues = [];
            $changedFields = [];

            foreach ($dirty as $key => $newValue) {
                $oldValue = $sk->getOriginal($key);
                if ((string)$oldValue !== (string)$newValue) {
                    $oldValues[$key] = $oldValue;
                    $newValues[$key] = $newValue;
                    $changedFields[] = $key;
                }
            }

            if (empty($changedFields)) {
                return;
            }

            $oldValues['sk_id'] = $sk->id;
            $newValues['sk_id'] = $sk->id;

            $lembaga = function_exists('nama_lembaga') ? nama_lembaga($sk->lembaga_id, true) : 'Lembaga #' . $sk->lembaga_id;
            $fieldLabels = array_map(fn($f) => PegawaiHistory::formatFieldName($f), $changedFields);

            $userId = (auth()->check() && \App\Models\User::where('id', auth()->id())->exists()) ? auth()->id() : null;

            PegawaiHistory::create([
                'pegawai_id' => $sk->pegawai_id,
                'user_id' => $userId,
                'action' => 'sk_update',
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => $changedFields,
                'description' => "Pembaruan SK Penugasan {$lembaga} (" . implode(', ', $fieldLabels) . ")",
            ]);
        });

        static::deleted(function (SkPenugasan $sk) {
            if (!empty($sk->pegawai_id) && $sk->is_induk) {
                $another = static::where('pegawai_id', $sk->pegawai_id)->first();
                if ($another) {
                    $another->update(['is_induk' => true]);
                    Pegawai::where('id', $sk->pegawai_id)->update(['lembaga_id' => $another->lembaga_id]);
                }
            }

            if (static::$suppressHistory || empty($sk->pegawai_id)) {
                return;
            }

            if (!Pegawai::where('id', $sk->pegawai_id)->exists()) {
                return;
            }

            $attributes = $sk->getAttributes();
            unset($attributes['created_at'], $attributes['updated_at']);
            $attributes['sk_id'] = $sk->id;

            $lembaga = function_exists('nama_lembaga') ? nama_lembaga($sk->lembaga_id, true) : 'Lembaga #' . $sk->lembaga_id;
            $noSk = $sk->nomor_sk ? ' (No: ' . $sk->nomor_sk . ')' : '';

            $userId = (auth()->check() && \App\Models\User::where('id', auth()->id())->exists()) ? auth()->id() : null;

            PegawaiHistory::create([
                'pegawai_id' => $sk->pegawai_id,
                'user_id' => $userId,
                'action' => 'sk_delete',
                'old_values' => $attributes,
                'new_values' => null,
                'changed_fields' => array_keys($attributes),
                'description' => "Penghapusan SK Penugasan: {$lembaga}{$noSk}",
            ]);
        });
    }

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
