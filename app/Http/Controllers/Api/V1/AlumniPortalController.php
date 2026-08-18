<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PengumumanSekolah;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlumniPortalController extends Controller
{
    private function getAuthenticatedAlumni(Request $request): ?Student
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        return Student::query()
            ->with(['educationUnit', 'kelas'])
            ->where('user_id', $user->id)
            ->first();
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $this->getAuthenticatedAlumni($request);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Data alumni tidak ditemukan untuk akun ini.',
            ], 404);
        }

        $meta = $student->metadata ?? [];

        $profileData = [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'nis' => $student->nis,
            'nisn' => $student->nisn,
            'gender' => $student->gender,
            'photo_url' => $student->photo_url,
            'avatar_url' => $student->avatar_url,
            'education_unit' => $student->educationUnit?->name ?? 'Unit Sekolah',
            'tahun_masuk' => $student->tahun_masuk,
            'tahun_lulus' => $meta['tahun_lulus'] ?? date('Y', strtotime($student->updated_at ?? now())),
            'status_kelulusan' => $meta['status_alumni'] ?? 'Tamat / Lulus',
            // Editable contact & study/work fields
            'address' => $student->address,
            'phone' => $meta['no_hp_alumni'] ?? $user->phone ?? '-',
            'email' => $user->email ?? '-',
            'status_lanjutan' => $meta['status_lanjutan'] ?? 'Belum Diisi',
            'perguruan_tinggi' => $meta['perguruan_tinggi'] ?? '-',
            'pekerjaan' => $meta['pekerjaan'] ?? '-',
            'instansi' => $meta['perusahaan'] ?? $meta['instansi'] ?? '-',
        ];

        $kpis = [
            'tahun_lulus' => $profileData['tahun_lulus'],
            'unit_asal' => $profileData['education_unit'],
            'status_profil' => ! empty($meta['perguruan_tinggi']) || ! empty($meta['pekerjaan']) ? 'Lengkap' : 'Perlu Diperbarui',
            'status_lanjutan' => $profileData['status_lanjutan'],
        ];

        $announcements = PengumumanSekolah::query()
            ->where('status_aktif', true)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Alumni berhasil dimuat.',
            'data' => [
                'context' => [
                    'role' => 'Alumni',
                    'name' => $student->full_name,
                ],
                'profile' => $profileData,
                'kpis' => $kpis,
                'announcements' => $announcements,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $student = $this->getAuthenticatedAlumni($request);

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'Data alumni tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'status_lanjutan' => 'nullable|string|max:100',
            'perguruan_tinggi' => 'nullable|string|max:200',
            'pekerjaan' => 'nullable|string|max:200',
            'instansi' => 'nullable|string|max:200',
        ]);

        if (! empty($validated['address'])) {
            $student->address = $validated['address'];
        }

        $meta = $student->metadata ?? [];
        if (isset($validated['phone'])) {
            $meta['no_hp_alumni'] = $validated['phone'];
        }
        if (isset($validated['status_lanjutan'])) {
            $meta['status_lanjutan'] = $validated['status_lanjutan'];
        }
        if (isset($validated['perguruan_tinggi'])) {
            $meta['perguruan_tinggi'] = $validated['perguruan_tinggi'];
        }
        if (isset($validated['pekerjaan'])) {
            $meta['pekerjaan'] = $validated['pekerjaan'];
        }
        if (isset($validated['instansi'])) {
            $meta['perusahaan'] = $validated['instansi'];
            $meta['instansi'] = $validated['instansi'];
        }

        $student->metadata = $meta;
        $student->save();

        if ($request->user() && ! empty($validated['email'])) {
            $request->user()->update(['email' => $validated['email']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil alumni berhasil diperbarui.',
            'data' => $student,
        ]);
    }
}
