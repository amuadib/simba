<?php

use App\Models\Setting;

if(!function_exists('toRoman')){
    function toRoman($number)
    {
        $map = array('X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
        $result = '';

        foreach ($map as $roman => $integer) {
            while ($number >= $integer) {
                $result .= $roman;
                $number -= $integer;
            }
        }
        return $result;
    }
}
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

if (!function_exists('nama_lembaga')) {
    function nama_lembaga($id)
    {
        if (!$id) {
            return '-';
        }
        $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
        $lembaga = $items[$id] ?? null;
        if (is_array($lembaga)) {
            return $lembaga['nama'] ?? $lembaga['singkatan'] ?? '-';
        }
        return is_string($lembaga) ? $lembaga : '-';
    }
}

if (!function_exists('kepala_lembaga')) {
    function kepala_lembaga($id = null)
    {
        if ($id) {
            $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
            $lembaga = $items[$id] ?? null;
            if (is_array($lembaga) && !empty($lembaga['kepala'])) {
                return $lembaga['kepala'];
            }
        }
        return setting('kepala_lembaga', '-');
    }
}

if (!function_exists('ttd_url')) {
    function ttd_url($lembagaId = null)
    {
        if ($lembagaId) {
            $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
            if (isset($items[$lembagaId]['ttd']) && !empty($items[$lembagaId]['ttd'])) {
                $ttd = $items[$lembagaId]['ttd'];
                return str_starts_with($ttd, 'http') ? $ttd : asset($ttd);
            }
        }
        $ttd = setting('ttd_kepala');
        if ($ttd) {
            return str_starts_with($ttd, 'http') ? $ttd : asset($ttd);
        }
        return null;
    }
}

if (!function_exists('stempel_url')) {
    function stempel_url($lembagaId = null)
    {
        if ($lembagaId) {
            $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
            if (isset($items[$lembagaId]['stempel']) && !empty($items[$lembagaId]['stempel'])) {
                $stempel = $items[$lembagaId]['stempel'];
                return str_starts_with($stempel, 'http') ? $stempel : asset($stempel);
            }
        }
        $stempel = setting('stempel_lembaga');
        if ($stempel) {
            return str_starts_with($stempel, 'http') ? $stempel : asset($stempel);
        }
        return null;
    }
}

if (!function_exists('daftar_lembaga')) {
    function daftar_lembaga()
    {
        $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
        $result = [];
        foreach ($items as $id => $item) {
            $result[$id] = is_array($item) ? ($item['nama'] ?? $item['singkatan'] ?? "-") : $item;
        }
        return $result;
    }
}

