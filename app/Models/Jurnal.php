<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Jurnal extends Model
{
    use HasUuids;
    protected $table = 'jurnal';

    protected $fillable = [
        'pembelajaran_id',
        'tanggal',
        'materi',
    ];
    protected $casts = [
        'tanggal' => 'date',
    ];
    public function pembelajaran()
    {
        return $this->belongsTo(Pembelajaran::class);
    }

    public function presensi()
    {
        return $this->hasMany(Presensi::class, 'pembelajaran_id', 'pembelajaran_id')
            ->whereColumn('presensi.tanggal', 'jurnal.tanggal');
    }
}
