<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\CodeSequence;
use App\Models\EducationUnit;
use App\Models\Kelas;
use App\Models\Position;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CodeGeneratorService
{
    private const DEFAULT_CONFIGS = [
        'student_nis' => [
            'prefix' => '',
            'separator' => '',
            'padding_length' => 4,
            'format_pattern' => '{YEAR}{UNIT_CODE}{SEQUENCE}',
        ],
        'employee_niy' => [
            'prefix' => 'NIY',
            'separator' => '-',
            'padding_length' => 3,
            'format_pattern' => '{PREFIX}{SEPARATOR}{YEAR}{SEPARATOR}{SEQUENCE}',
        ],
        'class_code' => [
            'prefix' => 'KLS',
            'separator' => '-',
            'padding_length' => 3,
            'format_pattern' => '{PREFIX}{SEPARATOR}{EXTRA}{SEPARATOR}{SEQUENCE}',
        ],
        'subject_code' => [
            'prefix' => 'MP',
            'separator' => '-',
            'padding_length' => 3,
            'format_pattern' => '{PREFIX}{SEPARATOR}{UNIT_CODE}{SEPARATOR}{SEQUENCE}',
        ],
        'position_code' => [
            'prefix' => 'JBT',
            'separator' => '-',
            'padding_length' => 3,
            'format_pattern' => '{PREFIX}{SEPARATOR}{SEQUENCE}',
        ],
        'modul_code' => [
            'prefix' => 'MDS',
            'separator' => '-',
            'padding_length' => 4,
            'format_pattern' => '{PREFIX}{SEPARATOR}{YEARMONTH}{SEPARATOR}{SEQUENCE}',
        ],
        'document_number' => [
            'prefix' => 'SURAT',
            'separator' => '/',
            'padding_length' => 4,
            'format_pattern' => '{SEQUENCE}{SEPARATOR}{PREFIX}{SEPARATOR}{UNIT_CODE}{SEPARATOR}{YEAR}',
        ],
        'curriculum_code' => [
            'prefix' => 'KUR',
            'separator' => '-',
            'padding_length' => 3,
            'format_pattern' => '{PREFIX}{SEPARATOR}{EXTRA}{SEPARATOR}{SEQUENCE}',
        ],
        'permit_number' => [
            'prefix' => 'BP',
            'separator' => '-',
            'padding_length' => 4,
            'format_pattern' => '{PREFIX}{SEPARATOR}{YEARMONTH}{SEPARATOR}{SEQUENCE}',
        ],
        'certificate_number' => [
            'prefix' => 'DN-03/D-S',
            'separator' => '/',
            'padding_length' => 5,
            'format_pattern' => '{PREFIX}{SEPARATOR}{YEAR}{SEPARATOR}{SEQUENCE}',
        ],
        'unit_code' => [
            'prefix' => 'UNIT',
            'separator' => '-',
            'padding_length' => 2,
            'format_pattern' => '{PREFIX}{SEPARATOR}{EXTRA}{SEPARATOR}{SEQUENCE}',
        ],
    ];

    /**
     * Dapatkan preview nomor berikutnya tanpa menambah sequence counter.
     */
    public static function preview(
        string $entityType,
        array|string|null $unitId = null,
        ?string $academicYearId = null,
        array $context = []
    ): string {
        $instance = app(self::class);
        $realUnitId = is_string($unitId) ? $unitId : null;
        $realContext = is_array($unitId) ? array_merge($unitId, $context) : $context;
        return $instance->resolveCode($entityType, $realUnitId, $academicYearId, $realContext, false);
    }

    /**
     * Generate nomor resmi berikutnya dengan database transactional locking.
     */
    public static function generate(
        string $entityType,
        array|string|null $unitId = null,
        ?string $academicYearId = null,
        array $context = []
    ): string {
        $instance = app(self::class);
        $realUnitId = is_string($unitId) ? $unitId : null;
        $realContext = is_array($unitId) ? array_merge($unitId, $context) : $context;
        return DB::transaction(function () use ($instance, $entityType, $realUnitId, $academicYearId, $realContext) {
            $periodKey = $realContext['period_key'] ?? now()->format('Y');
            if (DB::getDriverName() === 'pgsql') {
                $lockKey = crc32("code_seq_{$entityType}_" . ($realUnitId ?? 'global') . "_{$periodKey}");
                DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
            }
            return $instance->resolveCode($entityType, $realUnitId, $academicYearId, $realContext, true);
        });
    }

    /**
     * Resolusi kode nomor dengan atau tanpa increment sequence counter.
     */
    private function resolveCode(
        string $entityType,
        ?string $unitId,
        ?string $academicYearId,
        array $context,
        bool $increment
    ): string {
        $defaults = self::DEFAULT_CONFIGS[$entityType] ?? [
            'prefix' => strtoupper($entityType),
            'separator' => '-',
            'padding_length' => 4,
            'format_pattern' => '{PREFIX}{SEPARATOR}{SEQUENCE}',
        ];

        $now = now();
        $year = $now->format('Y');
        $yearMonth = $now->format('Ym');
        $periodKey = $context['period_key'] ?? $year;

        $unitCode = '';
        if ($unitId) {
            $unit = EducationUnit::find($unitId);
            $unitCode = strtoupper((string) ($unit?->code ?? $unit?->level ?? ''));
        }

        $extra = strtoupper((string) ($context['extra'] ?? $context['jenjang'] ?? ''));

        // Cek apakah tabel code_sequences tersedia
        if (Schema::hasTable('code_sequences')) {
            $query = CodeSequence::query()
                ->where('entity_type', $entityType)
                ->where(function ($q) use ($unitId) {
                    if ($unitId === null) {
                        $q->whereNull('unit_id');
                    } else {
                        $q->where('unit_id', $unitId);
                    }
                })
                ->where(function ($q) use ($periodKey) {
                    $q->where('period_key', $periodKey)
                        ->orWhereNull('period_key');
                })
                ->orderByRaw('CASE WHEN period_key IS NOT NULL THEN 0 ELSE 1 END');

            if ($increment) {
                $query->lockForUpdate();
            }

            $seqModel = $query->first();

            if (! $seqModel) {
                // Inisialisasi awal sequence
                $initialSeq = $this->detectInitialSequence($entityType, $unitId, $periodKey, $context);

                $seqModel = new CodeSequence([
                    'entity_type' => $entityType,
                    'prefix' => $context['prefix'] ?? $defaults['prefix'],
                    'separator' => $context['separator'] ?? $defaults['separator'],
                    'padding_length' => $context['padding_length'] ?? $defaults['padding_length'],
                    'unit_id' => $unitId,
                    'academic_year_id' => $academicYearId,
                    'period_key' => $periodKey,
                    'current_sequence' => $initialSeq,
                    'format_pattern' => $context['format_pattern'] ?? $defaults['format_pattern'],
                    'is_active' => true,
                    'metadata' => $context['metadata'] ?? null,
                ]);

                if ($increment) {
                    $seqModel->current_sequence = $initialSeq + 1;
                    try {
                        $seqModel->save();
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Concurrency race: baris dibuat oleh transaksi lain di milidetik yang sama
                        $seqModel = CodeSequence::where('entity_type', $entityType)
                            ->where('unit_id', $unitId)
                            ->where(function ($q) use ($periodKey) {
                                $q->where('period_key', $periodKey)->orWhereNull('period_key');
                            })
                            ->lockForUpdate()
                            ->first();
                        if ($seqModel) {
                            $seqModel->current_sequence += 1;
                            $seqModel->save();
                        }
                    }
                    $sequenceNumber = $seqModel->current_sequence;
                } else {
                    $sequenceNumber = $initialSeq + 1;
                }
            } else {
                if ($increment) {
                    $seqModel->current_sequence += 1;
                    $seqModel->save();
                    $sequenceNumber = $seqModel->current_sequence;
                } else {
                    $sequenceNumber = $seqModel->current_sequence + 1;
                }
            }

            $prefix = $seqModel->prefix ?? $defaults['prefix'];
            $separator = $seqModel->separator ?? $defaults['separator'];
            $padding = $seqModel->padding_length ?? $defaults['padding_length'];
            $pattern = $seqModel->format_pattern ?? $defaults['format_pattern'];
        } else {
            // Fallback runtime sebelum migration dijalankan
            $initialSeq = $this->detectInitialSequence($entityType, $unitId, $periodKey, $context);
            $sequenceNumber = $initialSeq + ($increment ? 1 : 1);
            $prefix = $context['prefix'] ?? $defaults['prefix'];
            $separator = $context['separator'] ?? $defaults['separator'];
            $padding = $context['padding_length'] ?? $defaults['padding_length'];
            $pattern = $context['format_pattern'] ?? $defaults['format_pattern'];
        }

        $paddedSequence = str_pad((string) $sequenceNumber, $padding, '0', STR_PAD_LEFT);

        $tokens = [
            '{PREFIX}' => $prefix,
            '{SEPARATOR}' => $separator,
            '{YEAR}' => $year,
            '{YEARMONTH}' => $yearMonth,
            '{PERIOD}' => $periodKey,
            '{UNIT_CODE}' => $unitCode,
            '{EXTRA}' => $extra,
            '{SEQUENCE}' => $paddedSequence,
        ];

        $result = str_replace(array_keys($tokens), array_values($tokens), $pattern);

        // Bersihkan multiple separators jika prefix atau token kosong
        if (! empty($separator)) {
            $escaped = preg_quote($separator, '/');
            $result = preg_replace("/{$escaped}+/", $separator, $result);
            $result = trim($result, $separator);
        }

        return $result;
    }

    /**
     * Deteksi sequence awal dari data historis yang sudah ada di database.
     */
    private function detectInitialSequence(
        string $entityType,
        ?string $unitId,
        string $periodKey,
        array $context
    ): int {
        return match ($entityType) {
            'student_nis' => $this->detectStudentNisSequence($unitId, $periodKey),
            'class_code' => $this->detectClassCodeSequence($context['jenjang'] ?? null),
            'position_code' => $this->detectPositionCodeSequence(),
            default => 0,
        };
    }

    private function detectStudentNisSequence(?string $unitId, string $periodKey): int
    {
        if (! Schema::hasTable('students')) {
            return 0;
        }

        $query = Student::withTrashed();
        if ($unitId) {
            $query->where('unit_id', $unitId);
        }

        $latestNis = $query->orderByDesc('nis')->value('nis');
        if (! $latestNis) {
            return 0;
        }

        // Ambil 4 digit terakhir
        $num = (int) preg_replace('/[^0-9]/', '', substr((string) $latestNis, -4));
        return $num > 0 ? $num : 0;
    }

    private function detectClassCodeSequence(?string $jenjang): int
    {
        if (! Schema::hasTable('tbl_kelas')) {
            return 0;
        }

        $query = Kelas::query();
        if ($jenjang) {
            $query->where('jenjang', $jenjang);
        }

        return $query->count();
    }

    private function detectPositionCodeSequence(): int
    {
        if (! Schema::hasTable('positions')) {
            return 0;
        }

        return Position::withTrashed()
            ->where('code', 'LIKE', 'JBT-%')
            ->pluck('code')
            ->map(fn (string $code) => (int) preg_replace('/[^0-9]/', '', $code))
            ->max() ?? 0;
    }
}
