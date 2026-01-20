@extends('layouts.app')

@section('content')
    <h5>{{ isset($jurnal) ? 'Edit' : 'Tambah' }} Jurnal</h5>

    <form method="post"
        action="{{ isset($jurnal) ? route('pembelajaran.jurnal.update', [$pembelajaran, $jurnal]) : route('pembelajaran.jurnal.store', $pembelajaran) }}">
        @csrf
        @isset($jurnal)
            @method('PUT')
        @endisset

        <div class="mb-2">
            <label>Pembelajaran</label>
            <input type="text" class="form-control-plaintext" value="{{ $pembelajaran->keterangan }}" readonly>
        </div>

        <div class="mb-2">
            <label>Tanggal</label>
            <input type="date" name="tanggal" class="form-control" required value="{{ $jurnal->tanggal ?? date('Y-m-d') }}">
        </div>

        <div class="mb-2">
            <label>Materi</label>
            <textarea name="materi" class="form-control" rows="3" required>
            {{ $jurnal->materi ?? '' }}
            </textarea>
        </div>

        <button class="btn btn-success">Simpan</button>
    </form>
@endsection
