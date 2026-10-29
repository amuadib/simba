@section('title', 'Data Pelajaran')
<?php

use App\Models\Pelajaran;
use Livewire\WithPagination;

new class extends \Livewire\Volt\Component {
    use WithPagination;

    public $nama = '';
    public $editingId = null;

    public $paginationTheme = 'bootstrap';

    public function store()
    {
        $this->validate(['nama' => 'required']);
        Pelajaran::create(['nama' => $this->nama]);
        $this->reset('nama');
        $this->dispatch('toast', message: 'Pelajaran berhasil ditambahkan', type: 'success');
    }

    public function edit($id)
    {
        $pelajaran = Pelajaran::findOrFail($id);
        $this->editingId = $id;
        $this->nama = $pelajaran->nama;
    }

    public function update()
    {
        $this->validate(['nama' => 'required']);
        $pelajaran = Pelajaran::findOrFail($this->editingId);
        $pelajaran->update(['nama' => $this->nama]);

        $this->reset(['nama', 'editingId']);
        $this->dispatch('toast', message: 'Pelajaran berhasil diperbarui', type: 'success');
    }

    public function cancelEdit()
    {
        $this->reset(['nama', 'editingId']);
    }

    public function delete($id)
    {
        Pelajaran::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Pelajaran berhasil dihapus', type: 'success');
    }

    public function with()
    {
        return [
            'pelajaran' => Pelajaran::orderBy('nama')->paginate(15),
        ];
    }
};

?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Data Pelajaran</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Kelola daftar mata pelajaran kurikulum</p>
        </div>

        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        @if ($editingId)
            <div class="p-4 md:p-5 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs mb-6">
                <h6 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
                    <i class="bi bi-pencil-square text-amber-500"></i>
                    <span>Edit Pelajaran</span>
                </h6>
                <form wire:submit="update" class="row g-2.5 items-center">
                    <div class="col">
                        <input wire:model="nama" class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('nama') is-invalid @enderror"
                            placeholder="Nama Pelajaran" required>
                        @error('nama')
                            <div class="invalid-feedback text-xs">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-auto flex items-center gap-2">
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
                    <span>Tambah Pelajaran Baru</span>
                </h6>
                <form wire:submit="store" class="row g-2.5 items-center">
                    <div class="col">
                        <input wire:model="nama" class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('nama') is-invalid @enderror"
                            placeholder="Nama Pelajaran Baru" required>
                        @error('nama')
                            <div class="invalid-feedback text-xs">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md">
                            <i class="bi bi-plus-circle text-sm"></i> Tambah
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-sm overflow-hidden">
            <div class="overflow-x-auto w-full">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="bg-slate-100/90 dark:bg-slate-800/90 font-display text-xs text-slate-700 dark:text-slate-300">
                    <tr>
                        <th class="py-3 px-4">Nama</th>
                        <th class="py-3 px-4" width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pelajaran as $p)
                        <tr wire:key="{{ $p->id }}">
                            <td class="px-4 font-semibold text-slate-800 dark:text-slate-100">{{ $p->nama }}</td>
                            <td class="px-4">
                                <button wire:click="edit('{{ $p->id }}')"
                                    class="btn btn-sm btn-outline-warning rounded-lg" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button wire:click="delete('{{ $p->id }}')" wire:confirm="Hapus data ini?"
                                    class="btn btn-sm btn-outline-danger rounded-lg ms-1" title="Hapus">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center py-5 text-slate-500">Tidak ada data pelajaran</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $pelajaran->links() }}
        </div>
    </div>
</div>
