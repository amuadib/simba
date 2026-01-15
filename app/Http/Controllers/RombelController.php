<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rombel;

class RombelController extends Controller
{
    public function index()
    {
        return view('rombel.index', [
            'rombel' => Rombel::orderBy('tingkat')->paginate(15),
            'action' => '',
        ]);
    }
    public function store(Request $r)
    {
        Rombel::create($r->validate([
            'nama' => 'required',
            'tingkat' => 'integer|required',
        ]));

        return back()->with('success', 'Rombel berhasil ditambahkan');
    }
    public function edit(Rombel $rombel)
    {
        return view('rombel.index', [
            'data' => $rombel,
            'action' => 'edit',
            'rombel' => Rombel::orderBy('nama')->paginate(15),
        ]);
    }
    public function update(Request $request, Rombel $rombel)
    {
        $rombel->update($request->validate([
            'nama' => 'required',
            'tingkat' => 'integer|required',
        ]));

        return redirect()->route('rombel.index');
    }
    public function destroy(Rombel $rombel)
    {
        $rombel->delete();
        return back();
    }
}
