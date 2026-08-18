<?php

namespace App\Services\Reports;

use App\Models\EducationUnit;
use App\Models\Student;
use Carbon\Carbon;

class AlumniReportService
{
    public function getReport(array $filters): array
    {
        $period = $this->resolvePeriod($filters);

        // Query students who are non-active or explicitly flagged as alumni
        $query = Student::with(['educationUnit', 'kelas', 'parent'])
            ->where(function ($q) {
                $q->where('is_active', false)
                  ->orWhere('metadata->is_alumni', true)
                  ->orWhere('metadata->status_siswa', 'alumni');
            });

        if (!empty($filters['unit_id']) && $filters['unit_id'] !== 'all') {
            $query->where('unit_id', $filters['unit_id']);
        }
        if (!empty($filters['angkatan']) && $filters['angkatan'] !== 'all') {
            $query->where('metadata->angkatan', $filters['angkatan']);
        }
        if (!empty($filters['tahun_lulus']) && $filters['tahun_lulus'] !== 'all') {
            $query->where('metadata->tahun_lulus', $filters['tahun_lulus']);
        }
        if (!empty($filters['pendidikan_lanjutan']) && $filters['pendidikan_lanjutan'] !== 'all') {
            $query->where('metadata->pendidikan_lanjutan', $filters['pendidikan_lanjutan']);
        }
        if (!empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        $allAlumni = (clone $query)->get();
        $totalAlumni = $allAlumni->count();

        $male = $allAlumni->filter(fn ($s) => in_array(strtolower($s->gender ?? 'l'), ['l', 'laki-laki', 'male']))->count();
        $female = max(0, $totalAlumni - $male);

        $currentYear = (int) date('Y');
        $thisYearAlumni = $allAlumni->filter(fn ($s) => (int) ($s->metadata['tahun_lulus'] ?? 0) === $currentYear)->count();

        $kuliah = $allAlumni->filter(fn ($s) => !empty($s->metadata['perguruan_tinggi']) || in_array(strtolower($s->metadata['status_lanjutan'] ?? ''), ['kuliah', 'pendidikan', 'studi']))->count();
        $kerja = $allAlumni->filter(fn ($s) => !empty($s->metadata['perusahaan']) || in_array(strtolower($s->metadata['status_lanjutan'] ?? ''), ['bekerja', 'kerja']))->count();
        $wirausaha = $allAlumni->filter(fn ($s) => in_array(strtolower($s->metadata['status_lanjutan'] ?? ''), ['wirausaha', 'bisnis', 'entrepreneur']))->count();

        $belumTerdata = max(0, $totalAlumni - ($kuliah + $kerja + $wirausaha));
        $contactable = $allAlumni->filter(fn ($s) => !empty($s->metadata['no_hp_alumni']) || !empty($s->parent->father_phone) || !empty($s->parent->mother_phone))->count();

        // Unit recaps
        $units = EducationUnit::all();
        $unitRecaps = $units->map(function ($u) {
            $alum = Student::where('unit_id', $u->id)
                ->where(function ($q) {
                    $q->where('is_active', false)
                      ->orWhere('metadata->is_alumni', true)
                      ->orWhere('metadata->status_siswa', 'alumni');
                })->get();

            $tot = $alum->count();
            $k = $alum->filter(fn ($s) => !empty($s->metadata['perguruan_tinggi']) || in_array(strtolower($s->metadata['status_lanjutan'] ?? ''), ['kuliah', 'pendidikan', 'studi']))->count();
            $w = $alum->filter(fn ($s) => !empty($s->metadata['perusahaan']) || in_array(strtolower($s->metadata['status_lanjutan'] ?? ''), ['bekerja', 'kerja']))->count();
            $e = $alum->filter(fn ($s) => in_array(strtolower($s->metadata['status_lanjutan'] ?? ''), ['wirausaha', 'bisnis']))->count();
            $bt = max(0, $tot - ($k + $w + $e));

            return [
                'unit_id' => $u->id,
                'unit_code' => $u->code,
                'unit_name' => $u->name,
                'total_alumni' => $tot,
                'melanjutkan_pendidikan' => $k,
                'bekerja' => $w,
                'berwirausaha' => $e,
                'belum_terdata' => $bt,
            ];
        });

        // Batch recaps
        $batchRecaps = $allAlumni->groupBy(fn ($s) => $s->metadata['angkatan'] ?? ('Angkatan ' . ($s->tahun_masuk ?? '2023')))
            ->map(function ($group, $key) {
                $tot = $group->count();
                $k = $group->filter(fn ($s) => !empty($s->metadata['perguruan_tinggi']) || in_array(strtolower($s->metadata['status_lanjutan'] ?? ''), ['kuliah', 'pendidikan', 'studi']))->count();
                $w = $group->filter(fn ($s) => !empty($s->metadata['perusahaan']) || in_array(strtolower($s->metadata['status_lanjutan'] ?? ''), ['bekerja', 'kerja']))->count();
                $e = $group->filter(fn ($s) => in_array(strtolower($s->metadata['status_lanjutan'] ?? ''), ['wirausaha', 'bisnis']))->count();

                return [
                    'angkatan' => $key,
                    'total_alumni' => $tot,
                    'kuliah' => $k,
                    'bekerja' => $w,
                    'berwirausaha' => $e,
                    'belum_terdata' => max(0, $tot - ($k + $w + $e)),
                ];
            })->values();

        $topUnit = $unitRecaps->sortByDesc('total_alumni')->first();

        $summary = [
            'total_alumni' => $totalAlumni,
            'alumni_laki_laki' => $male,
            'alumni_perempuan' => $female,
            'alumni_tahun_ini' => $thisYearAlumni,
            'alumni_kuliah' => $kuliah,
            'alumni_bekerja' => $kerja,
            'alumni_wirausaha' => $wirausaha,
            'alumni_belum_terdata' => $belumTerdata,
            'alumni_dapat_dihubungi' => $contactable,
            'unit_alumni_terbanyak' => $topUnit ? "{$topUnit['unit_name']} ({$topUnit['total_alumni']} Alumni)" : '-',
        ];

        $charts = [
            'alumni_by_unit' => $unitRecaps->map(fn ($r) => [
                'name' => $r['unit_code'] ?: $r['unit_name'],
                'total' => $r['total_alumni'],
                'kuliah' => $r['melanjutkan_pendidikan'],
                'bekerja' => $r['bekerja'],
            ]),
            'status_lanjutan' => [
                ['name' => 'Kuliah / Perguruan Tinggi', 'value' => $kuliah],
                ['name' => 'Bekerja', 'value' => $kerja],
                ['name' => 'Berwirausaha', 'value' => $wirausaha],
                ['name' => 'Belum Terdata', 'value' => $belumTerdata],
            ],
            'gender_distribution' => [
                ['name' => 'Laki-Laki', 'value' => $male],
                ['name' => 'Perempuan', 'value' => $female],
            ],
        ];

        // Paginated details
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);
        $paginated = (clone $query)->paginate($perPage, ['*'], 'page', $page);

        $formattedDetails = collect($paginated->items())->map(function ($s) {
            $meta = $s->metadata ?? [];
            $lanjutan = $meta['perguruan_tinggi'] ?? $meta['perusahaan'] ?? $meta['status_lanjutan'] ?? 'Belum tersedia';

            return [
                'id' => $s->id,
                'nis_nisn' => ($s->nis ?? '-') . ' / ' . ($s->nisn ?? '-'),
                'nama' => $s->full_name,
                'unit_asal' => $s->educationUnit->name ?? '-',
                'unit_code' => $s->educationUnit->code ?? '-',
                'angkatan' => $meta['angkatan'] ?? ('Angkatan ' . ($s->tahun_masuk ?? '-')),
                'tahun_lulus' => $meta['tahun_lulus'] ?? date('Y'),
                'pendidikan_pekerjaan' => $lanjutan,
                'status' => ucfirst($meta['status_alumni'] ?? 'Aktif Terdata'),
            ];
        });

        return [
            'report' => [
                'title' => 'Laporan Alumni',
                'description' => 'Laporan data alumni, angkatan, pendidikan lanjutan, dan pekerjaan seluruh Unit Pendidikan.',
                'period' => $period,
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => $summary,
            'charts' => $charts,
            'unit_recaps' => $unitRecaps,
            'batch_recaps' => $batchRecaps,
            'unit_recaps_total' => [
                'unit_name' => 'TOTAL KESELURUHAN',
                'total_alumni' => $unitRecaps->sum('total_alumni'),
                'melanjutkan_pendidikan' => $unitRecaps->sum('melanjutkan_pendidikan'),
                'bekerja' => $unitRecaps->sum('bekerja'),
                'berwirausaha' => $unitRecaps->sum('berwirausaha'),
                'belum_terdata' => $unitRecaps->sum('belum_terdata'),
            ],
            'details' => $formattedDetails,
            'insights' => [
                'pendidikan_lanjutan_rate' => $totalAlumni > 0 ? round(($kuliah / $totalAlumni) * 100, 1) . '% Melanjutkan ke Perguruan Tinggi' : 'Belum ada data',
                'data_terverifikasi' => $totalAlumni > 0 ? round((($totalAlumni - $belumTerdata) / $totalAlumni) * 100, 1) . '% Data Terdata Lengkap' : '0%',
            ],
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ];
    }

    public function getDetail(string $id): array
    {
        $s = Student::with(['educationUnit', 'parent'])->findOrFail($id);
        $meta = $s->metadata ?? [];

        return [
            'id' => $s->id,
            'foto' => $s->photo,
            'nis' => $s->nis ?? 'Belum tersedia',
            'nisn' => $s->nisn ?? 'Belum tersedia',
            'nama' => $s->full_name,
            'unit_asal' => $s->educationUnit->name ?? 'Belum tersedia',
            'angkatan' => $meta['angkatan'] ?? ('Angkatan ' . ($s->tahun_masuk ?? 'Belum tersedia')),
            'tahun_masuk' => $s->tahun_masuk ?? 'Belum tersedia',
            'tahun_lulus' => $meta['tahun_lulus'] ?? 'Belum tersedia',
            'no_hp' => $meta['no_hp_alumni'] ?? $s->parent->father_phone ?? $s->parent->mother_phone ?? 'Belum tersedia',
            'email' => $meta['email_alumni'] ?? 'Belum tersedia',
            'alamat' => $s->address ?? 'Belum tersedia',
            'pendidikan_lanjutan' => $meta['pendidikan_lanjutan'] ?? 'Belum tersedia',
            'perguruan_tinggi' => $meta['perguruan_tinggi'] ?? 'Belum tersedia',
            'program_studi' => $meta['program_studi'] ?? 'Belum tersedia',
            'pekerjaan' => $meta['pekerjaan'] ?? 'Belum tersedia',
            'perusahaan' => $meta['perusahaan'] ?? 'Belum tersedia',
            'status_alumni' => $meta['status_alumni'] ?? 'Terdata',
        ];
    }

    private function resolvePeriod(array $filters): array
    {
        return [
            'type' => 'all',
            'label' => 'Keseluruhan Angkatan Alumni',
            'start_date' => '2015-01-01',
            'end_date' => date('Y-12-31'),
        ];
    }
}
