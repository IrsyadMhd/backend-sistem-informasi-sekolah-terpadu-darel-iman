<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexRequest;
use App\Http\Requests\V1\StoreStudentRequest;
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
        $students = Student::query()
            ->orderBy('full_name')
            ->limit(8)
            ->get([
                'id',
                'nis',
                'full_name',
                'class_id',
                'gender',
                'birth_place',
                'birth_date',
                'address',
                'is_active',
                'metadata',
            ]);

        $classes = SchoolClass::query()
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'level', 'metadata']);

        $totalSiswa = Student::query()->count();
        $totalKelas = SchoolClass::query()->count();
        $siswaBaru = Student::query()->whereDate('created_at', '>=', now()->startOfYear())->count();
        $mutasiKeluar = Student::query()->where('is_active', false)->count();
        $alumni = Student::query()
            ->where('is_active', false)
            ->where(function ($query) {
                $query->where('metadata->status', 'alumni')
                    ->orWhere('metadata->status', 'lulus');
            })
            ->count();

        $selected = $students->first();

        $daftarSiswa = $students->map(function (Student $student) {
            return [
                'id' => $student->id,
                'nis' => $student->nis,
                'nama' => $student->full_name,
                'kelas' => $student->metadata['kelas_label'] ?? '-',
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
            'statistik' => [
                'total_siswa' => $totalSiswa,
                'total_kelas' => $totalKelas,
                'siswa_baru' => $siswaBaru,
                'mutasi_keluar' => $mutasiKeluar,
                'alumni' => $alumni,
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
