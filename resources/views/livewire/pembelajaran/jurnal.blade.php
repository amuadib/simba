@section('title', 'Jurnal Pembelajaran ' . $pembelajaran->keterangan)
<?php

use App\Models\Jurnal;
use App\Models\Pembelajaran;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public $pembelajaran;

    public $pembelajaran_list = [];

    public $pembelajaran_id = null;

    // Salin jurnal mode
    public $mode = 'list';

    public $salinId = null;

    public $salinMateri = '';

    public $salinTanggal = '';

    public $paginationTheme = 'bootstrap';

    public function mount(Pembelajaran $pembelajaran)
    {
        $this->pembelajaran = $pembelajaran;
    }

    public function delete($id)
    {
        $jurnal = Jurnal::find($id);
        if ($jurnal) {
            // Hapus file fisik
            foreach ($jurnal->files as $f) {
                Storage::disk('public')->delete($f->path);
                $f->delete();
            }
            $jurnal->delete();
        }
        $this->dispatch('toast', message: 'Jurnal berhasil dihapus', type: 'success');
    }

    public function openSalin($id)
    {
        $jurnal = Jurnal::find($id);
        $this->mode = 'salin';
        $this->salinId = $id;
        $this->salinTanggal = $jurnal->tanggal;
        $this->salinMateri = $jurnal->materi;
        $this->pembelajaran_list = Pembelajaran::where('tahun_ajaran_id', $this->pembelajaran->tahun_ajaran_id)
            ->orderBy('keterangan')
            ->get();
    }

    public function cancelSalin()
    {
        $this->reset(['mode', 'salinId', 'salinMateri', 'salinTanggal', 'pembelajaran_id']);
    }

    public function salin()
    {
        $this->validate([
            'salinTanggal' => 'required|date',
            'salinMateri' => 'required',
            'pembelajaran_id' => 'required',
        ]);

        Jurnal::create([
            'pembelajaran_id' => $this->pembelajaran_id,
            'tanggal' => $this->salinTanggal,
            'materi' => $this->salinMateri,
        ]);

        $this->reset(['mode', 'salinId', 'salinMateri', 'salinTanggal', 'pembelajaran_id']);
        $this->dispatch('toast', message: 'Jurnal berhasil disalin', type: 'success');
    }

    public function with()
    {
        return [
            'jurnals' => Jurnal::where('pembelajaran_id', $this->pembelajaran->id)
                ->withCount('files')
                ->orderByDesc('tanggal')
                ->paginate(10),
        ];
    }
};

?>
<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Jurnal Pembelajaran</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Dokumentasi kegiatan belajar mengajar: <strong class="text-slate-700 dark:text-slate-200">{{ $pembelajaran->keterangan }}</strong></p>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    {{-- ACTION BAR NAVIGASI --}}
    <div class="flex items-center justify-between gap-2.5 flex-wrap mb-4">
        <a href="{{ route('pembelajaran.index') }}" wire:navigate class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
            <i class="bi bi-arrow-left"></i>
            <span>Kembali ke Pembelajaran</span>
        </a>
        <a href="{{ route('pembelajaran.jurnal.create', $pembelajaran) }}" wire:navigate
            class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold shadow-md">
            <i class="bi bi-plus-circle"></i>
            <span>Tambah</span>
        </a>
    </div>

    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">

        {{-- FORM SALIN JURNAL (tetap inline karena tidak perlu upload file) --}}
        @if ($mode === 'salin')
            <div class="p-4 md:p-5 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs mb-6">
                <h6 class="font-bold text-slate-800 dark:text-slate-100 mb-3 flex items-center gap-2">
                    <i class="bi bi-copy text-sky-500"></i>
                    <span>Salin Jurnal ke Pembelajaran Lain</span>
                </h6>
                <form wire:submit="salin" class="row g-2.5 items-center">
                    <div class="col-md-3">
                        <input type="date" wire:model="salinTanggal" class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('salinTanggal') is-invalid @enderror" required>
                        @error('salinTanggal') <div class="invalid-feedback text-xs">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-4">
                        <input wire:model="salinMateri" class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('salinMateri') is-invalid @enderror"
                            placeholder="Materi Pembelajaran" required>
                        @error('salinMateri') <div class="invalid-feedback text-xs">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3">
                        <select wire:model="pembelajaran_id" class="form-select rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs @error('pembelajaran_id') is-invalid @enderror" required>
                            <option value="">Pilih Pembelajaran Tujuan</option>
                            @foreach ($pembelajaran_list as $p)
                                <option value="{{ $p->id }}">{{ $p->keterangan }}</option>
                            @endforeach
                        </select>
                        @error('pembelajaran_id') <div class="invalid-feedback text-xs">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-2 flex items-center gap-2">
                        <button type="submit" class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md">
                            <i class="bi bi-copy text-sm"></i> Salin
                        </button>
                        <button type="button" wire:click="cancelSalin" class="btn btn-outline-secondary btn-sm rounded-xl px-3 py-2 text-xs font-semibold">Batal</button>
                    </div>
                </form>
            </div>
        @endif

        {{-- TOOLBAR: RIWAYAT, PRESENSI & DAFTAR NILAI --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-200/80 dark:border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-sky-500/10 text-sky-600 dark:text-cyan-400 flex items-center justify-center text-sm border border-sky-500/20 shadow-2xs">
                    <i class="bi bi-journal-text"></i>
                </div>
                <div>
                    <h6 class="text-xs font-bold text-slate-800 dark:text-slate-100 font-display m-0">Riwayat Jurnal Pertemuan</h6>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">Total {{ $jurnals->total() }} pertemuan tercatat</span>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route('pembelajaran.presensi.create', $pembelajaran) }}?tanggal={{ date('Y-m-d') }}" wire:navigate
                    class="btn btn-outline-success btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                    <i class="bi bi-calendar-check"></i>
                    <span>Presensi Hari Ini</span>
                </a>
                <a href="{{ route('pembelajaran.jurnal.nilai.index', $pembelajaran) }}" wire:navigate
                    class="btn btn-outline-danger btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                    <i class="bi bi-star"></i>
                    <span>Daftar Nilai Siswa</span>
                </a>
            </div>
        </div>

        {{-- TAMPILAN MOBILE: KARTU JURNAL (LAYAR < MD) --}}
        <div class="block md:hidden space-y-3">
            @forelse ($jurnals as $j)
                <div wire:key="mobile-jurnal-{{ $j->id }}" class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-3.5 shadow-xs">
                    {{-- Header Kartu: Tanggal & Aksi --}}
                    <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-slate-100 dark:border-slate-800">
                        <div class="font-bold text-xs text-slate-700 dark:text-slate-200 flex items-center gap-1.5 font-display">
                            <i class="bi bi-calendar3 text-sky-500"></i>
                            <span>{{ \Carbon\Carbon::parse($j->tanggal)->locale('id_ID')->isoFormat('dddd, DD MMMM YYYY') }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('pembelajaran.jurnal.show', [$pembelajaran->id, $j->id]) }}" wire:navigate
                                class="w-7 h-7 rounded-lg text-slate-500 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-slate-800 flex items-center justify-center text-xs transition" title="Lihat Detail">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button wire:click="openSalin('{{ $j->id }}')"
                                class="w-7 h-7 rounded-lg text-slate-500 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-slate-800 flex items-center justify-center text-xs transition" title="Salin Jurnal">
                                <i class="bi bi-copy"></i>
                            </button>
                            <a href="{{ route('pembelajaran.jurnal.edit', [$pembelajaran->id, $j->id]) }}" wire:navigate
                                class="w-7 h-7 rounded-lg text-slate-500 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-slate-800 flex items-center justify-center text-xs transition" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button wire:click="delete('{{ $j->id }}')"
                                wire:confirm="Hapus jurnal ini beserta semua file lampirannya?" class="w-7 h-7 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-slate-800 flex items-center justify-center text-xs transition" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Isi Materi Pembelajaran --}}
                    <div class="py-2.5 text-xs text-slate-800 dark:text-slate-100 leading-relaxed font-medium">
                        {!! nl2br(e($j->materi)) !!}
                    </div>

                    {{-- Badge file --}}
                    @if ($j->files_count > 0)
                        <div class="mb-2">
                            <a href="{{ route('pembelajaran.jurnal.show', [$pembelajaran->id, $j->id]) }}" wire:navigate
                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-sky-600 dark:text-cyan-400 bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 px-2 py-0.5 rounded-full">
                                <i class="bi bi-paperclip"></i> {{ $j->files_count }} file
                            </a>
                        </div>
                    @endif

                    {{-- Tombol Aksi Utama: Presensi & Nilai --}}
                    <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        <a href="{{ route('pembelajaran.presensi.create', $pembelajaran->id) }}?tanggal={{ $j->tanggal }}"
                            wire:navigate class="btn btn-outline-success btn-sm rounded-xl py-2 text-xs font-bold inline-flex items-center justify-center gap-1.5 shadow-2xs">
                            <i class="bi bi-calendar-check"></i>
                            <span>Presensi</span>
                        </a>
                        <a href="{{ route('pembelajaran.jurnal.nilai.create', [$pembelajaran->id, $j->id]) }}"
                            wire:navigate class="btn btn-outline-primary btn-sm rounded-xl py-2 text-xs font-bold inline-flex items-center justify-center gap-1.5 shadow-2xs">
                            <i class="bi bi-award"></i>
                            <span>Nilai</span>
                        </a>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800 rounded-2xl p-6 text-center text-slate-400 text-xs">
                    <i class="bi bi-journal-x text-2xl d-block mb-1 text-slate-300"></i>
                    Belum ada jurnal pembelajaran.
                </div>
            @endforelse
        </div>

        {{-- TAMPILAN DESKTOP/TABLET: TABEL LENGKAP (LAYAR >= MD) --}}
        <div class="hidden md:block rounded-2xl border border-slate-200/90 dark:border-cyan-500/20 shadow-sm overflow-hidden">
            <div class="overflow-x-auto w-full">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem; min-width: 650px;">
                    <thead class="bg-slate-100/90 dark:bg-slate-800/90 font-display text-xs text-slate-700 dark:text-slate-300">
                        <tr>
                            <th class="py-3 px-4" width="200">Tanggal</th>
                            <th class="py-3 px-4">Materi Pembelajaran</th>
                            <th class="py-3 px-4 text-center" width="80">File</th>
                            <th class="py-3 px-4 text-center whitespace-nowrap" width="320">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($jurnals as $j)
                            <tr wire:key="desktop-jurnal-{{ $j->id }}">
                                <td class="px-4 font-semibold text-slate-700 dark:text-slate-300">
                                    <i class="bi bi-calendar3 me-1 text-sky-500"></i>
                                    {{ \Carbon\Carbon::parse($j->tanggal)->locale('id_ID')->isoFormat('DD MMMM YYYY') }}
                                </td>
                                <td class="px-4 text-slate-800 dark:text-slate-100">
                                    {!! nl2br(e($j->materi)) !!}
                                </td>
                                <td class="px-4 text-center">
                                    @if ($j->files_count > 0)
                                        <a href="{{ route('pembelajaran.jurnal.show', [$pembelajaran->id, $j->id]) }}" wire:navigate
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-sky-600 dark:text-cyan-400 bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 px-2 py-0.5 rounded-full hover:bg-sky-100 transition"
                                            title="Lihat file lampiran">
                                            <i class="bi bi-paperclip"></i> {{ $j->files_count }}
                                        </a>
                                    @else
                                        <span class="text-slate-300 dark:text-slate-600 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center justify-center gap-1.5">
                                        <a href="{{ route('pembelajaran.jurnal.show', [$pembelajaran->id, $j->id]) }}" wire:navigate
                                            class="btn btn-sm btn-outline-secondary rounded-xl text-xs px-2.5 py-1.5" title="Lihat Detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button wire:click="openSalin('{{ $j->id }}')"
                                            class="btn btn-sm btn-outline-info rounded-xl text-xs px-2.5 py-1.5" title="Salin Jurnal">
                                            <i class="bi bi-copy"></i>
                                        </button>
                                        <a href="{{ route('pembelajaran.presensi.create', $pembelajaran->id) }}?tanggal={{ $j->tanggal }}"
                                            wire:navigate
                                            class="btn btn-sm btn-outline-success rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 whitespace-nowrap px-2.5 py-1.5 shadow-2xs"
                                            title="Input Presensi Pertemuan">
                                            <i class="bi bi-calendar-check"></i>
                                            <span>Presensi</span>
                                        </a>
                                        <a href="{{ route('pembelajaran.jurnal.nilai.create', [$pembelajaran->id, $j->id]) }}"
                                            wire:navigate
                                            class="btn btn-sm btn-outline-primary rounded-xl text-xs font-semibold inline-flex items-center gap-1.5 whitespace-nowrap px-2.5 py-1.5 shadow-2xs"
                                            title="Input Nilai Pertemuan">
                                            <i class="bi bi-award"></i>
                                            <span>Nilai</span>
                                        </a>
                                        <a href="{{ route('pembelajaran.jurnal.edit', [$pembelajaran->id, $j->id]) }}" wire:navigate
                                            class="btn btn-sm btn-outline-warning rounded-xl text-xs px-2.5 py-1.5" title="Edit Jurnal">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button wire:click="delete('{{ $j->id }}')"
                                            wire:confirm="Hapus jurnal ini beserta semua file lampirannya?" class="btn btn-sm btn-outline-danger rounded-xl text-xs px-2.5 py-1.5" title="Hapus">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-slate-500 dark:text-slate-400 py-6 text-center">Belum ada jurnal pembelajaran.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $jurnals->links() }}
        </div>
    </div>
</div>
