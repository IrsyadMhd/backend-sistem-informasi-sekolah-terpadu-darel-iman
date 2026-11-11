<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppMenu;
use App\Models\AppModule;
use App\Models\MobileAppConfig;
use App\Services\NavigationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminNavigationController extends Controller
{
    /**
     * Get all modules, menus, roles, permissions, and available icon/route registries.
     */
    public function index(Request $request): JsonResponse
    {
        $platform = $request->query('platform'); // optional filter: 'web', 'mobile', or null (all)

        $query = AppModule::query()->with(['menus' => function ($q) use ($platform) {
            if ($platform) {
                $q->forPlatform($platform);
            }
            $q->orderBy('sort_order', 'asc');
        }])->orderBy('sort_order', 'asc');

        if ($platform) {
            $query->forPlatform($platform);
        }

        $modules = $query->get();

        $roles = Role::query()->orderBy('name')->pluck('name')->all();
        $permissions = Permission::query()->orderBy('name')->pluck('name')->all();

        return response()->json([
            'success' => true,
            'data' => [
                'modules' => $modules,
                'roles' => $roles,
                'permissions' => $permissions,
                'registries' => [
                    'mobile_screens' => $this->mobileScreensRegistry(),
                    'web_routes' => $this->webRoutesRegistry(),
                    'lucide_icons' => $this->lucideIconsRegistry(),
                    'material_icons' => $this->materialIconsRegistry(),
                    'canonical_roles' => $this->canonicalRolesRegistry(),
                ],
            ],
        ]);
    }

    /**
     * Get standalone registries for icons, mobile screens, and canonical roles.
     */
    public function iconRegistries(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'lucide_icons' => $this->lucideIconsRegistry(),
                'material_icons' => $this->materialIconsRegistry(),
                'mobile_screens' => $this->mobileScreensRegistry(),
                'web_routes' => $this->webRoutesRegistry(),
                'canonical_roles' => $this->canonicalRolesRegistry(),
            ],
        ]);
    }

    /**
     * Store a new module.
     */
    public function storeModule(Request $request): JsonResponse
    {
        if ($request->missing('id') && $request->filled('name')) {
            $request->merge(['id' => Str::slug($request->input('name')) . '-' . Str::random(4)]);
        }

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:50', 'unique:app_modules,id'],
            'name' => ['required', 'string', 'max:100'],
            'name_mobile' => ['nullable', 'string', 'max:100'],
            'icon' => ['required', 'string', 'max:50'],
            'icon_mobile' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'platform' => ['required', 'in:web,mobile,both'],
            'is_active' => ['boolean'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? (AppModule::max('sort_order') + 1);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $module = AppModule::create($validated);

        NavigationService::bumpCacheVersion();
        $this->autoSyncMobileMenus();

        return response()->json([
            'success' => true,
            'message' => 'Modul navigasi berhasil ditambahkan.',
            'data' => $module->load('menus'),
        ], 201);
    }

    /**
     * Update an existing module.
     */
    public function updateModule(Request $request, string $id): JsonResponse
    {
        $module = AppModule::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'name_mobile' => ['nullable', 'string', 'max:100'],
            'icon' => ['sometimes', 'required', 'string', 'max:50'],
            'icon_mobile' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'platform' => ['sometimes', 'required', 'in:web,mobile,both'],
            'is_active' => ['boolean'],
        ]);

        $module->update($validated);

        NavigationService::bumpCacheVersion();
        $this->autoSyncMobileMenus();

        return response()->json([
            'success' => true,
            'message' => 'Modul navigasi berhasil diperbarui.',
            'data' => $module->fresh()->load('menus'),
        ]);
    }

    /**
     * Delete a module and its menus.
     */
    public function destroyModule(string $id): JsonResponse
    {
        $module = AppModule::findOrFail($id);
        $module->delete();

        NavigationService::bumpCacheVersion();
        $this->autoSyncMobileMenus();

        return response()->json([
            'success' => true,
            'message' => 'Modul navigasi dan menu di dalamnya berhasil dihapus.',
        ]);
    }

    /**
     * Store a new menu item.
     */
    public function storeMenu(Request $request): JsonResponse
    {
        if ($request->missing('path') && $request->filled('route')) {
            $request->merge(['path' => $request->input('route')]);
        }
        if ($request->missing('id') && $request->filled('name')) {
            $request->merge(['id' => \Illuminate\Support\Str::slug($request->input('name')) . '-' . \Illuminate\Support\Str::random(5)]);
        }

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:80', 'unique:app_menus,id'],
            'module_id' => ['required', 'string', 'exists:app_modules,id'],
            'name' => ['required', 'string', 'max:100'],
            'name_mobile' => ['nullable', 'string', 'max:100'],
            'path' => ['required', 'string', 'max:255'],
            'icon' => ['required', 'string', 'max:50'],
            'icon_mobile' => ['nullable', 'string', 'max:50'],
            'required_permissions' => ['nullable', 'array'],
            'required_permissions.*' => ['string'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'platform' => ['required', 'in:web,mobile,both'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? (AppMenu::where('module_id', $validated['module_id'])->max('sort_order') + 1);
        $validated['is_active'] = $validated['is_active'] ?? true;

        if (empty($validated['name_mobile'])) {
            $validated['name_mobile'] = $validated['name'];
        }

        $menu = AppMenu::create($validated);

        NavigationService::bumpCacheVersion();
        $this->autoSyncMobileMenus();

        return response()->json([
            'success' => true,
            'message' => 'Menu navigasi berhasil ditambahkan.',
            'data' => $menu,
        ], 201);
    }

    /**
     * Update an existing menu item.
     */
    public function updateMenu(Request $request, string $id): JsonResponse
    {
        $menu = AppMenu::findOrFail($id);

        $validated = $request->validate([
            'module_id' => ['sometimes', 'required', 'string', 'exists:app_modules,id'],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'name_mobile' => ['nullable', 'string', 'max:100'],
            'path' => ['sometimes', 'required', 'string', 'max:255'],
            'icon' => ['sometimes', 'required', 'string', 'max:50'],
            'icon_mobile' => ['nullable', 'string', 'max:50'],
            'required_permissions' => ['nullable', 'array'],
            'required_permissions.*' => ['string'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'platform' => ['sometimes', 'required', 'in:web,mobile,both'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $menu->update($validated);

        NavigationService::bumpCacheVersion();
        $this->autoSyncMobileMenus();

        return response()->json([
            'success' => true,
            'message' => 'Menu navigasi berhasil diperbarui.',
            'data' => $menu->fresh(),
        ]);
    }

    /**
     * Delete a menu item.
     */
    public function destroyMenu(string $id): JsonResponse
    {
        $menu = AppMenu::findOrFail($id);
        $menu->delete();

        NavigationService::bumpCacheVersion();
        $this->autoSyncMobileMenus();

        return response()->json([
            'success' => true,
            'message' => 'Menu navigasi berhasil dihapus.',
        ]);
    }

    /**
     * Reorder modules or menus in batch.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:modules,menus'],
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'string'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        if ($validated['type'] === 'modules') {
            foreach ($validated['items'] as $item) {
                AppModule::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        } else {
            foreach ($validated['items'] as $item) {
                AppMenu::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        }

        NavigationService::bumpCacheVersion();
        $this->autoSyncMobileMenus();

        return response()->json([
            'success' => true,
            'message' => 'Urutan navigasi berhasil diperbarui.',
        ]);
    }

    /**
     * Synchronize all active mobile menus into mobile_app_configs table for instant mobile access.
     */
    public function syncMobileConfig(): JsonResponse
    {
        NavigationService::bumpCacheVersion();
        $synced = $this->autoSyncMobileMenus();

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi menu mobile berhasil disinkronkan ke seluruh aplikasi Android.',
            'data' => $synced,
        ]);
    }

    /**
     * Reset and seed the default web & mobile menus.
     */
    public function resetDefaults(): JsonResponse
    {
        \Artisan::call('db:seed', ['--class' => 'AppNavigationSeeder', '--force' => true]);
        $this->seedDefaultMobileMenus();
        NavigationService::bumpCacheVersion();
        $this->autoSyncMobileMenus();

        return response()->json([
            'success' => true,
            'message' => 'Menu navigasi bawaan Web dan Mobile berhasil dipulihkan.',
        ]);
    }

    /**
     * Internal helper to build role_menus and store in MobileAppConfig.
     */
    public function autoSyncMobileMenus(): array
    {
        $mobileMenus = AppMenu::query()
            ->active()
            ->whereHas('module', fn ($q) => $q->active())
            ->where(function ($q) {
                $q->where('platform', 'mobile')
                  ->orWhere(function ($sub) {
                      $sub->where('platform', 'both')
                          ->where('path', 'not like', '/%');
                  });
            })
            ->orderBy('sort_order', 'asc')
            ->get();

        $canonicalRoles = ['student', 'parent', 'teacher', 'principal', 'tu', 'foundation', 'divisi', 'musyrif', 'alumni'];
        $roleMenus = [];
        $seenDestinations = [];

        foreach ($canonicalRoles as $r) {
            $roleMenus[$r] = [];
            $seenDestinations[$r] = [];
        }

        foreach ($mobileMenus as $menu) {
            $roles = $menu->target_roles;
            if (empty($roles)) {
                // Empty target_roles means available for all canonical mobile roles
                $roles = $canonicalRoles;
            }

            $item = [
                'id' => $menu->id,
                'label' => $menu->name_mobile ?: $menu->name,
                'label_web' => $menu->name,
                'icon' => $menu->icon_mobile ?: $this->fallbackMaterialIcon($menu->icon),
                'icon_web' => $menu->icon,
                'color' => $menu->metadata['color'] ?? '#059669',
                'bg' => $menu->metadata['bg'] ?? '#ECFDF5',
                'route' => $menu->path,
                'tab' => $menu->metadata['tab'] ?? null,
                'badge' => $menu->metadata['badge'] ?? null,
                'order' => $menu->sort_order,
                'enabled' => (bool) $menu->is_active,
                'is_active' => (bool) $menu->is_active,
            ];

            $destKey = ($item['route'] ?? '') . '__' . ($item['tab'] ?? '');

            $pushRoleItem = function (string $roleTarget) use (&$roleMenus, &$seenDestinations, $item, $destKey) {
                if (isset($seenDestinations[$roleTarget][$destKey])) {
                    return; // Hindari menu ganda yang mengarah ke halaman yang sama
                }
                $seenDestinations[$roleTarget][$destKey] = true;
                $roleMenus[$roleTarget][] = $item;
            };

            foreach ($roles as $roleKey) {
                $rk = strtolower(trim($roleKey));
                // Normalization
                if (in_array($rk, ['siswa', 'student'])) {
                    $pushRoleItem('student');
                } elseif (in_array($rk, ['orang tua', 'orang_tua', 'orangtua', 'wali murid', 'parent'])) {
                    $pushRoleItem('parent');
                } elseif (in_array($rk, ['guru', 'teacher', 'guru tahfizh', 'wali kelas'])) {
                    $pushRoleItem('teacher');
                } elseif (in_array($rk, ['kepala sekolah', 'kepsek', 'principal'])) {
                    // Strict Role Scoping: Kepala Sekolah wewenang pengawasan eksekutif, bukan operasional staf TU, musyrif, bank soal guru, atau portal siswa
                    $unauthorizedForPrincipal = [
                        'dash-tu',
                        'portal-musyrif-workspace',
                        'mob-bank-soal',
                        'lms-bank-soal',
                        'portal-guru-workspace',
                        'dash-tahfizh',
                        'portal-sivitas',
                        'mob-perizinan-santri',
                        'mob-tugas-lms',
                        'lms-penugasan',
                        'mob-buku-hubung',
                        'mob-tagihan-spp',
                    ];
                    if (! in_array($item['id'], $unauthorizedForPrincipal, true)) {
                        $pushRoleItem('principal');
                    }
                } elseif (in_array($rk, ['tata usaha', 'tu', 'staff', 'staf', 'operator'])) {
                    $pushRoleItem('tu');
                } elseif (in_array($rk, ['divisi pendidikan', 'divisi_pendidikan', 'kepala bidang pendidikan', 'divisi'])) {
                    $pushRoleItem('divisi');
                } elseif (in_array($rk, ['yayasan', 'ketua yayasan', 'super admin', 'super_admin', 'superadmin', 'admin', 'foundation'])) {
                    $pushRoleItem('foundation');
                } elseif (in_array($rk, ['musyrif', 'musyrifah', 'asrama', 'pembimbing'])) {
                    $pushRoleItem('musyrif');
                } elseif (in_array($rk, ['alumni'])) {
                    $pushRoleItem('alumni');
                } elseif (isset($roleMenus[$rk])) {
                    $pushRoleItem($rk);
                }
            }
        }

        // Store into MobileAppConfig android record
        $setting = MobileAppConfig::android();
        $config = $setting->config ?? MobileAppConfig::defaults();
        $config['role_menus'] = $roleMenus;

        $setting->update([
            'config' => $config,
            'version' => $setting->version + 1,
            'is_published' => true,
            'published_at' => now(),
        ]);

        return $roleMenus;
    }

    /**
     * Seed default mobile-specific menus into app_menus.
     */
    public function seedDefaultMobileMenus(): void
    {
        // 1. Ensure Mobile-specific parent modules exist
        $mobileModule = AppModule::updateOrCreate(
            ['id' => 'mobile-services'],
            [
                'name' => 'Layanan Mobile Siswa & Sivitas',
                'name_mobile' => 'Layanan Utama',
                'icon' => 'Smartphone',
                'icon_mobile' => 'cellphone',
                'description' => 'Katalog menu operasional aplikasi Android siswa, wali murid, dan guru',
                'sort_order' => 99,
                'platform' => 'mobile',
                'is_active' => true,
            ]
        );

        // Predefined default mobile items
        $defaultMobileItems = [
            // Siswa & Orang Tua items
            [
                'id' => 'mob-jadwal-pelajaran',
                'name' => 'Jadwal Pelajaran',
                'name_mobile' => 'Jadwal Pelajaran',
                'path' => 'Jadwal',
                'icon' => 'CalendarClock',
                'icon_mobile' => 'calendar-clock',
                'target_roles' => ['student', 'parent', 'teacher', 'principal', 'tu'],
                'metadata' => ['color' => '#0D9488', 'bg' => '#ECFDF5'],
                'sort_order' => 1,
            ],
            [
                'id' => 'mob-tugas-lms',
                'name' => 'Tugas Siswa (LMS)',
                'name_mobile' => 'Tugas LMS',
                'path' => 'Tugas',
                'icon' => 'ClipboardList',
                'icon_mobile' => 'clipboard-text-outline',
                'target_roles' => ['student', 'parent', 'teacher', 'principal', 'tu'],
                'metadata' => ['color' => '#10B981', 'bg' => '#ECFDF5'],
                'sort_order' => 2,
            ],
            [
                'id' => 'mob-materi-belajar',
                'name' => 'Bahan & Modul Ajar',
                'name_mobile' => 'Materi Belajar',
                'path' => 'Materi',
                'icon' => 'BookOpen',
                'icon_mobile' => 'book-open-page-variant',
                'target_roles' => ['student', 'parent', 'teacher', 'principal', 'tu'],
                'metadata' => ['color' => '#0284C7', 'bg' => '#EFF6FF'],
                'sort_order' => 3,
            ],
            [
                'id' => 'mob-nilai-rapor',
                'name' => 'Penilaian & Rapor',
                'name_mobile' => 'Penilaian & Rapor',
                'path' => 'Nilai',
                'icon' => 'Award',
                'icon_mobile' => 'chart-box-outline',
                'target_roles' => ['student', 'parent', 'teacher', 'principal', 'tu'],
                'metadata' => ['color' => '#8B5CF6', 'bg' => '#F5F3FF'],
                'sort_order' => 4,
            ],
            [
                'id' => 'mob-tahfizh-quran',
                'name' => 'Tahfizh Al-Qur\'an',
                'name_mobile' => 'Tahfizh Al-Qur\'an',
                'path' => 'Tahfizh',
                'icon' => 'BookMarked',
                'icon_mobile' => 'book-marker',
                'target_roles' => ['student', 'parent', 'teacher', 'principal', 'tu', 'foundation', 'divisi', 'musyrif'],
                'metadata' => ['color' => '#059669', 'bg' => '#ECFDF5'],
                'sort_order' => 5,
            ],
            [
                'id' => 'mob-mutabaah-yaumiyyah',
                'name' => 'Mutaba\'ah Yaumiyyah',
                'name_mobile' => 'Mutaba\'ah Yaumiyyah',
                'path' => 'Mutabaah',
                'icon' => 'Sparkles',
                'icon_mobile' => 'clipboard-check-outline',
                'target_roles' => ['student', 'parent', 'teacher', 'principal', 'foundation', 'divisi', 'musyrif'],
                'metadata' => ['color' => '#D97706', 'bg' => '#FFFBEB'],
                'sort_order' => 6,
            ],
            [
                'id' => 'mob-tata-tertib',
                'name' => 'Tata Tertib & Poin',
                'name_mobile' => 'Tata Tertib & Poin',
                'path' => '__OPEN_DISCIPLINE_MODAL__',
                'icon' => 'ShieldCheck',
                'icon_mobile' => 'shield-alert-outline',
                'target_roles' => ['student', 'parent', 'teacher', 'musyrif', 'principal', 'tu', 'foundation', 'divisi'],
                'metadata' => ['color' => '#E11D48', 'bg' => '#FFF1F2'],
                'sort_order' => 7,
            ],
            [
                'id' => 'mob-buku-hubung',
                'name' => 'Buku Hubung Santri',
                'name_mobile' => 'Buku Hubung',
                'path' => 'Komentar',
                'icon' => 'MessageCircle',
                'icon_mobile' => 'notebook-edit-outline',
                'target_roles' => ['student', 'parent', 'tu'],
                'metadata' => ['color' => '#E11D48', 'bg' => '#FFF1F2'],
                'sort_order' => 7,
            ],
            [
                'id' => 'mob-bank-soal',
                'name' => 'Bank Soal Latihan',
                'name_mobile' => 'Bank Soal',
                'path' => 'BankSoal',
                'icon' => 'Database',
                'icon_mobile' => 'database-outline',
                'target_roles' => ['student', 'parent', 'teacher', 'principal', 'tu'],
                'metadata' => ['color' => '#0D9488', 'bg' => '#E6FFFA'],
                'sort_order' => 8,
            ],
            [
                'id' => 'mob-presensi-siswa',
                'name' => 'Presensi Siswa',
                'name_mobile' => 'Presensi Siswa',
                'path' => 'Absensi',
                'icon' => 'CalendarCheck',
                'icon_mobile' => 'account-check-outline',
                'target_roles' => ['student', 'parent', 'principal', 'tu', 'foundation'],
                'metadata' => ['color' => '#059669', 'bg' => '#ECFDF5'],
                'sort_order' => 9,
            ],
            [
                'id' => 'mob-perizinan-santri',
                'name' => 'Perizinan Santri',
                'name_mobile' => 'Perizinan Santri',
                'path' => 'Perizinan',
                'icon' => 'FileText',
                'icon_mobile' => 'file-document-edit-outline',
                'target_roles' => ['student', 'parent', 'principal', 'musyrif'],
                'metadata' => ['color' => '#EF4444', 'bg' => '#FEF2F2', 'tab' => 'permissions'],
                'sort_order' => 10,
            ],
            [
                'id' => 'mob-tagihan-spp',
                'name' => 'Tagihan & SPP',
                'name_mobile' => 'Tagihan SPP',
                'path' => 'Tagihan',
                'icon' => 'CreditCard',
                'icon_mobile' => 'cash-multiple',
                'target_roles' => ['parent', 'tu'],
                'metadata' => ['color' => '#059669', 'bg' => '#ECFDF5'],
                'sort_order' => 11,
            ],
            [
                'id' => 'mob-chat-guru',
                'name' => 'Percakapan Interaktif (Chat)',
                'name_mobile' => 'Chat Guru',
                'path' => 'Chat',
                'icon' => 'MessageCircle',
                'icon_mobile' => 'chat-processing-outline',
                'target_roles' => ['parent', 'teacher', 'principal', 'tu'],
                'metadata' => ['color' => '#2563EB', 'bg' => '#EFF6FF'],
                'sort_order' => 12,
            ],
            [
                'id' => 'mob-ujian-cbt',
                'name' => 'Ujian Online CBT',
                'name_mobile' => 'Ujian CBT Online',
                'path' => 'CbtExams',
                'icon' => 'Laptop',
                'icon_mobile' => 'laptop',
                'target_roles' => ['student', 'parent', 'teacher', 'tu'],
                'metadata' => ['color' => '#2563EB', 'bg' => '#EFF6FF'],
                'sort_order' => 13,
            ],
            [
                'id' => 'mob-kisi-kisi',
                'name' => 'Kisi-kisi Asesmen',
                'name_mobile' => 'Kisi-kisi Asesmen',
                'path' => 'KisiKisi',
                'icon' => 'FileQuestion',
                'icon_mobile' => 'file-question',
                'target_roles' => ['student', 'parent', 'teacher', 'tu'],
                'metadata' => ['color' => '#EF4444', 'bg' => '#FEF2F2'],
                'sort_order' => 14,
            ],
            [
                'id' => 'mob-kalender-akademik',
                'name' => 'Kalender Akademik',
                'name_mobile' => 'Kalender Akademik',
                'path' => 'Kalender',
                'icon' => 'CalendarDays',
                'icon_mobile' => 'calendar-month-outline',
                'target_roles' => ['student', 'parent', 'teacher', 'principal', 'tu', 'foundation', 'divisi', 'musyrif', 'alumni'],
                'metadata' => ['color' => '#0284C7', 'bg' => '#F0F9FF'],
                'sort_order' => 15,
            ],
            [
                'id' => 'mob-informasi-sekolah',
                'name' => 'Informasi & Warta Sekolah',
                'name_mobile' => 'Informasi Sekolah',
                'path' => 'Informasi',
                'icon' => 'Megaphone',
                'icon_mobile' => 'bullhorn-outline',
                'target_roles' => ['student', 'parent', 'teacher', 'principal', 'tu', 'foundation', 'divisi', 'musyrif', 'alumni'],
                'metadata' => ['color' => '#E11D48', 'bg' => '#FFF1F2'],
                'sort_order' => 16,
            ],
            [
                'id' => 'mob-quran-digital',
                'name' => 'Al-Qur\'an Digital',
                'name_mobile' => 'Al-Qur\'an Digital',
                'path' => 'Quran',
                'icon' => 'BookOpen',
                'icon_mobile' => 'book-open-variant',
                'target_roles' => ['student', 'parent', 'teacher', 'principal', 'tu', 'foundation', 'divisi', 'musyrif', 'alumni'],
                'metadata' => ['color' => '#059669', 'bg' => '#F0FDF4'],
                'sort_order' => 17,
            ],
            [
                'id' => 'mob-doa-dzikir',
                'name' => 'Doa & Dzikir Harian',
                'name_mobile' => 'Doa & Dzikir Harian',
                'path' => 'DoaDzikir',
                'icon' => 'HeartHandshake',
                'icon_mobile' => 'hands-pray',
                'target_roles' => ['student', 'parent', 'teacher', 'principal', 'tu', 'foundation', 'divisi', 'musyrif', 'alumni'],
                'metadata' => ['color' => '#F97316', 'bg' => '#FFF7ED'],
                'sort_order' => 18,
            ],
            // Staff / Guru specific items
            [
                'id' => 'mob-presensi-mandiri',
                'name' => 'Presensi Mandiri Pegawai',
                'name_mobile' => 'Presensi Mandiri',
                'path' => 'PresensiMandiriStaff',
                'icon' => 'UserCheck',
                'icon_mobile' => 'account-check-outline',
                'target_roles' => ['teacher', 'principal', 'tu', 'foundation', 'divisi', 'musyrif'],
                'metadata' => ['color' => '#10B981', 'bg' => '#ECFDF5'],
                'sort_order' => 19,
            ],
            [
                'id' => 'mob-absensi-gerbang',
                'name' => 'Absensi Gerbang Terminal',
                'name_mobile' => 'Absensi Gerbang',
                'path' => 'AbsensiGerbangTerminal',
                'icon' => 'DoorOpen',
                'icon_mobile' => 'door-open',
                'target_roles' => ['tu', 'principal'],
                'metadata' => ['color' => '#0D9488', 'bg' => '#E6FFFA'],
                'sort_order' => 20,
            ],
            [
                'id' => 'mob-data-master',
                'name' => 'Pusat Data Master',
                'name_mobile' => 'Data Master',
                'path' => 'Data',
                'icon' => 'Database',
                'icon_mobile' => 'database-outline',
                'target_roles' => ['tu', 'principal', 'foundation', 'divisi'],
                'metadata' => ['color' => '#059669', 'bg' => '#E6F4EA'],
                'sort_order' => 21,
            ],
            [
                'id' => 'mob-kelola-alumni',
                'name' => 'Kelola Alumni & Mutasi',
                'name_mobile' => 'Kelola Alumni & Mutasi',
                'path' => 'MutasiSiswa',
                'icon' => 'GraduationCap',
                'icon_mobile' => 'school',
                'target_roles' => ['principal', 'tu'],
                'metadata' => ['color' => '#7C3AED', 'bg' => '#F5F3FF'],
                'sort_order' => 22,
            ],
            [
                'id' => 'mob-pantau-kelas',
                'name' => 'Pantau Kelas & Rombel',
                'name_mobile' => 'Pantau Rombel',
                'path' => 'PantauKelas',
                'icon' => 'Compass',
                'icon_mobile' => 'google-classroom',
                'target_roles' => ['principal', 'divisi'],
                'metadata' => ['color' => '#EA580C', 'bg' => '#FFFBEB'],
                'sort_order' => 23,
            ],
            [
                'id' => 'mob-buku-induk',
                'name' => 'Buku Induk Siswa',
                'name_mobile' => 'Buku Induk Siswa',
                'path' => 'MasterSiswa',
                'icon' => 'BookOpen',
                'icon_mobile' => 'account-school-outline',
                'target_roles' => ['principal', 'tu', 'foundation', 'divisi'],
                'metadata' => ['color' => '#2563EB', 'bg' => '#EFF6FF'],
                'sort_order' => 24,
            ],
            [
                'id' => 'mob-supervisi-kbm',
                'name' => 'Supervisi KBM',
                'name_mobile' => 'Supervisi KBM',
                'path' => 'SupervisiKbm',
                'icon' => 'Compass',
                'icon_mobile' => 'human-male-board',
                'target_roles' => ['principal', 'foundation', 'divisi'],
                'metadata' => ['color' => '#0F766E', 'bg' => '#F0FDFA'],
                'sort_order' => 25,
            ],
        ];

        foreach ($defaultMobileItems as $it) {
            AppMenu::updateOrCreate(
                ['id' => $it['id']],
                [
                    'module_id' => 'mobile-services',
                    'name' => $it['name'],
                    'name_mobile' => $it['name_mobile'],
                    'path' => $it['path'],
                    'icon' => $it['icon'],
                    'icon_mobile' => $it['icon_mobile'],
                    'target_roles' => $it['target_roles'],
                    'metadata' => $it['metadata'],
                    'sort_order' => $it['sort_order'],
                    'platform' => 'mobile',
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Map web Lucide icons to Material Community Icons fallback.
     */
    protected function fallbackMaterialIcon(string $lucideIcon): string
    {
        $map = [
            'LayoutDashboard' => 'view-dashboard-outline',
            'Database' => 'database-outline',
            'BookOpen' => 'book-open-page-variant',
            'FileText' => 'file-document-outline',
            'Settings' => 'cog-outline',
            'CalendarCheck' => 'calendar-check-outline',
            'CalendarClock' => 'calendar-clock',
            'Calendar' => 'calendar-month',
            'CalendarDays' => 'calendar-month-outline',
            'Users' => 'account-group-outline',
            'User' => 'account-outline',
            'UserCheck' => 'account-check-outline',
            'Building2' => 'office-building',
            'ShieldCheck' => 'shield-check-outline',
            'CreditCard' => 'credit-card-outline',
            'Award' => 'trophy-award',
            'Sparkles' => 'sparkles',
            'MessageCircle' => 'message-text-outline',
            'Megaphone' => 'bullhorn-outline',
            'Laptop' => 'laptop',
            'FileQuestion' => 'file-question',
            'GraduationCap' => 'school-outline',
            'HeartHandshake' => 'hands-pray',
            'ClipboardList' => 'clipboard-text-outline',
            'DoorOpen' => 'door-open',
            'Compass' => 'compass-outline',
        ];

        return $map[$lucideIcon] ?? 'circle-outline';
    }

    /**
     * Common registries.
     */
    protected function mobileScreensRegistry(): array
    {
        return [
            ['id' => 'Jadwal', 'label' => 'Jadwal Pelajaran KBM'],
            ['id' => 'Tugas', 'label' => 'Tugas LMS & Pekerjaan Rumah'],
            ['id' => 'Materi', 'label' => 'Bahan Ajar & Modul Kurikulum'],
            ['id' => 'Nilai', 'label' => 'Penilaian Harian, PTS, PAS & Rapor'],
            ['id' => 'Tahfizh', 'label' => 'Setoran Hafalan Al-Qur\'an & Mutabaah Juz'],
            ['id' => 'Mutabaah', 'label' => 'Mutaba\'ah Yaumiyyah (Ibadah Harian)'],
            ['id' => 'Komentar', 'label' => 'Buku Hubung Guru & Catatan Santri'],
            ['id' => 'BankSoal', 'label' => 'Bank Soal Latihan Mandiri'],
            ['id' => 'Absensi', 'label' => 'Presensi Kehadiran Siswa'],
            ['id' => 'Perizinan', 'label' => 'Perizinan Santri / Asrama'],
            ['id' => 'Tagihan', 'label' => 'Tagihan SPP & Pembayaran'],
            ['id' => 'Chat', 'label' => 'Percakapan Interaktif (Chat Guru/Ortu)'],
            ['id' => 'CbtExams', 'label' => 'Pelaksanaan Ujian CBT Online'],
            ['id' => 'KisiKisi', 'label' => 'Kisi-kisi Ujian CBT'],
            ['id' => 'Kalender', 'label' => 'Kalender Akademik Sekolah'],
            ['id' => 'Informasi', 'label' => 'Informasi & Pengumuman Sekolah'],
            ['id' => 'Quran', 'label' => 'Al-Qur\'an Digital 30 Juz'],
            ['id' => 'DoaDzikir', 'label' => 'Doa & Dzikir Harian'],
            ['id' => 'PresensiMandiriStaff', 'label' => 'Presensi Mandiri Pegawai & Guru'],
            ['id' => '__OPEN_DISCIPLINE_MODAL__', 'label' => 'Tata Tertib & Poin'],
            ['id' => 'QR Code', 'label' => 'Scanner QR Presensi / ID Card'],
            ['id' => 'Data', 'label' => 'Pusat Data Master (Siswa/Pegawai/Kelas)'],
            ['id' => 'MutasiSiswa', 'label' => 'Kelola Alumni & Mutasi Siswa'],
            ['id' => 'PantauKelas', 'label' => 'Monitoring Guru Mengajar (Pantau Kelas)'],
            ['id' => 'AbsensiGerbangTerminal', 'label' => 'Terminal Absensi Gerbang'],
            ['id' => 'PresensiKbmPengganti', 'label' => 'Presensi KBM & Pengganti'],
            ['id' => 'RekapPresensiSiswa', 'label' => 'Rekap Presensi Siswa'],
            ['id' => 'RekapPresensiGuru', 'label' => 'Rekap Presensi Guru & Pegawai'],
            ['id' => 'MasterSiswa', 'label' => 'Buku Induk Siswa'],
            ['id' => 'SupervisiKbm', 'label' => 'Supervisi KBM'],
            ['id' => 'DivisiWorkspace', 'label' => 'Ruang Kerja Divisi Pendidikan'],
            ['id' => 'PrincipalDashboard', 'label' => 'Dashboard Kepala Sekolah Mobile'],
            ['id' => 'Bantuan', 'label' => 'Pusat Bantuan & Panduan FAQ'],
            ['id' => 'Profil', 'label' => 'Profil Pengguna & Akun'],
            ['id' => 'Lainnya', 'label' => 'Layar Menu Tambahan (Lainnya)'],
        ];
    }

    protected function webRoutesRegistry(): array
    {
        return [
            ['id' => '/dashboard', 'label' => 'Dashboard Utama / Super Admin'],
            ['id' => '/dashboard/yayasan', 'label' => 'Dashboard Yayasan'],
            ['id' => '/dashboard/kepala-sekolah', 'label' => 'Dashboard Kepala Sekolah'],
            ['id' => '/dashboard/divisi-pendidikan', 'label' => 'Dashboard Divisi Pendidikan'],
            ['id' => '/dashboard/tata-tertib', 'label' => 'Tata Tertib & Poin'],
            ['id' => '/portal-guru/workspace', 'label' => 'Ruang Kerja Guru'],
            ['id' => '/dashboard/tata-usaha', 'label' => 'Dashboard Tata Usaha'],
            ['id' => '/dashboard/master/unit-pendidikan', 'label' => 'Master Unit Pendidikan'],
            ['id' => '/dashboard/master/pegawai', 'label' => 'Master Pegawai & Guru'],
            ['id' => '/dashboard/master/siswa', 'label' => 'Master Siswa'],
            ['id' => '/dashboard/master/kelas', 'label' => 'Master Kelas & Rombel'],
            ['id' => '/dashboard/master/tahun-ajaran', 'label' => 'Master Tahun Ajaran'],
            ['id' => '/dashboard/master/mata-pelajaran', 'label' => 'Master Mata Pelajaran'],
            ['id' => '/dashboard/master-hak-akses', 'label' => 'Master Hak Akses & Akun'],
            ['id' => '/dashboard/akademik/kurikulum', 'label' => 'Kurikulum'],
            ['id' => '/dashboard/akademik/jadwal', 'label' => 'Jadwal Pelajaran'],
            ['id' => '/dashboard/lms/modul-ajar', 'label' => 'LMS Modul Ajar'],
            ['id' => '/dashboard/lms/materi', 'label' => 'LMS Materi Pembelajaran'],
            ['id' => '/dashboard/lms/penugasan', 'label' => 'LMS Penugasan'],
            ['id' => '/dashboard/lms/ujian', 'label' => 'LMS Ujian CBT'],
            ['id' => '/dashboard/lms/bank-soal', 'label' => 'LMS Bank Soal'],
            ['id' => '/dashboard/lms/penilaian', 'label' => 'LMS Penilaian'],
            ['id' => '/dashboard/lms/rapor', 'label' => 'LMS Rapor'],
            ['id' => '/dashboard/tahfizh', 'label' => 'Tahfizh Al-Qur\'an'],
            ['id' => '/dashboard/mutabaah', 'label' => 'Mutaba\'ah Yaumiyyah'],
            ['id' => '/dashboard/absensi', 'label' => 'Presensi Siswa'],
            ['id' => '/dashboard/rekap-absensi-gerbang', 'label' => 'Presensi Gerbang'],
            ['id' => '/dashboard/keuangan/tagihan', 'label' => 'Keuangan Tagihan SPP'],
            ['id' => '/dashboard/pengaturan', 'label' => 'Pengaturan Umum Sekolah'],
            ['id' => '/pengaturan-mobile-api', 'label' => 'Pengaturan Mobile App Android'],
            ['id' => '/dashboard/pengaturan/menu-navigasi', 'label' => 'Manajemen Menu & Navigasi'],
        ];
    }

    protected function lucideIconsRegistry(): array
    {
        return [
            'LayoutDashboard', 'Database', 'BookOpen', 'FileText', 'Settings',
            'CalendarCheck', 'CalendarClock', 'Calendar', 'CalendarDays',
            'Users', 'User', 'UserCheck', 'Building2', 'ShieldCheck', 'CreditCard',
            'Layers', 'Sparkles', 'Award', 'Laptop', 'FileQuestion',
            'BookMarked', 'Megaphone', 'MessageCircle', 'HeartHandshake',
            'GraduationCap', 'DoorOpen', 'ClipboardList', 'Compass', 'HelpCircle',
        ];
    }

    protected function materialIconsRegistry(): array
    {
        return [
            'view-dashboard-outline', 'calendar-clock', 'clipboard-text-outline',
            'book-open-page-variant', 'chart-box-outline', 'book-marker',
            'clipboard-check-outline', 'notebook-edit-outline', 'database-outline',
            'account-check-outline', 'file-document-edit-outline', 'cash-multiple',
            'chat-processing-outline', 'laptop', 'file-question',
            'calendar-month-outline', 'bullhorn-outline', 'book-open-variant',
            'hands-pray', 'account-circle-outline', 'school', 'qrcode-scan',
            'menu', 'account-group', 'clock-time-four', 'book-multiple',
            'chart-box', 'door-open', 'google-classroom',
        ];
    }

    protected function canonicalRolesRegistry(): array
    {
        return [
            ['id' => 'student', 'label' => 'Siswa / Santri'],
            ['id' => 'parent', 'label' => 'Orang Tua / Wali Murid'],
            ['id' => 'teacher', 'label' => 'Guru / Pengajar'],
            ['id' => 'principal', 'label' => 'Kepala Sekolah'],
            ['id' => 'tu', 'label' => 'Tata Usaha / Operator'],
            ['id' => 'divisi', 'label' => 'Divisi Pendidikan'],
            ['id' => 'foundation', 'label' => 'Pengurus Yayasan / Pimpinan'],
            ['id' => 'musyrif', 'label' => 'Musyrif / Pembimbing Asrama'],
            ['id' => 'alumni', 'label' => 'Alumni'],
            ['id' => 'super_admin', 'label' => 'Super Admin / Admin'],
        ];
    }
}
