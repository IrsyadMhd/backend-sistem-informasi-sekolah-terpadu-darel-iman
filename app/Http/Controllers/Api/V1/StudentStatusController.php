<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StudentStatus::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('order')->orderBy('name');

        if ($request->boolean('all', false) || $request->input('per_page') === 'all') {
            return response()->json([
                'success' => true,
                'data' => $query->get(),
            ]);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $statuses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $statuses->items(),
            'meta' => [
                'current_page' => $statuses->currentPage(),
                'last_page' => $statuses->lastPage(),
                'per_page' => $statuses->perPage(),
                'total' => $statuses->total(),
            ],
        ]);
    }

    /**
     * Get dropdown options for selection fields.
     */
    public function dropdown(): JsonResponse
    {
        $options = StudentStatus::active()
            ->get(['id', 'code', 'name', 'badge_color', 'order'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'value' => $item->code,
                'code' => $item->code,
                'label' => $item->name,
                'name' => $item->name,
                'badge_color' => $item->badge_color,
                'order' => $item->order,
            ]);

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    private const SYSTEM_STATUS_CODES = [
        'aktif',
        'lulus',
        'mutasi',
        'berhenti',
        'cuti',
        'nonaktif',
    ];

    private function assertCanManageStatus(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        if ($user->hasRole('Super Admin') || $user->hasRole('super_admin') || $user->hasRole('Superadmin') || $user->hasRole('Admin')) {
            return;
        }

        if ($user->can('sistem.master_data') || $user->can('student.manage') || $user->can('operator.manage')) {
            return;
        }

        abort(403, 'Akses ditolak. Anda tidak memiliki wewenang untuk mengelola master status siswa.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $this->assertCanManageStatus($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:student_statuses,code'],
            'name' => ['required', 'string', 'max:100'],
            'badge_color' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $status = StudentStatus::create([
            'code' => strtolower(trim($validated['code'])),
            'name' => trim($validated['name']),
            'badge_color' => $validated['badge_color'] ?? 'emerald',
            'order' => $validated['order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status siswa berhasil ditambahkan.',
            'data' => $status,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $status = StudentStatus::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->assertCanManageStatus($request);
        $status = StudentStatus::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('student_statuses', 'code')->ignore($status->id)],
            'name' => ['required', 'string', 'max:100'],
            'badge_color' => ['nullable', 'string', 'max:50'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $targetCode = strtolower(trim($validated['code']));

        // Proteksi kode status sistem agar integritas referensi operasional tetap terjaga
        if (in_array($status->code, self::SYSTEM_STATUS_CODES, true) && $targetCode !== $status->code) {
            return response()->json([
                'success' => false,
                'message' => "Kode status sistem '{$status->code}' dilindungi dan tidak dapat diubah.",
            ], 422);
        }

        $status->update([
            'code' => $targetCode,
            'name' => trim($validated['name']),
            'badge_color' => $validated['badge_color'] ?? $status->badge_color,
            'order' => $validated['order'] ?? $status->order,
            'is_active' => $validated['is_active'] ?? $status->is_active,
            'description' => $validated['description'] ?? $status->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status siswa berhasil diperbarui.',
            'data' => $status,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->assertCanManageStatus($request);
        $status = StudentStatus::findOrFail($id);

        // 1. Cegah penghapusan status sistem inti
        if (in_array($status->code, self::SYSTEM_STATUS_CODES, true)) {
            return response()->json([
                'success' => false,
                'message' => "Status sistem resmi '{$status->name}' dilindungi dan tidak dapat dihapus. Silakan nonaktifkan status melalui saklar aktif.",
            ], 422);
        }

        // 2. Cegah penghapusan jika status masih direferensikan pada data siswa aktif
        $studentCount = \Illuminate\Support\Facades\DB::table('students')
            ->whereNull('deleted_at')
            ->where(function ($q) use ($status) {
                $q->where('metadata->status_siswa', $status->code)
                    ->orWhere('metadata->status_siswa', $status->name);
            })
            ->count();

        if ($studentCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Status '{$status->name}' masih digunakan oleh {$studentCount} data siswa aktif dan tidak dapat dihapus. Silakan nonaktifkan status bila tidak lagi berlaku.",
            ], 422);
        }

        // 3. Cegah penghapusan jika tercatat pada riwayat mutasi siswa
        if (\Illuminate\Support\Facades\Schema::hasTable('student_mutations')) {
            $mutationCount = \Illuminate\Support\Facades\DB::table('student_mutations')
                ->where(function ($q) use ($status) {
                    $q->where('to_status', $status->code)
                        ->orWhere('from_status', $status->code);
                })
                ->count();

            if ($mutationCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Status '{$status->name}' masih direferensikan dalam {$mutationCount} riwayat mutasi siswa.",
                ], 422);
            }
        }

        $status->delete();

        return response()->json([
            'success' => true,
            'message' => 'Status siswa berhasil dihapus.',
        ]);
    }

    /**
     * Impor batch data status siswa dari berkas Excel/CSV atau payload JSON.
     */
    public function import(Request $request): JsonResponse
    {
        $this->assertCanManageStatus($request);

        $rows = [];
        $overwrite = $request->boolean('overwrite', false);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $sheetData = $sheet->toArray(null, true, true, true);

                if (! empty($sheetData)) {
                    $headerRow = array_shift($sheetData);
                    $headers = [];
                    foreach ($headerRow as $colKey => $colName) {
                        $colName = trim((string) $colName);
                        if (! empty($colName)) {
                            $headers[$colKey] = $colName;
                        }
                    }

                    foreach ($sheetData as $row) {
                        $rowData = [];
                        $hasVal = false;
                        foreach ($headers as $colKey => $colName) {
                            $val = isset($row[$colKey]) ? trim((string) $row[$colKey]) : '';
                            if ($val !== '') {
                                $hasVal = true;
                            }
                            $rowData[$colName] = $val;
                        }
                        if ($hasVal) {
                            $rows[] = $rowData;
                        }
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Gagal membaca berkas Excel/CSV: '.$e->getMessage(),
                ], 422);
            }
        } else {
            $inputData = $request->input('data', $request->input('rows', []));
            if (is_string($inputData)) {
                $decoded = json_decode($inputData, true);
                if (is_array($decoded)) {
                    $rows = $decoded;
                }
            } elseif (is_array($inputData)) {
                $rows = $inputData;
            }
        }

        if (empty($rows)) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Payload data impor tidak boleh kosong.',
            ], 422);
        }

        $berhasil = 0;
        $diperbarui = 0;
        $duplikat = 0;
        $gagal = 0;
        $errors = [];
        $duplicates = [];

        foreach ($rows as $index => $row) {
            $rowNum = $index + 1;
            $normalized = [];
            foreach ($row as $k => $v) {
                $cleanKey = strtolower(trim(str_replace([' ', '-', '/'], '_', (string) $k)));
                $normalized[$cleanKey] = $v;
            }

            $code = strtolower(trim((string) (
                $normalized['code'] ?? $normalized['kode'] ?? $normalized['kode_status'] ?? $row['code'] ?? ''
            )));
            $name = trim((string) (
                $normalized['name'] ?? $normalized['nama'] ?? $normalized['nama_status'] ?? $row['name'] ?? ''
            ));

            if (empty($code) || empty($name)) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: Kode dan Nama status wajib diisi.";
                continue;
            }

            $badgeColor = trim((string) ($normalized['badge_color'] ?? $normalized['warna'] ?? $row['badge_color'] ?? 'emerald'));
            $order = isset($normalized['order']) ? (int) $normalized['order'] : (isset($row['order']) ? (int) $row['order'] : $rowNum);
            $rawActive = $normalized['is_active'] ?? $normalized['status'] ?? $row['is_active'] ?? true;
            $isActive = is_bool($rawActive) ? $rawActive : in_array(strtolower(trim((string) $rawActive)), ['1', 'true', 'aktif', 'active', 'ya']);
            $description = $normalized['description'] ?? $normalized['deskripsi'] ?? $row['description'] ?? null;
            if ($description === '-') {
                $description = null;
            }

            try {
                $existing = StudentStatus::withTrashed()
                    ->where(function ($q) use ($code, $name) {
                        $q->where('code', $code)
                            ->orWhere('name', $name);
                    })
                    ->first();

                if ($existing) {
                    if ($overwrite) {
                        if ($existing->trashed()) {
                            $existing->restore();
                        }
                        $existing->update([
                            'name' => $name,
                            'badge_color' => $badgeColor ?: 'emerald',
                            'order' => $order,
                            'is_active' => $isActive,
                            'description' => $description,
                        ]);
                        $diperbarui++;
                        $duplicates[] = [
                            'baris' => $rowNum,
                            'kode' => $code,
                            'nama' => $name,
                            'status' => 'Ditimpa',
                            'keterangan' => 'Data status siswa diperbarui dengan data impor terbaru.',
                        ];
                    } else {
                        $duplikat++;
                        $duplicates[] = [
                            'baris' => $rowNum,
                            'kode' => $code,
                            'nama' => $name,
                            'status' => 'Dilewati',
                            'keterangan' => 'Kode atau nama status sudah terdaftar di database.',
                        ];
                    }
                    continue;
                }

                StudentStatus::create([
                    'code' => $code,
                    'name' => $name,
                    'badge_color' => $badgeColor ?: 'emerald',
                    'order' => $order,
                    'is_active' => $isActive,
                    'description' => $description,
                ]);
                $berhasil++;
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: ".$e->getMessage();
            }
        }

        $resultData = [
            'total' => count($rows),
            'berhasil' => $berhasil,
            'diperbarui' => $diperbarui,
            'duplikat' => $duplikat,
            'gagal' => $gagal,
            'overwrite' => $overwrite,
            'duplicates' => $duplicates,
            'errors' => $errors,
        ];

        return response()->json([
            'success' => true,
            'status' => 'success',
            'message' => "Proses impor selesai. Berhasil: {$berhasil}, Ditimpa: {$diperbarui}, Dilewati: {$duplikat}, Gagal: {$gagal}.",
            'data' => $resultData,
        ]);
    }

    /**
     * Unduh berkas format template CSV untuk impor status siswa.
     */
    public function importTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = ['code', 'name', 'badge_color', 'order', 'is_active', 'description'];
        $sample = [
            ['aktif', 'Aktif', 'emerald', '1', 'true', 'Siswa aktif mengikuti proses belajar mengajar'],
            ['lulus', 'Lulus', 'blue', '2', 'true', 'Siswa telah menyelesaikan pendidikan dan tamat'],
            ['mutasi', 'Mutasi Keluar', 'amber', '3', 'true', 'Siswa pindah ke satuan pendidikan lain'],
        ];

        return response()->streamDownload(function () use ($headers, $sample) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($sample as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'Template_Import_Status_Siswa.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
