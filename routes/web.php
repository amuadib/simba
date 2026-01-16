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

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Route::resource('presensi', PresensiController::class);
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


// Route::get('/rekap-presensi', [RekapPresensiController::class, 'index'])
//     ->name('rekap.presensi');

// Route::get('/rekap-presensi/export', [RekapPresensiController::class, 'export'])
//     ->name('rekap.presensi.export');
