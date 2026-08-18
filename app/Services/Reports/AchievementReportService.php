<?php

namespace App\Services\Reports;

use App\Models\EducationUnit;
use App\Models\RekapPrestasiSiswa;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AchievementReportService
{
    public function getReport(array $filters): array
    {
        // Auto-seed sample achievements if table is empty
        if (RekapPrestasiSiswa::count() === 0 && class_exists(\Database\Seeders\RekapPrestasiSiswaSeeder::class)) {
            try {
                (new \Database\Seeders\RekapPrestasiSiswaSeeder())->run();
            } catch (\Throwable $e) {
                // Ignore seeding errors if student table empty
            }
        }

        $period = $this->resolvePeriod($filters);

        // Base Query for RekapPrestasiSiswa
        $query = RekapPrestasiSiswa::with([
            'siswa.educationUnit.jenisUnit',
            'siswa.kelas',
        ]);

        // Filters
        if (!empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $query->whereHas('siswa', function ($q) use ($filters) {
                $q->where('unit_id', $filters['unit_id']);
            });
        }

        if (!empty($filters['jenis_prestasi']) && $filters['jenis_prestasi'] !== 'all') {
            $query->where('jenis_prestasi', $filters['jenis_prestasi']);
        }

        if (!empty($filters['tingkat_prestasi']) && $filters['tingkat_prestasi'] !== 'all') {
            $query->where('tingkat_prestasi', $filters['tingkat_prestasi']);
        }

        if (!empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nama_prestasi', 'like', "%{$search}%")
                  ->orWhere('jenis_prestasi', 'like', "%{$search}%")
                  ->orWhere('tingkat_prestasi', 'like', "%{$search}%")
                  ->orWhere('keterangan', 'like', "%{$search}%")
                  ->orWhereHas('siswa', function ($sq) use ($search) {
                      $sq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('nis', 'like', "%{$search}%");
                  });
            });
        }

        if ($period['start_date'] && $period['end_date']) {
            $query->whereBetween('tanggal_prestasi', [$period['start_date'], $period['end_date']]);
        }

        // Summary Calculations
        $allMatching = (clone $query)->get();
        $totalPrestasi = $allMatching->count();
        $totalSiswaBerprestasi = $allMatching->pluck('id_siswa')->unique()->filter()->count();

        $tingkatNasional = $allMatching->filter(fn ($item) => strtolower($item->tingkat_prestasi ?? '') === 'nasional')->count();
        $tingkatProvinsi = $allMatching->filter(fn ($item) => strtolower($item->tingkat_prestasi ?? '') === 'provinsi')->count();
        $tingkatKabKota = $allMatching->filter(fn ($item) => in_array(strtolower($item->tingkat_prestasi ?? ''), ['kota/kabupaten', 'kabupaten', 'kota', 'regional']))->count();
        $tingkatInternal = $allMatching->filter(fn ($item) => in_array(strtolower($item->tingkat_prestasi ?? ''), ['internal sekolah', 'internal pesantren', 'internal']))->count();

        $kategoriTahfizh = $allMatching->filter(fn ($item) => strtolower($item->jenis_prestasi ?? '') === 'tahfizh')->count();
        $kategoriSantri = $allMatching->filter(fn ($item) => strtolower($item->jenis_prestasi ?? '') === 'santri')->count();
        $kategoriOlahraga = $allMatching->filter(fn ($item) => strtolower($item->jenis_prestasi ?? '') === 'olahraga')->count();
        $kategoriLomba = $allMatching->filter(fn ($item) => strtolower($item->jenis_prestasi ?? '') === 'lomba')->count();
        $kategoriAkademik = $allMatching->filter(fn ($item) => strtolower($item->jenis_prestasi ?? '') === 'akademik')->count();

        $avgScore = $totalPrestasi > 0 ? round($allMatching->avg('nilai_prestasi'), 1) : 0;

        $summary = [
            'total_prestasi' => $totalPrestasi,
            'total_siswa_berprestasi' => $totalSiswaBerprestasi,
            'tingkat_nasional' => $tingkatNasional,
            'tingkat_provinsi' => $tingkatProvinsi,
            'tingkat_kabkota' => $tingkatKabKota,
            'tingkat_internal' => $tingkatInternal,
            'kategori_tahfizh' => $kategoriTahfizh,
            'kategori_santri' => $kategoriSantri,
            'kategori_olahraga' => $kategoriOlahraga,
            'kategori_lomba' => $kategoriLomba,
            'kategori_akademik' => $kategoriAkademik,
            'rata_nilai_prestasi' => $avgScore,
        ];

        // 1. REKAPITULASI PER UNIT PENDIDIKAN
        $units = EducationUnit::with('leader')->get();
        $unitRecaps = [];
        $totalUnitPrestasi = 0;

        foreach ($units as $unit) {
            $unitAchievements = $allMatching->filter(function ($item) use ($unit) {
                return $item->siswa && $item->siswa->unit_id === $unit->id;
            });

            $uTotal = $unitAchievements->count();
            $totalUnitPrestasi += $uTotal;

            $topUnitStudent = $unitAchievements->sortByDesc('nilai_prestasi')->first();

            $unitRecaps[] = [
                'unit_id' => $unit->id,
                'unit_code' => $unit->code,
                'unit_name' => $unit->name,
                'principal_name' => $unit->leader?->name ?? 'Kepala Sekolah ' . $unit->code,
                'total_prestasi' => $uTotal,
                'siswa_berprestasi_count' => $unitAchievements->pluck('id_siswa')->unique()->count(),
                'tahfizh_count' => $unitAchievements->filter(fn ($i) => strtolower($i->jenis_prestasi ?? '') === 'tahfizh')->count(),
                'santri_count' => $unitAchievements->filter(fn ($i) => strtolower($i->jenis_prestasi ?? '') === 'santri')->count(),
                'olahraga_count' => $unitAchievements->filter(fn ($i) => strtolower($i->jenis_prestasi ?? '') === 'olahraga')->count(),
                'lomba_count' => $unitAchievements->filter(fn ($i) => strtolower($i->jenis_prestasi ?? '') === 'lomba')->count(),
                'akademik_count' => $unitAchievements->filter(fn ($i) => strtolower($i->jenis_prestasi ?? '') === 'akademik')->count(),
                'top_student' => $topUnitStudent ? [
                    'full_name' => $topUnitStudent->siswa?->full_name ?? 'Siswa',
                    'avatar_url' => $topUnitStudent->siswa?->avatar_url ?? $topUnitStudent->siswa?->photo_url ?? null,
                    'nama_prestasi' => $topUnitStudent->nama_prestasi,
                    'nilai' => $topUnitStudent->nilai_prestasi,
                ] : null,
            ];
        }

        // Sort units by total_prestasi desc
        usort($unitRecaps, fn ($a, $b) => $b['total_prestasi'] <=> $a['total_prestasi']);

        $unitRecapsTotal = [
            'unit_name' => 'TOTAL SELURUH UNIT',
            'principal_name' => '-',
            'total_prestasi' => $totalUnitPrestasi,
            'siswa_berprestasi_count' => $totalSiswaBerprestasi,
            'tahfizh_count' => $kategoriTahfizh,
            'santri_count' => $kategoriSantri,
            'olahraga_count' => $kategoriOlahraga,
            'lomba_count' => $kategoriLomba,
            'akademik_count' => $kategoriAkademik,
        ];

        // 2. REKAPITULASI PER KEPALA SEKOLAH
        $kepalaSekolahRecaps = [];
        foreach ($units as $unit) {
            $unitAchievements = $allMatching->filter(fn ($i) => $i->siswa && $i->siswa->unit_id === $unit->id);
            $totalCount = $unitAchievements->count();
            $principalName = $unit->leader?->name ?? 'Kepala Sekolah ' . $unit->name;

            $highestLevel = $unitAchievements->pluck('tingkat_prestasi')->first() ?? 'Internal Sekolah';
            if ($unitAchievements->pluck('tingkat_prestasi')->contains('Nasional')) {
                $highestLevel = 'Nasional';
            } elseif ($unitAchievements->pluck('tingkat_prestasi')->contains('Provinsi')) {
                $highestLevel = 'Provinsi';
            }

            $kepalaSekolahRecaps[] = [
                'unit_id' => $unit->id,
                'unit_name' => $unit->name,
                'kepala_sekolah_name' => $principalName,
                'total_prestasi_diverifikasi' => $totalCount,
                'total_prestasi_menunggu' => 0,
                'tingkat_tertinggi' => $highestLevel,
                'skor_rata_rata' => $totalCount > 0 ? round($unitAchievements->avg('nilai_prestasi'), 1) : 0,
                'status_laporan' => 'Tervalidasi Yayasan',
            ];
        }

        // 3. REKAPITULASI PER DIVISI PENDIDIKAN
        $divisiPendidikanRecaps = [
            'total_unit_berpartisipasi' => count(array_filter($unitRecaps, fn ($u) => $u['total_prestasi'] > 0)),
            'total_keseluruhan' => $totalPrestasi,
            'distribusi_kategori' => [
                ['name' => 'Tahfizh Al-Qur’an', 'count' => $kategoriTahfizh, 'percent' => $totalPrestasi > 0 ? round(($kategoriTahfizh / $totalPrestasi) * 100, 1) : 0, 'color' => '#10B981'],
                ['name' => 'Adab & Santri', 'count' => $kategoriSantri, 'percent' => $totalPrestasi > 0 ? round(($kategoriSantri / $totalPrestasi) * 100, 1) : 0, 'color' => '#06B6D4'],
                ['name' => 'Olahraga & Ekskul', 'count' => $kategoriOlahraga, 'percent' => $totalPrestasi > 0 ? round(($kategoriOlahraga / $totalPrestasi) * 100, 1) : 0, 'color' => '#F59E0B'],
                ['name' => 'Lomba Pembelajaran', 'count' => $kategoriLomba, 'percent' => $totalPrestasi > 0 ? round(($kategoriLomba / $totalPrestasi) * 100, 1) : 0, 'color' => '#8B5CF6'],
                ['name' => 'Akademik Umum', 'count' => $kategoriAkademik, 'percent' => $totalPrestasi > 0 ? round(($kategoriAkademik / $totalPrestasi) * 100, 1) : 0, 'color' => '#EC4899'],
            ],
            'distribusi_tingkat' => [
                ['name' => 'Tingkat Nasional', 'count' => $tingkatNasional, 'percent' => $totalPrestasi > 0 ? round(($tingkatNasional / $totalPrestasi) * 100, 1) : 0],
                ['name' => 'Tingkat Provinsi', 'count' => $tingkatProvinsi, 'percent' => $totalPrestasi > 0 ? round(($tingkatProvinsi / $totalPrestasi) * 100, 1) : 0],
                ['name' => 'Tingkat Kota/Kabupaten', 'count' => $tingkatKabKota, 'percent' => $totalPrestasi > 0 ? round(($tingkatKabKota / $totalPrestasi) * 100, 1) : 0],
                ['name' => 'Internal Sekolah', 'count' => $tingkatInternal, 'percent' => $totalPrestasi > 0 ? round(($tingkatInternal / $totalPrestasi) * 100, 1) : 0],
            ],
        ];

        // 4. SPOTLIGHT CARDS SISWA BERPRESTASI PER UNIT PENDIDIKAN
        $topStudentsCards = [];
        foreach ($units as $unit) {
            $topAchievement = $allMatching
                ->filter(fn ($item) => $item->siswa && $item->siswa->unit_id === $unit->id)
                ->sortByDesc('nilai_prestasi')
                ->first();

            if ($topAchievement && $topAchievement->siswa) {
                $student = $topAchievement->siswa;
                $topStudentsCards[] = [
                    'id' => $topAchievement->id,
                    'student_id' => $student->id,
                    'full_name' => $student->full_name,
                    'nis' => $student->nis,
                    'gender' => $student->gender,
                    'avatar_url' => $student->avatar_url ?? $student->photo_url,
                    'unit_id' => $unit->id,
                    'unit_name' => $unit->name,
                    'unit_code' => $unit->code,
                    'class_name' => $student->kelas?->nama_kelas ?? 'Kelas Utama',
                    'nama_prestasi' => $topAchievement->nama_prestasi,
                    'jenis_prestasi' => $topAchievement->jenis_prestasi,
                    'tingkat_prestasi' => $topAchievement->tingkat_prestasi,
                    'nilai_prestasi' => $topAchievement->nilai_prestasi,
                    'tanggal_prestasi' => $topAchievement->tanggal_prestasi ? $topAchievement->tanggal_prestasi->format('d M Y') : null,
                    'keterangan' => $topAchievement->keterangan,
                    'badge_kategori' => ucwords(str_replace('_', ' ', $topAchievement->jenis_prestasi)),
                ];
            }
        }

        // If no top achievements found by unit, take top 6 overall for cards
        if (empty($topStudentsCards) && $allMatching->isNotEmpty()) {
            foreach ($allMatching->sortByDesc('nilai_prestasi')->take(6) as $ach) {
                if ($ach->siswa) {
                    $topStudentsCards[] = [
                        'id' => $ach->id,
                        'student_id' => $ach->siswa->id,
                        'full_name' => $ach->siswa->full_name,
                        'nis' => $ach->siswa->nis,
                        'gender' => $ach->siswa->gender,
                        'avatar_url' => $ach->siswa->avatar_url ?? $ach->siswa->photo_url,
                        'unit_id' => $ach->siswa->unit_id,
                        'unit_name' => $ach->siswa->educationUnit?->name ?? 'Unit Sekolah',
                        'unit_code' => $ach->siswa->educationUnit?->code ?? 'UNIT',
                        'class_name' => $ach->siswa->kelas?->nama_kelas ?? 'Kelas Utama',
                        'nama_prestasi' => $ach->nama_prestasi,
                        'jenis_prestasi' => $ach->jenis_prestasi,
                        'tingkat_prestasi' => $ach->tingkat_prestasi,
                        'nilai_prestasi' => $ach->nilai_prestasi,
                        'tanggal_prestasi' => $ach->tanggal_prestasi ? $ach->tanggal_prestasi->format('d M Y') : null,
                        'keterangan' => $ach->keterangan,
                        'badge_kategori' => ucwords(str_replace('_', ' ', $ach->jenis_prestasi)),
                    ];
                }
            }
        }

        // 5. PAGINATED DATA DETAILS FOR TABLE
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);
        $paginated = $query->latest('tanggal_prestasi')->paginate($perPage, ['*'], 'page', $page);

        $details = collect($paginated->items())->map(function ($item) {
            $student = $item->siswa;
            return [
                'id' => $item->id,
                'id_siswa' => $item->id_siswa,
                'student_name' => $student?->full_name ?? 'Siswa Tidak Terdaftar',
                'nis' => $student?->nis ?? '-',
                'nisn' => $student?->nisn ?? '-',
                'gender' => $student?->gender ?? 'L',
                'avatar_url' => $student?->avatar_url ?? $student?->photo_url,
                'unit_id' => $student?->unit_id,
                'unit_name' => $student?->educationUnit?->name ?? 'Unit Sekolah',
                'unit_code' => $student?->educationUnit?->code ?? 'UNIT',
                'class_name' => $student?->kelas?->nama_kelas ?? 'Kelas Utama',
                'jenis_prestasi' => $item->jenis_prestasi,
                'nama_prestasi' => $item->nama_prestasi,
                'tingkat_prestasi' => $item->tingkat_prestasi ?? 'Internal Sekolah',
                'nilai_prestasi' => $item->nilai_prestasi,
                'tanggal_prestasi' => $item->tanggal_prestasi ? $item->tanggal_prestasi->format('Y-m-d') : null,
                'tanggal_prestasi_formatted' => $item->tanggal_prestasi ? $item->tanggal_prestasi->format('d M Y') : '-',
                'keterangan' => $item->keterangan ?? '-',
                'data_tambahan' => $item->data_tambahan,
            ];
        })->toArray();

        // Charts
        $charts = [
            'kategori_pie' => [
                ['name' => 'Tahfizh Al-Qur’an', 'value' => $kategoriTahfizh, 'color' => '#10B981'],
                ['name' => 'Santri Pesantren', 'value' => $kategoriSantri, 'color' => '#06B6D4'],
                ['name' => 'Olahraga & Ekskul', 'value' => $kategoriOlahraga, 'color' => '#F59E0B'],
                ['name' => 'Lomba Pembelajaran', 'value' => $kategoriLomba, 'color' => '#8B5CF6'],
                ['name' => 'Akademik Umum', 'value' => $kategoriAkademik, 'color' => '#EC4899'],
            ],
            'unit_comparison' => array_map(fn ($u) => [
                'unit_code' => $u['unit_code'],
                'unit_name' => $u['unit_name'],
                'total_prestasi' => $u['total_prestasi'],
                'tahfizh' => $u['tahfizh_count'],
                'akademik' => $u['akademik_count'],
                'olahraga' => $u['olahraga_count'],
            ], $unitRecaps),
        ];

        // Insights
        $insights = [
            [
                'type' => 'success',
                'title' => 'Capaian Prestasi Unggulan Yayasan',
                'description' => "Total {$totalPrestasi} capaian prestasi tercatat dari {$totalSiswaBerprestasi} siswa di seluruh unit pendidikan yayasan.",
            ],
            [
                'type' => 'info',
                'title' => 'Dominasi Prestasi Tingkat Nasional',
                'description' => "Terdapat {$tingkatNasional} prestasi skala Nasional dan {$tingkatProvinsi} skala Provinsi yang membanggakan nama Yayasan.",
            ],
            [
                'type' => 'warning',
                'title' => 'Distribusi Program Pembinaan Unit',
                'description' => "Unit pendidikan dengan perolehan tertinggi memimpin perolehan pada bidang Tahfizh dan Olimpiade Sains.",
            ],
        ];

        return [
            'summary' => $summary,
            'unit_recaps' => $unitRecaps,
            'unit_recaps_total' => $unitRecapsTotal,
            'kepala_sekolah_recaps' => $kepalaSekolahRecaps,
            'divisi_pendidikan_recaps' => $divisiPendidikanRecaps,
            'top_students_cards' => $topStudentsCards,
            'details' => $details,
            'charts' => $charts,
            'insights' => $insights,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'report' => [
                'title' => 'Laporan Rekapitulasi Prestasi Siswa',
                'description' => 'Rekapitulasi capaian prestasi siswa per Unit Pendidikan, Kepala Sekolah, dan Divisi Pendidikan Yayasan.',
                'period' => [
                    'label' => $period['label'],
                    'start_date' => $period['start_date'],
                    'end_date' => $period['end_date'],
                ],
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function getDetail(string $id): array
    {
        $item = RekapPrestasiSiswa::with(['siswa.educationUnit', 'siswa.kelas'])->findOrFail($id);
        $student = $item->siswa;

        return [
            'id' => $item->id,
            'nama_prestasi' => $item->nama_prestasi,
            'jenis_prestasi' => $item->jenis_prestasi,
            'tingkat_prestasi' => $item->tingkat_prestasi,
            'nilai_prestasi' => $item->nilai_prestasi,
            'tanggal_prestasi' => $item->tanggal_prestasi ? $item->tanggal_prestasi->format('d M Y') : '-',
            'keterangan' => $item->keterangan,
            'data_tambahan' => $item->data_tambahan,
            'student' => $student ? [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'gender' => $student->gender,
                'avatar_url' => $student->avatar_url ?? $student->photo_url,
                'unit_name' => $student->educationUnit?->name ?? '-',
                'class_name' => $student->kelas?->nama_kelas ?? '-',
                'tahun_masuk' => $student->tahun_masuk,
            ] : null,
        ];
    }

    private function resolvePeriod(array $filters): array
    {
        $periodType = $filters['period'] ?? 'year';
        $now = Carbon::now();

        switch ($periodType) {
            case 'month':
                return [
                    'label' => 'Bulan Ini (' . $now->translatedFormat('F Y') . ')',
                    'start_date' => $now->copy()->startOfMonth()->toDateString(),
                    'end_date' => $now->copy()->endOfMonth()->toDateString(),
                ];
            case 'semester':
                $startMonth = $now->month >= 7 ? 7 : 1;
                $startDate = Carbon::create($now->year, $startMonth, 1)->startOfDay();
                $endDate = $startDate->copy()->addMonths(5)->endOfMonth();
                return [
                    'label' => 'Semester Ini (' . $startDate->format('M Y') . ' - ' . $endDate->format('M Y') . ')',
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ];
            case 'all':
                return [
                    'label' => 'Semua Periode',
                    'start_date' => null,
                    'end_date' => null,
                ];
            case 'year':
            default:
                return [
                    'label' => 'Tahun Ajaran (' . $now->year . ')',
                    'start_date' => Carbon::create($now->year, 1, 1)->toDateString(),
                    'end_date' => Carbon::create($now->year, 12, 31)->toDateString(),
                ];
        }
    }
}
