<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PelajaranController;
use App\Http\Controllers\RombelController;
use App\Http\Controllers\PembelajaranController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\PresensiController;
use App\Http\Controllers\TahunAjaranController;
use App\Http\Controllers\AnggotaPembelajaranController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\JurnalController;
use App\Http\Controllers\NilaiController;
use App\Http\Controllers\SettingController;

Route::get('/csrf-refresh', fn() => ['token' => csrf_token()]);
Route::get('/login', [AuthController::class, 'form'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::middleware('auth')->group(function () {

    Route::prefix('pembelajaran')->group(function () {
        Route::get('/{pembelajaran}/jurnal', [JurnalController::class, 'index'])->name('pembelajaran.jurnal.index');
        Route::post('/{pembelajaran}/jurnal', [JurnalController::class, 'store'])->name('pembelajaran.jurnal.store');
        Route::patch('/{pembelajaran}/jurnal/{jurnal}', [JurnalController::class, 'update'])->name('pembelajaran.jurnal.update');
        Route::get('/{pembelajaran}/jurnal/{jurnal}/delete', [JurnalController::class, 'destroy'])->name('pembelajaran.jurnal.destroy');
        Route::get('/{pembelajaran}/jurnal/{jurnal}/nilai/create', [NilaiController::class, 'create'])->name('pembelajaran.jurnal.nilai.create');
        Route::get('/{pembelajaran}/jurnal/nilai', [NilaiController::class, 'index'])->name('pembelajaran.jurnal.nilai.index');
        Route::post('/nilai/update', [NilaiController::class, 'update'])->name('pembelajaran.jurnal.nilai.update');
        Route::get('/presensi', [PresensiController::class, 'index'])->name('pembelajaran.presensi.index');
        Route::get('/presensi/export', [PresensiController::class, 'export'])->name('pembelajaran.presensi.export');
        Route::get('/presensi/load', [PresensiController::class, 'load'])->name('pembelajaran.presensi.load');
        Route::post('/presensi/update', [PresensiController::class, 'updateCell'])->name('pembelajaran.presensi.update');
        Route::get('/{pembelajaran}/presensi/create', [PresensiController::class, 'create'])->name('pembelajaran.presensi.create');
        Route::post('/{pembelajaran}/presensi', [PresensiController::class, 'store'])->name('pembelajaran.presensi.store');
        Route::get('/{pembelajaran}/anggota', [AnggotaPembelajaranController::class, 'index'])->name('pembelajaran.anggota.index');
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

    Route::post('siswa/pilih', [SiswaController::class, 'pilih'])->name('siswa.pilih');
    Route::get('siswa/preview-export', [SiswaController::class, 'previewExport'])->name('siswa.preview-export');
    Route::get('siswa/hapus-preview-export/{id}', [SiswaController::class, 'hapusPreviewExport'])->name('siswa.hapus-preview-export');
    Route::get('siswa/export', [SiswaController::class, 'export'])->name('siswa.export');
    Route::post('siswa/import', [SiswaController::class, 'import'])->name('siswa.import');
    Route::resource('siswa', SiswaController::class);
    Route::resource('pelajaran', PelajaranController::class);
    Route::resource('rombel', RombelController::class);
    Route::resource('pembelajaran', PembelajaranController::class);
    Route::resource('tahun_ajaran', TahunAjaranController::class);
});
