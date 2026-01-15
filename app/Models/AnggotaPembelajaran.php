<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AnggotaPembelajaran extends Model
{
    // use HasUuids;
    protected $table = 'anggota_pembelajaran';

    protected $fillable = [
        'pembelajaran_id',
        'siswa_id',
    ];

    public function pembelajaran()
    {
        return $this->belongsTo(Pembelajaran::class);
    }
    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}
