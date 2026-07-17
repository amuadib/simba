<?php

use App\Models\Rombel;
use Livewire\WithPagination;

new class extends \Livewire\Volt\Component {
    use WithPagination;

    public $nama = '';
    public $tingkat = 0;
    public $editingId = null;

    public $paginationTheme = 'bootstrap';

    public function store()
    {
        $this->validate([
            'nama' => 'required',
            'tingkat' => 'required|integer|min:1|max:12',
        ]);

        Rombel::create([
            'nama' => $this->nama,
            'tingkat' => $this->tingkat,
            'tahun_ajaran_id' => session('tahun_ajaran_id'),
        ]);

        $this->reset(['nama', 'tingkat']);
        $this->dispatch('toast', message: 'Rombel berhasil ditambahkan', type: 'success');
    }

    public function edit($id)
    {
        $rombel = Rombel::findOrFail($id);
        $this->editingId = $id;
        $this->nama = $rombel->nama;
        $this->tingkat = $rombel->tingkat;
    }

    public function update()
    {
        $this->validate([
            'nama' => 'required',
            'tingkat' => 'required|integer|min:1|max:12',
        ]);

        $rombel = Rombel::findOrFail($this->editingId);
        $rombel->update([
            'nama' => $this->nama,
            'tingkat' => $this->tingkat,
            'tahun_ajaran_id' => session('tahun_ajaran_id'),
        ]);

        $this->reset(['nama', 'tingkat', 'editingId']);
        $this->dispatch('toast', message: 'Rombel berhasil diperbarui', type: 'success');
    }

    public function cancelEdit()
    {
        $this->reset(['nama', 'tingkat', 'editingId']);
    }

    public function delete($id)
    {
        Rombel::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Rombel berhasil dihapus', type: 'success');
    }

    public function with()
    {
        return [
            'rombels' => Rombel::where('tahun_ajaran_id', session('tahun_ajaran_id'))->orderBy('tingkat')->orderBy('nama')->paginate(15),
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
                ];
            }
        }

        foreach($new_rombel_array as $nama => $data){
            $rombel = Rombel::create([
                'nama' => $nama,
                'tingkat' => $data['tingkat'],
                'tahun_ajaran_id' => $data['tahun_ajaran_id'],
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Data Rombel</h4>
            <small class="text-muted">Daftar seluruh Rombel</small>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">

            @if ($editingId)
                <form wire:submit="update" class="row g-2 mb-3">
                    <div class="col">
                        <input wire:model="nama" class="form-control @error('nama') is-invalid @enderror"
                            placeholder="Nama Rombel" required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col">
                        <select wire:model="tingkat" class="form-control @error('tingkat') is-invalid @enderror">
                            <option value="0">--Pilih Tingkat--</option>
                            @foreach (range(1, 12) as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                        @error('tingkat')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-check-circle"></i>
                            Update</button>
                        <button type="button" wire:click="cancelEdit" class="btn btn-secondary">Batal</button>
                    </div>
                </form>
            @else
                <form wire:submit="store" class="row g-2 mb-3">
                    <div class="col">
                        <input wire:model="nama" class="form-control @error('nama') is-invalid @enderror"
                            placeholder="Nama Rombel Baru" required>
                        @error('nama')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col">
                        <select wire:model="tingkat" class="form-control @error('tingkat') is-invalid @enderror">
                            <option value="0">--Pilih Tingkat--</option>
                            @foreach (range(1, 12) as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                        @error('tingkat')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah</button>
                    </div>
                </form>
            @endif

            <div class="table-responsive">
                <table class="table-bordered table">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>Tingkat</th>
                            <th width="150">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rombels as $r)
                            <tr wire:key="{{ $r->id }}">
                                <td>{{ $r->nama }}</td>
                                <td>{{ $r->tingkat }}</td>
                                <td>
                                    <button wire:click="edit('{{ $r->id }}')"
                                        class="btn btn-sm btn-outline-warning">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button wire:click="delete('{{ $r->id }}')" wire:confirm="Hapus data ini?"
                                        class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center">
                                    Tidak ada data
                                    <div class="mt-2">
                                        <button wire:click="naikkanTingkat" class="btn btn-sm btn-primary">
                                            Proses Kenaikan Kelas Otomatis
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $rombels->links() }}
            </div>
        </div>
    </div>
</div>
