<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Rombel extends Model
{
    use HasUuids;
    protected $table = 'rombel';
    protected $fillable = [
        'nama',
        'tingkat',
    ];
}
