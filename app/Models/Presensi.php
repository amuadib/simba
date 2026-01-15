<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Presensi extends Model
{
    use HasUuids;
    protected $table = 'presensi';
    protected $fillable = [
        'kelas_id',
        'siswa_id',
        'tanggal',
        'status',
    ];

    protected $dates = ['tanggal'];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }

    public function pembelajaran()
    {
        return $this->belongsTo(Pembelajaran::class);
    }
}
