<?php

namespace App\Http\Controllers;

use App\Models\Surat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SuratController extends Controller
{
    /**
     * Display printable view for a letter
     */
    public function print(Surat $surat)
    {
        if (! auth()->user()->canAccessLembaga($surat->lembaga_id)) {
            abort(403, 'Anda tidak memiliki akses ke surat lembaga ini.');
        }

        if ($surat->jenis_pembuatan === 'upload' && $surat->file_path) {
            if (Storage::disk('public')->exists($surat->file_path)) {
                return response()->file(Storage::disk('public')->path($surat->file_path));
            }
        }

        $items = \App\Models\Setting::getValue('lembaga') ?? config('local.lembaga') ?? [];
        $lembagaInfo = $items[$surat->lembaga_id] ?? null;

        return view('surat.print', compact('surat', 'lembagaInfo'));
    }

    /**
     * Download uploaded letter file
     */
    public function download(Surat $surat)
    {
        if (! auth()->user()->canAccessLembaga($surat->lembaga_id)) {
            abort(403, 'Anda tidak memiliki akses ke surat lembaga ini.');
        }

        if (! $surat->file_path || ! Storage::disk('public')->exists($surat->file_path)) {
            abort(404, 'File arsip surat tidak ditemukan.');
        }

        $ext = pathinfo($surat->file_path, PATHINFO_EXTENSION);
        $cleanNomor = str_replace(['/', '\\', ' '], '_', $surat->nomor_surat);
        $filename = "Surat_{$cleanNomor}.{$ext}";

        return Storage::disk('public')->download($surat->file_path, $filename);
    }
}
