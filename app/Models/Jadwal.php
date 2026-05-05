<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Pembelajaran;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Jadwal extends Model
{
    use HasUuids;

    protected $table = "jadwal";
    protected $fillable = [
        'pembelajaran_id',
        'user_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
    ];

    public function pembelajaran()
    {
        return $this->belongsTo(Pembelajaran::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getHariTextAttribute()
    {
        return match ($this->hari) {
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Ahad',
            default => '-',
        };
    }
}
