<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pembelajaran;
use App\Models\TahunAjaran;
use App\Models\Pelajaran;

class PembelajaranController extends Controller
{
    protected $rules = [
        'keterangan' => 'nullable',
        'tahun_ajaran_id' => 'required',
        'pelajaran_id' => 'required',
    ];

    public function anggota(Request $r, Pembelajaran $pembelajaran)
    {
        return view('pembelajaran.anggota', [
            'pembelajaran' => $pembelajaran,
            'anggota' => $pembelajaran->siswa,
        ]);
    }

    public function index()
    {
        return view('pembelajaran.index', [
            'pembelajaran' => Pembelajaran::with('tahunAjaran', 'pelajaran')->orderBy('keterangan', 'asc')->paginate(15),
            'tahunajaran' => TahunAjaran::orderBy('nama', 'desc')->get(),
            'pelajaran' => Pelajaran::orderBy('nama', 'desc')->get(),
            'kelas' => \App\Models\Rombel::orderBy('nama', 'desc')->get(),
            'action' => '',
        ]);
    }
    public function store(Request $r)
    {
        $pembelajaran = Pembelajaran::create($r->validate($this->rules));
        if ($r->kelas_id) {
            foreach (\App\Models\Siswa::where('rombel_id', $r->kelas_id)->get() as $siswa) {
                $pembelajaran->anggota()->create([
                    'siswa_id' => $siswa->id,
                ]);
            }
        }

        return back()->with('success', 'Pembelajaran berhasil ditambahkan');
    }
    public function edit(Pembelajaran $pembelajaran)
    {
        return view('pembelajaran.index', [
            'data' => $pembelajaran,
            'action' => 'edit',
            'pembelajaran' => Pembelajaran::with('tahunAjaran', 'pelajaran')->orderBy('id', 'desc')->paginate(15),
            'tahunajaran' => TahunAjaran::orderBy('nama', 'desc')->get(),
            'pelajaran' => Pelajaran::orderBy('nama', 'desc')->get(),
        ]);
    }
    public function update(Request $request, Pembelajaran $pembelajaran)
    {
        $pembelajaran->update($request->validate($this->rules));

        return redirect()->route('pembelajaran.index')->with('success', 'Pembelajaran berhasil diubah');
    }
    public function destroy(Pembelajaran $pembelajaran)
    {
        $pembelajaran->delete();
        return back()->with('success', 'Pembelajaran berhasil dihapus');
    }
}
