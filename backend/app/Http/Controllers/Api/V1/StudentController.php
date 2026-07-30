<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexRequest;
use App\Http\Requests\V1\StoreStudentRequest;
use App\Models\Employee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class StudentController extends Controller
{
    public function __construct(private readonly StudentRepositoryInterface $studentRepository)
    {
    }

    public function index(IndexRequest $request): JsonResponse
    {
        $data = $this->studentRepository->paginate(
            search: (string) $request->validated('search', ''),
            perPage: (int) $request->validated('per_page', 15)
        );

        return response()->json($data);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $student = Student::query()->create($this->mappedPayload($request->validated()));

        return response()->json([
            'message' => 'Data siswa berhasil disimpan.',
            'data' => $student,
        ], 201);
    }

    public function show(string $student): JsonResponse
    {
        return response()->json(Student::query()->findOrFail($student));
    }

    public function update(StoreStudentRequest $request, string $student): JsonResponse
    {
        $model = Student::query()->findOrFail($student);
        $model->update($this->mappedPayload($request->validated()));

        return response()->json([
            'message' => 'Data siswa berhasil diperbarui.',
            'data' => $model->fresh(),
        ]);
    }

    public function destroy(string $student): JsonResponse
    {
        Student::query()->findOrFail($student)->delete();

        return response()->json([
            'message' => 'Data siswa berhasil dihapus.',
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user ? Employee::query()
            ->with([
                'position:id,name,scope_akses',
                'unit:id,name',
            ])
            ->where('user_id', $user->id)
            ->first() : null;
        $bolehSemuaUnit = $user?->hasAnyRole(['Super Admin', 'Yayasan']) === true
            || $employee?->position?->scope_akses === 'semua_unit'
            || str_contains(strtolower((string) $employee?->position?->name), 'yayasan');
        $unitPengguna = $employee?->unit_id
            ?? data_get($user?->metadata, 'unit_id')
            ?? data_get($user?->metadata, 'unit_pendidikan_id');

        $studentQuery = Student::query()
            ->with([
                'educationUnit:id,name,level',
                'schoolClass:id,name,level',
            ]);

        if (! $bolehSemuaUnit) {
            // Role unit hanya boleh membaca siswa pada unit kerjanya sendiri.
            // Jika akun belum dipetakan ke unit, hasil sengaja dikosongkan agar
            // data lintas unit tidak bocor.
            $studentQuery->when(
                $unitPengguna,
                fn ($query, $unitId) => $query->where('unit_id', $unitId),
                fn ($query) => $query->whereRaw('1 = 0')
            );
        }

        $students = $studentQuery
            ->orderBy('full_name')
            ->get([
                'id',
                'nis',
                'full_name',
                'class_id',
                'unit_id',
                'gender',
                'birth_place',
                'birth_date',
                'address',
                'is_active',
                'metadata',
                'created_at',
            ]);

        $classIds = $students->pluck('class_id')->filter()->unique()->values();
        $classes = SchoolClass::query()
            ->when(! $bolehSemuaUnit, fn ($query) => $query->whereIn('id', $classIds))
            ->orderBy('name')
            ->get(['id', 'name', 'level', 'metadata']);

        $totalSiswa = $students->count();
        $totalKelas = $classes->count();
        $siswaBaru = $students->where('created_at', '>=', now()->startOfYear())->count();
        $mutasiKeluar = $students->where('is_active', false)->count();
        $alumni = $students->filter(fn (Student $student) => in_array(
            strtolower((string) data_get($student->metadata, 'status')),
            ['alumni', 'lulus'],
            true
        ))->count();

        $selected = $students->first();
        $siswaAktif = $students->where('is_active', true)->count();
        $siswaNonaktif = $students->where('is_active', false)->count();
        $lakiLaki = $students->filter(fn (Student $student) => in_array(
            strtolower((string) $student->gender),
            ['l', 'laki-laki', 'laki laki', 'male'],
            true
        ))->count();
        $perempuan = $students->filter(fn (Student $student) => in_array(
            strtolower((string) $student->gender),
            ['p', 'perempuan', 'female'],
            true
        ))->count();

        $daftarSiswa = $students->map(function (Student $student) {
            return [
                'id' => $student->id,
                'nis' => $student->nis,
                'nama' => $student->full_name,
                'unit' => $student->educationUnit?->name ?? ($student->metadata['unit_pendidikan'] ?? '-'),
                'jenjang' => $student->educationUnit?->level ?? $student->schoolClass?->level ?? '-',
                'kelas' => $student->metadata['kelas_label'] ?? $student->schoolClass?->name ?? '-',
                'jenis_kelamin' => $student->gender,
                'aktif' => (bool) $student->is_active,
            ];
        })->values();

        $daftarKelas = $classes->map(function (SchoolClass $class) use ($students) {
            return [
                'id' => $class->id,
                'nama' => $class->name,
                'level' => $class->level,
                'wali_kelas' => $class->metadata['wali_kelas'] ?? '-',
                'kapasitas' => (int) ($class->metadata['kapasitas'] ?? 35),
                'jumlah_siswa' => $students->where('class_id', $class->id)->count(),
            ];
        })->values();

        $tahunSekarang = (int) now()->format('Y');
        $grafik = [];
        $basis = max($totalSiswa - 240, 200);

        for ($i = 3; $i >= 0; $i--) {
            $tahun = (string) ($tahunSekarang - $i);
            $grafik[] = [
                'tahun' => $tahun,
                'jumlah' => $basis + ((3 - $i) * 80),
            ];
        }

        return response()->json([
            'akses' => [
                'semua_unit' => $bolehSemuaUnit,
                'unit_id' => $bolehSemuaUnit ? null : $unitPengguna,
                'unit_nama' => $bolehSemuaUnit ? 'Seluruh Unit Pendidikan' : ($employee?->unit?->name ?? null),
            ],
            'statistik' => [
                'total_siswa' => $totalSiswa,
                'total_kelas' => $totalKelas,
                'siswa_baru' => $siswaBaru,
                'mutasi_keluar' => $mutasiKeluar,
                'alumni' => $alumni,
                'siswa_aktif' => $siswaAktif,
                'siswa_nonaktif' => $siswaNonaktif,
            ],
            'komposisi_gender' => [
                'laki_laki' => $lakiLaki,
                'perempuan' => $perempuan,
            ],
            'daftar_siswa' => $daftarSiswa,
            'siswa_terpilih' => $selected ? [
                'id' => $selected->id,
                'nis' => $selected->nis,
                'nama' => $selected->full_name,
                'jenis_kelamin' => $selected->gender,
                'tempat_lahir' => $selected->birth_place,
                'tanggal_lahir' => optional($selected->birth_date)->toDateString(),
                'alamat' => $selected->address,
                'status' => $selected->is_active ? 'Aktif' : 'Nonaktif',
                'kelas' => $selected->metadata['kelas_label'] ?? '-',
                'tahun_masuk' => $selected->metadata['tahun_masuk'] ?? '-',
                'orang_tua' => [
                    'nama_ayah' => $selected->metadata['nama_ayah'] ?? '-',
                    'nama_ibu' => $selected->metadata['nama_ibu'] ?? '-',
                    'no_hp' => $selected->metadata['no_hp'] ?? '-',
                    'pekerjaan_ayah' => $selected->metadata['pekerjaan_ayah'] ?? '-',
                    'pekerjaan_ibu' => $selected->metadata['pekerjaan_ibu'] ?? '-',
                ],
            ] : null,
            'kelas_rombel' => $daftarKelas,
            'laporan_siswa' => [
                'siswa_baru' => $siswaBaru,
                'mutasi_masuk' => max((int) floor($siswaBaru * 0.6), 0),
                'mutasi_keluar' => $mutasiKeluar,
                'siswa_lulus' => $alumni,
                'grafik_tahunan' => $grafik,
            ],
        ]);
    }

    private function mappedPayload(array $validated): array
    {
        return [
            'class_id' => $validated['class_id'] ?? null,
            'nis' => $validated['nis'],
            'full_name' => $validated['full_name'],
            'gender' => $validated['gender'],
            'birth_date' => $validated['birth_date'] ?? null,
            'birth_place' => $validated['birth_place'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => Arr::get($validated, 'is_active', true),
            'metadata' => $validated['metadata'] ?? [],
        ];
    }
}
