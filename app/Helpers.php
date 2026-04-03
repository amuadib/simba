<?php

use App\Models\Setting;

if (!function_exists('setting')) {
    function setting($key, $default = null)
    {
        return Setting::getValue($key, $default);
    }
}
if(!function_exists('setNama')){
    function setNama($nama, $panggilan, $jenis_kelamin){
        if($panggilan){
            if(strpos($nama, $panggilan) !== false){
                $nama = str_replace($panggilan, '<span class="nickname nickname-'. $jenis_kelamin .'">' . $panggilan . '</span>', $nama);
            }else{
                $nama = $nama . ' (' . $panggilan . ')';
            }
            return $nama;
        }
        return $nama;
    }
}
