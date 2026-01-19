@extends('layouts.app')

@section('title', 'Data Siswa')

@section('content')
    <form method="post" action="{{ route('backup.export') }}">
        @csrf
        <h5>Export Database</h5>

        @foreach ($tables as $table)
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="tables[]" value="{{ $table }}" checked>
                <label class="form-check-label">{{ $table }}</label>
            </div>
        @endforeach

        <button class="btn btn-success mt-3">
            📤 Export
        </button>
    </form>

    <hr>

    <form method="post" action="{{ route('backup.import') }}" enctype="multipart/form-data">
        @csrf
        <h5>Import Database</h5>

        <input type="file" name="file" class="form-control" accept=".json" required>
        <button class="btn btn-primary mt-3">📥 Import</button>
    </form>
@endsection
