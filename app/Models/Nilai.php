<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Nilai extends Model
{
    use HasUuids;
    protected $table = 'nilai';

    protected $fillable = [
        'jurnal_id',
        'siswa_id',
        'jenis_nilai_id',
        'nilai'
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
    public function jurnal()
    {
        return $this->belongsTo(Jurnal::class);
    }
}
