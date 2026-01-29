@php
    $color = [70 => 'secondary', 80 => 'info', 90 => 'success', 100 => 'primary'];
    $presensi = ['H', 'S', 'I', 'A'];
    $color_presensi = ['S' => 'warning', 'A' => 'danger', 'H' => 'success', 'I' => 'primary'];
    $btn_group_nilai = '';
    $btn_group_presensi = '';
    foreach ([70, 80, 90, 100] as $n) {
        $btn_group_nilai .=
            '<button type="button" type="button" class="btn btn-sm btn-' .
            $color[$n] .
            ' btn-preset">' .
            $n .
            '  </button>';
    }
    foreach ($presensi as $p) {
        $btn_group_presensi .=
            '<button type="button" type="button" class="btn btn-sm btn-' .
            $color_presensi[$p] .
            '">' .
            $p .
            '  </button>';
    }
@endphp
@extends('layouts.app')

@section('title', 'Input Presensi & Nilai Jurnal ' . $jurnal->materi)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Presensi & Nilai</h4>
            <small class="text-muted">Input Presensi & Nilai Jurnal {{ $jurnal->materi }}</small>
        </div>
        <x-breadcrumb />
    </div>
    <div class="card shadow-sm">
        <div class="card-body">
            {{-- <form method="post" action="{{ route('pembelajaran.jurnal.nilai.store', [$pembelajaran, $jurnal]) }}"> --}}
            @csrf
            <table class="table-bordered table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Presensi</th>
                        <th>Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pembelajaran->anggota as $s)
                        <tr data-id="{{ $s->siswa->id }}">
                            <td>{{ $s->siswa->nama }}</td>
                            <td class="presensi">
                                <div class="input-group presensi-controls">
                                    {!! $btn_group_presensi !!}
                                    <select name="presensi[{{ $s->siswa->id }}]" class="form-control form-control-sm">
                                        <option value="-">-</option>
                                        @foreach ($presensi as $p)
                                            <option value="{{ $p }}"
                                                @if (isset($presensi_siswa[$s->siswa->id]) && $p == $presensi_siswa[$s->siswa->id]) selected @endif>{{ $p }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </td>
                            <td class="nilai">
                                <div class="input-group nilai-controls">
                                    {!! $btn_group_nilai !!}
                                    <input name="nilai[{{ $s->siswa->id }}]" type="number" min="0" max="100"
                                        class="form-control form-control-sm" value="{{ $nilai[$s->siswa->id] ?? '' }}">
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{-- <button class="btn btn-primary">Simpan</button>
            </form> --}}
        </div>
    </div>
@endsection

@push('scripts')
    {{-- <script>
        document.addEventListener('click', (e) => {
            const cell = e.target.closest('.btn-preset');
            const id = cell.closest('tr').dataset.id
            document.querySelector('input[name="nilai[' + id + ']"]').value = cell.textContent + "0"
        })
    </script> --}}

    {{-- <script>
        document.addEventListener('click', function(e) {

            /* ===============================
             * PRESENSI BUTTON → SELECT
             * =============================== */
            const presensiBtn = e.target.closest('.presensi-controls button');
            if (presensiBtn) {
                e.preventDefault();

                const wrapper = presensiBtn.closest('.presensi-controls');
                const select = wrapper.querySelector('select');
                if (!select) return;

                // ambil teks button (H / S / I / A)
                const status = presensiBtn.textContent.trim();

                // set select value
                select.value = status;

                // optional: trigger change (kalau ada listener lain)
                select.dispatchEvent(new Event('change', {
                    bubbles: true
                }));

                // UI active state
                wrapper.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                presensiBtn.classList.add('active');

                return;
            }

            /* ===============================
             * NILAI BUTTON → INPUT
             * =============================== */
            const nilaiBtn = e.target.closest('.nilai-controls .btn-preset');
            if (nilaiBtn) {
                e.preventDefault();

                const wrapper = nilaiBtn.closest('.nilai-controls');
                const input = wrapper.querySelector('input[type="number"]');
                if (!input) return;

                const nilai = nilaiBtn.textContent.trim();

                input.value = nilai;

                // trigger input & change (untuk hitung nilai / ajax)
                input.dispatchEvent(new Event('input', {
                    bubbles: true
                }));
                input.dispatchEvent(new Event('change', {
                    bubbles: true
                }));

                // UI active state
                wrapper.querySelectorAll('.btn-preset').forEach(b => b.classList.remove('active'));
                nilaiBtn.classList.add('active');
            }

        });
    </script> --}}

    <script>
        const urlUpdatePresensi = "{{ route('pembelajaran.presensi.update') }}";
        const urlUpdateNilai = "{{ route('pembelajaran.jurnal.nilai.update') }}";
        const pembelajaranId = "{{ $pembelajaran->id }}";
        const jenisNilaiId = 1;
        const jurnalId = "{{ $jurnal->id }}";
        const tanggal = "{{ date('Y-m-d', strtotime($jurnal->tanggal)) }}";
        /* =========================================
         * GLOBAL STATE
         * ========================================= */
        let ajaxQueue = [];
        let isSaving = false;
        let lastTapTime = 0;
        let nilaiDebounce = null;

        /* =========================================
         * AJAX QUEUE PROCESSOR
         * ========================================= */
        async function processQueue() {
            if (isSaving || ajaxQueue.length === 0) return;

            isSaving = true;
            const job = ajaxQueue.shift();

            try {
                const res = await fetch(job.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(job.payload)
                });
                if (!res.ok) throw new Error('HTTP Error');

                showToast('Tersimpan ✔');
            } catch (e) {
                console.error('Auto save gagal', e);
                showToast('Gagal menyimpan ✖', 'error');
            } finally {
                isSaving = false;
                processQueue();
            }
        }

        function enqueueSave(url, payload) {
            ajaxQueue.push({
                url,
                payload
            });
            processQueue();
        }

        function updatePresensi(e) {
            const presensiBtn = e.target.closest('.presensi-controls button');
            if (presensiBtn) {
                e.preventDefault();
                const wrap = presensiBtn.closest('.presensi-controls');
                const select = wrap.querySelector('select');
                if (!select) return;

                const value = presensiBtn.textContent.trim();
                const old = select.value;

                // toggle kosong
                select.value = (old === value) ? '-' : value;

                wrap.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                if (select.value !== '-') presensiBtn.classList.add('active');

                // AUTO SAVE
                enqueueSave(urlUpdatePresensi, {
                    siswa_id: select.name.match(/\[(.*)\]/)[1],
                    status: select.value,
                    pembelajaran_id: pembelajaranId,
                    tanggal: tanggal
                });

                return;
            }
        }

        function handlePresensiChange(select) {
            const wrap = select.closest('.presensi-controls');
            const value = select.value;
            const siswaId = select.name.match(/\[(.*)\]/)[1];

            // Sync tombol
            wrap.querySelectorAll('button').forEach(btn => {
                btn.classList.toggle(
                    'active',
                    btn.textContent.trim() === value
                );
            });

            // AUTO SAVE (queue)
            enqueueSave(urlUpdatePresensi, {
                siswa_id: select.name.match(/\[(.*)\]/)[1],
                status: value,
                pembelajaran_id: pembelajaranId,
                tanggal: tanggal
            });
        }

        function updateNilai(e) {
            const nilaiBtn = e.target.closest('.nilai-controls .btn-preset');
            if (nilaiBtn) {
                e.preventDefault();

                const wrap = nilaiBtn.closest('.nilai-controls');
                const input = wrap.querySelector('input[type="number"]');
                if (!input) return;

                const value = nilaiBtn.textContent.trim();
                const old = input.value;

                // toggle kosong
                input.value = (old === value) ? '' : value;

                wrap.querySelectorAll('.btn-preset').forEach(b => b.classList.remove('active'));
                if (input.value !== '') nilaiBtn.classList.add('active');

                // AUTO SAVE
                enqueueSave(urlUpdateNilai, {
                    siswa_id: input.name.match(/\[(.*)\]/)[1],
                    jurnal_id: jurnalId,
                    jenis_nilai_id: jenisNilaiId,
                    nilai: input.value
                });
            }
        }

        function updateNilaiInput(input) {
            enqueueSave(urlUpdateNilai, {
                siswa_id: input.name.match(/\[(.*)\]/)[1],
                jurnal_id: jurnalId,
                jenis_nilai_id: jenisNilaiId,
                nilai: input.value
            });

            // sync tombol preset
            const wrap = input.closest('.nilai-controls');
            wrap.querySelectorAll('.btn-preset').forEach(btn => {
                btn.classList.toggle('active', btn.textContent.trim() === nilai);
            });
        }

        // SELECT
        document.addEventListener('change', function(e) {
            const select = e.target;
            if (!select.matches('.presensi-controls select')) return;

            handlePresensiChange(select);
        });
        // INPUT
        document.addEventListener('input', function(e) {
            const input = e.target;
            if (!input.matches('.nilai-controls input[type="number"]')) return;

            clearTimeout(nilaiDebounce);
            nilaiDebounce = setTimeout(() => {
                updateNilaiInput(input);
            }, 500); // debounce 500ms
        });
        document.addEventListener('change', function(e) {
            const input = e.target;
            if (!input.matches('.nilai-controls input[type="number"]')) return;

            updateNilaiInput(input);
        });

        document.addEventListener('blur', function(e) {
            const input = e.target;
            if (!input.matches('.nilai-controls input[type="number"]')) return;

            updateNilaiInput(input);
        }, true);
        /* =========================================
         * CLICK + TOUCH HANDLER (ANTI DOUBLE TAP)
         * ========================================= */
        document.addEventListener('pointerdown', function(e) {
            const now = Date.now();
            if (now - lastTapTime < 300) return;
            lastTapTime = now;
            /*=========================* PRESENSI BUTTON
             *=========================*/
            updatePresensi(e)
            /*=========================* NILAI BUTTON
             *=========================*/
            updateNilai(e)
        });
    </script>
@endpush

@push('styles')
    <style>
        .presensi-controls button,
        .nilai-controls .btn-preset {
            touch-action: manipulation;
        }

        .presensi-controls button.active,
        .nilai-controls .btn-preset.active {
            box-shadow: inset 0 0 0 2px rgba(0, 0, 0, .3);
            transform: scale(0.95);
        }
    </style>
@endpush
