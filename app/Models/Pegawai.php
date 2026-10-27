<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Pegawai extends Model
{
    use HasUuids;

    protected $table = 'pegawai';

    protected $fillable = [
        'niy',
        'nuptk',
        'tmt',
        'nama',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'nik',
        'alamat',
        'no_hp',
        'gelar_depan',
        'gelar_belakang',
        'foto',
        'lembaga_id',
        'status',
        'user_id',
    ];

    protected $casts = [
        'tmt' => 'date',
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
    public ?PegawaiHistory $lastHistory = null;

    protected static function booted(): void
    {
        static::creating(function (Pegawai $pegawai) {
            if (empty($pegawai->niy)) {
                $pegawai->niy = static::generateNiy($pegawai->tmt, $pegawai->jenis_kelamin);
            }
        });

        static::created(function (Pegawai $pegawai) {
            if ($pegawai->suppressHistory) {
                return;
            }

            $attributes = $pegawai->getAttributes();
            unset($attributes['created_at'], $attributes['updated_at']);

            $userId = (auth()->check() && User::where('id', auth()->id())->exists()) ? auth()->id() : null;

            $history = PegawaiHistory::create([
                'pegawai_id' => $pegawai->id,
                'user_id' => $userId,
                'action' => $pegawai->historyAction ?? 'create',
                'old_values' => null,
                'new_values' => $attributes,
                'changed_fields' => array_keys($attributes),
                'description' => $pegawai->historyDescription ?? 'Data pegawai baru ditambahkan',
            ]);
            $pegawai->lastHistory = $history;
            $pegawai->historyAction = null;
            $pegawai->historyDescription = null;
        });

        static::updating(function (Pegawai $pegawai) {
            if ($pegawai->suppressHistory) {
                return;
            }

            $dirty = $pegawai->getDirty();
            unset($dirty['updated_at']);

            if (empty($dirty)) {
                return;
            }

            $oldValues = [];
            $newValues = [];
            $changedFields = [];

            foreach ($dirty as $key => $newValue) {
                $oldValue = $pegawai->getOriginal($key);
                if ((string)$oldValue !== (string)$newValue) {
                    $oldValues[$key] = $oldValue;
                    $newValues[$key] = $newValue;
                    $changedFields[] = $key;
                }
            }

            if (empty($changedFields)) {
                return;
            }

            $action = $pegawai->historyAction ?? 'update';
            $description = $pegawai->historyDescription;
            if (!$description) {
                $fieldNames = array_map(fn($f) => PegawaiHistory::formatFieldName($f), $changedFields);
                $description = 'Pembaruan data pegawai (' . implode(', ', $fieldNames) . ')';
            }

            $userId = (auth()->check() && User::where('id', auth()->id())->exists()) ? auth()->id() : null;

            $history = PegawaiHistory::create([
                'pegawai_id' => $pegawai->id,
                'user_id' => $userId,
                'action' => $action,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => $changedFields,
                'description' => $description,
            ]);
            $pegawai->lastHistory = $history;
            $pegawai->historyAction = null;
            $pegawai->historyDescription = null;
        });

        static::updated(function (Pegawai $pegawai) {
            // Sinkronisasi status SK penugasan jika status kepegawaian berubah
            if ($pegawai->wasChanged('status')) {
                $newStatus = (int) $pegawai->status;

                // Status non-aktif final: 3 (Non-Aktif), 4 (Mutasi), 5 (Pensiun), 6 (Keluar), 7 (Almarhum)
                if (in_array($newStatus, [3, 4, 5, 6, 7])) {
                    $activeSks = $pegawai->skPenugasan()->where('status', '1')->get();
                    if ($activeSks->isNotEmpty()) {
                        $statusLabel = $pegawai->status_label;
                        foreach ($activeSks as $sk) {
                            $sk->update([
                                'status' => '0',
                                'tmt_selesai' => $sk->tmt_selesai ?: now()->toDateString(),
                                'keterangan' => $sk->keterangan
                                    ? ($sk->keterangan . " (Selesai otomatis: status {$statusLabel})")
                                    : "Penugasan selesai otomatis karena status pegawai: {$statusLabel}",
                            ]);
                        }
                    }
                } elseif ($newStatus === 1) {
                    // Jika diaktifkan kembali dan belum ada SK aktif, aktifkan kembali penugasan induk/terakhir
                    $hasActiveSk = $pegawai->skPenugasan()->where('status', '1')->exists();
                    if (!$hasActiveSk) {
                        $induk = $pegawai->penugasanInduk ?: $pegawai->skPenugasan()->orderByDesc('created_at')->first();
                        if ($induk) {
                            $induk->update([
                                'status' => '1',
                                'tmt_selesai' => null,
                            ]);
                        }
                    }
                }
            }
        });
    }

    public function histories()
    {
        return $this->hasMany(PegawaiHistory::class)->orderBy('id', 'desc');
    }

    /**
     * Rollback a specific history entry.
     */
    public function rollbackHistory(PegawaiHistory|int $history): bool
    {
        if (is_numeric($history)) {
            $history = PegawaiHistory::where('pegawai_id', $this->id)->findOrFail($history);
        }

        if ($history->is_reverted) {
            return false;
        }

        // Handle SK Penugasan actions
        if (in_array($history->action, ['sk_create', 'sk_update', 'sk_delete'])) {
            if ($history->action === 'sk_update' && !empty($history->old_values)) {
                $oldValues = $history->old_values;
                $skId = $oldValues['sk_id'] ?? null;
                unset($oldValues['sk_id']);

                $sk = SkPenugasan::where('pegawai_id', $this->id)->find($skId);
                if ($sk) {
                    SkPenugasan::$suppressHistory = true;
                    $sk->update($oldValues);
                    SkPenugasan::$suppressHistory = false;

                    PegawaiHistory::create([
                        'pegawai_id' => $this->id,
                        'user_id' => auth()->id(),
                        'action' => 'rollback',
                        'old_values' => $history->new_values,
                        'new_values' => $history->old_values,
                        'changed_fields' => $history->changed_fields,
                        'description' => 'Undo perubahan SK Penugasan #' . $history->id,
                    ]);

                    $history->update([
                        'is_reverted' => true,
                        'reverted_at' => now(),
                        'reverted_by' => auth()->id(),
                    ]);

                    return true;
                }
                return false;
            } elseif ($history->action === 'sk_delete' && !empty($history->old_values)) {
                $oldValues = $history->old_values;
                unset($oldValues['sk_id'], $oldValues['created_at'], $oldValues['updated_at']);

                SkPenugasan::$suppressHistory = true;
                $this->skPenugasan()->create($oldValues);
                SkPenugasan::$suppressHistory = false;

                PegawaiHistory::create([
                    'pegawai_id' => $this->id,
                    'user_id' => auth()->id(),
                    'action' => 'rollback',
                    'old_values' => null,
                    'new_values' => $oldValues,
                    'changed_fields' => array_keys($oldValues),
                    'description' => 'Undo penghapusan SK Penugasan #' . $history->id,
                ]);

                $history->update([
                    'is_reverted' => true,
                    'reverted_at' => now(),
                    'reverted_by' => auth()->id(),
                ]);

                return true;
            } elseif ($history->action === 'sk_create' && !empty($history->new_values)) {
                $newValues = $history->new_values;
                $skId = $newValues['id'] ?? $newValues['sk_id'] ?? null;
                if ($skId) {
                    $sk = SkPenugasan::where('pegawai_id', $this->id)->find($skId);
                    if ($sk) {
                        SkPenugasan::$suppressHistory = true;
                        $sk->delete();
                        SkPenugasan::$suppressHistory = false;

                        PegawaiHistory::create([
                            'pegawai_id' => $this->id,
                            'user_id' => auth()->id(),
                            'action' => 'rollback',
                            'old_values' => $newValues,
                            'new_values' => null,
                            'changed_fields' => array_keys($newValues),
                            'description' => 'Undo penambahan SK Penugasan #' . $history->id,
                        ]);

                        $history->update([
                            'is_reverted' => true,
                            'reverted_at' => now(),
                            'reverted_by' => auth()->id(),
                        ]);

                        return true;
                    }
                }
                return false;
            }
            return false;
        }

        if (empty($history->old_values)) {
            return false;
        }

        $oldValues = $history->old_values;

        $this->historyAction = 'rollback';
        $fieldNames = array_map(fn($f) => PegawaiHistory::formatFieldName($f), array_keys($oldValues));
        $this->historyDescription = 'Undo perubahan #' . $history->id . ' (' . implode(', ', $fieldNames) . ')';

        $this->update($oldValues);

        $this->historyAction = null;
        $this->historyDescription = null;

        $history->update([
            'is_reverted' => true,
            'reverted_at' => now(),
            'reverted_by' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Generate NIY otomatis:
     * 4 digit tahun TMT + 2 digit bulan TMT + (001 untuk laki-laki / 002 untuk perempuan) + nomor urut dengan pad 3 digit
     * Nomor urut terakhir diambil dari 3 digit terakhir NIY yang di-sort.
     */
    public static function generateNiy($tmt = null, $jenisKelamin = 'l'): string
    {
        $date = $tmt instanceof \Carbon\Carbon ? $tmt : ($tmt ? \Carbon\Carbon::parse($tmt) : now());
        $year = $date->format('Y');
        $month = $date->format('m');
        $genderCode = strtolower((string) $jenisKelamin) === 'p' ? '002' : '001';

        $prefix = $year . $month . $genderCode;

        // Ambil semua NIY yang ada dan ambil 3 digit terakhirnya, lalu di-sort
        $existingNiys = static::whereNotNull('niy')
            ->where('niy', '!=', '')
            ->pluck('niy');

        $seqs = [];
        foreach ($existingNiys as $existingNiy) {
            $trimmed = trim((string) $existingNiy);
            if (strlen($trimmed) >= 3) {
                $suffix = substr($trimmed, -3);
                if (ctype_digit($suffix)) {
                    $seqs[] = (int) $suffix;
                }
            }
        }

        sort($seqs);
        $lastSeq = !empty($seqs) ? end($seqs) : 0;

        // Validasi juga terhadap NIY teratas jika di-sort descending
        $latestNiy = static::whereNotNull('niy')->where('niy', '!=', '')->orderByDesc('niy')->value('niy');
        if ($latestNiy) {
            $latestSuffix = substr(trim((string) $latestNiy), -3);
            if (ctype_digit($latestSuffix) && (int) $latestSuffix > $lastSeq) {
                $lastSeq = (int) $latestSuffix;
            }
        }

        $newSeq = str_pad($lastSeq + 1, 3, '0', STR_PAD_LEFT);

        return $prefix . $newSeq;
    }

    /**
     * Get nama lengkap dengan gelar depan dan gelar belakang.
     */
    public function getNamaLengkapAttribute(): string
    {
        $depan = $this->gelar_depan ? trim($this->gelar_depan) . ' ' : '';
        $belakang = $this->gelar_belakang ? ', ' . trim($this->gelar_belakang) : '';
        return $depan . $this->nama . $belakang;
    }

    /**
     * Get label jenis kelamin.
     */
    public function getJenisKelaminLabelAttribute(): string
    {
        return strtolower($this->jenis_kelamin) === 'l' ? 'Laki-laki' : 'Perempuan';
    }

    /**
     * Get label status pegawai.
     */
    public function getStatusLabelAttribute(): string
    {
        $statuses = config('local.status_pegawai', [
            1 => 'Aktif',
            2 => 'Cuti',
            3 => 'Non-Aktif',
            4 => 'Mutasi',
            5 => 'Pensiun',
            6 => 'Keluar',
        ]);

        return $statuses[$this->status] ?? (string) $this->status;
    }

    /**
     * Relasi ke semua SK Penugasan pegawai.
     */
    public function skPenugasan(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SkPenugasan::class, 'pegawai_id')->orderByDesc('is_induk')->orderBy('created_at');
    }

    /**
     * Relasi ke SK Penugasan aktif saja.
     */
    public function penugasanAktif(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SkPenugasan::class, 'pegawai_id')->where('status', '1')->orderByDesc('is_induk');
    }

    /**
     * Relasi ke SK Penugasan lembaga induk (satmikal).
     */
    public function penugasanInduk(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SkPenugasan::class, 'pegawai_id')->where('is_induk', true);
    }

    /**
     * Get nama lembaga utama/induk pegawai.
     */
    public function getNamaLembagaAttribute(): string
    {
        if ($this->relationLoaded('skPenugasan') && $this->skPenugasan->isNotEmpty()) {
            $induk = $this->skPenugasan->firstWhere('is_induk', true) ?? $this->skPenugasan->first();
            return nama_lembaga($induk->lembaga_id);
        }
        return nama_lembaga($this->lembaga_id);
    }

    /**
     * Get ringkasan semua lembaga tempat bertugas.
     */
    public function getDaftarNamaLembagaAttribute(): string
    {
        if ($this->skPenugasan && $this->skPenugasan->isNotEmpty()) {
            return $this->skPenugasan->map(fn($sk) => nama_lembaga($sk->lembaga_id, true) . ($sk->is_induk ? ' (Induk)' : '') . ($sk->jabatan ? ' - ' . nama_jabatan($sk->jabatan) : ''))->implode(', ');
        }
        return nama_lembaga($this->lembaga_id, true);
    }

    /**
     * Get ID jabatan induk pegawai.
     */
    public function getJabatanAttribute()
    {
        $induk = $this->relationLoaded('skPenugasan')
            ? ($this->skPenugasan->firstWhere('is_induk', true) ?? $this->skPenugasan->first())
            : $this->penugasanInduk;

        return $induk?->jabatan;
    }

    /**
     * Get nama jabatan induk pegawai.
     */
    public function getNamaJabatanAttribute(): string
    {
        return nama_jabatan($this->jabatan);
    }

    /**
     * Get url foto pegawai.
     */
    public function getFotoUrlAttribute(): ?string
    {
        if ($this->foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($this->foto)) {
            return asset('storage/' . $this->foto);
        }
        return null;
    }

    /**
     * Mutator untuk memastikan jenis_kelamin selalu huruf kecil ('l' atau 'p').
     */
    public function setJenisKelaminAttribute($value): void
    {
        $this->attributes['jenis_kelamin'] = strtolower($value ?? 'l');
    }

    /**
     * Relasi ke akun User pengguna.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
