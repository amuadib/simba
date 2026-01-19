<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Tag extends Model
{
    use HasUuids;
    protected $table = 'tag';

    protected $fillable = [
        'nama',
    ];
    public function siswa()
    {
        return $this->belongsToMany(Siswa::class);
    }
}
