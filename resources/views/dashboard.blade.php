@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

    <!-- STATS -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 shadow-sm">
                <small class="text-muted">Total Siswa</small>
                <h4>{{ $totalSiswa }}</h4>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 shadow-sm">
                <small class="text-muted">Hadir</small>
                <h4>{{ $total['h'] }}</h4>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 shadow-sm">
                <small class="text-muted">Sakit</small>
                <h4>{{ $total['s'] }}</h4>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 shadow-sm">
                <small class="text-muted">Izin</small>
                <h4>{{ $total['i'] }}</h4>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card stat-card p-3 shadow-sm">
                <small class="text-muted">Alpa</small>
                <h4>{{ $total['a'] }}</h4>
            </div>
        </div>
    </div>

    <!-- GRAPH -->
    <div class="card p-3 shadow-sm">
        <h6>Grafik Kehadiran</h6>
        <div class="d-flex justify-content-center align-items-center chart-container rounded"
            style="overflow-x: auto;width: 100%; ">
            <canvas id="presensiChart" style="min-width: 1000px; height: 260px;"></canvas>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('chart.js') }}"></script>

    <script>
        const labels = @json($chartData['labels']);
        const h = @json($chartData['series']['H']);
        const s = @json($chartData['series']['S']);
        const i = @json($chartData['series']['I']);
        const a = @json($chartData['series']['A']);
    </script>

    {{-- ChartJS --}}
    <script>
        const ctx = document.getElementById('presensiChart');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                        label: 'Hadir',
                        data: h,
                        borderColor: '#22c55e',
                        backgroundColor: '#22c55e',
                        tension: .3,
                        pointRadius: 4
                    },
                    {
                        label: 'Izin',
                        data: i,
                        borderColor: '#3b82f6',
                        backgroundColor: '#3b82f6',
                        tension: .3,
                        pointRadius: 4
                    },
                    {
                        label: 'Sakit',
                        data: s,
                        borderColor: '#facc15',
                        backgroundColor: '#facc15',
                        tension: .3,
                        pointRadius: 4
                    },
                    {
                        label: 'Alpa',
                        data: a,
                        borderColor: '#ef4444',
                        backgroundColor: '#ef4444',
                        tension: .3,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index', // 👈 tooltip gabungan
                    intersect: false
                },
                plugins: {
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            title: (ctx) => ctx[0].label,
                            label: (ctx) => `${ctx.dataset.label}: ${ctx.formattedValue}`
                        }
                    },
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
        const container = document.querySelector('.chart-container');
        container.scrollLeft = container.scrollWidth;
    </script>

    {{-- Manual --}}
    {{-- <script>
        const canvas = document.getElementById('presensiLine');
        const wrapper = canvas.parentElement;
        const ctx = canvas.getContext('2d');
        const tooltip = document.getElementById('chartTooltip');

        const padding = 40;
        const statuses = ['H', 'I', 'S', 'A'];

        const colors = {
            H: '#28a745',
            I: '#0dcaf0',
            S: '#ffc107',
            A: '#dc3545'
        };

        const max = Math.max(
            ...data.flatMap(d => statuses.map(s => d[s]))
        ) || 1;

        document.addEventListener('DOMContentLoaded', () => {

            let points = [];
            let activePoint = null;

            function draw() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                points = [];


                // ===== Y AXIS LABEL =====
                ctx.fillStyle = '#666';
                ctx.font = '11px sans-serif';
                ctx.textAlign = 'right';

                const steps = 4;
                for (let i = 0; i <= steps; i++) {
                    const value = Math.round(max * i / steps);
                    const y = canvas.height - padding -
                        (i / steps) * (canvas.height - padding * 2);

                    ctx.fillText(value, padding - 6, y + 4);

                    ctx.strokeStyle = '#eee';
                    ctx.beginPath();
                    ctx.moveTo(padding, y);
                    ctx.lineTo(canvas.width - padding, y);
                    ctx.stroke();
                }

                // ===== X AXIS LABEL =====
                ctx.textAlign = 'center';
                data.forEach((d, i) => {
                    const x = padding + i * (canvas.width - padding * 2) / (data.length - 1);
                    ctx.fillText(d.label, x, canvas.height - padding + 14);
                });

                // Axis
                ctx.strokeStyle = '#ccc';
                ctx.beginPath();
                ctx.moveTo(padding, padding);
                ctx.lineTo(padding, canvas.height - padding);
                ctx.lineTo(canvas.width - padding, canvas.height - padding);
                ctx.stroke();

                // Lines + points
                statuses.forEach(status => {
                    ctx.beginPath();
                    ctx.strokeStyle = colors[status];
                    ctx.lineWidth = 2;

                    data.forEach((d, i) => {
                        const x = padding + i * (canvas.width - padding * 2) / (data.length - 1);
                        const y = canvas.height - padding -
                            (d[status] / max) * (canvas.height - padding * 2);

                        points.push({
                            x,
                            y,
                            status,
                            value: d[status],
                            label: d.label
                        });

                        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
                    });
                    ctx.stroke();
                });

                // Points
                points.forEach(p => {
                    ctx.fillStyle = colors[p.status];
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p === activePoint ? 6 : 3, 0, Math.PI * 2);
                    ctx.fill();
                });

            }

            draw();

            // ===== INTERACTION =====
            function handleEvent(e) {
                const rect = canvas.getBoundingClientRect();
                const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
                const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top;
                let lastPoint = null;

                activePoint = points.find(p =>
                    Math.hypot(p.x - x, p.y - y) < 6
                );
                if (activePoint !== lastPoint) {
                    lastPoint = activePoint;
                    draw();
                }

                if (activePoint) {
                    tooltip.style.display = 'block';
                    tooltip.innerHTML = `
                    <b>${activePoint.label}</b><br>
                    ${activePoint.status}: ${activePoint.value}
                `;
                    tooltip.style.left = activePoint.x + 10 + 'px';
                    tooltip.style.top = activePoint.y - 10 + 'px';

                } else {
                    tooltip.style.display = 'none';
                }

                draw();
            }

            canvas.addEventListener('mousemove', handleEvent);
            canvas.addEventListener('touchstart', handleEvent);
        });

        function resizeCanvas() {
            const width = Math.max(wrapper.offsetWidth, 1200);
            canvas.width = width;
            canvas.height = 260;

            draw(); // render ulang grafik
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
    </script> --}}
@endpush

@push('styles')
    {{-- <style>
        .chart-container {
            width: 100%;
            overflow-x: auto;
            /* auto scroll */
            overflow-y: hidden;
        }

        .chart-inner {
            position: relative;
            min-width: 1200px;
            /* minimal lebar chart */
        }

        /* canvas ikut lebar parent */
        #presensiLine {
            width: 100%;
            height: 260px;
            display: block;
        }

        /* tooltip tetap absolute terhadap chart */
        .chart-tooltip {
            position: absolute;
            background: #fff;
            padding: 8px 10px;
            font-size: 12px;
            border-radius: 6px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, .15);
            pointer-events: none;
            transform: translate(-50%, -110%);
            display: none;
            z-index: 10;
        }

        @media print {
            canvas {
                max-width: 100%;
                height: auto;
            }
        }
    </style> --}}
@endpush
