<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

if (! function_exists('toRoman')) {
    function toRoman($number)
    {
        $map = ['X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
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
if (! function_exists('setting')) {
    function setting($key, $default = null)
    {
        return Setting::getValue($key, $default);
    }
}
if (! function_exists('setNama')) {
    function setNama($nama, $panggilan, $jenis_kelamin)
    {
        if ($panggilan) {
            if (strpos($nama, $panggilan) !== false) {
                $nama = str_replace($panggilan, '<span class="nickname nickname-'.$jenis_kelamin.'">'.$panggilan.'</span>', $nama);
            } else {
                $nama = $nama.' (<span class="nickname nickname-'.$jenis_kelamin.'">'.$panggilan.'</span>)';
            }

            return $nama;
        }

        return $nama;
    }
}

if (! function_exists('setNamaPegawai')) {
    function setNamaPegawai($nama, $gelar_depan = null, $gelar_belakang = null)
    {
        $depan = $gelar_depan ? e($gelar_depan).' ' : '';
        $belakang = $gelar_belakang ? ', '.e($gelar_belakang) : '';

        return $depan.e($nama).$belakang;
    }
}

if (! function_exists('logo_url')) {
    function logo_url()
    {
        $logo = setting('logo');
        if ($logo && Storage::disk('public')->exists($logo)) {
            return asset('storage/'.$logo);
        }

        return asset('logo.jpg');
    }
}

if (! function_exists('nama_lembaga')) {
    function nama_lembaga($id, $singkatan = false)
    {
        if (! $id) {
            return '-';
        }
        $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
        $lembaga = $items[$id] ?? null;
        if (is_array($lembaga)) {
            return $singkatan ? ($lembaga['singkatan'] ?? '-') : ($lembaga['nama'] ?? '-');
        }

        return is_string($lembaga) ? $lembaga : '-';
    }
}

if (! function_exists('email_lembaga')) {
    function email_lembaga($id = null)
    {
        if ($id) {
            $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
            $lembaga = $items[$id] ?? null;
            if (is_array($lembaga) && ! empty($lembaga['email'])) {
                return $lembaga['email'];
            }
        }

        return setting('email', '-');
    }
}

if (! function_exists('kepala_lembaga')) {
    function kepala_lembaga($id = null)
    {
        if ($id) {
            $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
            $lembaga = $items[$id] ?? null;
            if (is_array($lembaga) && ! empty($lembaga['kepala'])) {
                return $lembaga['kepala'];
            }
        }

        return setting('kepala_lembaga', '-');
    }
}

if (! function_exists('npsn_lembaga')) {
    function npsn_lembaga($id = null)
    {
        if ($id) {
            $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
            $lembaga = $items[$id] ?? null;
            if (is_array($lembaga) && ! empty($lembaga['npsn'])) {
                return $lembaga['npsn'];
            }
        }

        return setting('npsn', '-');
    }
}

if (! function_exists('akreditasi_lembaga')) {
    function akreditasi_lembaga($id = null)
    {
        if ($id) {
            $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
            $lembaga = $items[$id] ?? null;
            if (is_array($lembaga) && ! empty($lembaga['akreditasi'])) {
                return $lembaga['akreditasi'];
            }
        }

        return setting('akreditasi', '-');
    }
}

if (! function_exists('website_lembaga')) {
    function website_lembaga($id = null)
    {
        if ($id) {
            $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
            $lembaga = $items[$id] ?? null;
            if (is_array($lembaga) && ! empty($lembaga['website'])) {
                return $lembaga['website'];
            }
        }

        return setting('website', null);
    }
}

if (! function_exists('ttd_url')) {
    function ttd_url($lembagaId = null)
    {
        if ($lembagaId) {
            $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
            if (isset($items[$lembagaId]['ttd']) && ! empty($items[$lembagaId]['ttd'])) {
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

if (! function_exists('stempel_url')) {
    function stempel_url($lembagaId = null)
    {
        if ($lembagaId) {
            $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
            if (isset($items[$lembagaId]['stempel']) && ! empty($items[$lembagaId]['stempel'])) {
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

if (! function_exists('daftar_lembaga')) {
    function daftar_lembaga()
    {
        $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
        $result = [];
        foreach ($items as $id => $item) {
            $result[$id] = is_array($item) ? ($item['nama'] ?? $item['singkatan'] ?? '-') : $item;
        }

        return $result;
    }
}

if (! function_exists('daftar_jabatan')) {
    function daftar_jabatan()
    {
        return config('local.jabatan', []);
    }
}

if (! function_exists('nama_jabatan')) {
    function nama_jabatan($id)
    {
        if (! $id) {
            return '-';
        }
        $items = daftar_jabatan();
        if (isset($items[$id])) {
            return $items[$id];
        }
        if (is_numeric($id) && isset($items[(int) $id])) {
            return $items[(int) $id];
        }
        $flipped = array_flip($items);
        if (isset($flipped[$id])) {
            return $id;
        }
        return is_numeric($id) ? '-' : (string) $id;
    }
}

if (! function_exists('gmaps_embed_url')) {
    function gmaps_embed_url($lembagaId = null)
    {
        $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
        $lembaga = $lembagaId ? ($items[$lembagaId] ?? null) : reset($items);
        if (! $lembaga || ! is_array($lembaga)) {
            return null;
        }

        $embed = trim($lembaga['maps_embed'] ?? '');
        if (! empty($embed)) {
            if (preg_match('/src=["\']([^"\']+)["\']/', $embed, $matches)) {
                return $matches[1];
            }
            if (str_starts_with($embed, 'http')) {
                return $embed;
            }
        }

        $lat = trim((string) ($lembaga['lat'] ?? ''));
        $lon = trim((string) ($lembaga['lon'] ?? ''));
        if (! empty($lat) && ! empty($lon) && (float) $lat != 0 && (float) $lon != 0) {
            return "https://maps.google.com/maps?q={$lat},{$lon}&hl=id&z=16&output=embed";
        }

        if (! empty($lembaga['alamat'])) {
            return 'https://maps.google.com/maps?q='.urlencode($lembaga['alamat']).'&hl=id&z=15&output=embed';
        }

        return null;
    }
}

if (! function_exists('gmaps_url')) {
    function gmaps_url($lembagaId = null)
    {
        $items = Setting::getValue('lembaga') ?? config('local.lembaga') ?? config('custom.lembaga') ?? [];
        $lembaga = $lembagaId ? ($items[$lembagaId] ?? null) : reset($items);
        if (! $lembaga || ! is_array($lembaga)) {
            return null;
        }

        $lat = trim((string) ($lembaga['lat'] ?? ''));
        $lon = trim((string) ($lembaga['lon'] ?? ''));
        if (! empty($lat) && ! empty($lon) && (float) $lat != 0 && (float) $lon != 0) {
            return "https://www.google.com/maps?q={$lat},{$lon}";
        }

        if (! empty($lembaga['alamat'])) {
            return 'https://www.google.com/maps/search/?api=1&query='.urlencode($lembaga['alamat']);
        }

        return null;
    }
}

if (! function_exists('status_siswa')) {
    function status_siswa($status)
    {
        if ($status === null || $status === '') {
            return '-';
        }
        $statuses = config('local.status_siswa', [
            1 => 'Aktif',
            2 => 'Lulus',
            3 => 'Mutasi',
            4 => 'Non-Aktif',
            5 => 'Almarhum',
            6 => 'Keluar',
        ]);

        return $statuses[$status] ?? (is_numeric($status) ? '-' : (string) $status);
    }
}

if (! function_exists('status_pegawai')) {
    function status_pegawai($status)
    {
        if ($status === null || $status === '') {
            return '-';
        }
        $statuses = config('local.status_pegawai', [
            1 => 'Aktif',
            2 => 'Cuti',
            3 => 'Non-Aktif',
            4 => 'Mutasi',
            5 => 'Pensiun',
            6 => 'Keluar',
            7 => 'Almarhum',
        ]);

        return $statuses[$status] ?? (is_numeric($status) ? '-' : (string) $status);
    }
}

