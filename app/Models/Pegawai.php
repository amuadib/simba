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
    ];

    protected $casts = [
        'tmt' => 'date',
        'tanggal_lahir' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Pegawai $pegawai) {
            if (empty($pegawai->niy)) {
                $pegawai->niy = static::generateNiy($pegawai->tmt, $pegawai->jenis_kelamin);
            }
        });
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
}
