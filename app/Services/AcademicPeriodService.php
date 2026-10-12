<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Semester;

class AcademicPeriodService
{
    private static ?AcademicYear $cachedYear = null;
    private static ?Semester $cachedSemester = null;

    public static function clearCache(): void
    {
        self::$cachedYear = null;
        self::$cachedSemester = null;
    }

    /**
     * Dapatkan tahun ajaran yang aktif secara resmi di database.
     * Mengembalikan null jika belum ada tahun ajaran yang diaktifkan oleh administrator.
     */
    public static function getActiveYear(): ?AcademicYear
    {
        return AcademicYear::query()->where('is_active', true)->first();
    }

    /**
     * Dapatkan semester yang aktif secara resmi di database.
     * Mengembalikan null jika belum ada semester yang diaktifkan oleh administrator.
     */
    public static function getActiveSemester(?string $academicYearId = null): ?Semester
    {
        return Semester::query()
            ->where('is_active', true)
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->first();
    }

    /**
     * Resolusi konteks periode akademik aktif lengkap (Single Source of Truth).
     * Jika belum dikonfigurasi, is_configured bernilai false dan objek bernilai null.
     */
    public static function getActivePeriodContext(): array
    {
        $year = self::getActiveYear();
        $semester = self::getActiveSemester($year?->id);

        $isConfigured = ($year !== null && $semester !== null);

        return [
            'is_configured' => $isConfigured,
            'status_message' => $isConfigured ? 'Tahun ajaran dan semester aktif terkonfigurasi' : 'Tahun ajaran aktif belum dikonfigurasi',
            'academic_year_name' => $year ? ($year->name ?? $year->nama ?? $year->year_name ?? '') : null,
            'semester_name' => $semester ? ($semester->name ?? $semester->nama ?? '') : null,
            'academic_year' => $year ? [
                'id' => $year->id,
                'name' => $year->name ?? $year->nama ?? $year->year_name ?? '',
                'start_date' => $year->start_date,
                'end_date' => $year->end_date,
                'is_active' => true,
            ] : null,
            'semester' => $semester ? [
                'id' => $semester->id,
                'name' => $semester->name ?? $semester->nama ?? '',
                'sequence' => $semester->sequence ?? 1,
                'is_active' => true,
            ] : null,
        ];
    }

    public static function __callStatic($method, $args)
    {
        $instance = app(self::class);
        if (method_exists($instance, $method)) {
            return $instance->$method(...$args);
        }
        throw new \BadMethodCallException("Method {$method} does not exist on " . self::class);
    }
}
