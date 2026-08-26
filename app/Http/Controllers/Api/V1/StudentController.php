<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexRequest;
use App\Http\Requests\V1\StoreStudentRequest;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Student;
use App\Models\User;
use App\Repositories\Contracts\StudentRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class StudentController extends Controller
{
    public function __construct(private readonly StudentRepositoryInterface $studentRepository) {}

    public function index(IndexRequest $request): JsonResponse
    {
        [$canAccessAllUnits, $unitId] = $this->scopeForUser($request->user());

        $requestedUnitId = $request->validated('unit_id')
            ?? $request->query('unit_id')
            ?? $request->query('unit_pendidikan_id');

        $effectiveUnitId = $requestedUnitId ?: $unitId;

        $data = $this->studentRepository->paginate(
            search: (string) $request->validated('search', ''),
            perPage: (int) $request->validated('per_page', 15),
            unitId: $effectiveUnitId,
            canAccessAllUnits: $canAccessAllUnits && empty($requestedUnitId)
        );

        return response()->json($data);
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        $payload = $this->mappedPayload($request->validated());
        $payload['unit_id'] = $this->authorizedUnitId($request->user(), $payload['unit_id']);
        $payload['kelas_id'] = $this->authorizedKelasId($payload['kelas_id'], $payload['unit_id']);
        $student = Student::query()->create($payload);

        return response()->json([
            'message' => 'Data siswa berhasil disimpan.',
            'data' => $student,
        ], 201);
    }

    public function show(Request $request, string $student): JsonResponse
    {
        return response()->json($this->scopedStudentQuery($request->user())->findOrFail($student));
    }

    public function update(StoreStudentRequest $request, string $student): JsonResponse
    {
        $model = $this->scopedStudentQuery($request->user())->findOrFail($student);
        $validated = $request->validated();
        $payload = $this->mappedPayload($validated);

        if (array_key_exists('unit_id', $validated)) {
            $payload['unit_id'] = $this->authorizedUnitId($request->user(), $payload['unit_id']);
        } else {
            unset($payload['unit_id']);
        }

        if (array_key_exists('kelas_id', $validated)) {
            $payload['kelas_id'] = $this->authorizedKelasId($payload['kelas_id'], $payload['unit_id'] ?? $model->unit_id);
        } else {
            unset($payload['kelas_id']);
        }

        $model->update($payload);

        return response()->json([
            'message' => 'Data siswa berhasil diperbarui.',
            'data' => $model->fresh(),
        ]);
    }

    public function destroy(Request $request, string $student): JsonResponse
    {
        $this->scopedStudentQuery($request->user())->findOrFail($student)->delete();

        return response()->json([
            'message' => 'Data siswa berhasil dihapus.',
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        [$bolehSemuaUnit, $unitPengguna, $employee] = $this->scopeForUser($request->user());
        $requestedUnitId = $request->query('unit_id') ?? $request->query('unit_pendidikan_id');

        $studentQuery = Student::query()
            ->with([
                'educationUnit:id,name,level',
                'kelas:id,nama_kelas,tingkat',
            ]);

        if (! empty($requestedUnitId)) {
            $studentQuery->where('unit_id', $requestedUnitId);
        } else {
            $this->applyUnitScope($studentQuery, $bolehSemuaUnit, $unitPengguna);
        }

        $students = $studentQuery
            ->orderBy('full_name')
            ->get([
                'id',
                'nis',
                'full_name',
                'kelas_id',
                'unit_id',
                'gender',
                'birth_place',
                'birth_date',
                'address',
                'is_active',
                'metadata',
                'created_at',
            ]);

        $classIds = $students->pluck('kelas_id')->filter()->unique()->values();
        $classes = Kelas::query()
            ->whereIn('id', $classIds)
            ->orderBy('nama_kelas')
            ->get(['id', 'nama_kelas', 'tingkat', 'wali_kelas_id', 'kapasitas']);

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
                'jenjang' => $student->educationUnit?->level ?? $student->kelas?->tingkat ?? '-',
                'kelas' => $student->kelas?->nama_kelas ?? '-',
                'jenis_kelamin' => $student->gender,
                'aktif' => (bool) $student->is_active,
            ];
        })->values();

        $daftarKelas = $classes->map(function (Kelas $class) use ($students) {
            return [
                'id' => $class->id,
            'nama' => $class->nama_kelas,
            'level' => $class->tingkat,
            'wali_kelas_id' => $class->wali_kelas_id,
            'kapasitas' => (int) $class->kapasitas,
            'jumlah_siswa' => $students->where('kelas_id', $class->id)->count(),
            ];
        })->values();

        $tahunSekarang = (int) now()->format('Y');
        $grafik = collect(range($tahunSekarang - 3, $tahunSekarang))
            ->map(fn (int $tahun) => [
                'tahun' => (string) $tahun,
                'jumlah' => $students->filter(
                    fn (Student $student) => $student->created_at?->year === $tahun
                )->count(),
            ])
            ->values();
        $mutasiMasuk = $students->filter(
            fn (Student $student) => data_get($student->metadata, 'mutasi_type') === 'masuk'
        )->count();

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
                'kelas' => $selected->kelas?->nama_kelas ?? '-',
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
                'mutasi_masuk' => $mutasiMasuk,
                'mutasi_keluar' => $mutasiKeluar,
                'siswa_lulus' => $alumni,
                'grafik_tahunan' => $grafik,
            ],
        ]);
    }

    private function mappedPayload(array $validated): array
    {
        return [
            'parent_id' => $validated['parent_id'] ?? null,
            'unit_id' => $validated['unit_id'] ?? null,
            'kelas_id' => $validated['kelas_id'] ?? null,
            'nis' => $validated['nis'],
            'nisn' => $validated['nisn'] ?? Arr::get($validated, 'metadata.nisn'),
            'full_name' => $validated['full_name'],
            'gender' => $validated['gender'],
            'birth_date' => $validated['birth_date'] ?? null,
            'birth_place' => $validated['birth_place'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => Arr::get($validated, 'is_active', true),
            'metadata' => $validated['metadata'] ?? [],
        ];
    }

    private function scopedStudentQuery(User $user)
    {
        [$canAccessAllUnits, $unitId] = $this->scopeForUser($user);
        $query = Student::query();

        $this->applyUnitScope($query, $canAccessAllUnits, $unitId);

        return $query;
    }

    private function applyUnitScope($query, bool $canAccessAllUnits, ?string $unitId): void
    {
        if ($canAccessAllUnits) {
            return;
        }

        $query->when(
            $unitId,
            fn ($studentQuery) => $studentQuery->where('unit_id', $unitId),
            fn ($studentQuery) => $studentQuery->whereRaw('1 = 0')
        );
    }

    private function authorizedUnitId(User $user, ?string $requestedUnitId): ?string
    {
        [$canAccessAllUnits, $unitId] = $this->scopeForUser($user);

        if ($canAccessAllUnits) {
            return $requestedUnitId;
        }

        abort_unless($unitId, 403, 'Akun tidak memiliki cakupan unit pendidikan.');
        abort_if($requestedUnitId && $requestedUnitId !== $unitId, 403, 'Unit pendidikan tidak sesuai dengan cakupan akun.');

        return $unitId;
    }

    private function authorizedKelasId(?string $kelasId, ?string $unitId): ?string
    {
        if (! $kelasId) {
            return null;
        }

        abort_unless($unitId, 422, 'Unit pendidikan wajib ditetapkan sebelum memilih kelas.');
        abort_unless(
            Kelas::query()->whereKey($kelasId)->where('unit_pendidikan_id', $unitId)->exists(),
            403,
            'Kelas tidak sesuai dengan unit pendidikan siswa.'
        );

        return $kelasId;
    }

    private function scopeForUser(User $user): array
    {
        $employee = Employee::query()
            ->with([
                'position:id,name,scope_akses',
                'unit:id,name',
            ])
            ->where('user_id', $user->id)
            ->first();
        $canAccessAllUnits = $user->hasAnyRole([
            'Super Admin',
            'Yayasan',
            'Ketua Yayasan',
            'ketua_yayasan',
            'sekretaris_yayasan',
            'bendahara_yayasan',
            'pengurus_yayasan',
        ])
            || $user->can('foundation.student.view')
            || $employee?->position?->scope_akses === 'semua_unit'
            || str_contains(strtolower((string) $employee?->position?->name), 'yayasan');
        $unitId = $employee?->unit_id
            ?? data_get($user->metadata, 'unit_id')
            ?? data_get($user->metadata, 'unit_pendidikan_id');

        return [$canAccessAllUnits, $unitId, $employee];
    }

    public function export(Request $request): JsonResponse
    {
        [$canAccessAllUnits, $unitId] = $this->scopeForUser($request->user());
        $requestedUnitId = $request->query('unit_id') ?? $request->query('unit_pendidikan_id');
        $effectiveUnitId = $requestedUnitId ?: $unitId;

        $query = Student::query()
            ->with(['educationUnit', 'schoolClass'])
            ->when(! $canAccessAllUnits && $effectiveUnitId, fn ($q) => $q->where('unit_id', $effectiveUnitId))
            ->when($requestedUnitId, fn ($q) => $q->where('unit_id', $requestedUnitId));

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('nis', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->query('kelas_id'));
        }

        $students = $query->orderBy('full_name', 'asc')->get();

        $rows = $students->map(function ($std, $idx) {
            return [
                'no' => $idx + 1,
                'nis' => $std->nis ?? '-',
                'nisn' => $std->nisn ?? '-',
                'nama_lengkap' => $std->full_name,
                'jenis_kelamin' => $std->gender === 'female' ? 'Perempuan' : 'Laki-Laki',
                'unit_pendidikan' => $std->educationUnit?->name ?? '-',
                'kelas' => $std->schoolClass?->nama_kelas ?? '-',
                'status' => $std->is_active ? 'Aktif' : 'Nonaktif',
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Data siswa berhasil diexport.',
            'data' => $rows,
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $rows = $request->input('data', []);
        if (! is_array($rows) || empty($rows)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload data impor siswa tidak boleh kosong.',
            ], 422);
        }

        $berhasil = 0;
        $gagal = 0;
        $duplikat = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            $nama = trim($row['full_name'] ?? $row['nama_lengkap'] ?? $row['nama'] ?? '');
            $nis = trim($row['nis'] ?? '');
            $nisn = trim($row['nisn'] ?? '');

            if (empty($nama) || empty($nis)) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: Nama lengkap dan NIS siswa wajib diisi.";
                continue;
            }

            $nama = preg_replace('/\s+/', ' ', $nama);
            $nis = preg_replace('/\s+/', ' ', $nis);

            if (Student::query()->where('nis', $nis)->exists()) {
                $duplikat++;
                $errors[] = "Baris {$rowNum}: NIS '{$nis}' sudah terdaftar.";
                continue;
            }

            try {
                Student::query()->create([
                    'nis' => $nis,
                    'nisn' => $nisn ?: null,
                    'full_name' => $nama,
                    'gender' => in_array(strtolower($row['gender'] ?? $row['jenis_kelamin'] ?? ''), ['female', 'p', 'perempuan']) ? 'female' : 'male',
                    'unit_id' => $row['unit_id'] ?? null,
                    'kelas_id' => $row['kelas_id'] ?? null,
                    'is_active' => true,
                ]);
                $berhasil++;
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: ".$e->getMessage();
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => "Proses impor selesai. Berhasil: {$berhasil}, Duplikat/Skip: {$duplikat}, Gagal: {$gagal}.",
            'data' => [
                'total' => count($rows),
                'berhasil' => $berhasil,
                'duplikat' => $duplikat,
                'gagal' => $gagal,
                'errors' => $errors,
            ],
        ]);
    }

    public function template(): JsonResponse
    {
        return response()->json([
            'headers' => ['nis', 'nisn', 'full_name', 'gender', 'unit_id', 'kelas_id'],
            'sample' => [
                'nis' => '20261001',
                'nisn' => '0012345678',
                'full_name' => 'Muhammad Abdullah',
                'gender' => 'male',
                'unit_id' => '',
                'kelas_id' => '',
            ],
        ]);
    }
}
