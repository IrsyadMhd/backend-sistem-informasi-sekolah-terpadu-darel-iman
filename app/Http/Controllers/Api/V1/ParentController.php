<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ParentExport;
use App\Http\Controllers\Controller;
use App\Models\ParentModel;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ParentController extends Controller
{
    /**
     * Display a listing of parents with search, filter, and stats.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ParentModel::query()
            ->with(['students.educationUnit', 'students.kelas', 'studentsPivot.educationUnit', 'studentsPivot.kelas']);

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $operator) {
                $q->where('full_name', $operator, "%{$search}%")
                  ->orWhere('nik', $operator, "%{$search}%")
                  ->orWhere('phone', $operator, "%{$search}%")
                  ->orWhere('email', $operator, "%{$search}%")
                  ->orWhereHas('students', fn ($sq) => $sq->where('full_name', $operator, "%{$search}%"));
            });
        }

        $hubunganFilter = $request->query('hubungan');
        if ($hubunganFilter && $hubunganFilter !== 'Semua') {
            if ($hubunganFilter === 'Ayah') {
                $query->whereNotNull('father_nik')->where('father_nik', '!=', '');
            } elseif ($hubunganFilter === 'Ibu') {
                $query->whereNotNull('mother_nik')->where('mother_nik', '!=', '');
            } elseif ($hubunganFilter === 'Wali') {
                $query->where(function ($q) {
                    $q->whereNull('father_nik')->orWhere('father_nik', '');
                })->where(function ($q) {
                    $q->whereNull('mother_nik')->orWhere('mother_nik', '');
                });
            }
        }

        $perPage = (int) $request->query('per_page', 20);
        $paginated = $query->latest()->paginate($perPage);

        $items = collect($paginated->items())->map(function (ParentModel $parent) {
            $primaryStudent = $parent->students->first() ?? $parent->studentsPivot->first();
            $hubungan = 'Wali';
            if (! empty($parent->father_nik)) {
                $hubungan = 'Ayah';
            } elseif (! empty($parent->mother_nik)) {
                $hubungan = 'Ibu';
            }

            return [
                'id' => $parent->id,
                'nama' => $parent->full_name,
                'nik' => $parent->nik ?? $parent->father_nik ?? $parent->mother_nik ?? '-',
                'hubungan' => $hubungan,
                'student_id' => $primaryStudent?->id,
                'namaSiswa' => $primaryStudent?->full_name ?? '-',
                'kelasSiswa' => $primaryStudent?->kelas?->nama_kelas ?? $primaryStudent?->schoolClass?->name ?? '-',
                'unitPendidikan' => $primaryStudent?->educationUnit?->name ?? '-',
                'pekerjaan' => $parent->occupation ?? '-',
                'noHp' => $parent->phone ?? '-',
                'email' => $parent->email ?? '-',
                'alamat' => $parent->address ?? '-',
                'photo_url' => $parent->photo_url,
                'avatar_url' => $parent->avatar_url,
            ];
        });

        $totalParents = ParentModel::count();
        $totalFather = ParentModel::whereNotNull('father_nik')->where('father_nik', '!=', '')->count();
        $totalMother = ParentModel::whereNotNull('mother_nik')->where('mother_nik', '!=', '')->count();
        $totalGuardian = max(0, $totalParents - $totalFather - $totalMother);

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
            'summary' => [
                'total' => $totalParents,
                'ayah' => $totalFather,
                'ibu' => $totalMother,
                'wali' => $totalGuardian,
            ],
        ]);
    }

    /**
     * Display the specified parent.
     */
    public function show(string $id): JsonResponse
    {
        $parent = ParentModel::with(['students.educationUnit', 'students.kelas', 'studentsPivot.educationUnit', 'studentsPivot.kelas'])->findOrFail($id);
        $primaryStudent = $parent->students->first() ?? $parent->studentsPivot->first();
        $hubungan = 'Wali';
        if (! empty($parent->father_nik)) {
            $hubungan = 'Ayah';
        } elseif (! empty($parent->mother_nik)) {
            $hubungan = 'Ibu';
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $parent->id,
                'nama' => $parent->full_name,
                'nik' => $parent->nik ?? '-',
                'hubungan' => $hubungan,
                'student_id' => $primaryStudent?->id,
                'namaSiswa' => $primaryStudent?->full_name ?? '-',
                'kelasSiswa' => $primaryStudent?->kelas?->nama_kelas ?? '-',
                'unitPendidikan' => $primaryStudent?->educationUnit?->name ?? '-',
                'pekerjaan' => $parent->occupation ?? '-',
                'noHp' => $parent->phone ?? '-',
                'email' => $parent->email ?? '-',
                'alamat' => $parent->address ?? '-',
                'photo_url' => $parent->photo_url,
                'avatar_url' => $parent->avatar_url,
            ],
        ]);
    }

    /**
     * Store a newly created parent in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'hubungan' => 'required|string|in:Ayah,Ibu,Wali',
            'nik' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'pekerjaan' => 'nullable|string|max:255',
            'alamat' => 'nullable|string',
            'student_id' => 'nullable|uuid|exists:students,id',
        ]);

        $parentData = [
            'full_name' => $validated['nama'],
            'nik' => $validated['nik'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'occupation' => $validated['pekerjaan'] ?? null,
            'address' => $validated['alamat'] ?? null,
        ];

        if ($validated['hubungan'] === 'Ayah') {
            $parentData['father_nik'] = $validated['nik'] ?? 'NIK-AYAH-' . time();
        } elseif ($validated['hubungan'] === 'Ibu') {
            $parentData['mother_nik'] = $validated['nik'] ?? 'NIK-IBU-' . time();
        }

        $parent = ParentModel::create($parentData);

        if (! empty($validated['student_id'])) {
            $student = Student::find($validated['student_id']);
            if ($student) {
                $student->update(['parent_id' => $parent->id]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data orang tua / wali berhasil disimpan.',
            'data' => $parent,
        ], 201);
    }

    /**
     * Update the specified parent in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $parent = ParentModel::findOrFail($id);

        $validated = $request->validate([
            'nama' => 'sometimes|required|string|max:255',
            'hubungan' => 'nullable|string|in:Ayah,Ibu,Wali',
            'nik' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'pekerjaan' => 'nullable|string|max:255',
            'alamat' => 'nullable|string',
            'student_id' => 'nullable|uuid|exists:students,id',
        ]);

        $updateData = [];
        if (isset($validated['nama'])) {
            $updateData['full_name'] = $validated['nama'];
        }
        if (isset($validated['nik'])) {
            $updateData['nik'] = $validated['nik'];
        }
        if (isset($validated['phone'])) {
            $updateData['phone'] = $validated['phone'];
        }
        if (isset($validated['email'])) {
            $updateData['email'] = $validated['email'];
        }
        if (isset($validated['pekerjaan'])) {
            $updateData['occupation'] = $validated['pekerjaan'];
        }
        if (isset($validated['alamat'])) {
            $updateData['address'] = $validated['alamat'];
        }

        if (isset($validated['hubungan'])) {
            if ($validated['hubungan'] === 'Ayah') {
                $updateData['father_nik'] = $validated['nik'] ?? $parent->father_nik ?? 'NIK-AYAH-' . time();
            } elseif ($validated['hubungan'] === 'Ibu') {
                $updateData['mother_nik'] = $validated['nik'] ?? $parent->mother_nik ?? 'NIK-IBU-' . time();
            }
        }

        $parent->update($updateData);

        if (! empty($validated['student_id'])) {
            $student = Student::find($validated['student_id']);
            if ($student) {
                $student->update(['parent_id' => $parent->id]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data orang tua / wali berhasil diperbarui.',
            'data' => $parent,
        ]);
    }

    /**
     * Remove the specified parent from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $parent = ParentModel::findOrFail($id);
        $parent->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Data orang tua / wali berhasil dihapus.',
        ]);
    }

    /**
     * Export parents data to XLSX, XLS, CSV, or JSON.
     */
    public function export(Request $request)
    {
        $query = ParentModel::query()
            ->with(['students.educationUnit', 'students.kelas', 'studentsPivot.educationUnit', 'studentsPivot.kelas']);

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $operator) {
                $q->where('full_name', $operator, "%{$search}%")
                  ->orWhere('nik', $operator, "%{$search}%")
                  ->orWhere('phone', $operator, "%{$search}%")
                  ->orWhere('email', $operator, "%{$search}%");
            });
        }

        $hubunganFilter = $request->query('hubungan');
        if ($hubunganFilter && $hubunganFilter !== 'Semua') {
            if ($hubunganFilter === 'Ayah') {
                $query->whereNotNull('father_nik')->where('father_nik', '!=', '');
            } elseif ($hubunganFilter === 'Ibu') {
                $query->whereNotNull('mother_nik')->where('mother_nik', '!=', '');
            } elseif ($hubunganFilter === 'Wali') {
                $query->where(function ($q) {
                    $q->whereNull('father_nik')->orWhere('father_nik', '');
                })->where(function ($q) {
                    $q->whereNull('mother_nik')->orWhere('mother_nik', '');
                });
            }
        }

        $format = strtolower($request->query('format', 'json'));
        if (in_array($format, ['xlsx', 'xls', 'csv'])) {
            @ini_set('memory_limit', '512M');
            @set_time_limit(180);
            $excelFormat = match ($format) {
                'xlsx' => \Maatwebsite\Excel\Excel::XLSX,
                'xls' => \Maatwebsite\Excel\Excel::XLS,
                'csv' => \Maatwebsite\Excel\Excel::CSV,
            };
            $filename = 'data_orang_tua_' . date('Ymd_His') . '.' . $format;
            return Excel::download(new ParentExport($query->orderBy('full_name', 'asc')), $filename, $excelFormat);
        }

        $parents = $query->orderBy('full_name', 'asc')->get();

        $rows = $parents->map(function (ParentModel $parent) {
            $primaryStudent = $parent->students->first() ?? $parent->studentsPivot->first();
            $hubungan = 'Wali';
            if (! empty($parent->father_nik)) {
                $hubungan = 'Ayah';
            } elseif (! empty($parent->mother_nik)) {
                $hubungan = 'Ibu';
            }

            return [
                'nama' => $parent->full_name,
                'nik' => $parent->nik ?? '-',
                'hubungan' => $hubungan,
                'nama_siswa' => $primaryStudent?->full_name ?? '-',
                'kelas_siswa' => $primaryStudent?->kelas?->nama_kelas ?? '-',
                'unit_pendidikan' => $primaryStudent?->educationUnit?->name ?? '-',
                'phone' => $parent->phone ?? '-',
                'email' => $parent->email ?? '-',
                'pekerjaan' => $parent->occupation ?? '-',
                'alamat' => $parent->address ?? '-',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $rows,
        ]);
    }

    /**
     * Import parents data from JSON array.
     */
    public function import(Request $request): JsonResponse
    {
        $rows = $request->input('data', []);
        if (! is_array($rows) || empty($rows)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload data impor tidak boleh kosong.',
            ], 422);
        }

        $berhasil = 0;
        $gagal = 0;

        foreach ($rows as $row) {
            $nama = trim($row['nama'] ?? $row['full_name'] ?? '');
            if (empty($nama)) {
                $gagal++;
                continue;
            }

            $parentData = [
                'full_name' => $nama,
                'nik' => $row['nik'] ?? null,
                'phone' => $row['phone'] ?? $row['no_hp'] ?? null,
                'email' => $row['email'] ?? null,
                'occupation' => $row['pekerjaan'] ?? null,
                'address' => $row['alamat'] ?? null,
            ];

            $hubungan = $row['hubungan'] ?? 'Wali';
            if ($hubungan === 'Ayah') {
                $parentData['father_nik'] = $row['nik'] ?? 'NIK-A-' . time();
            } elseif ($hubungan === 'Ibu') {
                $parentData['mother_nik'] = $row['nik'] ?? 'NIK-I-' . time();
            }

            ParentModel::create($parentData);
            $berhasil++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Proses impor selesai. Berhasil: {$berhasil}, Gagal: {$gagal}.",
            'data' => [
                'berhasil' => $berhasil,
                'gagal' => $gagal,
            ],
        ]);
    }
}
