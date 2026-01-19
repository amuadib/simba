<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiswaTag extends Model
{
    protected $table = 'siswa_tag';
    public $timestamps = false;
    protected $fillable = [
        'siswa_id',
        'tag_id',
    ];
}
