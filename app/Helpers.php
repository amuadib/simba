<?php

use App\Models\Setting;

if (!function_exists('setting')) {
    function setting($key, $default = null)
    {
        return Setting::getValue($key, $default);
    }
}
if (!function_exists('setNama')) {
    function setNama($nama, $panggilan, $jenis_kelamin)
    {
        if ($panggilan) {
            if (strpos($nama, $panggilan) !== false) {
                $nama = str_replace($panggilan, '<span class="nickname nickname-' . $jenis_kelamin . '">' . $panggilan . '</span>', $nama);
            } else {
                $nama = $nama . ' (<span class="nickname nickname-' . $jenis_kelamin . '">' . $panggilan . '</span>)';
            }
            return $nama;
        }
        return $nama;
    }
}

if (!function_exists('logo_url')) {
    function logo_url()
    {
        $logo = setting('logo');
        if ($logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($logo)) {
            return asset('storage/' . $logo);
        }
        return asset('logo.jpg');
    }
}

