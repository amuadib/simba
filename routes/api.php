<?php

use App\Http\Controllers\Api\SiswaSyncController;
use App\Http\Controllers\Api\SettingSyncController;

Route::get('/siswa/sync', [SiswaSyncController::class, 'sync'])->name('api.siswa.sync');
Route::get('/settings/sync', [SettingSyncController::class, 'sync'])->name('api.settings.sync');
Route::get('/lembaga/sync', [SettingSyncController::class, 'sync'])->name('api.lembaga.sync');

