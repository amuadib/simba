<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        if (!auth()->user()->isAdmin()) {
            abort(403);
        }

        return view('admin.setting');
    }

    public function update(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403);
        }
        $request->validate([
            'nama_aplikasi' => 'required',
            'nama_lembaga'  => 'required',
            'alamat'  => 'required',
            'email'         => 'nullable|email',
            'logo'          => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'ttd'           => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'stempel'       => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        Setting::setValue('nama_aplikasi', $request->nama_aplikasi);
        Setting::setValue('nama_lembaga', $request->nama_lembaga);
        Setting::setValue('alamat', $request->alamat);
        Setting::setValue('email', $request->email);
        Setting::setValue('telp', $request->telp);
        if ($request->filled('kepala_lembaga')) {
            Setting::setValue('kepala_lembaga', $request->kepala_lembaga);
        }
        if ($request->has('npsn')) {
            Setting::setValue('npsn', $request->npsn);
        }
        if ($request->has('akreditasi')) {
            Setting::setValue('akreditasi', $request->akreditasi);
        }
        if ($request->has('website')) {
            Setting::setValue('website', $request->website);
        }
        if ($request->has('lat')) {
            Setting::setValue('lat', $request->lat);
        }
        if ($request->has('lon')) {
            Setting::setValue('lon', $request->lon);
        }
        if ($request->has('maps_embed')) {
            Setting::setValue('maps_embed', $request->maps_embed);
        }

        // Upload Logo
        if ($request->hasFile('logo')) {

            // hapus logo lama
            if (setting('logo')) {
                Storage::disk('public')->delete(setting('logo'));
            }

            $path = $request->file('logo')
                ->store('settings', 'public');

            Setting::setValue('logo', $path);
        }

        // Upload TTD Kepala
        if ($request->hasFile('ttd')) {

            if (setting('ttd_kepala')) {
                $oldPath = str_replace('/storage/', '', setting('ttd_kepala'));
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('ttd')
                ->store('settings/ttd', 'public');

            Setting::setValue('ttd_kepala', Storage::url($path));
        }

        // Upload Stempel Lembaga
        if ($request->hasFile('stempel')) {

            if (setting('stempel_lembaga')) {
                $oldPath = str_replace('/storage/', '', setting('stempel_lembaga'));
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('stempel')
                ->store('settings/stempel', 'public');

            Setting::setValue('stempel_lembaga', Storage::url($path));
        }

        return back()->with('success', 'Setting berhasil diperbarui');
    }
}
