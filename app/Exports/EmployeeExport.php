<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $employees) {}

    public function headings(): array
    {
        return [
            'No',
            'NIY',
            'NIK',
            'Nama Lengkap',
            'Gelar Depan',
            'Gelar Belakang',
            'Jenis Kelamin',
            'Jabatan',
            'Unit Kerja',
            'Status Pegawai',
            'Status Keaktifan',
            'No. HP',
            'Email',
            'Alamat',
            'Tanggal Masuk',
        ];
    }

    public function collection(): Collection
    {
        return $this->employees->map(function ($emp, $idx) {
            return [
                $idx + 1,
                $emp->niy ?? '-',
                $emp->nik ?? '-',
                $emp->nama_lengkap ?? $emp->name ?? '-',
                $emp->gelar_depan ?? '-',
                $emp->gelar_belakang ?? '-',
                ($emp->jenis_kelamin === 'L' || $emp->jenis_kelamin === 'male') ? 'Laki-Laki' : 'Perempuan',
                $emp->position?->name ?? $emp->jabatan?->nama_jabatan ?? '-',
                $emp->unit?->name ?? '-',
                $emp->status_pegawai ?? '-',
                $emp->status ?? ($emp->is_active ? 'Aktif' : 'Nonaktif'),
                $emp->no_hp ?? '-',
                $emp->email ?? '-',
                $emp->alamat ?? '-',
                $emp->tanggal_masuk ? (is_string($emp->tanggal_masuk) ? $emp->tanggal_masuk : optional($emp->tanggal_masuk)->format('d/m/Y')) : '-',
            ];
        });
    }
}
