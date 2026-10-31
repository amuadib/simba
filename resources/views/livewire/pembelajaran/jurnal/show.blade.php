@section('title', 'Detail Jurnal – ' . $pembelajaran->keterangan)
<?php

use App\Models\Jurnal;
use App\Models\Pembelajaran;
use Illuminate\Support\Facades\Storage;

new class extends \Livewire\Volt\Component {

    public $pembelajaran;
    public $jurnal;

    public function mount(Pembelajaran $pembelajaran, Jurnal $jurnal)
    {
        $this->pembelajaran = $pembelajaran;
        $this->jurnal       = $jurnal->load('files');
        abort_unless($jurnal->pembelajaran_id === $pembelajaran->id, 404);
    }

    public function delete()
    {
        foreach ($this->jurnal->files as $f) {
            Storage::disk('public')->delete($f->path);
            $f->delete();
        }
        $this->jurnal->delete();
        $this->dispatch('toast', message: 'Jurnal berhasil dihapus', type: 'success');
        $this->redirect(route('pembelajaran.jurnal.index', $this->pembelajaran), navigate: true);
    }
};
?>
<div>
    {{-- PAGE HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Detail Jurnal</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">
                Kelas: <strong class="text-slate-700 dark:text-slate-200">{{ $pembelajaran->keterangan }}</strong>
            </p>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    {{-- ACTION BAR --}}
    <div class="flex items-center justify-between gap-2.5 flex-wrap mb-5">
        <a href="{{ route('pembelajaran.jurnal.index', $pembelajaran) }}" wire:navigate
            class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
            <i class="bi bi-arrow-left"></i>
            <span>Kembali ke Jurnal</span>
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('pembelajaran.jurnal.edit', [$pembelajaran->id, $jurnal->id]) }}" wire:navigate
                class="btn btn-sm btn-outline-warning rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-pencil"></i>
                <span>Edit Jurnal</span>
            </a>
            <button wire:click="delete"
                wire:confirm="Hapus jurnal ini beserta semua file lampirannya?"
                class="btn btn-sm btn-outline-danger rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-trash"></i>
                <span>Hapus</span>
            </button>
        </div>
    </div>

    {{-- PANEL INFO JURNAL --}}
    <div class="glass-panel rounded-3xl p-5 md:p-7 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl mb-5">
        {{-- Tanggal --}}
        <div class="flex items-center gap-3 mb-5 pb-5 border-b border-slate-200/80 dark:border-slate-700/60">
            <div class="w-12 h-12 rounded-2xl bg-sky-500/10 text-sky-600 dark:text-cyan-400 flex items-center justify-center text-xl shadow-inner flex-shrink-0">
                <i class="bi bi-calendar3"></i>
            </div>
            <div>
                <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider m-0">Tanggal Pertemuan</p>
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100 font-display m-0">
                    {{ \Carbon\Carbon::parse($jurnal->tanggal)->locale('id_ID')->isoFormat('dddd, DD MMMM YYYY') }}
                </h2>
            </div>
        </div>

        {{-- Materi --}}
        <div class="mb-5">
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                <i class="bi bi-journal-text text-emerald-500"></i> Materi Pembelajaran
            </p>
            <div class="text-sm text-slate-800 dark:text-slate-100 leading-relaxed whitespace-pre-wrap bg-slate-50/70 dark:bg-slate-800/40 rounded-2xl p-4 border border-slate-200/80 dark:border-slate-700/60">{{ $jurnal->materi ?: '(Tidak ada keterangan materi)' }}</div>
        </div>

        {{-- Quick Links --}}
        <div class="flex items-center gap-2 flex-wrap pt-4 border-t border-slate-200/80 dark:border-slate-700/60">
            <a href="{{ route('pembelajaran.presensi.create', $pembelajaran->id) }}?tanggal={{ $jurnal->tanggal }}"
                wire:navigate class="btn btn-sm btn-outline-success rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-calendar-check"></i> Presensi Pertemuan Ini
            </a>
            <a href="{{ route('pembelajaran.jurnal.nilai.create', [$pembelajaran->id, $jurnal->id]) }}"
                wire:navigate class="btn btn-sm btn-outline-primary rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
                <i class="bi bi-award"></i> Input Nilai
            </a>
        </div>
    </div>

    {{-- PANEL FILE LAMPIRAN --}}
    <div class="glass-panel rounded-3xl p-5 md:p-7 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <div class="flex items-center justify-between gap-3 mb-5">
            <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-display m-0 flex items-center gap-2">
                <i class="bi bi-paperclip text-base"></i>
                File Lampiran
                @if($jurnal->files->count() > 0)
                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-[11px] font-bold">
                        {{ $jurnal->files->count() }}
                    </span>
                @endif
            </h4>
            <a href="{{ route('pembelajaran.jurnal.edit', [$pembelajaran->id, $jurnal->id]) }}" wire:navigate
                class="text-xs text-sky-600 dark:text-cyan-400 hover:underline inline-flex items-center gap-1 font-semibold">
                <i class="bi bi-plus-circle"></i> Tambah File
            </a>
        </div>

        @if ($jurnal->files->count() === 0)
            <div class="text-center py-10 text-slate-400">
                <i class="bi bi-folder-x text-4xl block mb-2 text-slate-300 dark:text-slate-600"></i>
                <p class="text-sm">Belum ada file lampiran untuk jurnal ini.</p>
                <a href="{{ route('pembelajaran.jurnal.edit', [$pembelajaran->id, $jurnal->id]) }}" wire:navigate
                    class="btn-logo-gradient inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-md mt-3">
                    <i class="bi bi-upload"></i> Upload File
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($jurnal->files as $f)
                    @php
                        $url       = Storage::disk('public')->url($f->path);
                        $isImg     = str_starts_with($f->mime_type ?? '', 'image/');
                        $isPdf     = $f->mime_type === 'application/pdf';
                        $isVid     = str_starts_with($f->mime_type ?? '', 'video/');
                        $isAud     = str_starts_with($f->mime_type ?? '', 'audio/');
                        $previewable = $isImg || $isPdf || $isVid || $isAud;
                    @endphp

                    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden shadow-xs flex flex-col">

                        {{-- ===== PREVIEW AREA ===== --}}
                        @if ($isImg)
                            <a href="{{ $url }}" target="_blank" class="block flex-shrink-0 bg-slate-100 dark:bg-slate-800">
                                <img src="{{ $url }}" alt="{{ $f->nama_file }}"
                                    class="w-full object-cover"
                                    style="max-height: 220px; object-fit: contain;">
                            </a>

                        @elseif ($isPdf)
                            <div class="relative bg-slate-100 dark:bg-slate-800" style="height: 280px;">
                                <iframe src="{{ $url }}#toolbar=0&navpanes=0&scrollbar=0"
                                    class="w-full h-full border-0"
                                    title="{{ $f->nama_file }}">
                                </iframe>
                                {{-- Overlay link supaya bisa klik buka full --}}
                                <a href="{{ $url }}" target="_blank"
                                    class="absolute top-2 right-2 bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm text-xs font-semibold text-slate-700 dark:text-slate-200 px-2.5 py-1 rounded-lg shadow hover:bg-white dark:hover:bg-slate-700 inline-flex items-center gap-1 transition">
                                    <i class="bi bi-box-arrow-up-right text-sky-500"></i> Buka
                                </a>
                            </div>

                        @elseif ($isVid)
                            <div class="bg-black flex-shrink-0" style="max-height: 240px;">
                                <video src="{{ $url }}" controls controlsList="nodownload"
                                    class="w-full" style="max-height: 240px; object-fit: contain;">
                                    Browser Anda tidak mendukung pemutaran video.
                                </video>
                            </div>

                        @elseif ($isAud)
                            <div class="flex items-center justify-center px-4 py-6 bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-950/30 dark:to-purple-950/30 flex-shrink-0">
                                <div class="w-full">
                                    <div class="flex justify-center mb-3">
                                        <div class="w-14 h-14 rounded-2xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-500 text-2xl shadow-inner">
                                            <i class="bi bi-music-note-beamed"></i>
                                        </div>
                                    </div>
                                    <audio src="{{ $url }}" controls class="w-full"></audio>
                                </div>
                            </div>

                        @else
                            {{-- Non-previewable: icon besar dengan link download --}}
                            <a href="{{ $url }}" target="_blank" download="{{ $f->nama_file }}"
                                class="flex flex-col items-center justify-center gap-2 py-8 px-4 bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-800 transition flex-shrink-0 text-center">
                                <i class="bi {{ $f->iconClass() }} text-5xl"></i>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                    {{ strtoupper(pathinfo($f->nama_file, PATHINFO_EXTENSION)) }}
                                </span>
                                <span class="text-[11px] text-sky-600 dark:text-cyan-400 font-semibold inline-flex items-center gap-1">
                                    <i class="bi bi-download"></i> Unduh File
                                </span>
                            </a>
                        @endif

                        {{-- ===== INFO FOOTER ===== --}}
                        <div class="p-3 flex items-center justify-between gap-2 border-t border-slate-100 dark:border-slate-800 mt-auto">
                            <div class="min-w-0">
                                <p class="text-[12px] font-semibold text-slate-700 dark:text-slate-200 truncate leading-tight m-0" title="{{ $f->nama_file }}">
                                    {{ $f->nama_file }}
                                </p>
                                <p class="text-[10px] text-slate-400 m-0 mt-0.5">{{ $f->ukuranFormatted() }}</p>
                            </div>
                            <a href="{{ $url }}" target="_blank" download="{{ $f->nama_file }}"
                                class="flex-shrink-0 w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-sky-100 dark:hover:bg-sky-900/40 text-slate-500 hover:text-sky-600 flex items-center justify-center text-sm transition"
                                title="Unduh {{ $f->nama_file }}">
                                <i class="bi bi-download"></i>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

