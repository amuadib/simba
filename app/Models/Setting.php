<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected static function booted()
    {
        static::saved(function ($model) {
            cache()->forget('custom_settings');
            cache()->forget('all_settings');
            if ($model->key) {
                cache()->forget("setting_{$model->key}");
            }
        });

        static::deleted(function ($model) {
            cache()->forget('custom_settings');
            cache()->forget('all_settings');
            if ($model->key) {
                cache()->forget("setting_{$model->key}");
            }
        });
    }

    public static function getValue($key, $default = null)
    {
        return cache()->remember("setting_$key", 86400, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            if ($setting) {
                if ($setting->payload !== null) {
                    return $setting->payload;
                }

                return $setting->value ?? $default;
            }

            if ($key === 'nama_aplikasi') {
                return config('local.app.nama') ?? config('custom.app.nama') ?? $default;
            }
            if ($key === 'nama_lembaga') {
                return config('local.lembaga.1.nama') ?? config('custom.lembaga.1.nama') ?? $default;
            }

            return $default;
        });
    }

    public static function setValue($key, $value)
    {
        $setting = self::where('key', $key)->first();
        $isPayload = is_array($value) || is_object($value);

        $attributes = $isPayload
            ? ['payload' => (array) $value, 'value' => json_encode($value)]
            : ['value' => $value];

        if ($setting) {
            $setting->update($attributes);
        } else {
            self::create(array_merge(['key' => $key], $attributes));
        }

        cache()->forget("setting_$key");
        cache()->forget('all_settings');
        cache()->forget('custom_settings');

        \Artisan::call('cache:clear');
    }
}
