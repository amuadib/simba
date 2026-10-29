@section('title', 'Data Rombel')

<?php

use App\Models\Rombel;
use Livewire\WithPagination;

new class extends \Livewire\Volt\Component {
    use WithPagination;

    public $nama = '';
    public $tingkat = 0;
    public $tahun_ajaran_id = null;
    public $lembaga_id = null;
    public $editingId = null;

    public function mount()
    {
        $this->tahun_ajaran_id = session('tahun_ajaran_id');
    }

    public $paginationTheme = 'bootstrap';

    public function store()
    {
        $this->validate([
            'nama' => 'required',
            'tingkat' => 'required|integer|min:1|max:12',
            'tahun_ajaran_id' => 'required',
            'lembaga_id' => 'required',
        ]);

        Rombel::create([
            'nama' => $this->nama,
            'tingkat' => $this->tingkat,
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'lembaga_id' => $this->lembaga_id,
        ]);

        $this->reset(['nama', 'tingkat', 'tahun_ajaran_id', 'lembaga_id']);
        $this->tahun_ajaran_id = session('tahun_ajaran_id');
        $this->dispatch('toast', message: 'Rombel berhasil ditambahkan', type: 'success');
    }

    public function edit($id)
    {
        $rombel = Rombel::findOrFail($id);
        $this->editingId = $id;
        $this->nama = $rombel->nama;
        $this->tingkat = $rombel->tingkat;
        $this->tahun_ajaran_id = $rombel->tahun_ajaran_id;
        $this->lembaga_id = $rombel->lembaga_id;
    }

    public function update()
    {
        $this->validate([
            'nama' => 'required',
            'tingkat' => 'required|integer|min:1|max:12',
            'tahun_ajaran_id' => 'required',
            'lembaga_id' => 'required',
        ]);

        $rombel = Rombel::findOrFail($this->editingId);
        $rombel->update([
            'nama' => $this->nama,
            'tingkat' => $this->tingkat,
            'tahun_ajaran_id' => $this->tahun_ajaran_id,
            'lembaga_id' => $this->lembaga_id,
        ]);

        $this->reset(['nama', 'tingkat', 'tahun_ajaran_id', 'lembaga_id', 'editingId']);
        $this->tahun_ajaran_id = session('tahun_ajaran_id');
        $this->dispatch('toast', message: 'Rombel berhasil diperbarui', type: 'success');
    }

    public function cancelEdit()
    {
        $this->reset(['nama', 'tingkat', 'tahun_ajaran_id', 'lembaga_id', 'editingId']);
        $this->tahun_ajaran_id = session('tahun_ajaran_id');
    }

    public function delete($id)
    {
        Rombel::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Rombel berhasil dihapus', type: 'success');
    }

    public function with()
    {
        return [
            'rombels' => Rombel::with('tahunAjaran')->where('tahun_ajaran_id', session('tahun_ajaran_id'))->orderBy('tingkat')->orderBy('nama')->paginate(15),
            'tahunAjarans' => \App\Models\TahunAjaran::orderBy('nama', 'desc')->get(),
        ];
    }

    public function naikkanTingkat()
    {
        $tahun_ajaran_sekarang = session('tahun_ajaran_nama');
        $tahun_ajaran_sekarang_id = session('tahun_ajaran_id');
        $tahun_ajaran_lalu = explode('/', $tahun_ajaran_sekarang)[0] - 1 . '/' . explode('/', $tahun_ajaran_sekarang)[1] - 1;
        $tahun_ajaran_lalu_id = \App\Models\TahunAjaran::where('nama', $tahun_ajaran_lalu)->first()->id;
              
        $new_rombel_array=[];
        $rollback_query=[];
        foreach (Rombel::where('tahun_ajaran_id', $tahun_ajaran_lalu_id)->get() as $rombel) {
            if(!in_array($rombel->tingkat, [6,9,12])){
                $prefix='';
                if(count(explode(' ',$rombel->nama))>1){
                    $prefix=explode(' ',$rombel->nama)[1];
                }
                $tingkat = $rombel->tingkat+1;  
                $nama = trim(toRoman($tingkat) . ' ' . $prefix);
                
                $new_rombel_array[$nama] = [
                    'tingkat' => $tingkat,
                    'tahun_ajaran_id' => $tahun_ajaran_sekarang_id,
                    'lembaga_id' => $rombel->lembaga_id,
                ];
            }else{
                \App\Models\Siswa::where('status',1)->where('rombel_id', $rombel->id)->update([
                    'status' => 2,
                ]);
                $rollback_query[]="UPDATE siswa SET status=1 WHERE rombel_id=".$rombel->id." AND status=2;";
                
            }
        }
        if(!$new_rombel_array){
            $this->dispatch('toast', message: 'Tidak ada rombel yang bisa naik tingkat', type: 'error');
            return;
        }
        foreach(Rombel::where('tahun_ajaran_id', $tahun_ajaran_sekarang_id)->get() as $rombel){
            if(!isset($new_rombel_array[$rombel->nama])){
                $new_rombel_array[$rombel->nama] = [
                    'tingkat' => $rombel->tingkat,
                    'tahun_ajaran_id' => $tahun_ajaran_sekarang_id,
                    'lembaga_id' => $rombel->lembaga_id,
                ];
            }
        }

        foreach($new_rombel_array as $nama => $data){
            $rombel = Rombel::create([
                'nama' => $nama,
                'tingkat' => $data['tingkat'],
                'tahun_ajaran_id' => $data['tahun_ajaran_id'],
                'lembaga_id' => $data['lembaga_id'],
            ]);

            $rollback_query[]="DELETE FROM rombel WHERE id=\"".$rombel->id."\";";
            
            //Rombel lama
            $prefix='';
            if(count(explode(' ',$rombel->nama))>1){
                $prefix=explode(' ',$rombel->nama)[1];
            }
            $tingkat = $rombel->tingkat-1;  
            $nama = trim(toRoman($tingkat) . ' ' . $prefix);
            $old_rombel = Rombel::where('tahun_ajaran_id', $tahun_ajaran_lalu_id)->where('nama', $nama)->first();
            if(isset($old_rombel->id)){
                \App\Models\Siswa::where('status',1)->where('rombel_id', $old_rombel->id)->update([
                    'rombel_id' => $rombel->id,
                ]);
                $rollback_query[]="UPDATE siswa SET rombel_id=\"".$old_rombel->id."\" WHERE rombel_id=\"".$rombel->id."\";";
            }
        }
        \Illuminate\Support\Facades\Log::info('Naikkan tingkat Rombel - Rollback Query', $rollback_query);
        $this->dispatch('toast', message: 'Rombel berhasil dinaikkan tingkatnya', type: 'success');
    }
};

?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Data Rombel</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Kelola data rombongan belajar dan tingkatan kelas</p>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <div>
            {{-- FORM AREA --}}
            @if ($editingId)
                <div class="p-4 md:p-5 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs mb-6">
                    <h6 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
                        <i class="bi bi-pencil-square text-amber-500"></i>
                        <span>Edit Data Rombel</span>
                    </h6>
                    <form wire:submit="update" class="row g-2.5 items-end">
                        <div class="col-md-3">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Nama Rombel</label>
                            <input wire:model="nama" class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('nama') is-invalid @enderror"
                                placeholder="Nama Rombel" required>
                            @error('nama')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Tingkat</label>
                            <select wire:model="tingkat" class="form-select rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('tingkat') is-invalid @enderror">
                                <option value="0">-- Tingkat --</option>
                                @foreach (range(1, 12) as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                            @error('tingkat')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Tahun Ajaran</label>
                            <select wire:model="tahun_ajaran_id" class="form-select rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('tahun_ajaran_id') is-invalid @enderror">
                                <option value="">-- Pilih Tahun Ajaran --</option>
                                @foreach ($tahunAjarans as $ta)
                                    <option value="{{ $ta->id }}">{{ $ta->nama }}</option>
                                @endforeach
                            </select>
                            @error('tahun_ajaran_id')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Lembaga</label>
                            <select wire:model="lembaga_id" class="form-select rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('lembaga_id') is-invalid @enderror">
                                <option value="">-- Pilih Lembaga --</option>
                                @foreach (daftar_lembaga() as $id => $namaLembaga)
                                    <option value="{{ $id }}">{{ $namaLembaga }}</option>
                                @endforeach
                            </select>
                            @error('lembaga_id')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2 flex items-center gap-2">
                            <button type="submit" class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md">
                                <i class="bi bi-check2-circle text-sm"></i> Update
                            </button>
                            <button type="button" wire:click="cancelEdit" class="btn btn-outline-secondary btn-sm rounded-xl px-4 py-2 text-xs font-semibold">Batal</button>
                        </div>
                    </form>
                </div>
            @else
                <div class="p-4 md:p-5 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs mb-6">
                    <h6 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
                        <i class="bi bi-plus-circle-fill text-sky-500"></i>
                        <span>Tambah Rombel Baru</span>
                    </h6>
                    <form wire:submit="store" class="row g-2.5 items-end">
                        <div class="col-md-3">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Nama Rombel</label>
                            <input wire:model="nama" class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('nama') is-invalid @enderror"
                                placeholder="Cth: VII-A, X-IPA" required>
                            @error('nama')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Tingkat</label>
                            <select wire:model="tingkat" class="form-select rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('tingkat') is-invalid @enderror">
                                <option value="0">-- Tingkat --</option>
                                @foreach (range(1, 12) as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                            @error('tingkat')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Tahun Ajaran</label>
                            <select wire:model="tahun_ajaran_id" class="form-select rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('tahun_ajaran_id') is-invalid @enderror">
                                <option value="">-- Pilih Tahun Ajaran --</option>
                                @foreach ($tahunAjarans as $ta)
                                    <option value="{{ $ta->id }}">{{ $ta->nama }}</option>
                                @endforeach
                            </select>
                            @error('tahun_ajaran_id')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300">Lembaga</label>
                            <select wire:model="lembaga_id" class="form-select rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('lembaga_id') is-invalid @enderror">
                                <option value="">-- Pilih Lembaga --</option>
                                @foreach (daftar_lembaga() as $id => $namaLembaga)
                                    <option value="{{ $id }}">{{ $namaLembaga }}</option>
                                @endforeach
                            </select>
                            @error('lembaga_id')
                                <div class="invalid-feedback text-xs">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md w-full justify-center">
                                <i class="bi bi-plus-circle text-sm"></i> Tambah
                            </button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-sm overflow-hidden">
                <div class="overflow-x-auto w-full">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem; min-width: 580px;">
                    <thead class="bg-slate-100/90 dark:bg-slate-800/90 font-display text-xs text-slate-700 dark:text-slate-300">
                        <tr>
                            <th class="py-3 px-3">Nama Rombel</th>
                            <th class="py-3 px-3">Tingkat</th>
                            <th class="py-3 px-3">Tahun Ajaran</th>
                            <th class="py-3 px-3">Lembaga</th>
                            <th class="text-center py-3 px-3" width="130">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rombels as $r)
                            <tr wire:key="{{ $r->id }}">
                                <td class="py-3 px-3 font-bold text-slate-800 dark:text-slate-100">{{ $r->nama }}</td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded-lg text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        Kelas {{ $r->tingkat }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-slate-600 dark:text-slate-300">{{ $r->tahunAjaran->nama ?? '-' }}</td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded-lg text-xs font-semibold bg-sky-500/10 text-sky-600 dark:text-cyan-400 border border-sky-500/20">
                                        {{ nama_lembaga($r->lembaga_id) }}
                                    </span>
                                </td>
                                <td class="text-center py-3 px-3">
                                    <div class="btn-group btn-group-sm">
                                        <button wire:click="edit('{{ $r->id }}')"
                                            class="btn btn-sm btn-outline-warning rounded-lg" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button wire:click="delete('{{ $r->id }}')" wire:confirm="Hapus data ini?"
                                            class="btn btn-sm btn-outline-danger rounded-lg ms-1" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-slate-400 py-5">
                                    <i class="bi bi-diagram-3 text-4xl block mb-2 opacity-50"></i>
                                    Tidak ada data rombel
                                    <div class="mt-3">
                                        <button wire:click="naikkanTingkat" class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md">
                                            <i class="bi bi-arrow-up-right-circle text-sm"></i> Proses Kenaikan Kelas Otomatis
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            @if ($rombels->hasPages())
                <div class="mt-4">
                    {{ $rombels->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
