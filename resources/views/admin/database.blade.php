@extends('layouts.app')

@section('title', 'Database')

@section('content')

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold font-display text-gradient-logo m-0">Database</h1>
            <p class="text-xs md:text-sm text-slate-500 dark:text-slate-400 m-0 mt-0.5">Ekspor dan impor cadangan database sistem</p>
        </div>
        <x-breadcrumb />
    </div>
    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <h5 class="font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2">
            <i class="bi bi-box-arrow-up text-sky-500"></i>
            <span>Ekspor Database</span>
        </h5>
        <form method="post" action="{{ route('database.export') }}">
            @csrf
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 mb-4 p-4 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs">
                @foreach ($tables as $table)
                    <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" name="tables[]" value="{{ $table }}" id="table_{{ $table }}" checked>
                        <label class="form-check-label text-xs font-semibold text-slate-700 dark:text-slate-300" for="table_{{ $table }}">{{ $table }}</label>
                    </div>
                @endforeach
            </div>

            <button type="submit" class="btn-logo-gradient inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-xs font-bold shadow-md">
                <i class="bi bi-cloud-arrow-down-fill text-sm"></i>
                <span>Ekspor Terpilih</span>
            </button>
        </form>
    </div>

    <div class="glass-panel rounded-3xl p-4 md:p-6 mb-6 border border-slate-200/90 dark:border-cyan-500/20 shadow-xl">
        <h5 class="font-bold text-slate-800 dark:text-slate-100 mb-4 flex items-center gap-2">
            <i class="bi bi-box-arrow-in-down text-emerald-500"></i>
            <span>Impor Database</span>
        </h5>

        <form method="post" action="{{ route('database.import') }}" enctype="multipart/form-data">
            @csrf
            <div class="p-4 rounded-2xl bg-slate-50/90 dark:bg-slate-800/40 border border-slate-200/90 dark:border-cyan-500/20 shadow-xs mb-4 max-w-xl">
                <label class="form-label text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">Pilih File Cadangan (.json)</label>
                <input type="file" name="file" class="form-control rounded-xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-100 text-xs py-2 shadow-xs" accept=".json" required>
            </div>
            <button type="submit" class="btn-logo-gradient inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl text-xs font-bold shadow-md">
                <i class="bi bi-cloud-arrow-up-fill text-sm"></i>
                <span>Impor Database</span>
            </button>
        </form>
    </div>
@endsection
