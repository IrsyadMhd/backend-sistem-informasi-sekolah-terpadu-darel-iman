<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 20px; }
        .header { border-bottom: 2px solid #0E5C44; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 18px; color: #0E5C44; text-transform: uppercase; }
        .header p { margin: 3px 0 0; font-size: 11px; color: #64748b; }
        .meta-bar { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; margin-bottom: 15px; width: 100%; }
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 2px 0; font-size: 10px; }
        .section-title { font-size: 13px; font-weight: bold; color: #0E5C44; margin: 15px 0 8px; border-left: 3px solid #0E5C44; padding-left: 6px; }
        .kpi-grid { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .kpi-card { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px; text-align: center; width: 23%; }
        .kpi-title { font-size: 9px; color: #64748b; font-weight: bold; text-transform: uppercase; }
        .kpi-value { font-size: 16px; color: #0E5C44; font-weight: bold; margin-top: 4px; }
        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table.data-table th { background-color: #0E5C44; color: #ffffff; padding: 6px; font-size: 10px; text-align: left; border: 1px solid #0E5C44; }
        table.data-table td { padding: 5px 6px; border: 1px solid #e2e8f0; font-size: 10px; }
        table.data-table tr:nth-child(even) { background-color: #f8fafc; }
        table.data-table tr.total-row { background-color: #f1f5f9; font-weight: bold; }
        .footer { margin-top: 30px; border-top: 1px solid #cbd5e1; padding-top: 10px; font-size: 9px; color: #94a3b8; text-align: justify; }
        .footer table { width: 100%; }
    </style>
</head>
<body>
    <div class="header">
        <h1>YAYASAN DAR EL-IMAN PADANG</h1>
        <p>{{ $title }} • Periode: {{ $period }}</p>
    </div>

    <div class="meta-bar">
        <table class="meta-table">
            <tr>
                <td><strong>Laporan:</strong> {{ $title }}</td>
                <td align="right"><strong>Tanggal Cetak:</strong> {{ date('d F Y H:i') }} WIB</td>
            </tr>
            <tr>
                <td><strong>Pencetak:</strong> {{ $user }}</td>
                <td align="right"><strong>Sistem:</strong> Sistem Manajemen Sekolah Terpadu</td>
            </tr>
        </table>
    </div>

    @if(!empty($data['summary']))
    <div class="section-title">RINGKASAN KPI LAPORAN</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Indikator Utama</th>
                <th style="text-align: right;">Nilai / Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['summary'] as $k => $v)
            <tr>
                <td>{{ ucwords(str_replace('_', ' ', $k)) }}</td>
                <td style="text-align: right; font-weight: bold;">{{ is_array($v) ? json_encode($v) : $v }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    @if(!empty($data['unit_recaps']))
    <div class="section-title">REKAPITULASI PER UNIT PENDIDIKAN</div>
    <table class="data-table">
        <thead>
            <tr>
                @foreach(array_keys((array)$data['unit_recaps'][0]) as $col)
                <th>{{ ucwords(str_replace('_', ' ', $col)) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data['unit_recaps'] as $row)
            <tr>
                @foreach((array)$row as $val)
                <td>{{ is_array($val) ? json_encode($val) : $val }}</td>
                @endforeach
            </tr>
            @endforeach
            @if(!empty($data['unit_recaps_total']))
            <tr class="total-row">
                @foreach((array)$data['unit_recaps_total'] as $val)
                <td>{{ is_array($val) ? json_encode($val) : $val }}</td>
                @endforeach
            </tr>
            @endif
        </tbody>
    </table>
    @endif

    @if(!empty($data['details']))
    <div class="section-title">RINCIAN DATA Pembentuk LAPORAN</div>
    <table class="data-table">
        <thead>
            <tr>
                @foreach(array_keys((array)$data['details'][0]) as $col)
                @if($col !== 'id')
                <th>{{ ucwords(str_replace('_', ' ', $col)) }}</th>
                @endif
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data['details'] as $row)
            <tr>
                @foreach((array)$row as $colKey => $val)
                @if($colKey !== 'id')
                <td>{{ is_array($val) ? json_encode($val) : $val }}</td>
                @endif
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        <table>
            <tr>
                <td>Dokumen ini dihasilkan secara otomatis oleh Sistem Manajemen Sekolah Terpadu Yayasan Dar el-Iman.</td>
                <td align="right">Halaman 1 dari 1</td>
            </tr>
        </table>
    </div>
</body>
</html>
