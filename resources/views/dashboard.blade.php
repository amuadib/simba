@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <header>
        <h1>📊 Dashboard Presensi Sekolah</h1>
    </header>
    <div class="no-print" style="max-width:300px">
        <canvas id="grafikPresensi"></canvas>
    </div>
    <footer>
        &copy; {{ date('Y') }} Sistem Presensi
    </footer>
@endsection

@push('scripts')
    {{-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('grafikPresensi').getContext('2d');
        const grafikPresensi = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($chartLabels) !!},
                datasets: [{
                        label: 'Hadir',
                        data: {!! json_encode($chartData['H']) !!},
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    },
                    {
                        label: 'Izin',
                        data: {!! json_encode($chartData['I']) !!},
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                    },
                    {
                        label: 'Sakit',
                        data: {!! json_encode($chartData['S']) !!},
                        backgroundColor: 'rgba(23, 162, 184, 0.7)',
                    },
                    {
                        label: 'Alpa',
                        data: {!! json_encode($chartData['A']) !!},
                        backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    },
                ],
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        precision: 0,
                    },
                },
            },
        });
    </script> --}}

    <script>
        function drawChart(data) {
            const canvas = document.getElementById('grafikPresensi');
            const ctx = canvas.getContext('2d');

            const total = data.H + data.I + data.S + data.A;
            if (total === 0) return;

            const colors = {
                H: '#28a745',
                I: '#ffc107',
                S: '#17a2b8',
                A: '#dc3545'
            };

            let start = 0;
            Object.entries(data).forEach(([key, value]) => {
                const angle = (value / total) * Math.PI * 2;

                ctx.beginPath();
                ctx.moveTo(150, 150);
                ctx.arc(150, 150, 120, start, start + angle);
                ctx.fillStyle = colors[key];
                ctx.fill();

                start += angle;
            });
        }

        function drawBarChart(data) {
            const canvas = document.getElementById('grafikPresensi');
            const ctx = canvas.getContext('2d');

            ctx.clearRect(0, 0, 300, 300);

            const max = Math.max(...Object.values(data));
            const keys = ['H', 'I', 'S', 'A'];
            const colors = ['#28a745', '#ffc107', '#17a2b8', '#dc3545'];

            keys.forEach((k, i) => {
                const h = (data[k] / max) * 200;
                ctx.fillStyle = colors[i];
                ctx.fillRect(50 + i * 50, 250 - h, 30, h);
                ctx.fillText(k, 55 + i * 50, 270);
            });
        }
        // ambil total dari tabel
        // function refreshChart(siswaId) {
        //     const get = s => Number(document.querySelector(`.total-${s}[data-row="${siswaId}"]`)?.textContent ?? 0);

        //     drawChart({
        //         H: get('h'),
        //         I: get('i'),
        //         S: get('s'),
        //         A: get('a')
        //     });
        // }
    </script>
@endpush
