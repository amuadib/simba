<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\Rombel;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'totalSiswa' => Siswa::count(),
            'totalRombel' => Rombel::count(),
        ]);
    }
}
