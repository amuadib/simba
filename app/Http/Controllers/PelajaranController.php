<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pelajaran;

class PelajaranController extends Controller
{
    public function index()
    {
        return view('pelajaran.index', [
            'pelajaran' => Pelajaran::orderBy('nama')->paginate(15),
            'action' => '',
        ]);
    }
    public function store(Request $r)
    {
        Pelajaran::create($r->validate([
            'nama' => 'required'
        ]));

        return back();
    }
    public function edit(Pelajaran $pelajaran)
    {
        return view('pelajaran.index', [
            'data' => $pelajaran,
            'action' => 'edit',
            'pelajaran' => Pelajaran::orderBy('nama')->paginate(15),
        ]);
    }
    public function update(Request $request, Pelajaran $pelajaran)
    {
        $pelajaran->update($request->validate([
            'nama' => 'required'
        ]));

        return redirect()->route('pelajaran.index');
    }
    public function destroy(Pelajaran $pelajaran)
    {
        $pelajaran->delete();
        return back();
    }
}
