<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\QuranJuzHelper;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\LmsMateri;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\LmsPresensi;
use App\Models\LessonAttendanceSession;
use App\Models\LmsUjian;
use App\Models\LmsUjianSesi;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\Notification;
use App\Models\ParentModel;
use App\Models\PortalMessage;
use App\Models\PengumumanSekolah;
use App\Models\RekapPrestasiSiswa;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\EducationProgramSetting;
use App\Models\StudentAttendancePermission;
use App\Models\DormitoryPermit;
use App\Models\StudentBill;
use App\Models\StudentGrade;
use App\Models\StudentNote;
use App\Models\Subject;
use App\Models\WorshipAttendanceDetail;
use App\Models\WorshipAttendanceSession;
use App\Models\WorshipAttendanceTemplate;
use App\Services\RealtimeBroadcastService;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsRapor;
use App\Models\Teacher;
use App\Models\User;
use App\Models\TahfizhDailyLog;
use App\Services\GateAttendanceService;
use App\Services\LmsUjianService;
use App\Services\StudentQrCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class StudentParentPortalController extends Controller
{
    public function __construct(
        private readonly LmsUjianService $ujianService,
        private readonly StudentQrCredentialService $studentQr,
        private readonly GateAttendanceService $gateService,
    ) {}

    private function parentStudentsQuery(ParentModel $parent)
    {
        return Student::query()->where(function ($query) use ($parent) {
            $query->where('parent_id', $parent->id)
                ->orWhereHas('parentsPivot', fn ($pivot) => $pivot->whereKey($parent->id));
        });
    }

    /** CBT memakai relasi siswa langsung, dengan fallback student context untuk Super Admin/Admin testing dan Orang Tua pendamping. */
    private function getAuthenticatedStudent(Request $request): ?Student
    {
        $student = Student::query()
            ->with(['kelas.unitPendidikan', 'educationUnit'])
            ->where('user_id', $request->user()?->id)
            ->where('is_active', true)
            ->first();

        if (! $student && $request->user()?->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Orang Tua', 'orang_tua', 'parent', 'Wali', 'wali'])) {
            return $this->getStudentContext($request);
        }

        return $student;
    }

    private function getStudentContext(Request $request): ?Student
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        // If parent or admin has selected a child id in route/header/query/input, honor it first!
        $selectedChildId = $request->route('studentId')
            ?? $request->route('childId')
            ?? $request->header('X-Child-Id')
            ?? $request->header('X-Student-Id')
            ?? $request->query('child_id')
            ?? $request->input('child_id')
            ?? $request->query('student_id')
            ?? $request->input('student_id')
            ?? $request->query('child')
            ?? $request->input('child');

        // Direct student account always resolves to their own active profile (student cannot switch identity)
        if ($user->hasAnyRole(['Siswa', 'siswa', 'student'])) {
            $selfStudent = Student::query()->with(['kelas.unitPendidikan', 'educationUnit'])
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();
            if ($selfStudent) {
                return $selfStudent;
            }
        }

        if ($selectedChildId) {
            if (Str::isUuid($selectedChildId)) {
                $parent = ParentModel::query()->where('user_id', $user->id)->first();
                if ($parent) {
                    $child = $this->parentStudentsQuery($parent)
                        ->with(['kelas.unitPendidikan', 'educationUnit'])
                        ->whereKey($selectedChildId)
                        ->where('is_active', true)
                        ->first();
                    if ($child) {
                        return $child;
                    }
                }
                $child = Student::query()->with(['kelas.unitPendidikan', 'educationUnit'])
                    ->where('user_id', $user->id)
                    ->whereKey($selectedChildId)
                    ->where('is_active', true)
                    ->first();
                if ($child) {
                    return $child;
                }

                // Fallback for Super Admin / Admin / Yayasan inspecting specific student in portal
                if ($user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan'])) {
                    $child = Student::query()->with(['kelas.unitPendidikan', 'educationUnit'])
                        ->whereKey($selectedChildId)
                        ->where('is_active', true)
                        ->first();
                    if ($child) {
                        return $child;
                    }
                }

                return null;
            }

            // Synthetic/demo identifiers must never resolve to an arbitrary student.
            return null;
        }

        // Fallback for Super Admin / Admin / Yayasan inspecting portal without specifying child
        if ($user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan'])) {
            $adminChild = Student::query()->with(['kelas.unitPendidikan', 'educationUnit'])
                ->where('is_active', true)
                ->first();
            if ($adminChild) {
                return $adminChild;
            }
        }

        // Check direct student relation
        $student = Student::query()->with(['kelas.unitPendidikan', 'educationUnit'])
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
        if ($student) {
            return $student;
        }

        // Check if parent account, fetch first child
        $parent = ParentModel::query()->where('user_id', $user->id)->first();
        if ($parent) {
            $child = $this->parentStudentsQuery($parent)
                ->with(['kelas.unitPendidikan', 'educationUnit'])
                ->where('is_active', true)
                ->first();
            if ($child) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Resolusi akurat tingkat kelas dan jenjang pendidikan ananda.
     * Menghindari kesalahan deteksi kata/substring seperti "el-Iman" -> "MA" -> "SMA".
     */
    private function resolveStudentGradeAndLevel(Student $student, ?Kelas $classObj = null): array
    {
        $classObj = $classObj ?? $student->kelas ?? \App\Models\Kelas::find($student->kelas_id ?? $student->class_id);
        $eduUnit = $classObj?->unitPendidikan ?? $student->educationUnit;
        $unitName = $eduUnit?->name ?? $eduUnit?->nama ?? $student->unit_name ?? null;
        $className = $classObj?->nama_kelas ?? $classObj?->name ?? $student->class_name ?? '';
        $rawTingkat = (string) ($classObj?->tingkat ?? $student->tingkat ?? '');

        // 1. Ekstrak angka tingkat kelas aktual
        $currentGradeNum = null;
        if (preg_match('/\b(1[0-2]|[1-9])\b/', $rawTingkat, $m)) {
            $currentGradeNum = (int) $m[1];
        } elseif (preg_match('/\b(1[0-2]|[1-9])\b/', $className, $m)) {
            $currentGradeNum = (int) $m[1];
        } elseif (preg_match('/([1-9]|1[0-2])[A-Za-z]?\b/', $className, $m)) {
            // Contoh "6A" -> 6, "10IPA" -> 10
            $currentGradeNum = (int) $m[1];
        } elseif (preg_match('/\b(XII|XI|X|IX|VIII|VII|VI|V|IV|III|II|I)\b/i', $className . ' ' . $rawTingkat, $m)) {
            $romans = [
                'XII' => 12, 'XI' => 11, 'X' => 10,
                'IX' => 9, 'VIII' => 8, 'VII' => 7,
                'VI' => 6, 'V' => 5, 'IV' => 4,
                'III' => 3, 'II' => 2, 'I' => 1,
            ];
            $currentGradeNum = $romans[strtoupper($m[1])] ?? null;
        }

        // 2. Tentukan jenjang / level pendidikan dengan word boundary regex
        $rawLevel = strtoupper(trim($eduUnit?->level ?? $classObj?->jenjang ?? $student->jenjang ?? ''));
        $unitNameUpper = strtoupper(trim($unitName));
        $combinedText = $rawLevel . ' ' . $unitNameUpper . ' ' . strtoupper($className);

        // The education unit is authoritative. Never manufacture a grade from
        // the unit name; keep it null when the class record has no grade.
        if (preg_match('/\b(TK|PAUD|RA|KB|TPA)\b/i', $combinedText)) {
            $normalizedLevel = 'TK';
        } elseif (preg_match('/\b(SMA|SMK|MA|ALIYAH|SLTA)\b/i', $combinedText)) {
            $normalizedLevel = 'SMA';
        } elseif (preg_match('/\b(SMP|MTS|SLTP)\b/i', $combinedText)) {
            $normalizedLevel = 'SMP';
        } elseif (preg_match('/\b(SD|MI|SDIT)\b/i', $combinedText)) {
            $normalizedLevel = 'SD';
        } else {
            $normalizedLevel = null;
        }

        return [
            'grade' => $currentGradeNum,
            'level' => $normalizedLevel,
            'unit_name' => $unitName,
            'class_name' => $className ?: null,
            'class_obj' => $classObj,
            'edu_unit' => $eduUnit,
        ];
    }

    /**
     * Resolusi kategori dan surah tampilan log setoran tahfizh secara dinamis & konsisten (Single Source of Truth).
     * Mengembalikan tuple: [$category, $displaySurah]
     * Kategori valid: 'ziyadah' | 'murajaah' | 'tilawah'
     */
    private function resolveTahfizhLogCategory(TahfizhDailyLog $log): array
    {
        $metaArr = is_array($log->metadata) ? $log->metadata : (is_string($log->metadata) ? json_decode($log->metadata, true) : []);
        $metaType = strtolower((string) data_get($metaArr, 'type', ''));
        $isSubmittedByParent = !empty($metaArr['submitted_by_parent']);
        $isMurajStatus = in_array(strtolower((string) $log->murajaah_status), ['pending_home', 'submitted_by_parent']);
        $isExplicitMurajaah = in_array($metaType, ['murajaah', "muroja'ah", 'pengulangan']) || $isSubmittedByParent || $isMurajStatus;

        // 1. Prioritas Utama: Eksplisit Murajaah (tugas orang tua / tipe murajaah / pengulangan)
        if ($isExplicitMurajaah) {
            $displaySurah = $log->murajaah_required_surah_name ?: ($log->murajaah_text ?: ($log->hafalan_surah_name ?: $log->surah));
            return ['murajaah', $displaySurah];
        }

        // 2. Setoran Hafalan Baru (Ziyadah)
        $hasZiyadah = !empty($log->hafalan_surah_name) && (!empty($log->hafalan_ayah_start) || $metaType === 'ziyadah');
        if ($hasZiyadah) {
            return ['ziyadah', $log->hafalan_surah_name];
        }

        // 3. Setoran Murajaah (jika ada murajaah_text atau lembar murajaah > 0 tanpa hafalan baru)
        $hasMurajaah = !empty($log->murajaah_text) || (!empty($log->murajaah_lembar) && (float) $log->murajaah_lembar > 0);
        if ($hasMurajaah) {
            $displaySurah = $log->murajaah_required_surah_name ?: ($log->murajaah_text ?: ($log->hafalan_surah_name ?: $log->surah));
            return ['murajaah', $displaySurah];
        }

        // 4. Setoran Tilawah (murni tilawah tanpa hafalan baru dan tanpa murajaah)
        $hasTilawah = $metaType === 'tilawah' || !empty($log->tilawah_text) || (!empty($log->tilawah_baris) && (float) $log->tilawah_baris > 0);
        if ($hasTilawah) {
            return ['tilawah', $log->tilawah_text ?: ($log->surah ?: "Tilawah Al-Qur'an")];
        }

        // 5. Fallback Default
        return ['ziyadah', $log->hafalan_surah_name ?: ($log->surah ?: null)];
    }

    /**
     * Menghitung status kehadiran hari ini secara dinamis dan akurat:
     * - Mendeteksi hari libur (akhir pekan / kalender libur sekolah)
     * - Mendeteksi catatan kehadiran gerbang / umum (attendances)
     * - Mendeteksi perizinan resmi (sakit/izin) yang telah disetujui
     * - Mendeteksi catatan LMS Presensi jika ada
     * - Memberikan status 'Belum Presensi' jika hari sekolah aktif namun belum diabsen
     */
    private function resolveStudentTodayAttendanceStatus(Student $student, ?Carbon $today = null): array
    {
        $today = $today ?: now();
        $todayStr = $today->toDateString();
        $isWeekend = $today->isWeekend();

        // 1. Cek Record Presensi Gerbang / Harian (tabel attendances)
        $att = Attendance::query()
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', $todayStr)
            ->first();

        if ($att) {
            $rawStatus = strtoupper(trim((string) $att->status));
            $checkIn = $att->check_in_time ? Carbon::parse($att->check_in_time)->format('H:i') : null;
            if (in_array($rawStatus, ['HADIR', 'PRESENT'])) {
                return [
                    'status' => 'hadir',
                    'status_label' => 'Hadir',
                    'label' => 'Hadir',
                    'color' => '#10B981',
                    'time' => $checkIn,
                    'is_holiday' => false,
                ];
            }
            if (in_array($rawStatus, ['TERLAMBAT', 'LATE'])) {
                return [
                    'status' => 'terlambat',
                    'status_label' => 'Terlambat',
                    'label' => 'Terlambat',
                    'color' => '#F59E0B',
                    'time' => $checkIn,
                    'is_holiday' => false,
                ];
            }
            if (in_array($rawStatus, ['SAKIT', 'SICK'])) {
                return [
                    'status' => 'sakit',
                    'status_label' => 'Sakit',
                    'label' => 'Sakit',
                    'color' => '#38BDF8',
                    'time' => null,
                    'is_holiday' => false,
                ];
            }
            if (in_array($rawStatus, ['IZIN', 'PERMIT'])) {
                return [
                    'status' => 'izin',
                    'status_label' => 'Izin',
                    'label' => 'Izin',
                    'color' => '#60A5FA',
                    'time' => null,
                    'is_holiday' => false,
                ];
            }
            if (in_array($rawStatus, ['ALPHA', 'ALPA', 'ABSENT'])) {
                return [
                    'status' => 'tidak_hadir',
                    'status_label' => 'Tidak Hadir',
                    'label' => 'Tidak Hadir',
                    'color' => '#EF4444',
                    'time' => null,
                    'is_holiday' => false,
                ];
            }
        }

        // 2. Cek Perizinan Kehadiran Siswa yang Disetujui (StudentAttendancePermission)
        $permission = StudentAttendancePermission::query()
            ->where('student_id', $student->id)
            ->where('start_date', '<=', $todayStr)
            ->where('end_date', '>=', $todayStr)
            ->whereIn('status', ['approved', 'disetujui'])
            ->first();

        if ($permission) {
            $pType = strtolower((string) $permission->type);
            $isSick = str_contains($pType, 'sakit');
            return [
                'status' => $isSick ? 'sakit' : 'izin',
                'status_label' => $isSick ? 'Sakit' : 'Izin',
                'label' => $isSick ? 'Sakit' : 'Izin',
                'color' => $isSick ? '#38BDF8' : '#60A5FA',
                'time' => null,
                'is_holiday' => false,
            ];
        }

        // 3. Cek LMS Presensi (Mapel / Kelas)
        $lms = LmsPresensi::query()
            ->where('siswa_id', $student->id)
            ->whereDate('tanggal', $todayStr)
            ->first();

        if ($lms) {
            $lmsStatus = strtolower(trim((string) ($lms->status ?? $lms->status_hadir ?? '')));
            $time = $lms->waktu_presensi ? Carbon::parse($lms->waktu_presensi)->format('H:i') : null;
            if (in_array($lmsStatus, ['h', 'hadir', 'present'])) {
                return [
                    'status' => 'hadir',
                    'status_label' => 'Hadir',
                    'label' => 'Hadir',
                    'color' => '#10B981',
                    'time' => $time,
                    'is_holiday' => false,
                ];
            }
            if (in_array($lmsStatus, ['t', 'terlambat', 'late'])) {
                return [
                    'status' => 'terlambat',
                    'status_label' => 'Terlambat',
                    'label' => 'Terlambat',
                    'color' => '#F59E0B',
                    'time' => $time,
                    'is_holiday' => false,
                ];
            }
            if (in_array($lmsStatus, ['s', 'sakit'])) {
                return [
                    'status' => 'sakit',
                    'status_label' => 'Sakit',
                    'label' => 'Sakit',
                    'color' => '#38BDF8',
                    'time' => null,
                    'is_holiday' => false,
                ];
            }
            if (in_array($lmsStatus, ['i', 'izin'])) {
                return [
                    'status' => 'izin',
                    'status_label' => 'Izin',
                    'label' => 'Izin',
                    'color' => '#60A5FA',
                    'time' => null,
                    'is_holiday' => false,
                ];
            }
            if (in_array($lmsStatus, ['a', 'alpa', 'alpha'])) {
                return [
                    'status' => 'tidak_hadir',
                    'status_label' => 'Tidak Hadir',
                    'label' => 'Tidak Hadir',
                    'color' => '#EF4444',
                    'time' => null,
                    'is_holiday' => false,
                ];
            }
        }

        // 4. Cek Hari Libur Sekolah di PengumumanSekolah (Kalender Akademik)
        $schoolHoliday = PengumumanSekolah::query()
            ->where('status_aktif', true)
            ->where('mulai_tampil', '<=', $todayStr)
            ->where(function ($sub) use ($todayStr) {
                $sub->whereNull('selesai_tampil')->orWhere('selesai_tampil', '>=', $todayStr);
            })
            ->where(function ($sub) {
                $sub->whereIn('data_tambahan->kategori', ['libur_sekolah', 'libur_semester', 'libur_nasional', 'cuti_bersama', 'libur'])
                    ->orWhere('judul_pengumuman', 'like', '%libur%');
            })
            ->first();

        if ($schoolHoliday) {
            $holidayLabel = str_contains(strtolower($schoolHoliday->judul_pengumuman), 'semester') ? 'Libur Semester' : 'Libur Sekolah';
            return [
                'status' => 'libur',
                'status_label' => $holidayLabel,
                'label' => $holidayLabel,
                'holiday_title' => $schoolHoliday->judul_pengumuman,
                'color' => '#F59E0B',
                'time' => null,
                'is_holiday' => true,
            ];
        }

        // 5. Cek Akhir Pekan (Sabtu / Minggu)
        if ($isWeekend) {
            return [
                'status' => 'libur',
                'status_label' => 'Libur Akhir Pekan',
                'label' => 'Libur',
                'color' => '#F59E0B',
                'time' => null,
                'is_holiday' => true,
            ];
        }

        // 6. Hari sekolah aktif biasa tetapi belum ada data presensi hari ini
        return [
            'status' => 'belum_presensi',
            'status_label' => 'Belum Presensi',
            'label' => 'Belum Presensi',
            'color' => '#94A3B8',
            'time' => null,
            'is_holiday' => false,
        ];
    }

    public function children(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "portal_children_user_{$user->id}";

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 30, function () use ($user) {
            $parent = ParentModel::query()->where('user_id', $user->id)->first();

            $enrichStudentsBatch = function ($students) use ($user) {
                if ($students->isEmpty()) return $students;

                $childIds = $students->pluck('id')->filter()->all();
                $todayStr = now()->toDateString();
                $isWeekend = now()->isWeekend();

                // 1. Single query untuk libur sekolah
                $schoolHoliday = PengumumanSekolah::query()
                    ->where('status_aktif', true)
                    ->where('mulai_tampil', '<=', $todayStr)
                    ->where(function ($sub) use ($todayStr) {
                        $sub->whereNull('selesai_tampil')->orWhere('selesai_tampil', '>=', $todayStr);
                    })
                    ->where(function ($sub) {
                        $sub->whereIn('data_tambahan->kategori', ['libur_sekolah', 'libur_semester', 'libur_nasional', 'cuti_bersama', 'libur'])
                            ->orWhere('judul_pengumuman', 'like', '%libur%');
                    })
                    ->first();

                // 2. Batch presensi gerbang/harian
                $attendances = Attendance::query()
                    ->whereIn('student_id', $childIds)
                    ->whereDate('attendance_date', $todayStr)
                    ->get()
                    ->keyBy('student_id');

                // 3. Batch perizinan disetujui
                $permissions = StudentAttendancePermission::query()
                    ->whereIn('student_id', $childIds)
                    ->where('start_date', '<=', $todayStr)
                    ->where('end_date', '>=', $todayStr)
                    ->whereIn('status', ['approved', 'disetujui'])
                    ->get()
                    ->keyBy('student_id');

                // 4. Batch presensi LMS
                $lmsPresensis = LmsPresensi::query()
                    ->whereIn('siswa_id', $childIds)
                    ->whereDate('tanggal', $todayStr)
                    ->get()
                    ->keyBy('siswa_id');

                // 5. Batch pending penugasan
                $classIds = $students->map(fn ($c) => array_values(array_unique(array_filter([$c->kelas_id, $c->class_id]))))->flatten()->unique()->filter()->values();
                $activeAssignments = $classIds->isNotEmpty()
                    ? \App\Models\LmsPenugasan::query()
                        ->whereIn('kelas_id', $classIds)
                        ->where('is_published', true)
                        ->where('deadline', '>=', now())
                        ->get(['id', 'kelas_id'])
                    : collect();

                $submittedGrouped = ($activeAssignments->isNotEmpty() && !empty($childIds))
                    ? \App\Models\LmsPengumpulanTugas::query()
                        ->whereIn('siswa_id', $childIds)
                        ->whereIn('penugasan_id', $activeAssignments->pluck('id'))
                        ->get(['siswa_id', 'penugasan_id'])
                        ->groupBy('siswa_id')
                    : collect();

                // 6. Batch notifikasi unread user (cukup 1x query, filter di memori)
                $unreadNotifs = \App\Models\Notification::byUser($user->id)->unread()->get();

                return $students->map(function ($c) use ($user, $todayStr, $isWeekend, $schoolHoliday, $attendances, $permissions, $lmsPresensis, $activeAssignments, $submittedGrouped, $unreadNotifs) {
                    $cidStr = (string) $c->id;

                    // Notifikasi unread count di memori
                    $notifCount = $unreadNotifs->filter(function ($n) use ($cidStr, $c) {
                        $meta = is_array($n->metadata) ? $n->metadata : (is_string($n->metadata) ? json_decode($n->metadata, true) : []);
                        $targetSid = (string) ($meta['student_id'] ?? $meta['child_id'] ?? '');
                        if ($targetSid && $targetSid === $cidStr) return true;
                        if ($n->notifiable_type === Student::class && (string) $n->notifiable_id === $cidStr) return true;
                        return false;
                    })->count();

                    // Hitung pending assignments
                    $classes = array_values(array_unique(array_filter([$c->kelas_id, $c->class_id])));
                    $relevantClassAssignments = $activeAssignments->whereIn('kelas_id', $classes);
                    $doneAssignmentIds = ($submittedGrouped->get($c->id) ?? collect())->pluck('penugasan_id')->all();
                    $pendingAssignments = $relevantClassAssignments->reject(fn ($a) => in_array($a->id, $doneAssignmentIds))->count();

                    // Resolusi status presensi hari ini
                    $todayAtt = null;
                    $att = $attendances->get($c->id);
                    if ($att) {
                        $rawStatus = strtoupper(trim((string) $att->status));
                        $checkIn = $att->check_in_time ? Carbon::parse($att->check_in_time)->format('H:i') : null;
                        if (in_array($rawStatus, ['HADIR', 'PRESENT'])) {
                            $todayAtt = ['status' => 'hadir', 'status_label' => 'Hadir', 'label' => 'Hadir', 'color' => '#10B981', 'time' => $checkIn, 'is_holiday' => false];
                        } elseif (in_array($rawStatus, ['TERLAMBAT', 'LATE'])) {
                            $todayAtt = ['status' => 'terlambat', 'status_label' => 'Terlambat', 'label' => 'Terlambat', 'color' => '#F59E0B', 'time' => $checkIn, 'is_holiday' => false];
                        } elseif (in_array($rawStatus, ['SAKIT', 'SICK'])) {
                            $todayAtt = ['status' => 'sakit', 'status_label' => 'Sakit', 'label' => 'Sakit', 'color' => '#38BDF8', 'time' => null, 'is_holiday' => false];
                        } elseif (in_array($rawStatus, ['IZIN', 'PERMIT'])) {
                            $todayAtt = ['status' => 'izin', 'status_label' => 'Izin', 'label' => 'Izin', 'color' => '#60A5FA', 'time' => null, 'is_holiday' => false];
                        } elseif (in_array($rawStatus, ['ALPHA', 'ALPA', 'ABSENT'])) {
                            $todayAtt = ['status' => 'tidak_hadir', 'status_label' => 'Tidak Hadir', 'label' => 'Tidak Hadir', 'color' => '#EF4444', 'time' => null, 'is_holiday' => false];
                        }
                    }

                    if (! $todayAtt) {
                        $perm = $permissions->get($c->id);
                        if ($perm) {
                            $isSick = str_contains(strtolower((string) $perm->type), 'sakit');
                            $todayAtt = ['status' => $isSick ? 'sakit' : 'izin', 'status_label' => $isSick ? 'Sakit' : 'Izin', 'label' => $isSick ? 'Sakit' : 'Izin', 'color' => $isSick ? '#38BDF8' : '#60A5FA', 'time' => null, 'is_holiday' => false];
                        }
                    }

                    if (! $todayAtt) {
                        $lms = $lmsPresensis->get($c->id);
                        if ($lms) {
                            $lmsStatus = strtolower(trim((string) ($lms->status ?? $lms->status_hadir ?? '')));
                            $time = $lms->waktu_presensi ? Carbon::parse($lms->waktu_presensi)->format('H:i') : null;
                            if (in_array($lmsStatus, ['h', 'hadir', 'present'])) {
                                $todayAtt = ['status' => 'hadir', 'status_label' => 'Hadir', 'label' => 'Hadir', 'color' => '#10B981', 'time' => $time, 'is_holiday' => false];
                            } elseif (in_array($lmsStatus, ['t', 'terlambat', 'late'])) {
                                $todayAtt = ['status' => 'terlambat', 'status_label' => 'Terlambat', 'label' => 'Terlambat', 'color' => '#F59E0B', 'time' => $time, 'is_holiday' => false];
                            } elseif (in_array($lmsStatus, ['s', 'sakit'])) {
                                $todayAtt = ['status' => 'sakit', 'status_label' => 'Sakit', 'label' => 'Sakit', 'color' => '#38BDF8', 'time' => null, 'is_holiday' => false];
                            } elseif (in_array($lmsStatus, ['i', 'izin'])) {
                                $todayAtt = ['status' => 'izin', 'status_label' => 'Izin', 'label' => 'Izin', 'color' => '#60A5FA', 'time' => null, 'is_holiday' => false];
                            } else {
                                $todayAtt = ['status' => 'tidak_hadir', 'status_label' => 'Tidak Hadir', 'label' => 'Tidak Hadir', 'color' => '#EF4444', 'time' => null, 'is_holiday' => false];
                            }
                        }
                    }

                    if (! $todayAtt && $schoolHoliday) {
                        $holidayLabel = str_contains(strtolower($schoolHoliday->judul_pengumuman), 'semester') ? 'Libur Semester' : 'Libur Sekolah';
                        $todayAtt = ['status' => 'libur', 'status_label' => $holidayLabel, 'label' => $holidayLabel, 'holiday_title' => $schoolHoliday->judul_pengumuman, 'color' => '#F59E0B', 'time' => null, 'is_holiday' => true];
                    }

                    if (! $todayAtt && $isWeekend) {
                        $todayAtt = ['status' => 'libur', 'status_label' => 'Libur Akhir Pekan', 'label' => 'Libur', 'color' => '#F59E0B', 'time' => null, 'is_holiday' => true];
                    }

                    if (! $todayAtt) {
                        $todayAtt = ['status' => 'belum_presensi', 'status_label' => 'Belum Presensi', 'label' => 'Belum Presensi', 'color' => '#94A3B8', 'time' => null, 'is_holiday' => false];
                    }

                    $progType = strtolower(trim((string) (
                        $c->educationUnit?->program_type ??
                        $c->kelas?->unitPendidikan?->program_type ??
                        $c->program_type ??
                        'fullday'
                    )));
                    $isBoarding = $progType === 'boarding' || str_contains(strtolower((string) ($c->kelas?->nama_kelas ?? '')), 'asrama') || str_contains(strtolower((string) ($c->educationUnit?->name ?? '')), 'ponpes');

                    $c->program_type = $isBoarding ? 'boarding' : 'fullday';
                    $c->is_boarding = $isBoarding;

                    $c->unread_notifications_count = $notifCount;
                    $c->unread_count = $notifCount;
                    $c->pending_assignments_count = $pendingAssignments;
                    $c->attendance_status = $todayAtt['status_label'];
                    $c->attendance_today = $todayAtt;
                    $c->presensi_status = $todayAtt['status_label'];
                    $c->status_presensi = $todayAtt['status_label'];
                    $c->is_holiday = $todayAtt['is_holiday'];

                    return $c;
                });
            };

            if (! $parent) {
                if ($user && $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan'])) {
                    $adminChildren = Student::query()->with(['kelas.unitPendidikan', 'educationUnit'])->where('is_active', true)->take(3)->get();
                    return $enrichStudentsBatch($adminChildren);
                }

                // Fallback check if user owns a student directly
                $student = Student::query()->with(['kelas.unitPendidikan', 'educationUnit'])->where('user_id', $user->id)->first();
                return $student ? $enrichStudentsBatch(collect([$student])) : collect();
            }

            $children = $this->parentStudentsQuery($parent)
                ->with(['kelas.unitPendidikan', 'educationUnit'])
                ->where('is_active', true)
                ->orderBy('created_at', 'asc')
                ->get();

            return $enrichStudentsBatch($children);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function attendanceQr(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        abort_unless($student, 404, 'Data siswa tidak ditemukan.');

        $issued = $this->studentQr->issue($student);

        return response()->json([
            'success' => true,
            'data' => [
                'student_id' => $student->id,
                'qr_token' => $issued['raw_token'],
                'credential_id' => $issued['credential']->id,
            ],
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $this->getStudentContext($request);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Data siswa tidak ditemukan.',
            ], 404);
        }

        $activeAcademicYear = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->latest('start_date')->first();
        $activeSemester = Semester::query()->where('is_active', true)->first()
            ?? ($activeAcademicYear ? Semester::query()->where('academic_year_id', $activeAcademicYear->id)->first() : null);

        $todayDay = Str::lower(now()->locale('id')->isoFormat('dddd'));

        // Today's schedule
        $schedulesToday = ClassSchedule::query()
            ->with(['subject', 'teacher', 'employee'])
            ->where(fn ($q) => $q->where('class_id', $student->class_id)->orWhere('kelas_id', $student->kelas_id ?? $student->class_id))
            ->when($activeAcademicYear, fn ($q) => $q->where(fn ($sq) => $sq->whereNull('academic_year_id')->orWhere('academic_year_id', $activeAcademicYear->id)))
            ->when($activeSemester, fn ($q) => $q->where(fn ($sq) => $sq->whereNull('semester_id')->orWhere('semester_id', $activeSemester->id)))
            ->where('day_of_week', now()->dayOfWeekIso)
            ->orderBy('time_start')
            ->get()
            ->unique(function ($item) {
                return ($item->day_of_week ?? '').'-'.($item->time_start ?? '').'-'.($item->time_end ?? '').'-'.($item->subject_id ?? '');
            })->values();

        // Attendance status today (Akurat & Dinamis dari resolveStudentTodayAttendanceStatus)
        $todayAttendanceStatus = $this->resolveStudentTodayAttendanceStatus($student, now());
        $student->attendance_status = $todayAttendanceStatus['status_label'];
        $student->attendance_today = $todayAttendanceStatus;
        $student->presensi_status = $todayAttendanceStatus['status_label'];
        $student->status_presensi = $todayAttendanceStatus['status_label'];
        $student->is_holiday = $todayAttendanceStatus['is_holiday'];

        // Active assignments
        $studentClasses = array_values(array_unique(array_filter([$student->kelas_id, $student->class_id])));

        $activeAssignments = LmsPenugasan::query()
            ->with(['subject', 'teacher', 'materi'])
            ->whereIn('kelas_id', $studentClasses)
            ->where('is_published', true)
            ->where('deadline', '>=', now())
            ->orderBy('deadline', 'asc')
            ->get();

        // Latest grades: sinkronisasi 100% dengan endpoint grades() & GradesWorkspace
        $publishedReport = LmsRapor::query()
            ->with(['kelas.unitPendidikan', 'semester', 'tahunAjaran'])
            ->where('siswa_id', $student->id)
            ->whereIn('status_rapor', ['published', 'diterbitkan', 'terbit'])
            ->orderByDesc('tanggal_terbit')
            ->orderByDesc('created_at')
            ->first();

        $studentContext = $this->resolveStudentGradeAndLevel($student, $publishedReport?->kelas);
        $reportClass = $publishedReport?->kelas;
        $unitId = $reportClass?->unit_pendidikan_id ?? $student->unit_id;
        $level = $studentContext['level'];

        if ($publishedReport) {
            $rawGrades = StudentGrade::query()
                ->with(['subject', 'kelas.unitPendidikan', 'academicYear', 'semester'])
                ->where('student_id', $student->id)
                ->where('academic_year_id', $publishedReport->tahun_ajaran_id)
                ->where('semester_id', $publishedReport->semester_id)
                ->where(function ($q) use ($publishedReport, $student) {
                    $q->where('kelas_id', $publishedReport->kelas_id)
                      ->orWhere('kelas_id', $student->kelas_id);
                })
                ->whereNotNull('final_score')
                ->whereHas('subject', function ($query) use ($unitId, $level) {
                    $query->where('status', true);
                    if ($unitId) {
                        $query->where('unit_pendidikan_id', $unitId);
                    } elseif ($level) {
                        $query->where('jenjang', $level);
                    }
                })
                ->orderBy(
                    Subject::query()->select('urutan_tampil')->whereColumn('subjects.id', 'student_grades.subject_id')
                )
                ->get();

            if ($rawGrades->isEmpty()) {
                $rawGrades = StudentGrade::query()
                    ->with(['subject', 'kelas.unitPendidikan', 'academicYear', 'semester'])
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $publishedReport->tahun_ajaran_id)
                    ->where('semester_id', $publishedReport->semester_id)
                    ->whereNotNull('final_score')
                    ->orderBy(
                        Subject::query()->select('urutan_tampil')->whereColumn('subjects.id', 'student_grades.subject_id')
                    )
                    ->get();
            }
        } else {
            $rawGrades = StudentGrade::query()
                ->with(['subject', 'kelas.unitPendidikan', 'academicYear', 'semester'])
                ->where('student_id', $student->id)
                ->whereNotNull('final_score')
                ->orderByDesc('updated_at')
                ->take(10)
                ->get();
        }

        $latestGrades = $rawGrades->map(function (StudentGrade $grade) use ($publishedReport) {
            $kkm = $grade->subject?->kkm !== null ? round((float) $grade->subject->kkm) : null;
            $score = $grade->final_score !== null ? round((float) $grade->final_score) : null;
            $isPassed = $score !== null && $kkm !== null ? $score >= $kkm : null;
            $subjectName = $grade->subject?->nama_mapel ?? $grade->subject?->name;

            return [
                'id' => $grade->id,
                'subject_id' => $grade->subject_id,
                'subject_name' => $subjectName,
                'nama_mapel' => $subjectName,
                'final_score' => $score,
                'nilai_akhir' => $score,
                'score_assignment' => $grade->score_assignment,
                'score_quiz' => $grade->score_quiz,
                'score_mid' => $grade->score_mid,
                'score_final' => $grade->score_final,
                'grade_letter' => $grade->grade_letter,
                'predicate' => $grade->grade_letter,
                'predikat' => $grade->grade_letter,
                'is_passed' => $isPassed,
                'kkm' => $kkm,
                'notes' => $grade->notes,
                'academic_year' => $publishedReport?->tahunAjaran?->name ?? $grade->academicYear?->name,
                'semester' => $publishedReport?->semester?->name ?? $grade->semester?->name,
                'subject' => [
                    'id' => $grade->subject?->id,
                    'code' => $grade->subject?->kode_mapel ?? $grade->subject?->code,
                    'name' => $subjectName,
                    'unit_id' => $grade->subject?->unit_pendidikan_id,
                    'level' => $grade->subject?->jenjang,
                ],
            ];
        })->values();

        // Tahfizh progress
        $latestTahfizh = TahfizhDailyLog::query()
            ->where('student_id', $student->id)
            ->orderBy('record_date', 'desc')
            ->first();

        // tahfizh_daily_logs tidak punya kolom jumlah_ayat; hitung dari
        // rentang ayat hafalan secara efisien via SQL engine (fallback ke baris hafalan).
        $totalAyat = (int) TahfizhDailyLog::query()
            ->where('student_id', $student->id)
            ->sum(DB::raw('CASE WHEN hafalan_ayah_start IS NOT NULL AND hafalan_ayah_end IS NOT NULL AND hafalan_ayah_end >= hafalan_ayah_start THEN (hafalan_ayah_end - hafalan_ayah_start + 1) ELSE COALESCE(hafalan_baris, 0) END'));

        // Mutabaah today
        $mutabaahToday = MutabaahDailyHeader::query()
            ->with('details')
            ->where('student_id', $student->id)
            ->whereDate('activity_date', now()->toDateString())
            ->first();

        // Presensi stats & ringkasan kehadiran dinamis
        $attTotal = Attendance::query()->where('student_id', $student->id)->count();
        $attHadir = Attendance::query()->where('student_id', $student->id)->whereRaw('UPPER(status) IN (?, ?)', ['HADIR', 'PRESENT'])->count();
        $attendanceRate = $attTotal > 0 ? (int) round(($attHadir / $attTotal) * 100) : null;
        $latestAttendance = Attendance::query()->where('student_id', $student->id)->orderByDesc('attendance_date')->first();

        // Data tahfizh terakhir
        $latestTahfizhMeta = is_array($latestTahfizh?->metadata) ? $latestTahfizh->metadata : (is_string($latestTahfizh?->metadata) ? json_decode($latestTahfizh->metadata, true) : []);
        $latestTahfizhData = $latestTahfizh ? [
            'id' => $latestTahfizh->id,
            'surah_name' => $latestTahfizh->hafalan_surah_name ?: ($latestTahfizh->surah ?: null),
            'ayah_start' => $latestTahfizh->hafalan_ayah_start ?: $latestTahfizh->ayat_start,
            'ayah_end' => $latestTahfizh->hafalan_ayah_end ?: $latestTahfizh->ayat_end,
            'juz' => data_get($latestTahfizhMeta, 'juz') ?? ($latestTahfizh->calculated_juz ?? null),
            'status' => $latestTahfizh->status ?? null,
            'score' => data_get($latestTahfizhMeta, 'skor') ?? null,
            'quality' => data_get($latestTahfizhMeta, 'tajwid') ?? ($latestTahfizh->tajwid_label ?? ($latestTahfizh->status ?? null)),
            'kelancaran' => $latestTahfizh->kelancaran_label ?? (data_get($latestTahfizhMeta, 'kelancaran') ?? null),
            'tajwid' => $latestTahfizh->tajwid_label ?? data_get($latestTahfizhMeta, 'tajwid'),
            'makhraj' => $latestTahfizh->makhraj_label ?? data_get($latestTahfizhMeta, 'makhraj'),
            'record_date' => $latestTahfizh->record_date,
            'teacher_name' => $latestTahfizh->teacher_name ?? $latestTahfizh->employee?->nama_lengkap ?? null,
        ] : null;

        // Announcements: Sinkronisasi 100% dengan endpoint schoolInformation & SchoolInformationWorkspace
        $annRequest = new Request(['type' => 'announcement']);
        $annState = $this->schoolInformationState($request);
        $announcements = $this->schoolInformationQuery($annRequest, $student)
            ->orderByDesc('prioritas')
            ->orderByDesc('mulai_tampil')
            ->take(6)
            ->get()
            ->map(fn ($item) => $this->mapSchoolInformation($item, $annState))
            ->values();

        // Agenda Akademik Resmi Mendatang dari Database: Selaras dengan kalender akademik & schoolInformation
        $calRequest = new Request(['type' => 'calendar']);
        $calEvents = $this->schoolInformationQuery($calRequest, $student)
            ->where(function ($q) {
                $q->whereNull('selesai_tampil')->orWhere('selesai_tampil', '>=', now()->subDays(30));
            })
            ->orderBy('mulai_tampil', 'asc')
            ->get();

        $mappedCalendarEvents = collect();

        foreach ($calEvents as $ev) {
            $meta = $ev->data_tambahan ?? [];
            $rawStart = $meta['event']['start_at'] ?? ($meta['tanggal'] ?? $ev->mulai_tampil);
            $rawEnd = $meta['event']['end_at'] ?? ($ev->selesai_tampil ?? $rawStart);
            $startDate = $rawStart ? Carbon::parse($rawStart) : null;
            $endDate = $rawEnd ? Carbon::parse($rawEnd) : null;
            $startFormatted = $startDate ? $startDate->locale('id')->isoFormat('D MMM Y') : null;
            $endFormatted = $endDate ? $endDate->locale('id')->isoFormat('D MMM Y') : null;
            $dateLabel = $startFormatted && $endFormatted && $startFormatted !== $endFormatted 
                ? ($startFormatted . ' - ' . $endFormatted) 
                : $startFormatted;

            $category = $meta['kategori'] ?? 'kegiatan';
            $color = $meta['color'] ?? ($category === 'ujian' ? 'purple' : ($category === 'mulai_kbm' ? 'emerald' : ($category === 'libur_sekolah' ? 'rose' : ($category === 'libur_semester' ? 'amber' : 'cyan'))));

            $mappedCalendarEvents->push([
                'id' => (string) $ev->id,
                'title' => $ev->judul_pengumuman,
                'date_label' => $dateLabel,
                'start_date' => $startDate?->toDateString(),
                'end_date' => $endDate?->toDateString(),
                'category' => $category,
                'color' => $color,
                'notes' => $meta['ringkasan'] ?? ($meta['event']['keterangan'] ?? Str::limit(strip_tags($ev->isi_pengumuman), 80)),
            ]);
        }

        if ($activeSemester && $activeSemester->start_date && Carbon::parse($activeSemester->start_date)->gte(now()->subDays(14))) {
            $mappedCalendarEvents->push([
                'id' => 'sem-start-' . $activeSemester->id,
                'title' => 'Awal Semester ' . $activeSemester->name,
                'date_label' => Carbon::parse($activeSemester->start_date)->locale('id')->isoFormat('D MMM Y'),
                'start_date' => Carbon::parse($activeSemester->start_date)->toDateString(),
                'end_date' => Carbon::parse($activeSemester->start_date)->toDateString(),
                'category' => 'mulai_kbm',
                'color' => 'emerald',
                'notes' => 'Awal kegiatan belajar mengajar semester ' . $activeSemester->name,
            ]);
        }
        if ($activeSemester && $activeSemester->end_date && Carbon::parse($activeSemester->end_date)->gte(now()->startOfDay())) {
            $mappedCalendarEvents->push([
                'id' => 'sem-end-' . $activeSemester->id,
                'title' => 'Akhir Semester / Penilaian ' . $activeSemester->name,
                'date_label' => Carbon::parse($activeSemester->end_date)->locale('id')->isoFormat('D MMM Y'),
                'start_date' => Carbon::parse($activeSemester->end_date)->toDateString(),
                'end_date' => Carbon::parse($activeSemester->end_date)->toDateString(),
                'category' => 'terima_rapor',
                'color' => 'indigo',
                'notes' => 'Batas akhir evaluasi dan penerimaan rapor semester ' . $activeSemester->name,
            ]);
        }

        $upcomingCalendarEvents = $mappedCalendarEvents
            ->sortBy(function ($item) {
                return $item['start_date'] ?? '9999-12-31';
            })
            ->values()
            ->take(3);

        // Catatan Resmi Terakhir dari Guru / Wali Kelas untuk Orang Tua
        $latestNote = StudentNote::query()
            ->with('teacher')
            ->where('student_id', $student->id)
            ->where(fn ($sq) => $sq->where('visible_to_parent', true)->orWhereNull('visible_to_parent'))
            ->orderBy('date', 'desc')
            ->first();

        // Ringkasan Orang Tua (Tagihan, Pesan Baru, Perizinan, Agenda)
        $unpaidBills = StudentBill::query()
            ->where('student_id', $student->id)
            ->whereIn('status', ['unpaid', 'partially_paid', 'pending', 'belum_lunas'])
            ->get();
        $unpaidAmount = (float) $unpaidBills->sum(fn ($b) => max(0, ($b->amount ?? 0) - ($b->paid_amount ?? 0)));
        $latestBill = StudentBill::query()->where('student_id', $student->id)->latest('due_date')->first();

        $unreadNotesCount = StudentNote::query()
            ->where('student_id', $student->id)
            ->where(fn ($sq) => $sq->where('visible_to_parent', true)->orWhereNull('visible_to_parent'))
            ->whereNull('signed_at')
            ->count();
        if ($unreadNotesCount === 0) {
            $unreadNotesCount = StudentNote::query()
                ->where('student_id', $student->id)
                ->where(fn ($sq) => $sq->where('visible_to_parent', true)->orWhereNull('visible_to_parent'))
                ->count();
        }

        $permissionsCount = StudentAttendancePermission::query()
            ->where('student_id', $student->id)
            ->count();
        $dormitoryPermitsCount = DormitoryPermit::query()
            ->where('student_id', $student->id)
            ->count();
        $totalPermissions = $permissionsCount + $dormitoryPermitsCount;

        $agendaCount = $schedulesToday->count();

        // ── Rincian Mutaba'ah Terpadu & Terperinci (Shalat 5 Waktu, Sunnah, Tilawah, Adab) ──
        $activeMutabaahHeader = DB::table('mutabaah_daily_headers as h')
            ->join('mutabaah_daily_details as d', 'h.id', '=', 'd.daily_header_id')
            ->where('h.student_id', $student->id)
            ->whereNotNull('d.status_value')
            ->where('h.activity_date', '<=', now()->toDateString())
            ->select('h.id', 'h.activity_date', 'h.status', 'h.score', 'h.supervisor_notes', 'h.finalized_at')
            ->orderByDesc('h.activity_date')
            ->first();

        $mutabaahMonitoringDetails = null;
        if ($activeMutabaahHeader) {
            $rawDetails = DB::table('mutabaah_daily_details as d')
                ->join('mutabaah_agenda_items as a', 'a.id', '=', 'd.agenda_item_id')
                ->join('mutabaah_categories as c', 'c.id', '=', 'a.category_id')
                ->where('d.daily_header_id', $activeMutabaahHeader->id)
                ->select(
                    'a.name', 'a.code',
                    'c.id as category_id', 'c.name as category_name', 'c.code as category_code',
                    'c.icon as category_icon', 'c.color as category_color',
                    'd.status_value', 'd.numeric_value', 'd.text_value', 'd.notes'
                )
                ->orderBy('c.id')
                ->orderBy('a.id')
                ->get();

            $dynamicCategories = $rawDetails->groupBy('category_name')->map(function ($items, $catName) {
                $first = $items->first();
                $goodCount = $items->where('status_value', 'good')->count();
                $totalCount = $items->count();
                return [
                    'category_id' => $first->category_id ?? null,
                    'category_name' => $catName,
                    'category_code' => $first->category_code ?? null,
                    'category_icon' => $first->category_icon ?? 'Heart',
                    'category_color' => $first->category_color ?? '#10B981',
                    'good_count' => $goodCount,
                    'total_count' => $totalCount,
                    'rate' => $totalCount > 0 ? round(($goodCount / $totalCount) * 100) : 0,
                    'items' => $items->values(),
                ];
            })->values();

            $fardhuCodes = ['SUBUH', 'ZUHUR', 'ASHAR', 'MAGHRIB', 'ISYA'];
            $shalatFardhu = $rawDetails->filter(fn ($it) => in_array($it->code, $fardhuCodes) || str_contains(strtoupper($it->category_code ?? ''), 'FARDHU'))->values();
            $shalatSunnah = $rawDetails->filter(fn ($it) => ($it->category_code === 'SHALAT' && !in_array($it->code, $fardhuCodes)) || str_contains(strtoupper($it->category_code ?? ''), 'SUNNAH'))->values();
            $tilawahDzikir = $rawDetails->filter(fn ($it) => in_array($it->category_code, ['TILAWAH', 'TAHFIZH']) || str_contains(strtoupper($it->category_code ?? ''), 'QURAN'))->values();
            $adabKarakter = $rawDetails->filter(fn ($it) => !in_array($it->category_code, ['SHALAT', 'TILAWAH', 'TAHFIZH']))->values();

            $mutabaahMonitoringDetails = [
                'header_id' => $activeMutabaahHeader->id,
                'activity_date' => Carbon::parse($activeMutabaahHeader->activity_date)->locale('id')->isoFormat('dddd, D MMMM Y'),
                'raw_date' => $activeMutabaahHeader->activity_date,
                'score' => $activeMutabaahHeader->score !== null ? (float) $activeMutabaahHeader->score : null,
                'status' => $activeMutabaahHeader->status,
                'supervisor_notes' => $activeMutabaahHeader->supervisor_notes,
                'finalized_at' => $activeMutabaahHeader->finalized_at ? Carbon::parse($activeMutabaahHeader->finalized_at)->locale('id')->isoFormat('D MMMM Y HH:mm') : null,
                'categories' => $dynamicCategories,
                'dynamic_categories' => $dynamicCategories,
                'all_items' => $rawDetails->values(),
                'shalat_fardhu' => $shalatFardhu,
                'shalat_sunnah' => $shalatSunnah,
                'tilawah_dzikir' => $tilawahDzikir,
                'adab_karakter' => $adabKarakter,
            ];
        }

        // ── Rincian Target & Evaluasi Asatidz Tahfizh Terperinci ──
        $tahfizhService = app(\App\Services\TahfizhAchievementService::class);
        $tahfizhSummaryData = $tahfizhService->summary($student);
        $latestLogWithNote = TahfizhDailyLog::query()
            ->where('student_id', $student->id)
            ->whereNotNull('notes_teacher')
            ->where('notes_teacher', '!=', '')
            ->orderByDesc('record_date')
            ->first();

        $targetRow = DB::table('memorization_targets')
            ->where(function ($q) use ($student) {
                $q->where('student_id', $student->id)
                    ->orWhere('class_id', $student->class_id ?? $student->kelas_id);
            })
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->first();
        $targetMeta = $targetRow && $targetRow->metadata
            ? (is_string($targetRow->metadata) ? json_decode($targetRow->metadata, true) : (array) $targetRow->metadata)
            : [];
        $dbTargetAyat = (int) (data_get($targetMeta, 'target_ayat') ?: ($targetRow->target_lines ?? 0));
        $targetAyatFromService = (int) ($tahfizhSummaryData['target_ayah'] ?? 0);
        $tahfizhTargetAyat = (int) ($student->educationUnit?->tahfizh_target_ayat ?? ($dbTargetAyat > 0 ? $dbTargetAyat : $targetAyatFromService));

        $recentTahfizhLogs = TahfizhDailyLog::query()
            ->where('student_id', $student->id)
            ->orderByDesc('record_date')
            ->take(4)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'surah' => $l->hafalan_surah_name ?: ($l->surah ?: ($l->tilawah_text ?: ($l->murajaah_text ?: null))),
                'ayah_range' => ($l->hafalan_ayah_start && $l->hafalan_ayah_end)
                    ? 'Ayat ' . $l->hafalan_ayah_start . '-' . $l->hafalan_ayah_end
                    : ($l->hafalan_ayah_start ? 'Ayat ' . $l->hafalan_ayah_start : null),
                'quality' => $l->tajwid_label ?? $l->status ?? null,
                'teacher_name' => $l->teacher_name ?? $l->employee?->nama_lengkap ?? null,
                'date' => $l->record_date ? Carbon::parse($l->record_date)->locale('id')->isoFormat('D MMM Y') : null,
                'notes' => $l->notes_teacher ?: null,
            ]);

        $actualAyat = $tahfizhSummaryData['validated_unique_ayah'] ?? $totalAyat;
        $evalPct = $tahfizhSummaryData['achievement_percentage'] ?? ($tahfizhTargetAyat > 0 ? min(100, max(0, (int) round(($actualAyat / $tahfizhTargetAyat) * 100))) : null);
        $tahfizhEvaluationDetails = [
            'total_ayat_tercapai' => $actualAyat,
            'target_ayat' => $tahfizhTargetAyat ?: null,
            'persentase' => $evalPct,
            'surah_tuntas' => $tahfizhSummaryData['completed_surahs'] ?? [],
            'juz_aktif' => $tahfizhSummaryData['juz_with_validated_ayah'] ?? [],
            'catatan_pembimbing' => $latestLogWithNote?->notes_teacher ?? null,
            'guru_pembimbing' => $latestLogWithNote?->teacher_name ?? $latestTahfizhData['teacher_name'] ?? null,
            'tanggal_evaluasi' => $latestLogWithNote?->record_date ? Carbon::parse($latestLogWithNote->record_date)->locale('id')->isoFormat('D MMMM Y') : now()->locale('id')->isoFormat('D MMMM Y'),
            'recent_logs' => $recentTahfizhLogs,
        ];

        // ── Agregasi Multi-Periode Pemantauan Terpadu (Harian, Mingguan, Bulanan, Semester) ──
        if ($mutabaahMonitoringDetails && $activeMutabaahHeader) {
            $refDate = Carbon::parse($activeMutabaahHeader->activity_date);

            $calcCategoryStats = function ($headerIds) {
                if (empty($headerIds) || count($headerIds) === 0) {
                    return [
                        'fardhu' => ['rate' => 0, 'good' => 0, 'total' => 0, 'label' => '-'],
                        'sunnah' => ['rate' => 0, 'good' => 0, 'total' => 0, 'label' => '-'],
                        'tilawah' => ['rate' => 0, 'good' => 0, 'total' => 0, 'label' => '-'],
                        'adab' => ['rate' => 0, 'good' => 0, 'total' => 0, 'label' => '-'],
                        'categories' => [],
                    ];
                }
                $details = DB::table('mutabaah_daily_details as d')
                    ->join('mutabaah_agenda_items as a', 'a.id', '=', 'd.agenda_item_id')
                    ->join('mutabaah_categories as c', 'c.id', '=', 'a.category_id')
                    ->whereIn('d.daily_header_id', $headerIds)
                    ->select(
                        'a.code', 'd.status_value',
                        'c.id as category_id', 'c.name as category_name', 'c.code as category_code',
                        'c.icon as category_icon', 'c.color as category_color'
                    )
                    ->get();

                $categories = $details->groupBy('category_name')->map(function ($items, $catName) {
                    $first = $items->first();
                    $good = $items->where('status_value', 'good')->count();
                    $total = $items->count();
                    $rate = $total > 0 ? round(($good / $total) * 100) : 0;
                    $label = $total === 0 ? '-' : ($rate >= 90 ? 'Sangat Tertib' : ($rate >= 75 ? 'Tertib' : ($rate >= 60 ? 'Cukup' : 'Perlu Pendampingan')));
                    return [
                        'category_id' => $first->category_id ?? null,
                        'name' => $catName,
                        'code' => $first->category_code ?? null,
                        'icon' => $first->category_icon ?? 'Heart',
                        'color' => $first->category_color ?? '#10B981',
                        'rate' => $rate,
                        'good' => $good,
                        'total' => $total,
                        'label' => $label,
                    ];
                })->values();

                $fardhuCodes = ['SUBUH', 'ZUHUR', 'ASHAR', 'MAGHRIB', 'ISYA'];
                $fardhu = $details->filter(fn ($d) => in_array($d->code, $fardhuCodes) || str_contains(strtoupper($d->category_code ?? ''), 'FARDHU'));
                $fardhuGood = $fardhu->where('status_value', 'good')->count();
                $fardhuTotal = $fardhu->count();
                $fardhuRate = $fardhuTotal > 0 ? round(($fardhuGood / $fardhuTotal) * 100) : 0;

                $sunnah = $details->filter(fn ($d) => ($d->category_code === 'SHALAT' && !in_array($d->code, $fardhuCodes)) || str_contains(strtoupper($d->category_code ?? ''), 'SUNNAH'));
                $sunnahGood = $sunnah->where('status_value', 'good')->count();
                $sunnahTotal = $sunnah->count();
                $sunnahRate = $sunnahTotal > 0 ? round(($sunnahGood / $sunnahTotal) * 100) : 0;

                $tilawah = $details->filter(fn ($d) => in_array($d->category_code, ['TILAWAH', 'TAHFIZH']) || str_contains(strtoupper($d->category_code ?? ''), 'QURAN'));
                $tilawahGood = $tilawah->where('status_value', 'good')->count();
                $tilawahTotal = $tilawah->count();
                $tilawahRate = $tilawahTotal > 0 ? round(($tilawahGood / $tilawahTotal) * 100) : 0;

                $adab = $details->filter(fn ($d) => !in_array($d->category_code, ['SHALAT', 'TILAWAH', 'TAHFIZH']));
                $adabGood = $adab->where('status_value', 'good')->count();
                $adabTotal = $adab->count();
                $adabRate = $adabTotal > 0 ? round(($adabGood / $adabTotal) * 100) : 0;

                return [
                    'categories' => $categories,
                    'fardhu' => ['rate' => $fardhuRate, 'good' => $fardhuGood, 'total' => $fardhuTotal, 'label' => $fardhuTotal > 0 ? ($fardhuRate >= 90 ? 'Sangat Tertib' : 'Tertib') : '-'],
                    'sunnah' => ['rate' => $sunnahRate, 'good' => $sunnahGood, 'total' => $sunnahTotal, 'label' => $sunnahTotal > 0 ? ($sunnahRate >= 80 ? 'Rutin Terjaga' : 'Cukup Rutin') : '-'],
                    'tilawah' => ['rate' => $tilawahRate, 'good' => $tilawahGood, 'total' => $tilawahTotal, 'label' => $tilawahTotal > 0 ? ($tilawahRate >= 85 ? 'Aktif Istiqamah' : 'Aktif') : '-'],
                    'adab' => ['rate' => $adabRate, 'good' => $adabGood, 'total' => $adabTotal, 'label' => $adabTotal > 0 ? ($adabRate >= 90 ? 'Karakter Unggul' : 'Baik') : '-'],
                ];
            };

            $buildPeriodItems = function ($headerIds) {
                if (empty($headerIds) || count($headerIds) === 0) {
                    return [
                        'categories' => [],
                        'all_items' => [],
                        'shalat_fardhu' => [],
                        'shalat_sunnah' => [],
                        'tilawah_dzikir' => [],
                        'adab_karakter' => [],
                    ];
                }

                $rawDetails = DB::table('mutabaah_daily_details as d')
                    ->join('mutabaah_agenda_items as a', 'a.id', '=', 'd.agenda_item_id')
                    ->join('mutabaah_categories as c', 'c.id', '=', 'a.category_id')
                    ->whereIn('d.daily_header_id', $headerIds)
                    ->select(
                        'a.id as item_id', 'a.name', 'a.code',
                        'c.id as category_id', 'c.name as category_name', 'c.code as category_code',
                        'c.icon as category_icon', 'c.color as category_color',
                        'd.status_value'
                    )
                    ->get();

                $categoriesResult = [];
                $groupedByCategory = $rawDetails->groupBy('category_name');

                foreach ($groupedByCategory as $catName => $catItems) {
                    $first = $catItems->first();
                    $groupedByItem = $catItems->groupBy('code');
                    $formattedItems = [];

                    foreach ($groupedByItem as $code => $items) {
                        $name = $items->first()->name ?? $code;
                        $goodCount = $items->where('status_value', 'good')->count();
                        $itemTotal = $items->count();
                        $rate = $itemTotal > 0 ? round(($goodCount / $itemTotal) * 100) : 0;

                        if ($rate >= 95) {
                            $statusLabel = 'Istiqamah';
                            $statusColor = 'emerald';
                        } elseif ($rate >= 80) {
                            $statusLabel = 'Rutin Terjaga';
                            $statusColor = 'teal';
                        } elseif ($rate >= 60) {
                            $statusLabel = 'Cukup Rutin';
                            $statusColor = 'amber';
                        } else {
                            $statusLabel = 'Perlu Evaluasi';
                            $statusColor = 'rose';
                        }

                        $formattedItems[] = [
                            'code' => $code,
                            'name' => $name,
                            'good_count' => $goodCount,
                            'total_days' => $itemTotal,
                            'rate' => (int) $rate,
                            'status_label' => $statusLabel,
                            'status_color' => $statusColor,
                        ];
                    }

                    $catGood = $catItems->where('status_value', 'good')->count();
                    $catTotal = $catItems->count();
                    $catRate = $catTotal > 0 ? round(($catGood / $catTotal) * 100) : 0;

                    $categoriesResult[] = [
                        'category_id' => $first->category_id,
                        'category_name' => $catName,
                        'category_code' => $first->category_code,
                        'category_icon' => $first->category_icon,
                        'category_color' => $first->category_color,
                        'rate' => $catRate,
                        'good_count' => $catGood,
                        'total_count' => $catTotal,
                        'items' => $formattedItems,
                    ];
                }

                $allItems = [];
                foreach ($categoriesResult as $cat) {
                    foreach ($cat['items'] as $item) {
                        $allItems[] = array_merge($item, [
                            'category_name' => $cat['category_name'],
                            'category_code' => $cat['category_code'],
                            'category_icon' => $cat['category_icon'],
                            'category_color' => $cat['category_color'],
                        ]);
                    }
                }

                $fardhuCodes = ['SUBUH', 'ZUHUR', 'ASHAR', 'MAGHRIB', 'ISYA'];
                $shalatFardhu = collect($allItems)->filter(fn ($it) => in_array($it['code'], $fardhuCodes) || str_contains(strtoupper($it['category_code'] ?? ''), 'FARDHU'))->values()->all();
                $shalatSunnah = collect($allItems)->filter(fn ($it) => ($it['category_code'] === 'SHALAT' && !in_array($it['code'], $fardhuCodes)) || str_contains(strtoupper($it['category_code'] ?? ''), 'SUNNAH'))->values()->all();
                $tilawahDzikir = collect($allItems)->filter(fn ($it) => in_array($it['category_code'], ['TILAWAH', 'TAHFIZH']) || str_contains(strtoupper($it['category_code'] ?? ''), 'QURAN'))->values()->all();
                $adabKarakter = collect($allItems)->filter(fn ($it) => !in_array($it['category_code'], ['SHALAT', 'TILAWAH', 'TAHFIZH']))->values()->all();

                return [
                    'categories' => $categoriesResult,
                    'all_items' => $allItems,
                    'shalat_fardhu' => $shalatFardhu,
                    'shalat_sunnah' => $shalatSunnah,
                    'tilawah_dzikir' => $tilawahDzikir,
                    'adab_karakter' => $adabKarakter,
                ];
            };

            $formatTahfizhLogs = function ($tahfizhRecords) {
                return $tahfizhRecords->map(function ($l) {
                    $metadata = is_string($l->metadata) ? json_decode($l->metadata, true) : (is_array($l->metadata) ? $l->metadata : []);
                    $teacherName = ($l->teacher_id ? DB::table('teachers')->where('id', $l->teacher_id)->value('full_name') : null)
                        ?? ($l->teacher_id ? DB::table('employees')->where('id', $l->teacher_id)->value('nama_lengkap') : null)
                        ?? ($l->teacher_name ?? null);

                    $surah = $l->hafalan_surah_name ?: (($l->surah ?? null) ?: ($l->tilawah_text ?: ($l->murajaah_text ?: null)));
                    $ayahRange = ($l->hafalan_ayah_start && $l->hafalan_ayah_end)
                        ? 'Ayat ' . $l->hafalan_ayah_start . ' - ' . $l->hafalan_ayah_end
                        : ($l->hafalan_ayah_start ? 'Ayat ' . $l->hafalan_ayah_start : null);

                    $scoreVal = isset($l->score) ? (float) $l->score : (isset($metadata['skor']) ? (float) $metadata['skor'] : null);
                    $qualityVal = !empty($metadata['tajwid']) ? 'Predikat ' . $metadata['tajwid'] : ($l->status ?? null);

                    return [
                        'id' => $l->id,
                        'surah' => $surah,
                        'ayah_range' => $ayahRange,
                        'quality' => $qualityVal,
                        'score' => $scoreVal,
                        'teacher_name' => $teacherName,
                        'date' => $l->record_date ? Carbon::parse($l->record_date)->locale('id')->isoFormat('D MMM Y') : null,
                        'notes' => $l->notes_teacher ?: ($l->murajaah_status ? 'Murajaah: ' . $l->murajaah_status : null),
                    ];
                })->values();
            };

            // 1. Weekly (7 Days)
            $weeklyStart = $refDate->copy()->subDays(6)->toDateString();
            $weeklyEnd = $refDate->toDateString();
            $weeklyHeaders = DB::table('mutabaah_daily_headers')
                ->where('student_id', $student->id)
                ->whereBetween('activity_date', [$weeklyStart, $weeklyEnd])
                ->select('id', 'activity_date', 'score', 'status')
                ->orderBy('activity_date')
                ->get();
            $weeklyScores = $weeklyHeaders->whereNotNull('score');
            $weeklyAvg = $weeklyScores->count() > 0 ? round($weeklyScores->avg('score'), 1) : null;
            $weeklyTrend = $weeklyHeaders->map(fn ($h) => [
                'date' => Carbon::parse($h->activity_date)->locale('id')->isoFormat('D MMM'),
                'day' => Carbon::parse($h->activity_date)->locale('id')->isoFormat('ddd'),
                'score' => (float) ($h->score ?? 0),
            ])->values();
            $weeklyTahfizh = DB::table('tahfizh_daily_logs')
                ->where('student_id', $student->id)
                ->whereBetween('record_date', [$weeklyStart, $weeklyEnd])
                ->orderByDesc('record_date')
                ->get();
            $weeklyCatStats = $calcCategoryStats($weeklyHeaders->pluck('id'));
            $weeklyItems = $buildPeriodItems($weeklyHeaders->pluck('id'));
            $weeklyTahfizhLogs = $formatTahfizhLogs($weeklyTahfizh);

            // 2. Monthly (30 Days)
            $monthlyStart = $refDate->copy()->subDays(29)->toDateString();
            $monthlyEnd = $refDate->toDateString();
            $monthlyHeaders = DB::table('mutabaah_daily_headers')
                ->where('student_id', $student->id)
                ->whereBetween('activity_date', [$monthlyStart, $monthlyEnd])
                ->select('id', 'activity_date', 'score', 'status')
                ->orderBy('activity_date')
                ->get();
            $monthlyScores = $monthlyHeaders->whereNotNull('score');
            $monthlyAvg = $monthlyScores->count() > 0 ? round($monthlyScores->avg('score'), 1) : null;
            $monthlyTahfizh = DB::table('tahfizh_daily_logs')
                ->where('student_id', $student->id)
                ->whereBetween('record_date', [$monthlyStart, $monthlyEnd])
                ->orderByDesc('record_date')
                ->get();
            $monthlyCatStats = $calcCategoryStats($monthlyHeaders->pluck('id'));
            $monthlyItems = $buildPeriodItems($monthlyHeaders->pluck('id'));
            $monthlyTahfizhLogs = $formatTahfizhLogs($monthlyTahfizh);

            // 3. Semester (Dinamis dari Database)
            $activeSem = DB::table('semesters')->where('is_active', true)->first();
            $semesterStart = $activeSem?->start_date ?? now()->startOfYear()->toDateString();
            $semesterEnd = $activeSem?->end_date ?? now()->endOfYear()->toDateString();
            $semesterLabel = $activeSem?->name ?? null;
            $semesterRange = Carbon::parse($semesterStart)->locale('id')->isoFormat('MMMM Y') . ' - ' . Carbon::parse($semesterEnd)->locale('id')->isoFormat('MMMM Y');

            $semesterHeaders = DB::table('mutabaah_daily_headers')
                ->where('student_id', $student->id)
                ->whereBetween('activity_date', [$semesterStart, $semesterEnd])
                ->select('id', 'activity_date', 'score', 'status')
                ->get();
            $semesterScores = $semesterHeaders->whereNotNull('score');
            $semesterAvg = $semesterScores->count() > 0 ? round($semesterScores->avg('score'), 1) : null;
            $semesterTahfizh = DB::table('tahfizh_daily_logs')
                ->where('student_id', $student->id)
                ->whereBetween('record_date', [$semesterStart, $semesterEnd])
                ->orderByDesc('record_date')
                ->get();
            $semesterCatStats = $calcCategoryStats($semesterHeaders->pluck('id'));
            $semesterItems = $buildPeriodItems($semesterHeaders->pluck('id'));
            $semesterTahfizhLogs = $formatTahfizhLogs($semesterTahfizh);

            $mutabaahMonitoringDetails['periods'] = [
                'harian' => [
                    'key' => 'harian',
                    'label' => 'Harian',
                    'badge' => 'Catatan Harian',
                    'sub_label' => 'Hari Ini / Terakhir: ' . Carbon::parse($activeMutabaahHeader->activity_date)->locale('id')->isoFormat('D MMM Y'),
                    'date_display' => Carbon::parse($activeMutabaahHeader->activity_date)->locale('id')->isoFormat('dddd, D MMMM Y'),
                    'score' => $activeMutabaahHeader->score !== null ? (float) $activeMutabaahHeader->score : null,
                    'status' => $activeMutabaahHeader->status,
                    'categories' => $dynamicCategories,
                    'dynamic_categories' => $dynamicCategories,
                    'all_items' => $rawDetails->values(),
                    'shalat_fardhu' => $shalatFardhu,
                    'shalat_sunnah' => $shalatSunnah,
                    'tilawah_dzikir' => $tilawahDzikir,
                    'adab_karakter' => $adabKarakter,
                    'tahfizh_latest' => $latestTahfizhData,
                ],
                'mingguan' => [
                    'key' => 'mingguan',
                    'label' => 'Mingguan',
                    'badge' => '7 Hari Terakhir',
                    'sub_label' => Carbon::parse($weeklyStart)->locale('id')->isoFormat('D MMM') . ' - ' . Carbon::parse($weeklyEnd)->locale('id')->isoFormat('D MMM Y'),
                    'date_range' => Carbon::parse($weeklyStart)->locale('id')->isoFormat('D MMM') . ' - ' . Carbon::parse($weeklyEnd)->locale('id')->isoFormat('D MMM Y'),
                    'score' => $weeklyAvg,
                    'days_active' => $weeklyHeaders->count(),
                    'days_total' => 7,
                    'daily_trend' => $weeklyTrend,
                    'category_stats' => $weeklyCatStats,
                    'categories' => $weeklyItems['categories'],
                    'dynamic_categories' => $weeklyItems['categories'],
                    'all_items' => $weeklyItems['all_items'],
                    'shalat_fardhu' => $weeklyItems['shalat_fardhu'],
                    'shalat_sunnah' => $weeklyItems['shalat_sunnah'],
                    'tilawah_dzikir' => $weeklyItems['tilawah_dzikir'],
                    'adab_karakter' => $weeklyItems['adab_karakter'],
                    'tahfizh_summary' => [
                        'total_logs' => $weeklyTahfizh->count(),
                        'latest_surah' => $weeklyTahfizh->first()?->hafalan_surah_name ?? ($latestTahfizhData['surah_name'] ?? null),
                        'surah_list' => $weeklyTahfizh->pluck('hafalan_surah_name')->filter()->unique()->values(),
                        'ayat_tercapai' => $weeklyTahfizh->sum(fn ($l) => max(1, abs(($l->hafalan_ayah_end ?? 1) - ($l->hafalan_ayah_start ?? 1) + 1))),
                        'avg_score' => $weeklyTahfizh->avg('score') ? round($weeklyTahfizh->avg('score'), 1) : null,
                    ],
                    'tahfizh_logs' => $weeklyTahfizhLogs,
                ],
                'bulanan' => [
                    'key' => 'bulanan',
                    'label' => 'Bulanan',
                    'badge' => '30 Hari Terakhir',
                    'sub_label' => Carbon::parse($monthlyStart)->locale('id')->isoFormat('D MMM') . ' - ' . Carbon::parse($monthlyEnd)->locale('id')->isoFormat('D MMM Y'),
                    'date_range' => Carbon::parse($monthlyStart)->locale('id')->isoFormat('D MMM') . ' - ' . Carbon::parse($monthlyEnd)->locale('id')->isoFormat('D MMM Y'),
                    'score' => $monthlyAvg,
                    'days_active' => $monthlyHeaders->count(),
                    'days_total' => Carbon::parse($monthlyStart)->diffInDays(Carbon::parse($monthlyEnd)) + 1,
                    'category_stats' => $monthlyCatStats,
                    'categories' => $monthlyItems['categories'],
                    'dynamic_categories' => $monthlyItems['categories'],
                    'all_items' => $monthlyItems['all_items'],
                    'shalat_fardhu' => $monthlyItems['shalat_fardhu'],
                    'shalat_sunnah' => $monthlyItems['shalat_sunnah'],
                    'tilawah_dzikir' => $monthlyItems['tilawah_dzikir'],
                    'adab_karakter' => $monthlyItems['adab_karakter'],
                    'tahfizh_summary' => [
                        'total_logs' => $monthlyTahfizh->count(),
                        'surah_list' => $monthlyTahfizh->pluck('hafalan_surah_name')->filter()->unique()->values(),
                        'ayat_tercapai' => $monthlyTahfizh->sum(fn ($l) => max(1, abs(($l->hafalan_ayah_end ?? 1) - ($l->hafalan_ayah_start ?? 1) + 1))),
                        'avg_score' => $monthlyTahfizh->avg('score') ? round($monthlyTahfizh->avg('score'), 1) : null,
                    ],
                    'tahfizh_logs' => $monthlyTahfizhLogs,
                ],
                'semester' => [
                    'key' => 'semester',
                    'label' => 'Semester',
                    'badge' => $semesterLabel,
                    'semester_name' => $semesterLabel,
                    'sub_label' => $semesterRange,
                    'date_range' => $semesterRange,
                    'score' => $semesterAvg,
                    'days_active' => $semesterHeaders->count(),
                    'category_stats' => $semesterCatStats,
                    'categories' => $semesterItems['categories'],
                    'dynamic_categories' => $semesterItems['categories'],
                    'all_items' => $semesterItems['all_items'],
                    'shalat_fardhu' => $semesterItems['shalat_fardhu'],
                    'shalat_sunnah' => $semesterItems['shalat_sunnah'],
                    'tilawah_dzikir' => $semesterItems['tilawah_dzikir'],
                    'adab_karakter' => $semesterItems['adab_karakter'],
                    'tahfizh_summary' => [
                        'target_ayat' => $tahfizhEvaluationDetails['target_ayat'],
                        'total_ayat' => $tahfizhEvaluationDetails['total_ayat_tercapai'],
                        'persentase' => $tahfizhEvaluationDetails['persentase'],
                        'surah_tuntas' => $tahfizhEvaluationDetails['surah_tuntas'],
                        'total_logs' => $semesterTahfizh->count(),
                    ],
                    'tahfizh_logs' => $semesterTahfizhLogs,
                ],
            ];
        }

        $parentSummary = [
            'tagihan' => [
                'amount' => $unpaidAmount,
                'count' => $unpaidBills->count(),
                'formatted' => $unpaidAmount > 0 
                    ? 'Rp ' . number_format($unpaidAmount, 0, ',', '.') 
                    : ($latestBill ? 'Rp ' . number_format((float) $latestBill->amount, 0, ',', '.') : 'Rp 0'),
                'is_paid' => $unpaidAmount == 0,
                'status_label' => $unpaidAmount == 0 ? 'Lunas' : 'Belum Lunas',
            ],
            'pesan_baru' => [
                'count' => $unreadNotesCount,
                'label' => 'Pesan Baru',
            ],
            'perizinan' => [
                'count' => $totalPermissions,
                'label' => 'Perizinan',
            ],
            'agenda' => [
                'count' => $agendaCount,
                'label' => 'Agenda',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'student' => $student,
                'parent_summary' => $parentSummary,
                'academic_context' => [
                    'academic_year' => $activeAcademicYear?->name ?? null,
                    'semester' => $activeSemester?->name ?? null,
                    'date' => now()->translatedFormat('l, d F Y'),
                ],
                'attendance_today' => $todayAttendanceStatus['status_label'],
                'attendance_today_detail' => $todayAttendanceStatus,
                'latest_tahfizh' => $latestTahfizhData,
                'latest_note' => $latestNote ? [
                    'id' => $latestNote->id,
                    'title' => $latestNote->title,
                    'content' => $latestNote->content,
                    'date' => $latestNote->date ? Carbon::parse($latestNote->date)->isoFormat('dddd, D MMMM Y') : null,
                    'raw_date' => $latestNote->date,
                    'teacher_name' => $latestNote->teacher?->name ?? $latestNote->teacher?->full_name ?? null,
                ] : null,
                'kpi' => [
                    'attendance_rate' => $attendanceRate,
                    'attendance_summary' => [
                        'rate' => $attendanceRate,
                        'total_days' => $attTotal,
                        'present_days' => $attHadir,
                        'latest_date' => $latestAttendance?->attendance_date ? Carbon::parse($latestAttendance->attendance_date)->isoFormat('D MMM Y') : null,
                        'latest_status' => $latestAttendance?->status ? ucfirst(strtolower($latestAttendance->status)) : null,
                    ],
                    'schedules_today_count' => $schedulesToday->count(),
                    'active_assignments_count' => $activeAssignments->count(),
                    'total_tahfizh_ayat' => $totalAyat,
                    'latest_tahfizh_surah' => (function () use ($latestTahfizh) {
                        if (! $latestTahfizh) return null;
                        $surahName = $latestTahfizh->hafalan_surah_name ?: ($latestTahfizh->surah ?: ($latestTahfizh->tilawah_text ?: ($latestTahfizh->murajaah_text ?: null)));
                        $start = $latestTahfizh->hafalan_ayah_start ?: $latestTahfizh->ayat_start;
                        $end = $latestTahfizh->hafalan_ayah_end ?: $latestTahfizh->ayat_end;
                        $range = $start ? ' (Ayat '.$start.($end && $end != $start ? '-'.$end : '').')' : '';
                        return $surahName ? ($surahName.$range) : null;
                    })(),
                    'mutabaah_status' => $mutabaahToday ? $mutabaahToday->status : null,
                ],
                'pending_home_murajaah' => ($pendingHome = TahfizhDailyLog::where('student_id', $student->id)
                ->where('murajaah_required', true)
                ->whereIn('murajaah_status', ['pending_home', 'submitted_by_parent'])
                ->orderByDesc('record_date')
                ->first()) ? [
                'id' => $pendingHome->id,
                'surah_number' => $pendingHome->murajaah_required_surah_number,
                'surah_name' => $pendingHome->murajaah_required_surah_name,
                'ayat_start' => $pendingHome->murajaah_required_ayah_start,
                'ayat_end' => $pendingHome->murajaah_required_ayah_end,
                'notes_teacher' => $pendingHome->notes_teacher,
                'status' => $pendingHome->murajaah_status,
                'record_date' => $pendingHome->record_date,
            ] : null,
            'tahfizh_target' => (function () use ($student, $totalAyat, $tahfizhSummaryData, $tahfizhTargetAyat) {
                    $targetRow = DB::table('memorization_targets')->where('student_id', $student->id)->whereNull('deleted_at')->latest('target_date')->first();
                    $targetMeta = $targetRow?->metadata ?? [];
                    if (is_string($targetMeta)) $targetMeta = json_decode($targetMeta, true) ?: [];
                    $targetAyatVal = (int) (data_get($targetMeta, 'target_ayat') ?: ($targetRow->target_lines ?? 0));
                    $targetSurahVal = data_get($targetMeta, 'target_surah') ?: ($targetRow->target_name ?? null);
                    $finalTargetAyat = $targetAyatVal ?: ($tahfizhTargetAyat > 0 ? $tahfizhTargetAyat : (int) ($tahfizhSummaryData['target_ayah'] ?? 0));
                    $targetJuzLabel = data_get($targetMeta, 'target_juz') ?: ($tahfizhSummaryData['target_juz'] ?? null);
                    $targetNameLabel = $targetSurahVal ?: ($targetJuzLabel && $finalTargetAyat > 0 ? "Target {$targetJuzLabel} ({$finalTargetAyat} Ayat)" : ($finalTargetAyat > 0 ? "Target {$finalTargetAyat} Ayat" : null));
                    return [
                        'surah_target' => $targetNameLabel,
                        'target_ayat' => $finalTargetAyat > 0 ? $finalTargetAyat : ($totalAyat > 0 ? $totalAyat : null),
                    ];
                })(),
                'schedules_today' => $schedulesToday,
                'active_assignments' => $activeAssignments,
                'latest_grades' => $latestGrades,
                'announcements' => $announcements,
                'upcoming_calendar_events' => $upcomingCalendarEvents,
                'mutabaah_monitoring' => $mutabaahMonitoringDetails,
                'tahfizh_evaluation_details' => $tahfizhEvaluationDetails,
            ],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $student->load(['kelas', 'educationUnit', 'parents']),
        ]);
    }

    public function schedules(Request $request): JsonResponse
    {
        // WM-02 PATCH: removed arbitrary Student::first() fallback to prevent cross-parent schedule leakage.
        // If getStudentContext returns null (foreign/invalid child_id), reject with 404.
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $academicYearId = $request->query('academic_year_id') ?: $request->query('academic_year');
        $semesterId = $request->query('semester_id') ?: $request->query('semester');

        // Otomatis prioritaskan semester dan tahun ajaran aktif jika tidak dispesifikasikan secara eksplisit
        if (! $semesterId) {
            $activeSemester = Semester::query()->where('is_active', true)->first();
            $semesterId = $activeSemester?->id;
        }
        if (! $academicYearId) {
            $activeYear = AcademicYear::query()->where('is_active', true)->first();
            $academicYearId = $activeYear?->id;
        }

        $subjectId = $request->query('subject_id') ?: $request->query('subject');
        $classIds = array_values(array_unique(array_filter([$student->class_id, $student->kelas_id])));
        $hasExplicitSemester = $request->filled('semester_id') || $request->filled('semester');
        $schedules = ClassSchedule::query()
            ->with(['subject', 'teacher', 'employee'])
            ->when($classIds, fn ($q) => $q->where(fn ($sub) => $sub->whereIn('class_id', $classIds)->orWhereIn('kelas_id', $classIds)))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->when($academicYearId, fn ($q) => $q->where(fn ($sq) => $sq->whereNull('academic_year_id')->orWhere('academic_year_id', $academicYearId)))
            ->when($semesterId, fn ($q) => $q->where(fn ($sq) => $sq->whereNull('semester_id')->orWhere('semester_id', $semesterId)))
            ->orderBy('day_of_week')
            ->orderBy('time_start')
            ->get();

        // Toleransi transisi semester: Jika di semester aktif belum ada entri jadwal khusus,
        // muat jadwal kelas pada tahun ajaran aktif agar jadwal tidak kosong
        if ($schedules->isEmpty() && $semesterId && ! $hasExplicitSemester) {
            $schedules = ClassSchedule::query()
                ->with(['subject', 'teacher', 'employee'])
                ->when($classIds, fn ($q) => $q->where(fn ($sub) => $sub->whereIn('class_id', $classIds)->orWhereIn('kelas_id', $classIds)))
                ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
                ->when($academicYearId, fn ($q) => $q->where(fn ($sq) => $sq->whereNull('academic_year_id')->orWhere('academic_year_id', $academicYearId)))
                ->orderBy('day_of_week')
                ->orderBy('time_start')
                ->get();
        }

        // Bersihkan data kembar jika ada duplikasi entri jadwal (hari, jam mulai, jam selesai, mapel yang sama)
        $schedules = $schedules->unique(function ($item) {
            return ($item->day_of_week ?? '').'-'.($item->time_start ?? '').'-'.($item->time_end ?? '').'-'.($item->subject_id ?? '');
        })->values();

        $dateParam = $request->query('date');
        $selectedDate = $dateParam ? Carbon::parse($dateParam) : now();
        $dateStr = $selectedDate->format('Y-m-d');
        $todayStr = now()->format('Y-m-d');
        $dayOfWeekIso = $selectedDate->dayOfWeekIso; // 1 = Senin, ..., 7 = Minggu
        $currentTime = now()->format('H:i:s');

        $isToday = ($dateStr === $todayStr);
        $isPastDay = ($dateStr < $todayStr);
        $isFutureDay = ($dateStr > $todayStr);

        // Presensi mapel siswa pada tanggal terpilih
        $attendances = LmsPresensi::where('siswa_id', $student->id)
            ->whereDate('tanggal', $dateStr)
            ->get()
            ->keyBy('jadwal_pelajaran_id');

        // Sesi pembelajaran & jurnal KBM guru pada tanggal terpilih
        $scheduleIds = $schedules->pluck('id');
        $teachingSessions = LessonAttendanceSession::whereIn('schedule_id', $scheduleIds)
            ->whereDate('attendance_date', $dateStr)
            ->get()
            ->keyBy('schedule_id');

        // Presensi harian gerbang / izin siswa pada tanggal terpilih
        $dailyPermission = StudentAttendancePermission::where('student_id', $student->id)
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->first();

        $dayNames = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu',
        ];
        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $formattedDate = ($dayNames[$dayOfWeekIso] ?? '') . ', ' . $selectedDate->day . ' ' . ($monthNames[$selectedDate->month] ?? '') . ' ' . $selectedDate->year;

        $mappedSchedules = $schedules->map(function ($s) use ($attendances, $teachingSessions, $dailyPermission, $currentTime, $isPastDay, $isFutureDay, $formattedDate, $dateStr) {
            $att = $attendances->get($s->id);
            $timeStart = $s->time_start;
            $timeEnd = $s->time_end;

            if ($isPastDay) {
                $isPast = true;
                $isOngoing = false;
            } elseif ($isFutureDay) {
                $isPast = false;
                $isOngoing = false;
            } else {
                $isPast = $timeEnd ? ($currentTime > $timeEnd) : false;
                $isOngoing = ($timeStart && $timeEnd) ? ($currentTime >= $timeStart && $currentTime <= $timeEnd) : false;
            }

            $attData = null;
            if ($att) {
                $attData = [
                    'status' => $att->status_hadir,
                    'status_label' => $att->status_label ?? ucfirst($att->status_hadir),
                    'recorded_at' => $att->waktu_presensi ? Carbon::parse($att->waktu_presensi)->format('H:i') : ($att->arrival_time ?? null),
                    'keterangan' => $att->keterangan,
                    'notes' => $att->keterangan,
                    'arrival_time' => $att->arrival_time ? substr((string) $att->arrival_time, 0, 5) : null,
                ];
            } elseif ($dailyPermission) {
                $attData = [
                    'status' => strtolower($dailyPermission->type ?? 'izin'),
                    'status_label' => ucfirst($dailyPermission->type ?? 'Izin'),
                    'recorded_at' => null,
                    'keterangan' => $dailyPermission->reason,
                    'notes' => $dailyPermission->reason,
                    'arrival_time' => null,
                ];
            }

            $session = $teachingSessions->get($s->id);
            if (! $session && $att && $att->session_id) {
                $session = LessonAttendanceSession::find($att->session_id);
            }

            $journalData = null;
            if ($session) {
                $journalData = [
                    'id' => $session->id,
                    'meeting_number' => $session->meeting_number,
                    'topic' => $session->topic ?: $session->learning_material,
                    'learning_material' => $session->learning_material ?: $session->topic,
                    'learning_activity' => $session->learning_activity,
                    'meeting_notes' => $session->meeting_notes,
                    'status' => $session->status,
                    'finalized_at' => $session->finalized_at ? Carbon::parse($session->finalized_at)->format('H:i') : null,
                ];
            }

            $timeDisplay = ($timeStart && $timeEnd)
                ? substr($timeStart, 0, 5) . ' - ' . substr($timeEnd, 0, 5)
                : ($timeStart ? substr($timeStart, 0, 5) : null);

            return [
                'id' => $s->id,
                'class_id' => $s->class_id ?? $s->kelas_id,
                'day_of_week' => (int) ($s->day_of_week ?? 1),
                'day_name' => match ((int) ($s->day_of_week ?? 1)) {
                    1 => 'Senin',
                    2 => 'Selasa',
                    3 => 'Rabu',
                    4 => 'Kamis',
                    5 => 'Jumat',
                    6 => 'Sabtu',
                    default => 'Minggu',
                },
                'date' => $dateStr,
                'date_formatted' => $formattedDate,
                'time_start' => $timeStart,
                'time_end' => $timeEnd,
                'time_display' => $timeDisplay,
                'activity_type' => $s->activity_type ?? 'kbm',
                'activity_name' => $s->activity_name,
                'subject' => [
                    'id' => $s->subject?->id,
                    'name' => ($s->activity_type && $s->activity_type !== 'kbm')
                        ? ($s->activity_name ?: null)
                        : ($s->subject?->name ?? $s->subject?->nama_mapel ?? null),
                    'code' => ($s->activity_type && $s->activity_type !== 'kbm') ? 'Kegiatan' : ($s->subject?->code ?? $s->subject?->kode_mapel ?? ''),
                    'icon' => $s->subject?->ikon ?? $s->subject?->icon ?? null,
                    'color' => $s->subject?->warna ?? $s->subject?->color ?? null,
                ],
                'room' => $s->room ?? $s->ruangan ?? null,
                'teacher' => [
                    'id' => $s->employee?->id ?? $s->teacher?->id,
                    'name' => $s->employee?->nama_lengkap ?? $s->teacher?->name ?? null,
                ],
                'is_past' => $isPast,
                'is_ongoing' => $isOngoing,
                'status' => $isPast ? 'selesai' : ($isOngoing ? 'berlangsung' : 'akan_datang'),
                'status_label' => $isPast ? 'Selesai' : ($isOngoing ? 'Sedang Berlangsung' : 'Akan Datang'),
                'attendance' => $attData,
                'journal' => $journalData,
                'learning' => $journalData,
            ];
        });

        // Jadwal pada tanggal terpilih
        $activeDisplayToday = $mappedSchedules->where('day_of_week', $dayOfWeekIso)->values();

        // Generate pekan Senin s/d Sabtu untuk minggu tanggal terpilih
        $startOfWeek = $selectedDate->copy()->startOfWeek();
        $weekDates = [];
        for ($i = 0; $i < 6; $i++) {
            $curD = $startOfWeek->copy()->addDays($i);
            $wDayIso = $i + 1;
            $wDateStr = $curD->format('Y-m-d');
            $weekDates[] = [
                'day_id' => $wDayIso,
                'day_name' => $dayNames[$wDayIso],
                'date' => $wDateStr,
                'date_short' => $curD->day . ' ' . ($monthNames[$curD->month] ?? ''),
                'date_formatted' => $dayNames[$wDayIso] . ', ' . $curD->day . ' ' . ($monthNames[$curD->month] ?? '') . ' ' . $curD->year,
                'is_selected' => ($wDateStr === $dateStr),
                'is_today' => ($wDateStr === $todayStr),
                'count' => $mappedSchedules->where('day_of_week', $wDayIso)->count(),
            ];
        }

        $uniqueSubjectsCount = $mappedSchedules->pluck('subject.id')->unique()->count();
        $uniqueTeachersCount = $mappedSchedules->pluck('teacher.id')->filter()->unique()->count();

        $kpi = [
            'today_count' => $activeDisplayToday->count(),
            'weekly_count' => $mappedSchedules->count(),
            'subject_count' => $uniqueSubjectsCount,
            'teacher_count' => $uniqueTeachersCount,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'kpi' => $kpi,
                'semester_name' => $activeSemester?->nama_semester ?? $activeSemester?->name ?? null,
                'academic_year' => $activeYear?->name ?? $activeYear?->tahun_ajaran ?? '',
                'selected_date' => $dateStr,
                'selected_date_formatted' => $formattedDate,
                'is_today' => $isToday,
                'is_past_day' => $isPastDay,
                'is_future_day' => $isFutureDay,
                'week_dates' => $weekDates,
                'today_schedules' => $activeDisplayToday,
                'weekly_schedules' => $mappedSchedules,
                'all_schedules' => $mappedSchedules,
                'today_day' => $dayOfWeekIso,
                'current_time' => $currentTime,
                'current_date' => $todayStr,
                'student' => [
                    'id' => $student->id,
                    'name' => $student->nama_lengkap ?? $student->name,
                    'class' => $student->classroom?->name ?? $student->kelas?->name ?? '',
                    'unit' => $student->educationUnit?->name ?? '',
                    'unit_id' => $student->educationUnit?->id ?? $student->unit_id,
                    'program_type' => $student->educationUnit?->program_type ?? $student->kelas?->unitPendidikan?->program_type ?? 'fullday',
                    'jam_kegiatan' => $student->educationUnit?->jam_kegiatan ?? $student->kelas?->unitPendidikan?->jam_kegiatan ?? null,
                    'foto_url' => $student->photo_url ?? $student->avatar_url ?? ($student->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) : null),
                    'photo_url' => $student->photo_url ?? $student->avatar_url ?? ($student->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) : null),
                    'foto' => $student->photo ?? $student->foto ?? null,
                    'photo' => $student->photo ?? null,
                ],
            ],
        ]);
    }

    public function academicCalendar(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $unit = $student->educationUnit ?: \App\Models\EducationUnit::find($student->education_unit_id);
        $unitName = $unit ? $unit->name : null;
        $unitCode = $unit ? ($unit->code ?? $unit->kode ?? null) : null;

        $range = $request->query('range', 'week');
        $anchorDateStr = $request->query('date', now()->format('Y-m-d'));
        try {
            $anchor = \Carbon\Carbon::parse($anchorDateStr);
        } catch (\Throwable $e) {
            $anchor = now();
        }

        switch ($range) {
            case 'today':
                $start = $anchor->copy()->startOfDay();
                $end = $anchor->copy()->endOfDay();
                $rangeLabel = 'Hari Ini ('.$anchor->locale('id')->isoFormat('D MMMM Y').')';
                break;
            case 'week':
                $start = $anchor->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                $end = $anchor->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                $rangeLabel = '1 Minggu ('.$start->locale('id')->isoFormat('D MMM').' - '.$end->locale('id')->isoFormat('D MMM Y').')';
                break;
            case 'month':
                $start = $anchor->copy()->startOfMonth();
                $end = $anchor->copy()->endOfMonth();
                $rangeLabel = '1 Bulan ('.$anchor->locale('id')->isoFormat('MMMM Y').')';
                break;
            case 'semester':
                $start = $anchor->month >= 7 ? $anchor->copy()->month(7)->startOfMonth() : $anchor->copy()->month(1)->startOfMonth();
                $end = $anchor->month >= 7 ? $anchor->copy()->month(12)->endOfMonth() : $anchor->copy()->month(6)->endOfMonth();
                $semNum = $anchor->month >= 7 ? 'Ganjil' : 'Genap';
                $rangeLabel = 'Semester '.$semNum.' ('.$start->locale('id')->isoFormat('MMMM').' - '.$end->locale('id')->isoFormat('MMMM Y').')';
                break;
            case 'year':
                $start = $anchor->month >= 7 ? $anchor->copy()->month(7)->startOfMonth() : $anchor->copy()->subYear()->month(7)->startOfMonth();
                $end = $start->copy()->addYear()->subDay()->endOfDay();
                $rangeLabel = 'Tahun Ajaran ('.$start->format('Y').'/'.$end->format('Y').')';
                break;
            case 'all':
            default:
                $start = now()->subMonths(6)->startOfMonth();
                $end = now()->addMonths(12)->endOfMonth();
                $rangeLabel = 'Semua Agenda Kegiatan';
                break;
        }

        $activeYear = \App\Models\AcademicYear::where('is_active', true)->first()
            ?: \App\Models\AcademicYear::orderByDesc('start_date')->first();

        // Ambil seluruh agenda pengumuman sekolah bertipe kalender / kegiatan resmi untuk unit siswa aktif
        $schoolEvents = $this->schoolInformationQuery($request, $student)
            ->orderBy('mulai_tampil')
            ->get();

        $allEvents = collect();

        foreach ($schoolEvents as $se) {
            $meta = $se->data_tambahan ?? [];
            $evDate = $meta['tanggal'] ?? $se->mulai_tampil?->format('Y-m-d') ?? now()->format('Y-m-d');
            $cat = \Illuminate\Support\Str::lower($meta['kategori'] ?? $meta['tipe'] ?? 'kegiatan');

            $badgeCat = 'Kegiatan Sekolah';
            $color = '#0284C7';
            $colorName = 'sky';

            if (str_contains($cat, 'ujian') || str_contains($cat, 'formatif') || str_contains($cat, 'sumatif')) {
                $badgeCat = 'Ujian & Penilaian';
                $color = '#7C3AED';
                $colorName = 'purple';
            } elseif (str_contains($cat, 'libur')) {
                $badgeCat = 'Libur Sekolah & Nasional';
                $color = '#E11D48';
                $colorName = 'rose';
            } elseif (str_contains($cat, 'rapor') || str_contains($cat, 'wisuda')) {
                $badgeCat = 'Penerimaan Rapor';
                $color = '#D97706';
                $colorName = 'amber';
            } elseif (str_contains($cat, 'akademik') || str_contains($cat, 'kbm') || str_contains($cat, 'mpls') || str_contains($cat, 'masuk')) {
                $badgeCat = 'Kegiatan Akademik';
                $color = '#059669';
                $colorName = 'emerald';
            } elseif (str_contains($cat, 'ekskul') || str_contains($cat, 'meeting')) {
                $badgeCat = 'Ekstrakurikuler';
                $color = '#EA580C';
                $colorName = 'orange';
            }

            $startD = $se->mulai_tampil?->format('Y-m-d') ?? \Carbon\Carbon::parse($evDate)->format('Y-m-d');
            $endD = $se->selesai_tampil?->format('Y-m-d') ?? $startD;

            $allEvents->push([
                'id' => 'event-'.$se->id,
                'title' => $se->judul_pengumuman,
                'category' => $badgeCat,
                'color' => $color,
                'color_name' => $colorName,
                'date' => \Carbon\Carbon::parse($evDate)->format('Y-m-d'),
                'start_date' => $startD,
                'end_date' => $endD,
                'time_start' => $meta['jam_mulai'] ?? null,
                'time_end' => $meta['jam_selesai'] ?? null,
                'time_display' => (!empty($meta['jam_mulai']) && !empty($meta['jam_selesai']))
                    ? ($meta['jam_mulai'] . ' - ' . $meta['jam_selesai'])
                    : ($meta['jam_mulai'] ?? 'Sepanjang Hari'),
                'location' => $meta['lokasi'] ?? $unitName,
                'room' => $meta['ruang'] ?? null,
                'unit' => $meta['education_unit'] ?? $unitName,
                'notes' => strip_tags($se->isi_pengumuman),
                'type' => 'event',
            ]);
        }

        // Milestone resmi Tahun Ajaran aktif
        if ($activeYear) {
            $stYear = $activeYear->start_date ? \Carbon\Carbon::parse($activeYear->start_date) : null;
            $enYear = $activeYear->end_date ? \Carbon\Carbon::parse($activeYear->end_date) : null;
            if ($stYear) {
                $allEvents->push([
                    'id' => 'milestone-start-'.$activeYear->id,
                    'title' => 'Awal KBM & Pembukaan Tahun Ajaran '.$activeYear->name,
                    'category' => 'Kegiatan Akademik',
                    'color' => '#059669',
                    'color_name' => 'emerald',
                    'date' => $stYear->format('Y-m-d'),
                    'start_date' => $stYear->format('Y-m-d'),
                    'end_date' => $stYear->format('Y-m-d'),
                    'time_start' => null,
                    'time_end' => null,
                    'time_display' => 'Sepanjang Hari',
                    'location' => $unitName,
                    'unit' => $unitName,
                    'notes' => 'Hari pertama masuk sekolah tahun ajaran '.$activeYear->name,
                    'type' => 'academic_year',
                ]);
            }
            if ($enYear) {
                $allEvents->push([
                    'id' => 'milestone-end-'.$activeYear->id,
                    'title' => 'Libur Semester & Kenaikan Kelas TA '.$activeYear->name,
                    'category' => 'Libur Sekolah & Nasional',
                    'color' => '#E11D48',
                    'color_name' => 'rose',
                    'date' => $enYear->format('Y-m-d'),
                    'start_date' => $enYear->format('Y-m-d'),
                    'end_date' => $enYear->format('Y-m-d'),
                    'time_start' => null,
                    'time_end' => null,
                    'time_display' => 'Sepanjang Hari',
                    'location' => $unitName,
                    'unit' => $unitName,
                    'notes' => 'Periode libur kenaikan kelas tahun ajaran '.$activeYear->name,
                    'type' => 'academic_year',
                ]);
            }
        }

        $sortedAllEvents = $allEvents->sortBy(fn ($e) => $e['date'].' '.($e['time_start'] ?? '00:00'))->values();

        // Filter events berdasarkan rentang tanggal yang diminta jika bukan 'all'
        $filteredEvents = $sortedAllEvents;
        if ($range !== 'all') {
            $filteredEvents = $sortedAllEvents->filter(function ($evt) use ($start, $end) {
                $sDate = $evt['start_date'] ?? $evt['date'];
                $eDate = $evt['end_date'] ?? $sDate;
                return ($sDate >= $start->format('Y-m-d') && $sDate <= $end->format('Y-m-d'))
                    || ($eDate >= $start->format('Y-m-d') && $eDate <= $end->format('Y-m-d'))
                    || ($sDate <= $start->format('Y-m-d') && $eDate >= $end->format('Y-m-d'));
            })->values();
        }

        $upcoming = $sortedAllEvents->filter(fn ($e) => ($e['end_date'] ?? $e['date']) >= now()->format('Y-m-d'))->take(6)->values();
        if ($upcoming->isEmpty()) {
            $upcoming = $sortedAllEvents->take(6)->values();
        }

        $highlightedDates = [];
        $periodLimit = min(366, (int) $start->diffInDays($end) + 1);
        $rangePeriod = \Carbon\CarbonPeriod::create($start->copy()->startOfDay(), $start->copy()->addDays($periodLimit - 1)->endOfDay());
        foreach ($rangePeriod as $d) {
            $highlightedDates[] = $d->format('Y-m-d');
        }

        $weekStart = $anchor->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
        $weekDays = [];
        $dayNamesShort = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        for ($i = 0; $i < 7; $i++) {
            $dayDate = $weekStart->copy()->addDays($i);
            $dStr = $dayDate->format('Y-m-d');
            $evCount = $sortedAllEvents->where('date', $dStr)->count();
            $weekDays[] = [
                'name' => $dayNamesShort[$i],
                'day' => $dayDate->format('j'),
                'date' => $dStr,
                'label' => $dayNamesShort[$i].' '.$dayDate->format('j/n'),
                'is_today' => $dayDate->isToday(),
                'is_highlighted' => in_array($dStr, $highlightedDates, true),
                'has_events' => $evCount > 0,
                'event_count' => $evCount,
            ];
        }

        // Determine dynamic display dates strip: for month/semester/year/all, show all dates of the anchor month
        $dayNamesIso = [
            1 => 'Sen',
            2 => 'Sel',
            3 => 'Rab',
            4 => 'Kam',
            5 => 'Jum',
            6 => 'Sab',
            7 => 'Min',
        ];
        $displayDates = [];
        if (in_array($range, ['month', 'semester', 'year', 'all'], true)) {
            $monthStart = $anchor->copy()->startOfMonth();
            $monthEnd = $anchor->copy()->endOfMonth();
            $monthPeriod = \Carbon\CarbonPeriod::create($monthStart, $monthEnd);
            foreach ($monthPeriod as $mDate) {
                $dStr = $mDate->format('Y-m-d');
                $evCount = $sortedAllEvents->where('date', $dStr)->count();
                $displayDates[] = [
                    'name' => $dayNamesIso[$mDate->dayOfWeekIso] ?? 'Hari',
                    'day' => $mDate->format('j'),
                    'date' => $dStr,
                    'label' => ($dayNamesIso[$mDate->dayOfWeekIso] ?? '').' '.$mDate->format('j/n'),
                    'is_today' => $mDate->isToday(),
                    'is_highlighted' => in_array($dStr, $highlightedDates, true),
                    'has_events' => $evCount > 0,
                    'event_count' => $evCount,
                ];
            }
        } else {
            $displayDates = $weekDays;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'unit' => [
                    'id' => $unit?->id,
                    'name' => $unitName,
                    'code' => $unitCode,
                ],
                'child' => [
                    'id' => $student->id,
                    'name' => $student->nama_lengkap ?? $student->name,
                    'nis' => $student->nis,
                    'class' => $student->classroom?->name ?? $student->kelas?->name ?? '',
                ],
                'academic_year' => [
                    'name' => $activeYear?->name ?? null,
                    'start_date' => $activeYear?->start_date,
                    'end_date' => $activeYear?->end_date,
                ],
                'range' => $range,
                'range_info' => [
                    'range' => $range,
                    'label' => $rangeLabel,
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                    'formatted_range' => $start->locale('id')->isoFormat('D MMMM Y').($start->format('Y-m-d') !== $end->format('Y-m-d') ? ' — '.$end->locale('id')->isoFormat('D MMMM Y') : ''),
                    'total_days' => $start->diffInDays($end) + 1,
                    'highlighted_count' => count($highlightedDates),
                    'events_count' => $filteredEvents->count(),
                    'total_events' => $sortedAllEvents->count(),
                ],
                'quick_ranges' => [
                    ['key' => 'today', 'label' => 'Hari Ini', 'description' => 'Jadwal & kegiatan hari ini'],
                    ['key' => 'week', 'label' => '1 Minggu', 'description' => 'Jadwal 7 hari pekan ini'],
                    ['key' => 'month', 'label' => '1 Bulan', 'description' => 'Kalender 1 bulan penuh'],
                    ['key' => 'semester', 'label' => 'Semester', 'description' => 'Agenda semester berjalan'],
                    ['key' => 'year', 'label' => 'Tahun Ajaran', 'description' => 'Agenda 1 tahun ajaran'],
                    ['key' => 'all', 'label' => 'Semua Agenda', 'description' => 'Seluruh rekaman agenda'],
                ],
                'highlighted_dates' => $highlightedDates,
                'display_dates' => $displayDates,
                'week_dates' => $weekDays,
                'view_date' => $anchor->format('Y-m-d'),
                'view_title' => $anchor->locale('id')->isoFormat('D MMMM Y'),
                'events' => $filteredEvents,
                'all_events' => $sortedAllEvents,
                'upcoming_events' => $upcoming,
                'total' => $filteredEvents->count(),
                'total_all' => $sortedAllEvents->count(),
            ],
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $logs = LmsPresensi::query()
            ->with(['jadwalPelajaran.subject', 'jadwalPelajaran.kelas', 'session.schedule'])
            ->where('siswa_id', $student->id)
            ->orderByDesc('tanggal')
            ->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    public function gateAttendanceToday(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $today = today()->toDateString();
        $gate = Attendance::query()
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', $today)
            ->first();

        $progType = strtolower(trim((string) (
            $student->educationUnit?->program_type ??
            $student->kelas?->unitPendidikan?->program_type ??
            $student->program_type ??
            'fullday'
        )));
        $isBoarding = $progType === 'boarding' || str_contains(strtolower((string) ($student->kelas?->nama_kelas ?? '')), 'asrama') || str_contains(strtolower((string) ($student->educationUnit?->name ?? '')), 'ponpes');

        $activeDormPermit = null;
        if ($isBoarding) {
            $activeDormPermit = \App\Models\DormitoryPermit::query()
                ->where('student_id', $student->id)
                ->whereIn('status', ['disetujui', 'keluar'])
                ->orderByDesc('created_at')
                ->first();
        }

        return response()->json([
            'success' => true,
            'is_boarding' => $isBoarding,
            'program_type' => $isBoarding ? 'boarding' : 'fullday',
            'dormitory_permit' => $activeDormPermit,
            'data' => $gate ? [
                'id' => $gate->id,
                'attendance_date' => $gate->attendance_date,
                'check_in_time' => $gate->check_in_time ? Carbon::parse($gate->check_in_time)->timezone('Asia/Jakarta')->format('H:i') : null,
                'check_out_time' => $gate->check_out_time ? Carbon::parse($gate->check_out_time)->timezone('Asia/Jakarta')->format('H:i') : null,
                'status' => $gate->status,
                'status_label' => $gate->status_label ?? ucfirst(strtolower($gate->status)),
                'attendance_method' => $gate->attendance_method,
                'location' => $gate->location,
                'latitude' => $gate->latitude,
                'longitude' => $gate->longitude,
                'keterangan' => $gate->keterangan,
            ] : null,
        ]);
    }

    public function gateAttendanceTap(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|string|in:in,out,auto',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $today = today()->toDateString();
        $existing = Attendance::query()
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', $today)
            ->first();

        $type = $request->input('type', 'auto');
        if ($type === 'auto') {
            $type = ($existing && $existing->check_in_time && ! $existing->check_out_time) ? 'out' : 'in';
        }

        $payload = [
            'student_id' => $student->id,
            'nis' => $student->nis,
            'attendance_method' => 'TAP_LOKASI',
            'attendance_date' => $today,
            'location' => $request->input('location') ?: 'Gerbang Sekolah (Tap Lokasi GPS)',
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'notes' => $request->input('notes'),
        ];

        if ($type === 'in') {
            $result = $this->gateService->recordCheckIn($payload, $request->user()?->id);
        } else {
            $result = $this->gateService->recordCheckOut($payload, $request->user()?->id);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function submitPermission(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:Izin,Sakit,Keperluan keluarga,Lainnya',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('permissions', 'public');
        }

        $permission = StudentAttendancePermission::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'type' => $request->type,
            'reason' => $request->reason,
            'attachment_path' => $attachmentPath,
            'status' => 'pending',
            'submitted_at' => now(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin/sakit berhasil dikirim dan menunggu verifikasi.',
            'data' => $permission,
        ]);
    }

    public function permissionsHistory(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $history = StudentAttendancePermission::query()
            ->where('student_id', $student->id)
            ->orderBy('submitted_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    public function materials(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $classIds = array_values(array_filter([$student->kelas_id, $student->class_id]));

        $query = LmsMateri::query()
            ->with([
                'subject',
                'guru',
                'media',
                'modulAjar' => function ($mq) {
                    $mq->select([
                        'id', 'kelas_id', 'mata_pelajaran_id', 'guru_id', 'semester_id', 'tahun_ajaran_id',
                        'judul_modul', 'kode_modul', 'fase', 'semester', 'alokasi_waktu_jp',
                        'tujuan_pembelajaran', 'profil_pelajar_pancasila', 'model_pembelajaran', 'metode_pembelajaran',
                        'kegiatan_pendahuluan', 'kegiatan_inti', 'kegiatan_penutup', 'rencana_penilaian',
                        'sumber_belajar', 'media_pembelajaran', 'deskripsi',
                    ]);
                },
            ])
            ->where('is_published', true);

        if (! empty($classIds)) {
            $query->where(function ($q) use ($classIds) {
                $q->whereHas('modulAjar', fn ($mq) => $mq->whereIn('kelas_id', $classIds))
                  ->orWhereNull('modul_ajar_id');
            });
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                  ->orWhere('konten', 'like', "%{$search}%")
                  ->orWhereHas('subject', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($subjectId = $request->query('subject_id')) {
            $query->where('mata_pelajaran_id', $subjectId);
        }

        $materials = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $materials,
            'student' => [
                'id' => $student->id,
                'name' => $student->nama_lengkap ?? $student->full_name ?? $student->name,
                'class' => $student->kelas?->name ?? $student->kelas?->nama_kelas ?? null,
                'nis' => $student->nis ?? $student->nisn ?? null,
                'foto_url' => $student->photo_url ?? $student->avatar_url ?? ($student->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) : null),
                'photo_url' => $student->photo_url ?? $student->avatar_url ?? ($student->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) : null),
                'photo' => $student->photo ?? null,
                'foto' => $student->photo ?? $student->foto ?? null,
                'gender' => $student->gender ?? $student->jenis_kelamin ?? null,
                'jenis_kelamin' => $student->jenis_kelamin ?? $student->gender ?? null,
            ],
        ]);
    }

    public function assignments(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $studentClasses = array_values(array_unique(array_filter([$student->kelas_id, $student->class_id])));

        $baseQuery = LmsPenugasan::query()
            ->with(['subject', 'teacher', 'materi', 'pengumpulanTugas' => function ($q) use ($student) {
                $q->where('siswa_id', $student->id);
            }])
            ->whereIn('kelas_id', $studentClasses)
            ->where('is_published', true);

        // Hitung KPI dari seluruh penugasan siswa di database
        $allAssignments = (clone $baseQuery)->get();
        $tugasAktif = 0;
        $belumDikumpulkan = 0;
        $terlambat = 0;
        $sudahDinilai = 0;
        $now = now();

        foreach ($allAssignments as $a) {
            $sub = $a->pengumpulanTugas->first();
            $isGraded = $sub && ($sub->status === 'dinilai' || $sub->nilai_guru !== null);
            $isSubmitted = $sub && in_array($sub->status, ['dikumpulkan', 'submitted', 'revisi']);
            $isLate = ($sub && $sub->status === 'terlambat') || (! $sub && $a->deadline && $a->deadline < $now);

            if ($isGraded) {
                $sudahDinilai++;
            } elseif ($isLate) {
                $terlambat++;
            } elseif ($isSubmitted) {
                // Dikumpulkan menunggu dinilai
            } else {
                $belumDikumpulkan++;
                $tugasAktif++;
            }
        }

        $perPage = (int) ($request->query('per_page', 30));
        $page = (int) ($request->query('page', 1));
        $assignments = $baseQuery->orderBy('deadline', 'asc')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $assignments,
            'kpi' => [
                'total_tugas' => $allAssignments->count(),
                'tugas_aktif' => $tugasAktif,
                'belum_dikumpulkan' => $belumDikumpulkan,
                'terlambat' => $terlambat,
                'sudah_dinilai' => $sudahDinilai,
                'sudah_dikumpulkan' => max(0, $allAssignments->count() - $belumDikumpulkan - $terlambat),
            ],
            'summary' => [
                'total_tugas' => $allAssignments->count(),
                'tugas_aktif' => $tugasAktif,
                'belum_dikumpulkan' => $belumDikumpulkan,
                'terlambat' => $terlambat,
                'sudah_dinilai' => $sudahDinilai,
                'sudah_dikumpulkan' => max(0, $allAssignments->count() - $belumDikumpulkan - $terlambat),
            ],
            'student' => [
                'id' => $student->id,
                'name' => $student->nama_lengkap ?? $student->full_name ?? $student->name,
                'class' => $student->kelas?->name ?? $student->kelas?->nama_kelas ?? null,
                'nis' => $student->nis ?? $student->nisn ?? null,
                'foto_url' => $student->photo_url ?? $student->avatar_url ?? ($student->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) : null),
                'photo_url' => $student->photo_url ?? $student->avatar_url ?? ($student->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) : null),
                'photo' => $student->photo ?? null,
                'foto' => $student->photo ?? $student->foto ?? null,
                'gender' => $student->gender ?? $student->jenis_kelamin ?? null,
                'jenis_kelamin' => $student->jenis_kelamin ?? $student->gender ?? null,
            ],
        ]);
    }

    public function submitAssignment(Request $request, string $assignmentId): JsonResponse
    {
        if (! Str::isUuid($assignmentId)) {
            return response()->json(['success' => false, 'message' => 'Penugasan tidak ditemukan.'], 404);
        }

        $request->validate([
            'jawaban_teks' => 'nullable|string',
            'file_lampiran' => 'nullable|file|max:10240',
            'url_link' => 'nullable|string|max:1000',
            'link' => 'nullable|string|max:1000',
            'metadata' => 'nullable',
            'child_id' => 'nullable|string',
        ]);

        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $user = $request->user();
        $isParentUser = $user && $user->hasAnyRole(['Orang Tua', 'orang_tua', 'orang-tua', 'Orangtua', 'Wali Murid', 'parent']);
        $isStudentUser = $user && $user->hasAnyRole(['Siswa', 'siswa', 'student']);

        // Jika yang mengumpulkan adalah akun orang tua:
        if ($isParentUser && ! $isStudentUser) {
            $gradeAndLevel = $this->resolveStudentGradeAndLevel($student, $student->kelas);
            $level = strtoupper($gradeAndLevel['level'] ?? '');
            $unitName = trim(($student->kelas?->unitPendidikan?->name ?? '') . ' ' . ($student->educationUnit?->name ?? ''));
            $isSd = in_array($level, ['SD', 'TK'], true) || preg_match('/\b(SD|MI|SDIT|SEKOLAH DASAR|IBTIDAIYAH)\b/i', $unitName);

            if (! $isSd) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengumpulan tugas untuk jenjang SMP dan SMA hanya dapat dilakukan langsung melalui portal/akun siswa.',
                ], 403);
            }
        }

        $penugasan = LmsPenugasan::findOrFail($assignmentId);

        // Hanya penugasan yang sudah dipublikasikan untuk kelas siswa sendiri.
        $isPublished = (bool) $penugasan->is_published || $penugasan->status === 'published';
        if (! $isPublished) {
            return response()->json(['success' => false, 'message' => 'Penugasan belum dipublikasikan.'], 403);
        }

        $assignmentClasses = array_values(array_filter([$penugasan->kelas_id, $penugasan->class_id]));
        $studentClasses = array_values(array_filter([$student->kelas_id, $student->class_id]));
        if (! empty($assignmentClasses) && empty(array_intersect($assignmentClasses, $studentClasses))) {
            return response()->json(['success' => false, 'message' => 'Penugasan ini bukan untuk kelas Anda.'], 403);
        }

        $filePath = null;
        if ($request->hasFile('file_lampiran')) {
            $filePath = $request->file('file_lampiran')->store('submissions', 'public');
        }

        $urlLink = $request->input('url_link', $request->input('link'));
        $metadataInput = $request->input('metadata');
        if (is_string($metadataInput)) {
            $decodedMeta = json_decode($metadataInput, true);
            $metadataInput = is_array($decodedMeta) ? $decodedMeta : ['raw' => $metadataInput];
        }

        $isLate = now()->gt($penugasan->deadline);
        $status = $isLate ? 'terlambat' : 'dikumpulkan';
        $nilaiOtomatis = null;
        $catatanOtomatis = null;
        $waktuDinilai = null;

        // Auto-scoring jika kuis memiliki butir soal & kunci jawaban
        if (in_array($penugasan->jenis_tugas, ['quiz', 'kuis'], true) && ! empty($request->jawaban_teks)) {
            $soalRaw = $penugasan->metadata['soal_kuis'] ?? $penugasan->deskripsi ?: '';
            $soalList = is_array($soalRaw) ? $soalRaw : json_decode($soalRaw, true);
            $jawabanSiswa = json_decode($request->jawaban_teks, true);

            if (is_array($soalList) && count($soalList) > 0 && is_array($jawabanSiswa)) {
                $totalPoin = 0;
                $perolehanPoin = 0;

                foreach ($soalList as $idx => $s) {
                    $poinSoal = floatval($s['bobot'] ?? $s['poin'] ?? 20);
                    $totalPoin += $poinSoal;
                    $kunci = trim(strtoupper($s['kunci_jawaban'] ?? $s['kunci'] ?? ''));

                    $keySoal = $s['id'] ?? (string) $idx;
                    $jawab = trim(strtoupper($jawabanSiswa[$keySoal] ?? ''));

                    if ($kunci !== '' && $jawab === $kunci) {
                        $perolehanPoin += $poinSoal;
                    }
                }

                if ($totalPoin > 0) {
                    $scoreCalc = round(($perolehanPoin / $totalPoin) * 100, 1);
                    $nilaiOtomatis = min(100, max(0, $scoreCalc));
                    $status = 'dinilai';
                    $waktuDinilai = now();
                    $catatanOtomatis = "Nilai Otomatis CBT Kuis ($perolehanPoin / $totalPoin poin)";
                }
            }
        }

        $submission = LmsPengumpulanTugas::updateOrCreate(
            [
                'penugasan_id' => $penugasan->id,
                'siswa_id' => $student->id,
            ],
            [
                'jawaban_teks' => $request->jawaban_teks,
                'file_path' => $filePath,
                'url_link' => $urlLink,
                'metadata' => $metadataInput,
                'status' => $status,
                'nilai_guru' => $nilaiOtomatis,
                'catatan_guru' => $catatanOtomatis,
                'waktu_dinilai' => $waktuDinilai,
                'waktu_kumpul' => now(),
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Tugas berhasil dikumpulkan.',
            'data' => $submission,
        ]);
    }

    public function grades(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        // Resolusi filter parameter tahun ajaran
        $targetAcademicYearId = $request->query('academic_year_id');
        $academicYearName = $request->query('academic_year');
        if (! $targetAcademicYearId && $academicYearName) {
            $targetAcademicYearId = AcademicYear::where('name', $academicYearName)
                ->orWhere('name', str_replace('-', '/', $academicYearName))
                ->value('id');
        }

        // Resolusi filter parameter semester
        $targetSemesterId = $request->query('semester_id');
        $semesterName = $request->query('semester');
        if (! $targetSemesterId && $semesterName) {
            $semNameClean = trim(strtolower($semesterName));
            if (str_contains($semNameClean, 'genap') || str_contains($semNameClean, '2')) {
                $targetSemesterId = Semester::where('name', 'Genap')
                    ->when($targetAcademicYearId, fn ($q) => $q->where('academic_year_id', $targetAcademicYearId))
                    ->value('id');
            } elseif (str_contains($semNameClean, 'ganjil') || str_contains($semNameClean, '1')) {
                $targetSemesterId = Semester::where('name', 'Ganjil')
                    ->when($targetAcademicYearId, fn ($q) => $q->where('academic_year_id', $targetAcademicYearId))
                    ->value('id');
            }
        }

        // Cari published report sesuai filter periode jika ada
        $raporQuery = LmsRapor::query()
            ->with(['kelas.unitPendidikan', 'semester', 'tahunAjaran'])
            ->where('siswa_id', $student->id)
            ->whereIn('status_rapor', ['published', 'diterbitkan', 'terbit']);

        if ($targetAcademicYearId) {
            $raporQuery->where('tahun_ajaran_id', $targetAcademicYearId);
        }
        if ($targetSemesterId) {
            $raporQuery->where('semester_id', $targetSemesterId);
        }

        $publishedReport = $raporQuery->orderByDesc('tanggal_terbit')
            ->orderByDesc('created_at')
            ->first();

        // Tentukan semester & tahun ajaran aktif untuk query nilai
        $selectedYearId = $publishedReport?->tahun_ajaran_id ?? $targetAcademicYearId;
        $selectedSemesterId = $publishedReport?->semester_id ?? $targetSemesterId;

        if (! $selectedYearId || ! $selectedSemesterId) {
            // Cek nilai terakhir siswa di student_grades
            $latestGrade = StudentGrade::where('student_id', $student->id)
                ->when($selectedYearId, fn ($q) => $q->where('academic_year_id', $selectedYearId))
                ->when($selectedSemesterId, fn ($q) => $q->where('semester_id', $selectedSemesterId))
                ->orderByDesc('updated_at')
                ->first();

            if ($latestGrade) {
                $selectedYearId = $selectedYearId ?: $latestGrade->academic_year_id;
                $selectedSemesterId = $selectedSemesterId ?: $latestGrade->semester_id;
            } else {
                $activeAy = AcademicYear::where('is_active', true)->first();
                $selectedYearId = $selectedYearId ?: $activeAy?->id;
                $activeSem = Semester::where('is_active', true)
                    ->when($selectedYearId, fn ($q) => $q->where('academic_year_id', $selectedYearId))
                    ->first();
                $selectedSemesterId = $selectedSemesterId ?: $activeSem?->id;
            }
        }

        $studentContext = $this->resolveStudentGradeAndLevel($student, $publishedReport?->kelas ?? $student->kelas);
        $reportClass = $publishedReport?->kelas ?? $student->kelas;
        $unitId = $reportClass?->unit_pendidikan_id ?? $student->unit_id;
        $level = $studentContext['level'];

        // Query nilai dari student_grades
        $gradesQuery = StudentGrade::query()
            ->with(['subject', 'kelas.unitPendidikan', 'academicYear', 'semester'])
            ->where('student_id', $student->id)
            ->whereNotNull('final_score');

        if ($selectedYearId) {
            $gradesQuery->where('academic_year_id', $selectedYearId);
        }
        if ($selectedSemesterId) {
            $gradesQuery->where('semester_id', $selectedSemesterId);
        }

        if ($publishedReport) {
            $gradesQuery->where(function ($q) use ($publishedReport, $student) {
                $q->where('kelas_id', $publishedReport->kelas_id)
                  ->orWhere('kelas_id', $student->kelas_id);
            });
        }

        $grades = (clone $gradesQuery)->whereHas('subject', function ($query) use ($unitId, $level) {
            $query->where('status', true);
            if ($unitId) {
                $query->where('unit_pendidikan_id', $unitId);
            } elseif ($level) {
                $query->where('jenjang', $level);
            }
        })->orderBy(
            Subject::query()->select('urutan_tampil')->whereColumn('subjects.id', 'student_grades.subject_id')
        )->get();

        // Fallback jika relasi subject unit_id tidak cocok karena perbedaan jenjang/unit di rapor
        if ($grades->isEmpty()) {
            $grades = $gradesQuery->orderBy(
                Subject::query()->select('urutan_tampil')->whereColumn('subjects.id', 'student_grades.subject_id')
            )->get();
        }

        // Jika masih kosong dan tidak ada filter spesifik, cari nilai terbaru apapun periode
        if ($grades->isEmpty() && ! $targetSemesterId && ! $targetAcademicYearId) {
            $grades = StudentGrade::query()
                ->with(['subject', 'kelas.unitPendidikan', 'academicYear', 'semester'])
                ->where('student_id', $student->id)
                ->whereNotNull('final_score')
                ->orderByDesc('updated_at')
                ->take(15)
                ->get();
        }

        $items = $grades->map(function (StudentGrade $grade) {
            $kkm = $grade->subject?->kkm !== null ? round((float) $grade->subject->kkm) : null;
            $score = $grade->final_score !== null ? round((float) $grade->final_score) : null;
            $isPassed = $score !== null && $kkm !== null ? $score >= $kkm : null;

            return [
                'id' => $grade->id,
                'subject_name' => $grade->subject?->nama_mapel ?? $grade->subject?->name ?? null,
                'final_score' => $score,
                'score_assignment' => $grade->score_assignment !== null ? round((float) $grade->score_assignment) : null,
                'score_quiz' => $grade->score_quiz !== null ? round((float) $grade->score_quiz) : null,
                'score_mid' => $grade->score_mid !== null ? round((float) $grade->score_mid) : null,
                'score_final' => $grade->score_final !== null ? round((float) $grade->score_final) : null,
                'grade_letter' => $grade->grade_letter,
                'predicate' => $grade->grade_letter ?? null,
                'is_passed' => $isPassed,
                'kkm' => $kkm,
                'notes' => $grade->notes,
                'subject' => [
                    'id' => $grade->subject?->id,
                    'code' => $grade->subject?->kode_mapel ?? $grade->subject?->code,
                    'name' => $grade->subject?->nama_mapel ?? $grade->subject?->name,
                    'unit_id' => $grade->subject?->unit_pendidikan_id,
                    'level' => $grade->subject?->jenjang,
                ],
            ];
        })->values();

        $scores = $items->pluck('final_score')->filter(fn ($score) => $score !== null);
        $summary = [
            'average_score' => $scores->isNotEmpty() ? round((float) $scores->average()) : null,
            'highest_score' => $scores->isNotEmpty() ? round((float) $scores->max()) : null,
            'passed_subjects' => $items->where('is_passed', true)->count(),
            'remedial_subjects' => $items->where('is_passed', false)->count(),
            'total_subjects' => $items->count(),
        ];

        // Resolusi periode
        $firstGrade = $grades->first();
        $periodAy = $publishedReport?->tahunAjaran ?? $firstGrade?->academicYear ?? ($selectedYearId ? AcademicYear::find($selectedYearId) : null);
        $periodSem = $publishedReport?->semester ?? $firstGrade?->semester ?? ($selectedSemesterId ? Semester::find($selectedSemesterId) : null);
        $periodClass = $publishedReport?->kelas ?? $firstGrade?->kelas ?? $student->kelas;
        $periodUnit = $periodClass?->unitPendidikan ?? $student->educationUnit;

        $period = [
            'academic_year_id' => $periodAy?->id ?? $selectedYearId,
            'academic_year' => $periodAy?->name,
            'semester_id' => $periodSem?->id ?? $selectedSemesterId,
            'semester' => $periodSem?->name,
            'class_id' => $periodClass?->id,
            'class_name' => $periodClass?->nama_kelas,
            'unit_id' => $periodUnit?->id ?? $student->unit_id,
            'unit_name' => $periodUnit?->name,
        ];

        // Resolusi status publikasi
        if ($publishedReport) {
            $publication = [
                'report_id' => $publishedReport->id,
                'status' => $publishedReport->status_rapor,
                'published_at' => $publishedReport->tanggal_terbit?->toDateString(),
                'notes' => $publishedReport->catatan_wali_kelas,
            ];
        } else {
            $publication = [
                'report_id' => null,
                'status' => $items->isNotEmpty() ? 'dalam_proses' : 'belum_tersedia',
                'published_at' => null,
                'notes' => $items->isNotEmpty()
                    ? 'Nilai formatif dan sumatif berjalan (rapor semester resmi belum diterbitkan).'
                    : 'Belum ada data nilai pada periode ini.',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'summary' => $summary,
                'student' => [
                    'id' => $student->id,
                    'name' => $student->full_name ?? $student->name,
                    'nis' => $student->nis ?? $student->nisn,
                    'class_name' => $periodClass?->nama_kelas ?? $studentContext['class_name'],
                    'unit_name' => $periodUnit?->name ?? $studentContext['unit_name'],
                ],
                'period' => $period,
                'publication' => $publication,
            ],
        ]);
    }

    public function tahfizh(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $query = TahfizhDailyLog::query()
            ->with(['teacher', 'classModel.unitPendidikan', 'student.educationUnit', 'student.kelas.unitPendidikan'])
            ->where('student_id', $student->id)
            ->orderBy('record_date', 'desc');

        $perPage = min(max((int) $request->integer('per_page', 50), 1), 100);
        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function ($log) use ($student) {
            $ayatStart = $log->hafalan_ayah_start;
            $ayatEnd = $log->hafalan_ayah_end;
            $jumlahAyat = ($ayatStart && $ayatEnd && $ayatEnd >= $ayatStart)
                ? (int) ($ayatEnd - $ayatStart + 1)
                : ((int) $log->hafalan_baris ?: 0);

            $displaySurah = $log->hafalan_surah_name
                ?: ($log->surah ?: ($log->tilawah_text ?: ($log->murajaah_text ?: null)));

            $teacherName = $log->teacher?->full_name
                ?? $log->teacher?->name
                ?? ($log->signature_teacher ?: null);

            // Resolusi Single Source of Truth kategori dan nama surat setoran
            [$logCategory, $displaySurah] = $this->resolveTahfizhLogCategory($log);
            $metaArr = is_array($log->metadata) ? $log->metadata : (is_string($log->metadata) ? json_decode($log->metadata, true) : []);

            // Ekstraksi Unit Pendidikan Fullday & Jenjang Tingkat (TK, SD, SMP, SMA)
            $classObj = $log->classModel ?? $student->kelas;
            $resInfo = $this->resolveStudentGradeAndLevel($student, $classObj);
            $normalizedLevel = $resInfo['level'];
            $resolvedUnitName = $resInfo['unit_name'];

            $logArr = $log->toArray();
            $logArr['category'] = $logCategory;
            $logArr['type'] = $logCategory;
            $logArr['surah'] = $displaySurah;
            $logArr['nama_surah'] = $displaySurah;
            $logArr['hafalan_surah_name'] = $log->hafalan_surah_name;
            $logArr['date'] = $log->record_date;
            $logArr['tanggal'] = $log->record_date;
            $logArr['record_date'] = $log->record_date;
            $logArr['ayat_start'] = $ayatStart;
            $logArr['ayat_end'] = $ayatEnd;
            $logArr['ayat_mulai'] = $ayatStart;
            $logArr['ayat_selesai'] = $ayatEnd;
            $logArr['hafalan_ayah_start'] = $ayatStart;
            $logArr['hafalan_ayah_end'] = $ayatEnd;
            $logArr['jumlah_ayat'] = $jumlahAyat;
            $logArr['catatan'] = $log->notes_teacher ?: ($log->notes_parent ?: '');
            $logArr['notes_teacher'] = $log->notes_teacher;
            $logArr['notes_parent'] = $log->notes_parent;
            $logArr['status'] = $log->status;
            $logArr['class_name'] = $classObj?->nama_kelas ?? $student->class_name ?? '';
            $logArr['class_level'] = $classObj?->tingkat ?? $student->tingkat ?? '';
            $logArr['unit_name'] = $resolvedUnitName;
            $logArr['unit_level'] = $normalizedLevel;

            // Predikat / Nilai & Evaluasi Kualitas Jujur
            $explicitNilai = data_get($metaArr, 'nilai') ?: data_get($metaArr, 'predikat');
            $kelancaranVal = data_get($metaArr, 'kelancaran')
                ?: ($log->kelancaran_label && !in_array(strtolower($log->kelancaran_label), ['terverifikasi', 'menunggu evaluasi', 'approved', 'verified', 'submitted']) ? $log->kelancaran_label : null);
            $tajwidVal = data_get($metaArr, 'tajwid') ?: $log->tajwid_label;
            $makhrajVal = data_get($metaArr, 'makhraj') ?: $log->makhraj_label;

            // Kualitas evaluasi jujur (tanpa mengarang predikat dan memisahkan status approval)
            $qualityEvaluation = $explicitNilai ?: ($kelancaranVal ?: ($tajwidVal ?: ($makhrajVal ?: null)));

            if ($qualityEvaluation) {
                $predikatVal = $qualityEvaluation;
            } elseif ($log->status === 'pending_approval') {
                $predikatVal = 'Menunggu Verifikasi';
            } elseif ($log->status === 'approved' || $log->status === 'verified') {
                $predikatVal = 'Terverifikasi';
            } else {
                $predikatVal = $log->status ? ucfirst(str_replace('_', ' ', (string) $log->status)) : null;
            }

            $logArr['nilai'] = $predikatVal;
            $logArr['predikat'] = $predikatVal;
            $logArr['calculated_juz'] = $log->calculated_juz;
            $logArr['juz'] = $log->calculated_juz;
            $logArr['kelancaran'] = $log->kelancaran_label ?? data_get($metaArr, 'kelancaran');
            $logArr['tajwid'] = $log->tajwid_label ?? data_get($metaArr, 'tajwid');
            $logArr['makhraj'] = $log->makhraj_label ?? data_get($metaArr, 'makhraj');
            $logArr['metadata'] = $log->metadata;
            $logArr['teacher'] = [
                'id' => $log->teacher?->id,
                'nama_lengkap' => $teacherName,
                'full_name' => $teacherName,
                'name' => $teacherName,
            ];

            return $logArr;
        });

        $allLogs = TahfizhDailyLog::query()
            ->with(['teacher', 'classModel.unitPendidikan'])
            ->where('student_id', $student->id)
            ->orderBy('record_date', 'desc')
            ->get();

        $totalAyat = $allLogs->sum(function ($l) {
            if ($l->hafalan_ayah_start && $l->hafalan_ayah_end && $l->hafalan_ayah_end >= $l->hafalan_ayah_start) {
                return (int) ($l->hafalan_ayah_end - $l->hafalan_ayah_start + 1);
            }
            return (int) ($l->hafalan_baris ?: 0);
        });

        $thisMonthCount = $allLogs->filter(function ($l) {
            if (! $l->record_date) return false;
            $dt = \Carbon\Carbon::parse($l->record_date);
            return $dt->isCurrentMonth() && $dt->isCurrentYear();
        })->count();

        $ziyadahCount = 0;
        $murajaahCount = 0;
        $tilawahCount = 0;

        foreach ($allLogs as $l) {
            [$cat] = $this->resolveTahfizhLogCategory($l);
            if ($cat === 'ziyadah') {
                $ziyadahCount++;
            } elseif ($cat === 'murajaah') {
                $murajaahCount++;
            } elseif ($cat === 'tilawah') {
                $tilawahCount++;
            }
        }

        // Deteksi unit dan tingkat kelas ananda secara akurat dari data kelas aktual
        $resolved = $this->resolveStudentGradeAndLevel($student);
        $classObj = $resolved['class_obj'];
        $unitName = $resolved['unit_name'];
        $normalizedLevel = $resolved['level'];
        $currentGradeNum = $resolved['grade'];

        // Tarik target hafalan dinamis langsung dari database tabel memorization_targets
        $targetRecord = DB::table('memorization_targets')
            ->where(function ($q) use ($student) {
                $q->where('student_id', $student->id)
                    ->orWhere('class_id', $student->class_id ?? $student->kelas_id);
            })
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->first();

        $metaTarget = $targetRecord && $targetRecord->metadata
            ? (is_string($targetRecord->metadata) ? json_decode($targetRecord->metadata, true) : (array) $targetRecord->metadata)
            : [];

        $surahTarget = $metaTarget['surah_target'] ?? null;
        $targetAyat = (int) ($metaTarget['target_ayat'] ?? ($targetRecord->target_lines ?? 0));

        // Jika belum ada target individual di memorization_targets, gunakan target kurikulum dari tahfizh_kpi_targets
        if ($targetAyat <= 0) {
            $eduUnitId = $student->education_unit_id ?? $student->unit_id ?? $resolved['edu_unit']?->id;
            $actualUnitLevel = $resolved['edu_unit']?->level ?? $resolved['class_obj']?->jenjang ?? $normalizedLevel;

            $levels = array_filter([$actualUnitLevel, $normalizedLevel]);
            if (in_array(strtoupper((string) $actualUnitLevel), ['MAHAD', "MA'HAD", 'PONPES', 'PESANTREN', 'BOARDING'])) {
                $levels = array_unique(array_merge($levels, ['PONPES', 'MAHAD', 'PESANTREN', 'BOARDING']));
            }
            if (in_array(strtoupper((string) $actualUnitLevel), ['TAUD', 'TKIT', 'TK'])) {
                $levels = array_unique(array_merge($levels, ['TKIT', 'TAUD', 'TK']));
            }

            $gradeVariants = $currentGradeNum !== null ? [(string) $currentGradeNum, 'ALL'] : ['ALL'];
            if ((int) $currentGradeNum === 4) $gradeVariants[] = '3';
            if ((int) $currentGradeNum === 6) $gradeVariants[] = '5';

            $kpiTarget = DB::table('tahfizh_kpi_targets')
                ->where('is_active', true)
                ->where(function ($q) use ($levels, $eduUnitId) {
                    if (!empty($levels)) {
                        $q->whereIn('unit_level', $levels);
                    }
                    if ($eduUnitId) {
                        $q->orWhere('education_unit_id', $eduUnitId);
                    }
                })
                ->whereIn('grade_level', $gradeVariants)
                ->orderByRaw("CASE WHEN grade_level = ? THEN 1 WHEN grade_level IN ('3','5') THEN 2 WHEN grade_level = 'ALL' THEN 3 ELSE 4 END", [(string) ($currentGradeNum ?? 0)])
                ->first();

            if ($kpiTarget) {
                $surahTarget = $kpiTarget->target_juz ? "{$kpiTarget->target_juz} ({$kpiTarget->target_ayats} Ayat)" : $kpiTarget->target_name;
                $targetAyat = (int) ($kpiTarget->target_ayats ?? 0);
            }
        }

        // Only expose grades represented by actual database records, plus the
        // student's current class. Do not synthesize missing historical grades.
        $gradeForLog = function (TahfizhDailyLog $log): ?int {
            $metadata = is_array($log->metadata) ? $log->metadata : [];
            $grade = $log->classModel?->tingkat ?? ($metadata['class_level'] ?? null);
            if (! is_numeric($grade) && is_string($grade) && preg_match('/\b(1[0-2]|[1-9])\b/', $grade, $match)) {
                $grade = $match[1];
            }
            return is_numeric($grade) ? (int) $grade : null;
        };

        $recordedGrades = $allLogs->map($gradeForLog)->filter()->unique()->values();
        if ($currentGradeNum !== null && ! $recordedGrades->contains($currentGradeNum)) {
            $recordedGrades->push($currentGradeNum);
        }
        $recordedGrades = $recordedGrades->sort()->values();

        $gradeSummaries = [];
        foreach ($recordedGrades as $g) {
            $isCurrent = ($g === $currentGradeNum);
            $logsForGrade = $allLogs->filter(function ($log) use ($g, $gradeForLog) {
                return $gradeForLog($log) === $g;
            });
            $gradeClass = $logsForGrade->first()?->classModel;

            $totalAyatGrade = $logsForGrade->sum(function ($l) {
                if ($l->hafalan_ayah_start && $l->hafalan_ayah_end && $l->hafalan_ayah_end >= $l->hafalan_ayah_start) {
                    return (int) ($l->hafalan_ayah_end - $l->hafalan_ayah_start + 1);
                }
                return (int) ($l->hafalan_baris ?: 0);
            });

            $targetCapaian = $isCurrent ? $surahTarget : null;
            $predikatGrade = $isCurrent ? 'Sedang Berjalan' : null;
            $setoranCount = $logsForGrade->count();
            if ($isCurrent) {
                $totalAyatGrade = $totalAyat;
                // Older records may not yet carry class_level metadata.
                if ($setoranCount === 0) {
                    $setoranCount = $allLogs->count();
                }
            }

            $gradeSummaries[] = [
                'grade' => $g,
                'label' => 'Kelas ' . $g,
                'is_current' => $isCurrent,
                'total_ayat' => $totalAyatGrade,
                'target_surah' => $targetCapaian,
                'predikat' => $predikatGrade,
                'total_setoran' => $setoranCount,
                'class_name' => $gradeClass?->nama_kelas ?? $gradeClass?->name,
                'unit_name' => $gradeClass?->unitPendidikan?->name ?? $unitName,
                'status_label' => $isCurrent ? 'Kelas Aktif Berjalan' : null,
                'logs' => $logsForGrade->values(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $paginator,
            'kpi' => [
                'total_ayat' => $totalAyat,
                'total_juz' => DB::table('tahfizh_ayah_achievements')->where('student_id', $student->id)->where('status', 'validated')->whereNotNull('juz_number')->distinct('juz_number')->count('juz_number'),
                'this_month_count' => $thisMonthCount,
                'target_surah' => $surahTarget,
                'target_ayat' => $targetAyat,
                'tab_counts' => [
                    'all' => $allLogs->count(),
                    'ziyadah' => $ziyadahCount,
                    'tilawah' => $tilawahCount,
                    'murajaah' => $murajaahCount,
                ],
            ],
            'student' => [
                'id' => $student->id,
                'name' => $student->full_name ?? $student->nama_lengkap ?? $student->name,
                'full_name' => $student->full_name ?? $student->nama_lengkap ?? $student->name,
                'nis' => $student->nis ?? $student->nisn ?? null,
                'unit_name' => $unitName,
                'unit_level' => $normalizedLevel,
                'class_name' => $classObj?->nama_kelas ?? $classObj?->name ?? $student->class_name ?? null,
                'current_grade' => $currentGradeNum,
            ],
            'grade_journey' => [
                'unit_name' => $unitName,
                'unit_level' => $normalizedLevel,
                'current_grade' => $currentGradeNum,
                'grades' => $gradeSummaries,
            ],
            'tab_counts' => [
                'all' => $allLogs->count(),
                'ziyadah' => $ziyadahCount,
                'tilawah' => $tilawahCount,
                'murajaah' => $murajaahCount,
            ],
            'tahfizh_target' => [
                'surah_target' => $surahTarget,
                'target_ayat' => $targetAyat,
            ],
        ]);
    }

    public function mutabaah(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $date = $request->query('date', now()->toDateString());

        $portalService = app(\App\Services\MutabaahPortalService::class);
        $overview = $portalService->overview($student, ['date' => $date]);

        $header = MutabaahDailyHeader::query()
            ->with(['details.agendaItem.category', 'details.templateItem'])
            ->where('student_id', $student->id)
            ->whereDate('activity_date', $date)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $header ?: ($overview['today'] ?? null),
            'overview' => $overview,
            'today' => $overview['today'] ?? null,
            'weekly' => $overview['weekly'] ?? null,
            'monthly' => $overview['monthly'] ?? null,
            'student' => $overview['student'] ?? null,
        ]);
    }

    public function saveMutabaahStudent(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $date = $request->query('date', now()->toDateString());

        // Header mutabaah memerlukan assignment supervisor & template aktif
        // (kolom wajib di mutabaah_daily_headers). Tanpa assignment aktif,
        // siswa tidak dapat membuat checklist secara langsung.
        $assignment = MutabaahSupervisorAssignment::query()
            ->active()
            ->byDate($date)
            ->where('education_unit_id', $student->unit_id ?? $student->education_unit_id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $assignment) {
            return response()->json(['success' => false, 'message' => 'Belum ada agenda mutaba' . 'ah aktif untuk siswa ini.'], 422);
        }

        $template = $assignment->template_id
            ? MutabaahTemplate::query()->find($assignment->template_id)
            : null;

        if (! $template) {
            return response()->json(['success' => false, 'message' => 'Template agenda mutaba' . 'ah belum ditetapkan.'], 422);
        }

        $header = MutabaahDailyHeader::firstOrCreate(
            [
                'student_id' => $student->id,
                'activity_date' => Carbon::parse($date)->startOfDay(),
                'template_id' => $template->id,
            ],
            [
                'supervisor_assignment_id' => $assignment->id,
                'education_unit_id' => $assignment->education_unit_id,
                'kelas_id' => $assignment->kelas_id,
                'rombel_id' => $assignment->rombel_id,
                'academic_year_id' => $assignment->academic_year_id,
                'semester_id' => $assignment->semester_id,
                'status' => 'draft',
                'total_items' => $template->items()->count(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Checklist mutabaah siswa berhasil diperbarui.',
            'data' => $header->load('details'),
        ]);
    }

    public function studentNotes(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $isParent = ParentModel::query()->where('user_id', $request->user()->id)->exists();

        $notes = StudentNote::query()
            ->with('teacher')
            ->where('student_id', $student->id)
            ->when($isParent, fn ($q) => $q->where(fn ($sq) => $sq->where('visible_to_parent', true)->orWhereNull('visible_to_parent')))
            ->when(! $isParent, fn ($q) => $q->where(fn ($sq) => $sq->where('visible_to_student', true)->orWhereNull('visible_to_student')))
            ->orderBy('date', 'desc')
            ->paginate(15);

        // Sertakan status tanda tangan (signed / signed_updated / unsigned).
        $notes->getCollection()->transform(function (StudentNote $note) {
            $hash = $note->signature_content_hash;
            $signed = $note->signed_by_user_id !== null && $note->signed_at !== null;
            $stale = $signed && $hash !== null && $hash !== StudentNote::contentHash($note->content);
            $note->setAttribute('signature_status', $signed ? ($stale ? 'signed_updated' : 'signed') : 'unsigned');
            $note->setAttribute('signature_stale', $stale);

            return $note;
        });

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    public function achievements(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $achievements = RekapPrestasiSiswa::query()
            ->where('id_siswa', $student->id)
            ->orderBy('tanggal_prestasi', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $achievements,
        ]);
    }

    public function announcements(Request $request): JsonResponse
    {
        $role = $request->user()?->getRoleNames()->first();
        $announcements = PengumumanSekolah::query()
            ->where('status_aktif', true)
            ->where('mulai_tampil', '<=', now())
            ->where(fn ($query) => $query->whereNull('selesai_tampil')->orWhere('selesai_tampil', '>=', now()))
            ->when($role, fn ($query) => $query->where(fn ($target) => $target
                ->whereNull('target_peran')
                ->orWhereJsonContains('target_peran', $role)))
            ->orderByDesc('prioritas')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }

    /**
     * Aggregator read-only Informasi Sekolah. Konten tetap berasal dari
     * pengumuman_sekolahs; tipe/unit/target memakai data_tambahan existing.
     */
    public function schoolInformation(Request $request): JsonResponse
    {
        $user = $request->user();
        $isParentUser = $user && $user->hasAnyRole(['Orang Tua', 'orang_tua', 'Parent', 'parent', 'wali_murid']);
        $hasSpecificChildRequested = $request->filled('child_id') || $request->filled('student_id') || $request->header('X-Child-Id');

        $student = null;
        if ($hasSpecificChildRequested || ! $isParentUser) {
            $student = $this->getStudentContext($request);
            if ($hasSpecificChildRequested && ! $student) {
                return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
            }
        }

        if (! $student && ! $isParentUser && ! ($user && $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan', 'Pengurus', 'pengurus', 'Guru', 'guru', 'Teacher', 'teacher', 'Kepala Sekolah', 'kepala_sekolah', 'Tata Usaha', 'tata_usaha', 'Musyrif', 'musyrif', 'Staff', 'staff']))) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $request->validate([
            'type' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:60'],
            'priority' => ['nullable', 'string', 'max:30'],
            'read_status' => ['nullable', 'in:read,unread'],
            'bookmarked' => ['nullable', 'boolean'],
            'has_attachment' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $state = $this->schoolInformationState($request);
        $readIds = array_keys($state['read']);
        $bookmarkIds = array_keys($state['bookmarks']);
        $query = $this->schoolInformationQuery($request, $student);

        $query
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($search) => $search
                ->where('judul_pengumuman', 'like', '%'.$request->string('search').'%')
                ->orWhere('isi_pengumuman', 'like', '%'.$request->string('search').'%')))
            ->when($request->filled('type') && $request->type !== 'all', function ($q) use ($request) {
                if ($request->type === 'calendar') {
                    $q->where(function ($sub) {
                        $sub->where('data_tambahan->tipe', 'calendar')
                            ->orWhereNotNull('data_tambahan->event')
                            ->orWhereIn('data_tambahan->kategori', ['kalender', 'kalender pendidikan', 'ujian', 'libur_sekolah', 'libur_semester', 'mulai_kbm', 'terima_rapor']);
                    });
                } elseif ($request->type === 'event') {
                    $q->where(function ($sub) {
                        $sub->where('data_tambahan->tipe', 'event')
                            ->orWhereNotNull('data_tambahan->event')
                            ->orWhereIn('data_tambahan->kategori', ['agenda', 'kegiatan', 'event']);
                    });
                } elseif ($request->type === 'news') {
                    $q->where(function ($sub) {
                        $sub->where('data_tambahan->tipe', 'news')
                            ->orWhereIn('data_tambahan->kategori', ['berita', 'prestasi', 'news']);
                    });
                } elseif ($request->type === 'circular') {
                    $q->where(function ($sub) {
                        $sub->where('data_tambahan->tipe', 'circular')
                            ->orWhereIn('data_tambahan->kategori', ['surat edaran', 'edaran', 'circular']);
                    });
                } elseif ($request->type === 'gallery') {
                    $q->where(function ($sub) {
                        $sub->where('data_tambahan->tipe', 'gallery')
                            ->orWhereIn('data_tambahan->kategori', ['galeri', 'gallery', 'dokumentasi']);
                    });
                } elseif ($request->type === 'announcement') {
                    $q->where(function ($sub) {
                        $sub->where('data_tambahan->tipe', 'announcement')
                            ->orWhere(function ($ann) {
                                $ann->whereNull('data_tambahan->tipe')
                                    ->whereNull('data_tambahan->event')
                                    ->whereNotIn('data_tambahan->kategori', [
                                        'kalender', 'kalender pendidikan', 'ujian', 'libur_sekolah',
                                        'libur_semester', 'mulai_kbm', 'terima_rapor', 'agenda',
                                        'kegiatan', 'event', 'berita', 'prestasi', 'news',
                                        'surat edaran', 'edaran', 'circular', 'galeri', 'gallery'
                                    ]);
                            });
                    });
                } else {
                    $q->where('data_tambahan->tipe', $request->type);
                }
            })
            ->when($request->filled('category'), fn ($q) => $q->where('data_tambahan->kategori', $request->category))
            ->when($request->filled('priority'), fn ($q) => $q->where('data_tambahan->prioritas', $request->priority))
            ->when($request->boolean('has_attachment'), fn ($q) => $q->whereNotNull('data_tambahan->lampiran'))
            ->when($request->date_from, fn ($q, $date) => $q->whereDate('mulai_tampil', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->whereDate('mulai_tampil', '<=', $date))
            ->when($request->read_status === 'read', fn ($q) => $q->whereIn('id', $readIds ?: ['__none__']))
            ->when($request->read_status === 'unread', fn ($q) => $q->when($readIds, fn ($inner) => $inner->whereNotIn('id', $readIds)))
            ->when($request->boolean('bookmarked'), fn ($q) => $q->whereIn('id', $bookmarkIds ?: ['__none__']));

        $items = $query->orderByDesc('prioritas')->orderByDesc('mulai_tampil')->paginate($request->integer('per_page', 50));
        $items->getCollection()->transform(fn ($item) => $this->mapSchoolInformation($item, $state));

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function schoolInformationSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $isParentUser = $user && $user->hasAnyRole(['Orang Tua', 'orang_tua', 'Parent', 'parent', 'wali_murid']);
        $hasSpecificChildRequested = $request->filled('child_id') || $request->filled('student_id') || $request->header('X-Child-Id');

        $student = null;
        if ($hasSpecificChildRequested || ! $isParentUser) {
            $student = $this->getStudentContext($request);
            if ($hasSpecificChildRequested && ! $student) {
                return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
            }
        }

        if (! $student && ! $isParentUser && ! ($user && $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan', 'Pengurus', 'pengurus', 'Guru', 'guru', 'Teacher', 'teacher', 'Kepala Sekolah', 'kepala_sekolah', 'Tata Usaha', 'tata_usaha', 'Musyrif', 'musyrif', 'Staff', 'staff']))) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $state = $this->schoolInformationState($request);
        $items = $this->schoolInformationQuery($request, $student)
            ->orderByDesc('prioritas')->orderByDesc('mulai_tampil')->get()
            ->map(fn ($item) => $this->mapSchoolInformation($item, $state));
        $types = ['announcement', 'event', 'news', 'circular', 'calendar', 'gallery'];
        $counts = ['all' => $items->count()];
        foreach ($types as $type) {
            $counts[$type] = $items->where('type', $type)->count();
        }

        $eduUnit = $student?->educationUnit ?: $student?->kelas?->unitPendidikan;
        if (! $eduUnit && $student && ($student->education_unit_id || $student->unit_id)) {
            $eduUnit = \App\Models\EducationUnit::find($student->education_unit_id ?: $student->unit_id);
        }
        if (! $eduUnit && $student && $student->kelas_id) {
            $eduUnit = \App\Models\Kelas::with('unitPendidikan')->find($student->kelas_id)?->unitPendidikan;
        }
        if (! $eduUnit && $user?->employee?->education_unit_id) {
            $eduUnit = \App\Models\EducationUnit::find($user->employee->education_unit_id);
        }
        if (! $eduUnit && ($user?->education_unit_id ?? $user?->unit_id)) {
            $eduUnit = \App\Models\EducationUnit::find($user->education_unit_id ?? $user->unit_id);
        }

        return response()->json(['success' => true, 'data' => [
            'unit' => $eduUnit,
            'important' => $items->filter(fn ($item) => in_array($item['priority'], ['mendesak', 'penting'], true))->take(3)->values(),
            'counts' => $counts,
            'upcoming_events' => $items->filter(fn ($i) => $i['type'] === 'event' || $i['type'] === 'calendar' || !empty($i['event']))->take(6)->values(),
            'latest_news' => $items->where('type', 'news')->take(6)->values(),
            'latest_circulars' => $items->where('type', 'circular')->take(5)->values(),
            'calendar' => $items->filter(fn ($i) => $i['type'] === 'calendar' || !empty($i['event']))->values(),
            'galleries' => $items->where('type', 'gallery')->take(8)->values(),
            'unread_count' => $items->where('is_read', false)->count(),
            'bookmarked_count' => $items->where('is_bookmarked', true)->count(),
        ]]);
    }

    public function updateSchoolInformationState(Request $request, string $informationId): JsonResponse
    {
        if (! Str::isUuid($informationId)) {
            return response()->json(['success' => false, 'message' => 'Informasi tidak ditemukan.'], 404);
        }

        $user = $request->user();
        $isStaffOrTeacher = $user && $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan', 'Pengurus', 'pengurus', 'Guru', 'guru', 'Teacher', 'teacher', 'Kepala Sekolah', 'kepala_sekolah', 'Tata Usaha', 'tata_usaha', 'Musyrif', 'musyrif', 'Staff', 'staff']);
        $student = $this->getStudentContext($request);
        abort_unless($student || $isStaffOrTeacher, 404, 'Data pengguna atau siswa tidak ditemukan.');

        $item = $this->schoolInformationQuery($request, $student)->whereKey($informationId)->firstOrFail();
        $payload = $request->validate(['action' => ['required', 'in:read,bookmark,unbookmark,acknowledge']]);
        $metadata = $user->metadata ?? [];
        $portalState = $metadata['portal_school_information'] ?? [];
        $key = $payload['action'] === 'bookmark' || $payload['action'] === 'unbookmark' ? 'bookmarks' : ($payload['action'] === 'acknowledge' ? 'acknowledgements' : 'read');
        $values = $portalState[$key] ?? [];
        if ($payload['action'] === 'unbookmark') {
            unset($values[$informationId]);
        } else {
            $values[$informationId] = now()->toIso8601String();
        }
        $portalState[$key] = $values;
        if (in_array($payload['action'], ['read', 'acknowledge'], true)) {
            $portalState['read'][$informationId] = now()->toIso8601String();
        }
        $metadata['portal_school_information'] = $portalState;
        $user->forceFill(['metadata' => $metadata])->save();

        return response()->json(['success' => true, 'message' => 'Status informasi berhasil diperbarui.', 'data' => $this->mapSchoolInformation($item, $this->schoolInformationState($request))]);
    }

    public function markAllSchoolInformationRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaffOrTeacher = $user && $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan', 'Pengurus', 'pengurus', 'Guru', 'guru', 'Teacher', 'teacher', 'Kepala Sekolah', 'kepala_sekolah', 'Tata Usaha', 'tata_usaha', 'Musyrif', 'musyrif', 'Staff', 'staff']);
        $student = $this->getStudentContext($request);
        abort_unless($student || $isStaffOrTeacher, 404, 'Data pengguna atau siswa tidak ditemukan.');

        $metadata = $user->metadata ?? [];
        $state = $metadata['portal_school_information'] ?? [];
        foreach ($this->schoolInformationQuery($request, $student)->pluck('id') as $id) {
            $state['read'][$id] = now()->toIso8601String();
        }
        $metadata['portal_school_information'] = $state;
        $user->forceFill(['metadata' => $metadata])->save();

        return response()->json(['success' => true, 'message' => 'Semua informasi telah ditandai dibaca.']);
    }

    private function schoolInformationQuery(Request $request, ?Student $student = null)
    {
        $user = $request->user();
        $role = $user?->getRoleNames()->first();
        
        $isCalendar = $request->type === 'calendar' || $request->input('category') === 'kalender' || $request->input('category') === 'calendar' || $request->routeIs('*summary*') || $request->is('*summary*');

        $unitIds = [];
        $classIds = [];
        $unitNames = [];
        if ($student) {
            $unitIds = array_values(array_unique(array_filter([
                $student->education_unit_id,
                $student->unit_id ?? null,
                $student->kelas?->unit_pendidikan_id ?? null,
                $student->kelas?->education_unit_id ?? null,
                $student->educationUnit?->id ?? null,
            ])));
            $classIds = array_values(array_unique(array_filter([$student->class_id, $student->kelas_id])));
            $uName = $student->unit_name ?: ($student->educationUnit?->nama_unit ?: ($student->educationUnit?->name ?: ($student->kelas?->unitPendidikan?->nama_unit ?: $student->kelas?->unitPendidikan?->name)));
            if ($uName) {
                $unitNames[] = $uName;
            }
        } elseif ($user && $user->hasAnyRole(['Orang Tua', 'orang_tua', 'Parent', 'parent', 'wali_murid'])) {
            $parent = ParentModel::query()->where('user_id', $user->id)->first();
            if ($parent) {
                $parentStudents = $this->parentStudentsQuery($parent)->with(['kelas.unitPendidikan', 'educationUnit'])->where('is_active', true)->get();
                foreach ($parentStudents as $c) {
                    $uIds = array_filter([
                        $c->education_unit_id,
                        $c->unit_id ?? null,
                        $c->kelas?->unit_pendidikan_id ?? null,
                        $c->kelas?->education_unit_id ?? null,
                        $c->educationUnit?->id ?? null,
                    ]);
                    foreach ($uIds as $uid) {
                        $unitIds[] = $uid;
                    }
                    if ($c->class_id) $classIds[] = $c->class_id;
                    if ($c->kelas_id) $classIds[] = $c->kelas_id;
                    $cUnit = $c->unit_name ?: ($c->educationUnit?->nama_unit ?: ($c->educationUnit?->name ?: ($c->kelas?->unitPendidikan?->nama_unit ?: $c->kelas?->unitPendidikan?->name)));
                    if ($cUnit) {
                        $unitNames[] = $cUnit;
                    }
                }
                $unitIds = array_values(array_unique(array_filter($unitIds)));
                $classIds = array_values(array_unique(array_filter($classIds)));
                $unitNames = array_values(array_unique(array_filter($unitNames)));
            }
        } elseif ($user && $user->hasAnyRole(['Guru', 'guru', 'Teacher', 'teacher', 'Kepala Sekolah', 'kepala_sekolah', 'Tata Usaha', 'tata_usaha', 'Musyrif', 'musyrif'])) {
            $empUnit = $user->employee?->education_unit_id ?? $user->education_unit_id ?? $user->unit_id ?? null;
            if ($empUnit) {
                $unitIds = [$empUnit];
            }
            $uName = $user->employee?->unit_name ?? $user->employee?->educationUnit?->name ?? null;
            if ($uName) {
                $unitNames[] = $uName;
            }
        }

        $isFullAccess = $user && $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan', 'Pengurus', 'pengurus']) && ! $student;

        return PengumumanSekolah::query()
            ->where('status_aktif', true)
            ->when(! $isCalendar, function ($q) {
                $q->where(function ($dateSub) {
                    $dateSub->where(function ($activeSub) {
                        $activeSub->where('mulai_tampil', '<=', now())
                            ->where(fn ($sub) => $sub->whereNull('selesai_tampil')->orWhere('selesai_tampil', '>=', now()));
                    })->orWhereNotNull('data_tambahan->event')
                      ->orWhere('data_tambahan->tipe', 'calendar')
                      ->orWhereIn('data_tambahan->kategori', ['kalender', 'kalender pendidikan', 'ujian', 'libur_sekolah', 'libur_semester', 'mulai_kbm', 'terima_rapor']);
                });
            })
            ->when($isCalendar, function ($q) {
                $q->where(fn ($sub) => $sub->whereNull('selesai_tampil')->orWhere('selesai_tampil', '>=', now()->subYear()));
            })
            ->when($user && ! $isFullAccess, function ($q) use ($user) {
                $driver = DB::connection()->getDriverName();
                $userRoles = $user->getRoleNames()->toArray();
                $q->where(function ($target) use ($driver, $userRoles) {
                    $target->whereNull('target_peran');
                    if ($driver === 'pgsql') {
                        $target->orWhereRaw("target_peran::text ILIKE '%all%'");
                    } else {
                        $target->orWhere('target_peran', 'like', '%all%');
                    }
                    foreach ($userRoles as $r) {
                        $target->orWhereJsonContains('target_peran', $r);
                        $rLower = strtolower($r);
                        if (in_array($rLower, ['tata usaha', 'tu', 'tata_usaha', 'operator'], true)) {
                            $target->orWhereJsonContains('target_peran', 'Tata Usaha')
                                ->orWhereJsonContains('target_peran', 'tata_usaha')
                                ->orWhereJsonContains('target_peran', 'TU')
                                ->orWhereJsonContains('target_peran', 'tu')
                                ->orWhereJsonContains('target_peran', 'Staff')
                                ->orWhereJsonContains('target_peran', 'staff');
                        }
                    }
                });
            })
            ->when(! $isFullAccess, function ($q) use ($request, $unitIds, $unitNames) {
                $strictUnit = $request->boolean('strict_unit');
                $q->where(function ($sub) use ($strictUnit, $unitIds, $unitNames) {
                    if (! $strictUnit) {
                        $sub->where(function ($yayasanSub) {
                            $yayasanSub->where('data_tambahan->unit_id', 'all')
                                ->orWhere('data_tambahan->education_unit_id', 'all')
                                ->orWhere('data_tambahan->is_public', true)
                                ->orWhere('data_tambahan->target_unit', 'all')
                                ->orWhere('data_tambahan->target_unit_name', 'like', '%Semua%')
                                ->orWhere('data_tambahan->target_unit_name', 'like', '%Yayasan%')
                                ->orWhere('data_tambahan->unit_name', 'like', '%Semua%')
                                ->orWhere('data_tambahan->unit_name', 'like', '%Yayasan%')
                                ->orWhere(function ($nullSub) {
                                    $nullSub->whereNull('data_tambahan->unit_id')
                                        ->whereNull('data_tambahan->education_unit_id')
                                        ->whereNull('data_tambahan->unit_name');
                                });
                        });
                    }
                    if (! empty($unitIds)) {
                        if ($strictUnit) {
                            $sub->where(function ($strictSub) use ($unitIds, $unitNames) {
                                $strictSub->whereIn('data_tambahan->unit_id', $unitIds)
                                    ->orWhereIn('data_tambahan->education_unit_id', $unitIds);
                                if (! empty($unitNames)) {
                                    foreach ($unitNames as $uName) {
                                        $strictSub->orWhere('data_tambahan->unit_name', 'like', '%'.$uName.'%')
                                            ->orWhere('data_tambahan->education_unit', 'like', '%'.$uName.'%');
                                    }
                                }
                            });
                        } else {
                            $sub->orWhereIn('data_tambahan->unit_id', $unitIds)
                                ->orWhereIn('data_tambahan->education_unit_id', $unitIds);
                            if (! empty($unitNames)) {
                                foreach ($unitNames as $uName) {
                                    $sub->orWhere('data_tambahan->unit_name', 'like', '%'.$uName.'%')
                                        ->orWhere('data_tambahan->education_unit', 'like', '%'.$uName.'%');
                                }
                            }
                        }
                    }
                });
            })
            ->when(! $isFullAccess && ! empty($classIds), function ($q) use ($classIds) {
                $q->where(fn ($sub) => $sub->whereNull('data_tambahan->class_id')
                    ->orWhereIn('data_tambahan->class_id', $classIds));
            });
    }

    private function schoolInformationState(Request $request): array
    {
        $state = ($request->user()?->metadata ?? [])['portal_school_information'] ?? [];
        return ['read' => $state['read'] ?? [], 'bookmarks' => $state['bookmarks'] ?? [], 'acknowledgements' => $state['acknowledgements'] ?? []];
    }

    private function mapSchoolInformation(PengumumanSekolah $item, array $state): array
    {
        $meta = $item->data_tambahan ?? [];
        $rawType = Str::lower($meta['tipe'] ?? '');
        $rawKat = Str::lower($meta['kategori'] ?? '');

        if (in_array($rawType, ['calendar', 'event', 'announcement', 'news', 'circular', 'gallery'])) {
            $type = $rawType;
        } elseif (isset($meta['event']) || in_array($rawKat, ['kalender', 'kalender pendidikan', 'ujian', 'libur_sekolah', 'libur_semester', 'mulai_kbm', 'terima_rapor'])) {
            $type = 'calendar';
        } elseif (in_array($rawKat, ['agenda', 'kegiatan', 'event'])) {
            $type = 'event';
        } elseif (in_array($rawKat, ['berita', 'prestasi', 'news'])) {
            $type = 'news';
        } elseif (in_array($rawKat, ['surat edaran', 'edaran', 'circular'])) {
            $type = 'circular';
        } elseif (in_array($rawKat, ['galeri', 'gallery', 'dokumentasi'])) {
            $type = 'gallery';
        } else {
            $type = 'announcement';
        }
        return [
            'id' => $item->id, 'type' => $type, 'category' => $meta['kategori'] ?? null,
            'priority' => Str::lower($meta['prioritas'] ?? ($item->prioritas ? 'penting' : 'umum')),
            'title' => $item->judul_pengumuman, 'summary' => Str::limit(strip_tags($item->isi_pengumuman), 180), 'content' => $item->isi_pengumuman,
            'published_at' => $item->mulai_tampil, 'expired_at' => $item->selesai_tampil,
            'education_unit' => $meta['education_unit'] ?? $meta['unit_name'] ?? $meta['target_unit_name'] ?? $meta['unit'] ?? null, 'audience' => $meta['target'] ?? null,
            'publisher' => $meta['penerbit'] ?? null, 'attachments' => $meta['lampiran'] ?? [], 'cover' => $meta['cover'] ?? $meta['gambar_url'] ?? null,
            'event' => $meta['event'] ?? null, 'calendar_date' => $meta['tanggal'] ?? null, 'gallery' => $meta['galeri'] ?? [],
            'color' => $meta['color'] ?? null,
            'requires_acknowledgement' => (bool) ($meta['perlu_konfirmasi'] ?? false),
            'is_read' => isset($state['read'][$item->id]), 'read_at' => $state['read'][$item->id] ?? null,
            'is_bookmarked' => isset($state['bookmarks'][$item->id]), 'acknowledged_at' => $state['acknowledgements'][$item->id] ?? null,
        ];
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $this->getStudentContext($request);

        $query = Notification::userQuery($user->id);

        // Child scope: bila ananda tertentu dipilih, utamakan notifikasi khusus miliknya
        // (presensi, tahfizh, tugas, lms) atau pengumuman umum.
        if ($student) {
            $query->where(function ($q) use ($student) {
                $q->where(function ($sub) use ($student) {
                    $sub->where('metadata->student_id', (string) $student->id)
                        ->orWhere('metadata->child_id', (string) $student->id);
                })
                ->orWhere(function ($sub) use ($student) {
                    $sub->where('notifiable_type', Student::class)
                        ->where('notifiable_id', $student->id);
                })
                ->orWhere(function ($sub) {
                    $sub->whereNull('metadata->student_id')
                        ->whereNull('metadata->child_id')
                        ->where('notifiable_type', '!=', Student::class);
                });
            });
        }

        $notifications = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $this->getStudentContext($request);

        $query = Notification::byUser($user->id)->unread();

        if ($student) {
            $query->where(function ($q) use ($student) {
                $q->where('metadata->student_id', (string) $student->id)
                  ->orWhere('metadata->child_id', (string) $student->id)
                  ->orWhere(fn ($sub) => $sub->where('notifiable_type', Student::class)->where('notifiable_id', $student->id));
            });
        }

        $canonical = Notification::usesCanonicalSchema();
        if ($canonical) {
            $query->update(['read_at' => now()]);
        } else {
            $query->update(['is_read' => true, 'read_at' => now()]);
        }

        \Illuminate\Support\Facades\Cache::forget("notif_unread:{$user->id}");
        \Illuminate\Support\Facades\Cache::forget("notif_unread_v2:{$user->id}");

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil ditandai telah dibaca.',
        ]);
    }

    public function bills(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $isParent = \App\Models\ParentModel::query()->where('user_id', $user->id)->exists()
                || $user->hasAnyRole(['Orang Tua', 'orang_tua', 'Parent', 'parent', 'wali', 'Wali Murid']);
            $isStudent = \App\Models\Student::query()->where('user_id', $user->id)->exists()
                || $user->hasAnyRole(['Siswa', 'siswa', 'Student', 'student']);
            $isPrivilegedStaff = $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan', 'Tata Usaha', 'tu', 'Operator', 'operator']);

            if (! $isParent && ! $isStudent && ! $isPrivilegedStaff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Informasi tagihan dan SPP khusus untuk akun Orang Tua/Wali Murid dan Siswa.',
                ], 403);
            }
        }

        $student = $this->getStudentContext($request);
        if (! $student) return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);

        $unit = $student->educationUnit;
        $unitMeta = is_array($unit?->metadata) ? $unit->metadata : [];
        $siteSetting = \App\Models\SiteSetting::current();

        $studentMeta = is_array($student->metadata) ? $student->metadata : [];
        $bsiPrefix = $siteSetting->bsi_va_prefix ?: '9023';
        $studentVa = $studentMeta['no_va'] ?? $studentMeta['nis_pembayaran'] ?? null;
        if (!$studentVa && !empty($student->nis)) {
            $studentVa = $bsiPrefix . preg_replace('/\D/', '', (string) $student->nis);
        }

        $paymentInfo = [
            'bank_name' => $siteSetting->bank_name ?: ($unitMeta['bank_name'] ?? null),
            'account_number' => $siteSetting->bank_account_number ?: ($unitMeta['bank_account_number'] ?? null),
            'account_holder' => $siteSetting->bank_account_name ?: ($unitMeta['bank_account_holder'] ?? ($unit?->name ?: ($siteSetting->school_name ?: null))),
            'institution_name' => $unit?->name ?: ($siteSetting->school_name ?: null),
            'student_name' => $student->full_name,
            'student_nis' => $student->nis,
            'student_va' => $studentVa,
            'va_bank_name' => 'Bank Syariah Indonesia (BSI)',
            'va_biller_code' => $bsiPrefix,
        ];

        // Cek integrasi SIPDEI Billing (Pihak Ketiga)
        $nisPembayaran = $studentMeta['nis_pembayaran'] ?? $studentMeta['no_pendaftaran'] ?? $student->nis ?? null;
        $sipdeiService = app(\App\Services\SipdeiBillingService::class);

        if ($nisPembayaran && $sipdeiService->isConfigured()) {
            $filters = [];
            if ($request->filled('status_tagihan')) {
                $filters['status_tagihan'] = (string) $request->query('status_tagihan');
            }
            if ($request->filled('id_tahun_ajaran')) {
                $filters['id_tahun_ajaran'] = (int) $request->query('id_tahun_ajaran');
            }
            if ($request->filled('bulan_tagihan')) {
                $filters['bulan_tagihan'] = (string) $request->query('bulan_tagihan');
            }
            if ($request->filled('bulan_tagihan_end')) {
                $filters['bulan_tagihan_end'] = (string) $request->query('bulan_tagihan_end');
            }
            if ($request->filled('limit')) {
                $filters['limit'] = min(100, max(1, (int) $request->query('limit', 20)));
            } else {
                $filters['limit'] = 20;
            }
            if ($request->filled('page')) {
                $filters['page'] = max(1, (int) $request->query('page', 1));
            }

            $forceRefresh = $request->boolean('refresh', false);
            $sipdeiResult = $sipdeiService->getBillingHistory((string) $nisPembayaran, $filters, $forceRefresh);

            if (!empty($sipdeiResult['success']) && !empty($sipdeiResult['data']['tagihan'])) {
                $sipdeiBills = $sipdeiService->formatBillsForPortal($sipdeiResult['data']['tagihan'], $student);
                $pagination = $sipdeiResult['pagination'] ?? [];
                $currentPage = (int) ($pagination['page'] ?? 1);
                $perPage = (int) ($pagination['limit'] ?? count($sipdeiBills));
                $total = (int) ($pagination['total'] ?? count($sipdeiBills));
                $lastPage = (int) ($pagination['pages'] ?? 1);

                $billsPayload = [
                    'current_page' => $currentPage,
                    'data' => $sipdeiBills,
                    'first_page_url' => $request->url() . '?page=1',
                    'from' => count($sipdeiBills) > 0 ? (($currentPage - 1) * $perPage) + 1 : null,
                    'last_page' => $lastPage,
                    'last_page_url' => $request->url() . '?page=' . $lastPage,
                    'links' => [],
                    'next_page_url' => $currentPage < $lastPage ? $request->url() . '?page=' . ($currentPage + 1) : null,
                    'path' => $request->url(),
                    'per_page' => $perPage,
                    'prev_page_url' => $currentPage > 1 ? $request->url() . '?page=' . ($currentPage - 1) : null,
                    'to' => count($sipdeiBills) > 0 ? min($total, $currentPage * $perPage) : null,
                    'total' => $total,
                ];

                return response()->json([
                    'success' => true,
                    'source' => 'sipdei',
                    'data' => $billsPayload,
                    'payment_info' => $paymentInfo,
                    'sipdei_meta' => [
                        'nis_pembayaran' => $nisPembayaran,
                        'nama_lengkap' => $sipdeiResult['data']['nama_lengkap'] ?? $student->full_name,
                        'from_cache' => $sipdeiResult['from_cache'] ?? false,
                    ],
                ]);
            }

            // Murni dari API SIPDEI: jika tidak ditemukan tagihan di SIPDEI, kembalikan tagihan kosong
            return response()->json([
                'success' => true,
                'source' => 'sipdei',
                'message' => $sipdeiResult['message'] ?? 'Belum ada data tagihan di sistem SIPDEI.',
                'data' => [
                    'current_page' => 1,
                    'data' => [],
                    'from' => null,
                    'last_page' => 1,
                    'per_page' => 20,
                    'to' => null,
                    'total' => 0,
                ],
                'payment_info' => $paymentInfo,
                'sipdei_meta' => [
                    'nis_pembayaran' => $nisPembayaran,
                    'status_code' => $sipdeiResult['status_code'] ?? 404,
                    'message' => $sipdeiResult['message'] ?? 'NIS Pembayaran tidak ditemukan di SIPDEI.',
                ],
            ]);
        }

        // Jika SIPDEI belum dikonfigurasi, kembalikan data kosong murni
        return response()->json([
            'success' => true,
            'source' => 'sipdei',
            'message' => 'Layanan SIPDEI belum dikonfigurasi atau NIS pembayaran belum terdaftar.',
            'data' => [
                'current_page' => 1,
                'data' => [],
                'total' => 0,
                'last_page' => 1,
            ],
            'payment_info' => $paymentInfo,
        ]);
    }

    public function createVaPayment(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $isParent = \App\Models\ParentModel::query()->where('user_id', $user->id)->exists()
                || $user->hasAnyRole(['Orang Tua', 'orang_tua', 'Parent', 'parent', 'wali', 'Wali Murid']);
            $isStudent = \App\Models\Student::query()->where('user_id', $user->id)->exists()
                || $user->hasAnyRole(['Siswa', 'siswa', 'Student', 'student']);
            $isPrivilegedStaff = $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan', 'Tata Usaha', 'tu', 'Operator', 'operator']);

            if (! $isParent && ! $isStudent && ! $isPrivilegedStaff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Fitur pembayaran Virtual Account khusus untuk Orang Tua/Wali Murid dan Siswa.',
                ], 403);
            }
        }

        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'bank' => ['required', 'string', 'in:NAGARI,BSI,nagari,bsi'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.jenis' => ['required', 'string', 'in:TAGIHAN,TUNGGAKAN,DANA_ORTU_ASUH,tagihan,tunggakan,dana_ortu_asuh'],
            'items.*.id' => ['required', 'integer'],
            'items.*.bayar' => ['required', 'integer', 'min:1'],
        ]);

        $studentMeta = is_array($student->metadata) ? $student->metadata : [];
        $nisPembayaran = $studentMeta['nis_pembayaran'] ?? $studentMeta['no_pendaftaran'] ?? $student->nis ?? null;

        if (! $nisPembayaran) {
            return response()->json([
                'success' => false,
                'message' => 'NIS Pembayaran siswa belum terdaftar di sistem SIPDEI.',
            ], 422);
        }

        $sipdeiService = app(\App\Services\SipdeiBillingService::class);
        if (! $sipdeiService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Layanan SIPDEI belum dikonfigurasi di server. Silakan hubungi Super Admin / Admin untuk mengisi API Key pada Pengaturan Sistem.',
            ], 503);
        }

        $result = $sipdeiService->createVaPayment(
            (string) $nisPembayaran,
            strtoupper($validated['bank']),
            $validated['items']
        );

        $statusCode = $result['status_code'] ?? ($result['success'] ? 200 : 422);
        return response()->json($result, $statusCode >= 200 && $statusCode < 600 ? $statusCode : 500);
    }

    public function signStudentNote(Request $request, string $noteId): JsonResponse
    {
        if (! Str::isUuid($noteId)) {
            return response()->json(['success' => false, 'message' => 'Catatan siswa tidak ditemukan.'], 404);
        }

        $user = $request->user();
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        // Tanda tangan hanya boleh dilakukan oleh Orang Tua yang terhubung
        // dengan siswa tersebut. Siswa tidak memberi tanda tangan sebagai
        // Orang Tua.
        $parent = ParentModel::query()->where('user_id', $user->id)->first();
        if (! $parent) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Orang Tua yang dapat menandatangani catatan siswa.',
            ], 403);
        }

        $isLinkedChild = $this->parentStudentsQuery($parent)->whereKey($student->id)->exists();
        if (! $isLinkedChild) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk menandatangani catatan siswa ini.',
            ], 403);
        }

        $note = StudentNote::where('student_id', $student->id)->find($noteId);
        if (! $note) {
            return response()->json(['success' => false, 'message' => 'Catatan siswa tidak ditemukan.'], 404);
        }

        abort_unless($note->visible_to_parent, 403, 'Catatan belum dipublikasikan untuk orang tua.');

        $parentNotes = $request->input('notes_parent') ?? $request->input('follow_up') ?? $note->follow_up;

        // Tanda tangan idempotent: hash isi saat ini dicocokkan untuk mendeteksi
        // perubahan versi dokumen. Isi yang berubah setelah tanda tangan
        // memerlukan tanda tangan ulang (signature_status = 'stale').
        $currentHash = StudentNote::contentHash($note->content);
        $signedVersion = $note->signature_content_hash;

        $note->update([
            'follow_up' => $parentNotes,
            'signed_by_user_id' => $user->id,
            'signed_at' => now(),
            'signature_content_hash' => $currentHash,
        ]);

        $stale = $signedVersion !== null && $signedVersion !== $currentHash;

        // Notifikasi balik ke Guru pembuat catatan
        try {
            $note->loadMissing(['teacher.employee']);
            $teacherUserId = $note->teacher?->user_id ?? $note->teacher?->employee?->user_id;
            if ($teacherUserId) {
                $parentName = $parent->full_name ?? $user->name ?? 'Orang Tua / Wali';
                $studentName = $student->full_name ?? 'Santri';
                $title = 'Buku Penghubung Ditandatangani';
                $body = "{$parentName} telah menandatangani catatan {$studentName}" . ($parentNotes ? ": \"{$parentNotes}\"" : '.');

                Notification::deliver(
                    (string) $teacherUserId,
                    $title,
                    $body,
                    'buku_penghubung',
                    [
                        'type' => 'student_note_signed',
                        'note_id' => $note->id,
                        'student_id' => $student->id,
                        'follow_up' => $parentNotes,
                        'signed_at' => now()->toIso8601String(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal mengirimkan notifikasi buku penghubung ke guru: ' . $e->getMessage());
        }

        // Realtime broadcast ke kanal santri/wali
        try {
            app(RealtimeBroadcastService::class)->broadcastStudentActivity(
                $note->student_id,
                [
                    'id' => 'note-signed-' . $note->id,
                    'type' => 'komentar',
                    'title' => 'Catatan ditandatangani orang tua',
                    'subtitle' => '“' . ($parentNotes ?: 'Telah disetujui & ditandatangani') . '”',
                    'date_label' => 'Hari ini',
                    'time_label' => now()->format('H:i'),
                    'badge_label' => 'Disetujui',
                    'badge_type' => 'emerald',
                    'has_red_dot' => false,
                    'icon' => 'check-decagram',
                    'icon_bg' => '#ECFDF5',
                    'icon_color' => '#059669',
                    'screen' => 'Komentar',
                    'verifier_role' => 'Orang Tua / Wali Santri',
                    'verifier_name' => $parent->full_name ?? $user->name ?? 'Orang Tua',
                ]
            );
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Catatan & persetujuan orang tua berhasil disimpan.',
            'data' => array_merge($note->toArray(), [
                'signed_at' => $note->signed_at?->toIso8601String(),
                'signed_by_user_id' => $user->id,
                // Status mengikuti state SETELAH penandatanganan: hash kini
                // cocok dengan isi versi terkini → selalu 'signed'. Bila tanda
                // tangan sebelumnya sudah basi, ditandai via signature_was_stale.
                'signature_status' => 'signed',
                'signature_was_stale' => $stale,
            ]),
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);

        $reports = LmsRapor::query()->with(['kelas', 'semester', 'tahunAjaran', 'waliKelas'])
            ->where('siswa_id', $student->id)->whereIn('status_rapor', ['published', 'diterbitkan', 'terbit'])
            ->orderBy('tanggal_terbit', 'desc')->get();

        return response()->json(['success' => true, 'data' => $reports]);
    }

    public function downloadReport(Request $request, string $id)
    {
        if (! Str::isUuid($id)) {
            return response()->json(['success' => false, 'message' => 'Rapor tidak tersedia.'], 404);
        }

        $student = $this->getStudentContext($request);
        $report = $student ? LmsRapor::with(['siswa', 'kelas', 'semester', 'tahunAjaran', 'waliKelas'])
            ->where('siswa_id', $student->id)->whereIn('status_rapor', ['published', 'diterbitkan', 'terbit'])->find($id) : null;
        if (! $report) return response()->json(['success' => false, 'message' => 'Rapor tidak tersedia.'], 404);

        $grades = StudentGrade::with('subject')->where('student_id', $student->id)->get();
        $report->update(['sudah_dilihat_ortu' => true]);
        return Pdf::loadView('reports.student-report', compact('report', 'grades'))
            ->setPaper('a4')->download('rapor-'.Str::slug($student->full_name).'.pdf');
    }



    /**
     * CBT MONITORING (read-only) — berlaku untuk Orang Tua maupun Siswa.
     * Hanya menampilkan jadwal/status/keikutsertaan; tidak ada soal maupun
     * tombol mulai (endpoint start/save/finish tetap role:Siswa).
     */
    public function examOverview(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $classIds = array_values(array_filter([$student->kelas_id, $student->class_id]));

        // Mata Pelajaran Resmi Berdasarkan Jadwal Pelajaran (ClassSchedule) Kelas Siswa
        $officialSubjects = ClassSchedule::query()
            ->with('subject')
            ->whereIn('kelas_id', $classIds)
            ->where('is_active', true)
            ->get()
            ->map(fn ($cs) => $cs->subject?->name)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (empty($officialSubjects)) {
            $unitId = $student->unit_id ?? $student->kelas?->unit_pendidikan_id;
            $jenjang = $student->kelas?->jenjang;
            $officialSubjects = Subject::query()
                ->where('unit_pendidikan_id', $unitId)
                ->where('status', true)
                ->when($jenjang, fn ($q, $j) => $q->where('jenjang', $j))
                ->pluck('name')
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        $exams = LmsUjian::query()
            ->with(['kisiKisi.subject', 'kelas', 'semester', 'guru'])
            ->with(['sesi' => fn ($query) => $query->where('siswa_id', $student->id)->latest('created_at')])
            ->whereIn('kelas_id', $classIds)
            ->whereIn('status', ['published', 'berlangsung', 'selesai'])
            ->orderBy('waktu_mulai')
            ->get();

        $now = now();
        $items = $exams->map(function (LmsUjian $exam) use ($now, $officialSubjects) {
            $sessions = $exam->sesi;
            $activeSession = $sessions->firstWhere('status', 'proses');
            $completed = $sessions->whereIn('status', ['selesai', 'timeout'])->count();
            $beforeStart = $exam->waktu_mulai && $now->lt($exam->waktu_mulai);
            $afterEnd = $exam->waktu_selesai && $now->gt($exam->waktu_selesai);
            $attemptsLeft = max(0, (int) $exam->max_attempt - $completed);
            $availability = $activeSession ? 'resume' : ($beforeStart ? 'upcoming' : ($afterEnd ? 'ended' : ($attemptsLeft > 0 ? 'available' : 'completed')));

            $resolvedSubject = $exam->kisiKisi?->subject?->name;
            if (! empty($officialSubjects)) {
                foreach ($officialSubjects as $offSubj) {
                    if (stripos($exam->judul_ujian, $offSubj) !== false) {
                        $resolvedSubject = $offSubj;
                        break;
                    }
                }
            }

            return [
                'id' => $exam->id,
                'judul_ujian' => $exam->judul_ujian,
                'instruksi' => $exam->instruksi,
                'waktu_mulai' => $exam->waktu_mulai?->toIso8601String(),
                'waktu_selesai' => $exam->waktu_selesai?->toIso8601String(),
                'durasi_menit' => (int) $exam->durasi_menit,
                'nilai_kkm' => (float) $exam->nilai_kkm,
                'max_attempt' => (int) $exam->max_attempt,
                'attempts_used' => $completed,
                'attempts_left' => $attemptsLeft,
                'status' => $exam->status,
                'availability' => $availability,
                'mata_pelajaran' => $resolvedSubject,
                'kelas' => $exam->kelas?->nama_kelas,
                'guru' => $exam->guru?->nama_lengkap,
                'kisi_kisi' => $exam->kisiKisi ? [
                    'id' => $exam->kisiKisi->id,
                    'judul' => $exam->kisiKisi->judul_kisi,
                    'jenis_ujian' => $exam->kisiKisi->jenis_ujian,
                    'jumlah_soal' => (int) $exam->kisiKisi->jumlah_soal,
                    'alokasi_waktu_menit' => (int) $exam->kisiKisi->alokasi_waktu_menit,
                    'kompetensi_dasar' => $exam->kisiKisi->kompetensi_dasar,
                    'level_kognitif' => $exam->kisiKisi->level_kognitif,
                    'distribusi_bobot' => $exam->kisiKisi->distribusi_bobot,
                ] : null,
                'latest_result' => ($latest = $sessions->firstWhere('status', 'selesai')) ? [
                    'sesi_id' => $latest->id,
                    'nilai_final' => $exam->tampilkan_nilai_langsung ? (float) $latest->nilai_final : null,
                    'nilai_tersedia' => (bool) $exam->tampilkan_nilai_langsung,
                    'jumlah_benar' => $exam->tampilkan_nilai_langsung ? (int) $latest->jumlah_benar : null,
                    'jumlah_salah' => $exam->tampilkan_nilai_langsung ? (int) $latest->jumlah_salah : null,
                    'jumlah_kosong' => $exam->tampilkan_nilai_langsung ? (int) $latest->jumlah_kosong : null,
                    'waktu_selesai' => $latest->waktu_selesai?->toIso8601String(),
                ] : null,
            ];
        })->values();

        $student->loadMissing(['kelas.unitPendidikan', 'educationUnit']);

        return response()->json([
            'success' => true,
            'student' => $student,
            'data' => [
                'student' => $student,
                'official_subjects' => $officialSubjects,
                'summary' => [
                    'total' => $items->count(),
                    'available' => $items->whereIn('availability', ['available', 'resume'])->count(),
                    'upcoming' => $items->where('availability', 'upcoming')->count(),
                    'completed' => $items->whereNotNull('latest_result')->count(),
                ],
                'exams' => $items,
            ],
        ]);
    }

    public function examGrids(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $classIds = array_values(array_filter([$student->kelas_id, $student->class_id]));
        $grids = LmsKisiKisi::query()
            ->with(['subject', 'kelas', 'guru'])
            ->whereIn('kelas_id', $classIds)
            ->where('status', true)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'student' => $student->loadMissing(['kelas.unitPendidikan', 'educationUnit']),
            'data' => $grids,
        ]);
    }

    public function bankSoal(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $classIds = array_values(array_filter([$student->kelas_id, $student->class_id]));
        $kisiIds = LmsKisiKisi::query()
            ->whereIn('kelas_id', $classIds)
            ->where('status', true)
            ->pluck('id')
            ->all();

        $query = LmsBankSoal::query()
            ->with(['subject', 'kisiKisi.guru', 'kisiKisi.kelas'])
            ->where('status', true);

        if (! empty($kisiIds)) {
            $query->where(function ($q) use ($kisiIds) {
                $q->whereIn('kisi_kisi_id', $kisiIds)
                  ->orWhereNull('kisi_kisi_id');
            });
        }

        if ($request->filled('search')) {
            $s = trim($request->string('search'));
            $query->where(function ($inner) use ($s) {
                $inner->where('pertanyaan', 'like', "%{$s}%")
                    ->orWhere('kode_soal', 'like', "%{$s}%")
                    ->orWhere('indikator', 'like', "%{$s}%")
                    ->orWhereHas('subject', fn ($sub) => $sub->where('name', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('mata_pelajaran_id')) {
            $query->where('mata_pelajaran_id', $request->mata_pelajaran_id);
        }

        if ($request->filled('tipe_soal')) {
            $query->where('tipe_soal', $request->tipe_soal);
        }

        if ($request->filled('tingkat_kesulitan')) {
            $query->where('tingkat_kesulitan', $request->tingkat_kesulitan);
        }

        if ($request->filled('kisi_kisi_id')) {
            $query->where('kisi_kisi_id', $request->kisi_kisi_id);
        }

        $perPage = (int) $request->get('per_page', 20);
        $questions = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'student' => $student->loadMissing(['kelas.unitPendidikan', 'educationUnit']),
            'data' => $questions,
        ]);
    }

    public function results(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $classIds = array_values(array_filter([$student->kelas_id, $student->class_id]));

        // Mata Pelajaran Resmi Berdasarkan Jadwal Pelajaran (ClassSchedule) Kelas Siswa
        $officialSubjects = ClassSchedule::query()
            ->with('subject')
            ->whereIn('kelas_id', $classIds)
            ->where('is_active', true)
            ->get()
            ->map(fn ($cs) => $cs->subject?->name)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (empty($officialSubjects)) {
            $unitId = $student->unit_id ?? $student->kelas?->unit_pendidikan_id;
            $jenjang = $student->kelas?->jenjang;
            $officialSubjects = Subject::query()
                ->where('unit_pendidikan_id', $unitId)
                ->where('status', true)
                ->when($jenjang, fn ($q, $j) => $q->where('jenjang', $j))
                ->pluck('name')
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        // CBT Results
        $cbtExams = LmsUjian::query()
            ->with(['kisiKisi.subject', 'guru'])
            ->with(['sesi' => fn ($q) => $q->where('siswa_id', $student->id)->whereIn('status', ['selesai', 'timeout'])->latest('updated_at')])
            ->whereIn('kelas_id', $classIds)
            ->whereHas('sesi', fn ($q) => $q->where('siswa_id', $student->id)->whereIn('status', ['selesai', 'timeout']))
            ->get()
            ->map(function ($exam) use ($officialSubjects) {
                $latest = $exam->sesi->first();
                $showScore = (bool) $exam->tampilkan_nilai_langsung;

                $resolvedSubject = $exam->kisiKisi?->subject?->name;
                if (! empty($officialSubjects)) {
                    foreach ($officialSubjects as $offSubj) {
                        if (stripos($exam->judul_ujian, $offSubj) !== false) {
                            $resolvedSubject = $offSubj;
                            break;
                        }
                    }
                }

                return [
                    'id' => 'cbt-'.$exam->id,
                    'type' => 'ujian',
                    'title' => $exam->judul_ujian,
                    'subject' => $resolvedSubject,
                    'teacher' => $exam->guru?->nama_lengkap,
                    'date' => $latest?->updated_at?->toIso8601String(),
                    'score' => $showScore ? (float) $latest?->nilai_final : null,
                    'is_published' => $showScore,
                    'correct' => $showScore ? (int) $latest?->jumlah_benar : null,
                    'wrong' => $showScore ? (int) $latest?->jumlah_salah : null,
                    'empty' => $showScore ? (int) $latest?->jumlah_kosong : null,
                    'kkm' => (float) $exam->nilai_kkm,
                    'status_tuntas' => $showScore ? (($latest?->nilai_final ?? 0) >= $exam->nilai_kkm ? 'Tuntas' : 'Belum Tuntas') : 'Menunggu Publikasi',
                ];
            });

        // Assignment Results
        $assignmentResults = LmsPengumpulanTugas::query()
            ->with(['penugasan.subject', 'penugasan.teacher'])
            ->where('siswa_id', $student->id)
            ->whereNotNull('nilai_guru')
            ->get()
            ->map(function ($submission) {
                $kkm = $submission->penugasan?->subject?->kkm !== null ? (float) $submission->penugasan->subject->kkm : null;
                $score = (float) $submission->nilai_guru;
                return [
                    'id' => 'tugas-'.$submission->id,
                    'type' => 'tugas',
                    'title' => $submission->penugasan?->judul,
                    'subject' => $submission->penugasan?->subject?->name,
                    'teacher' => $submission->penugasan?->teacher?->nama_lengkap ?? $submission->penugasan?->teacher?->name,
                    'date' => $submission->waktu_kumpul?->toIso8601String(),
                    'score' => $score,
                    'is_published' => true,
                    'notes' => $submission->catatan_guru,
                    'kkm' => $kkm,
                    'status_tuntas' => ($kkm !== null && $score !== null) ? ($score >= $kkm ? 'Tuntas' : 'Belum Tuntas') : null,
                ];
            });

        // Grades
        $grades = StudentGrade::query()
            ->with('subject')
            ->where('student_id', $student->id)
            ->get()
            ->map(function ($grade) {
                $kkm = $grade->subject?->kkm !== null ? (float) $grade->subject->kkm : null;
                $score = (float) ($grade->final_score ?? $grade->nilai_akhir ?? $grade->nilai_tugas ?? 0);
                return [
                    'id' => 'grade-'.$grade->id,
                    'type' => 'rapor_komponen',
                    'title' => $grade->subject?->name,
                    'subject' => $grade->subject?->name,
                    'date' => $grade->updated_at?->toIso8601String(),
                    'score' => $score,
                    'is_published' => true,
                    'notes' => $grade->notes,
                    'kkm' => $kkm,
                    'status_tuntas' => ($kkm !== null && $score !== null) ? ($score >= $kkm ? 'Tuntas' : 'Belum Tuntas') : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'official_subjects' => $officialSubjects,
                'cbt_results' => $cbtExams,
                'assignment_results' => $assignmentResults,
                'grades' => $grades,
                'summary' => [
                    'total_completed' => $cbtExams->count() + $assignmentResults->count(),
                    'average_score' => collect([...$cbtExams->pluck('score')->filter(), ...$assignmentResults->pluck('score')])->avg(),
                ],
            ],
        ]);
    }

    public function startExam(Request $request, string $id): JsonResponse

    {
        if (! Str::isUuid($id)) {
            return response()->json(['success' => false, 'message' => 'Ujian tidak tersedia untuk kelas Anda.'], 404);
        }

        $student = $this->getAuthenticatedStudent($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Akun siswa tidak valid.'], 403);
        }

        $exam = LmsUjian::query()->find($id);
        if (! $exam || ! in_array($exam->kelas_id, array_filter([$student->kelas_id, $student->class_id]), true)) {
            return response()->json(['success' => false, 'message' => 'Ujian tidak tersedia untuk kelas Anda.'], 404);
        }

        $active = LmsUjianSesi::query()->where('ujian_id', $id)->where('siswa_id', $student->id)->where('status', 'proses')->first();
        if (! $active) {
            if (! in_array($exam->status, ['published', 'berlangsung'], true)) {
                return response()->json(['success' => false, 'message' => 'Ujian belum dapat dimulai.'], 422);
            }
            if (($exam->waktu_mulai && now()->lt($exam->waktu_mulai)) || ($exam->waktu_selesai && now()->gt($exam->waktu_selesai))) {
                return response()->json(['success' => false, 'message' => 'Ujian berada di luar jadwal pengerjaan.'], 422);
            }
            $attempts = LmsUjianSesi::query()->where('ujian_id', $id)->where('siswa_id', $student->id)->whereIn('status', ['selesai', 'timeout'])->count();
            if ($attempts >= (int) $exam->max_attempt) {
                return response()->json(['success' => false, 'message' => 'Batas percobaan ujian telah tercapai.'], 422);
            }
        }

        try {
            return response()->json(['success' => true, 'data' => $this->ujianService->mulaiSesi($id, $student->id)]);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    public function saveExamAnswers(Request $request, string $sesiId): JsonResponse
    {
        if (! Str::isUuid($sesiId)) {
            return response()->json(['success' => false, 'message' => 'Sesi ujian tidak ditemukan atau bukan milik Anda.'], 403);
        }

        $request->validate(['jawaban' => ['present', 'array', 'max:500']]);
        $student = $this->getAuthenticatedStudent($request);
        $session = $student ? LmsUjianSesi::with('ujian')->where('id', $sesiId)->where('siswa_id', $student->id)->first() : null;
        if (! $session && $request->user()?->hasAnyRole(['Orang Tua', 'orang_tua', 'parent', 'Wali', 'wali'])) {
            $parent = ParentModel::query()->where('user_id', $request->user()->id)->first();
            if ($parent) {
                $childIds = $this->parentStudentsQuery($parent)->pluck('students.id')->all();
                $session = LmsUjianSesi::with('ujian')->where('id', $sesiId)->whereIn('siswa_id', $childIds)->first();
            }
        }
        if (! $session || $session->status !== 'proses') {
            return response()->json(['success' => false, 'message' => 'Sesi ujian tidak ditemukan atau bukan milik Anda.'], 403);
        }

        $allowedQuestionIds = LmsBankSoal::query()->where('kisi_kisi_id', $session->ujian->kisi_kisi_id)->where('status', true)->pluck('id')->all();
        $answers = collect($request->input('jawaban'))->filter(fn ($answer) => is_array($answer) && in_array($answer['soal_id'] ?? null, $allowedQuestionIds, true))->values()->all();
        if (count($answers) !== count($request->input('jawaban'))) {
            return response()->json(['success' => false, 'message' => 'Terdapat jawaban untuk soal yang tidak valid.'], 422);
        }

        return response()->json([
            'success' => $this->ujianService->simpanJawaban($sesiId, $answers),
            'message' => 'Jawaban tersimpan.',
            'data' => ['saved_at' => now()->toIso8601String()],
        ]);
    }

    public function finishExam(Request $request, string $sesiId): JsonResponse
    {
        if (! Str::isUuid($sesiId)) {
            return response()->json(['success' => false, 'message' => 'Sesi ujian tidak ditemukan atau bukan milik Anda.'], 403);
        }

        $student = $this->getAuthenticatedStudent($request);
        $session = $student ? LmsUjianSesi::with('ujian')->where('id', $sesiId)->where('siswa_id', $student->id)->first() : null;
        if (! $session && $request->user()?->hasAnyRole(['Orang Tua', 'orang_tua', 'parent', 'Wali', 'wali'])) {
            $parent = ParentModel::query()->where('user_id', $request->user()->id)->first();
            if ($parent) {
                $childIds = $this->parentStudentsQuery($parent)->pluck('students.id')->all();
                $session = LmsUjianSesi::with('ujian')->where('id', $sesiId)->whereIn('siswa_id', $childIds)->first();
            }
        }
        if (! $session || $session->status !== 'proses') {
            return response()->json(['success' => false, 'message' => 'Sesi ujian tidak ditemukan atau bukan milik Anda.'], 403);
        }

        if ($request->has('jawaban')) {
            $saveResponse = $this->saveExamAnswers($request, $sesiId);
            if ($saveResponse->getStatusCode() >= 400) {
                return $saveResponse;
            }
        }

        $result = $this->ujianService->selesaikanSesi($sesiId);
        if (! $result) {
            return response()->json(['success' => false, 'message' => 'Ujian gagal dikumpulkan.'], 422);
        }

        // Nilai hanya ditampilkan bila ujian diatur tampilkan_nilai_langsung.
        $showScore = (bool) $session->ujian->tampilkan_nilai_langsung;

        return response()->json([
            'success' => true,
            'message' => 'Ujian berhasil dikumpulkan.',
            'data' => [
                'sesi_id' => $result->id,
                'nilai_tersedia' => $showScore,
                'nilai_final' => $showScore ? (float) $result->nilai_final : null,
                'nilai_kkm' => $showScore ? (float) $session->ujian->nilai_kkm : null,
                'jumlah_benar' => $showScore ? (int) $result->jumlah_benar : null,
                'jumlah_salah' => $showScore ? (int) $result->jumlah_salah : null,
                'jumlah_kosong' => $showScore ? (int) $result->jumlah_kosong : null,
            ],
        ]);
    }

    /**
     * Pastikan penerima chat portal adalah kontak sah anak yang dipilih:
     * wali kelas atau guru mapel aktif di kelas siswa. Mencegah pesan
     * dikirim ke user-id sembarang (mis. wali/siswa lain).
     */
    /**
     * Pastikan penerima chat portal adalah kontak sah anak yang dipilih:
     * wali kelas atau guru mapel aktif di kelas siswa. Mencegah pesan
     * dikirim ke user-id sembarang (mis. wali/siswa lain).
     */
    private function isValidTeacherContact(Student $student, string $teacherUserId): bool
    {
        if (! Str::isUuid($teacherUserId)) {
            return false;
        }

        $cacheKey = 'valid_teacher_' . $student->id . '_' . $teacherUserId;
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($student, $teacherUserId) {
            $kelasId = $student->kelas_id ?? $student->class_id;
            if ($kelasId && Str::isUuid($kelasId)) {
                $kelas = Kelas::query()->with(['waliKelas.user'])->find($kelasId);

                // 1. Wali kelas
                $waliUser = $kelas?->waliKelas?->user;
                if (! $waliUser && $kelas?->waliKelas?->email) {
                    $waliUser = User::query()->where('email', $kelas->waliKelas->email)->first();
                }
                if ($waliUser && $waliUser->id === $teacherUserId) {
                    return true;
                }

                // 2. Guru mapel pada jadwal aktif kelas siswa
                $schedules = ClassSchedule::query()
                    ->with(['employee.user', 'teacher.user'])
                    ->where(fn ($q) => $q->where('kelas_id', $kelasId)->orWhere('class_id', $kelasId))
                    ->where('is_active', true)
                    ->get();

                foreach ($schedules as $sched) {
                    $teacherUser = $sched->employee?->user ?? $sched->teacher?->user;
                    if (! $teacherUser && ($sched->employee?->email || $sched->teacher?->email)) {
                        $teacherUser = User::query()->where('email', $sched->employee?->email ?? $sched->teacher?->email)->first();
                    }
                    if ($teacherUser && $teacherUser->id === $teacherUserId) {
                        return true;
                    }
                }
            }

            return false;
        });
    }

    public function chatContacts(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        $user = $request->user();
        $studentIdKey = $student ? (string) $student->id : 'none';
        $cacheKey = "chat_contacts_{$user->id}_{$studentIdKey}";

        $contactsMap = \Illuminate\Support\Facades\Cache::remember($cacheKey, 20, function () use ($student, $user) {
            $contactsMap = [];

            if ($student) {
                $kelasId = $student->kelas_id ?? $student->class_id;
            if ($kelasId && Str::isUuid($kelasId)) {
                $kelas = Kelas::query()->with(['waliKelas.user', 'unitPendidikan'])->find($kelasId);
                $waliKelasEmp = $kelas?->waliKelas;
                $waliUser = $waliKelasEmp?->user;
                if (! $waliUser && $waliKelasEmp?->email) {
                    $waliUser = User::query()->where('email', $waliKelasEmp->email)->first();
                }

                $resolvedUnitName = $student->educationUnit?->name
                    ?? $student->kelas?->unitPendidikan?->name
                    ?? $kelas?->unitPendidikan?->name
                    ?? $student->kelas?->unit_pendidikan?->name
                    ?? $student->unit_name
                    ?? null;

                if ($waliUser) {
                    $contactsMap[$waliUser->id] = [
                        'user_id' => $waliUser->id,
                        'name' => $waliKelasEmp?->nama_lengkap ?? $waliUser->name ?? null,
                        'photo' => $waliUser->avatar_url ?? null,
                        'role' => 'Wali Kelas',
                        'teacher_type' => 'wali_kelas',
                        'subject' => 'Wali Kelas' . ($kelas?->nama_kelas ? ' (' . $kelas->nama_kelas . ')' : ''),
                        'class_name' => $kelas?->nama_kelas ?? null,
                        'unit_name' => $resolvedUnitName,
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'last_message' => null,
                        'last_message_at' => null,
                        'unread_count' => 0,
                    ];
                }

                $schedules = ClassSchedule::query()
                    ->with(['subject', 'employee.user.roles', 'teacher.user.roles'])
                    ->where(fn ($q) => $q->where('kelas_id', $kelasId)->orWhere('class_id', $kelasId))
                    ->where('is_active', true)
                    ->get();

                $allowedTeacherRoles = ['Guru', 'Guru Mata Pelajaran', 'Guru PAI', 'Guru Tahfizh', 'Wali Kelas', 'Teacher', 'Guru BK', 'Musyrif', 'Musyrifah', 'Musyrif / Musyrifah', 'Pembimbing'];
                $disallowedTeacherRoles = [
                    'Yayasan', 'Ketua Yayasan', 'Sekretaris Yayasan', 'Bendahara Yayasan', 'Pengurus Yayasan',
                    'Kepala Sekolah', 'Wakil Kepala Sekolah', 'Tata Usaha', 'TU', 'Admin', 'Super Admin',
                    'Operator', 'Divisi Pendidikan', 'Divisi Kurikulum', 'Divisi Kesiswaan', 'Divisi Bahasa',
                    'Divisi Program Khusus', 'Kepala Bidang Pendidikan', 'Waka Kurikulum', 'Waka Kesiswaan'
                ];

                foreach ($schedules as $sched) {
                    $teacherUser = $sched->employee?->user ?? $sched->teacher?->user;
                    if (! $teacherUser || isset($contactsMap[$teacherUser->id]) || $teacherUser->id === $user->id) {
                        continue;
                    }

                    $roles = $teacherUser->roles->pluck('name')->all();
                    $hasDisallowed = count(array_intersect($roles, $disallowedTeacherRoles)) > 0;
                    $hasAllowed = count(array_intersect($roles, $allowedTeacherRoles)) > 0;

                    if ($hasDisallowed || ! $hasAllowed) {
                        continue;
                    }
                    $teacherName = $sched->employee?->nama_lengkap ?? $sched->teacher?->full_name ?? $teacherUser->name;
                    $subjectName = $sched->subject?->name ?? $sched->subject?->nama_mata_pelajaran ?? null;

                    $contactsMap[$teacherUser->id] = [
                        'user_id' => $teacherUser->id,
                        'name' => $teacherName,
                        'photo' => $teacherUser->avatar_url ?? null,
                        'role' => 'Guru Mapel',
                        'teacher_type' => 'guru_mapel',
                        'subject' => $subjectName,
                        'class_name' => $student->kelas?->nama_kelas ?? ($kelas?->nama_kelas ?? null),
                        'unit_name' => $resolvedUnitName,
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'last_message' => null,
                        'last_message_at' => null,
                        'unread_count' => 0,
                    ];
                }

                // 2. Sertakan guru yang berinteraksi / mencatat buku penghubung untuk santri ini
                $notesTeachers = StudentNote::query()
                    ->with(['teacher.user.roles'])
                    ->where('student_id', $student->id)
                    ->get();

                foreach ($notesTeachers as $note) {
                    $teacherUser = $note->teacher?->user;
                    if (! $teacherUser || isset($contactsMap[$teacherUser->id]) || $teacherUser->id === $user->id) {
                        continue;
                    }

                    $roles = $teacherUser->roles->pluck('name')->all();
                    $hasDisallowed = count(array_intersect($roles, $disallowedTeacherRoles)) > 0;
                    $hasAllowed = count(array_intersect($roles, $allowedTeacherRoles)) > 0;

                    if ($hasDisallowed || ! $hasAllowed) {
                        continue;
                    }

                    $teacherName = $note->teacher?->full_name ?? $teacherUser->name;
                    $roleLabel = match ($note->category) {
                        'Tahfizh' => 'Guru Tahfizh',
                        'Konseling' => 'Guru BK',
                        'Ibadah' => 'Musyrif / Pembina',
                        default => 'Guru Pengampu',
                    };
                    $teacherType = match ($note->category) {
                        'Tahfizh' => 'guru_tahfizh',
                        'Konseling' => 'guru_bk',
                        default => 'guru_mapel',
                    };

                    $contactsMap[$teacherUser->id] = [
                        'user_id' => $teacherUser->id,
                        'name' => $teacherName,
                        'photo' => $teacherUser->avatar_url ?? null,
                        'role' => $roleLabel,
                        'teacher_type' => $teacherType,
                        'subject' => $note->category ? 'Buku Penghubung (' . $note->category . ')' : 'Guru Pembimbing',
                        'class_name' => $student->kelas?->nama_kelas ?? ($kelas?->nama_kelas ?? null),
                        'unit_name' => $resolvedUnitName,
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'last_message' => null,
                        'last_message_at' => null,
                        'unread_count' => 0,
                    ];
                }

                // 3. Fallback jika masih kosong: guru aktif di unit pendidikan santri tersebut
                if (empty($contactsMap) && $student->unit_id) {
                    $unitEmployees = Employee::query()
                        ->with('user.roles')
                        ->where('unit_id', $student->unit_id)
                        ->whereNotNull('user_id')
                        ->get();

                    foreach ($unitEmployees as $emp) {
                        $tUser = $emp->user;
                        if (! $tUser || isset($contactsMap[$tUser->id]) || $tUser->id === $user->id) {
                            continue;
                        }
                        $roles = $tUser->roles->pluck('name')->all();
                        $hasDisallowed = count(array_intersect($roles, $disallowedTeacherRoles)) > 0;
                        $hasAllowed = count(array_intersect($roles, $allowedTeacherRoles)) > 0;
                        if ($hasDisallowed || ! $hasAllowed) {
                            continue;
                        }

                        $contactsMap[$tUser->id] = [
                            'user_id' => $tUser->id,
                            'name' => $emp->nama_lengkap ?? $tUser->name,
                            'photo' => $tUser->avatar_url ?? null,
                            'role' => in_array('Wali Kelas', $roles) ? 'Wali Kelas' : 'Guru Unit',
                            'teacher_type' => in_array('Wali Kelas', $roles) ? 'wali_kelas' : 'guru_mapel',
                            'subject' => 'Guru ' . ($resolvedUnitName ?: 'Unit'),
                            'class_name' => $student->kelas?->nama_kelas ?? ($kelas?->nama_kelas ?? null),
                            'unit_name' => $resolvedUnitName,
                            'student_id' => $student->id,
                            'student_name' => $student->full_name,
                            'last_message' => null,
                            'last_message_at' => null,
                            'unread_count' => 0,
                        ];
                    }
                }
            }
        }

        // WM-04 PATCH: removed global teacher fallback to prevent cross-parent contact disclosure.
        // If no student context or no schedule-based contacts found, return empty contacts list.
        // This prevents a parent from accessing teachers unrelated to their own child's class.

        $teacherUserIds = array_keys($contactsMap);
        $teacherUserIdsStr = array_map('strval', $teacherUserIds);

        if (! empty($teacherUserIdsStr) && $student && Str::isUuid($student->id)) {
            $unreadCounts = PortalMessage::query()
                ->where('student_id', $student->id)
                ->where('recipient_user_id', $user->id)
                ->whereIn('sender_user_id', $teacherUserIdsStr)
                ->whereNull('read_at')
                ->whereNotNull('message')
                ->where('message', '!=', '')
                ->selectRaw('sender_user_id, count(*) as total')
                ->groupBy('sender_user_id')
                ->pluck('total', 'sender_user_id')
                ->all();

            $recentMessages = PortalMessage::query()
                ->where('student_id', $student->id)
                ->where(function ($q) use ($user, $teacherUserIdsStr) {
                    $q->where('sender_user_id', $user->id)->whereIn('recipient_user_id', $teacherUserIdsStr)
                      ->orWhere('recipient_user_id', $user->id)->whereIn('sender_user_id', $teacherUserIdsStr);
                })
                ->orderByDesc('created_at')
                ->get(['sender_user_id', 'recipient_user_id', 'message', 'created_at']);

            $latestMsgMap = [];
            foreach ($recentMessages as $msg) {
                $otherId = ($msg->sender_user_id === $user->id) ? (string) $msg->recipient_user_id : (string) $msg->sender_user_id;
                if (! isset($latestMsgMap[$otherId])) {
                    $latestMsgMap[$otherId] = $msg;
                }
            }

            foreach ($contactsMap as $uid => &$c) {
                $uidStr = (string) $uid;
                $c['unread_count'] = (int) ($unreadCounts[$uidStr] ?? 0);
                if (isset($latestMsgMap[$uidStr])) {
                    $c['last_message'] = $latestMsgMap[$uidStr]->message;
                    $c['last_message_at'] = $latestMsgMap[$uidStr]->created_at?->toIso8601String();
                }
            }
            unset($c);
        }

            return $contactsMap;
        });

        $teacherUserIdsStr = array_map('strval', array_keys($contactsMap));

        $presences = \App\Models\UserPresence::query()
            ->whereIn('user_id', $teacherUserIdsStr)
            ->get()
            ->keyBy('user_id');

        $activeTokenUserIds = \Laravel\Sanctum\PersonalAccessToken::query()
            ->whereIn('tokenable_id', $teacherUserIdsStr)
            ->where(function ($q) {
                $q->where('last_used_at', '>=', now()->subHours(2))
                  ->orWhere(function ($q2) {
                      $q2->whereNull('last_used_at')->where('created_at', '>=', now()->subHours(2));
                  });
            })
            ->pluck('tokenable_id')
            ->map(fn ($id) => (string) $id)
            ->flip()
            ->toArray();

        foreach ($contactsMap as $uid => &$contact) {
            $uidStr = (string) $uid;
            $presenceRecord = $presences[$uidStr] ?? null;

            $isOnlineViaPresence = false;
            if ($presenceRecord) {
                $recentActivity = ($presenceRecord->last_activity_at && $presenceRecord->last_activity_at->gt(now()->subMinutes(30)))
                    || ($presenceRecord->last_seen_at && $presenceRecord->last_seen_at->gt(now()->subMinutes(30)));
                $isOnlineViaPresence = ($presenceRecord->status === 'online') && $recentActivity;
            }

            $isOnlineViaToken = isset($activeTokenUserIds[$uidStr]);
            $isOnlineViaCache = \Illuminate\Support\Facades\Cache::has('user-online-' . $uidStr);

            $isOnline = $isOnlineViaPresence || $isOnlineViaToken || $isOnlineViaCache;
            $contact['is_online'] = (bool) $isOnline;
            $contact['status'] = $isOnline ? 'online' : ($presenceRecord?->status === 'busy' ? 'busy' : 'offline');
        }
        unset($contact);

        return response()->json([
            'success' => true,
            'data' => array_values($contactsMap),
        ]);
    }

    public function chatMessages(Request $request, string $teacherUserId): JsonResponse
    {
        if (! Str::isUuid($teacherUserId)) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $student = $this->getStudentContext($request);
        $user = $request->user();

        if ($student && Str::isUuid($student->id)) {
            if (! $this->isValidTeacherContact($student, $teacherUserId)) {
                return response()->json(['success' => false, 'message' => 'Guru tidak terhubung dengan siswa ini.'], 403);
            }

            // Mark incoming messages as read only when unread messages actually exist
            $hasUnread = PortalMessage::query()
                ->where('student_id', $student->id)
                ->where('sender_user_id', $teacherUserId)
                ->where('recipient_user_id', $user->id)
                ->whereNull('read_at')
                ->exists();

            if ($hasUnread) {
                PortalMessage::query()
                    ->where('student_id', $student->id)
                    ->where('sender_user_id', $teacherUserId)
                    ->where('recipient_user_id', $user->id)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }

            $messages = PortalMessage::query()
                ->with(['sender:id,name', 'recipient:id,name', 'attachments'])
                ->where('student_id', $student->id)
                ->where(function ($q) use ($user, $teacherUserId) {
                    $q->where(fn ($q2) => $q2->where('sender_user_id', $user->id)->where('recipient_user_id', $teacherUserId))
                        ->orWhere(fn ($q2) => $q2->where('sender_user_id', $teacherUserId)->where('recipient_user_id', $user->id));
                })
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $messages,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [],
        ]);
    }

    public function sendChatMessage(Request $request, string $teacherUserId): JsonResponse
    {
        $request->validate([
            'message' => 'required_without:attachment|nullable|string|max:5000',
            'attachment' => 'nullable|file|max:10240',
        ]);

        if (! Str::isUuid($teacherUserId)) {
            return response()->json(['success' => false, 'message' => 'ID Guru tidak valid.'], 422);
        }

        $student = $this->getStudentContext($request);
        $user = $request->user();

        if (! $this->isValidTeacherContact($student, $teacherUserId)) {
            return response()->json(['success' => false, 'message' => 'Guru tidak terhubung dengan siswa ini.'], 403);
        }

        $studentId = ($student && Str::isUuid($student->id)) ? $student->id : null;

        $message = PortalMessage::query()->create([
            'id' => (string) Str::uuid(),
            'student_id' => $studentId,
            'sender_user_id' => $user->id,
            'recipient_user_id' => $teacherUserId,
            'message' => trim((string) ($request->input('message') ?? '')),
        ]);

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('chat/attachments', 'public');
            \App\Models\PortalMessageAttachment::create([
                'message_id' => $message->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => $file->getSize(),
            ]);
        }

        try {
            Notification::deliver(
                userId: $teacherUserId,
                title: 'Pesan Baru Orang Tua (' . $student->full_name . ')',
                body: Str::limit($message->message, 100),
                channel: 'chat',
                metadata: [
                    'student_id' => $student->id,
                    'parent_user_id' => $user->id,
                    'message_id' => $message->id,
                ],
            );
        } catch (\Throwable $e) {
            // Silence notification schema fallback
        }

        // Realtime Broadcast chat message to teacher
        try {
            app(RealtimeBroadcastService::class)->broadcastChatMessage(
                $teacherUserId,
                [
                    'id' => $message->id,
                    'student_id' => $studentId,
                    'sender_user_id' => $user->id,
                    'sender_name' => $user->name,
                    'recipient_user_id' => $teacherUserId,
                    'message' => $message->message,
                    'created_at' => $message->created_at ? $message->created_at->toISOString() : now()->toISOString(),
                ],
                $user->id
            );
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dikirim.',
            'data' => $message->load(['sender:id,name', 'recipient:id,name', 'attachments']),
        ]);
    }

    public function updateChildPhoto(Request $request, string $childId): JsonResponse
    {
        $user = $request->user();
        $student = Student::where('id', $childId)->first();
        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Data anak tidak ditemukan.',
            ], 404);
        }

        // Verify parent access or student ownership or admin
        $parent = ParentModel::where('user_id', $user->id)->first();
        if ($parent) {
            $isLinkedChild = $this->parentStudentsQuery($parent)->whereKey($student->id)->exists();
            if (! $isLinkedChild) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak akses untuk memperbarui foto siswa ini.',
                ], 403);
            }
        } elseif (! $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan'])) {
            if ($student->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak akses untuk memperbarui foto siswa ini.',
                ], 403);
            }
        }

        $file = $request->file('photo') ?? $request->file('foto') ?? $request->file('avatar');
        if (! $file) {
            return response()->json([
                'success' => false,
                'message' => 'Berkas foto wajib diunggah.',
                'errors' => ['photo' => ['Berkas foto wajib diunggah.']],
            ], 422);
        }

        $validator = \Illuminate\Support\Facades\Validator::make(['photo' => $file], [
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:3072'],
        ], [
            'photo.required' => 'Berkas foto wajib diunggah.',
            'photo.image' => 'Berkas harus berupa gambar.',
            'photo.mimes' => 'Format foto yang diizinkan: jpeg, png, jpg, webp.',
            'photo.max' => 'Ukuran foto maksimal 3MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $file->store('students/photos', 'public');

        // Clean up old local storage file
        if ($student->photo && ! str_starts_with($student->photo, 'http') && \Illuminate\Support\Facades\Storage::disk('public')->exists($student->photo)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($student->photo);
        }

        $metadata = is_array($student->metadata) ? $student->metadata : [];
        $metadata['photo'] = $path;
        $metadata['foto'] = $path;
        $metadata['photo_updated_at'] = now()->toIso8601String();

        $student->photo = $path;
        $student->photo_thumb = $path;
        $student->metadata = $metadata;
        $student->save();

        $photoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($path);

        return response()->json([
            'success' => true,
            'message' => 'Foto siswa (' . $student->full_name . ') berhasil diperbarui dan disimpan.',
            'data' => [
                'photo' => $path,
                'photo_url' => $photoUrl,
            ],
        ]);
    }

    public function updateChildPassword(Request $request, string $childId): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        // WM-01 PATCH: verify parent-child ownership before allowing password mutation.
        // Mirrors the authorization guard already present in updateChildPhoto().
        $user = $request->user();
        $student = Str::isUuid($childId) ? Student::where('id', $childId)->first() : null;
        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Data anak tidak ditemukan.',
            ], 404);
        }

        $parent = ParentModel::where('user_id', $user->id)->first();
        if ($parent) {
            $isLinkedChild = $this->parentStudentsQuery($parent)->whereKey($student->id)->exists();
            if (! $isLinkedChild) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak akses untuk mengubah password siswa ini.',
                ], 403);
            }
        } elseif (! $user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan'])) {
            if ($student->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak akses untuk mengubah password siswa ini.',
                ], 403);
            }
        }

        if ($student->user_id) {
            $studentUser = \App\Models\User::find($student->user_id);
            if ($studentUser) {
                $studentUser->update(['password' => \Illuminate\Support\Facades\Hash::make($request->password)]);
            }
        }

        $metadata = is_array($student->metadata) ? $student->metadata : [];
        $metadata['login_password_updated_at'] = now()->toIso8601String();
        $student->update(['metadata' => $metadata]);

        return response()->json([
            'success' => true,
            'message' => 'Password login untuk anak (' . $student->full_name . ') berhasil diperbarui.',
        ]);
    }

    /**
     * Input Setoran Pengulangan / Murajaah dari Orang Tua di Rumah
     */
    public function submitMurajaah(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => 'required',
            'surah_number' => 'required|integer|between:1,114',
            'ayat_start' => 'required|integer|min:1',
            'ayat_end' => 'required|integer|min:1',
            'record_date' => 'required|date',
            'record_time' => 'nullable|string',
            'murajaah_lembar' => 'nullable|numeric|min:0',
            'notes_parent' => 'nullable|string|max:500',
        ]);

        // WM-03 PATCH: verify parent-child ownership before writing murajaah records.
        // A parent must not be able to submit murajaah for a student they do not own.
        $user = $request->user();
        $studentId = $validated['student_id'];
        if (! Str::isUuid($studentId)) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }
        $student = null;
        $parentForMurajaah = ParentModel::where('user_id', $user->id)->first();
        if ($parentForMurajaah) {
            $student = $this->parentStudentsQuery($parentForMurajaah)->where('id', $studentId)->where('is_active', true)->first();
        } elseif ($user->hasAnyRole(['Super Admin', 'super_admin', 'Admin', 'admin', 'Yayasan', 'yayasan'])) {
            $student = Student::where('id', $studentId)->first();
        } else {
            // Siswa hanya bisa submit murajaah untuk diri sendiri
            $student = Student::where('id', $studentId)->where('user_id', $user->id)->first();
        }
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan atau Anda tidak memiliki akses.'], 404);
        }

        $surah = \App\Models\QuranSurah::where('nomor', $validated['surah_number'])->first();
        if (! $surah) {
            return response()->json(['success' => false, 'message' => 'Surah tidak valid.'], 422);
        }

        if ($validated['ayat_end'] < $validated['ayat_start']) {
            return response()->json(['success' => false, 'message' => 'Ayat akhir tidak boleh lebih kecil dari ayat mulai.'], 422);
        }

        if ($validated['ayat_end'] > $surah->jumlah_ayat) {
            return response()->json([
                'success' => false,
                'message' => "Surah {$surah->nama_latin} hanya memiliki {$surah->jumlah_ayat} ayat. Ayat akhir melebihi jumlah ayat."
            ], 422);
        }

        $user = $request->user();
        $parentName = $user ? ($user->name ?? $user->full_name ?? null) : null;
        $recordTime = $validated['record_time'] ?? now()->format('H:i');
        $recordDate = $validated['record_date'];

        $dayIndex = Carbon::parse($recordDate)->dayOfWeekIso; // 1 = Senin, 7 = Ahad
        $daysIndo = [
            1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Ahad'
        ];
        $dayName = $daysIndo[$dayIndex] ?? 'Senin';

        $surahText = "Surah {$surah->nama_latin} (Ayat {$validated['ayat_start']} - {$validated['ayat_end']})";
        $totalAyat = (int) ($validated['ayat_end'] - $validated['ayat_start'] + 1);

        $metadata = [
            'murajaah_time' => $recordTime,
            'murajaah_datetime' => "{$recordDate} {$recordTime}:00",
            'surah_number' => (int) $surah->nomor,
            'surah_name' => $surah->nama_latin,
            'ayat_start' => (int) $validated['ayat_start'],
            'ayat_end' => (int) $validated['ayat_end'],
            'jumlah_ayat' => $totalAyat,
            'submitted_by_parent' => true,
            'parent_name' => $parentName,
            'submitted_at' => now()->toIso8601String(),
        ];

                $juzNum = QuranJuzHelper::getJuz((int) $surah->nomor, (int) $validated['ayat_start']);
        $metadata['juz'] = $juzNum;

        // Cek apakah ada tugas wajib murajaah dari guru yang sedang menunggu
        $teacherTask = TahfizhDailyLog::where('student_id', $student->id)
            ->where('murajaah_required', true)
            ->whereIn('murajaah_status', ['pending_home', 'rejected'])
            ->orderByDesc('record_date')
            ->first();

        if ($teacherTask) {
            $teacherTask->update(['murajaah_status' => 'submitted_by_parent']);
        }

        $log = TahfizhDailyLog::create([
            'student_id' => $student->id,
            'class_id' => $student->class_id ?? $student->kelas_id,
            'academic_year_id' => $student->academic_year_id ?? null,
            'record_date' => $recordDate,
            'day_name' => $dayName,
            'hafalan_surah_number' => $surah->nomor,
            'hafalan_surah_name' => $surah->nama_latin,
            'hafalan_ayah_start' => $validated['ayat_start'],
            'hafalan_ayah_end' => $validated['ayat_end'],
            'hafalan_baris' => $totalAyat,
            'murajaah_text' => $surahText,
            'murajaah_lembar' => $validated['murajaah_lembar'] ?? 1.0,
            'murajaah_required' => true,
            'murajaah_required_surah_number' => $surah->nomor,
            'murajaah_required_surah_name' => $surah->nama_latin,
            'murajaah_required_ayah_start' => $validated['ayat_start'],
            'murajaah_required_ayah_end' => $validated['ayat_end'],
            'murajaah_status' => 'submitted_by_parent',
            'notes_parent' => $validated['notes_parent'] ?? "Pengulangan hafalan mandiri di rumah bersama orang tua ({$parentName}) pukul {$recordTime}",
            'signature_parent' => $parentName,
            'status' => 'pending_approval',
            'metadata' => $metadata,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Alhamdulillah, laporan murajaah di rumah berhasil dikirim. Menunggu verifikasi dari Ustadz / Guru Tahfizh.',
            'data' => $log,
        ], 201);
    }

    /**
     * Ambil antrean setoran murajaah rumah untuk di-review & diverifikasi oleh Guru Tahfizh.
     */
    public function pendingMurajaahReviewList(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $accessScope = app(\App\Services\AccessScopeService::class);
        $query = TahfizhDailyLog::with(['student', 'teacher'])
            ->where('status', 'pending_approval');

        if (! $accessScope->hasGlobalScope($user)) {
            $accessibleStudentIds = $accessScope->accessibleStudents($user)->select('students.id');
            $query->whereIn('student_id', $accessibleStudentIds);
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->query('class_id'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->query('student_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%");
            });
        }

        $pendingLogs = $query->orderByDesc('record_date')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Data setoran murajaah menunggu verifikasi berhasil dimuat.',
            'total_pending' => $pendingLogs->count(),
            'data' => $pendingLogs,
        ]);
    }

    /**
     * Setujui (Approve) setoran murajaah dari orang tua oleh Guru Tahfizh.
     */
    public function approveMurajaahReview(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $userRoleNames = $user->roles->pluck('name')->map(fn ($r) => strtolower($r))->toArray();
        $isStudentOrAlumniOrParent = collect($userRoleNames)->contains(fn ($r) => in_array($r, ['siswa', 'student', 'alumni', 'orang tua', 'orang_tua', 'ortu', 'parent', 'wali murid']));
        abort_if($isStudentOrAlumniOrParent, 403, 'Hanya Ustadz / Guru Pembimbing yang berhak memverifikasi murajaah rumah.');

        $log = TahfizhDailyLog::where('id', $id)->first();
        if (! $log) {
            return response()->json(['success' => false, 'status' => 'error', 'message' => 'Data log tidak ditemukan.'], 404);
        }

        $accessScope = app(\App\Services\AccessScopeService::class);
        if (! $accessScope->hasGlobalScope($user)) {
            abort_unless($accessScope->accessibleStudents($user)->where('students.id', $log->student_id)->exists(), 403, 'Akses verifikasi murajaah ditolak: siswa berada di luar wewenang akun.');
        }

        $validated = $request->validate([
            'notes_teacher' => 'nullable|string|max:500',
            'nilai' => 'nullable|string|in:Mumtaz,Lancar,Mutqin,Jayyid Jiddan,Jayyid,Perlu Latihan',
        ]);

        $teacherName = $user ? ($user->nama_lengkap ?? $user->name ?? $user->full_name ?? null) : null;
        $nilai = $validated['nilai'] ?? null;

        $meta = is_array($log->metadata) ? $log->metadata : [];
        $meta['approved_at'] = now()->toIso8601String();
        $meta['approved_by'] = $teacherName;
        if ($nilai !== null) {
            $meta['nilai'] = $nilai;
        }

        $log->update([
            'status' => 'approved',
            'murajaah_status' => 'approved',
            'signature_teacher' => $teacherName,
            'notes_teacher' => $validated['notes_teacher'] ?? null,
            'metadata' => $meta,
        ]);

        // Selesaikan tugas wajib murajaah terkait untuk siswa ini agar bisa melanjutkan hafalan berikutnya
        TahfizhDailyLog::where('student_id', $log->student_id)
            ->where('murajaah_required', true)
            ->whereIn('murajaah_status', ['pending_home', 'submitted_by_parent'])
            ->update(['murajaah_status' => 'approved']);

        // Sinkronisasi capaian ayat siswa
        $student = Student::find($log->student_id);
        if ($student) {
            try {
                app(\App\Services\TahfizhAchievementService::class)->synchronize($student);
            } catch (\Throwable $e) {}
        }

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Setoran murajaah ananda berhasil disetujui & diverifikasi.',
            'data' => $log,
        ]);
    }

    /**
     * Berikan catatan revisi / tolak setoran murajaah rumah oleh Guru Tahfizh.
     */
    public function rejectMurajaahReview(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        $userRoleNames = $user->roles->pluck('name')->map(fn ($r) => strtolower($r))->toArray();
        $isStudentOrAlumniOrParent = collect($userRoleNames)->contains(fn ($r) => in_array($r, ['siswa', 'student', 'alumni', 'orang tua', 'orang_tua', 'ortu', 'parent', 'wali murid']));
        abort_if($isStudentOrAlumniOrParent, 403, 'Hanya Ustadz / Guru Pembimbing yang berhak memverifikasi murajaah rumah.');

        $log = TahfizhDailyLog::where('id', $id)->first();
        if (! $log) {
            return response()->json(['success' => false, 'status' => 'error', 'message' => 'Data log tidak ditemukan.'], 404);
        }

        $accessScope = app(\App\Services\AccessScopeService::class);
        if (! $accessScope->hasGlobalScope($user)) {
            abort_unless($accessScope->accessibleStudents($user)->where('students.id', $log->student_id)->exists(), 403, 'Akses verifikasi murajaah ditolak: siswa berada di luar wewenang akun.');
        }

        $validated = $request->validate([
            'notes_teacher' => 'required|string|max:500',
        ]);

        $teacherName = $user ? ($user->nama_lengkap ?? $user->name ?? $user->full_name ?? null) : null;

        $meta = is_array($log->metadata) ? $log->metadata : [];
        $meta['rejected_at'] = now()->toIso8601String();
        $meta['rejected_by'] = $teacherName;

        $log->update([
            'status' => 'needs_revision',
            'murajaah_status' => 'rejected',
            'signature_teacher' => $teacherName,
            'notes_teacher' => $validated['notes_teacher'],
            'metadata' => $meta,
        ]);

        // Kembalikan status tugas ke pending_home agar orang tua mengulang kembali
        TahfizhDailyLog::where('student_id', $log->student_id)
            ->where('murajaah_required', true)
            ->where('murajaah_status', 'submitted_by_parent')
            ->update(['murajaah_status' => 'pending_home']);

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => 'Catatan revisi setoran murajaah telah disimpan.',
            'data' => $log,
        ]);
    }

    /**
     * Live Timeline Aktivitas Siswa Hari Ini (Fullday & Boarding)
     * Menggabungkan presensi gerbang, presensi mapel, sholat wajib/sunnah, tahfizh, dan mutabaah.
     */
    public function todayLiveTimeline(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Data siswa tidak ditemukan atau belum dipilih.',
            ], 404);
        }

        // Gunakan parameter ?date=YYYY-MM-DD jika disediakan (untuk navigasi hari sebelumnya),
        // fallback ke hari ini di sistem sekolah jika tidak ada atau 'today'.
        $serverDate = Carbon::now()->toDateString();
        $requestedDate = $request->input('date');
        if (empty($requestedDate) || $requestedDate === 'today') {
            $today = $serverDate;
        } else {
            $today = ($requestedDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate))
                ? $requestedDate
                : $serverDate;
        }


        // 1. Program Type (Fullday vs Boarding)
        $programSetting = EducationProgramSetting::query()
            ->where('education_unit_id', $student->unit_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today))
            ->where(fn ($q) => $q->whereNull('class_id')->orWhere('class_id', $student->kelas_id))
            ->latest()
            ->first();

        $meta = is_array($student->metadata) ? $student->metadata : [];
        $unitName = strtolower($student->educationUnit?->name ?? $student->unit_name ?? '');
        $isBoarding = ($programSetting?->program_type === 'boarding')
            || (! empty($meta['dormitory_id']))
            || (! empty($meta['dormitory']))
            || str_contains($unitName, 'boarding')
            || str_contains($unitName, 'pesantren')
            || str_contains($unitName, 'pondok')
            || str_contains($unitName, 'kulliyatul');

        $programType = $isBoarding ? 'boarding' : 'fullday';
        $programLabel = $isBoarding ? 'Boarding / Pesantren' : 'Fullday School';

        // 2. Presensi Gerbang / Harian (Attendance)
        $gateRecord = Attendance::query()
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', $today)
            ->latest()
            ->first();

        $gateInfo = [
            'status' => $gateRecord ? strtolower($gateRecord->status) : 'belum_hadir',
            'status_label' => $gateRecord ? ($gateRecord->status_label ?? ucfirst($gateRecord->status)) : 'Belum Check-in',
            'check_in_time' => $gateRecord?->check_in_time ? Carbon::parse($gateRecord->check_in_time)->format('H:i') : null,
            'check_out_time' => $gateRecord?->check_out_time ? Carbon::parse($gateRecord->check_out_time)->format('H:i') : null,
            'photo_snapshot' => $gateRecord?->photo_snapshot,
            'keterangan' => $gateRecord?->keterangan,
        ];

        // 3. Presensi Pelajaran Kelas (LmsPresensi)
        $studentClasses = array_values(array_unique(array_filter([$student->kelas_id, $student->class_id])));

        $lessonPresensi = LmsPresensi::query()
            ->with([
                'jadwalPelajaran.subject',
                'jadwalPelajaran.teacher',
                'jadwalPelajaran.kelas',
                'session.schedule.subject',
                'session.schedule.teacher',
            ])
            ->where('siswa_id', $student->id)
            ->whereHas('jadwalPelajaran', fn ($q) => $q->whereIn('class_id', $studentClasses))
            ->whereDate('tanggal', $today)
            ->orderBy('created_at', 'asc')
            ->get();

        $lessonsList = $lessonPresensi->map(function ($lp) {
            $sched = $lp->jadwalPelajaran ?? $lp->session?->schedule;
            $subjectName = $sched?->subject?->name ?? $sched?->subject?->nama ?? null;
            $teacherName = $sched?->teacher?->full_name ?? $sched?->teacher?->name ?? $sched?->employee?->nama_lengkap ?? $sched?->employee?->name ?? null;
            $rawStatus = strtolower($lp->status_hadir ?? $lp->status ?? 'hadir');
            if (in_array($rawStatus, ['h', 'hadir'])) {
                $statusKey = 'hadir';
                $statusLabel = 'Hadir';
            } elseif (in_array($rawStatus, ['i', 'izin'])) {
                $statusKey = 'izin';
                $statusLabel = 'Izin';
            } elseif (in_array($rawStatus, ['s', 'sakit'])) {
                $statusKey = 'sakit';
                $statusLabel = 'Sakit';
            } elseif (in_array($rawStatus, ['t', 'terlambat'])) {
                $statusKey = 'terlambat';
                $statusLabel = 'Terlambat';
            } else {
                $statusKey = 'alpa';
                $statusLabel = 'Alpa / Tidak Hadir';
            }

            return [
                'id' => (string) $lp->id,
                'subject' => $subjectName,
                'teacher' => $teacherName,
                'status' => $statusKey,
                'status_label' => $statusLabel,
                'time_start' => $sched?->jam_mulai ? substr((string) $sched->jam_mulai, 0, 5) : null,
                'time_end' => $sched?->jam_selesai ? substr((string) $sched->jam_selesai, 0, 5) : null,
                'recorded_at' => $lp->created_at ? Carbon::parse($lp->created_at)->format('H:i') : null,
                'keterangan' => $lp->keterangan ?? $lp->notes ?? null,
                'notes' => $lp->keterangan ?? $lp->notes ?? null,
                'arrival_time' => $lp->arrival_time ? substr((string) $lp->arrival_time, 0, 5) : null,
            ];
        })->values();

        // 4. Presensi Sholat Wajib & Sunnah (WorshipAttendanceDetail)
        $worshipDetails = WorshipAttendanceDetail::query()
            ->with(['session.template', 'verifier', 'session.supervisor'])
            ->where('student_id', $student->id)
            ->whereHas('session', fn ($q) => $q->whereDate('session_date', $today))
            ->get();

        $prayerKeys = $isBoarding
            ? ['subuh', 'dhuha', 'zuhur', 'asar', 'magrib', 'isya', 'tahajud']
            : ['dhuha', 'zuhur', 'asar'];

        $prayerNames = [
            'subuh' => ['name' => 'Subuh', 'type' => 'wajib', 'icon' => 'weather-sunset-up'],
            'dhuha' => ['name' => 'Dhuha', 'type' => 'sunnah', 'icon' => 'white-balance-sunny'],
            'zuhur' => ['name' => 'Dzuhur', 'type' => 'wajib', 'icon' => 'weather-sunny'],
            'asar' => ['name' => 'Ashar', 'type' => 'wajib', 'icon' => 'weather-partly-cloudy'],
            'magrib' => ['name' => 'Maghrib', 'type' => 'wajib', 'icon' => 'weather-sunset-down'],
            'isya' => ['name' => 'Isya', 'type' => 'wajib', 'icon' => 'weather-night'],
            'tahajud' => ['name' => 'Qiyamul Lail', 'type' => 'sunnah', 'icon' => 'moon-waning-crescent'],
        ];

        $prayersList = collect($prayerKeys)->map(function ($pKey) use ($worshipDetails, $prayerNames) {
            $info = $prayerNames[$pKey] ?? ['name' => ucfirst($pKey), 'type' => 'wajib', 'icon' => 'hands-pray'];
            
            $detail = $worshipDetails->first(function ($wd) use ($pKey) {
                $pName = strtolower($wd->session?->template?->prayer_name ?? '');
                $tName = strtolower($wd->session?->template?->nama ?? '');
                return str_contains($pName, $pKey) || str_contains($tName, $pKey);
            });

            if ($detail) {
                $rawStat = strtolower($detail->attendance_status ?? 'hadir_berjamaah');
                $isDone = in_array($rawStat, ['hadir_berjamaah', 'hadir_sendiri', 'hadir', 'selesai']);
                $isExcused = in_array($rawStat, ['izin', 'sakit', 'uzur_syarii', 'haid', 'dispensasi']);
                $statusKey = $isDone ? 'hadir_berjamaah' : ($isExcused ? 'uzur' : 'tidak_hadir');
                $statusLabel = $isDone ? ($rawStat === 'hadir_sendiri' ? 'Munfarid' : 'Berjamaah') : ($isExcused ? 'Uzur / Izin' : 'Tidak Hadir');
                $time = $detail->attended_at ? Carbon::parse($detail->attended_at)->format('H:i') : null;
            } else {
                $statusKey = 'pending';
                $statusLabel = 'Belum Ada Input';
                $time = null;
            }

            return [
                'prayer_key' => $pKey,
                'name' => $info['name'],
                'type' => $info['type'],
                'icon' => $info['icon'],
                'status' => $statusKey,
                'status_label' => $statusLabel,
                'time' => $time,
            ];
        })->values();

        // 5. Tahfizh Harian (TahfizhDailyLog)
        $tahfizhLog = TahfizhDailyLog::query()
            ->with(['teacher', 'employee', 'teacherUser'])
            ->where('student_id', $student->id)
            ->whereDate('record_date', $today)
            ->latest('created_at')
            ->first();

        $isLatestFromPast = false;
        if (! $tahfizhLog) {
            $tahfizhLog = TahfizhDailyLog::query()
                ->with(['teacher', 'employee', 'teacherUser'])
                ->where('student_id', $student->id)
                ->latest('record_date')
                ->first();
            $isLatestFromPast = (bool) $tahfizhLog;
        }

        $tahfizhInfo = null;
        if ($tahfizhLog) {
            $tahfizhInfo = [
                'surah_name' => $tahfizhLog->hafalan_surah_name ?: ($tahfizhLog->surah ?: ($tahfizhLog->tilawah_text ?: ($tahfizhLog->murajaah_text ?: null))),
                'ayah_start' => $tahfizhLog->hafalan_ayah_start,
                'ayah_end' => $tahfizhLog->hafalan_ayah_end,
                'baris' => $tahfizhLog->hafalan_baris,
                'tilawah_text' => $tahfizhLog->tilawah_text,
                'murajaah_text' => $tahfizhLog->murajaah_text,
                'murajaah_lembar' => $tahfizhLog->murajaah_lembar,
                'notes_teacher' => $tahfizhLog->notes_teacher,
                'status' => $tahfizhLog->status,
                'status_label' => $tahfizhLog->status ? ucfirst($tahfizhLog->status) : null,
                'record_date' => $tahfizhLog->record_date ? Carbon::parse($tahfizhLog->record_date)->format('d M Y') : null,
                'is_today' => ! $isLatestFromPast,
            ];
        }

        // 6. Mutabaah Yaumiyah (MutabaahDailyHeader)
        $mutabaahHeader = MutabaahDailyHeader::query()
            ->where('student_id', $student->id)
            ->whereDate('activity_date', $today)
            ->latest()
            ->first();

        $mutabaahInfo = [
            'has_entry' => (bool) $mutabaahHeader,
            'good_count' => (int) ($mutabaahHeader?->good_count ?? 0),
            'less_count' => (int) ($mutabaahHeader?->less_count ?? 0),
            'not_done_count' => (int) ($mutabaahHeader?->not_done_count ?? 0),
            'total_items' => (int) ($mutabaahHeader?->total_items ?? 0),
            'score' => (float) ($mutabaahHeader?->score ?? 0),
            'status' => $mutabaahHeader?->status?->value ?? 'draft',
        ];

        // 7. Overall Quick Counts
        $hadirCount = $lessonsList->where('status', 'hadir')->count();
        $totalLessonRecorded = $lessonsList->count();
        $prayerDoneCount = $prayersList->whereIn('status', ['hadir_berjamaah', 'hadir_sendiri'])->count();

        // 8. Format Relative Time Helper
        $formatRelativeTime = function ($dateTime) {
            if (! $dateTime) {
                return ['date' => 'Hari ini', 'time' => '-'];
            }
            try {
                $c = Carbon::parse($dateTime)->locale('id');
                if ($c->isToday()) {
                    $dateLabel = 'Hari ini';
                } elseif ($c->isYesterday()) {
                    $dateLabel = 'Kemarin';
                } else {
                    $dateLabel = $c->translatedFormat('j M Y');
                }
                return [
                    'date' => $dateLabel,
                    'time' => $c->format('H:i'),
                ];
            } catch (\Throwable $e) {
                return ['date' => 'Hari ini', 'time' => '-'];
            }
        };

        // 9. Unified Activity Feed: 100% Real Database Queries with Authentic Timestamps (Anti-Future Filter)
        $activities = [];
        $studentFirstName = explode(' ', trim($student->nama_lengkap ?? $student->full_name ?? ''))[0];
        $nowWib = Carbon::now()->timezone('Asia/Jakarta');
        $isViewingTodayDate = ($today === $serverDate);

        // 1. Presensi Gerbang (Masuk & Keluar Sekolah) Hari Ini (Attendance)
        try {
            if ($gateRecord && $gateRecord->check_in_time) {
                $isHadir = in_array(strtolower($gateRecord->status ?? ''), ['h', 'hadir', 'tepat_waktu', 'hadir_dalam_toleransi']);
                $isLate = in_array(strtolower($gateRecord->status ?? ''), ['t', 'terlambat']);
                $checkInCarbon = Carbon::parse($gateRecord->check_in_time)->timezone('Asia/Jakarta');
                $checkInTimeFormatted = $checkInCarbon->format('H:i');
                $rawTime = $checkInCarbon->format('Y-m-d H:i:s');

                $gateVerifierName = null;
                if (! empty($gateRecord->verified_by)) {
                    $verifierEmp = \App\Models\Employee::find($gateRecord->verified_by);
                    if ($verifierEmp) {
                        $gateVerifierName = $verifierEmp->full_name ?? $verifierEmp->name ?? null;
                    } else {
                        $verifierUser = \App\Models\User::find($gateRecord->verified_by);
                        if ($verifierUser) {
                            $gateVerifierName = $verifierUser->name ?? null;
                        }
                    }
                }

                // Cegah jam masa depan jika sedang melihat hari ini
                if (! ($isViewingTodayDate && $checkInCarbon->gt($nowWib))) {
                    $activities[] = [
                        'id' => 'gate-in-' . $gateRecord->id,
                        'raw_time' => $rawTime,
                        'type' => 'absensi_gerbang',
                        'title' => ($studentFirstName ? $studentFirstName . ' ' : '') . 'masuk sekolah',
                        'subtitle' => 'Gerbang utama (Scan QR / RFID)',
                        'date_label' => $isViewingTodayDate ? 'Hari ini' : Carbon::parse($today)->isoFormat('D MMM'),
                        'time_label' => $checkInTimeFormatted,
                        'is_past' => ! $isViewingTodayDate,
                        'badge_label' => $isHadir ? 'Tepat Waktu' : ($isLate ? 'Terlambat' : ($gateRecord->status ? ucfirst($gateRecord->status) : null)),
                        'badge_type' => $isHadir ? 'green' : ($isLate ? 'amber' : 'blue'),
                        'icon' => 'account-circle',
                        'icon_bg' => '#ECFDF5',
                        'icon_color' => '#10B981',
                        'screen' => 'Absensi',
                        'verifier_role' => 'Petugas Gerbang & Keamanan',
                        'verifier_name' => $gateVerifierName,
                    ];
                }
            }

            if ($gateRecord && $gateRecord->check_out_time) {
                $checkOutCarbon = Carbon::parse($gateRecord->check_out_time)->timezone('Asia/Jakarta');
                $checkOutTimeFormatted = $checkOutCarbon->format('H:i');
                $rawTimeOut = $checkOutCarbon->format('Y-m-d H:i:s');

                if (! ($isViewingTodayDate && $checkOutCarbon->gt($nowWib))) {
                    $activities[] = [
                        'id' => 'gate-out-' . $gateRecord->id,
                        'raw_time' => $rawTimeOut,
                        'type' => 'absensi_gerbang',
                        'title' => $studentFirstName . ' keluar / pulang sekolah',
                        'subtitle' => 'Gerbang utama (Scan RFID Kepulangan)',
                        'date_label' => $isViewingTodayDate ? 'Hari ini' : Carbon::parse($today)->isoFormat('D MMM'),
                        'time_label' => $checkOutTimeFormatted,
                        'is_past' => ! $isViewingTodayDate,
                        'badge_label' => 'Selesai KBM',
                        'badge_type' => 'green',
                        'icon' => 'logout',
                        'icon_bg' => '#EFF6FF',
                        'icon_color' => '#2563EB',
                        'screen' => 'Absensi',
                        'verifier_role' => 'Petugas Gerbang & Keamanan',
                        'verifier_name' => $gateVerifierName,
                    ];
                }
            }
        } catch (\Throwable $e) {}

        // 2. Presensi Mapel Kelas yang Diinput Guru Hari Ini (LmsPresensi)
        try {
            $groupedBySubject = [];
            foreach ($lessonPresensi as $lp) {
                $sched = $lp->jadwalPelajaran ?? $lp->session?->schedule;
                $subjectName = $sched?->subject?->name ?? $sched?->subject?->nama ?? null;
                $subjectKey = strtolower(trim((string) $subjectName));

                if ($tahfizhLog && str_contains($subjectKey, 'tahfizh')) {
                    continue;
                }

                $timeKey = $lp->arrival_time ?: ($sched?->start_time ?: ($sched?->jam_mulai ?: $lp->id));
                $groupedBySubject[$subjectKey . '-' . $timeKey][] = $lp;
            }

            foreach ($groupedBySubject as $mapKey => $lpList) {
                $lp = $lpList[0];
                $sched = $lp->jadwalPelajaran ?? $lp->session?->schedule;
                $subjectName = $sched?->subject?->name ?? $sched?->subject?->nama ?? null;
                $teacherName = $sched?->teacher?->full_name ?? $sched?->teacher?->name ?? $sched?->employee?->nama_lengkap ?? $sched?->employee?->name ?? null;

                // Tentukan jam pelajaran riil dari waktu kedatangan atau jadwal kelas
                $startTimeStr = $lp->arrival_time 
                    ? substr((string) $lp->arrival_time, 0, 5) 
                    : ($sched?->start_time 
                        ? substr((string) $sched->start_time, 0, 5) 
                        : ($sched?->jam_mulai 
                            ? substr((string) $sched->jam_mulai, 0, 5) 
                            : ($lp->created_at ? Carbon::parse($lp->created_at)->timezone('Asia/Jakarta')->format('H:i') : null)));

                $endTimeStr = $sched?->end_time 
                    ? substr((string) $sched->end_time, 0, 5) 
                    : ($sched?->jam_selesai ? substr((string) $sched->jam_selesai, 0, 5) : null);

                $timeRangeSubtitle = $endTimeStr ? ' (' . $startTimeStr . '–' . $endTimeStr . ')' : ' (Pukul ' . $startTimeStr . ')';
                $rawTime = $today . ' ' . $startTimeStr . ':00';
                $lessonCarbon = Carbon::parse($rawTime);

                // Anti-future filter untuk hari ini
                if ($isViewingTodayDate && $lessonCarbon->gt($nowWib)) {
                    continue;
                }

                $rawStat = strtolower($lp->status_hadir ?? $lp->status ?? 'hadir');
                $bLabel = 'Hadir';
                $bType = 'green';
                if (in_array($rawStat, ['i', 'izin'])) {
                    $bLabel = 'Izin';
                    $bType = 'amber';
                } elseif (in_array($rawStat, ['s', 'sakit'])) {
                    $bLabel = 'Sakit';
                    $bType = 'blue';
                } elseif (in_array($rawStat, ['t', 'terlambat'])) {
                    $bLabel = 'Terlambat';
                    $bType = 'amber';
                } elseif (in_array($rawStat, ['a', 'alpa', 'tidak_hadir'])) {
                    $bLabel = 'Alpa';
                    $bType = 'red';
                }

                $activities[] = [
                    'id' => 'lesson-presensi-' . $lp->id,
                    'raw_time' => $rawTime,
                    'type' => 'mapel',
                    'title' => 'Absensi kelas ' . $subjectName,
                    'subtitle' => 'Guru: ' . $teacherName . $timeRangeSubtitle,
                    'date_label' => $isViewingTodayDate ? 'Hari ini' : Carbon::parse($today)->isoFormat('D MMM'),
                    'time_label' => $startTimeStr,
                    'is_past' => ! $isViewingTodayDate,
                    'badge_label' => $bLabel,
                    'badge_type' => $bType,
                    'icon' => 'account-check',
                    'icon_bg' => '#ECFDF5',
                    'icon_color' => '#10B981',
                    'screen' => 'Absensi',
                    'verifier_role' => 'Guru Pengampu ' . $subjectName,
                    'verifier_name' => $teacherName,
                    'keterangan' => $lp->keterangan ?? $lp->notes ?? null,
                    'notes' => $lp->keterangan ?? $lp->notes ?? null,
                    'arrival_time' => $lp->arrival_time ? substr((string) $lp->arrival_time, 0, 5) : null,
                ];
            }
        } catch (\Throwable $e) {}

        // 3. Sholat Wajib & Sunnah oleh Musyrif Asrama / Ponpes Hari Ini (WorshipAttendanceDetail)
        try {
            foreach ($worshipDetails as $wd) {
                if (! $wd->attendance_status) {
                    continue;
                }
                $rawStat = strtolower($wd->attendance_status);
                $isDone = in_array($rawStat, ['hadir_berjamaah', 'hadir_sendiri', 'hadir', 'selesai']);
                $isExcused = in_array($rawStat, ['izin', 'sakit', 'uzur_syarii', 'haid', 'dispensasi']);

                $bLabel = $isDone ? ($rawStat === 'hadir_sendiri' ? 'Munfarid' : 'Berjamaah') : ($isExcused ? 'Uzur' : 'Tidak Hadir');
                $bType = $isDone ? 'green' : ($isExcused ? 'amber' : 'red');

                $prayerName = strtolower($wd->session?->template?->prayer_name ?? $wd->session?->template?->nama ?? '');

                // Gunakan attended_at aktual jika ada, atau waktu sesi
                if ($wd->attended_at) {
                    $recTime = Carbon::parse($wd->attended_at)->timezone('Asia/Jakarta')->format('H:i');
                } elseif ($wd->session?->scheduled_start_at) {
                    $recTime = Carbon::parse($wd->session->scheduled_start_at)->timezone('Asia/Jakarta')->format('H:i');
                } else {
                    $recTime = null;
                }

                $rawTime = $recTime ? ($today . ' ' . $recTime . ':00') : ($today . ' 00:00:00');
                $worshipCarbon = Carbon::parse($rawTime);

                // Anti-future filter untuk hari ini
                if ($isViewingTodayDate && $recTime && $worshipCarbon->gt($nowWib)) {
                    continue;
                }

                $musyrifName = $wd->verifier?->name ?? $wd->session?->supervisor?->nama_lengkap ?? $wd->session?->supervisor?->name ?? null;

                $rawPrayerName = trim($wd->session?->template?->prayer_name ?: ($wd->session?->template?->nama ?: ''));
                $cleanPrayerName = preg_replace('/^(shalat|sholat)\s+/i', '', $rawPrayerName);
                $cleanPrayerName = preg_replace('/\s+bersama$/i', '', $cleanPrayerName);

                $activities[] = [
                    'id' => 'worship-' . $wd->id,
                    'raw_time' => $rawTime,
                    'type' => 'sholat',
                    'title' => 'Sholat ' . ucwords($cleanPrayerName),
                    'subtitle' => ($isDone ? 'Berjamaah di Masjid / Musholla' : 'Uzur Syar\'i / Dispensasi'),
                    'date_label' => $isViewingTodayDate ? 'Hari ini' : Carbon::parse($today)->isoFormat('D MMM'),
                    'time_label' => $recTime,
                    'is_past' => ! $isViewingTodayDate,
                    'badge_label' => $bLabel,
                    'badge_type' => $bType,
                    'icon' => 'hands-pray',
                    'icon_bg' => '#EFF6FF',
                    'icon_color' => '#3B82F6',
                    'screen' => 'Mutabaah',
                    'verifier_role' => 'Musyrif / Pembina Ibadah',
                    'verifier_name' => $musyrifName,
                ];
            }
        } catch (\Throwable $e) {}

        // 4. Setoran Tahfizh Al-Qur'an oleh Musyrif / Ustadz Hari Ini (TahfizhDailyLog)
        try {
            $tahfizhTodayList = TahfizhDailyLog::query()
                ->with(['teacher', 'employee', 'teacherUser'])
                ->where('student_id', $student->id)
                ->whereDate('record_date', $today)
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($tahfizhTodayList as $tLog) {
                $surahName = $tLog->hafalan_surah_name ?: ($tLog->surah ?: ($tLog->tilawah_text ?: ($tLog->murajaah_text ?: null)));
                $ayahInfo = '';
                if ($tLog->hafalan_ayah_start && $tLog->hafalan_ayah_end) {
                    $ayahInfo = ' (Ayat ' . $tLog->hafalan_ayah_start . '–' . $tLog->hafalan_ayah_end . ')';
                }

                // Jam aktual saat ustadz menginput setoran di sistem
                $recTime = $tLog->created_at 
                    ? Carbon::parse($tLog->created_at)->timezone('Asia/Jakarta')->format('H:i') 
                    : ($tLog->record_date ? Carbon::parse($tLog->record_date)->format('H:i') : null);

                $rawTime = $today . ' ' . ($recTime ?: '00:00') . ':00';
                $tahfizhCarbon = Carbon::parse($rawTime);

                // Anti-future filter untuk hari ini
                if ($isViewingTodayDate && $tahfizhCarbon->gt($nowWib)) {
                    continue;
                }

                $tahfizhTeacherName = $tLog->teacher?->full_name ?? $tLog->teacherUser?->name ?? $tLog->employee?->name ?? $tLog->teacher_name ?? null;

                $activities[] = [
                    'id' => 'tahfizh-' . $tLog->id,
                    'raw_time' => $rawTime,
                    'type' => 'tahfizh',
                    'title' => 'Membaca Al-Qur’an (Tahfizh)',
                    'subtitle' => 'Surah ' . $surahName . $ayahInfo,
                    'date_label' => $isViewingTodayDate ? 'Hari ini' : Carbon::parse($today)->isoFormat('D MMM'),
                    'time_label' => $recTime,
                    'is_past' => ! $isViewingTodayDate,
                    'badge_label' => 'Selesai',
                    'badge_type' => 'green',
                    'icon' => 'book-open-page-variant',
                    'icon_bg' => '#FEF2F2',
                    'icon_color' => '#EF4444',
                    'screen' => 'Tahfizh',
                    'verifier_role' => 'Guru Pembimbing Tahfizh',
                    'verifier_name' => $tahfizhTeacherName,
                ];
            }
        } catch (\Throwable $e) {}

        // 5. Catatan / Komentar Guru untuk Siswa Hari Ini (StudentNote)
        try {
            $teacherNotesToday = StudentNote::query()
                ->with(['teacher.user'])
                ->where('student_id', $student->id)
                ->where('visible_to_parent', true)
                ->where(function ($q) use ($today) {
                    $q->whereDate('date', $today)->orWhereDate('created_at', $today);
                })
                ->latest()
                ->take(2)
                ->get();

            foreach ($teacherNotesToday as $tNote) {
                $noteTime = $tNote->created_at 
                    ? Carbon::parse($tNote->created_at)->timezone('Asia/Jakarta')->format('H:i') 
                    : ($tNote->date ? Carbon::parse($tNote->date)->timezone('Asia/Jakarta')->format('H:i') : null);

                $rawTime = $noteTime ? ($today . ' ' . $noteTime . ':00') : ($today . ' 00:00:00');
                $noteCarbon = Carbon::parse($rawTime);

                if ($isViewingTodayDate && $noteTime && $noteCarbon->gt($nowWib)) {
                    continue;
                }

                $noteTeacherName = $tNote->teacher?->user?->name ?? $tNote->teacher?->nama_lengkap ?? $tNote->teacher?->name ?? null;

                $activities[] = [
                    'id' => 'note-' . $tNote->id,
                    'raw_time' => $rawTime,
                    'type' => 'komentar',
                    'title' => 'Guru memberikan catatan evaluasi',
                    'subtitle' => ($tNote->content ?? $tNote->title) ? ('“' . ($tNote->content ?? $tNote->title) . '”') : null,
                    'date_label' => $isViewingTodayDate ? 'Hari ini' : Carbon::parse($today)->isoFormat('D MMM'),
                    'time_label' => $noteTime,
                    'is_past' => ! $isViewingTodayDate,
                    'badge_label' => 'Catatan',
                    'badge_type' => 'purple',
                    'has_red_dot' => true,
                    'icon' => 'message-text',
                    'icon_bg' => '#F5F3FF',
                    'icon_color' => '#8B5CF6',
                    'screen' => 'Komentar',
                    'verifier_role' => 'Wali Kelas / Guru Pengampu',
                    'verifier_name' => $noteTeacherName,
                ];
            }
        } catch (\Throwable $e) {}

        // 6. Tugas Dikumpulkan Hari Ini (LmsPengumpulanTugas)
        try {
            $todaySubmissions = LmsPengumpulanTugas::query()
                ->with(['penugasan.subject', 'penugasan.teacher', 'penugasan.employee'])
                ->where('siswa_id', $student->id)
                ->where(function ($q) use ($today) {
                    $q->whereDate('waktu_kumpul', $today)->orWhereDate('created_at', $today);
                })
                ->latest()
                ->take(3)
                ->get();

            foreach ($todaySubmissions as $sub) {
                $subPenugasan = $sub->penugasan;
                $subjectName = $subPenugasan?->subject?->name ?? $subPenugasan?->subject?->nama ?? null;
                $subCarbon = Carbon::parse($sub->waktu_kumpul ?? $sub->created_at)->timezone('Asia/Jakarta');
                $recTime = $subCarbon->format('H:i');
                $rawTime = $subCarbon->format('Y-m-d H:i:s');

                if ($isViewingTodayDate && $subCarbon->gt($nowWib)) {
                    continue;
                }

                $activities[] = [
                    'id' => 'tugas-' . $sub->id,
                    'raw_time' => $rawTime,
                    'type' => 'tugas',
                    'title' => 'Mengumpulkan tugas' . ($subjectName ? ' ' . $subjectName : ''),
                    'subtitle' => ($subPenugasan?->judul_tugas ?? $subPenugasan?->judul) ? ('Tugas: ' . ($subPenugasan->judul_tugas ?? $subPenugasan->judul)) : null,
                    'date_label' => $isViewingTodayDate ? 'Hari ini' : Carbon::parse($today)->isoFormat('D MMM'),
                    'time_label' => $recTime,
                    'is_past' => ! $isViewingTodayDate,
                    'badge_label' => 'Terkirim',
                    'badge_type' => 'green',
                    'icon' => 'file-document-outline',
                    'icon_bg' => '#FFFBEB',
                    'icon_color' => '#F59E0B',
                    'screen' => 'Tugas',
                ];
            }
        } catch (\Throwable $e) {}

        // 7. Pengisian Mutabaah Harian (tampil jika terdapat catatan amalan harian terisi)
        try {
            if ($mutabaahHeader && ($mutabaahHeader->good_count > 0 || $mutabaahHeader->total_items > 0)) {
                $mutCarbon = $mutabaahHeader->updated_at 
                    ? Carbon::parse($mutabaahHeader->updated_at)->timezone('Asia/Jakarta') 
                    : ($mutabaahHeader->created_at ? Carbon::parse($mutabaahHeader->created_at)->timezone('Asia/Jakarta') : null);

                // Jika jam di masa depan untuk hari ini, jangan tampilkan
                if (! ($isViewingTodayDate && $mutCarbon && $mutCarbon->gt($nowWib))) {
                    $recTime = $mutCarbon ? $mutCarbon->format('H:i') : null;
                    $rawTime = $recTime ? ($today . ' ' . $recTime . ':00') : ($today . ' 00:00:00');

                        $activities[] = [
                            'id' => 'mutabaah-' . $mutabaahHeader->id,
                            'raw_time' => $rawTime,
                            'type' => 'mutabaah',
                            'title' => 'Mengisi mutabaah harian',
                            'subtitle' => 'Amalan harian siswa (' . (int) $mutabaahHeader->good_count . ' kegiatan terlaksana)',
                            'date_label' => $isViewingTodayDate ? 'Hari ini' : Carbon::parse($today)->isoFormat('D MMM'),
                            'time_label' => $recTime,
                            'is_past' => ! $isViewingTodayDate,
                            'badge_label' => 'Selesai',
                            'badge_type' => 'green',
                            'icon' => 'calendar-check',
                            'icon_bg' => '#EFF6FF',
                            'icon_color' => '#3B82F6',
                            'screen' => 'Mutabaah',
                        ];
                    }
                }
        } catch (\Throwable $e) {}

        // Urutkan descending (kegiatan paling baru di atas) untuk feed realtime orang tua
        usort($activities, function ($a, $b) {
            return strcmp($b['raw_time'] ?? '', $a['raw_time'] ?? '');
        });

        return response()->json([
            'success' => true,
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->full_name ?? $student->nama_lengkap ?? $student->name,
                    'nis' => $student->nis ?? $student->nisn ?? null,
                    'class_name' => $student->kelas?->nama_kelas ?? $student->kelas?->name ?? null,
                    'unit_name' => $student->educationUnit?->name ?? $student->kelas?->unitPendidikan?->name ?? $student->kelas?->unit_pendidikan?->name ?? $student->unit_name ?? null,
                ],
                'date' => $today,
                'server_date' => $serverDate,
                'program' => [
                    'type' => $programType, // 'fullday' | 'boarding'
                    'label' => $programLabel,
                    'is_boarding' => $isBoarding,
                ],
                'activities' => $activities,
                'gate' => $gateInfo,
                'attendance' => $gateInfo,
                'lessons' => [
                    'total_recorded' => $totalLessonRecorded,
                    'hadir_count' => $hadirCount,
                    'items' => $lessonsList,
                ],
                'prayers' => [
                    'done_count' => $prayerDoneCount,
                    'total_scheduled' => $prayersList->count(),
                    'items' => $prayersList,
                ],
                'tahfizh' => $tahfizhInfo,
                'mutabaah' => $mutabaahInfo,
            ],
        ]);
    }
}
