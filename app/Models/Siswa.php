<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\SiswaLembaga;
use App\Models\User;

class Siswa extends Model
{
    use HasUuids;
    protected $table = 'siswa';

    protected $fillable = [
        'nama',
        'nisn',
        'status',
        'rombel_id',
        'panggilan',
        'jenis_kelamin',
        'nik',
        'nis',
        'no_akte',
        'no_kk',
        'tempat_lahir',
        'tanggal_lahir',
        'lembaga_id',
        'alamat',
        'telepon',
        'ayah',
        'ibu',
        'foto',
        'user_id',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    /**
     * Optional flag to suppress history recording if needed.
     */
    public bool $suppressHistory = false;

    /**
     * Optional custom action and description for the next history entry.
     */
    public ?string $historyAction = null;
    public ?string $historyDescription = null;

    /**
     * Reference to the last created history entry in the current lifecycle.
     */
    public ?SiswaHistory $lastHistory = null;

    protected static function booted()
    {
        static::created(function (Siswa $siswa) {
            // Auto-create initial keanggotaan lembaga if lembaga_id is set
            if (!empty($siswa->lembaga_id)) {
                SiswaLembaga::withoutEvents(function () use ($siswa) {
                    SiswaLembaga::firstOrCreate([
                        'siswa_id' => $siswa->id,
                        'lembaga_id' => (int) $siswa->lembaga_id,
                    ], [
                        'rombel_id' => $siswa->rombel_id,
                        'nis_lokal' => $siswa->nis,
                        'is_induk' => true,
                        'status' => (int) ($siswa->status ?? 1),
                        'tmt_masuk' => now()->toDateString(),
                        'keterangan' => 'Keanggotaan awal saat pendaftaran siswa',
                    ]);
                });
            }

            if ($siswa->suppressHistory) {
                return;
            }

            $attributes = $siswa->getAttributes();
            unset($attributes['created_at'], $attributes['updated_at']);

            $userId = (auth()->check() && User::where('id', auth()->id())->exists()) ? auth()->id() : null;

            $history = SiswaHistory::create([
                'siswa_id' => $siswa->id,
                'user_id' => $userId,
                'action' => $siswa->historyAction ?? 'create',
                'old_values' => null,
                'new_values' => $attributes,
                'changed_fields' => array_keys($attributes),
                'description' => $siswa->historyDescription ?? 'Data siswa baru ditambahkan',
            ]);
            $siswa->lastHistory = $history;
            $siswa->historyAction = null;
            $siswa->historyDescription = null;
        });

        static::updating(function (Siswa $siswa) {
            if ($siswa->suppressHistory) {
                return;
            }

            $dirty = $siswa->getDirty();
            unset($dirty['updated_at']);

            if (empty($dirty)) {
                return;
            }

            $oldValues = [];
            $newValues = [];
            $changedFields = [];

            foreach ($dirty as $key => $newValue) {
                $oldValue = $siswa->getOriginal($key);
                if ((string)$oldValue !== (string)$newValue) {
                    $oldValues[$key] = $oldValue;
                    $newValues[$key] = $newValue;
                    $changedFields[] = $key;
                }
            }

            if (empty($changedFields)) {
                return;
            }

            $action = $siswa->historyAction ?? 'update';
            $description = $siswa->historyDescription;
            if (!$description) {
                $fieldNames = array_map(fn($f) => SiswaHistory::formatFieldName($f), $changedFields);
                $description = 'Pembaruan data siswa (' . implode(', ', $fieldNames) . ')';
            }

            $userId = (auth()->check() && User::where('id', auth()->id())->exists()) ? auth()->id() : null;

            $history = SiswaHistory::create([
                'siswa_id' => $siswa->id,
                'user_id' => $userId,
                'action' => $action,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => $changedFields,
                'description' => $description,
            ]);
            $siswa->lastHistory = $history;
            $siswa->historyAction = null;
            $siswa->historyDescription = null;
        });

        static::saved(function (Siswa $siswa) {
            if ($siswa->wasChanged('lembaga_id') && !empty($siswa->lembaga_id)) {
                SiswaLembaga::withoutEvents(function () use ($siswa) {
                    $membership = SiswaLembaga::where('siswa_id', $siswa->id)
                        ->where('lembaga_id', (int) $siswa->lembaga_id)
                        ->first();

                    if ($membership) {
                        SiswaLembaga::where('siswa_id', $siswa->id)
                            ->where('id', '!=', $membership->id)
                            ->update(['is_induk' => false]);

                        $membership->update([
                            'is_induk' => true,
                            'rombel_id' => $siswa->rombel_id ?: $membership->rombel_id,
                        ]);
                    } else {
                        SiswaLembaga::where('siswa_id', $siswa->id)->update(['is_induk' => false]);
                        SiswaLembaga::create([
                            'siswa_id' => $siswa->id,
                            'lembaga_id' => (int) $siswa->lembaga_id,
                            'rombel_id' => $siswa->rombel_id,
                            'nis_lokal' => $siswa->nis,
                            'is_induk' => true,
                            'status' => (int) ($siswa->status ?? 1),
                            'tmt_masuk' => now()->toDateString(),
                        ]);
                    }
                });
            }
        });
    }

    public function histories()
    {
        return $this->hasMany(SiswaHistory::class)->orderBy('id', 'desc');
    }

    /**
     * Sync tags and record or merge into history.
     */
    public function syncTagsWithHistory(array $newTagIds, ?string $action = 'update', ?string $description = null): bool
    {
        if ($this->suppressHistory) {
            $this->tags()->sync($newTagIds);
            return false;
        }

        // Get current tag names before sync
        $oldTagNames = $this->tags()->pluck('nama')->sort()->values()->toArray();

        // Perform the sync
        $this->tags()->sync($newTagIds);

        // Get new tag names after sync
        $this->unsetRelation('tags');
        $newTagNames = $this->tags()->pluck('nama')->sort()->values()->toArray();

        // If no change in tags, do nothing
        if ($oldTagNames === $newTagNames) {
            return false;
        }

        // If a history entry was created during this request
        if ($this->lastHistory && in_array($this->lastHistory->action, [$action, 'update', 'create', 'bulk_update'])) {
            $oldVals = $this->lastHistory->old_values ?? [];
            $newVals = $this->lastHistory->new_values ?? [];
            $fields = $this->lastHistory->changed_fields ?? [];

            $oldVals['tags'] = $oldTagNames;
            $newVals['tags'] = $newTagNames;
            if (!in_array('tags', $fields)) {
                $fields[] = 'tags';
            }

            $desc = $this->lastHistory->description;
            if ($desc && !str_contains($desc, 'Tag Siswa')) {
                $desc .= ', Tag Siswa';
            }

            $this->lastHistory->update([
                'old_values' => $oldVals,
                'new_values' => $newVals,
                'changed_fields' => $fields,
                'description' => $desc,
            ]);

            return true;
        }

        // Otherwise create a new history entry specifically for tag changes
        $desc = $description ?? 'Pembaruan tag siswa';
        $history = SiswaHistory::create([
            'siswa_id' => $this->id,
            'user_id' => auth()->id(),
            'action' => $this->historyAction ?? $action,
            'old_values' => ['tags' => $oldTagNames],
            'new_values' => ['tags' => $newTagNames],
            'changed_fields' => ['tags'],
            'description' => $this->historyDescription ?? $desc,
        ]);
        $this->lastHistory = $history;
        $this->historyAction = null;
        $this->historyDescription = null;

        return true;
    }

    /**
     * Rollback a specific history entry.
     */
    public function rollbackHistory(SiswaHistory|int $history): bool
    {
        if (is_numeric($history)) {
            $history = SiswaHistory::where('siswa_id', $this->id)->findOrFail($history);
        }

        if ($history->is_reverted || empty($history->old_values)) {
            return false;
        }

        $oldValues = $history->old_values;
        $tagNamesToRestore = null;

        // Check if tags are part of this history
        if (array_key_exists('tags', $oldValues)) {
            $tagNamesToRestore = $oldValues['tags'] ?? [];
            unset($oldValues['tags']);
        }

        $currentTags = $this->tags()->pluck('nama')->sort()->values()->toArray();

        // Restore tags if present
        if ($tagNamesToRestore !== null) {
            $tagIds = [];
            foreach ($tagNamesToRestore as $tagName) {
                $tagName = trim($tagName);
                if (!empty($tagName)) {
                    $tag = Tag::firstOrCreate(['nama' => $tagName]);
                    $tagIds[] = $tag->id;
                }
            }
            $this->tags()->sync($tagIds);

            // If only tags were in this history entry (no attribute updates needed)
            if (empty($oldValues)) {
                $this->historyAction = 'rollback';
                $this->historyDescription = 'Undo perubahan tag siswa';

                SiswaHistory::create([
                    'siswa_id' => $this->id,
                    'user_id' => auth()->id(),
                    'action' => 'rollback',
                    'old_values' => ['tags' => $currentTags],
                    'new_values' => ['tags' => $tagNamesToRestore],
                    'changed_fields' => ['tags'],
                    'description' => 'Undo perubahan tag siswa',
                ]);

                $this->historyAction = null;
                $this->historyDescription = null;

                $history->update([
                    'is_reverted' => true,
                    'reverted_at' => now(),
                    'reverted_by' => auth()->id(),
                ]);

                return true;
            }
        }

        // Restore attributes
        $this->historyAction = 'rollback';
        $fieldNames = array_map(fn($f) => SiswaHistory::formatFieldName($f), array_keys($oldValues));
        if ($tagNamesToRestore !== null) {
            $fieldNames[] = 'Tag Siswa';
        }
        $this->historyDescription = 'Undo perubahan #' . $history->id . ' (' . implode(', ', $fieldNames) . ')';

        $this->update($oldValues);

        $this->historyAction = null;
        $this->historyDescription = null;

        // If tags were also restored along with attributes, merge tags into the newly created rollback history
        if ($tagNamesToRestore !== null && $this->lastHistory) {
            $oldVals = $this->lastHistory->old_values ?? [];
            $newVals = $this->lastHistory->new_values ?? [];
            $fields = $this->lastHistory->changed_fields ?? [];

            $oldVals['tags'] = $currentTags;
            $newVals['tags'] = $tagNamesToRestore;
            if (!in_array('tags', $fields)) {
                $fields[] = 'tags';
            }

            $this->lastHistory->update([
                'old_values' => $oldVals,
                'new_values' => $newVals,
                'changed_fields' => $fields,
            ]);
        }

        $history->update([
            'is_reverted' => true,
            'reverted_at' => now(),
            'reverted_by' => auth()->id(),
        ]);

        return true;
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    public function rombel()
    {
        return $this->belongsTo(Rombel::class);
    }

    public function presensi()
    {
        return $this->hasMany(Presensi::class);
    }

    public function keanggotaanLembaga(): HasMany
    {
        return $this->hasMany(SiswaLembaga::class, 'siswa_id');
    }

    public function lembagaInduk(): HasOne
    {
        return $this->hasOne(SiswaLembaga::class, 'siswa_id')->where('is_induk', true);
    }

    public function lembagaAktif(): HasMany
    {
        return $this->keanggotaanLembaga()->where('status', 1);
    }

    /**
     * Get label status siswa.
     */
    public function getStatusLabelAttribute(): string
    {
        return function_exists('status_siswa') ? status_siswa($this->status) : (string) $this->status;
    }

    /**
     * Get label jenis kelamin.
     */
    public function getJenisKelaminLabelAttribute(): string
    {
        $jk = strtoupper((string) $this->jenis_kelamin);
        if ($jk === 'L') return 'Laki-laki';
        if ($jk === 'P') return 'Perempuan';
        return '-';
    }

    /**
     * Relasi ke akun User pengguna.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

