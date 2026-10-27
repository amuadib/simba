<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Surat - {{ $surat->nomor_surat }}</title>
    <link href="{{ asset('bootstrap-icons.css') }}" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 20mm 20mm 20mm 20mm;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
            background-color: #f1f5f9;
            margin: 0;
            padding: 20px;
        }

        .paper {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 20mm 20mm 25mm 20mm;
            box-sizing: border-box;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
        }

        /* Kop Surat Styling */
        .kop-container {
            margin-bottom: 20px;
        }
        .kop-image {
            width: 100%;
            max-height: 130px;
            object-fit: contain;
            display: block;
        }
        .kop-text-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            padding-bottom: 10px;
            text-align: center;
        }
        .kop-logo {
            width: 85px;
            height: 85px;
            object-fit: contain;
        }
        .kop-title {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .kop-subtitle {
            font-size: 13pt;
            font-weight: bold;
            margin: 2px 0;
        }
        .kop-address {
            font-size: 10pt;
            margin: 2px 0;
            font-family: Arial, sans-serif;
            color: #222;
        }
        .kop-divider {
            border-top: 3px solid #000;
            border-bottom: 1px solid #000;
            height: 2px;
            margin-top: 6px;
            margin-bottom: 25px;
        }

        /* Content Styling */
        .surat-body {
            text-align: justify;
            min-height: 480px;
        }
        .surat-body p {
            margin: 0 0 10px 0;
        }
        .surat-body table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Tanda Tangan */
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: flex-end;
            page-break-inside: avoid;
        }
        .signature-box {
            width: 250px;
            text-align: center;
            position: relative;
        }
        .stempel-image {
            position: absolute;
            left: 10px;
            top: 25px;
            width: 95px;
            opacity: 0.85;
            pointer-events: none;
            z-index: 1;
        }
        .ttd-image {
            position: absolute;
            left: 55px;
            top: 25px;
            width: 120px;
            z-index: 2;
        }
        .signature-space {
            height: 75px;
        }

        /* Print Toolbar */
        .print-toolbar {
            position: fixed;
            top: 15px;
            right: 20px;
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(8px);
            padding: 8px 16px;
            border-radius: 9999px;
            display: flex;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            z-index: 999;
        }
        .print-btn {
            background: #0ea5e9;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .print-btn:hover {
            background: #0284c7;
        }
        .close-btn {
            background: #475569;
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .close-btn:hover {
            background: #334155;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .paper {
                box-shadow: none;
                padding: 0;
                width: 100%;
                min-height: auto;
            }
            .print-toolbar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    {{-- Floating Print Control Bar --}}
    <div class="print-toolbar">
        <button onclick="window.print()" class="print-btn">
            <i class="bi bi-printer"></i> Cetak Dokumen
        </button>
        <button onclick="window.close()" class="close-btn">
            <i class="bi bi-x-circle"></i> Tutup
        </button>
    </div>

    <div class="paper">
        {{-- KOP SURAT --}}
        <div class="kop-container">
            @php
                $kopImage = kop_url($surat->lembaga_id);
                $logoUrl = isset($lembagaInfo['logo']) && $lembagaInfo['logo'] ? asset($lembagaInfo['logo']) : logo_url();
            @endphp

            @if ($kopImage)
                <img src="{{ $kopImage }}" alt="Kop Surat" class="kop-image">
            @else
                <div class="kop-text-wrapper">
                    <img src="{{ $logoUrl }}" alt="Logo" class="kop-logo">
                    <div style="flex: 1;">
                        <div class="kop-title">{{ config('local.lembaga.99.nama', 'YAYASAN PENDIDIKAN ISLAM BASTOMIYAH RAHMAN') }}</div>
                        <div class="kop-subtitle">{{ $lembagaInfo['nama'] ?? nama_lembaga($surat->lembaga_id) }}</div>
                        <div class="kop-address">
                            {{ $lembagaInfo['alamat'] ?? '' }}
                            @if (!empty($lembagaInfo['kontak']))
                                • Telp: {{ $lembagaInfo['kontak'] }}
                            @endif
                            @if (!empty($lembagaInfo['email']))
                                • Email: {{ $lembagaInfo['email'] }}
                            @endif
                        </div>
                    </div>
                </div>
                <div class="kop-divider"></div>
            @endif
        </div>

        {{-- BODY SURAT --}}
        <div class="surat-body">
            {!! $surat->konten !!}
        </div>

        {{-- SIGNATURE SECTION --}}
        <div class="signature-section">
            <div class="signature-box">
                <div>
                    {{ $lembagaInfo['kota'] ?? 'Boyolali' }}, 
                    {{ $surat->tanggal_surat ? $surat->tanggal_surat->isoFormat('D MMMM Y') : now()->isoFormat('D MMMM Y') }}
                </div>
                <div style="font-weight: bold; margin-top: 2px;">
                    Kepala {{ $surat->nama_lembaga }}
                </div>

                <div class="signature-space">
                    @php
                        $stempel = stempel_url($surat->lembaga_id);
                        $ttd = ttd_url($surat->lembaga_id);
                    @endphp
                    @if ($stempel)
                        <img src="{{ $stempel }}" alt="Stempel" class="stempel-image">
                    @endif
                    @if ($ttd)
                        <img src="{{ $ttd }}" alt="TTD" class="ttd-image">
                    @endif
                </div>

                <div style="font-weight: bold; text-decoration: underline;">
                    {{ $lembagaInfo['kepala'] ?? kepala_lembaga($surat->lembaga_id) }}
                </div>
                @if (!empty($lembagaInfo['nip_kepala']))
                    <div style="font-size: 10pt;">
                        NIY/NIP: {{ $lembagaInfo['nip_kepala'] }}
                    </div>
                @endif
            </div>
        </div>
    </div>

</body>
</html>
