<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TahunAjaran;

class TahunAjaranController extends Controller
{
    public function index()
    {
        return view('tahun_ajaran.index', [
            'tahunajaran' => TahunAjaran::orderBy('nama', 'desc')->paginate(15),
            'action' => '',
        ]);
    }
    public function store(Request $r)
    {
        TahunAjaran::create($r->validate([
            'nama' => 'required',
            'aktif' => 'required',
        ]));

        return back()->with('success', 'Tahun Ajaran berhasil ditambahkan');
    }
    public function edit(TahunAjaran $tahun_ajaran)
    {
        return view('tahun_ajaran.index', [
            'data' => $tahun_ajaran,
            'action' => 'edit',
            'tahunajaran' => TahunAjaran::orderBy('tahun')->paginate(15),
        ]);
    }
    public function update(Request $request, TahunAjaran $tahun_ajaran)
    {
        $tahun_ajaran->update($request->validate([
            'nama' => 'required',
            'aktif' => 'required',
        ]));

        return redirect()->route('tahun_ajaran.index');
    }
    public function destroy(TahunAjaran $tahun_ajaran)
    {
        $tahun_ajaran->delete();
        return back();
    }
}
