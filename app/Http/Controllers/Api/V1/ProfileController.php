<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Ambil data profil lengkap pengguna yang sedang login.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load([
            'roles:id,name',
            'employee.unit:id,name,code',
            'employee.position:id,name,level_jabatan',
            'employee.division:id,name,code',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->formatUserProfile($user),
        ]);
    }

    /**
     * Perbarui informasi pribadi profil pengguna.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'fullName' => ['nullable', 'string', 'max:255'],
            'nama_panggilan' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'alamat' => ['nullable', 'string', 'max:500'],
            'unit' => ['nullable', 'string', 'max:255'],
            'unit_id' => ['nullable', 'string', 'max:255'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Alamat email sudah digunakan oleh pengguna lain.',
        ]);

        $name = $validated['name'] ?? $validated['fullName'] ?? null;

        DB::transaction(function () use ($user, $validated, $name) {
            // 1. Update User Record
            $userPayload = [
                'email' => strtolower($validated['email']),
                'phone' => $validated['phone'] ?? $user->phone,
            ];
            if ($name) {
                $userPayload['name'] = $name;
            }
            $user->fill($userPayload)->save();

            // 2. Resolve Unit ID if provided
            $unitId = null;
            if (! empty($validated['unit_id'])) {
                $unitVal = $validated['unit_id'];
                if (Str::isUuid($unitVal)) {
                    $unitId = $unitVal;
                } else {
                    $unitId = \App\Models\EducationUnit::where('name', $unitVal)
                        ->orWhere('code', $unitVal)
                        ->value('id');
                }
            } elseif (! empty($validated['unit'])) {
                $unitVal = $validated['unit'];
                $unitQuery = \App\Models\EducationUnit::where('name', $unitVal);
                if (Str::isUuid($unitVal)) {
                    $unitQuery->orWhere('id', $unitVal);
                } else {
                    $unitQuery->orWhere('code', $unitVal);
                }
                $unitId = $unitQuery->value('id');
            }

            // 3. Update Employee Record (jika terhubung)
            /** @var Employee|null $employee */
            $employee = $user->employee;
            if ($employee) {
                $employeePayload = [
                    'nama_panggilan' => $validated['nama_panggilan'] ?? $employee->nama_panggilan,
                    'no_hp' => $validated['phone'] ?? $employee->no_hp,
                    'email' => strtolower($validated['email']),
                    'alamat' => $validated['alamat'] ?? $employee->alamat,
                ];
                if ($name) {
                    $employeePayload['nama_lengkap'] = $name;
                }
                if ($unitId) {
                    $employeePayload['unit_id'] = $unitId;
                }
                $employee->fill($employeePayload)->save();
            }
        });

        $user->refresh();
        $user->load([
            'roles:id,name',
            'employee.unit:id,name,code',
            'employee.position:id,name,level_jabatan',
            'employee.division:id,name,code',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Profil berhasil diperbarui.',
            'data' => $this->formatUserProfile($user),
        ]);
    }

    /**
     * Upload dan ganti foto profil / avatar.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $file = $request->file('foto') ?? $request->file('avatar');

        if (! $file) {
            return response()->json([
                'status' => 'error',
                'message' => 'File foto wajib diunggah.',
                'errors' => [
                    'foto' => ['File foto wajib diunggah.'],
                ],
            ], 422);
        }

        $validator = \Illuminate\Support\Facades\Validator::make(['foto' => $file], [
            'foto' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], [
            'foto.required' => 'File foto wajib diunggah.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Format foto yang diizinkan: jpeg, png, jpg, webp.',
            'foto.max' => 'Ukuran foto maksimal 2MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $path = $file->store('avatars', 'public');

        DB::transaction(function () use ($user, $path) {
            /** @var Employee|null $employee */
            $employee = $user->employee;
            if ($employee) {
                // Hapus foto lama jika ada
                if ($employee->foto && Storage::disk('public')->exists($employee->foto)) {
                    Storage::disk('public')->delete($employee->foto);
                }
                $employee->update(['foto' => $path]);
            }

            $metadata = $user->metadata ?? [];
            $metadata['avatar'] = $path;
            $user->update(['metadata' => $metadata]);
        });

        $user->refresh();
        $user->load([
            'roles:id,name',
            'employee.unit:id,name,code',
            'employee.position:id,name,level_jabatan',
            'employee.division:id,name,code',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Foto profil berhasil diperbarui.',
            'data' => $this->formatUserProfile($user),
        ]);
    }

    /**
     * Ubah password akun.
     */
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.min' => 'Password baru minimal 8 karakter.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Password saat ini yang Anda masukkan salah.',
                'errors' => [
                    'current_password' => ['Password saat ini yang Anda masukkan tidak sesuai.'],
                ],
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password berhasil diubah.',
        ]);
    }

    /**
     * Format data profil lengkap pengguna.
     */
    private function formatUserProfile(User $user): array
    {
        $employee = $user->employee;
        $roles = $user->getRoleNames()->values()->toArray();
        $permissions = $user->getAllPermissions()->pluck('name')->values()->toArray();

        $fotoUrl = null;
        if ($employee && $employee->foto) {
            $fotoUrl = str_starts_with($employee->foto, 'http')
                ? $employee->foto
                : asset('storage/' . $employee->foto);
        } elseif (isset($user->metadata['avatar'])) {
            $fotoUrl = str_starts_with($user->metadata['avatar'], 'http')
                ? $user->metadata['avatar']
                : asset('storage/' . $user->metadata['avatar']);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'fullName' => $employee?->nama_lengkap ?? $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? $employee?->no_hp,
            'roles' => $roles,
            'permissions' => $permissions,
            'foto' => $fotoUrl,
            'avatar' => $fotoUrl,
            'unit' => $employee?->unit?->name,
            'employee' => $employee ? [
                'id' => $employee->id,
                'niy' => $employee->niy,
                'nik' => $employee->nik,
                'nama_lengkap' => $employee->nama_lengkap,
                'nama_panggilan' => $employee->nama_panggilan,
                'gelar_depan' => $employee->gelar_depan,
                'gelar_belakang' => $employee->gelar_belakang,
                'jenis_kelamin' => $employee->jenis_kelamin,
                'tempat_lahir' => $employee->tempat_lahir,
                'tanggal_lahir' => $employee->tanggal_lahir?->format('Y-m-d'),
                'foto' => $fotoUrl,
                'status_pegawai' => $employee->status_pegawai ?? 'Aktif',
                'tanggal_masuk' => $employee->tanggal_masuk?->format('Y-m-d'),
                'no_hp' => $employee->no_hp,
                'email' => $employee->email,
                'alamat' => $employee->alamat,
                'unit' => $employee->unit ? [
                    'id' => $employee->unit->id,
                    'name' => $employee->unit->name,
                    'code' => $employee->unit->code,
                ] : null,
                'position' => $employee->position ? [
                    'id' => $employee->position->id,
                    'name' => $employee->position->name,
                ] : null,
                'division' => $employee->division ? [
                    'id' => $employee->division->id,
                    'name' => $employee->division->name,
                ] : null,
            ] : null,
            'metadata' => $user->metadata,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
