<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\Tag;
use App\Imports\SiswaImport;
use App\Models\Rombel;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class SiswaController extends Controller
{
    protected $paginate = 25;
    public function index()
    {
        return view('siswa.index', [
            'siswa' => Siswa::with('rombel')
                ->orderBy('nama')
                ->when(request('rombel_id'), function ($q, $rombel_id) {
                    $q->where('rombel_id', $rombel_id);
                })
                ->when(request('tag_id'), function ($q, $tag_id) {
                    $q->whereHas('tags', function ($q) use ($tag_id) {
                        $q->where('tag_id', $tag_id);
                    });
                })
                ->paginate($this->paginate)
                ->withQueryString(),
            'rombel' => Rombel::orderBy('tingkat')->get(),
            'tags' => Tag::orderBy('nama')->get(),
            'action' => '',
        ]);
    }

    public function store(Request $request)
    {
        $siswa = Siswa::create($request->validate([
            'nama' => 'required',
            'nisn' => 'nullable',
            'rombel_id' => 'required'
        ]));
        $siswa->tags()->sync($request->tags ?? []);

        if ($request->tags_new) {
            foreach ($request->tags_new as $name) {
                $tag = Tag::firstOrCreate(
                    ['nama' => $name]
                );
                $siswa->tags()->attach($tag->id);
            }
        }

        return back()->with('success', 'Siswa berhasil ditambahkan');
    }
    public function edit(Siswa $siswa)
    {
        return view('siswa.index', [
            'data' => $siswa,
            'action' => 'edit',
            'rombel' => Rombel::orderBy('tingkat')->get(),
            'tags' => Tag::orderBy('nama')->get(),
            'siswa' => Siswa::with('rombel')->orderBy('nama')->paginate($this->paginate),
        ]);
    }
    public function update(Request $request, Siswa $siswa)
    {
        $siswa->update($request->validate([
            'nama' => 'required',
            'nisn' => 'nullable',
            'rombel_id' => 'required'
        ]));

        $siswa->tags()->sync($request->tags ?? []);
        if ($request->tags_new) {
            foreach ($request->tags_new as $name) {
                $tag = Tag::firstOrCreate([
                    'nama' => $name,
                ]);

                $siswa->tags()->attach($tag->id);
            }
        }

        return redirect()->route('siswa.index')->with('success', 'Siswa berhasil diperbarui');
    }
    public function destroy(Siswa $siswa)
    {
        \DB::transaction(function () use ($siswa) {
            $siswa->tags()->detach(); // hapus pivot saja
            $siswa->delete();
        });
        return back()->with('success', 'Siswa berhasil dihapus');
    }
    public function import(Request $r)
    {
        $r->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'rombel_id' => 'required'
        ]);
        Excel::import(new SiswaImport($r->get('rombel_id')), $r->file('file'));
        return back()->with('success', 'Import berhasil');
    }
}
