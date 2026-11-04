<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentVocabularySubmission;
use App\Models\VocabularyBank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VocabularyController extends Controller
{
    /**
     * Helper to get user's unit ID safely
     */
    protected function getUserUnitId($user)
    {
        return $user->unit_id
            ?? $user->education_unit_id
            ?? $user->employee?->unit_id
            ?? $user->metadata['unit_id']
            ?? null;
    }

    /**
     * Check if user is Divisi Pendidikan / Executive
     */
    protected function isDivisiOrGlobal($user): bool
    {
        $roleNames = collect($user->roles ?? [])->map(function ($r) {
            return is_string($r) ? $r : ($r->name ?? '');
        })->toArray();

        $globals = [
            'Super Admin', 'Admin', 'Yayasan', 'Ketua Yayasan',
            'Pengurus Yayasan', 'Divisi Pendidikan', 'Kepala Bidang Pendidikan',
            'divisi', 'foundation', 'super_admin',
        ];

        foreach ($globals as $g) {
            if (in_array($g, $roleNames)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user is Kepala Sekolah
     */
    protected function isPrincipal($user): bool
    {
        $roleNames = collect($user->roles ?? [])->map(function ($r) {
            return is_string($r) ? $r : ($r->name ?? '');
        })->toArray();

        $principals = ['Kepala Sekolah', 'principal'];
        foreach ($principals as $p) {
            if (in_array($p, $roleNames)) {
                return true;
            }
        }
        return false;
    }

    /**
     * List vocabulary bank with strict scoping
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $query = VocabularyBank::with(['unit', 'creator:id,name', 'principal:id,name', 'divisi:id,name']);

        $isGlobal = $this->isDivisiOrGlobal($user);
        $isKepsek = $this->isPrincipal($user);
        $userUnitId = $this->getUserUnitId($user);

        // 1. Scoping Rule:
        if ($isGlobal) {
            // Divisi Pendidikan can view all units or filter by specific unit
            if ($request->filled('unit_id') && $request->unit_id !== 'all') {
                $query->where('unit_id', $request->unit_id);
            }
        } elseif ($isKepsek) {
            // Kepala Sekolah STRICTLY locked to their own education unit
            if (! $userUnitId) {
                return response()->json(['success' => false, 'message' => 'Akun Kepala Sekolah belum terhubung dengan unit.'], 403);
            }
            $query->where('unit_id', $userUnitId);
        } else {
            // Guru Mapel / Staf / Siswa / Orang Tua
            if ($request->filled('unit_id') && $request->unit_id !== 'all') {
                $query->where('unit_id', $request->unit_id);
            } elseif ($userUnitId) {
                $query->where(function ($q) use ($userUnitId, $user) {
                    $q->where('unit_id', $userUnitId)
                      ->orWhere('created_by', $user->id);
                });
            } elseif ($request->input('status') === 'approved') {
                // Siswa / Orang Tua dapat melihat bank kosakata yang telah disahkan
                $query->where('status', 'approved');
            } else {
                $query->where('created_by', $user->id);
            }
        }

        // 2. Filters
        if ($request->filled('language') && $request->language !== 'all') {
            $query->where('language', strtolower($request->language));
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('word', 'LIKE', $search)
                  ->orWhere('translation', 'LIKE', $search)
                  ->orWhere('theme', 'LIKE', $search);
            });
        }

        $perPage = min((int) ($request->per_page ?? 20), 100);
        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar bank kosakata berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * Propose new vocabulary (Guru Mapel Bahasa)
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'word' => 'required|string|max:255',
            'translation' => 'required|string|max:255',
            'language' => 'required|in:arab,inggris',
            'phonetic' => 'nullable|string|max:255',
            'example_sentence' => 'nullable|string',
            'target_level' => 'nullable|string|max:50',
            'theme' => 'nullable|string|max:100',
            'unit_id' => 'nullable|integer',
        ]);

        $unitId = $validated['unit_id'] ?? $this->getUserUnitId($user);
        if (! $unitId) {
            return response()->json(['success' => false, 'message' => 'Unit pendidikan pengusul tidak ditemukan.'], 422);
        }

        $vocab = VocabularyBank::create([
            'unit_id' => $unitId,
            'subject_id' => $request->subject_id,
            'language' => $validated['language'],
            'word' => $validated['word'],
            'phonetic' => $validated['phonetic'] ?? null,
            'translation' => $validated['translation'],
            'example_sentence' => $validated['example_sentence'] ?? null,
            'target_level' => $validated['target_level'] ?? 'Umum',
            'theme' => $validated['theme'] ?? 'Umum',
            'created_by' => $user->id,
            'status' => 'pending_principal', // Menunggu persetujuan Kepsek unit
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kosakata berhasil diajukan, menunggu telaah Kepala Sekolah unit.',
            'data' => $vocab->load('unit'),
        ], 201);
    }

    /**
     * Stage 1 Approval by Kepala Sekolah (Strict Unit Scoping)
     */
    public function approveByPrincipal(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $isGlobal = $this->isDivisiOrGlobal($user);
        $isKepsek = $this->isPrincipal($user);

        if (! $isKepsek && ! $isGlobal) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Hanya Kepala Sekolah yang berhak memverifikasi usulan kosakata di unitnya.'], 403);
        }

        $vocab = VocabularyBank::findOrFail($id);
        $userUnitId = $this->getUserUnitId($user);

        // Strict Unit Scope Check for Kepala Sekolah
        if ($isKepsek && ! $isGlobal && (string) $vocab->unit_id !== (string) $userUnitId) {
            return response()->json([
                'success' => false,
                'message' => 'Kepala Sekolah hanya memiliki otoritas menyetujui kosakata di unit pendidikannya sendiri.',
            ], 403);
        }

        $decision = $request->input('decision', 'approve'); // 'approve' | 'reject'
        $notes = $request->input('notes', '');

        if ($decision === 'approve') {
            $vocab->update([
                'status' => 'pending_divisi', // Naik ke Divisi Pendidikan
                'principal_id' => $user->id,
                'principal_approved_at' => now(),
                'principal_notes' => $notes ?: 'Disetujui oleh Kepala Sekolah unit.',
            ]);
            $msg = 'Kosakata disetujui Kepala Sekolah dan diteruskan ke Divisi Pendidikan Yayasan.';
        } else {
            $vocab->update([
                'status' => 'rejected_principal',
                'principal_id' => $user->id,
                'principal_approved_at' => now(),
                'principal_notes' => $notes ?: 'Perlu perbaikan butir kosakata di tingkat unit.',
            ]);
            $msg = 'Kosakata dikembalikan ke Guru Mapel untuk perbaikan.';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data' => $vocab->fresh(['unit', 'principal']),
        ]);
    }

    /**
     * Stage 2 Final Approval by Divisi Pendidikan (Global Scope)
     */
    public function approveByDivisi(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (! $this->isDivisiOrGlobal($user)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak. Hanya Divisi Pendidikan yang memiliki hak pengesahan final kurikulum kosakata.'], 403);
        }

        $vocab = VocabularyBank::findOrFail($id);
        $decision = $request->input('decision', 'approve'); // 'approve' | 'reject'
        $notes = $request->input('notes', '');

        if ($decision === 'approve') {
            $vocab->update([
                'status' => 'approved', // Resmi aktif menjadi materi setoran santri
                'divisi_id' => $user->id,
                'divisi_approved_at' => now(),
                'divisi_notes' => $notes ?: 'Disahkan oleh Divisi Pendidikan Yayasan sebagai materi setoran aktif.',
            ]);
            $msg = 'Kosakata resmi disahkan sebagai materi setoran aktif santri.';
        } else {
            $vocab->update([
                'status' => 'rejected_divisi',
                'divisi_id' => $user->id,
                'divisi_approved_at' => now(),
                'divisi_notes' => $notes ?: 'Ditolak/diminta revisi oleh Divisi Pendidikan.',
            ]);
            $msg = 'Kosakata dikembalikan oleh Divisi Pendidikan untuk penyesuaian standar.';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data' => $vocab->fresh(['unit', 'principal', 'divisi']),
        ]);
    }

    /**
     * List student submissions
     */
    public function submissions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $query = StudentVocabularySubmission::with([
            'student:id,name,nisn,class_id',
            'vocabulary:id,word,translation,language,theme',
            'teacher:id,name',
            'unit:id,name',
        ]);

        $isGlobal = $this->isDivisiOrGlobal($user);
        $isKepsek = $this->isPrincipal($user);
        $userUnitId = $this->getUserUnitId($user);

        if ($isGlobal) {
            if ($request->filled('unit_id') && $request->unit_id !== 'all') {
                $query->where('unit_id', $request->unit_id);
            }
        } elseif ($isKepsek) {
            $query->where('unit_id', $userUnitId);
        } elseif ($request->filled('student_id')) {
            // Orang Tua / Siswa / Wali Kelas memantau setoran siswa tertentu
            $query->where('student_id', $request->student_id);
        } else {
            // Guru Mapel default melihat setoran yang ia uji
            $query->where('teacher_id', $user->id);
        }

        if ($request->filled('student_id') && ($isGlobal || $isKepsek)) {
            $query->where('student_id', $request->student_id);
        }

        $perPage = min((int) ($request->per_page ?? 20), 100);
        $data = $query->orderBy('submission_date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Daftar setoran kosakata santri berhasil dimuat.',
            'data' => $data,
        ]);
    }

    /**
     * Input student vocabulary submission log (Guru Mapel Bahasa)
     */
    public function storeSubmission(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'student_id' => 'required|string',
            'vocabulary_id' => 'required|integer',
            'submission_date' => 'required|date',
            'fluency_rating' => 'required|in:mutqin,lancar,mengulang',
            'score' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        $vocab = VocabularyBank::findOrFail($validated['vocabulary_id']);

        // Hanya kosakata yang telah berstatus approved yang boleh disetorkan
        if ($vocab->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Kosakata ini belum disetujui penuh oleh Kepala Sekolah dan Divisi Pendidikan sehingga belum dapat dijadikan bahan setoran.',
            ], 422);
        }

        $submission = StudentVocabularySubmission::create([
            'student_id' => $validated['student_id'],
            'vocabulary_id' => $validated['vocabulary_id'],
            'teacher_id' => $user->id,
            'unit_id' => $vocab->unit_id,
            'submission_date' => $validated['submission_date'],
            'fluency_rating' => $validated['fluency_rating'],
            'score' => $validated['score'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Setoran hafalan kosakata santri berhasil dicatat.',
            'data' => $submission->load(['student', 'vocabulary', 'teacher']),
        ], 201);
    }
}
