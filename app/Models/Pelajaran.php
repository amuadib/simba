<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Pelajaran extends Model
{
    use HasUuids;
    protected $table = 'pelajaran';

    protected $fillable = [
        'nama',
    ];
}
