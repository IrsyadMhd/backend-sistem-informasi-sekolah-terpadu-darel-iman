<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserAccountController extends Controller
{
    public function __construct(
        private readonly AccessScopeService $accessScope,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $unitId = trim((string) $request->query('unit_id', ''));
        $category = trim((string) $request->query('category', 'semua'));

        if ($unitId) {
            $this->accessScope->assertEducationUnitAccess($request->user(), $unitId);
        }

        $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($request->user())->pluck('id');
        $isGlobalAdmin = $this->isGlobalAdmin($request->user());

        $users = User::query()
            ->with([
                'roles:id,name',
                'employee.unit',
                'student.educationUnit',
                'parent.students.educationUnit',
                'parent.studentsPivot.educationUnit',
            ])
            ->when($request->query('status') === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($request->query('status') === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->when($request->query('role_status') === 'without_role', fn ($query) => $query->doesntHave('roles'))
            ->when($category && $category !== 'semua', function ($query) use ($category) {
                if ($category === 'pegawai') {
                    $query->where(function ($q) {
                        $q->whereHas('employee')
                          ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['Pegawai', 'Tata Usaha', 'Kepala Sekolah', 'Staf', 'tata_usaha', 'kepala_sekolah']));
                    })->whereDoesntHave('roles', fn ($r) => $r->where('name', 'like', '%Guru%'));
                } elseif ($category === 'guru') {
                    $query->whereHas('roles', fn ($r) => $r->where('name', 'like', '%Guru%')->orWhereIn('name', ['Wali Kelas', 'Musyrif', 'Guru Tahfizh', 'Pembimbing']));
                } elseif ($category === 'siswa') {
                    $query->where(function ($q) {
                        $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['Siswa', 'siswa', 'student']))
                          ->orWhereHas('student');
                    });
                } elseif ($category === 'orangtua' || $category === 'parent') {
                    $query->where(function ($q) {
                        $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['Orang Tua', 'orang_tua', 'Orangtua', 'Wali Murid', 'parent']))
                          ->orWhereHas('parent');
                    });
                } elseif ($category === 'standalone') {
                    $query->doesntHave('employee')->doesntHave('student')->doesntHave('parent');
                }
            })
            ->when($unitId !== '', function ($query) use ($unitId) {
                $query->where(function ($q) use ($unitId) {
                    $q->whereHas('employee', fn ($e) => $e->where('unit_id', $unitId))
                      ->orWhereHas('student', fn ($s) => $s->where('unit_id', $unitId))
                      ->orWhereHas('parent.students', fn ($s) => $s->where('unit_id', $unitId))
                      ->orWhereHas('parent.studentsPivot', fn ($s) => $s->where('unit_id', $unitId));
                });
            })
            ->when(! $isGlobalAdmin && $unitId === '', function ($query) use ($accessibleUnitIds, $request) {
                $query->where(function ($q) use ($accessibleUnitIds, $request) {
                    $q->where('id', $request->user()->id)
                      ->orWhereHas('employee', fn ($e) => $e->whereIn('unit_id', $accessibleUnitIds))
                      ->orWhereHas('student', fn ($s) => $s->whereIn('unit_id', $accessibleUnitIds))
                      ->orWhereHas('parent.students', fn ($s) => $s->whereIn('unit_id', $accessibleUnitIds))
                      ->orWhereHas('parent.studentsPivot', fn ($s) => $s->whereIn('unit_id', $accessibleUnitIds));
                });
            })
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $query->where('name', $operator, "%{$search}%")
                    ->orWhere('email', $operator, "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return response()->json([
            'success' => true,
            'data' => collect($users->items())->map(fn (User $user) => $this->serialize($user)),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $validated = $request->validate($this->rules());

        $user = DB::transaction(function () use ($validated, $request) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
                'is_active' => $validated['is_active'] ?? true,
                'metadata' => ['must_change_password' => true, 'created_by' => 'user_account_module'],
            ]);

            $additionalRoles = collect($validated['additional_roles'] ?? [])
                ->filter(fn ($r) => ! empty($r) && $r !== $validated['role'])
                ->values()
                ->all();

            foreach ($additionalRoles as $addRole) {
                $this->accessScope->assertRoleAssignmentAllowed($request->user(), $addRole);
            }

            $allRolesToSync = array_values(array_unique(array_merge([$validated['role']], $additionalRoles)));
            $user->syncRoles($allRolesToSync);

            return $user->load(['roles:id,name', 'employee.unit', 'student.educationUnit']);
        });

        return response()->json([
            'success' => true,
            'message' => "Akun {$user->name} berhasil dibuat.",
            'data' => $this->serialize($user),
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->assertUserAccess($request->user(), $user);

        return response()->json([
            'success' => true,
            'data' => $this->serialize($user->load(['roles:id,name', 'employee.unit', 'student.educationUnit'])),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->assertUserAccess($request->user(), $user);

        $targetIsSuperAdmin = $user->hasAnyRole(['Super Admin', 'Superadmin', 'super_admin', 'super-admin']);
        $actorIsSuperAdmin = $request->user()->hasAnyRole(['Super Admin', 'Superadmin', 'super_admin', 'super-admin']);
        abort_if($targetIsSuperAdmin && ! $actorIsSuperAdmin, 403, 'Hanya Super Admin yang dapat mengubah atau mengelola akun Super Admin.');

        $isUnitOnlyManager = $this->accessScope->canManageUnitAccess($request->user())
            && ! $this->accessScope->canManageGlobalAccess($request->user());
        $validated = $request->validate($isUnitOnlyManager
            ? [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
                'role' => ['required', 'string', Rule::exists(Role::class, 'name')],
                'additional_roles' => ['nullable', 'array'],
                'additional_roles.*' => ['string', Rule::exists(Role::class, 'name')],
                'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            ]
            : $this->rules($user, false));
        $this->accessScope->assertRoleAssignmentAllowed($request->user(), $validated['role']);

        $additionalRoles = collect($validated['additional_roles'] ?? [])
            ->filter(fn ($r) => ! empty($r) && $r !== $validated['role'])
            ->values()
            ->all();

        foreach ($additionalRoles as $addRole) {
            $this->accessScope->assertRoleAssignmentAllowed($request->user(), $addRole);
        }

        $removingOwnAdminAccess = $actorIsSuperAdmin
            && $request->user()->is($user)
            && ((! ($validated['is_active'] ?? $user->is_active))
                || (($validated['role'] ?? $user->getRoleNames()->first()) !== 'Super Admin'));

        abort_if($removingOwnAdminAccess, 422, 'Super Admin tidak dapat menonaktifkan atau mencabut role dirinya sendiri.');

        DB::transaction(function () use ($user, $validated, $additionalRoles, $isUnitOnlyManager) {
            $user->fill([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
            ]);
            if (! $isUnitOnlyManager) {
                $user->fill([
                    'phone' => $validated['phone'] ?? null,
                    'is_active' => $validated['is_active'] ?? $user->is_active,
                ]);
            }
            if (! empty($validated['password'])) {
                $user->password = $validated['password'];
                $metadata = $user->metadata ?? [];
                $metadata['must_change_password'] = false;
                $metadata['password_reset_at'] = now()->toISOString();
                $user->metadata = $metadata;
            }
            $user->save();

            $allRolesToSync = array_values(array_unique(array_merge([$validated['role']], $additionalRoles)));
            $user->syncRoles($allRolesToSync);

            if (! $user->is_active) {
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'success' => true,
            'message' => "Akun {$user->name} berhasil diperbarui.",
            'data' => $this->serialize($user->fresh(['roles:id,name', 'employee.unit', 'student.educationUnit'])),
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->assertUserAccess($request->user(), $user);

        $targetIsSuperAdmin = $user->hasAnyRole(['Super Admin', 'Superadmin', 'super_admin', 'super-admin']);
        $actorIsSuperAdmin = $request->user()->hasAnyRole(['Super Admin', 'Superadmin', 'super_admin', 'super-admin']);
        abort_if($targetIsSuperAdmin && ! $actorIsSuperAdmin, 403, 'Hanya Super Admin yang dapat mereset password akun Super Admin.');

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->letters()->numbers()->symbols()],
        ]);

        $metadata = $user->metadata ?? [];
        $metadata['must_change_password'] = true;
        $metadata['password_reset_at'] = now()->toIso8601String();
        $metadata['password_reset_by'] = $request->user()->id;

        $user->forceFill([
            'password' => $validated['password'],
            'metadata' => $metadata,
        ])->save();
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => "Password {$user->name} berhasil direset. Semua sesi lama telah dikeluarkan.",
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());
        $this->assertUserAccess($request->user(), $user);

        $targetIsSuperAdmin = $user->hasAnyRole(['Super Admin', 'Superadmin', 'super_admin', 'super-admin']);
        $actorIsSuperAdmin = $request->user()->hasAnyRole(['Super Admin', 'Superadmin', 'super_admin', 'super-admin']);
        abort_if($targetIsSuperAdmin && ! $actorIsSuperAdmin, 403, 'Hanya Super Admin yang dapat menghapus akun Super Admin.');

        abort_if($request->user()->is($user), 422, 'Anda tidak dapat menghapus akun yang sedang digunakan.');

        if ($user->hasRole('Super Admin')) {
            $activeAdmins = User::query()
                ->where('is_active', true)
                ->role('Super Admin')
                ->count();
            abort_if($activeAdmins <= 1, 422, 'Super Admin aktif terakhir tidak dapat dihapus.');
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->delete();
        });

        return response()->json([
            'success' => true,
            'message' => "Akun {$user->name} berhasil dihapus.",
        ]);
    }

    /**
     * Impor data akun pengguna dari berkas Excel/CSV atau payload JSON.
     */
    public function import(Request $request): JsonResponse
    {
        $this->accessScope->assertGlobalAccessManagement($request->user());

        $rows = [];
        $overwrite = $request->boolean('overwrite', false);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            try {
                $spreadsheet = IOFactory::load($file->getRealPath());
                $sheet = $spreadsheet->getActiveSheet();
                $sheetData = $sheet->toArray(null, true, true, true);

                if (! empty($sheetData)) {
                    $headerRow = array_shift($sheetData);
                    $headers = [];
                    foreach ($headerRow as $colKey => $colName) {
                        $colNameClean = strtolower(trim((string) $colName));
                        $headers[$colKey] = $colNameClean;
                    }

                    foreach ($sheetData as $rowData) {
                        $row = [];
                        $hasData = false;
                        foreach ($rowData as $colKey => $cellValue) {
                            $header = $headers[$colKey] ?? $colKey;
                            $trimmed = is_string($cellValue) ? trim($cellValue) : $cellValue;
                            if ($trimmed !== null && $trimmed !== '') {
                                $hasData = true;
                            }
                            $row[$header] = $trimmed;
                        }
                        if ($hasData) {
                            $rows[] = $row;
                        }
                    }
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Gagal membaca berkas: '.$e->getMessage(),
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

            $name = trim((string) ($normalized['name'] ?? $normalized['nama'] ?? $normalized['nama_lengkap'] ?? $row['name'] ?? ''));
            $email = strtolower(trim((string) ($normalized['email'] ?? $normalized['surel'] ?? $normalized['email_address'] ?? $row['email'] ?? '')));

            if (empty($name) || empty($email)) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: Nama dan Email akun wajib diisi.";
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $gagal++;
                $errors[] = "Baris {$rowNum}: Format email '{$email}' tidak valid.";
                continue;
            }

            $phone = trim((string) ($normalized['phone'] ?? $normalized['telepon'] ?? $normalized['no_hp'] ?? $normalized['whatsapp'] ?? $row['phone'] ?? ''));
            $roleName = trim((string) ($normalized['role'] ?? $normalized['peran'] ?? $normalized['hak_akses'] ?? $row['role'] ?? ''));
            $password = trim((string) ($normalized['password'] ?? $normalized['kata_sandi'] ?? $row['password'] ?? ''));
            $rawActive = $normalized['is_active'] ?? $normalized['status'] ?? $row['is_active'] ?? true;
            $isActive = is_bool($rawActive) ? $rawActive : in_array(strtolower(trim((string) $rawActive)), ['1', 'true', 'aktif', 'active', 'ya']);

            try {
                $existing = User::where('email', $email)->first();

                if ($existing) {
                    if ($overwrite) {
                        $updateData = [
                            'name' => $name,
                            'is_active' => $isActive,
                        ];
                        if (! empty($phone)) {
                            $updateData['phone'] = $phone;
                        }
                        if (! empty($password)) {
                            $updateData['password'] = $password;
                            $metadata = $existing->metadata ?? [];
                            $metadata['must_change_password'] = false;
                            $metadata['password_reset_at'] = now()->toISOString();
                            $updateData['metadata'] = $metadata;
                        }
                        $existing->update($updateData);

                        if (! empty($roleName) && Role::where('name', $roleName)->exists()) {
                            $existing->syncRoles([$roleName]);
                        }

                        $diperbarui++;
                        $duplicates[] = [
                            'baris' => $rowNum,
                            'kode' => $email,
                            'nama' => $name,
                            'status' => 'Ditimpa',
                            'keterangan' => 'Akun diperbarui dengan data impor terbaru.',
                        ];
                    } else {
                        $duplikat++;
                        $duplicates[] = [
                            'baris' => $rowNum,
                            'kode' => $email,
                            'nama' => $name,
                            'status' => 'Dilewati',
                            'keterangan' => 'Email akun sudah terdaftar di sistem.',
                        ];
                    }
                    continue;
                }

                $newUser = User::create([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone ?: null,
                    'password' => $password ?: 'Password123!',
                    'is_active' => $isActive,
                    'metadata' => [
                        'must_change_password' => empty($password),
                        'created_by' => 'user_account_import',
                    ],
                ]);

                if (! empty($roleName) && Role::where('name', $roleName)->exists()) {
                    $newUser->assignRole($roleName);
                }

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
     * Unduh berkas format template CSV untuk impor akun pengguna.
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = ['name', 'email', 'role', 'phone', 'password', 'is_active'];
        $sample = [
            ['Ahmad Dahlan', 'ahmad.dahlan@sekolah.sch.id', 'Guru', '081234567890', 'Password123!', 'true'],
            ['Siti Nurhaliza', 'siti.nur@sekolah.sch.id', 'Tata Usaha', '081298765432', 'Password123!', 'true'],
            ['Budi Santoso', 'budi.santoso@sekolah.sch.id', 'Siswa', '081311223344', 'Password123!', 'true'],
        ];

        return response()->streamDownload(function () use ($headers, $sample) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($sample as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'Template_Import_Akun_Pengguna.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function isGlobalAdmin(User $user): bool
    {
        return $this->accessScope->hasGlobalScope($user);
    }

    private function assertUserAccess(User $authUser, User $targetUser): void
    {
        if ($this->accessScope->canManageGlobalAccess($authUser)) {
            return;
        }

        $accessibleUnitIds = $this->accessScope->accessibleEducationUnits($authUser)->pluck('id');
        $targetUnitId = $targetUser->employee?->unit_id ?? $targetUser->student?->unit_id;

        if (! $targetUnitId && $targetUser->parent) {
            $parentChildUnits = collect($targetUser->parent->students ?? [])
                ->merge($targetUser->parent->studentsPivot ?? [])
                ->pluck('unit_id')
                ->filter();
            $targetUnitId = $parentChildUnits->first(fn ($uId) => $accessibleUnitIds->contains($uId));
        }

        abort_unless(
            $this->accessScope->canManageUnitAccess($authUser)
                && $targetUnitId
                && $accessibleUnitIds->contains($targetUnitId),
            403,
            'Akun pengguna berada di luar cakupan unit pendidikan Anda.'
        );
    }

    private function rules(?User $user = null, bool $withPassword = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', Rule::exists(Role::class, 'name')],
            'additional_roles' => ['nullable', 'array'],
            'additional_roles.*' => ['string', Rule::exists(Role::class, 'name')],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($withPassword) {
            $rules['password'] = ['required', 'confirmed', Password::min(8)->mixedCase()->letters()->numbers()->symbols()];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:8', 'confirmed'];
        }

        return $rules;
    }

    private function serialize(User $user): array
    {
        $unit = null;
        if ($user->employee?->unit) {
            $unit = [
                'id' => $user->employee->unit->id,
                'nama' => $user->employee->unit->nama_unit ?? $user->employee->unit->name ?? '-',
                'is_parent_unit' => false,
            ];
        } elseif ($user->student?->educationUnit) {
            $unit = [
                'id' => $user->student->educationUnit->id,
                'nama' => $user->student->educationUnit->nama_unit ?? $user->student->educationUnit->name ?? '-',
                'is_parent_unit' => false,
            ];
        } elseif ($user->parent) {
            $childrenUnits = collect($user->parent->students ?? [])
                ->merge($user->parent->studentsPivot ?? [])
                ->pluck('educationUnit')
                ->filter()
                ->unique('id');
            if ($childrenUnits->isNotEmpty()) {
                $unit = [
                    'id' => $childrenUnits->first()->id,
                    'nama' => $childrenUnits->map(fn ($u) => $u->nama_unit ?? $u->name)->join(', '),
                    'is_parent_unit' => true,
                ];
            }
        }

        $personType = 'standalone';
        if ($user->employee) {
            $personType = 'employee';
        } elseif ($user->student) {
            $personType = 'student';
        } elseif ($user->parent) {
            $personType = 'parent';
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'person_type' => $personType,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->values(),
            'unit' => $unit,
            'must_change_password' => (bool) data_get($user->metadata, 'must_change_password', false),
            'last_password_reset_at' => data_get($user->metadata, 'password_reset_at'),
            'created_at' => $user->created_at,
        ];
    }
}
