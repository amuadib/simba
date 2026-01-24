@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Database</h4>
            <small class="text-muted">Ekspor & Impor Database</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="{{ route('database.export') }}">
                @csrf
                <h5>Ekspor Database</h5>
                @foreach ($tables as $table)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tables[]" value="{{ $table }}" checked>
                        <label class="form-check-label">{{ $table }}</label>
                    </div>
                @endforeach

                <button class="btn btn-success mt-3">
                    📤 Ekspor
                </button>
            </form>
        </div>
    </div>
    <br>
    <div class="card shadow-sm">
        <div class="card-body">

            <form method="post" action="{{ route('database.import') }}" enctype="multipart/form-data">
                @csrf
                <h5>Impor Database</h5>

                <input type="file" name="file" class="form-control" accept=".json" required>
                <button class="btn btn-primary mt-3">📥 Impor</button>
            </form>
        </div>
    </div>
@endsection
