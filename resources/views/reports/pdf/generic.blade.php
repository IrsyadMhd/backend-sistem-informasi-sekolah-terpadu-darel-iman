<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 6mm; }
        body { font-family: sans-serif; font-size: 8.5px; color: #1e293b; margin: 0; padding: 0; }
        .header { border-bottom: 2px solid #0E5C44; padding-bottom: 6px; margin-bottom: 10px; text-align: center; }
        .header h1 { margin: 0; font-size: 14px; color: #0E5C44; text-transform: uppercase; letter-spacing: 0.5px; }
        .header h2 { margin: 2px 0 0; font-size: 11px; color: #1e293b; text-transform: uppercase; }
        .header p { margin: 2px 0 0; font-size: 8.5px; color: #64748b; }
        .meta-bar { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; padding: 5px 8px; margin-bottom: 10px; width: 100%; }
        .meta-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 1px 0; font-size: 8px; }
        .section-title { font-size: 9.5px; font-weight: bold; color: #0E5C44; margin: 10px 0 5px; border-left: 3px solid #0E5C44; padding-left: 5px; text-transform: uppercase; }
        
        .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
        .kpi-table td { padding: 2px; vertical-align: top; }
        .kpi-box { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 5px; padding: 5px; text-align: center; }
        .kpi-title { font-size: 7.5px; color: #64748b; font-weight: bold; text-transform: uppercase; white-space: nowrap; }
        .kpi-value { font-size: 12px; color: #0E5C44; font-weight: bold; margin-top: 2px; }

        table.data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: auto; }
        table.data-table th { background-color: #0E5C44; color: #ffffff; padding: 4px 5px; font-size: 8px; text-align: left; border: 1px solid #0E5C44; white-space: nowrap; }
        table.data-table th.align-right, table.data-table td.align-right { text-align: right; }
        table.data-table td { padding: 4px 5px; border: 1px solid #e2e8f0; font-size: 8px; vertical-align: middle; }
        table.data-table tr:nth-child(even) { background-color: #f8fafc; }
        table.data-table tr.total-row { background-color: #e2e8f0; font-weight: bold; }
        .footer { margin-top: 15px; border-top: 1px solid #cbd5e1; padding-top: 5px; font-size: 7.5px; color: #94a3b8; }
        .footer table { width: 100%; }
    </style>
</head>
<body>
    <div class="header">
        <h1>YAYASAN DAR EL-IMAN PADANG</h1>
        <h2>{{ $title }}</h2>
        <p>Periode: {{ $period }} • Dicetak: {{ date('d F Y H:i') }} WIB</p>
    </div>

    <div class="meta-bar">
        <table class="meta-table">
            <tr>
                <td><strong>Jenis Dokumen:</strong> Laporan Resmi Pengurus Yayasan</td>
                <td align="right"><strong>Pencetak:</strong> {{ $user }}</td>
            </tr>
            <tr>
                <td><strong>Status:</strong> Validated & Read-Only</td>
                <td align="right"><strong>Sistem:</strong> Sistem Manajemen Sekolah Terpadu</td>
            </tr>
        </table>
    </div>

    @if(!empty($data['summary']))
    <div class="section-title">Ringkasan KPI Utama</div>
    @php
        $kpiLabels = [
            'total_sdm' => 'Total SDM Pegawai',
            'total_guru' => 'Total Guru / Pendidik',
            'total_non_guru' => 'Pegawai Non-Guru',
            'sdm_aktif' => 'SDM Aktif',
            'sdm_nonaktif' => 'SDM Nonaktif',
            'guru_tetap' => 'Guru Tetap',
            'guru_tidak_tetap' => 'Guru Tidak Tetap',
            'pegawai_tetap' => 'Pegawai Tetap',
            'pegawai_tidak_tetap' => 'Pegawai Tidak Tetap',
            'laki_laki' => 'Laki-Laki',
            'perempuan' => 'Perempuan',
            'sdm_baru' => 'SDM Baru (Periode)',
            'sdm_keluar' => 'SDM Keluar',
        ];
        $chunks = array_chunk(array_keys($data['summary']), 4);
    @endphp
    <table class="kpi-table">
        @foreach($chunks as $rowKeys)
        <tr>
            @foreach($rowKeys as $key)
            @php $val = $data['summary'][$key] ?? 0; @endphp
            <td>
                <div class="kpi-box">
                    <div class="kpi-title">{{ $kpiLabels[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</div>
                    <div class="kpi-value">{{ is_numeric($val) ? number_format($val, 0, ',', '.') : $val }}</div>
                </div>
            </td>
            @endforeach
            @for($i = count($rowKeys); $i < 4; $i++)
            <td></td>
            @endfor
        </tr>
        @endforeach
    </table>
    @endif

    @if(!empty($data['unit_recaps']))
    <div class="section-title">Rekapitulasi Per Unit Pendidikan</div>
    @php
        $recapHeaderMap = [
            'unit_name' => 'Unit Pendidikan',
            'guru' => 'Guru',
            'non_guru' => 'Pegawai Non-Guru',
            'total_sdm' => 'Total SDM',
            'aktif' => 'Aktif',
            'nonaktif' => 'Nonaktif',
            'laki_laki' => 'Laki-Laki',
            'perempuan' => 'Perempuan',
        ];
    @endphp
    <table class="data-table">
        <thead>
            <tr>
                @foreach($recapHeaderMap as $key => $label)
                <th class="{{ in_array($key, ['guru', 'non_guru', 'total_sdm', 'aktif', 'nonaktif', 'laki_laki', 'perempuan']) ? 'align-right' : '' }}">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data['unit_recaps'] as $row)
            @php $rowArr = (array)$row; @endphp
            <tr>
                @foreach($recapHeaderMap as $key => $label)
                @php $v = $rowArr[$key] ?? '-'; @endphp
                <td class="{{ in_array($key, ['guru', 'non_guru', 'total_sdm', 'aktif', 'nonaktif', 'laki_laki', 'perempuan']) ? 'align-right' : '' }}">
                    {{ is_numeric($v) ? number_format($v, 0, ',', '.') : $v }}
                </td>
                @endforeach
            </tr>
            @endforeach
            @if(!empty($data['unit_recaps_total']))
            @php $totalArr = (array)$data['unit_recaps_total']; @endphp
            <tr class="total-row">
                @foreach($recapHeaderMap as $key => $label)
                @php $v = $totalArr[$key] ?? '-'; @endphp
                <td class="{{ in_array($key, ['guru', 'non_guru', 'total_sdm', 'aktif', 'nonaktif', 'laki_laki', 'perempuan']) ? 'align-right' : '' }}">
                    {{ is_numeric($v) ? number_format($v, 0, ',', '.') : $v }}
                </td>
                @endforeach
            </tr>
            @endif
        </tbody>
    </table>
    @endif

    @if(!empty($data['details']))
    <div class="section-title">Rincian Data Pembentuk Laporan</div>
    @php
        $detailHeaderMap = [
            'niy' => 'NIY / NIK',
            'nama' => 'Nama Lengkap',
            'jenis_sdm' => 'Jenis SDM',
            'unit' => 'Unit Pendidikan',
            'jabatan' => 'Jabatan',
            'divisi_mapel' => 'Divisi / Mapel',
            'status_kepegawaian' => 'Status Kepegawaian',
            'tanggal_masuk' => 'Tanggal Masuk',
            'status' => 'Status',
        ];
        $sampleRow = (array)$data['details'][0];
        $displayKeys = array_filter(array_keys($sampleRow), function($k) {
            return !in_array($k, ['id', 'unit_id', 'created_at', 'updated_at']);
        });
    @endphp
    <table class="data-table">
        <thead>
            <tr>
                @foreach($displayKeys as $k)
                <th>{{ $detailHeaderMap[$k] ?? ucwords(str_replace('_', ' ', $k)) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data['details'] as $row)
            @php $rowArr = (array)$row; @endphp
            <tr>
                @foreach($displayKeys as $k)
                <td>{{ is_array($rowArr[$k] ?? null) ? json_encode($rowArr[$k]) : ($rowArr[$k] ?? '-') }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        <table>
            <tr>
                <td>Dokumen resmi Sistem Manajemen Sekolah Terpadu — Yayasan Dar el-Iman Padang.</td>
                <td align="right">Halaman 1 dari 1</td>
            </tr>
        </table>
    </div>
</body>
</html>

