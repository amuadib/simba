@section('title', ($jurnal ? 'Edit' : 'Tambah') . ' Jurnal – ' . $pembelajaran->keterangan)
<?php

use App\Models\Jurnal;
use App\Models\JurnalFile;
use App\Models\Pembelajaran;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

new class extends \Livewire\Volt\Component {
    use WithFileUploads;

    // Route model bindings
    public $pembelajaran;
    public $jurnal = null; // null = tambah, model = edit

    // Form fields
    public string $tanggal    = '';
    public string $materi     = '';
    public array  $files      = []; // new uploads (Livewire TemporaryUploadedFile[])
    public array  $deletedIds = []; // existing file IDs to delete on save

    public function mount(Pembelajaran $pembelajaran, ?Jurnal $jurnal = null)
    {
        $this->pembelajaran = $pembelajaran;
        $this->jurnal       = $jurnal?->exists ? $jurnal : null;

        if ($this->jurnal) {
            $this->tanggal = $this->jurnal->tanggal;
            $this->materi  = $this->jurnal->materi ?? '';
        } else {
            $this->tanggal = date('Y-m-d');
        }
    }

    public function save()
    {
        $this->validate([
            'tanggal'   => 'required|date',
            'materi'    => 'required|string|max:2000',
            'files.*'   => 'nullable|file|max:20480', // 20 MB per file
        ], [
            'materi.required' => 'Materi pembelajaran wajib diisi.',
            'files.*.max'     => 'Ukuran file maksimal 20 MB.',
        ]);

        if ($this->jurnal) {
            // --- UPDATE ---
            $this->jurnal->update([
                'tanggal' => $this->tanggal,
                'materi'  => $this->materi,
            ]);

            // Hapus file yang ditandai deleted
            foreach ($this->deletedIds as $fid) {
                $jf = JurnalFile::find($fid);
                if ($jf) {
                    Storage::disk('public')->delete($jf->path);
                    $jf->delete();
                }
            }

            $jurnalId = $this->jurnal->id;
            $msg = 'Jurnal berhasil diperbarui';
        } else {
            // --- CREATE ---
            $jurnal = Jurnal::create([
                'pembelajaran_id' => $this->pembelajaran->id,
                'tanggal'         => $this->tanggal,
                'materi'          => $this->materi,
            ]);
            $jurnalId = $jurnal->id;
            $msg = 'Jurnal berhasil ditambahkan';
        }

        // Simpan file baru
        foreach ($this->files as $file) {
            $path = $file->store('jurnal-files/' . $jurnalId, 'public');
            JurnalFile::create([
                'jurnal_id'  => $jurnalId,
                'nama_file'  => $file->getClientOriginalName(),
                'path'       => $path,
                'mime_type'  => $file->getMimeType(),
                'ukuran'     => $file->getSize(),
            ]);
        }

        $this->dispatch('toast', message: $msg, type: 'success');
        $this->redirect(route('pembelajaran.jurnal.index', $this->pembelajaran), navigate: true);
    }

    public function markDelete(string $id)
    {
        if (!in_array($id, $this->deletedIds)) {
            $this->deletedIds[] = $id;
        }
    }

    public function unmarkDelete(string $id)
    {
        $this->deletedIds = array_values(array_filter($this->deletedIds, fn($x) => $x !== $id));
    }

    public function removeNewFile(int $index)
    {
        array_splice($this->files, $index, 1);
    }

    public function with()
    {
        return [
            'existingFiles' => $this->jurnal
                ? $this->jurnal->files()->orderBy('created_at')->get()
                : collect(),
        ];
    }
};
?>
<div>
    {{-- PAGE HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">
                {{ $jurnal ? 'Edit Jurnal' : 'Tambah Jurnal' }}
            </h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">
                Kelas: <strong class="text-slate-700 dark:text-slate-200">{{ $pembelajaran->keterangan }}</strong>
            </p>
        </div>
        <div wire:key="breadcrumb-wrapper" wire:ignore>
            <x-breadcrumb />
        </div>
    </div>

    {{-- ACTION BAR --}}
    <div class="flex items-center gap-2.5 flex-wrap mb-5">
        <a href="{{ route('pembelajaran.jurnal.index', $pembelajaran) }}" wire:navigate
            class="btn btn-outline-secondary btn-sm rounded-xl px-3.5 py-2 text-xs font-semibold inline-flex items-center gap-1.5 shadow-xs">
            <i class="bi bi-arrow-left"></i>
            <span>Kembali ke Jurnal</span>
        </a>
    </div>

    <form wire:submit="save" class="space-y-5">
        {{-- PANEL UTAMA: TANGGAL & MATERI --}}
        <div class="glass-panel rounded-3xl p-5 md:p-7 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
            <h4 class="text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-cyan-400 font-display mb-5 flex items-center gap-2">
                <i class="bi bi-journal-text text-base"></i>
                {{ $jurnal ? 'Edit Data Jurnal' : 'Data Jurnal Baru' }}
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-5">
                {{-- Tanggal --}}
                <div class="md:col-span-4">
                    <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">
                        Tanggal Pertemuan <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" wire:model="tanggal"
                        class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl @error('tanggal') is-invalid @enderror"
                        required>
                    @error('tanggal') <div class="invalid-feedback text-xs">{{ $message }}</div> @enderror
                </div>

                {{-- Materi --}}
                <div class="md:col-span-8">
                    <label class="form-label text-xs font-bold text-slate-600 dark:text-slate-300">
                        Materi Pembelajaran <span class="text-rose-500">*</span>
                    </label>
                    <textarea wire:model="materi" rows="4"
                        class="form-control form-control-sm bg-slate-50/50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 rounded-xl @error('materi') is-invalid @enderror"
                        placeholder="Deskripsi materi, topik, atau kegiatan pembelajaran hari ini..."></textarea>
                    @error('materi') <div class="invalid-feedback text-xs">{{ $message }}</div> @enderror
                    <span class="text-[11px] text-slate-400 mt-0.5 block">Maks. 2000 karakter.</span>
                </div>
            </div>
        </div>

        {{-- PANEL FILE UPLOAD --}}
        <div class="glass-panel rounded-3xl p-5 md:p-7 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
            <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 font-display mb-2 flex items-center gap-2">
                <i class="bi bi-paperclip text-base"></i> Lampiran / Materi File
            </h4>
            <p class="text-[11px] text-slate-400 mb-4">Upload bahan ajar, soal, dokumen, atau media (maks. 20 MB/file). Format yang didukung: gambar, PDF, video, audio, Word, Excel, PowerPoint, dll.</p>

            {{-- Input upload --}}
            <div class="mb-4">
                <label
                    class="flex flex-col items-center justify-center gap-3 w-full border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-2xl py-8 px-4 cursor-pointer hover:border-sky-400 dark:hover:border-cyan-500 hover:bg-sky-50/40 dark:hover:bg-sky-950/20 transition-all"
                    for="file-upload-input">
                    <div class="w-12 h-12 rounded-2xl bg-sky-100 dark:bg-sky-950/40 flex items-center justify-center text-sky-500 text-2xl shadow-inner">
                        <i class="bi bi-cloud-arrow-up"></i>
                    </div>
                    <div class="text-center">
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Klik untuk memilih file</span><br>
                        <span class="text-xs text-slate-400">atau seret & lepas file ke sini</span>
                    </div>
                </label>
                <input id="file-upload-input" type="file" wire:model="files" multiple
                    class="hidden"
                    accept="image/*,application/pdf,video/*,audio/*,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                {{-- Loading indicator --}}
                <div wire:loading wire:target="files" class="mt-2 flex items-center gap-2 text-xs text-sky-600 dark:text-cyan-400">
                    <span class="spinner-border spinner-border-sm"></span> Mengunggah file...
                </div>
                @error('files.*') <div class="mt-1 text-xs text-red-500">{{ $message }}</div> @enderror
            </div>

            {{-- Preview file baru yang baru dipilih --}}
            @if (count($files) > 0)
                <div class="mb-5">
                    <h6 class="text-xs font-bold text-slate-600 dark:text-slate-300 mb-2 flex items-center gap-1.5">
                        <i class="bi bi-upload text-sky-500"></i> File Baru ({{ count($files) }})
                    </h6>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach ($files as $i => $file)
                            <div wire:key="new-file-{{ $i }}" class="relative rounded-2xl border border-sky-200 dark:border-sky-800 bg-sky-50/60 dark:bg-sky-950/30 overflow-hidden">
                                {{-- Preview area --}}
                                @php
                                    $mime = $file->getMimeType();
                                    $isImg = str_starts_with($mime, 'image/');
                                    $isPdf = $mime === 'application/pdf';
                                    $isVid = str_starts_with($mime, 'video/');
                                    $isAud = str_starts_with($mime, 'audio/');
                                @endphp

                                @if ($isImg)
                                    <img src="{{ $file->temporaryUrl() }}" alt="{{ $file->getClientOriginalName() }}"
                                        class="w-full h-36 object-cover">
                                @elseif ($isPdf)
                                    <iframe src="{{ $file->temporaryUrl() }}" class="w-full h-36 border-0" title="{{ $file->getClientOriginalName() }}"></iframe>
                                @elseif ($isVid)
                                    <video src="{{ $file->temporaryUrl() }}" controls class="w-full h-36 object-contain bg-black"></video>
                                @elseif ($isAud)
                                    <div class="w-full h-16 flex items-center justify-center px-3">
                                        <audio src="{{ $file->temporaryUrl() }}" controls class="w-full"></audio>
                                    </div>
                                @else
                                    <div class="w-full h-20 flex items-center justify-center text-4xl text-slate-300 dark:text-slate-600 bg-slate-50 dark:bg-slate-800/50">
                                        <i class="bi bi-file-earmark"></i>
                                    </div>
                                @endif

                                {{-- Info & tombol hapus --}}
                                <div class="p-2.5 flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 truncate leading-tight" title="{{ $file->getClientOriginalName() }}">
                                            {{ $file->getClientOriginalName() }}
                                        </p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">
                                            {{ round($file->getSize() / 1024, 1) }} KB
                                        </p>
                                    </div>
                                    <button type="button" wire:click="removeNewFile({{ $i }})"
                                        class="flex-shrink-0 w-6 h-6 rounded-lg bg-rose-100 dark:bg-rose-900/40 text-rose-500 hover:bg-rose-200 dark:hover:bg-rose-800/60 flex items-center justify-center text-xs transition"
                                        title="Hapus dari daftar upload">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- File yang sudah tersimpan (mode edit) --}}
            @if ($existingFiles->count() > 0)
                <div>
                    <h6 class="text-xs font-bold text-slate-600 dark:text-slate-300 mb-2 flex items-center gap-1.5">
                        <i class="bi bi-folder2-open text-emerald-500"></i> File Tersimpan ({{ $existingFiles->count() }})
                    </h6>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach ($existingFiles as $ef)
                            @php
                                $markedDelete = in_array($ef->id, $deletedIds);
                                $url = Storage::disk('public')->url($ef->path);
                                $isImg = str_starts_with($ef->mime_type ?? '', 'image/');
                                $isPdf = $ef->mime_type === 'application/pdf';
                                $isVid = str_starts_with($ef->mime_type ?? '', 'video/');
                                $isAud = str_starts_with($ef->mime_type ?? '', 'audio/');
                                $previewable = $isImg || $isPdf || $isVid || $isAud;
                            @endphp
                            <div wire:key="existing-file-{{ $ef->id }}"
                                class="relative rounded-2xl border overflow-hidden transition-all
                                    {{ $markedDelete
                                        ? 'border-rose-300 dark:border-rose-700 bg-rose-50/60 dark:bg-rose-950/30 opacity-60'
                                        : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900' }}">

                                {{-- Preview area --}}
                                @if ($isImg)
                                    <a href="{{ $url }}" target="_blank" class="block">
                                        <img src="{{ $url }}" alt="{{ $ef->nama_file }}" class="w-full h-36 object-cover {{ $markedDelete ? 'grayscale' : '' }}">
                                    </a>
                                @elseif ($isPdf)
                                    <iframe src="{{ $url }}" class="w-full h-36 border-0 {{ $markedDelete ? 'grayscale' : '' }}" title="{{ $ef->nama_file }}"></iframe>
                                @elseif ($isVid)
                                    <video src="{{ $url }}" controls class="w-full h-36 object-contain bg-black {{ $markedDelete ? 'grayscale' : '' }}"></video>
                                @elseif ($isAud)
                                    <div class="w-full h-16 flex items-center justify-center px-3">
                                        <audio src="{{ $url }}" controls class="w-full"></audio>
                                    </div>
                                @else
                                    <a href="{{ $url }}" target="_blank"
                                        class="w-full h-20 flex flex-col items-center justify-center text-3xl gap-1 bg-slate-50 dark:bg-slate-800/50 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                        <i class="bi {{ $ef->iconClass() }}"></i>
                                        <span class="text-[10px] text-slate-400 font-semibold uppercase">{{ pathinfo($ef->nama_file, PATHINFO_EXTENSION) }}</span>
                                    </a>
                                @endif

                                {{-- Info & aksi --}}
                                <div class="p-2.5 flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 truncate leading-tight" title="{{ $ef->nama_file }}">
                                            {{ $ef->nama_file }}
                                        </p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">{{ $ef->ukuranFormatted() }}</p>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        {{-- Download --}}
                                        <a href="{{ $url }}" target="_blank" download="{{ $ef->nama_file }}"
                                            class="w-6 h-6 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-slate-700 flex items-center justify-center text-xs transition"
                                            title="Unduh">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        {{-- Toggle delete --}}
                                        @if ($markedDelete)
                                            <button type="button" wire:click="unmarkDelete('{{ $ef->id }}')"
                                                class="w-6 h-6 rounded-lg bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 hover:bg-emerald-200 flex items-center justify-center text-xs transition"
                                                title="Batalkan hapus">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        @else
                                            <button type="button" wire:click="markDelete('{{ $ef->id }}')"
                                                class="w-6 h-6 rounded-lg bg-rose-100 dark:bg-rose-900/40 text-rose-500 hover:bg-rose-200 flex items-center justify-center text-xs transition"
                                                title="Hapus file ini">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                @if ($markedDelete)
                                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                        <span class="bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow">AKAN DIHAPUS</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($existingFiles->count() === 0 && count($files) === 0)
                <div class="text-center py-4 text-slate-400 text-xs">
                    <i class="bi bi-folder-x text-2xl block mb-1 text-slate-300"></i>
                    Belum ada file lampiran untuk jurnal ini.
                </div>
            @endif
        </div>

        {{-- BOTTOM ACTION BAR --}}
        <div class="flex items-center justify-between pt-1">
            <a href="{{ route('pembelajaran.jurnal.index', $pembelajaran) }}" wire:navigate
                class="px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold transition-all inline-flex items-center gap-2">
                <i class="bi bi-x-circle"></i> Batal
            </a>
            <button type="submit"
                class="btn-logo-gradient px-6 py-2.5 rounded-xl text-xs font-bold shadow-md inline-flex items-center gap-2">
                <i class="bi bi-save"></i>
                {{ $jurnal ? 'Perbarui Jurnal' : 'Simpan Jurnal' }}
            </button>
        </div>
    </form>
</div>

