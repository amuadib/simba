<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];
    public static function getValue($key, $default = null)
    {
        return cache()->remember("setting_$key", 86400, function () use ($key, $default) {
            return self::where('key', $key)->value('value') ?? $default;
        });
    }

    public static function setValue($key, $value)
    {
        $setting = self::where('key', $key)->first();

        if ($setting) {
            $setting->update(['value' => $value]);
        } else {
            Setting::create(['key' => $key, 'value' => $value]);
        }

        cache()->forget("setting_$key");
        cache()->forget('all_settings');
    }
}
