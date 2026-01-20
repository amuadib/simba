@extends('layouts.app')

@section('content')
    <h5>Jurnal Pembelajaran {{ $pembelajaran->keterangan }}</h5>

    <table class="table-bordered table" id="table-jurnal">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Materi</th>
                <th width="150">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @if (!$jurnals->count())
                <tr>
                    <td colspan="3" class="text-center">Data tidak ditemukan</td>
                </tr>
            @else
                @foreach ($jurnals as $j)
                    <tr data-id="{{ $j->id }}">
                        <td class="editable" data-field="tanggal" data-value={{ $j->tanggal }}>
                            {{ $j->tanggal->format('d F Y') }}</td>
                        <td class="editable" data-field="materi">{!! $j->materi !!}</td>
                        <td>
                            <button class="btn btn-sm btn-danger btn-delete">🗑</button>
                        </td>
                    </tr>
                @endforeach
            @endif
            <tr id="row-add">
                <td>
                    <input type="date" id="add-tanggal" class="form-control form-control-sm" placeholder="Tanggal">
                </td>
                <td>
                    <input type="text" id="add-materi" class="form-control form-control-sm" placeholder="Materi">
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" id="btn-add">➕</button>
                </td>
            </tr>
        </tbody>
    </table>

    {{ $jurnals->links() }}
@endsection

@push('scripts')
    <script>
        document.addEventListener('click', function(e) {
            const cell = e.target.closest('.editable');
            if (!cell) return;
            if (cell.querySelector('input')) return;

            const oldValue = cell.innerHTML.trim();
            const field = cell.dataset.field;
            const id = cell.closest('tr').dataset.id;

            const input = document.createElement('input');
            input.type = field === 'tanggal' ? 'date' : 'text';
            input.value = field === 'tanggal' ? cell.dataset.value : oldValue.replace(/<br>/g, '\n');
            input.className = 'form-control form-control-sm';

            cell.innerHTML = '';
            cell.appendChild(input);
            input.focus();

            input.addEventListener('blur', function() {
                const newValue = input.value.trim();
                if (newValue === oldValue) {
                    cell.innerHTML = oldValue;
                    return;
                }
                fetch(`{{ url('/pembelajaran') }}/{{ $pembelajaran->id }}/jurnal/${id}`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            field: field,
                            value: newValue
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (field === 'tanggal') {
                            const date = new Date(newValue);
                            cell.innerHTML = date.toLocaleDateString('id-ID', {
                                day: '2-digit',
                                month: 'long',
                                year: 'numeric'
                            });
                        } else {
                            cell.innerHTML = newValue.replace(/\n/g, '<br>');
                        }
                    })
                    .catch(() => {
                        cell.innerHTML = oldValue;
                        alert('Gagal memperbarui data.');
                    });
            });
        });

        document.getElementById('btn-add').addEventListener('click', function() {
            const tanggal = document.getElementById('add-tanggal').value;
            const materi = document.getElementById('add-materi').value.trim();

            if (!tanggal || !materi) {
                alert('Tanggal dan Materi harus diisi.');
                return;
            }

            fetch(`{{ route('pembelajaran.jurnal.store', $pembelajaran) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        tanggal: tanggal,
                        materi: materi
                    })
                })
                .then(response => response.json())
                .then(data => {
                    const tbody = document.querySelector('#table-jurnal tbody');
                    const newRow = document.createElement('tr');
                    newRow.dataset.id = data.id;
                    newRow.innerHTML = `
                        <td class="editable" data-field="tanggal">${data.tanggal}</td>
                        <td class="editable" data-field="materi">${data.materi}</td>
                        <td>
                            <button class="btn btn-sm btn-danger btn-delete">🗑</button>
                        </td>
                    `;
                    tbody.insertBefore(newRow, document.getElementById('row-add'));
                    document.getElementById('add-tanggal').value = '';
                    document.getElementById('add-materi').value = '';
                })
                .catch(() => {
                    alert('Gagal menambahkan jurnal.');
                });
        });

        document.querySelector('#table-jurnal').addEventListener('click', function(e) {
            if (!e.target.classList.contains('btn-delete')) return;

            const row = e.target.closest('tr');
            const id = row.dataset.id;

            if (!confirm('Hapus jurnal?')) return;

            fetch(`{{ url('/pembelajaran') }}/{{ $pembelajaran->id }}/jurnal/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.json())
                .then(data => {
                    row.remove();
                })
                .catch(() => {
                    alert('Gagal menghapus jurnal.');
                });
        });
    </script>
@endpush
