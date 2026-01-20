<form class="row g-2 mb-3" method="post"
    action="{{ isset($jurnal) ? route('pembelajaran.jurnal.update', [$pembelajaran, $jurnal]) : route('pembelajaran.jurnal.store', $pembelajaran) }}">
    @csrf
    @isset($jurnal)
        @method('PUT')
    @endisset

    <div class="col">
        <input type="date" name="tanggal" class="form-control" required value="{{ $jurnal->tanggal ?? date('Y-m-d') }}">
    </div>

    <div class="col">
        <input type="text" name="materi" class="form-control" required value="{{ $jurnal->materi ?? '' }}"
            placeholder="Materi">
    </div>
    <div class="col">
        @if (isset($jurnal))
            <button class="btn btn-warning">Edit</button>
        @else
            <button class="btn btn-success">Simpan</button>
        @endif
    </div>
</form>
