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
 
    public function bulkAddTag(Request $request){
        $request->validate([
            'tag_id' => 'required',
            'rombel_id' => 'nullable',
            'status' => 'nullable',
            'q' => 'nullable',
        ]);
        
        foreach (Siswa::when($request->rombel_id, function ($q, $rombel_id) {
                    $q->where('rombel_id', $rombel_id);
                })
                ->when($request->status, function ($q, $status) {
                    $q->where('status', $status);
                })
                ->when($request->q, function ($query, $q) {
                    $query->where('nama', 'like', "%{$q}%");
                })->get() as $s) {
            if($s->tags()->where('tag_id', $request->tag_id)->exists()){
                continue;
            }
            $s->tags()->attach($request->tag_id);
        }
        return back()->with('success', 'Tag berhasil ditambahkan ke siswa');
    }  
    public function export($from = 'session'){
        if($from=='session'){
            $selected = session('selected_siswa', []);
            // session()->forget('selected_siswa');
        }else{
            $selected = Siswa::when(request('rombel_id'), function ($q, $rombel_id) {
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
                ->pluck('id')->toArray();
        }
        return Excel::download(new \App\Exports\SiswaExport($selected), 'siswa-' . now()->format('YmdHis') . '.xlsx');
    }
    public function hapusPreviewExport($id): JsonResponse
    {
        if ($id == 'all') {
            if(count(session('selected_siswa', [])) == 0){
                return response()->json([
                    'success' => false,
                    'message' => 'Belum ada data siswa'
                ], 422);
            }
            session()->forget('selected_siswa');
            return response()->json([
                'success' => true,
                'message' => 'Semua siswa berhasil dihapus',
                'count' => 0
            ], 200);
        }
        if (!in_array($id, session('selected_siswa', []))) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan dalam daftar pilihan'
            ], 422);
        }

        $selected = session('selected_siswa', []);
        $selected = array_values(array_diff($selected, [$id]));
        session(['selected_siswa' => $selected]);

        return response()->json([
            'success' => true,
            'message' => 'Siswa berhasil dihapus',
            'count' => count($selected)
        ]);
    }
    public function previewExport()
    {
        $selected = session('selected_siswa', []);
        $siswa = Siswa::with('rombel')
            ->whereIn('id', $selected)
            ->orderBy('nama')
            ->get();

        if (request()->ajax()) {
            return response()->json([
                'html' => view('siswa.preview-export', compact('siswa'))->render()
            ]);
        }

        return view('siswa.preview-export', compact('siswa'));
    }
    public function pilih(Request $request)
    {
        $siswa = $request->validate([
            'siswa_id' => 'required|exists:siswa,id'
        ]);

        session()->push('selected_siswa', $siswa['siswa_id']);

        return response()->json([
            'success' => true,
            'message' => 'Siswa berhasil dipilih',
            'count' => count(session('selected_siswa'))
        ]);

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
