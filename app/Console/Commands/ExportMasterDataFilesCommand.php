<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Position;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportMasterDataFilesCommand extends Command
{
    protected $signature = 'app:export-master-data {--output= : Path direktori target output}';
    protected $description = 'Ekspor seluruh 7 data master dari database PostgreSQL ke file Excel (.xlsx)';

    public function handle(): int
    {
        $this->info('Memulai ekspor 7 Master Data dari PostgreSQL...');

        $targetDir = $this->option('output') ?: base_path('../exports');
        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        // 1. Export Unit Pendidikan
        $this->exportEducationUnits($targetDir);

        // 2. Export Tahun Ajaran & Semester
        $this->exportAcademicYears($targetDir);

        // 3. Export Jabatan & Struktur
        $this->exportPositions($targetDir);

        // 4. Export Kelas & Rombel
        $this->exportClasses($targetDir);

        // 5. Export Pegawai & Guru
        $this->exportEmployees($targetDir);

        // 6. Export Siswa / Santri
        $this->exportStudents($targetDir);

        // 7. Export Mata Pelajaran
        $this->exportSubjects($targetDir);

        // 8. Export Combined Master Workbook (Semua Sheet)
        $this->exportCombinedWorkbook($targetDir);

        $this->info("Semua file Excel berhasil diekspor ke: {$targetDir}");
        return 0;
    }

    private function applyHeaderStyle($sheet, string $maxCol): void
    {
        $sheet->getStyle("A1:{$maxCol}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '059669'], // Emerald 600
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);
    }

    private function autoFitColumns($sheet, string $maxCol): void
    {
        foreach (range('A', $maxCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function exportEducationUnits(string $targetDir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Unit Pendidikan');

        $headers = ['No', 'ID Unit', 'Kode Unit', 'Nama Unit Pendidikan', 'Jenjang', 'Status'];
        $sheet->fromArray([$headers], null, 'A1');
        $this->applyHeaderStyle($sheet, 'F');

        $units = EducationUnit::orderBy('name')->get();
        $rowIdx = 2;
        foreach ($units as $i => $u) {
            $sheet->fromArray([[
                $i + 1,
                (string)$u->id,
                $u->code ?? '-',
                $u->name,
                $u->level ?? '-',
                $u->is_active ? 'Aktif' : 'Nonaktif',
            ]], null, "A{$rowIdx}");
            $rowIdx++;
        }

        $this->autoFitColumns($sheet, 'F');
        $writer = new Xlsx($spreadsheet);
        $writer->save($targetDir . '/01_Unit_Pendidikan.xlsx');
        $this->line(" [✓] 01_Unit_Pendidikan.xlsx (" . count($units) . " baris)");
    }

    private function exportAcademicYears(string $targetDir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tahun Ajaran & Semester');

        $headers = ['No', 'ID Tahun Ajaran', 'Nama Tahun Ajaran', 'Tanggal Mulai', 'Tanggal Selesai', 'Status Tahun Ajaran', 'ID Semester', 'Nama Semester', 'Periode Semester', 'Status Semester'];
        $sheet->fromArray([$headers], null, 'A1');
        $this->applyHeaderStyle($sheet, 'J');

        $years = AcademicYear::with('semesters')->orderBy('start_date', 'desc')->get();
        $rowIdx = 2;
        $counter = 1;
        foreach ($years as $y) {
            $semesters = $y->semesters;
            if ($semesters->isEmpty()) {
                $sheet->fromArray([[
                    $counter++,
                    (string)$y->id,
                    $y->name,
                    $y->start_date ? $y->start_date->format('Y-m-d') : '-',
                    $y->end_date ? $y->end_date->format('Y-m-d') : '-',
                    $y->is_active ? 'Aktif' : 'Nonaktif',
                    '-',
                    '-',
                    '-',
                    '-',
                ]], null, "A{$rowIdx}");
                $rowIdx++;
            } else {
                foreach ($semesters as $s) {
                    $sheet->fromArray([[
                        $counter++,
                        (string)$y->id,
                        $y->name,
                        $y->start_date ? $y->start_date->format('Y-m-d') : '-',
                        $y->end_date ? $y->end_date->format('Y-m-d') : '-',
                        $y->is_active ? 'Aktif' : 'Nonaktif',
                        (string)$s->id,
                        $s->name,
                        ($s->start_date ? $s->start_date->format('Y-m-d') : '') . ' s/d ' . ($s->end_date ? $s->end_date->format('Y-m-d') : ''),
                        $s->is_active ? 'Aktif' : 'Nonaktif',
                    ]], null, "A{$rowIdx}");
                    $rowIdx++;
                }
            }
        }

        $this->autoFitColumns($sheet, 'J');
        $writer = new Xlsx($spreadsheet);
        $writer->save($targetDir . '/02_Tahun_Ajaran_dan_Semester.xlsx');
        $this->line(" [✓] 02_Tahun_Ajaran_dan_Semester.xlsx (" . ($counter - 1) . " baris)");
    }

    private function exportPositions(string $targetDir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jabatan');

        $headers = ['No', 'ID Jabatan', 'Kode Jabatan', 'Nama Jabatan', 'Satuan Kerja', 'Level User', 'Status'];
        $sheet->fromArray([$headers], null, 'A1');
        $this->applyHeaderStyle($sheet, 'G');

        $positions = Position::orderBy('name')->get();
        $rowIdx = 2;
        foreach ($positions as $i => $p) {
            $sheet->fromArray([[
                $i + 1,
                (string)$p->id,
                $p->code ?? '-',
                $p->name,
                $p->satuan_kerja ?? ($p->metadata['satuan_kerja'] ?? '-'),
                $p->level_jabatan ?? ($p->metadata['level_user'] ?? '-'),
                $p->is_active ? 'Aktif' : 'Nonaktif',
            ]], null, "A{$rowIdx}");
            $rowIdx++;
        }

        $this->autoFitColumns($sheet, 'G');
        $writer = new Xlsx($spreadsheet);
        $writer->save($targetDir . '/03_Jabatan_dan_Struktur.xlsx');
        $this->line(" [✓] 03_Jabatan_dan_Struktur.xlsx (" . count($positions) . " baris)");
    }

    private function exportClasses(string $targetDir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kelas & Rombel');

        $headers = ['No', 'ID Kelas', 'Nama Kelas / Rombel', 'Tingkat', 'Unit Pendidikan', 'Wali Kelas', 'Kapasitas', 'Ruangan', 'Status'];
        $sheet->fromArray([$headers], null, 'A1');
        $this->applyHeaderStyle($sheet, 'I');

        $classes = Kelas::with(['unitPendidikan', 'waliKelas'])->orderBy('nama_kelas')->get();
        $rowIdx = 2;
        foreach ($classes as $i => $c) {
            $sheet->fromArray([[
                $i + 1,
                (string)$c->id,
                $c->nama_kelas,
                $c->tingkat ?? '-',
                $c->unitPendidikan?->name ?? '-',
                $c->waliKelas?->nama_lengkap ?? '-',
                (int)$c->kapasitas,
                $c->ruangan ?? '-',
                $c->status ?? ($c->is_active ? 'Aktif' : 'Nonaktif'),
            ]], null, "A{$rowIdx}");
            $rowIdx++;
        }

        $this->autoFitColumns($sheet, 'I');
        $writer = new Xlsx($spreadsheet);
        $writer->save($targetDir . '/04_Kelas_dan_Rombel.xlsx');
        $this->line(" [✓] 04_Kelas_dan_Rombel.xlsx (" . count($classes) . " baris)");
    }

    private function exportEmployees(string $targetDir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pegawai & Guru');

        $headers = ['No', 'ID Pegawai', 'NIY', 'NIK', 'Nama Lengkap', 'Jenis Kelamin', 'Unit Pendidikan', 'Jabatan', 'Status Pegawai', 'Status Keaktifan', 'No HP', 'Email'];
        $sheet->fromArray([$headers], null, 'A1');
        $this->applyHeaderStyle($sheet, 'L');

        $employees = Employee::with(['unit', 'position'])->orderBy('nama_lengkap')->get();
        $rowIdx = 2;
        foreach ($employees as $i => $e) {
            $sheet->fromArray([[
                $i + 1,
                (string)$e->id,
                $e->niy ? "'{$e->niy}" : '-',
                $e->nik ? "'{$e->nik}" : '-',
                $e->nama_lengkap,
                $e->jenis_kelamin === 'L' || $e->jenis_kelamin === 'male' ? 'Laki-laki' : 'Perempuan',
                $e->unit?->name ?? '-',
                $e->position?->name ?? '-',
                $e->status_pegawai ?? 'Tetap',
                $e->status ?? 'Aktif',
                $e->no_hp ? "'{$e->no_hp}" : '-',
                $e->email ?? '-',
            ]], null, "A{$rowIdx}");
            $rowIdx++;
        }

        $this->autoFitColumns($sheet, 'L');
        $writer = new Xlsx($spreadsheet);
        $writer->save($targetDir . '/05_Pegawai_dan_Guru.xlsx');
        $this->line(" [✓] 05_Pegawai_dan_Guru.xlsx (" . count($employees) . " baris)");
    }

    private function exportStudents(string $targetDir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Buku Induk Siswa');

        $headers = ['No', 'ID Siswa', 'NIS', 'NISN', 'Nama Lengkap Siswa / Santri', 'Jenis Kelamin', 'Unit Pendidikan', 'Kelas / Rombel', 'Tempat Lahir', 'Tanggal Lahir', 'Status Keaktifan', 'Nama Orang Tua / Wali', 'No WhatsApp Ortu'];
        $sheet->fromArray([$headers], null, 'A1');
        $this->applyHeaderStyle($sheet, 'M');

        $students = Student::with(['unit', 'kelas', 'parent'])->orderBy('full_name')->get();
        $rowIdx = 2;
        foreach ($students as $i => $s) {
            $sheet->fromArray([[
                $i + 1,
                (string)$s->id,
                $s->nis ? "'{$s->nis}" : '-',
                $s->nisn ? "'{$s->nisn}" : '-',
                $s->full_name,
                $s->gender === 'female' ? 'Perempuan' : 'Laki-laki',
                $s->unit?->name ?? '-',
                $s->kelas?->nama_kelas ?? '-',
                $s->birth_place ?? '-',
                $s->birth_date ? (is_string($s->birth_date) ? $s->birth_date : $s->birth_date->format('Y-m-d')) : '-',
                $s->is_active ? 'Aktif' : 'Nonaktif',
                $s->parent?->full_name ?? ($s->metadata['nama_ayah'] ?? ($s->metadata['nama_ibu'] ?? '-')),
                $s->parent?->phone ? "'{$s->parent->phone}" : ($s->metadata['no_hp_ayah'] ?? ($s->metadata['no_hp_ibu'] ?? '-')),
            ]], null, "A{$rowIdx}");
            $rowIdx++;
        }

        $this->autoFitColumns($sheet, 'M');
        $writer = new Xlsx($spreadsheet);
        $writer->save($targetDir . '/06_Buku_Induk_Siswa.xlsx');
        $this->line(" [✓] 06_Buku_Induk_Siswa.xlsx (" . count($students) . " baris)");
    }

    private function exportSubjects(string $targetDir): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Mata Pelajaran');

        $headers = ['No', 'ID Mapel', 'Kode Mapel', 'Nama Mata Pelajaran', 'Kategori', 'KKM Standar', 'Jam Pelajaran', 'Status'];
        $sheet->fromArray([$headers], null, 'H1');
        $this->applyHeaderStyle($sheet, 'H');

        $subjects = Subject::orderBy('name')->get();
        $rowIdx = 2;
        foreach ($subjects as $i => $sb) {
            $sheet->fromArray([[
                $i + 1,
                (string)$sb->id,
                $sb->code ?? $sb->kode_mapel ?? '-',
                $sb->name ?? $sb->nama_mapel,
                $sb->kategori ?? ($sb->metadata['category'] ?? ($sb->description ?? 'Umum')),
                $sb->kkm ?? ($sb->metadata['kkm'] ?? 75),
                $sb->jam_pelajaran ?? 2,
                $sb->status ? 'Aktif' : 'Nonaktif',
            ]], null, "A{$rowIdx}");
            $rowIdx++;
        }

        $this->autoFitColumns($sheet, 'H');
        $writer = new Xlsx($spreadsheet);
        $writer->save($targetDir . '/07_Mata_Pelajaran.xlsx');
        $this->line(" [✓] 07_Mata_Pelajaran.xlsx (" . count($subjects) . " baris)");
    }

    private function exportCombinedWorkbook(string $targetDir): void
    {
        $spreadsheet = new Spreadsheet();

        // 1. Sheet Unit Pendidikan
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Unit Pendidikan');
        $sheet1->fromArray([['No', 'ID Unit', 'Kode Unit', 'Nama Unit Pendidikan', 'Jenjang', 'Status']], null, 'A1');
        $this->applyHeaderStyle($sheet1, 'F');
        $units = EducationUnit::orderBy('name')->get();
        foreach ($units as $i => $u) {
            $sheet1->fromArray([[$i + 1, (string)$u->id, $u->code ?? '-', $u->name, $u->level ?? '-', $u->is_active ? 'Aktif' : 'Nonaktif']], null, 'A' . ($i + 2));
        }
        $this->autoFitColumns($sheet1, 'F');

        // 2. Sheet Tahun Ajaran
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Tahun Ajaran');
        $sheet2->fromArray([['No', 'ID', 'Tahun Ajaran', 'Mulai', 'Selesai', 'Status']], null, 'A1');
        $this->applyHeaderStyle($sheet2, 'F');
        $years = AcademicYear::orderBy('start_date', 'desc')->get();
        foreach ($years as $i => $y) {
            $sheet2->fromArray([[$i + 1, (string)$y->id, $y->name, $y->start_date?->format('Y-m-d') ?? '-', $y->end_date?->format('Y-m-d') ?? '-', $y->is_active ? 'Aktif' : 'Nonaktif']], null, 'A' . ($i + 2));
        }
        $this->autoFitColumns($sheet2, 'F');

        // 3. Sheet Jabatan
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Jabatan');
        $sheet3->fromArray([['No', 'ID', 'Kode', 'Nama Jabatan', 'Satuan Kerja', 'Status']], null, 'A1');
        $this->applyHeaderStyle($sheet3, 'F');
        $positions = Position::orderBy('name')->get();
        foreach ($positions as $i => $p) {
            $sheet3->fromArray([[$i + 1, (string)$p->id, $p->code ?? '-', $p->name, $p->satuan_kerja ?? '-', $p->is_active ? 'Aktif' : 'Nonaktif']], null, 'A' . ($i + 2));
        }
        $this->autoFitColumns($sheet3, 'F');

        // 4. Sheet Kelas
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Kelas & Rombel');
        $sheet4->fromArray([['No', 'ID', 'Nama Kelas', 'Tingkat', 'Unit', 'Wali Kelas', 'Kapasitas', 'Status']], null, 'A1');
        $this->applyHeaderStyle($sheet4, 'H');
        $classes = Kelas::with(['unitPendidikan', 'waliKelas'])->orderBy('nama_kelas')->get();
        foreach ($classes as $i => $c) {
            $sheet4->fromArray([[$i + 1, (string)$c->id, $c->nama_kelas, $c->tingkat ?? '-', $c->unitPendidikan?->name ?? '-', $c->waliKelas?->nama_lengkap ?? '-', (int)$c->kapasitas, $c->status ?? 'Aktif']], null, 'A' . ($i + 2));
        }
        $this->autoFitColumns($sheet4, 'H');

        // 5. Sheet Pegawai
        $sheet5 = $spreadsheet->createSheet();
        $sheet5->setTitle('Pegawai & Guru');
        $sheet5->fromArray([['No', 'NIY', 'NIK', 'Nama Lengkap', 'JK', 'Unit', 'Jabatan', 'Status']], null, 'A1');
        $this->applyHeaderStyle($sheet5, 'H');
        $employees = Employee::with(['unit', 'position'])->orderBy('nama_lengkap')->get();
        foreach ($employees as $i => $e) {
            $sheet5->fromArray([[$i + 1, $e->niy ? "'{$e->niy}" : '-', $e->nik ? "'{$e->nik}" : '-', $e->nama_lengkap, $e->jenis_kelamin ?? '-', $e->unit?->name ?? '-', $e->position?->name ?? '-', $e->status ?? 'Aktif']], null, 'A' . ($i + 2));
        }
        $this->autoFitColumns($sheet5, 'H');

        // 6. Sheet Siswa
        $sheet6 = $spreadsheet->createSheet();
        $sheet6->setTitle('Buku Induk Siswa');
        $sheet6->fromArray([['No', 'NIS', 'NISN', 'Nama Lengkap', 'JK', 'Unit', 'Kelas', 'Status']], null, 'A1');
        $this->applyHeaderStyle($sheet6, 'H');
        $students = Student::with(['unit', 'kelas'])->orderBy('full_name')->get();
        foreach ($students as $i => $s) {
            $sheet6->fromArray([[$i + 1, $s->nis ? "'{$s->nis}" : '-', $s->nisn ? "'{$s->nisn}" : '-', $s->full_name, $s->gender ?? '-', $s->unit?->name ?? '-', $s->kelas?->nama_kelas ?? '-', $s->is_active ? 'Aktif' : 'Nonaktif']], null, 'A' . ($i + 2));
        }
        $this->autoFitColumns($sheet6, 'H');

        // 7. Sheet Mapel
        $sheet7 = $spreadsheet->createSheet();
        $sheet7->setTitle('Mata Pelajaran');
        $sheet7->fromArray([['No', 'Kode', 'Nama Mata Pelajaran', 'Kategori', 'KKM', 'Status']], null, 'A1');
        $this->applyHeaderStyle($sheet7, 'F');
        $subjects = Subject::orderBy('name')->get();
        foreach ($subjects as $i => $sb) {
            $sheet7->fromArray([[$i + 1, $sb->code ?? '-', $sb->name ?? $sb->nama_mapel, $sb->kategori ?? 'Umum', $sb->kkm ?? 75, $sb->status ? 'Aktif' : 'Nonaktif']], null, 'A' . ($i + 2));
        }
        $this->autoFitColumns($sheet7, 'F');

        $spreadsheet->setActiveSheetIndex(0);
        $writer = new Xlsx($spreadsheet);
        $writer->save($targetDir . '/MASTER_DATA_TERPADU_SIMSIT_2026.xlsx');
        $this->line(" [✓] MASTER_DATA_TERPADU_SIMSIT_2026.xlsx (Workbook Multi-Sheet)");
    }
}
