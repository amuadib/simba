<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Jadwal Pelajaran {{ $tahun_ajaran }}</title>
    <style>
        @page {
            size: landscape;
            margin: 10mm;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .header-title {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .header-subtitle {
            font-size: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 2px solid #000;
            text-align: center;
            vertical-align: middle;
        }

        th {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 16px;
        }

        /* Layout specific rows */
        .top-row th {
            height: 40px;
            background-color: #fff;
        }

        .day-row th {
            height: 40px;
        }

        .data-row td {
            height: 60px;
        }

        .waktu-col {
            width: 150px;
        }

        /* Hide UI on print */
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body onload="window.print()">

    @php
        $timeSlots = [];
        foreach ($jadwal as $j) {
            $key = substr($j->jam_mulai, 0, 5) . '-' . substr($j->jam_selesai, 0, 5);
            if (!isset($timeSlots[$key])) {
                $timeSlots[$key] = [
                    'mulai' => substr($j->jam_mulai, 0, 5),
                    'selesai' => substr($j->jam_selesai, 0, 5),
                ];
            }
        }

        usort($timeSlots, function ($a, $b) {
            return strcmp($a['mulai'], $b['mulai']);
        });

        $matrix = [];
        $activeDays = [];
        foreach ($jadwal as $j) {
            $timeKey = substr($j->jam_mulai, 0, 5) . '-' . substr($j->jam_selesai, 0, 5);
            $hari = $j->hari;
            $matrix[$timeKey][$hari][] = $j;
            if (!in_array($hari, $activeDays)) {
                $activeDays[] = $hari;
            }
        }
        sort($activeDays);

        // If there is no data, use default days to match the design (1, 2, 3, 4, 6)
        if (empty($activeDays)) {
            $activeDays = [1, 2, 3, 4, 6];
        }

        $hariNames = [
            1 => 'SENIN',
            2 => 'SELASA',
            3 => 'RABU',
            4 => 'KAMIS',
            5 => 'JUMAT',
            6 => 'SABTU',
            7 => 'AHAD',
        ];
    @endphp

    <div class="header-title">
        JADWAL PELAJARAN
        <div class="header-subtitle">TAHUN AJARAN {{ $tahun_ajaran }}</div>
        <button class="no-print" onclick="window.print()"
            style="float: right; padding: 5px 15px; font-size: 14px; cursor: pointer; background: #000; color: #fff; border: none; border-radius: 4px;">Print
            PDF</button>
    </div>

    <table>
        <tr class="top-row">
            <th rowspan="2" class="waktu-col">WAKTU</th>
            <th colspan="{{ count($activeDays) }}">HARI</th>
        </tr>
        <tr class="day-row">
            @foreach ($activeDays as $hari)
                <th>{{ $hariNames[$hari] ?? '-' }}</th>
            @endforeach
        </tr>

        @forelse($timeSlots as $slot)
            @php
                $timeKey = $slot['mulai'] . '-' . $slot['selesai'];

            @endphp
            <tr class="data-row">
                <td>
                    {{ str_replace(':', '.', $slot['mulai']) }}–{{ str_replace(':', '.', $slot['selesai']) }}
                </td>
                @foreach ($activeDays as $hari)
                    <td>
                        @if (isset($matrix[$timeKey][$hari]))
                            @foreach ($matrix[$timeKey][$hari] as $item)
                                <div>
                                    {{ $item->pembelajaran->keterangan }}
                                </div>
                            @endforeach
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr class="data-row">
                <td colspan="{{ count($activeDays) + 1 }}" style="color: #999; font-style: italic;">
                    Tidak ada jadwal pelajaran.
                </td>
            </tr>
        @endforelse
    </table>

</body>

</html>
