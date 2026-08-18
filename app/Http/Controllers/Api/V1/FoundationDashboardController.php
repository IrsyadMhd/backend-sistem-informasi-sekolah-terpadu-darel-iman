<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\ParentModel;
use App\Models\PengumumanSekolah;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\FoundationDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FoundationDashboardController extends Controller
{
    protected FoundationDashboardService $service;

    public function __construct(FoundationDashboardService $service)
    {
        $this->service = $service;
    }

    /**
     * Aggregated main overview for Foundation Dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'unit_id' => $request->query('unit_id', 'all'),
            'academic_year_id' => $request->query('academic_year_id'),
            'period' => $request->query('period', 'year'),
        ];

        $data = $this->service->getDashboardOverview($filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Education units list with aggregate stats.
     */
    public function units(Request $request): JsonResponse
    {
        $filters = $request->only(['unit_id', 'jenis_unit_id', 'status', 'search']);
        $data = $this->service->getUnitSummaries($filters);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Single unit detail.
     */
    public function unitDetail(string $id): JsonResponse
    {
        $data = $this->service->getUnitDetail($id);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Employees & Teachers list (Read-Only).
     */
    public function employees(Request $request): JsonResponse
    {
        $query = Employee::with(['unit', 'position', 'division']);

        if ($request->filled('unit_id') && $request->query('unit_id') !== 'all') {
            $query->where('unit_id', $request->query('unit_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('niy', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }

        if ($request->filled('jabatan_id')) {
            $query->where('jabatan_id', $request->query('jabatan_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $employees = $query->paginate($perPage);

        return response()->json($employees);
    }

    /**
     * Teachers list, sourced from the same table as the Super Admin KPI.
     */
    public function teachers(Request $request): JsonResponse
    {
        $query = Teacher::with(['employee.unit', 'employee.position']);

        if ($request->filled('unit_id') && $request->query('unit_id') !== 'all') {
            $query->whereHas('employee', fn ($employee) => $employee->where('unit_id', $request->query('unit_id')));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $operator = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($teacher) use ($search, $operator) {
                $teacher->where('full_name', $operator, "%{$search}%")
                    ->orWhere('employee_number', $operator, "%{$search}%")
                    ->orWhereHas('employee', fn ($employee) => $employee
                        ->where('nama_lengkap', $operator, "%{$search}%")
                        ->orWhere('niy', $operator, "%{$search}%"));
            });
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $teachers = $query->orderBy('full_name')->paginate($perPage);
        $teachers->through(fn (Teacher $teacher) => $this->serializeTeacher($teacher));

        return response()->json($teachers);
    }

    /**
     * Active students list (Read-Only).
     */
    public function students(Request $request): JsonResponse
    {
        $query = Student::with(['educationUnit', 'kelas'])->where('is_active', true);

        if ($request->filled('unit_id') && $request->query('unit_id') !== 'all') {
            $query->where('unit_id', $request->query('unit_id'));
        }

        if ($request->filled('academic_year_id') && $request->query('academic_year_id') !== 'all') {
            $ayId = $request->query('academic_year_id');
            $activeYear = AcademicYear::find($ayId);
            $yearNum = $activeYear ? (int) substr($activeYear->name, 0, 4) : (int) $ayId;
            $query->where(function ($q) use ($ayId, $yearNum) {
                $q->where('tahun_masuk', $yearNum)
                  ->orWhereHas('kelas', fn ($k) => $k->where('tahun_ajaran_id', $ayId));
            });
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->query('gender'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $students = $query->paginate($perPage);

        return response()->json($students);
    }

    /**
     * New students list.
     */
    public function newStudents(Request $request): JsonResponse
    {
        $query = Student::with(['educationUnit', 'kelas'])
            ->where(function ($q) {
                $q->where('tahun_masuk', date('Y'))
                  ->orWhere('metadata->is_new_student', true);
            });

        if ($request->filled('unit_id') && $request->query('unit_id') !== 'all') {
            $query->where('unit_id', $request->query('unit_id'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $newStudents = $query->paginate($perPage);

        return response()->json($newStudents);
    }

    /**
     * Student mutations list.
     */
    public function studentMutations(Request $request): JsonResponse
    {
        $query = Student::with(['educationUnit', 'kelas'])
            ->whereNotNull('metadata->mutasi_type');

        if ($request->filled('unit_id') && $request->query('unit_id') !== 'all') {
            $query->where('unit_id', $request->query('unit_id'));
        }

        if ($request->filled('type')) {
            $query->where('metadata->mutasi_type', $request->query('type'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $mutations = $query->paginate($perPage);

        return response()->json($mutations);
    }

    /**
     * Graduations & Alumni list.
     */
    public function graduations(Request $request): JsonResponse
    {
        $query = Student::with(['educationUnit', 'kelas'])
            ->where('is_active', false);

        if ($request->filled('unit_id') && $request->query('unit_id') !== 'all') {
            $query->where('unit_id', $request->query('unit_id'));
        }

        $perPage = (int) $request->query('per_page', 15);
        $graduations = $query->paginate($perPage);

        return response()->json($graduations);
    }

    /**
     * Alumni list.
     */
    public function alumni(Request $request): JsonResponse
    {
        $query = Student::with(['educationUnit'])
            ->where(function ($q) {
                $q->where('is_active', false)
                  ->orWhere('metadata->is_alumni', true);
            });

        if ($request->filled('unit_id') && $request->query('unit_id') !== 'all') {
            $query->where('unit_id', $request->query('unit_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('full_name', 'like', "%{$search}%");
        }

        $perPage = (int) $request->query('per_page', 15);
        $alumni = $query->paginate($perPage);

        return response()->json($alumni);
    }

    /**
     * School Information & Announcements.
     */
    public function information(Request $request): JsonResponse
    {
        $query = PengumumanSekolah::where('status_aktif', true);

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('judul_pengumuman', 'like', "%{$search}%");
        }

        $perPage = (int) $request->query('per_page', 12);
        $info = $query->latest()->paginate($perPage);

        return response()->json($info);
    }

    /**
     * Reports aggregation preview data.
     */
    public function reports(Request $request): JsonResponse
    {
        $filters = [
            'unit_id' => $request->query('unit_id', 'all'),
            'academic_year_id' => $request->query('academic_year_id'),
            'period' => $request->query('period', 'year'),
        ];
        $overview = $this->service->getDashboardOverview($filters);
        $unitSummaries = $overview['unit_summaries'] ?? [];

        $reportsData = [
            'type' => $request->query('type', 'ringkasan'),
            'unit_id' => $filters['unit_id'],
            'generated_at' => now()->toIso8601String(),
            'total_records' => count($unitSummaries),
            'preview' => array_map(static fn (array $unit) => [
                'column_1' => $unit['name'] ?? '-',
                'column_2' => ($unit['pegawai_count'] ?? 0) . ' Pegawai',
                'column_3' => ($unit['guru_count'] ?? 0) . ' Guru',
                'column_4' => ($unit['siswa_aktif_count'] ?? 0) . ' Siswa',
            ], $unitSummaries),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $reportsData,
        ]);
    }

    /**
     * Parents / Orang Tua list with summary stats.
     */
    public function parents(Request $request): JsonResponse
    {
        $query = ParentModel::with(['students.educationUnit', 'studentsPivot.educationUnit']);

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $operator = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where('full_name', $operator, "%{$search}%")
                  ->orWhere('nik', $operator, "%{$search}%")
                  ->orWhere('phone', $operator, "%{$search}%")
                  ->orWhere('email', $operator, "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $parents = $query->paginate($perPage);
        $parents->through(function (ParentModel $parent) {
            $children = $parent->students
                ->concat($parent->studentsPivot)
                ->unique('id')
                ->values();
            $parent->setRelation('students', $children);
            $parent->unsetRelation('studentsPivot');

            return $parent;
        });

        $totalParents = ParentModel::count();
        $totalFather = ParentModel::whereNotNull('father_nik')->where('father_nik', '!=', '')->count();
        $totalMother = ParentModel::whereNotNull('mother_nik')->where('mother_nik', '!=', '')->count();
        $totalGuardian = ParentModel::whereNull('father_nik')->whereNull('mother_nik')->count();

        return response()->json([
            'status' => 'success',
            'data' => $parents,
            'summary' => [
                'total' => $totalParents,
                'father' => $totalFather,
                'mother' => $totalMother,
                'guardian' => $totalGuardian,
            ],
        ]);
    }

    /**
     * Kelas list with summary stats.
     */
    public function classes(Request $request): JsonResponse
    {
        $query = Kelas::with(['waliKelas', 'unitPendidikan', 'siswa', 'siswaLegacy']);

        if ($request->filled('unit_id') && $request->query('unit_id') !== 'all') {
            $query->where('unit_pendidikan_id', $request->query('unit_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('nama_kelas', 'like', "%{$search}%");
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $status = strtolower((string) $request->query('status'));
            $query->where(function ($kelas) use ($status) {
                $kelas->whereRaw('LOWER(status) = ?', [$status]);
                if ($status === 'aktif') {
                    $kelas->orWhereNull('status');
                }
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $classes = $query->paginate($perPage);
        $classes->through(fn (Kelas $kelas) => $this->serializeKelas($kelas));

        $totalKelas = Kelas::count();
        $totalAktif = Kelas::where(function ($q) {
            $q->whereRaw('LOWER(status) = ?', ['aktif'])->orWhereNull('status');
        })->count();
        $totalNonaktif = max(0, $totalKelas - $totalAktif);

        return response()->json([
            'status' => 'success',
            'data' => $classes,
            'summary' => [
                'total' => $totalKelas,
                'aktif' => $totalAktif,
                'nonaktif' => $totalNonaktif,
            ],
        ]);
    }

    /**
     * Rombel list with summary stats.
     */
    public function rombel(Request $request): JsonResponse
    {
        $query = Kelas::with(['waliKelas', 'unitPendidikan', 'siswa', 'siswaLegacy']);

        if ($request->filled('unit_id') && $request->query('unit_id') !== 'all') {
            $query->where('unit_pendidikan_id', $request->query('unit_id'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where('nama_kelas', 'like', "%{$search}%");
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $status = strtolower((string) $request->query('status'));
            $query->where(function ($kelas) use ($status) {
                $kelas->whereRaw('LOWER(status) = ?', [$status]);
                if ($status === 'aktif') {
                    $kelas->orWhereNull('status');
                }
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $rombel = $query->paginate($perPage);
        $rombel->through(fn (Kelas $kelas) => $this->serializeKelas($kelas));

        $totalRombel = Kelas::count();
        $totalAktif = Kelas::where(function ($q) {
            $q->whereRaw('LOWER(status) = ?', ['aktif'])->orWhereNull('status');
        })->count();
        $totalKapasitas = (int) Kelas::sum('kapasitas');
        $totalTerisi = (int) Student::where('is_active', true)
            ->where(fn ($student) => $student->whereNotNull('kelas_id')->orWhereNotNull('class_id'))
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => $rombel,
            'summary' => [
                'total' => $totalRombel,
                'aktif' => $totalAktif,
                'kapasitas' => $totalKapasitas > 0 ? $totalKapasitas : ($totalRombel * 30),
                'terisi' => $totalTerisi,
            ],
        ]);
    }

    /**
     * Employee detail.
     */
    public function employeeDetail(string $id): JsonResponse
    {
        $employee = Employee::with(['unit', 'position', 'division'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $employee]);
    }

    /**
     * Teacher detail.
     */
    public function teacherDetail(string $id): JsonResponse
    {
        $teacher = Teacher::with(['employee.unit', 'employee.position'])->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $this->serializeTeacher($teacher)]);
    }

    /**
     * Student detail.
     */
    public function studentDetail(string $id): JsonResponse
    {
        $student = Student::with(['educationUnit', 'kelas.waliKelas', 'parent'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $student]);
    }

    /**
     * Parent detail.
     */
    public function parentDetail(string $id): JsonResponse
    {
        $parent = ParentModel::with([
            'students.educationUnit',
            'students.kelas',
            'studentsPivot.educationUnit',
            'studentsPivot.kelas',
        ])->findOrFail($id);
        $children = $parent->students
            ->concat($parent->studentsPivot)
            ->unique('id')
            ->values();
        $parent->setRelation('students', $children);
        $parent->unsetRelation('studentsPivot');

        return response()->json(['status' => 'success', 'data' => $parent]);
    }

    /**
     * Alumni detail.
     */
    public function alumniDetail(string $id): JsonResponse
    {
        $alumni = Student::with(['educationUnit', 'kelas'])->findOrFail($id);
        return response()->json(['status' => 'success', 'data' => $alumni]);
    }

    /**
     * Class detail.
     */
    public function classDetail(string $id): JsonResponse
    {
        $class = Kelas::with(['waliKelas', 'unitPendidikan', 'siswa', 'siswaLegacy'])->findOrFail($id);
        $class = $this->serializeKelas($class);
        return response()->json(['status' => 'success', 'data' => $class]);
    }

    /**
     * Rombel detail.
     */
    public function rombelDetail(string $id): JsonResponse
    {
        $rombel = Kelas::with(['waliKelas', 'unitPendidikan', 'siswa', 'siswaLegacy'])->findOrFail($id);
        $rombel = $this->serializeKelas($rombel);
        return response()->json(['status' => 'success', 'data' => $rombel]);
    }

    private function serializeKelas(Kelas $kelas): Kelas
    {
        $students = $kelas->siswa
            ->concat($kelas->siswaLegacy)
            ->unique('id')
            ->values();
        $kelas->setRelation('students', $students);
        $kelas->unsetRelation('siswa');
        $kelas->unsetRelation('siswaLegacy');
        $kelas->setAttribute('students_count', $students->count());

        return $kelas;
    }

    /**
     * Get organizational structure per unit (or all units).
     */
    public function structure(Request $request): JsonResponse
    {
        $unitId = $request->query('unit_id', 'all');
        $data = $this->service->getUnitStructure($unitId);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Get organizational structure for a specific unit.
     */
    public function unitStructure(string $id): JsonResponse
    {
        $data = $this->service->getUnitStructure($id);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    private function serializeTeacher(Teacher $teacher): array
    {
        $employee = $teacher->employee;

        return [
            'id' => $teacher->id,
            'niy' => $employee?->niy ?? $teacher->employee_number,
            'nik' => $employee?->nik,
            'nama_lengkap' => $employee?->nama_lengkap ?? $teacher->full_name,
            'jenis_kelamin' => $employee?->jenis_kelamin,
            'unit' => $employee?->unit,
            'position' => $employee?->position,
            'jabatan' => $employee?->position?->name ?? 'Guru',
            'status_pegawai' => $employee?->status_pegawai,
            'status' => $employee?->status ?? 'Aktif',
            'no_hp' => $employee?->no_hp ?? $teacher->phone,
            'email' => $employee?->email ?? $teacher->email,
            'alamat' => $employee?->alamat,
            'tanggal_masuk' => $employee?->tanggal_masuk ?? $teacher->join_date,
        ];
    }
}
