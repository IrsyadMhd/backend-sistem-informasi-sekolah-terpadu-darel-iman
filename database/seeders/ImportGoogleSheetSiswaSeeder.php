<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentParent;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportGoogleSheetSiswaSeeder extends Seeder
{
    public const SHEET_URL = 'https://docs.google.com/spreadsheets/d/15P--ejXn_FahqQDze6-fOk5MGVXrOclILazHPGfWN7E/export?format=csv&gid=2024181082';
    public const LOCAL_CSV_PATH = 'storage/app/imports/google_sheet_siswa.csv';

    public function run(): void
    {
        $this->command?->info('=== MEMULAI IMPORT DATA SISWA DARI GOOGLE SPREADSHEET ===');

        // 1. Dapatkan berkas CSV
        $csvPath = base_path(self::LOCAL_CSV_PATH);
        if (! File::exists($csvPath) || File::size($csvPath) < 1000) {
            $this->command?->info('Mengunduh berkas CSV dari Google Spreadsheet...');
            File::ensureDirectoryExists(dirname($csvPath));
            $response = Http::timeout(120)->get(self::SHEET_URL);
            if (! $response->successful()) {
                throw new \RuntimeException('Gagal mengunduh Google Spreadsheet: HTTP ' . $response->status());
            }
            File::put($csvPath, $response->body());
        }

        $this->command?->info('Membaca berkas CSV: ' . $csvPath . ' (' . number_format(File::size($csvPath) / 1024, 1) . ' KB)');

        // 2. Cache Master Data Pendukung
        $units = EducationUnit::all();
        $unitsByName = [];
        foreach ($units as $u) {
            $unitsByName[trim($u->name)] = $u;
            $unitsByName[trim($u->code)] = $u;
        }

        // Alias penyesuaian nama unit
        $unitAliases = [
            'SDIT 1 Dar el-Iman - Padang' => $unitsByName['SDIT 1 Dar el-Iman - Padang'] ?? $unitsByName['SDIT-01'] ?? null,
            'SDIT Dar el-Iman - 50 Kota' => $unitsByName['SDIT Dar el-Iman - 50 Kota'] ?? $unitsByName['SDIT-50K'] ?? null,
        ];

        $activeYear = AcademicYear::where('is_active', true)->orderByDesc('start_date')->first()
            ?? AcademicYear::first();
        $activeSemester = Semester::where('academic_year_id', $activeYear?->id)->where('is_active', true)->first()
            ?? Semester::where('academic_year_id', $activeYear?->id)->first()
            ?? Semester::first();

        // 3. Baca dan parsing CSV
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
        $this->command?->info("Total data baris valid: {$totalRows} siswa.");

        $classesCache = [];
        $seenNisns = [];
        $createdStudents = 0;
        $updatedStudents = 0;
        $skippedRows = 0;

        // 4. Proses baris dalam chunk transaksi
        $chunkSize = 100;
        $chunks = array_chunk($rows, $chunkSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            DB::transaction(function () use (
                $chunk,
                $unitsByName,
                $unitAliases,
                $activeYear,
                $activeSemester,
                &$classesCache,
                &$seenNisns,
                &$createdStudents,
                &$updatedStudents,
                &$skippedRows
            ) {
                foreach ($chunk as $row) {
                    $unitName = trim($row[1] ?? '');
                    $namaLengkap = trim($row[6] ?? '');

                    if (empty($namaLengkap)) {
                        $skippedRows++;
                        continue;
                    }

                    // Resolusi Unit Pendidikan
                    $unit = $unitAliases[$unitName] ?? $unitsByName[$unitName] ?? null;
                    if (! $unit) {
                        // Fallback partial matching
                        foreach ($unitsByName as $nameKey => $unitObj) {
                            if (stripos($nameKey, $unitName) !== false || stripos($unitName, $nameKey) !== false) {
                                $unit = $unitObj;
                                break;
                            }
                        }
                    }

                    $unitId = $unit?->id;

                    // Parse Tanggal Lahir (Mendukung Excel Serial & Format Standar)
                    $birthDate = $this->parseDate($row[7] ?? '');
                    $gender = $this->normalizeGender($row[9] ?? '');

                    // Resolusi Kelas / Rombel
                    $rawKelas = trim($row[51] ?? '');
                    if ($rawKelas === '#N/A' || $rawKelas === '') {
                        $rawKelas = 'Kelas Belum Ditentukan';
                    }

                    $kelasId = null;
                    if ($unitId) {
                        $kelasKey = $unitId . '_' . $rawKelas;
                        if (isset($classesCache[$kelasKey])) {
                            $kelasId = $classesCache[$kelasKey];
                        } else {
                            $tingkat = preg_replace('/[^0-9]/', '', $rawKelas) ?: '1';
                            $kodeKelas = 'K-' . ($unit->code ?? 'UNIT') . '-' . Str::slug($rawKelas);
                            
                            $kelas = Kelas::withTrashed()->firstOrCreate(
                                ['unit_pendidikan_id' => $unitId, 'nama_kelas' => $rawKelas],
                                [
                                    'kode_kelas' => $kodeKelas,
                                    'tahun_ajaran_id' => $activeYear?->id,
                                    'semester_id' => $activeSemester?->id,
                                    'jenjang' => $unit->level ?? 'SDIT',
                                    'tingkat' => $tingkat,
                                    'kapasitas' => 30,
                                    'status' => 'Aktif',
                                ]
                            );
                            if ($kelas->trashed()) {
                                $kelas->restore();
                            }
                            $classesCache[$kelasKey] = $kelas->id;
                            $kelasId = $kelas->id;
                        }
                    }

                    // Resolusi Profil Orang Tua (Ayah / Ibu)
                    $namaAyah = trim($row[62] ?? '');
                    $nikAyah = trim($row[61] ?? '');
                    $hpAyah = trim($row[66] ?? $row[75] ?? '');
                    $namaIbu = trim($row[78] ?? '');
                    $nikIbu = trim($row[79] ?? '');
                    $hpIbu = trim($row[83] ?? $row[92] ?? '');

                    $parent = null;
                    if (! empty($namaAyah) || ! empty($namaIbu)) {
                        $primaryName = ! empty($namaAyah) ? $namaAyah : $namaIbu;
                        $primaryNik = ! empty($nikAyah) ? $nikAyah : (! empty($nikIbu) ? $nikIbu : null);
                        $primaryPhone = ! empty($hpAyah) ? $hpAyah : $hpIbu;
                        $normalizedPhone = PhoneNormalizer::normalize($primaryPhone);

                        $parentMetadata = [
                            'ayah' => [
                                'nama' => $namaAyah,
                                'nik' => $nikAyah,
                                'tempat_lahir' => trim($row[63] ?? ''),
                                'tgl_lahir' => $this->parseDate($row[64] ?? ''),
                                'telfon' => trim($row[65] ?? ''),
                                'hp' => $hpAyah,
                                'pendidikan' => trim($row[67] ?? ''),
                                'pekerjaan' => trim($row[68] ?? ''),
                                'instansi' => trim($row[69] ?? ''),
                                'jabatan' => trim($row[70] ?? ''),
                                'alamat_instansi' => trim($row[71] ?? ''),
                                'keahlian' => trim($row[72] ?? ''),
                                'penghasilan' => trim($row[73] ?? ''),
                                'alamat' => trim($row[74] ?? ''),
                                'no_wa' => trim($row[75] ?? ''),
                                'medsos' => trim($row[76] ?? ''),
                                'catatan' => trim($row[77] ?? ''),
                            ],
                            'ibu' => [
                                'nama' => $namaIbu,
                                'nik' => $nikIbu,
                                'tempat_lahir' => trim($row[80] ?? ''),
                                'tgl_lahir' => $this->parseDate($row[81] ?? ''),
                                'telfon' => trim($row[82] ?? ''),
                                'hp' => $hpIbu,
                                'pendidikan' => trim($row[84] ?? ''),
                                'pekerjaan' => trim($row[85] ?? ''),
                                'instansi' => trim($row[86] ?? ''),
                                'jabatan' => trim($row[87] ?? ''),
                                'alamat_instansi' => trim($row[88] ?? ''),
                                'keahlian' => trim($row[89] ?? ''),
                                'penghasilan' => trim($row[90] ?? ''),
                                'alamat' => trim($row[91] ?? ''),
                                'no_wa' => trim($row[92] ?? ''),
                                'medsos' => trim($row[93] ?? ''),
                                'catatan' => trim($row[94] ?? ''),
                            ],
                            'keluarga' => [
                                'status_pernikahan' => trim($row[95] ?? ''),
                                'tanggungan_anak' => trim($row[96] ?? ''),
                            ],
                            'wali' => [
                                'nik' => trim($row[97] ?? ''),
                                'nama' => trim($row[98] ?? ''),
                                'tempat_lahir' => trim($row[99] ?? ''),
                                'tgl_lahir' => $this->parseDate($row[100] ?? ''),
                                'telfon' => trim($row[101] ?? ''),
                                'hp' => trim($row[102] ?? ''),
                                'pendidikan' => trim($row[103] ?? ''),
                                'pekerjaan' => trim($row[104] ?? ''),
                                'instansi' => trim($row[105] ?? ''),
                                'jabatan' => trim($row[106] ?? ''),
                                'alamat_instansi' => trim($row[107] ?? ''),
                                'keahlian' => trim($row[108] ?? ''),
                                'penghasilan' => trim($row[109] ?? ''),
                                'alamat' => trim($row[110] ?? ''),
                                'no_wa' => trim($row[111] ?? ''),
                                'medsos' => trim($row[112] ?? ''),
                            ],
                        ];

                        $parentQuery = ParentModel::withTrashed();
                        if (! empty($primaryNik)) {
                            $parentQuery->where(function ($q) use ($primaryNik) {
                                $q->where('nik', $primaryNik)
                                  ->orWhere('father_nik', $primaryNik)
                                  ->orWhere('mother_nik', $primaryNik);
                            });
                        } elseif (! empty($normalizedPhone)) {
                            $parentQuery->where('phone', $normalizedPhone);
                        } else {
                            $parentQuery->where('full_name', $primaryName);
                        }

                        $parent = $parentQuery->first();
                        if (! $parent) {
                            $parent = ParentModel::create([
                                'full_name' => $primaryName,
                                'nik' => $primaryNik,
                                'father_nik' => ! empty($nikAyah) ? $nikAyah : null,
                                'mother_nik' => ! empty($nikIbu) ? $nikIbu : null,
                                'phone' => $normalizedPhone ?: null,
                                'address' => trim($row[74] ?? $row[19] ?? ''),
                                'occupation' => trim($row[68] ?? ''),
                                'metadata' => $parentMetadata,
                            ]);
                        } else {
                            if ($parent->trashed()) {
                                $parent->restore();
                            }
                            $parent->update([
                                'father_nik' => $parent->father_nik ?: (! empty($nikAyah) ? $nikAyah : null),
                                'mother_nik' => $parent->mother_nik ?: (! empty($nikIbu) ? $nikIbu : null),
                                'phone' => $parent->phone ?: ($normalizedPhone ?: null),
                                'metadata' => array_merge($parent->metadata ?? [], $parentMetadata),
                            ]);
                        }
                    }

                    // Metadata Lengkap Siswa (DAPODIK, PPDB, Pembiayaan, Bantuan)
                    $studentMetadata = [
                        'source' => 'ImportGoogleSheetSiswaSeeder',
                        'sheet_row_no' => trim($row[0] ?? ''),
                        'no_pendaftaran' => trim($row[2] ?? ''),
                        'nik' => trim($row[3] ?? ''),
                        'no_registrasi_akta' => trim($row[4] ?? ''),
                        'no_kk' => trim($row[5] ?? ''),
                        'agama' => trim($row[10] ?? 'Islam'),
                        'email' => trim($row[11] ?? ''),
                        'anak_ke' => trim($row[12] ?? ''),
                        'jumlah_saudara' => trim($row[13] ?? ''),
                        'jumlah_saudara_tiri' => trim($row[14] ?? ''),
                        'berat_badan' => trim($row[15] ?? ''),
                        'tinggi_badan' => trim($row[16] ?? ''),
                        'riwayat_penyakit' => trim($row[17] ?? ''),
                        'kewarganegaraan' => trim($row[18] ?? 'WNI'),
                        'domisili' => [
                            'rt' => trim($row[20] ?? ''),
                            'rw' => trim($row[21] ?? ''),
                            'dusun' => trim($row[22] ?? ''),
                            'kelurahan' => trim($row[23] ?? ''),
                            'kecamatan' => trim($row[24] ?? ''),
                            'kode_pos' => trim($row[25] ?? ''),
                            'kota_kabupaten' => trim($row[26] ?? ''),
                            'provinsi' => trim($row[27] ?? ''),
                            'jenis_tempat_tinggal' => trim($row[28] ?? ''),
                            'jarak_tempuh' => trim($row[29] ?? ''),
                            'transportasi' => trim($row[30] ?? ''),
                        ],
                        'sekolah_asal' => [
                            'nama' => trim($row[31] ?? ''),
                            'status' => trim($row[32] ?? ''),
                            'kecamatan' => trim($row[33] ?? ''),
                            'kota_kab' => trim($row[34] ?? ''),
                            'no_hp' => trim($row[35] ?? ''),
                        ],
                        'minat' => [
                            'hobi' => trim($row[36] ?? ''),
                            'cita_cita' => trim($row[37] ?? ''),
                        ],
                        'riwayat_penyakit_tambahan' => trim($row[38] ?? ''),
                        'pembiayaan' => [
                            'nominal_spp' => trim($row[39] ?? ''),
                            'nominal_ortu_asuh' => trim($row[40] ?? ''),
                            'nip_pembayaran' => trim($row[49] ?? ''),
                        ],
                        'bantuan_pemerintah' => [
                            'penerima_kps_pkh' => trim($row[41] ?? ''),
                            'punya_kip' => trim($row[42] ?? ''),
                            'layak_pip' => trim($row[43] ?? ''),
                            'alasan_menolak_pip' => trim($row[44] ?? ''),
                        ],
                        'unit_asal' => trim($row[46] ?? ''),
                        'tahun_ajaran_masuk_str' => trim($row[50] ?? ''),
                        'keterangan_kelas' => trim($row[52] ?? ''),
                        'tahun_ajaran_berjalan' => trim($row[53] ?? ''),
                        'status_siswa_raw' => trim($row[54] ?? ''),
                        'kepegawaian_ortu' => [
                            'status_ortu' => trim($row[55] ?? ''),
                            'niy_pegawai' => trim($row[56] ?? ''),
                        ],
                        'wali_kelas_asal' => [
                            'nama' => trim($row[57] ?? ''),
                            'niy' => trim($row[58] ?? ''),
                            'email' => trim($row[59] ?? ''),
                        ],
                        'catatan' => [
                            'catatan_1' => trim($row[45] ?? ''),
                            'catatan_2' => trim($row[60] ?? ''),
                        ],
                    ];

                    $cleanNis = trim($row[47] ?? '');
                    if ($cleanNis === '' || $cleanNis === '#N/A' || $cleanNis === '-') {
                        $cleanNis = null;
                    }

                    $cleanNisn = trim($row[48] ?? '');
                    if ($cleanNisn === '' || $cleanNisn === '#N/A' || $cleanNisn === '-' || ! preg_match('/^[0-9]+$/', $cleanNisn)) {
                        $cleanNisn = null;
                    } elseif (isset($seenNisns[$cleanNisn])) {
                        $studentMetadata['raw_duplicate_nisn'] = $cleanNisn;
                        $cleanNisn = null;
                    } else {
                        $seenNisns[$cleanNisn] = true;
                    }

                    $tahunMasukRaw = trim($row[50] ?? '');
                    $tahunMasukInt = (int) (preg_replace('/[^0-9]/', '', substr($tahunMasukRaw, 0, 4)) ?: date('Y'));

                    $statusRaw = strtolower(trim($row[54] ?? 'aktif'));
                    $isActive = ($statusRaw === 'aktif' || ! str_contains($statusRaw, 'tidak aktif'));

                    // Cari siswa eksisting (idempotent)
                    $studentQuery = Student::withTrashed();
                    if (! empty($cleanNis) && $unitId) {
                        $studentQuery->where('unit_id', $unitId)->where('nis', $cleanNis);
                    } else {
                        $studentQuery->where('unit_id', $unitId)
                                     ->where('full_name', $namaLengkap);
                        if (! empty($birthDate)) {
                            $studentQuery->where('birth_date', $birthDate);
                        }
                    }

                    $student = $studentQuery->first();
                    $studentPayload = [
                        'unit_id' => $unitId,
                        'kelas_id' => $kelasId,
                        'parent_id' => $parent?->id,
                        'nis' => $cleanNis,
                        'nisn' => $cleanNisn,
                        'full_name' => $namaLengkap,
                        'gender' => $gender,
                        'birth_date' => $birthDate,
                        'birth_place' => trim($row[8] ?? ''),
                        'address' => trim($row[19] ?? ''),
                        'is_active' => $isActive,
                        'tahun_masuk' => $tahunMasukInt,
                        'metadata' => $studentMetadata,
                        'deleted_at' => null,
                    ];

                    if (! $student) {
                        $student = Student::create($studentPayload);
                        $createdStudents++;
                    } else {
                        if ($student->trashed()) {
                            $student->restore();
                        }
                        $student->update($studentPayload);
                        $updatedStudents++;
                    }

                    // Tautkan relasi StudentParent
                    if ($parent) {
                        StudentParent::updateOrCreate(
                            ['student_id' => $student->id, 'parent_id' => $parent->id],
                            [
                                'relationship_type' => ! empty($namaAyah) ? 'father' : 'mother',
                                'is_primary' => true,
                                'metadata' => ['imported_from_google_sheet' => true],
                            ]
                        );
                    }
                }
            });

            $processedCount = min(($chunkIndex + 1) * $chunkSize, $totalRows);
            $this->command?->info("Progress: {$processedCount} / {$totalRows} baris diproses...");
        }

        $this->command?->info('=== IMPORT GOOGLE SPREADSHEET SELESAI ===');
        $this->command?->info("Total Siswa Dibuat : {$createdStudents}");
        $this->command?->info("Total Siswa Diupdate: {$updatedStudents}");
        $this->command?->info("Total Baris Diskip  : {$skippedRows}");
        $this->command?->info('Total Siswa di DB   : ' . Student::count());
    }

    /**
     * Konversi format tanggal, mendukung Excel Serial Number (e.g. 44704) dan string tanggal.
     */
    protected function parseDate(?string $raw): ?string
    {
        $raw = trim((string)$raw);
        if ($raw === '' || $raw === '#N/A' || $raw === '-') {
            return null;
        }

        // Cek jika merupakan angka serial Excel (misal: 44704)
        if (is_numeric($raw) && (int)$raw > 10000 && (int)$raw < 80000) {
            $days = (int) $raw;
            // Base date Excel: 1899-12-30
            return Carbon::create(1899, 12, 30)->addDays($days)->format('Y-m-d');
        }

        // Cek format YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        // Cek format DD/MM/YYYY atau DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw, $matches)) {
            return sprintf('%04d-%02d-%02d', (int)$matches[3], (int)$matches[2], (int)$matches[1]);
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Normalisasi nilai gender ke canonical 'male' / 'female'.
     */
    protected function normalizeGender(?string $raw): string
    {
        $norm = strtolower(trim((string)$raw));
        if (in_array($norm, ['p', 'perempuan', 'female'])) {
            return 'female';
        }
        return 'male';
    }
}
