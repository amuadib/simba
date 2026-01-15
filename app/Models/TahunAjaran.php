<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TahunAjaran extends Model
{
    use HasUuids;
    protected $table = 'tahun_ajaran';

    protected $fillable = [
        'nama',
        'aktif',
    ];
}
