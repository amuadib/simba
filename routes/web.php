<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\SiswaController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\AnggotaPembelajaranController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\JadwalController;

Route::get('/csrf-refresh', fn() => ['token' => csrf_token()]);

use Livewire\Volt\Volt;
Volt::route('/login', 'auth.login')->name('login')->middleware('guest');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Volt::route('/', 'dashboard')->name('dashboard')->middleware('auth');

Route::middleware('auth')->group(function () {

    Route::prefix('pembelajaran')->group(function () {
        Volt::route('/{pembelajaran}/jurnal', 'pembelajaran.jurnal')->name('pembelajaran.jurnal.index');
        Volt::route('/{pembelajaran}/jurnal/{jurnal}/nilai/create', 'pembelajaran.nilai.create')->name('pembelajaran.jurnal.nilai.create');
        Volt::route('/{pembelajaran}/jurnal/nilai', 'pembelajaran.nilai.index')->name('pembelajaran.jurnal.nilai.index');
        Route::get('/presensi', function() { return redirect()->route('pembelajaran.presensi.index'); });
        Volt::route('/presensi/rekap', 'presensi.index')->name('pembelajaran.presensi.index');
        Route::get('/presensi/export', [PresensiController::class, 'export'])->name('pembelajaran.presensi.export');
        Route::get('/presensi/load', [PresensiController::class, 'load'])->name('pembelajaran.presensi.load');
        Route::post('/presensi/update', [PresensiController::class, 'updateCell'])->name('pembelajaran.presensi.update');
        Volt::route('/{pembelajaran}/presensi/create', 'presensi.create')->name('pembelajaran.presensi.create');
        Route::post('/{pembelajaran}/presensi', [PresensiController::class, 'store'])->name('pembelajaran.presensi.store');
        Volt::route('/{pembelajaran}/anggota', 'pembelajaran.anggota')->name('pembelajaran.anggota.index');
        Route::post('/{pembelajaran}/anggota/{mode?}', [AnggotaPembelajaranController::class, 'update'])->name('pembelajaran.anggota.update');
    });

    Route::prefix('admin')->group(function () {
        Route::get('/database', [DatabaseController::class, 'index'])->name('database.index');
        Route::post('/database/export', [DatabaseController::class, 'export'])->name('database.export');
        Route::post('/database/import', [DatabaseController::class, 'import'])->name('database.import');
        Route::get('/settings', [SettingController::class, 'index'])
            ->name('settings.index');

        Route::post('/settings', [SettingController::class, 'update'])
            ->name('settings.update');
    });

    Route::prefix('tags')->group(function () {
        Route::post('/', [TagController::class, 'store']);
        Route::put('/{tag}', [TagController::class, 'update']);
        Route::delete('/{tag}', [TagController::class, 'destroy']);
        Route::get('/search', function (\Illuminate\Http\Request $request) {
            $q = $request->get('q');
            return \App\Models\Tag::where('nama', 'like', "%{$q}%")
                ->orderBy('nama')
                ->limit(10)
                ->get(['id', 'nama']);
        });
    });

    Route::get('/siswa/preview-export', [SiswaController::class, 'previewExport'])->name('siswa.preview-export');
    Route::delete('/siswa/preview-export/{id}', [SiswaController::class, 'hapusPreviewExport'])->name('siswa.preview-export.destroy');
    Route::get('/siswa/export/{from?}/{template?}', [SiswaController::class, 'export'])->name('siswa.export');
    Volt::route('/siswa', 'siswa.index')->name('siswa.index');
    Volt::route('/pelajaran', 'pelajaran.index')->name('pelajaran.index');
    Volt::route('/rombel', 'rombel.index')->name('rombel.index');
    Volt::route('/pembelajaran', 'pembelajaran.index')->name('pembelajaran.index');
    Volt::route('/jadwal', 'jadwal.index')->name('jadwal.index');
    Route::get('/jadwal/print', [JadwalController::class, 'print'])->name('jadwal.print');
    Volt::route('/tahun-ajaran', 'tahun-ajaran.index')->name('tahun_ajaran.index');
});
