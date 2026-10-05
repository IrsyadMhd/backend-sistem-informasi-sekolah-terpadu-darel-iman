<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\Student;
use App\Models\StudentMutation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentMutationController extends Controller
{
    /**
     * Dapatkan cakupan akses unit pengguna saat ini
     */
    private function scopeForUser(User $user): array
    {
        $employee = Employee::query()
            ->with(['position:id,name,scope_akses', 'unit:id,name'])
            ->where('user_id', $user->id)
            ->first();

        $canAccessAllUnits = $user->can('student.view_all')
            || $user->can('foundation.student.view')
            || $user->can('report.cross_unit.view')
            || $employee?->position?->scope_akses === 'semua_unit'
            || str_contains(strtolower((string) $employee?->position?->name), 'yayasan');

        $unitId = $employee?->unit_id
            ?? data_get($user->metadata, 'unit_id')
            ?? data_get($user->metadata, 'unit_pendidikan_id');

        return [$canAccessAllUnits, $unitId, $employee];
    }

    /**
     * Daftar mutasi siswa (History & Status)
     */
    public function index(Request $request): JsonResponse
    {
        [$canAccessAllUnits, $userUnitId] = $this->scopeForUser($request->user());
        $requestedUnitId = $request->query('unit_id');

        $query = StudentMutation::query()
            ->with([
                'student:id,nis,nisn,full_name,gender,photo,metadata',
                'unitAsal:id,name,level',
                'unitTujuan:id,name,level',
                'kelasAsal:id,nama_kelas,tingkat',
                'kelasTujuan:id,nama_kelas,tingkat',
                'diajukanOleh:id,name,email',
                'disetujuiOleh:id,name,email',
            ])
            ->latest('diajukan_pada');

        if (! $canAccessAllUnits) {
            abort_unless($userUnitId, 403, 'Akun Anda tidak memiliki cakupan unit pendidikan.');
            $query->where(function ($q) use ($userUnitId) {
                $q->where('unit_asal_id', $userUnitId)
                  ->orWhere('unit_tujuan_id', $userUnitId);
            });
        } elseif (! empty($requestedUnitId)) {
            $query->where(function ($q) use ($requestedUnitId) {
                $q->where('unit_asal_id', $requestedUnitId)
                  ->orWhere('unit_tujuan_id', $requestedUnitId);
            });
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('jenis_mutasi') && $request->query('jenis_mutasi') !== 'all') {
            $query->where('jenis_mutasi', $request->query('jenis_mutasi'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->whereHas('student', function ($sq) use ($search) {
                $sq->where('full_name', 'like', "%{$search}%")
                   ->orWhere('nis', 'like', "%{$search}%")
                   ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $data = $query->paginate($perPage);

        return response()->json($data);
    }

    /**
     * Daftar mutasi masuk yang menunggu persetujuan (Incoming Transfers for TU Unit Tujuan)
     */
    public function incoming(Request $request): JsonResponse
    {
        [$canAccessAllUnits, $userUnitId] = $this->scopeForUser($request->user());
        $requestedUnitId = $request->query('unit_id');

        $query = StudentMutation::query()
            ->with([
                'student:id,nis,nisn,full_name,gender,birth_place,birth_date,address,photo,metadata',
                'unitAsal:id,name,level',
                'unitTujuan:id,name,level',
                'kelasAsal:id,nama_kelas,tingkat',
                'diajukanOleh:id,name,email',
            ])
            ->where('status', 'menunggu_persetujuan')
            ->where('jenis_mutasi', 'pindah_unit_internal')
            ->latest('diajukan_pada');

        if (! $canAccessAllUnits) {
            abort_unless($userUnitId, 403, 'Akun Anda tidak memiliki cakupan unit pendidikan.');
            $query->where('unit_tujuan_id', $userUnitId);
        } elseif (! empty($requestedUnitId)) {
            $query->where('unit_tujuan_id', $requestedUnitId);
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->whereHas('student', function ($sq) use ($search) {
                $sq->where('full_name', 'like', "%{$search}%")
                   ->orWhere('nis', 'like', "%{$search}%")
                   ->orWhere('nisn', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->query('per_page', 20);
        $data = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'message' => 'Daftar pengajuan siswa pindahan masuk berhasil dimuat.',
            'total_pending' => $data->total(),
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ]);
    }

    /**
     * Mengajukan pindah unit / mutasi siswa (Dilakukan oleh TU Unit Asal)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'jenis_mutasi' => ['required', 'string', 'in:pindah_unit_internal,pindah_keluar,pindah_masuk,berhenti'],
            'target_unit_id' => ['nullable', 'uuid', 'exists:education_units,id'],
            'sekolah_tujuan' => ['nullable', 'string', 'max:180'],
            'sekolah_asal' => ['nullable', 'string', 'max:180'],
            'alasan' => ['nullable', 'string', 'max:500'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        [$canAccessAllUnits, $userUnitId] = $this->scopeForUser($request->user());
        $student = Student::findOrFail($validated['student_id']);

        // Verifikasi kepemilikan unit
        if (! $canAccessAllUnits && $userUnitId && $student->unit_id !== $userUnitId) {
            abort(403, 'Anda hanya dapat mengajukan mutasi untuk siswa di unit pendidikan Anda.');
        }

        // Cek apakah siswa sudah memiliki pengajuan mutasi yang masih berstatus pending
        $hasPending = StudentMutation::where('student_id', $student->id)
            ->where('status', 'menunggu_persetujuan')
            ->exists();

        if ($hasPending) {
            return response()->json([
                'status' => 'error',
                'message' => 'Siswa ini masih memiliki permohonan mutasi yang sedang diproses oleh unit tujuan.',
            ], 422);
        }

        $jenisMutasi = $validated['jenis_mutasi'];
        $reason = $validated['alasan'] ?? $validated['keterangan'] ?? 'Permohonan mutasi siswa';

        // Mutasi Antar Unit Internal (Dua Arah - Handshake)
        if ($jenisMutasi === 'pindah_unit_internal') {
            if (empty($validated['target_unit_id'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit pendidikan tujuan wajib dipilih untuk mutasi antar unit internal.',
                ], 422);
            }

            if ($student->unit_id === $validated['target_unit_id']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit tujuan tidak boleh sama dengan unit asal siswa.',
                ], 422);
            }

            $mutation = DB::transaction(function () use ($student, $validated, $request, $reason) {
                // Catat transaksi mutasi
                $mut = StudentMutation::create([
                    'student_id' => $student->id,
                    'unit_asal_id' => $student->unit_id,
                    'unit_tujuan_id' => $validated['target_unit_id'],
                    'kelas_asal_id' => $student->kelas_id,
                    'kelas_tujuan_id' => null, // akan divalidasi oleh TU unit tujuan
                    'jenis_mutasi' => 'pindah_unit_internal',
                    'alasan' => $reason,
                    'status' => 'menunggu_persetujuan',
                    'diajukan_oleh' => $request->user()->id,
                    'diajukan_pada' => now(),
                    'metadata' => [
                        'unit_asal_nama' => $student->educationUnit?->name,
                        'kelas_asal_nama' => $student->kelas?->nama_kelas,
                    ],
                ]);

                // Update status siswa sementara menjadi mutasi proses
                $meta = is_array($student->metadata) ? $student->metadata : [];
                $meta['status_siswa'] = 'mutasi_proses';
                $meta['mutasi_type'] = 'antar_unit';
                $meta['mutasi_status'] = 'Diajukan';
                $meta['unit_tujuan_id'] = $validated['target_unit_id'];
                $meta['pending_mutation_id'] = $mut->id;

                $student->metadata = $meta;
                $student->save();

                return $mut;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan pindah unit berhasil dikirim ke unit tujuan. Menunggu validasi & penetapan kelas oleh TU unit tujuan.',
                'data' => $mutation->load(['student', 'unitAsal', 'unitTujuan']),
            ], 201);
        }

        // Mutasi Keluar Eksternal
        if ($jenisMutasi === 'pindah_keluar') {
            if (empty($validated['sekolah_tujuan'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Nama sekolah tujuan wajib diisi untuk mutasi keluar.',
                ], 422);
            }

            $mutation = DB::transaction(function () use ($student, $validated, $request, $reason) {
                $mut = StudentMutation::create([
                    'student_id' => $student->id,
                    'unit_asal_id' => $student->unit_id,
                    'unit_tujuan_id' => null,
                    'kelas_asal_id' => $student->kelas_id,
                    'kelas_tujuan_id' => null,
                    'jenis_mutasi' => 'pindah_keluar',
                    'sekolah_tujuan' => $validated['sekolah_tujuan'],
                    'alasan' => $reason,
                    'status' => 'disetujui',
                    'diajukan_oleh' => $request->user()->id,
                    'diajukan_pada' => now(),
                    'disetujui_oleh' => $request->user()->id,
                    'disetujui_pada' => now(),
                ]);

                $meta = is_array($student->metadata) ? $student->metadata : [];
                $meta['status_siswa'] = 'mutasi_keluar';
                $meta['mutasi_type'] = 'keluar';
                $meta['mutasi_status'] = 'Selesai';
                $meta['sekolah_tujuan'] = $validated['sekolah_tujuan'];
                $meta['tgl_mutasi'] = now()->toDateTimeString();

                $student->is_active = false;
                $student->metadata = $meta;
                $student->save();

                return $mut;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Data mutasi keluar berhasil disimpan.',
                'data' => $mutation,
            ], 201);
        }

        return response()->json(['status' => 'error', 'message' => 'Jenis mutasi tidak didukung.'], 422);
    }

    /**
     * TU Unit Tujuan melakukan Approval & Validasi Kelas Siswa Pindahan
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'kelas_id' => ['required', 'uuid', 'exists:tbl_kelas,id'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        [$canAccessAllUnits, $userUnitId] = $this->scopeForUser($request->user());

        $mutation = StudentMutation::with(['student', 'unitAsal', 'unitTujuan'])->findOrFail($id);

        if ($mutation->status !== 'menunggu_persetujuan') {
            return response()->json([
                'status' => 'error',
                'message' => "Pengajuan mutasi ini tidak dapat disetujui karena statusnya sudah '{$mutation->status}'.",
            ], 422);
        }

        // Pastikan pengguna memiliki wewenang atas Unit Tujuan
        if (! $canAccessAllUnits && $userUnitId && $mutation->unit_tujuan_id !== $userUnitId) {
            abort(403, 'Hanya TU / Operator dari unit pendidikan tujuan yang berhak memvalidasi & menyetujui mutasi masuk ini.');
        }

        // Pastikan kelas_id yang dipilih memang milik Unit Tujuan
        $targetClass = Kelas::where('id', $validated['kelas_id'])
            ->where('unit_pendidikan_id', $mutation->unit_tujuan_id)
            ->first();

        if (! $targetClass) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kelas yang dipilih tidak sesuai dengan unit pendidikan tujuan.',
            ], 422);
        }

        DB::transaction(function () use ($mutation, $validated, $request, $targetClass) {
            $student = $mutation->student;
            $oldUnitId = $student->unit_id;
            $newUnitId = $mutation->unit_tujuan_id;

            // 1. Update status mutasi
            $mutation->update([
                'status' => 'disetujui',
                'kelas_tujuan_id' => $targetClass->id,
                'disetujui_oleh' => $request->user()->id,
                'disetujui_pada' => now(),
                'metadata' => array_merge($mutation->metadata ?? [], [
                    'kelas_tujuan_nama' => $targetClass->nama_kelas,
                    'catatan_approval' => $validated['catatan'] ?? null,
                ]),
            ]);

            // 2. Update Student: pindahkan unit dan tetapkan kelas
            $meta = is_array($student->metadata) ? $student->metadata : [];
            $riwayat = $meta['riwayat_mutasi'] ?? [];
            $riwayat[] = [
                'mutation_id' => $mutation->id,
                'unit_asal_id' => $oldUnitId,
                'unit_tujuan_id' => $newUnitId,
                'kelas_tujuan_id' => $targetClass->id,
                'kelas_tujuan_nama' => $targetClass->nama_kelas,
                'tanggal' => now()->toDateTimeString(),
                'disetujui_oleh' => $request->user()->name,
            ];

            $meta['riwayat_mutasi'] = $riwayat;
            $meta['status_siswa'] = 'aktif';
            $meta['mutasi_status'] = 'Disetujui';
            $meta['mutasi_type'] = 'masuk_unit_baru';
            $meta['unit_asal_id'] = $oldUnitId;
            $meta['tgl_mutasi'] = now()->toDateTimeString();
            $meta['jenis_pendaftaran'] = 'pindahan_internal';
            unset($meta['pending_mutation_id']);

            $student->unit_id = $newUnitId;
            $student->kelas_id = $targetClass->id;
            $student->class_id = $targetClass->id; // backward compat
            $student->is_active = true;
            $student->metadata = $meta;
            $student->save();
        });

        return response()->json([
            'status' => 'success',
            'message' => "Siswa {$mutation->student->full_name} berhasil divalidasi dan diterima di {$mutation->unitTujuan?->name} kelas {$targetClass->nama_kelas}.",
            'data' => $mutation->fresh(['student', 'unitTujuan', 'kelasTujuan']),
        ]);
    }

    /**
     * TU Unit Tujuan Menolak Pengajuan Mutasi Masuk
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'catatan_penolakan' => ['required', 'string', 'max:500'],
        ]);

        [$canAccessAllUnits, $userUnitId] = $this->scopeForUser($request->user());

        $mutation = StudentMutation::with(['student'])->findOrFail($id);

        if ($mutation->status !== 'menunggu_persetujuan') {
            return response()->json([
                'status' => 'error',
                'message' => "Pengajuan mutasi ini tidak dapat ditolak karena statusnya sudah '{$mutation->status}'.",
            ], 422);
        }

        if (! $canAccessAllUnits && $userUnitId && $mutation->unit_tujuan_id !== $userUnitId) {
            abort(403, 'Hanya TU / Operator dari unit pendidikan tujuan yang berhak menolak permohonan mutasi masuk ini.');
        }

        DB::transaction(function () use ($mutation, $validated, $request) {
            $mutation->update([
                'status' => 'ditolak',
                'catatan_penolakan' => $validated['catatan_penolakan'],
                'disetujui_oleh' => $request->user()->id,
                'disetujui_pada' => now(),
            ]);

            // Kembalikan status siswa di unit asal ke aktif
            $student = $mutation->student;
            $meta = is_array($student->metadata) ? $student->metadata : [];
            $meta['status_siswa'] = 'aktif';
            $meta['mutasi_status'] = 'Ditolak';
            $meta['alasan_penolakan_mutasi'] = $validated['catatan_penolakan'];
            unset($meta['pending_mutation_id']);

            $student->metadata = $meta;
            $student->save();
        });

        return response()->json([
            'status' => 'success',
            'message' => "Pengajuan mutasi siswa {$mutation->student->full_name} berhasil ditolak. Siswa tetap aktif di unit asal.",
            'data' => $mutation->fresh(),
        ]);
    }
}
