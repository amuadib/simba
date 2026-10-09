<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\Tag;
use App\Imports\SiswaImport;
use App\Models\Rombel;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiswaController extends Controller
{
    protected $paginate = 25;

    public function bulkAddTag(Request $request)
    {
        $request->validate([
            'tag_id' => 'required',
            'rombel_id' => 'nullable',
            'status' => 'nullable',
            'q' => 'nullable',
        ]);

        foreach (
            Siswa::when($request->rombel_id, function ($q, $rombel_id) {
                $q->where('rombel_id', $rombel_id);
            })
                ->when($request->status, function ($q, $status) {
                    $q->where('status', $status);
                })
                ->when($request->q, function ($query, $q) {
                    $query->where('nama', 'like', "%{$q}%");
                })->get() as $s
        ) {
            if ($s->tags()->where('tag_id', $request->tag_id)->exists()) {
                continue;
            }
            $s->tags()->attach($request->tag_id);
        }
        return back()->with('success', 'Tag berhasil ditambahkan ke siswa');
    }
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
                ->when(request('status'), function ($q, $status) {
                    $q->where('status', $status);
                })
                ->when(request('q'), function ($query, $q) {
                    $query->where('nama', 'like', "%{$q}%");
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
            'panggilan' => 'nullable',
            'jenis_kelamin' => 'nullable',
            'nisn' => 'nullable',
            'status' => 'required|in:1,2,3,4,5,6',
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
            'panggilan' => 'nullable',
            'jenis_kelamin' => 'nullable',
            'nisn' => 'nullable',
            'status' => 'required|in:1,2,3,4,5,6',
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

        return redirect(route('siswa.index', ['page' => request('page'), 'rombel_id' => request('rombel_id'), 'tag_id' => request('tag_id'), 'status' => request('status'), 'q' => request('q')]) . '#tr-' . $siswa->id)
            ->with('success', 'Siswa berhasil diperbarui');
    }

    public function show(Siswa $siswa)
    {
        return view('siswa.index', [
            'data' => $siswa,
            'action' => 'show',
            'rombel' => Rombel::orderBy('tingkat')->get(),
            'tags' => Tag::orderBy('nama')->get(),
            'siswa' => Siswa::with('rombel')->orderBy('nama')->paginate($this->paginate),
        ]);
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
