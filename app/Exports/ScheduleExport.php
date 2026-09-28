<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ScheduleExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $schedules) {}

    public function headings(): array
    {
        return [
            'No',
            'Hari',
            'Jam Mulai',
            'Jam Selesai',
            'Mata Pelajaran',
            'Kelas',
            'Guru Pengampu',
            'Tahun Ajaran',
            'Semester',
            'Ruangan',
        ];
    }

    public function collection(): Collection
    {
        $dayNames = [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Ahad',
        ];

        return $this->schedules->map(function ($sch, $idx) use ($dayNames) {
            $day = $dayNames[$sch->day_of_week] ?? $sch->day_name ?? $sch->day_of_week ?? '-';
            $mapel = $sch->subject?->nama_mapel ?? $sch->subject?->name ?? '-';
            $kelas = $sch->kelas?->nama_kelas ?? $sch->schoolClass?->name ?? '-';
            $guru = $sch->employee?->nama_lengkap ?? $sch->teacher?->name ?? '-';
            $thn = $sch->academicYear?->name ?? '-';
            $sem = $sch->semester?->name ?? '-';

            return [
                $idx + 1,
                $day,
                $sch->start_time ?? '-',
                $sch->end_time ?? '-',
                $mapel,
                $kelas,
                $guru,
                $thn,
                $sem,
                $sch->room ?? '-',
            ];
        });
    }
}
