<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Imports\PegawaiImport;
use App\Exports\PegawaiExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    protected $paginate = 25;

    public function index()
    {
        return view('livewire.pegawai.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'niy' => 'nullable|string|max:100',
            'nuptk' => 'nullable|string|max:100',
            'tmt' => 'nullable|date',
            'jenis_kelamin' => 'required|in:l,p,L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'nik' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
            'no_hp' => 'nullable|string|max:50',
            'gelar_depan' => 'nullable|string|max:50',
            'gelar_belakang' => 'nullable|string|max:50',
            'foto' => 'nullable|image|max:2048',
            'lembaga_id' => 'nullable|integer',
            'status' => 'required',
        ]);

        $validated['jenis_kelamin'] = strtolower($validated['jenis_kelamin']);
        if (empty($validated['niy'])) {
            $validated['niy'] = Pegawai::generateNiy($validated['tmt'] ?? null, $validated['jenis_kelamin']);
        }

        if ($request->hasFile('foto')) {
            $validated['foto'] = $request->file('foto')->store('pegawai', 'public');
        }

        Pegawai::create($validated);

        return back()->with('success', 'Pegawai berhasil ditambahkan');
    }

    public function update(Request $request, Pegawai $pegawai)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'niy' => 'nullable|string|max:100',
            'nuptk' => 'nullable|string|max:100',
            'tmt' => 'nullable|date',
            'jenis_kelamin' => 'required|in:l,p,L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'nik' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
            'no_hp' => 'nullable|string|max:50',
            'gelar_depan' => 'nullable|string|max:50',
            'gelar_belakang' => 'nullable|string|max:50',
            'foto' => 'nullable|image|max:2048',
            'lembaga_id' => 'nullable|integer',
            'status' => 'required',
        ]);

        $validated['jenis_kelamin'] = strtolower($validated['jenis_kelamin']);
        if (empty($validated['niy'])) {
            $validated['niy'] = Pegawai::generateNiy($validated['tmt'] ?? $pegawai->tmt, $validated['jenis_kelamin']);
        }

        if ($request->hasFile('foto')) {
            if ($pegawai->foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($pegawai->foto)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($pegawai->foto);
            }
            $validated['foto'] = $request->file('foto')->store('pegawai', 'public');
        }

        $pegawai->update($validated);

        return back()->with('success', 'Pegawai berhasil diperbarui');
    }

    public function destroy(Pegawai $pegawai)
    {
        if ($pegawai->foto && \Illuminate\Support\Facades\Storage::disk('public')->exists($pegawai->foto)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($pegawai->foto);
        }
        $pegawai->delete();
        return back()->with('success', 'Pegawai berhasil dihapus');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
        ]);

        $import = new PegawaiImport();
        Excel::import($import, $request->file('file'));

        $results = $import->getResults();
        $errorsCount = count($results['errors']);
        $message = "Impor data pegawai selesai. Sukses: {$results['success']}" . ($errorsCount > 0 ? ", Gagal: {$errorsCount}" : "");

        return back()->with($errorsCount > 0 ? 'warning' : 'success', $message);
    }

    public function export()
    {
        return Excel::download(new PegawaiExport([], 'data'), 'SIMBA-ekspor-pegawai-' . date('YmdHis') . '.xlsx');
    }

    public function exportTemplate()
    {
        return Excel::download(new PegawaiExport([], 'template-import'), 'SIMBA-template-import-pegawai-' . date('YmdHis') . '.xlsx');
    }
}
