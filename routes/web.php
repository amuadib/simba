<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PelajaranController;
use App\Http\Controllers\RombelController;
use App\Http\Controllers\PembelajaranController;
use App\Http\Controllers\RekapPresensiController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\TahunAjaranController;
use App\Http\Controllers\AnggotaPembelajaranController;
use App\Http\Controllers\AuthController;

Route::get('/login', [AuthController::class, 'form'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::middleware('auth')->group(function () {
    Route::resource('siswa', SiswaController::class);
    Route::post('siswa/import', [SiswaController::class, 'import'])->name('siswa.import');
    Route::resource('pelajaran', PelajaranController::class);
    Route::resource('rombel', RombelController::class);
    Route::get('pembelajaran/presensi', [PresensiController::class, 'index'])->name('pembelajaran.presensi.index');
    Route::get('pembelajaran/presensi/load', [PresensiController::class, 'load'])->name('pembelajaran.presensi.load');
    Route::post('pembelajaran/presensi/update', [PresensiController::class, 'updateCell'])->name('pembelajaran.presensi.update');
    Route::get('pembelajaran/{pembelajaran}/presensi/create', [PresensiController::class, 'create'])->name('pembelajaran.presensi.create');
    Route::post('pembelajaran/{pembelajaran}/presensi', [PresensiController::class, 'store'])->name('pembelajaran.presensi.store');
    Route::get('pembelajaran/{pembelajaran}/anggota', [AnggotaPembelajaranController::class, 'index'])->name('pembelajaran.anggota.index');
    Route::post('pembelajaran/{pembelajaran}/anggota/{mode?}', [AnggotaPembelajaranController::class, 'update'])->name('pembelajaran.anggota.update');
    Route::resource('pembelajaran', PembelajaranController::class);
    Route::resource('tahun_ajaran', TahunAjaranController::class);
});
