<?php

return [
    'lembaga' => [
        1 => 'SDI Miftahul Ulum Klemunan',
        2 => 'SMPI Miftahul Ulum',
        3 => 'SMAI Miftahul Ulum',
        99 => 'Yayasan Bastomiyah Rahman',
    ],
    'jenis_nilai' => [
        1 => [
            'kode' => 'UH',
            'nama' => 'Ulangan Harian',
            'bobot' => 1,
        ],
        2 => [
            'kode' => 'UTS',
            'nama' => 'Ujian Tengah Semester',
            'bobot' => 2,
        ],
        3 => [
            'kode' => 'UAS',
            'nama' => 'Ujian Akhir Semester',
            'bobot' => 3,
        ],
    ],
    'status_siswa' => [
        1 => 'Aktif',
        2 => 'Lulus',
        3 => 'Mutasi',
        4 => 'Non-Aktif',
        5 => 'Almarhum',
        6 => 'Keluar',
    ],
    'saku' => [
        'key' => env('SAKU_KEY', 'secret-token-123'),
        'url' => env('SAKU_URL', 'https://saku-v2.lol'),
    ],
];
