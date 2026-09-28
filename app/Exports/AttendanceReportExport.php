<?php

namespace App\Exports;

use App\Models\LmsPresensi;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AttendanceReportExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    protected Builder $query;
    private int $rowNumber = 0;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'No',
            'NIS',
            'NISN',
            'Nama Siswa',
            'Unit Pendidikan',
            'Kelas & Rombel',
            'Tanggal',
            'Mata Pelajaran',
            'Status Presensi',
            'Catatan',
        ];
    }

    /**
     * @param LmsPresensi $row
     */
    public function map($row): array
    {
        $this->rowNumber++;

        $statusLabels = [
            'hadir' => 'Hadir',
            'terlambat' => 'Terlambat',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alpa' => 'Alpha',
        ];

        $statusKey = strtolower($row->status_hadir ?? '');
        $statusStr = $statusLabels[$statusKey] ?? ($row->status_hadir ?? '-');

        $unitName = $row->siswa?->educationUnit?->name 
            ?? $row->siswa?->kelas?->unitPendidikan?->name 
            ?? $row->jadwalPelajaran?->kelas?->unitPendidikan?->name 
            ?? '-';

        $kelasName = $row->siswa?->kelas?->nama_kelas 
            ?? $row->jadwalPelajaran?->kelas?->nama_kelas 
            ?? '-';

        $subjectName = $row->jadwalPelajaran?->subject?->name ?? '-';

        $tanggalStr = $row->tanggal ? date('d-m-Y', strtotime($row->tanggal)) : '-';

        return [
            $this->rowNumber,
            $row->siswa?->nis ?? '-',
            $row->siswa?->nisn ?? '-',
            $row->siswa?->full_name ?? '-',
            $unitName,
            $kelasName,
            $tanggalStr,
            $subjectName,
            $statusStr,
            $row->catatan ?? ($row->keterangan ?? '-'),
        ];
    }
}
