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
                            <td>{{ $s->siswa->nama }} ({{ $s->siswa->rombel->nama }})</td>
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
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const CONFIG = {
            presensiUrl: "{{ route('pembelajaran.presensi.update') }}",
            nilaiUrl: "{{ route('pembelajaran.jurnal.nilai.update') }}",
            pembelajaranId: "{{ $pembelajaran->id }}",
            jurnalId: "{{ $jurnal->id }}",
            jenisNilaiId: 1,
            tanggal: "{{ date('Y-m-d', strtotime($jurnal->tanggal)) }}",
        };
        const QUEUE = {
            jobs: [],
            active: 0,
            maxConcurrent: 3,
            locks: new Set(), // siswa_id yang sedang disave
        };

        let ajaxQueue = [];
        let isSaving = false;
        let lastTapTime = 0;
        let nilaiDebounce = null;

        const csrfToken = () =>
            document.querySelector('meta[name="csrf-token"]').content;

        const getIdFromName = (name) =>
            name.match(/\[(.*)\]/)?.[1] ?? null;

        function enqueueSave(url, payload, siswaId) {
            QUEUE.jobs.push({
                url,
                payload,
                siswaId,
                attempt: 0,
                maxRetry: 3,
            });
            runQueue();
        }

        async function runQueue() {
            if (QUEUE.active >= QUEUE.maxConcurrent) return;
            if (!QUEUE.jobs.length) return;

            const job = QUEUE.jobs.find(j => !QUEUE.locks.has(j.siswaId));
            if (!job) return;

            QUEUE.jobs = QUEUE.jobs.filter(j => j !== job);

            QUEUE.active++;
            QUEUE.locks.add(job.siswaId);
            setRowLoading(job.siswaId, true);

            try {
                await sendRequest(job);
                showToast('Tersimpan ✔');
            } catch (err) {
                job.attempt++;

                if (job.attempt <= job.maxRetry) {
                    const delay = 500 * Math.pow(2, job.attempt);
                    setTimeout(() => {
                        QUEUE.jobs.push(job);
                        runQueue();
                    }, delay);
                } else {
                    setRowError(job.siswaId);
                    showToast('Gagal menyimpan ✖', 'error');
                }
            } finally {
                QUEUE.active--;
                QUEUE.locks.delete(job.siswaId);
                setRowLoading(job.siswaId, false);
                runQueue();
            }
        }

        async function sendRequest(job) {
            const res = await fetch(job.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(job.payload)
            });

            if (!res.ok) throw new Error('HTTP error');
        }

        function setRowLoading(siswaId, state) {
            const row = document.querySelector(`tr[data-id="${siswaId}"]`);
            if (!row) return;

            row.classList.toggle('is-saving', state);
            if (state) row.classList.remove('is-error');
        }

        function setRowError(siswaId) {
            const row = document.querySelector(`tr[data-id="${siswaId}"]`);
            if (!row) return;

            row.classList.add('is-error');
        }

        function savePresensi(select) {
            const siswaId = getIdFromName(select.name);
            if (!siswaId) return;

            enqueueSave(
                CONFIG.presensiUrl, {
                    siswa_id: siswaId,
                    status: select.value,
                    pembelajaran_id: CONFIG.pembelajaranId,
                    tanggal: CONFIG.tanggal
                },
                siswaId
            );
        }

        function syncPresensiButtons(select) {
            const wrap = select.closest('.presensi-controls');
            wrap.querySelectorAll('button').forEach(btn =>
                btn.classList.toggle('active', btn.textContent.trim() === select.value)
            );
        }

        function saveNilai(input) {
            const siswaId = getIdFromName(input.name);
            if (!siswaId) return;

            enqueueSave(
                CONFIG.nilaiUrl, {
                    siswa_id: siswaId,
                    jurnal_id: CONFIG.jurnalId,
                    jenis_nilai_id: CONFIG.jenisNilaiId,
                    nilai: input.value,
                },
                siswaId
            );

        }

        function syncNilaiButtons(input) {
            const wrap = input.closest('.nilai-controls');
            wrap.querySelectorAll('.btn-preset').forEach(btn =>
                btn.classList.toggle('active', btn.textContent.trim() === input.value)
            );
        }


        // PRESENSI SELECT
        document.addEventListener('change', e => {
            const select = e.target.closest('.presensi-controls select');
            if (!select) return;

            syncPresensiButtons(select);
            savePresensi(select);
        });

        // NILAI INPUT
        document.addEventListener('input', e => {
            const input = e.target.closest('.nilai-controls input[type="number"]');
            if (!input) return;

            clearTimeout(nilaiDebounce);
            nilaiDebounce = setTimeout(() => {
                syncNilaiButtons(input);
                saveNilai(input);
            }, 500);
        });

        document.addEventListener('change', e => {
            const input = e.target.closest('.nilai-controls input[type="number"]');
            if (!input) return;

            syncNilaiButtons(input);
            saveNilai(input);
        });

        document.addEventListener('blur', e => {
            const input = e.target.closest('.nilai-controls input[type="number"]');
            if (!input) return;

            syncNilaiButtons(input);
            saveNilai(input);
        }, true);

        /* =========================================
         * BUTTON (POINTER / TOUCH)
         * ========================================= */
        document.addEventListener('pointerdown', e => {
            const now = Date.now();
            if (now - lastTapTime < 300) return;
            lastTapTime = now;

            /* PRESENSI BUTTON */
            const presensiBtn = e.target.closest('.presensi-controls button');
            if (presensiBtn) {
                e.preventDefault();

                const wrap = presensiBtn.closest('.presensi-controls');
                const select = wrap.querySelector('select');
                if (!select) return;

                const val = presensiBtn.textContent.trim();
                select.value = select.value === val ? '-' : val;
                select.dispatchEvent(new Event('change', {
                    bubbles: true
                }));
                return;
            }

            /* NILAI BUTTON */
            const nilaiBtn = e.target.closest('.nilai-controls .btn-preset');
            if (nilaiBtn) {
                e.preventDefault();

                const wrap = nilaiBtn.closest('.nilai-controls');
                const input = wrap.querySelector('input[type="number"]');
                if (!input) return;

                const val = nilaiBtn.textContent.trim();
                input.value = input.value === val ? '' : val;
                syncNilaiButtons(input);
                saveNilai(input);
            }
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

        tr.is-saving {
            opacity: .6;
            position: relative;
            pointer-events: none;
        }

        tr.is-saving::after {
            content: '⏳';
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
        }

        tr.is-error {
            background: #ffe6e6;
        }
    </style>
@endpush
