<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class KelasExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $classes) {}

    public function headings(): array
    {
        return [
            'No',
            'Kode Kelas',
            'Nama Kelas',
            'Unit Pendidikan',
            'Jenjang',
            'Tingkat',
            'Wali Kelas',
            'Jumlah Siswa',
            'Kapasitas',
            'Ruangan',
            'Status',
        ];
    }

    public function collection(): Collection
    {
        return $this->classes->map(function ($cls, $idx) {
            $wali = $cls->waliKelas?->nama_lengkap ?? $cls->waliKelas?->name ?? '-';
            $unit = $cls->unitPendidikan?->name ?? '-';
            $jmlSiswa = $cls->siswa_count ?? ($cls->siswa ? $cls->siswa->count() : 0);

            return [
                $idx + 1,
                $cls->kode_kelas ?? '-',
                $cls->nama_kelas ?? '-',
                $unit,
                $cls->jenjang ?? '-',
                $cls->tingkat ?? '-',
                $wali,
                $jmlSiswa,
                $cls->kapasitas ?? 30,
                $cls->ruangan ?? '-',
                $cls->status ?? 'Aktif',
            ];
        });
    }
}
