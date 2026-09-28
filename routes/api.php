<?php

use App\Http\Controllers\Api\SiswaSyncController;

Route::get('/siswa/sync', [SiswaSyncController::class, 'sync'])->name('api.siswa.sync');

