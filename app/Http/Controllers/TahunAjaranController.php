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
    public function edit(TahunAjaran $tahunajaran)
    {
        return view('tahun_ajaran.index', [
            'data' => $tahunajaran,
            'action' => 'edit',
            'tahunajaran' => TahunAjaran::orderBy('tahun')->paginate(15),
        ]);
    }
    public function update(Request $request, TahunAjaran $tahunajaran)
    {
        $tahunajaran->update($request->validate([
            'nama' => 'required',
            'aktif' => 'required',
        ]));

        return redirect()->route('tahun_ajaran.index');
    }
    public function destroy(TahunAjaran $tahunajaran)
    {
        $tahunajaran->delete();
        return back();
    }
}
