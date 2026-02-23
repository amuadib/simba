<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function index()
    {
        return view('admin.setting');
    }

    public function update(Request $request)
    {
        $request->validate([
            'nama_aplikasi' => 'required',
            'nama_lembaga'  => 'required',
            'alamat'  => 'required',
            'email'         => 'nullable|email',
            'logo'          => 'nullable|image|mimes:png,jpg,jpeg|max:2048'
        ]);

        Setting::setValue('nama_aplikasi', $request->nama_aplikasi);
        Setting::setValue('nama_lembaga', $request->nama_lembaga);
        Setting::setValue('alamat', $request->alamat);
        Setting::setValue('email', $request->email);
        Setting::setValue('telp', $request->telp);

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

        return back()->with('success', 'Setting berhasil diperbarui');
    }
}
