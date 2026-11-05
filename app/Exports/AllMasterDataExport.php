<?php

namespace App\Exports;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Position;
use App\Models\Student;
use App\Models\Subject;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class AllMasterDataExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new UnitPendidikanExportSheet(),
            new JabatanExportSheet(),
            new KelasExportSheet(),
            new MapelExportSheet(),
            new PegawaiExportSheet(),
            new SiswaExportSheet(),
        ];
    }
}

class UnitPendidikanExportSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize
{
    public function title(): string
    {
        return 'Unit Pendidikan';
    }

    public function headings(): array
    {
        return ['No', 'Kode Unit', 'Nama Unit Pendidikan', 'Jenjang', 'Status Aktif', 'Keterangan'];
    }

    public function array(): array
    {
        $units = EducationUnit::query()->orderBy('name')->get();
        $rows = [];
        foreach ($units as $idx => $u) {
            $rows[] = [
                $idx + 1,
                $u->code ?? '-',
                $u->name,
                $u->level ?? '-',
                $u->is_active ? 'Aktif' : 'Nonaktif',
                $u->description ?? '-',
            ];
        }
        return $rows;
    }
}

class JabatanExportSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize
{
    public function title(): string
    {
        return 'Jabatan & Struktur';
    }

    public function headings(): array
    {
        return ['No', 'Nama', 'Satuan Kerja', 'Level User', 'Kode Jabatan', 'Status'];
    }

    public function array(): array
    {
        $positions = Position::query()->orderBy('urutan')->get();
        $rows = [];
        foreach ($positions as $idx => $p) {
            $meta = is_array($p->metadata) ? $p->metadata : [];
            $rows[] = [
                $idx + 1,
                $p->name,
                $p->satuan_kerja ?? ($meta['satuan_kerja'] ?? 'Unit Pendidikan'),
                $p->level_jabatan ?? ($meta['level_user'] ?? 8),
                $p->code,
                $p->is_active ? 'Aktif' : 'Nonaktif',
            ];
        }
        return $rows;
    }
}

class KelasExportSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize
{
    public function title(): string
    {
        return 'Kelas & Rombel';
    }

    public function headings(): array
    {
        return ['No', 'Nama Kelas', 'Tingkat', 'Unit Pendidikan', 'Wali Kelas', 'Kapasitas', 'Status'];
    }

    public function array(): array
    {
        $classes = Kelas::with(['unitPendidikan', 'waliKelas'])->orderBy('nama_kelas')->get();
        $rows = [];
        foreach ($classes as $idx => $c) {
            $rows[] = [
                $idx + 1,
                $c->nama_kelas,
                $c->tingkat ?? '-',
                $c->unitPendidikan?->name ?? '-',
                $c->waliKelas?->nama_lengkap ?? '-',
                (int) $c->kapasitas,
                $c->status ?? ($c->is_active ? 'Aktif' : 'Nonaktif'),
            ];
        }
        return $rows;
    }
}

class MapelExportSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize
{
    public function title(): string
    {
        return 'Mata Pelajaran';
    }

    public function headings(): array
    {
        return ['No', 'Kode Mapel', 'Nama Mata Pelajaran', 'Kategori', 'KKM Standar', 'Status'];
    }

    public function array(): array
    {
        $subjects = Subject::query()->orderBy('name')->get();
        $rows = [];
        foreach ($subjects as $idx => $s) {
            $meta = is_array($s->metadata) ? $s->metadata : [];
            $rows[] = [
                $idx + 1,
                $s->code,
                $s->name,
                $meta['category'] ?? ($s->description ?? 'Umum'),
                $meta['kkm'] ?? 75,
                $s->is_active ?? true ? 'Aktif' : 'Nonaktif',
            ];
        }
        return $rows;
    }
}

class PegawaiExportSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize
{
    public function title(): string
    {
        return 'Pegawai & Guru';
    }

    public function headings(): array
    {
        return [
            'No',
            'Unit Pendidikan',
            'NIY (No Induk Yayasan)',
            'Nama Lengkap',
            'Jenis Kelamin',
            'Satuan Kerja Asal',
            'Jabatan',
            'Kelas Perwalian',
            'Kelas Yang Diajar',
            'Mapel Yang Diajar',
            'Nomor HP',
            'Email',
            'NIK',
        ];
    }

    public function array(): array
    {
        $employees = Employee::with(['unit', 'position'])->orderBy('nama_lengkap')->get();
        $rows = [];
        foreach ($employees as $idx => $e) {
            $meta = is_array($e->metadata) ? $e->metadata : [];
            $rows[] = [
                $idx + 1,
                $e->unit?->name ?? ($meta['unit_pendidikan'] ?? '-'),
                $e->niy ?? '-',
                $e->nama_lengkap,
                ($e->jenis_kelamin === 'L' || $e->jenis_kelamin === 'male') ? 'Laki-laki' : 'Perempuan',
                $meta['satuan_kerja_asal'] ?? ($e->unit?->name ?? '-'),
                $e->position?->name ?? ($meta['jabatan'] ?? 'Guru'),
                $meta['kelas_perwalian'] ?? '-',
                $meta['kelas_diajar'] ?? '-',
                $meta['mapel_diajar'] ?? '-',
                $e->no_hp ?? '-',
                $e->email ?? '-',
                $e->nik ?? '-',
            ];
        }
        return $rows;
    }
}

class SiswaExportSheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize
{
    public function title(): string
    {
        return 'Siswa & Santri';
    }

    public function headings(): array
    {
        return (new StudentExport(Student::query()))->headings();
    }

    public function array(): array
    {
        $exporter = new StudentExport(Student::with(['educationUnit', 'kelas', 'parent']));
        $students = Student::with(['educationUnit', 'kelas', 'parent'])->orderBy('full_name')->get();
        $rows = [];
        foreach ($students as $std) {
            $rows[] = $exporter->map($std);
        }
        return $rows;
    }
}
