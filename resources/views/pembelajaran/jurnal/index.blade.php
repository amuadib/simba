@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Jurnal Pembelajaran</h4>
            <small class="text-muted">Daftar Jurnal Pembelajaran {{ $pembelajaran->keterangan }}</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table-bordered table" id="table-jurnal">
                <thead>
                    <tr>
                        <th style="width:200px;">Tanggal</th>
                        <th>Materi</th>
                        <th width="150">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @if (!$jurnals->count())
                        <tr id="no-data">
                            <td colspan="3" class="text-center">Data tidak ditemukan</td>
                        </tr>
                    @else
                        @foreach ($jurnals as $j)
                            <tr data-id="{{ $j->id }}">
                                <td class="editable" data-field="tanggal" data-value={{ $j->tanggal }}>
                                    {{ $j->tanggal->locale('id_ID')->isoFormat('DD MMMM YYYY') }}
                                </td>
                                <td class="editable" data-field="materi">{!! $j->materi !!}</td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger btn-delete"> <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    <tr id="row-add">
                        <td>
                            <input type="date" id="add-tanggal" class="form-control form-control-sm"
                                placeholder="Tanggal">
                        </td>
                        <td>
                            <input type="text" id="add-materi" class="form-control form-control-sm" placeholder="Materi">
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" id="btn-add"><i class="bi bi-plus-circle"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>

            {{ $jurnals->links() }}
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const url = "{{ url('/pembelajaran') }}";
        const pembelajaran_id = "{{ $pembelajaran->id }}";
        const noDataRowContent = `<tr id="no-data">
                    <td colspan="3" class="text-center">Data tidak ditemukan</td>
                </tr>`;

        /* ==========================
           HELPER FETCH JSON
        ========================== */
        async function fetchJSON(endpoint, options = {}) {
            const res = await fetch(endpoint, {
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    ...(options.headers || {})
                },
                ...options
            });

            if (!res.ok) {
                throw new Error('Network error');
            }

            return res.json();
        }


        /* ==========================
           INLINE EDIT
        ========================== */
        document.addEventListener('click', (e) => {
            const cell = e.target.closest('.editable');
            if (!cell || cell.querySelector('input')) return;

            const oldValue = cell.innerHTML.trim();
            const field = cell.dataset.field;
            const row = cell.closest('tr');
            const id = row.dataset.id;

            const input = document.createElement('input');
            input.type = field === 'tanggal' ? 'date' : 'text';
            input.value = field === 'tanggal' ?
                cell.dataset.value :
                oldValue.replace(/<br>/g, '\n');

            input.className = 'form-control form-control-sm';

            cell.innerHTML = '';
            cell.appendChild(input);
            input.focus();

            input.addEventListener('blur', async () => {
                const newValue = input.value.trim();

                if (newValue === oldValue) {
                    cell.innerHTML = oldValue;
                    return;
                }

                try {
                    await fetchJSON(
                        `${url}/${pembelajaran_id}/jurnal/${id}`, {
                            method: 'PATCH',
                            body: JSON.stringify({
                                field,
                                value: newValue
                            })
                        }
                    );

                    if (field === 'tanggal') {
                        const date = new Date(newValue);
                        cell.innerHTML = date.toLocaleDateString('id-ID', {
                            day: '2-digit',
                            month: 'long',
                            year: 'numeric'
                        });
                        cell.dataset.value = newValue;
                    } else {
                        cell.innerHTML = newValue.replace(/\n/g, '<br>');
                    }
                } catch (err) {
                    cell.innerHTML = oldValue;
                    alert('Gagal memperbarui data.');
                }
            });
        });

        /* ==========================
           INLINE ADD
        ========================== */
        document.getElementById('btn-add').addEventListener('click', async () => {
            const tanggal = document.getElementById('add-tanggal').value;
            const materi = document.getElementById('add-materi').value.trim();

            if (!tanggal || !materi) {
                alert('Tanggal dan Materi harus diisi.');
                return;
            }

            try {
                const data = await fetchJSON(
                    `${url}/${pembelajaran_id}/jurnal`, {
                        method: 'POST',
                        body: JSON.stringify({
                            tanggal,
                            materi
                        })
                    }
                );

                const tbody = document.querySelector('#table-jurnal tbody');
                const newRow = document.createElement('tr');
                newRow.dataset.id = data.id;
                newRow.innerHTML = `
            <td class="editable" data-field="tanggal" data-value="${data.tanggal}">
                ${data.tanggal_label ?? data.tanggal}
            </td>
            <td class="editable" data-field="materi">
                ${data.materi}
            </td>
            <td>
                <button class="btn btn-sm btn-danger btn-delete">🗑</button>
            </td>
        `;

                tbody.insertBefore(newRow, document.getElementById('row-add'));

                document.getElementById('add-tanggal').value = '';
                document.getElementById('add-materi').value = '';

                const noDataRow = document.getElementById('no-data');
                if (noDataRow) noDataRow.remove();

            } catch (err) {
                alert('Gagal menambahkan jurnal.');
            }
        });

        /* ==========================
           DELETE
        ========================== */
        document
            .querySelector('#table-jurnal')
            .addEventListener('click', async (e) => {

                if (!e.target.classList.contains('btn-delete')) return;

                if (!confirm('Hapus jurnal?')) return;

                const row = e.target.closest('tr');
                const id = row.dataset.id;

                try {
                    await fetchJSON(
                        `${url}/${pembelajaran_id}/jurnal/${id}`, {
                            method: 'DELETE'
                        }
                    );

                    row.remove();

                    const tbody = document.querySelector('#table-jurnal tbody');
                    if (tbody.querySelectorAll('tr').length === 1) {
                        tbody.insertAdjacentHTML('afterbegin', noDataRowContent);
                    }

                } catch (err) {
                    alert('Gagal menghapus jurnal.');
                }
            });
    </script>
@endpush

@push('styles')
    <style>
        .editable {
            cursor: pointer;
            touch-action: manipulation
        }
    </style>
@endpush
