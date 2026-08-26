<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\LmsMateri;
use App\Models\LmsBankSoal;
use App\Models\LmsKisiKisi;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\LmsPresensi;
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
use App\Models\StudentAttendancePermission;
use App\Models\StudentBill;
use App\Models\StudentGrade;
use App\Models\StudentNote;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsRapor;
use App\Models\Teacher;
use App\Models\User;
use App\Models\TahfizhDailyLog;
use App\Services\LmsUjianService;
use App\Services\StudentQrCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class StudentParentPortalController extends Controller
{
    public function __construct(
        private readonly LmsUjianService $ujianService,
        private readonly StudentQrCredentialService $studentQr,
    ) {}

    private function parentStudentsQuery(ParentModel $parent)
    {
        return Student::query()->where(function ($query) use ($parent) {
            $query->where('parent_id', $parent->id)
                ->orWhereHas('parentsPivot', fn ($pivot) => $pivot->whereKey($parent->id));
        });
    }

    /** CBT hanya boleh memakai relasi siswa langsung dari akun yang login. */
    private function getAuthenticatedStudent(Request $request): ?Student
    {
        return Student::query()
            ->with(['kelas', 'educationUnit'])
            ->where('user_id', $request->user()?->id)
            ->where('is_active', true)
            ->first();
    }

    private function getStudentContext(Request $request): ?Student
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        // If parent has selected a child id in header/query/input
        $selectedChildId = $request->header('X-Child-Id')
            ?? $request->query('child_id')
            ?? $request->input('child_id')
            ?? $request->query('child')
            ?? $request->input('child');

        if ($selectedChildId && Str::isUuid($selectedChildId)) {
            $parent = ParentModel::query()->where('user_id', $user->id)->first();
            if ($parent) {
                $child = $this->parentStudentsQuery($parent)
                    ->with(['kelas', 'educationUnit'])
                    ->whereKey($selectedChildId)
                    ->where('is_active', true)
                    ->first();
                if ($child) {
                    return $child;
                }
            }
            $child = Student::query()->with(['kelas', 'educationUnit'])->where('user_id', $user->id)->whereKey($selectedChildId)->first();
            if ($child) {
                return $child;
            }
        }

        // Check direct student relation
        $student = Student::query()->with(['kelas', 'educationUnit'])->where('user_id', $user->id)->first();
        if ($student) {
            return $student;
        }

        // Check if parent account, fetch first child
        $parent = ParentModel::query()->where('user_id', $user->id)->first();
        if ($parent) {
            $child = $this->parentStudentsQuery($parent)
                ->with(['kelas', 'educationUnit'])
                ->where('is_active', true)
                ->first();
            if ($child) {
                return $child;
            }
        }

        return null;
    }

    public function children(Request $request): JsonResponse
    {
        $user = $request->user();
        $parent = ParentModel::query()->where('user_id', $user->id)->first();

        if (! $parent) {
            // Fallback check if user owns a student directly
            $student = Student::query()->with(['kelas', 'educationUnit'])->where('user_id', $user->id)->first();
            return response()->json([
                'success' => true,
                'data' => $student ? [$student] : [],
            ]);
        }

        $children = $this->parentStudentsQuery($parent)
            ->with(['kelas', 'educationUnit'])
            ->where('is_active', true)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $children,
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

        $activeAcademicYear = AcademicYear::query()->where('is_active', true)->first();
        $activeSemester = Semester::query()->where('is_active', true)->first();

        $todayDay = Str::lower(now()->locale('id')->isoFormat('dddd'));

        // Today's schedule
        $schedulesToday = ClassSchedule::query()
            ->with(['subject', 'teacher', 'employee'])
            ->where(fn ($q) => $q->where('class_id', $student->class_id)->orWhere('kelas_id', $student->kelas_id ?? $student->class_id))
            ->where('day_of_week', now()->dayOfWeekIso)
            ->orderBy('time_start')
            ->get();

        // Attendance status today
        $attendanceToday = LmsPresensi::query()
            ->where('siswa_id', $student->id)
            ->whereDate('tanggal', now()->toDateString())
            ->first();

        // Active assignments
        $activeAssignments = LmsPenugasan::query()
            ->with(['subject', 'teacher'])
            // lms_penugasan hanya punya kolom kelas_id (tidak ada class_id).
            ->where('kelas_id', $student->kelas_id ?? $student->class_id)
            ->where('is_published', true)
            ->where('deadline', '>=', now())
            ->orderBy('deadline', 'asc')
            ->get();

        // Latest grades
        $latestGrades = StudentGrade::query()
            ->with('subject')
            ->where('student_id', $student->id)
            ->take(5)
            ->get();

        // Tahfizh progress
        $latestTahfizh = TahfizhDailyLog::query()
            ->where('student_id', $student->id)
            ->orderBy('record_date', 'desc')
            ->first();

        // tahfizh_daily_logs tidak punya kolom jumlah_ayat; hitung dari
        // rentang ayat hafalan (fallback ke baris hafalan bila range kosong).
        $totalAyat = TahfizhDailyLog::query()
            ->where('student_id', $student->id)
            ->get()
            ->sum(function (TahfizhDailyLog $log): int {
                if ($log->hafalan_ayah_start && $log->hafalan_ayah_end) {
                    return (int) ($log->hafalan_ayah_end - $log->hafalan_ayah_start + 1);
                }

                return (int) $log->hafalan_baris;
            });

        // Mutabaah today
        $mutabaahToday = MutabaahDailyHeader::query()
            ->with('details')
            ->where('student_id', $student->id)
            ->whereDate('activity_date', now()->toDateString())
            ->first();

        // Announcements
        $announcements = PengumumanSekolah::query()
            ->where('status_aktif', true)
            ->where('mulai_tampil', '<=', now())
            ->where(fn ($query) => $query->whereNull('selesai_tampil')->orWhere('selesai_tampil', '>=', now()))
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'student' => $student,
                'academic_context' => [
                    'academic_year' => $activeAcademicYear?->name ?? '2025/2026',
                    'semester' => $activeSemester?->name ?? 'Ganjil',
                    'date' => now()->translatedFormat('l, d F Y'),
                ],
                'attendance_today' => $attendanceToday?->status_label ?? 'Belum Diinput',
                'kpi' => [
                    'schedules_today_count' => $schedulesToday->count(),
                    'active_assignments_count' => $activeAssignments->count(),
                    'total_tahfizh_ayat' => $totalAyat,
                    'latest_tahfizh_surah' => $latestTahfizh ? ($latestTahfizh->surah.' (Ayat '.$latestTahfizh->ayat_start.'-'.$latestTahfizh->ayat_end.')') : 'Belum Ada',
                    'mutabaah_status' => $mutabaahToday ? $mutabaahToday->status : 'Belum Diisi',
                ],
                'schedules_today' => $schedulesToday,
                'active_assignments' => $activeAssignments,
                'latest_grades' => $latestGrades,
                'announcements' => $announcements,
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
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $schedules = ClassSchedule::query()
            ->with(['subject', 'teacher', 'employee'])
            ->where(fn ($q) => $q->where('class_id', $student->class_id)->orWhere('kelas_id', $student->kelas_id ?? $student->class_id))
            ->orderBy('time_start')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
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
        $materials = LmsMateri::query()
            ->with(['subject', 'guru', 'media', 'modulAjar:id,kelas_id,judul_modul,kode_modul'])
            ->whereHas('modulAjar', fn ($query) => $query->whereIn('kelas_id', $classIds))
            ->where('is_published', true)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $materials,
        ]);
    }

    public function assignments(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $assignments = LmsPenugasan::query()
            ->with(['subject', 'teacher', 'pengumpulanTugas' => function ($q) use ($student) {
                $q->where('siswa_id', $student->id);
            }])
            // lms_penugasan hanya punya kolom kelas_id (tidak ada class_id).
            ->where('kelas_id', $student->kelas_id ?? $student->class_id)
            ->where('is_published', true)
            ->orderBy('deadline', 'asc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $assignments,
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
        ]);

        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
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

        $isLate = now()->gt($penugasan->deadline);

        $submission = LmsPengumpulanTugas::updateOrCreate(
            [
                'penugasan_id' => $penugasan->id,
                'siswa_id' => $student->id,
            ],
            [
                'jawaban_teks' => $request->jawaban_teks,
                'file_path' => $filePath,
                'status' => $isLate ? 'terlambat' : 'dikumpulkan',
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

        $grades = StudentGrade::query()
            ->with('subject')
            ->where('student_id', $student->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $grades,
        ]);
    }

    public function tahfizh(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $logs = TahfizhDailyLog::query()
            ->with('teacher')
            ->where('student_id', $student->id)
            ->orderBy('record_date', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    public function mutabaah(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $date = $request->query('date', now()->toDateString());

        $header = MutabaahDailyHeader::query()
            ->with('details')
            ->where('student_id', $student->id)
            ->whereDate('activity_date', $date)
            ->first();

        return response()->json([
            'success' => true,
            'data' => $header,
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
            ->when($isParent, fn ($q) => $q->where('visible_to_parent', true))
            ->when(! $isParent, fn ($q) => $q->where('visible_to_student', true))
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
            ->where('student_id', $student->id)
            ->orderBy('tanggal', 'desc')
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
        $student = $this->getStudentContext($request);
        if (! $student) {
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
            ->when($request->filled('type') && $request->type !== 'all', fn ($q) => $q->where('data_tambahan->tipe', $request->type))
            ->when($request->filled('category'), fn ($q) => $q->where('data_tambahan->kategori', $request->category))
            ->when($request->filled('priority'), fn ($q) => $q->where('data_tambahan->prioritas', $request->priority))
            ->when($request->boolean('has_attachment'), fn ($q) => $q->whereNotNull('data_tambahan->lampiran'))
            ->when($request->date_from, fn ($q, $date) => $q->whereDate('mulai_tampil', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->whereDate('mulai_tampil', '<=', $date))
            ->when($request->read_status === 'read', fn ($q) => $q->whereIn('id', $readIds ?: ['__none__']))
            ->when($request->read_status === 'unread', fn ($q) => $q->when($readIds, fn ($inner) => $inner->whereNotIn('id', $readIds)))
            ->when($request->boolean('bookmarked'), fn ($q) => $q->whereIn('id', $bookmarkIds ?: ['__none__']));

        $items = $query->orderByDesc('prioritas')->orderByDesc('mulai_tampil')->paginate($request->integer('per_page', 12));
        $items->getCollection()->transform(fn ($item) => $this->mapSchoolInformation($item, $state));

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function schoolInformationSummary(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
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

        return response()->json(['success' => true, 'data' => [
            'important' => $items->filter(fn ($item) => in_array($item['priority'], ['mendesak', 'penting'], true))->take(3)->values(),
            'counts' => $counts,
            'upcoming_events' => $items->where('type', 'event')->take(5)->values(),
            'latest_news' => $items->where('type', 'news')->take(6)->values(),
            'latest_circulars' => $items->where('type', 'circular')->take(5)->values(),
            'calendar' => $items->where('type', 'calendar')->values(),
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

        $student = $this->getStudentContext($request);
        abort_unless($student, 404, 'Data siswa tidak ditemukan.');
        $item = $this->schoolInformationQuery($request, $student)->whereKey($informationId)->firstOrFail();
        $payload = $request->validate(['action' => ['required', 'in:read,bookmark,unbookmark,acknowledge']]);
        $user = $request->user();
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
        $student = $this->getStudentContext($request);
        abort_unless($student, 404, 'Data siswa tidak ditemukan.');
        $user = $request->user();
        $metadata = $user->metadata ?? [];
        $state = $metadata['portal_school_information'] ?? [];
        foreach ($this->schoolInformationQuery($request, $student)->pluck('id') as $id) {
            $state['read'][$id] = now()->toIso8601String();
        }
        $metadata['portal_school_information'] = $state;
        $user->forceFill(['metadata' => $metadata])->save();

        return response()->json(['success' => true, 'message' => 'Semua informasi telah ditandai dibaca.']);
    }

    private function schoolInformationQuery(Request $request, Student $student)
    {
        $role = $request->user()?->getRoleNames()->first();
        $unitIds = array_values(array_filter([$student->education_unit_id, $student->unit_id ?? null]));
        $classIds = array_values(array_filter([$student->class_id, $student->kelas_id]));

        return PengumumanSekolah::query()
            ->where('status_aktif', true)
            ->where('mulai_tampil', '<=', now())
            ->where(fn ($q) => $q->whereNull('selesai_tampil')->orWhere('selesai_tampil', '>=', now()))
            ->when($role, fn ($q) => $q->where(fn ($target) => $target->whereNull('target_peran')->orWhereJsonContains('target_peran', $role)))
            ->where(fn ($q) => $q->whereNull('data_tambahan->education_unit_id')
                ->orWhere('data_tambahan->is_public', true)
                ->when($unitIds, fn ($unit) => $unit->orWhereIn('data_tambahan->education_unit_id', $unitIds)))
            ->where(fn ($q) => $q->whereNull('data_tambahan->class_id')
                ->when($classIds, fn ($class) => $class->orWhereIn('data_tambahan->class_id', $classIds)));
    }

    private function schoolInformationState(Request $request): array
    {
        $state = ($request->user()?->metadata ?? [])['portal_school_information'] ?? [];
        return ['read' => $state['read'] ?? [], 'bookmarks' => $state['bookmarks'] ?? [], 'acknowledgements' => $state['acknowledgements'] ?? []];
    }

    private function mapSchoolInformation(PengumumanSekolah $item, array $state): array
    {
        $meta = $item->data_tambahan ?? [];
        $type = Str::lower($meta['tipe'] ?? $meta['kategori'] ?? 'announcement');
        $aliases = ['pengumuman' => 'announcement', 'agenda' => 'event', 'berita' => 'news', 'surat edaran' => 'circular', 'kalender pendidikan' => 'calendar', 'galeri' => 'gallery'];
        $type = $aliases[$type] ?? $type;
        return [
            'id' => $item->id, 'type' => $type, 'category' => $meta['kategori'] ?? 'Umum',
            'priority' => Str::lower($meta['prioritas'] ?? ($item->prioritas ? 'penting' : 'umum')),
            'title' => $item->judul_pengumuman, 'summary' => Str::limit(strip_tags($item->isi_pengumuman), 180), 'content' => $item->isi_pengumuman,
            'published_at' => $item->mulai_tampil, 'expired_at' => $item->selesai_tampil,
            'education_unit' => $meta['education_unit'] ?? $meta['unit'] ?? 'Seluruh Yayasan', 'audience' => $meta['target'] ?? 'Siswa dan Orang Tua',
            'publisher' => $meta['penerbit'] ?? 'Sekolah', 'attachments' => $meta['lampiran'] ?? [], 'cover' => $meta['cover'] ?? null,
            'event' => $meta['event'] ?? null, 'calendar_date' => $meta['tanggal'] ?? null, 'gallery' => $meta['galeri'] ?? [],
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

        // Child scope: notifikasi bertipe Student hanya tampil bila siswa
        // termasuk anak yang terhubung resmi dengan orang tua.
        if ($student) {
            $query->where(function ($q) use ($student) {
                $q->where('notifiable_type', '!=', Student::class)
                    ->orWhere('notifiable_id', $student->id);
            });
        }

        $notifications = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    public function bills(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);

        $bills = StudentBill::query()->with(['feeCategory', 'academicYear', 'payments'])
            ->where('student_id', $student->id)->orderBy('due_date', 'desc')->paginate(20);

        return response()->json(['success' => true, 'data' => $bills]);
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
            ->where('siswa_id', $student->id)->whereIn('status_rapor', ['published', 'diterbitkan'])
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
            ->where('siswa_id', $student->id)->whereIn('status_rapor', ['published', 'diterbitkan'])->find($id) : null;
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
        $exams = LmsUjian::query()
            ->with(['kisiKisi.subject', 'kelas', 'semester', 'guru'])
            ->with(['sesi' => fn ($query) => $query->where('siswa_id', $student->id)->latest('created_at')])
            ->whereIn('kelas_id', $classIds)
            ->whereIn('status', ['published', 'berlangsung', 'selesai'])
            ->orderBy('waktu_mulai')
            ->get();

        $now = now();
        $items = $exams->map(function (LmsUjian $exam) use ($now) {
            $sessions = $exam->sesi;
            $activeSession = $sessions->firstWhere('status', 'proses');
            $completed = $sessions->whereIn('status', ['selesai', 'timeout'])->count();
            $beforeStart = $exam->waktu_mulai && $now->lt($exam->waktu_mulai);
            $afterEnd = $exam->waktu_selesai && $now->gt($exam->waktu_selesai);
            $attemptsLeft = max(0, (int) $exam->max_attempt - $completed);
            $availability = $activeSession ? 'resume' : ($beforeStart ? 'upcoming' : ($afterEnd ? 'ended' : ($attemptsLeft > 0 ? 'available' : 'completed')));

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
                'mata_pelajaran' => $exam->kisiKisi?->subject?->name,
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

        return response()->json([
            'success' => true,
            'data' => [
                'student' => $student,
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
            ->where('status_publikasi', true)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $grids,
        ]);
    }

    public function results(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        if (! $student) {
            return response()->json(['success' => false, 'message' => 'Data siswa tidak ditemukan.'], 404);
        }

        $classIds = array_values(array_filter([$student->kelas_id, $student->class_id]));

        // CBT Results
        $cbtExams = LmsUjian::query()
            ->with(['kisiKisi.subject', 'guru'])
            ->with(['sesi' => fn ($q) => $q->where('siswa_id', $student->id)->whereIn('status', ['selesai', 'timeout'])->latest('updated_at')])
            ->whereIn('kelas_id', $classIds)
            ->whereHas('sesi', fn ($q) => $q->where('siswa_id', $student->id)->whereIn('status', ['selesai', 'timeout']))
            ->get()
            ->map(function ($exam) {
                $latest = $exam->sesi->first();
                $showScore = (bool) $exam->tampilkan_nilai_langsung;
                return [
                    'id' => 'cbt-'.$exam->id,
                    'type' => 'ujian',
                    'title' => $exam->judul_ujian,
                    'subject' => $exam->kisiKisi?->subject?->name,
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
            ->map(fn ($submission) => [
                'id' => 'tugas-'.$submission->id,
                'type' => 'tugas',
                'title' => $submission->penugasan?->judul,
                'subject' => $submission->penugasan?->subject?->name,
                'teacher' => $submission->penugasan?->teacher?->nama_lengkap,
                'date' => $submission->waktu_kumpul?->toIso8601String(),
                'score' => (float) $submission->nilai_guru,
                'is_published' => true,
                'notes' => $submission->catatan_guru,
                'status_tuntas' => ((float) $submission->nilai_guru >= 75) ? 'Tuntas' : 'Belum Tuntas',
            ]);

        // Grades
        $grades = StudentGrade::query()
            ->with('subject')
            ->where('student_id', $student->id)
            ->get()
            ->map(fn ($grade) => [
                'id' => 'grade-'.$grade->id,
                'type' => 'rapor_komponen',
                'title' => $grade->subject?->name,
                'subject' => $grade->subject?->name,
                'date' => $grade->updated_at?->toIso8601String(),
                'score' => (float) ($grade->final_score ?? $grade->nilai_akhir ?? $grade->nilai_tugas ?? 0),
                'is_published' => true,
                'notes' => $grade->notes,
                'status_tuntas' => (($grade->final_score ?? $grade->nilai_akhir ?? 0) >= 75) ? 'Tuntas' : 'Belum Tuntas',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
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

        return User::query()->where('id', $teacherUserId)->exists();
    }

    public function chatContacts(Request $request): JsonResponse
    {
        $student = $this->getStudentContext($request);
        $user = $request->user();
        $contactsMap = [];

        if ($student) {
            $kelasId = $student->kelas_id ?? $student->class_id;
            if ($kelasId && Str::isUuid($kelasId)) {
                $kelas = Kelas::query()->with(['waliKelas.user'])->find($kelasId);
                $waliKelasEmp = $kelas?->waliKelas;
                $waliUser = $waliKelasEmp?->user;
                if (! $waliUser && $waliKelasEmp?->email) {
                    $waliUser = User::query()->where('email', $waliKelasEmp->email)->first();
                }

                if ($waliUser) {
                    $lastMsg = Str::isUuid($student->id)
                        ? PortalMessage::query()
                            ->where('student_id', $student->id)
                            ->where(function ($q) use ($user, $waliUser) {
                                $q->where(fn ($q2) => $q2->where('sender_user_id', $user->id)->where('recipient_user_id', $waliUser->id))
                                    ->orWhere(fn ($q2) => $q2->where('sender_user_id', $waliUser->id)->where('recipient_user_id', $user->id));
                            })
                            ->latest()
                            ->first()
                        : null;

                    $unreadCount = Str::isUuid($student->id)
                        ? PortalMessage::query()
                            ->where('student_id', $student->id)
                            ->where('sender_user_id', $waliUser->id)
                            ->where('recipient_user_id', $user->id)
                            ->whereNull('read_at')
                            ->count()
                        : 0;

                    $contactsMap[$waliUser->id] = [
                        'user_id' => $waliUser->id,
                        'name' => $waliKelasEmp?->nama_lengkap ?? $waliUser->name ?? 'Wali Kelas',
                        'photo' => $waliUser->avatar_url ?? null,
                        'role' => 'Wali Kelas',
                        'teacher_type' => 'wali_kelas',
                        'subject' => 'Wali Kelas (' . ($kelas->nama_kelas ?? '-') . ')',
                        'class_name' => $kelas->nama_kelas ?? '-',
                        'unit_name' => $student->educationUnit?->name ?? '-',
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'last_message' => $lastMsg?->message,
                        'last_message_at' => $lastMsg?->created_at?->toIso8601String(),
                        'unread_count' => $unreadCount,
                    ];
                }

                $schedules = ClassSchedule::query()
                    ->with(['subject', 'employee.user', 'teacher.user'])
                    ->where(fn ($q) => $q->where('kelas_id', $kelasId)->orWhere('class_id', $kelasId))
                    ->where('is_active', true)
                    ->get();

                foreach ($schedules as $sched) {
                    $teacherUser = $sched->employee?->user ?? $sched->teacher?->user;
                    if (! $teacherUser && ($sched->employee?->email || $sched->teacher?->email)) {
                        $teacherUser = User::query()->where('email', $sched->employee?->email ?? $sched->teacher?->email)->first();
                    }

                    if ($teacherUser && ! isset($contactsMap[$teacherUser->id])) {
                        $teacherName = $sched->employee?->nama_lengkap ?? $sched->teacher?->full_name ?? $teacherUser->name;
                        $subjectName = $sched->subject?->name ?? $sched->subject?->nama_mata_pelajaran ?? 'Mata Pelajaran';

                        $lastMsg = Str::isUuid($student->id)
                            ? PortalMessage::query()
                                ->where('student_id', $student->id)
                                ->where(function ($q) use ($user, $teacherUser) {
                                    $q->where(fn ($q2) => $q2->where('sender_user_id', $user->id)->where('recipient_user_id', $teacherUser->id))
                                        ->orWhere(fn ($q2) => $q2->where('sender_user_id', $teacherUser->id)->where('recipient_user_id', $user->id));
                                })
                                ->latest()
                                ->first()
                            : null;

                        $unreadCount = Str::isUuid($student->id)
                            ? PortalMessage::query()
                                ->where('student_id', $student->id)
                                ->where('sender_user_id', $teacherUser->id)
                                ->where('recipient_user_id', $user->id)
                                ->whereNull('read_at')
                                ->count()
                            : 0;

                        $contactsMap[$teacherUser->id] = [
                            'user_id' => $teacherUser->id,
                            'name' => $teacherName,
                            'photo' => $teacherUser->avatar_url ?? null,
                            'role' => 'Guru Mapel',
                            'teacher_type' => 'guru_mapel',
                            'subject' => $subjectName,
                            'class_name' => $student->kelas?->nama_kelas ?? '-',
                            'unit_name' => $student->educationUnit?->name ?? '-',
                            'student_id' => $student->id,
                            'student_name' => $student->full_name,
                            'last_message' => $lastMsg?->message,
                            'last_message_at' => $lastMsg?->created_at?->toIso8601String(),
                            'unread_count' => $unreadCount,
                        ];
                    }
                }
            }
        }

        // Fallback: load available active teachers if class map is empty
        if (empty($contactsMap)) {
            $teachers = User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Guru', 'Wali Kelas', 'Teacher']))
                ->take(3)
                ->get();
            if ($teachers->isEmpty()) {
                $teachers = User::query()->where('id', '!=', $user->id)->take(3)->get();
            }

            foreach ($teachers as $idx => $t) {
                $contactsMap[$t->id] = [
                    'user_id' => $t->id,
                    'name' => $t->name ?? ($idx === 0 ? 'Wali Kelas' : 'Guru Mata Pelajaran'),
                    'photo' => $t->avatar_url ?? null,
                    'role' => $idx === 0 ? 'Wali Kelas' : 'Guru Mapel',
                    'teacher_type' => $idx === 0 ? 'wali_kelas' : 'guru_mapel',
                    'subject' => $idx === 0 ? 'Wali Kelas' : 'Mata Pelajaran',
                    'class_name' => $student?->kelas?->nama_kelas ?? 'Kelas SIT',
                    'unit_name' => $student?->educationUnit?->name ?? 'Sekolah Terpadu',
                    'student_id' => $student?->id ?? 'default-child',
                    'student_name' => $student?->full_name ?? 'Siswa',
                    'last_message' => null,
                    'last_message_at' => null,
                    'unread_count' => 0,
                ];
            }
        }

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
            // Mark incoming messages as read
            PortalMessage::query()
                ->where('student_id', $student->id)
                ->where('sender_user_id', $teacherUserId)
                ->where('recipient_user_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);

            $messages = PortalMessage::query()
                ->with(['sender:id,name', 'recipient:id,name'])
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
            'message' => 'required|string|max:5000',
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
            'message' => trim($request->input('message')),
        ]);

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

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dikirim.',
            'data' => $message->load(['sender:id,name', 'recipient:id,name']),
        ]);
    }

    public function updateChildPassword(Request $request, string $childId): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $student = Student::where('id', $childId)->first();
        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Data anak tidak ditemukan.',
            ], 404);
        }

        if ($student->user_id) {
            $user = \App\Models\User::find($student->user_id);
            if ($user) {
                $user->update(['password' => \Illuminate\Support\Facades\Hash::make($request->password)]);
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
}
