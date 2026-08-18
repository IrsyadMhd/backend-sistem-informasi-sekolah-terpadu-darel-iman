<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { font-size: 16px; margin-bottom: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 5px; }
        th { background: #e2e8f0; text-align: left; }
    </style>
</head>
<body>
    <h1>Master Mata Pelajaran</h1>
    <table>
        <thead>
        <tr>
            <th>Kode</th>
            <th>Nama</th>
            <th>Unit</th>
            <th>Kurikulum</th>
            <th>Kelompok</th>
            <th>Kategori</th>
            <th>Jenjang</th>
            <th>JP</th>
            <th>KKM</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($subjects as $subject)
            <tr>
                <td>{{ $subject->kode_mapel ?? $subject->code }}</td>
                <td>{{ $subject->nama_mapel ?? $subject->name }}</td>
                <td>{{ $subject->unitPendidikan?->name ?? '-' }}</td>
                <td>{{ $subject->kurikulum?->nama_kurikulum ?? '-' }}</td>
                <td>{{ $subject->kelompok_mapel ?? '-' }}</td>
                <td>{{ $subject->kategori ?? '-' }}</td>
                <td>{{ $subject->jenjang ?? '-' }}</td>
                <td>{{ $subject->jam_pelajaran ?? '-' }}</td>
                <td>{{ $subject->kkm ?? '-' }}</td>
                <td>{{ $subject->status ? 'Aktif' : 'Nonaktif' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
