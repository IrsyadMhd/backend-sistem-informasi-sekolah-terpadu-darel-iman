<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 12mm 15mm 12mm 15mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8.5pt;
            color: #0f172a;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .header-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }
        .logo-box {
            width: 58px;
            height: 48px;
            border: 2px solid #047857;
            border-radius: 8px;
            background-color: #ecfdf5;
            color: #047857;
            text-align: center;
            line-height: 48px;
            font-size: 13pt;
            font-weight: bold;
        }
        .inst-name {
            font-size: 12pt;
            font-weight: bold;
            color: #047857;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .inst-motto {
            font-size: 8pt;
            font-style: italic;
            color: #475569;
            margin-top: 2px;
        }
        .inst-address {
            font-size: 7.5pt;
            color: #334155;
            margin-top: 2px;
        }
        .inst-legal {
            font-size: 7pt;
            color: #64748b;
            margin-top: 2px;
            font-weight: bold;
        }
        .header-divider {
            border-bottom: 2px solid #047857;
            margin-top: 6px;
            margin-bottom: 12px;
        }
        .title-box {
            text-align: center;
            margin-bottom: 12px;
        }
        .doc-title {
            font-size: 11pt;
            font-weight: bold;
            color: #064e3b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 3px 0;
        }
        .doc-subtitle {
            font-size: 8pt;
            color: #475569;
            font-weight: 600;
            margin: 0 0 2px 0;
        }
        .doc-meta {
            font-size: 7.5pt;
            color: #64748b;
            margin: 0;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .data-table th {
            background-color: #0E5C44;
            color: #ffffff;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 5px 6px;
            border: 1px solid #0E5C44;
            text-align: left;
        }
        .data-table td {
            padding: 4.5px 6px;
            font-size: 7.5pt;
            border: 1px solid #cbd5e1;
            color: #1e293b;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
        }
        .footer-table td {
            border: none;
            padding: 4px 0 0 0;
            font-size: 7pt;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <!-- KOP SURAT RESMI LEMBAGA (DATABASE ALIGNED) -->
    <table class="header-table">
        <tr>
            <td style="width: 70px;">
                <div class="logo-box">{{ $logoText ?? 'SIT' }}</div>
            </td>
            <td style="text-align: center;">
                <div class="inst-name">{{ $schoolName }}</div>
                @if(!empty($motto))
                    <div class="inst-motto">"{{ $motto }}"</div>
                @endif
                @if(!empty($address) || !empty($phone))
                    <div class="inst-address">{{ $address }}{{ !empty($phone) ? ' · Telp: ' . $phone : '' }}</div>
                @endif
                @if(!empty($skPendirian))
                    <div class="inst-legal">Badan Hukum SK Kemenkumham No. {{ $skPendirian }}</div>
                @endif
            </td>
            <td style="width: 70px; text-align: right;">
                <div style="font-size: 7pt; font-weight: bold; color: #047857; text-align: right; padding: 4px 6px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px;">
                    DOKUMEN<br>RESMI
                </div>
            </td>
        </tr>
    </table>
    <div class="header-divider"></div>

    <!-- JUDUL DOKUMEN & METADATA -->
    <div class="title-box">
        <h1 class="doc-title">{{ $title }}</h1>
        @if(!empty($subtitle))
            <div class="doc-subtitle">{{ $subtitle }}</div>
        @endif
        <div class="doc-meta">
            @if(!empty($period)) Periode: {{ $period }} &nbsp;·&nbsp; @endif
            Waktu Cetak: {{ $printDate }} &nbsp;·&nbsp; Total: {{ count($rows) }} Data
        </div>
    </div>

    <!-- DATA TABLE -->
    <table class="data-table">
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell !== null && $cell !== '' ? $cell : '-' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) ?: 1 }}" style="text-align: center; padding: 12px; color: #64748b;">
                        Tidak ada data yang tersedia.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- FOOTER DOKUMEN -->
    <table class="footer-table">
        <tr>
            <td style="text-align: left;">Dokumen Sah Sistem Manajemen Sekolah Terpadu (SIMSIT)</td>
            <td style="text-align: right;">Halaman 1 (Format PDF Resmi)</td>
        </tr>
    </table>
</body>
</html>
