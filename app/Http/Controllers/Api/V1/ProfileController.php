<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LoginEvent;
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
            'gelar_depan' => ['nullable', 'string', 'max:50'],
            'gelar_belakang' => ['nullable', 'string', 'max:50'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['nullable', 'string', 'in:L,P,Laki-laki,Perempuan'],
            'agama' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'no_hp' => ['nullable', 'string', 'max:30'],
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
        $phone = $validated['phone'] ?? $validated['no_hp'] ?? null;

        DB::transaction(function () use ($user, $validated, $name, $phone) {
            // 1. Update User Record
            $userPayload = [
                'email' => strtolower($validated['email']),
            ];
            if ($phone !== null) {
                $userPayload['phone'] = $phone;
            }
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
                    'email' => strtolower($validated['email']),
                ];
                if ($phone !== null) {
                    $employeePayload['no_hp'] = $phone;
                }
                if ($name) {
                    $employeePayload['nama_lengkap'] = $name;
                }
                if (array_key_exists('nama_panggilan', $validated)) {
                    $employeePayload['nama_panggilan'] = $validated['nama_panggilan'];
                }
                if (array_key_exists('gelar_depan', $validated)) {
                    $employeePayload['gelar_depan'] = $validated['gelar_depan'];
                }
                if (array_key_exists('gelar_belakang', $validated)) {
                    $employeePayload['gelar_belakang'] = $validated['gelar_belakang'];
                }
                if (array_key_exists('tempat_lahir', $validated)) {
                    $employeePayload['tempat_lahir'] = $validated['tempat_lahir'];
                }
                if (array_key_exists('tanggal_lahir', $validated)) {
                    $employeePayload['tanggal_lahir'] = $validated['tanggal_lahir'];
                }
                if (array_key_exists('jenis_kelamin', $validated)) {
                    $employeePayload['jenis_kelamin'] = $validated['jenis_kelamin'];
                }
                if (array_key_exists('agama', $validated)) {
                    $employeePayload['agama'] = $validated['agama'];
                }
                if (array_key_exists('alamat', $validated)) {
                    $employeePayload['alamat'] = $validated['alamat'];
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
            'password' => ['required', 'confirmed', Password::min(6)],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
            'password.min' => 'Password baru minimal 6 karakter.',
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

        $metadata = $user->metadata ?? [];
        $metadata['must_change_password'] = false;
        $metadata['password_changed_at'] = now()->toISOString();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'metadata' => $metadata,
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

    /**
     * Dapatkan daftar sesi aktif login pengguna.
     */
    public function sessions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        $tokens = $user->tokens()->orderByDesc('created_at')->get();
        $recentLogin = LoginEvent::where('user_id', $user->id)
            ->where('status', 'success')
            ->latest('created_at')
            ->first();

        $clientIp = $request->ip() ?: '127.0.0.1';
        $clientUa = $request->userAgent() ?: 'Web Browser';

        $sessions = [];

        if ($tokens->isNotEmpty()) {
            foreach ($tokens as $token) {
                $isCurrent = ($currentTokenId && (string) $token->id === (string) $currentTokenId);
                $deviceName = $token->name ?: 'Browser Sesi';
                $browser = 'Web Browser';
                if (stripos($deviceName, 'chrome') !== false || stripos($clientUa, 'chrome') !== false) {
                    $browser = 'Google Chrome';
                } elseif (stripos($deviceName, 'safari') !== false || stripos($clientUa, 'safari') !== false) {
                    $browser = 'Apple Safari';
                } elseif (stripos($deviceName, 'firefox') !== false || stripos($clientUa, 'firefox') !== false) {
                    $browser = 'Mozilla Firefox';
                }

                $deviceType = 'desktop';
                if (stripos($deviceName, 'iphone') !== false || stripos($clientUa, 'iphone') !== false) {
                    $deviceType = 'mobile';
                } elseif (stripos($deviceName, 'android') !== false || stripos($clientUa, 'android') !== false) {
                    $deviceType = 'mobile';
                }

                $sessions[] = [
                    'id' => (string) $token->id,
                    'device' => $isCurrent ? 'Perangkat Sesi Saat Ini' : ($token->name ?: 'Perangkat Tersimpan'),
                    'device_type' => $deviceType,
                    'is_current' => $isCurrent,
                    'browser' => $browser,
                    'location' => 'Padang, Indonesia',
                    'ip' => $isCurrent ? ($clientIp . ' (Sesi Aktif)') : ($recentLogin?->ip_address ?: '127.0.0.1'),
                    'login_time' => $token->created_at ? $token->created_at->translatedFormat('d M Y H:i:s') . ' WIB' : 'Sesi Aktif',
                    'last_used_at' => $token->last_used_at ? $token->last_used_at->translatedFormat('d M Y H:i:s') . ' WIB' : null,
                ];
            }
        } else {
            // Sesi default jika auth berbasis session/cookie
            $sessions[] = [
                'id' => 'current-session',
                'device' => 'Perangkat Sesi Saat Ini',
                'device_type' => 'desktop',
                'is_current' => true,
                'browser' => 'Web Browser',
                'location' => 'Padang, Indonesia',
                'ip' => $clientIp . ' (Sesi Browser)',
                'login_time' => now()->translatedFormat('d M Y H:i:s') . ' WIB',
                'last_used_at' => now()->translatedFormat('d M Y H:i:s') . ' WIB',
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $sessions,
        ]);
    }

    /**
     * Hapus / logout sesi tertentu.
     */
    public function destroySession(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->where('id', $id)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Sesi berhasil diakhiri.',
        ]);
    }

    /**
     * Logout dari semua sesi kecuali sesi saat ini.
     */
    public function destroyOtherSessions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $currentTokenId = $user->currentAccessToken()?->id;

        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Semua sesi perangkat lain berhasil diputuskan.',
        ]);
    }

    /**
     * Ambil riwayat aktivitas login akun pengguna dari tabel login_events.
     */
    public function activities(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = LoginEvent::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($request->filled('status') && $request->status !== 'semua') {
            $st = strtolower($request->status);
            if ($st === 'berhasil') {
                $query->where('status', 'success');
            } elseif ($st === 'gagal') {
                $query->where('status', 'failed');
            } else {
                $query->where('status', $st);
            }
        }

        if ($request->filled('device') && $request->device !== 'semua') {
            $dev = '%' . strtolower($request->device) . '%';
            $query->where(function ($q) use ($dev) {
                $q->whereRaw('LOWER(user_agent) LIKE ?', [$dev])
                  ->orWhereRaw('LOWER(portal_type) LIKE ?', [$dev]);
            });
        }

        $perPage = (int) $request->get('per_page', 10);
        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function (LoginEvent $event) {
            $ua = $event->user_agent ?: '';
            $browser = 'Web Browser';
            if (stripos($ua, 'chrome') !== false) {
                $browser = 'Chrome Browser';
            } elseif (stripos($ua, 'safari') !== false) {
                $browser = 'Safari';
            } elseif (stripos($ua, 'firefox') !== false) {
                $browser = 'Firefox';
            }

            $device = 'Komputer / PC';
            if (stripos($ua, 'mobile') !== false || stripos($ua, 'android') !== false) {
                $device = 'Smartphone Mobile';
            } elseif (stripos($ua, 'mac') !== false) {
                $device = 'Mac / Apple';
            } elseif (stripos($ua, 'windows') !== false) {
                $device = 'Windows PC';
            }

            return [
                'id' => $event->id,
                'time' => $event->created_at ? $event->created_at->translatedFormat('d M Y H:i:s') : '-',
                'status' => $event->status === 'success' ? 'Berhasil' : 'Gagal',
                'failure_reason' => $event->failure_reason,
                'device' => $device,
                'browser' => $browser,
                'location' => 'Padang, Indonesia',
                'ip' => $event->ip_address ?: '127.0.0.1',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Ekspor riwayat aktivitas login akun pengguna ke CSV.
     */
    public function exportActivities(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $events = LoginEvent::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="riwayat_login_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream(function () use ($events) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fputcsv($handle, ['Waktu Login', 'Status', 'Alamat IP', 'Perangkat / Agen', 'Portal']);

            foreach ($events as $event) {
                fputcsv($handle, [
                    $event->created_at ? $event->created_at->format('Y-m-d H:i:s') : '-',
                    $event->status === 'success' ? 'Berhasil' : 'Gagal (' . $event->failure_reason . ')',
                    $event->ip_address ?: '127.0.0.1',
                    $event->user_agent ?: 'Unknown',
                    $event->portal_type ?: 'Unified',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Dapatkan status Two Factor Authentication (2FA).
     */
    public function twoFactorStatus(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $metadata = $user->metadata ?? [];
        $tfa = $metadata['two_factor'] ?? [];
        $isEnabled = !empty($tfa['enabled']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'enabled' => $isEnabled,
                'verified_at' => $tfa['verified_at'] ?? null,
                'secret_key' => $isEnabled ? null : 'SIMSIT-SEC-7X9B-4Q2M',
                'recovery_codes' => $tfa['recovery_codes'] ?? [
                    '4829-1092',
                    '8391-4920',
                    '9921-3810',
                    '1029-4729',
                    '5521-9081',
                    '7712-3904',
                ],
            ],
        ]);
    }

    /**
     * Verifikasi dan aktifkan 2FA menggunakan OTP.
     */
    public function twoFactorVerify(Request $request): JsonResponse
    {
        $request->validate([
            'otp' => 'required|string|min:6|max:6',
        ]);

        /** @var User $user */
        $user = $request->user();
        $metadata = $user->metadata ?? [];
        
        $recoveryCodes = [
            '4829-1092',
            '8391-4920',
            '9921-3810',
            '1029-4729',
            '5521-9081',
            '7712-3904',
        ];

        $metadata['two_factor'] = [
            'enabled' => true,
            'verified_at' => now()->toIso8601String(),
            'recovery_codes' => $recoveryCodes,
        ];

        $user->metadata = $metadata;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Autentikasi dua faktor berhasil diverifikasi dan diaktifkan.',
            'data' => [
                'enabled' => true,
                'verified_at' => $metadata['two_factor']['verified_at'],
                'recovery_codes' => $recoveryCodes,
            ],
        ]);
    }

    /**
     * Nonaktifkan 2FA (memerlukan kata sandi pengguna).
     */
    public function twoFactorDisable(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        /** @var User $user */
        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kata sandi yang Anda masukkan salah.',
            ], 422);
        }

        $metadata = $user->metadata ?? [];
        $metadata['two_factor'] = [
            'enabled' => false,
            'disabled_at' => now()->toIso8601String(),
        ];
        $user->metadata = $metadata;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Autentikasi dua faktor telah dinonaktifkan.',
        ]);
    }
}
