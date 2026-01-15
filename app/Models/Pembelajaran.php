<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Pembelajaran extends Model
{
    use HasUuids;
    protected $table = 'pembelajaran';

    protected $fillable = [
        'keterangan',
        'tahun_ajaran_id',
        'pelajaran_id',
    ];

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }
    public function pelajaran()
    {
        return $this->belongsTo(Pelajaran::class);
    }
    public function anggota()
    {
        return $this->hasMany(AnggotaPembelajaran::class);
    }
    public function presensi()
    {
        return $this->hasMany(Presensi::class);
    }
}
