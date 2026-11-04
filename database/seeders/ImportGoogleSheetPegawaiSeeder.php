<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Teacher;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportGoogleSheetPegawaiSeeder extends Seeder
{
    public const SHEET_URL = 'https://docs.google.com/spreadsheets/d/15P--ejXn_FahqQDze6-fOk5MGVXrOclILazHPGfWN7E/export?format=csv&gid=1155007152';
    public const LOCAL_CSV_PATH = 'storage/app/imports/google_sheet_pegawai.csv';

    public function run(): void
    {
        $this->command?->info('=== MEMULAI IMPORT DATA PEGAWAI & GURU DARI GOOGLE SPREADSHEET ===');

        // 1. Dapatkan berkas CSV
        $csvPath = base_path(self::LOCAL_CSV_PATH);
        if (! File::exists($csvPath) || File::size($csvPath) < 500) {
            $this->command?->info('Mengunduh berkas CSV Pegawai & Guru dari Google Spreadsheet...');
            File::ensureDirectoryExists(dirname($csvPath));
            $response = Http::timeout(60)->get(self::SHEET_URL);
            if (! $response->successful()) {
                throw new \RuntimeException('Gagal mengunduh Google Spreadsheet Pegawai: HTTP ' . $response->status());
            }
            File::put($csvPath, $response->body());
        }

        $this->command?->info('Membaca berkas CSV: ' . $csvPath . ' (' . number_format(File::size($csvPath) / 1024, 1) . ' KB)');

        // 2. Cache Master Unit Pendidikan
        $units = EducationUnit::all();
        $unitsByName = [];
        foreach ($units as $u) {
            $unitsByName[trim($u->name)] = $u;
            $unitsByName[trim($u->code)] = $u;
        }

        $unitAliases = [
            'SDIT 1 Dar el-Iman - Padang' => $unitsByName['SDIT 1 Dar el-Iman - Padang'] ?? $unitsByName['SDIT-01'] ?? null,
            'SDIT Dar el-Iman - 50 Kota' => $unitsByName['SDIT Dar el-Iman - 50 Kota'] ?? $unitsByName['SDIT-50K'] ?? null,
        ];

        // 3. Cache Positions
        $positions = Position::all();
        $positionsByName = [];
        foreach ($positions as $p) {
            $positionsByName[strtolower(trim($p->name))] = $p;
        }

        // 4. Baca Baris CSV
        $handle = fopen($csvPath, 'r');
        if (! $handle) {
            throw new \RuntimeException('Gagal membuka file CSV: ' . $csvPath);
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            throw new \RuntimeException('File CSV kosong atau header tidak ditemukan.');
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (empty(array_filter($data, fn($v) => trim((string)$v) !== ''))) {
                continue;
            }
            $rows[] = $data;
        }
        fclose($handle);

        $totalRows = count($rows);
        $this->command?->info("Total data baris valid: {$totalRows} pegawai/guru.");

        $createdEmployees = 0;
        $updatedEmployees = 0;
        $createdTeachers = 0;
        $skippedRows = 0;

        $seenNiys = [];

        DB::transaction(function () use (
            $rows,
            $unitsByName,
            $unitAliases,
            &$positionsByName,
            &$seenNiys,
            &$createdEmployees,
            &$updatedEmployees,
            &$createdTeachers,
            &$skippedRows
        ) {
            foreach ($rows as $index => $row) {
                $rowNo = trim($row[0] ?? (string)($index + 1));
                $unitName = trim($row[1] ?? '');
                $rawNiy = trim($row[2] ?? '');
                $namaLengkap = trim($row[3] ?? '');

                if (empty($namaLengkap)) {
                    $skippedRows++;
                    continue;
                }

                // Resolusi Unit
                $unit = $unitAliases[$unitName] ?? $unitsByName[$unitName] ?? null;
                if (! $unit) {
                    foreach ($unitsByName as $k => $uObj) {
                        if (stripos($k, $unitName) !== false || stripos($unitName, $k) !== false) {
                            $unit = $uObj;
                            break;
                        }
                    }
                }
                $unitId = $unit?->id;

                // Sanitasi NIY & Penanganan Duplikasi / Kosong
                $duplicateNiy = false;
                if (empty($rawNiy) || $rawNiy === '-' || $rawNiy === '#N/A') {
                    $cleanNiy = 'NIY-' . ($unit->code ?? 'UNIT') . '-' . str_pad($rowNo, 3, '0', STR_PAD_LEFT);
                } elseif (isset($seenNiys[$rawNiy])) {
                    $cleanNiy = $rawNiy . '-' . ($unit->code ?? 'DUP');
                    $duplicateNiy = true;
                } else {
                    $cleanNiy = $rawNiy;
                    $seenNiys[$rawNiy] = true;
                }

                // Normalisasi Gender: 'L' atau 'P'
                $rawGender = strtolower(trim($row[4] ?? ''));
                $gender = (in_array($rawGender, ['p', 'perempuan', 'female'])) ? 'P' : 'L';

                // Resolusi Jabatan
                $rawJabatan = trim($row[6] ?? 'Pegawai');
                $cleanJabatan = preg_replace('/\s+/', ' ', $rawJabatan);
                $jabatanLower = strtolower($cleanJabatan);

                $position = $positionsByName[$jabatanLower] ?? null;
                if (! $position) {
                    // Cari partial match
                    foreach ($positionsByName as $pName => $pObj) {
                        if (str_contains($jabatanLower, $pName) || str_contains($pName, $jabatanLower)) {
                            $position = $pObj;
                            break;
                        }
                    }
                }

                // Jika jabatan belum ada sama sekali di master_jabatan/positions, buat baru
                if (! $position) {
                    $baseCode = 'JAB-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', Str::slug($cleanJabatan)), 0, 15));
                    $posCode = $baseCode;
                    $counter = 1;
                    while (Position::where('code', $posCode)->exists()) {
                        $posCode = substr($baseCode, 0, 15) . '-' . $counter++;
                    }

                    $position = Position::firstOrCreate(
                        ['name' => $cleanJabatan],
                        [
                            'code' => $posCode,
                            'satuan_kerja' => trim($row[5] ?? ''),
                            'is_active' => true,
                            'level_jabatan' => 5,
                        ]
                    );
                    $positionsByName[$jabatanLower] = $position;
                }

                $jabatanId = $position->id;

                // Nomor HP & Email
                $rawPhone = trim($row[10] ?? '');
                $cleanPhone = PhoneNormalizer::normalize($rawPhone) ?: null;

                $rawEmail = strtolower(trim($row[11] ?? ''));
                $cleanEmail = filter_var($rawEmail, FILTER_VALIDATE_EMAIL) ? $rawEmail : null;

                $cleanNik = trim($row[12] ?? '') ?: null;

                $metadata = [
                    'source' => 'ImportGoogleSheetPegawaiSeeder',
                    'sheet_row_no' => $rowNo,
                    'satuan_kerja_asal' => trim($row[5] ?? ''),
                    'jabatan_raw' => $rawJabatan,
                    'kelas_perwalian' => trim($row[7] ?? ''),
                    'kelas_yang_diajar' => trim($row[8] ?? ''),
                    'mapel_yang_diajar' => trim($row[9] ?? ''),
                    'raw_niy_original' => $rawNiy,
                    'is_duplicate_niy_resolved' => $duplicateNiy,
                ];

                // Simpan Employee
                $employee = Employee::withTrashed()->where('niy', $cleanNiy)->first();
                if (! $employee && $cleanNik) {
                    $employee = Employee::withTrashed()->where('nik', $cleanNik)->first();
                }

                $employeePayload = [
                    'niy' => $cleanNiy,
                    'nik' => $cleanNik,
                    'nama_lengkap' => $namaLengkap,
                    'jenis_kelamin' => $gender,
                    'unit_id' => $unitId,
                    'jabatan_id' => $jabatanId,
                    'status_pegawai' => 'Tetap',
                    'status' => 'Aktif',
                    'no_hp' => $cleanPhone,
                    'email' => $cleanEmail,
                    'metadata' => $metadata,
                    'deleted_at' => null,
                ];

                if (! $employee) {
                    $employee = Employee::create($employeePayload);
                    $createdEmployees++;
                } else {
                    if ($employee->trashed()) {
                        $employee->restore();
                    }
                    $employee->update($employeePayload);
                    $updatedEmployees++;
                }

                // Jika peran memiliki fungsi mengajar, catat ke tabel teachers
                $isTeacherRole = false;
                $teachingKeywords = [
                    'guru', 'wali kelas', 'walas', 'tahsin', 'tahfizh', 'mapel', 'halaqoh',
                    'dirosah', 'musyrif', 'musyrifah', 'pengasuhan', 'santri', 'pengajar'
                ];
                foreach ($teachingKeywords as $kw) {
                    if (str_contains($jabatanLower, $kw)) {
                        $isTeacherRole = true;
                        break;
                    }
                }

                if ($isTeacherRole) {
                    Teacher::withTrashed()->updateOrCreate(
                        ['employee_id' => $employee->id],
                        [
                            'employee_number' => $employee->niy,
                            'full_name' => $employee->nama_lengkap,
                            'email' => $employee->email,
                            'phone' => $employee->no_hp,
                            'metadata' => [
                                'kelas_perwalian' => trim($row[7] ?? ''),
                                'kelas_diajar' => trim($row[8] ?? ''),
                                'mapel' => trim($row[9] ?? ''),
                                'imported_from_sheet' => true,
                            ],
                            'deleted_at' => null,
                        ]
                    );
                    $createdTeachers++;
                }
            }
        });

        $this->command?->info('=== IMPORT DATA PEGAWAI & GURU SELESAI ===');
        $this->command?->info("Total Pegawai Dibuat : {$createdEmployees}");
        $this->command?->info("Total Pegawai Diupdate: {$updatedEmployees}");
        $this->command?->info("Total Guru Terdaftar : {$createdTeachers}");
        $this->command?->info("Total Baris Diskip   : {$skippedRows}");
        $this->command?->info('Total Pegawai di DB   : ' . Employee::count());
    }
}
