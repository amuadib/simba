<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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
    ];
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
}
