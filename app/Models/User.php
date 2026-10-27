<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGuru(): bool
    {
        return $this->role === 'guru';
    }

    public function isSiswa(): bool
    {
        return $this->role === 'siswa';
    }

    public function pegawai(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Pegawai::class, 'user_id');
    }

    public function siswa(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Siswa::class, 'user_id');
    }

    public function getProfilAttribute()
    {
        return $this->pegawai ?? $this->siswa;
    }

    public function getLembagaIds(): array
    {
        if ($this->isAdmin()) {
            return array_map('intval', array_keys(daftar_lembaga()));
        }

        $ids = [];
        if ($this->pegawai) {
            if ($this->pegawai->lembaga_id) {
                $ids[] = (int) $this->pegawai->lembaga_id;
            }
            if (method_exists($this->pegawai, 'skPenugasan')) {
                $skLembagas = $this->pegawai->skPenugasan()
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', '1');
                    })
                    ->pluck('lembaga_id')
                    ->map(fn($id) => (int) $id)
                    ->toArray();
                $ids = array_merge($ids, $skLembagas);
            }
        } elseif ($this->siswa) {
            if ($this->siswa->lembaga_id) {
                $ids[] = (int) $this->siswa->lembaga_id;
            }
            if (method_exists($this->siswa, 'lembagaTambahan')) {
                $tambahan = $this->siswa->lembagaTambahan()->pluck('lembaga_id')->map(fn($id) => (int) $id)->toArray();
                $ids = array_merge($ids, $tambahan);
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    public function canAccessLembaga($lembagaId): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array((int) $lembagaId, $this->getLembagaIds(), true);
    }
}
