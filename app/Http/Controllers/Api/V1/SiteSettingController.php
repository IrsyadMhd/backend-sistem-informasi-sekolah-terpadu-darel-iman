<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SiteSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $setting = SiteSetting::current()->toArray();

        // Keamanan: Cek otorisasi untuk field sensitif (mapbox_token)
        // Pengguna publik/unauthenticated atau role non-admin TIDAK boleh melihat mapbox_token
        $user = auth('sanctum')->user() ?? $request->user();
        if (!$user || !$user->can('sistem.pengaturan')) {
            unset($setting['mapbox_token']);
        }

        // 1. Ambil FAQ portal dari school_settings jika tersedia
        $faqSetting = DB::table('school_settings')->where('setting_key', 'portal_faqs')->first();
        if ($faqSetting && $faqSetting->setting_value !== null) {
            $parsedFaqs = is_string($faqSetting->setting_value) ? json_decode($faqSetting->setting_value, true) : $faqSetting->setting_value;
            if (is_array($parsedFaqs)) {
                $setting['custom_faqs'] = $parsedFaqs;
            }
        }

        // 2. Ambil Panduan portal dari school_settings jika tersedia
        $manualSetting = DB::table('school_settings')->where('setting_key', 'portal_manuals')->first();
        if ($manualSetting && $manualSetting->setting_value !== null) {
            $parsedManuals = is_string($manualSetting->setting_value) ? json_decode($manualSetting->setting_value, true) : $manualSetting->setting_value;
            if (is_array($parsedManuals)) {
                $setting['custom_manuals'] = $parsedManuals;
            }
        }

        // 3. Ambil Kustomisasi Label UI dari school_settings jika tersedia
        $labelsSetting = DB::table('school_settings')->where('setting_key', 'custom_labels')->first();
        if ($labelsSetting && $labelsSetting->setting_value !== null) {
            $parsedLabels = is_string($labelsSetting->setting_value) ? json_decode($labelsSetting->setting_value, true) : $labelsSetting->setting_value;
            if (is_array($parsedLabels)) {
                $setting['custom_labels'] = $parsedLabels;
            }
        }

        return response()->json(['data' => $setting]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user?->roles?->pluck('name')->map(fn($r) => strtolower(preg_replace('/[\s_-]+/', '', $r)))->toArray() ?? [];
        $isSuperAdminOrAdmin = in_array('superadmin', $roles) || in_array('admin', $roles) || in_array('administrator', $roles);
        if (!$isSuperAdminOrAdmin) {
            return response()->json([
                'message' => 'Akses ditolak. Pengaturan sistem hanya dapat diubah oleh Super Admin dan Admin.',
            ], 403);
        }

        $data = $request->validate([
            'application_name' => ['required', 'string', 'max:100'],
            'school_name' => ['required', 'string', 'max:150'],
            'logo_text' => ['required', 'string', 'max:20'],
            'footer_text' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'sk_pendirian' => ['nullable', 'string', 'max:100'],
            'motto' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'bsi_va_prefix' => ['nullable', 'string', 'max:20'],
            'bsi_va_format' => ['nullable', 'string', 'max:50'],
            'card_rules_student' => ['nullable', 'string'],
            'card_rules_employee' => ['nullable', 'string'],
            'card_footer_motto' => ['nullable', 'string', 'max:255'],
            'header_style' => ['required', Rule::in(['light', 'solid', 'transparent'])],
            'header_sticky' => ['required', 'boolean'],
            'sidebar_style' => ['required', Rule::in(['gradient', 'solid', 'light'])],
            'sidebar_position' => ['required', Rule::in(['left', 'right'])],
            'sidebar_collapsed' => ['required', 'boolean'],
            'template' => ['required', Rule::in(['modern', 'compact', 'comfortable'])],
            'sidebar_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'body_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'header_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,jpg,jpeg,webp,svg', 'max:512'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'mapbox_token' => ['nullable', 'string', 'max:255'],
            'custom_faqs' => ['nullable'],
            'custom_manuals' => ['nullable'],
            'custom_labels' => ['nullable'],
        ]);

        $customFaqsRaw = $data['custom_faqs'] ?? null;
        $customManualsRaw = $data['custom_manuals'] ?? null;
        $customLabelsRaw = $data['custom_labels'] ?? null;
        $responseData = [];

        DB::transaction(function () use ($request, $data, $customFaqsRaw, $customManualsRaw, $customLabelsRaw, &$responseData) {
            $setting = SiteSetting::current();
            $oldData = $setting->only(array_keys($data));

            $this->replaceFile($request, $setting, 'logo', 'logo_path', 'site/logo');
            $this->replaceFile($request, $setting, 'favicon', 'favicon_path', 'site/favicon');

            foreach (['logo', 'favicon'] as $type) {
                if ($request->boolean("remove_{$type}")) {
                    $column = "{$type}_path";
                    if ($setting->{$column}) {
                        Storage::disk('public')->delete($setting->{$column});
                    }
                    $setting->{$column} = null;
                }
            }

            unset($data['logo'], $data['favicon'], $data['remove_logo'], $data['remove_favicon'], $data['custom_faqs'], $data['custom_manuals'], $data['custom_labels']);

            $setting->fill($data)->save();

            // 1. Simpan custom_faqs jika disertakan
            $faqsSaved = null;
            if ($request->has('custom_faqs')) {
                $faqs = is_string($customFaqsRaw) ? json_decode($customFaqsRaw, true) : $customFaqsRaw;
                if (is_array($faqs)) {
                    if (count($faqs) > 100) {
                        abort(422, 'Jumlah FAQ portal maksimal 100 item.');
                    }
                    $this->saveSchoolSetting('portal_faqs', $faqs, 'Daftar FAQ Portal Resmi SIMSIT');
                    $faqsSaved = $faqs;
                }
            }

            // 2. Simpan custom_manuals jika disertakan
            $manualsSaved = null;
            if ($request->has('custom_manuals')) {
                $manuals = is_string($customManualsRaw) ? json_decode($customManualsRaw, true) : $customManualsRaw;
                if (is_array($manuals)) {
                    if (count($manuals) > 100) {
                        abort(422, 'Jumlah Buku Panduan portal maksimal 100 item.');
                    }
                    $this->saveSchoolSetting('portal_manuals', $manuals, 'Daftar Buku Panduan Pengguna SIMSIT');
                    $manualsSaved = $manuals;
                }
            }

            // 3. Simpan custom_labels jika disertakan
            $labelsSaved = null;
            if ($request->has('custom_labels')) {
                $labels = is_string($customLabelsRaw) ? json_decode($customLabelsRaw, true) : $customLabelsRaw;
                if (is_array($labels)) {
                    $this->saveSchoolSetting('custom_labels', $labels, 'Kustomisasi Label dan Terminologi Antarmuka Sistem');
                    $labelsSaved = $labels;
                }
            }

            // 4. Catat audit trail perubahan konfigurasi (token rahasia disanitasi)
            $this->recordSettingAudit($request, $oldData, $data, [
                'has_faqs_update' => $faqsSaved !== null,
                'faqs_count' => $faqsSaved !== null ? count($faqsSaved) : null,
                'has_manuals_update' => $manualsSaved !== null,
                'manuals_count' => $manualsSaved !== null ? count($manualsSaved) : null,
                'has_labels_update' => $labelsSaved !== null,
            ]);

            $result = $setting->fresh()->toArray();
            if ($faqsSaved !== null) {
                $result['custom_faqs'] = $faqsSaved;
            } else {
                $faqSetting = DB::table('school_settings')->where('setting_key', 'portal_faqs')->first();
                if ($faqSetting && $faqSetting->setting_value !== null) {
                    $result['custom_faqs'] = is_string($faqSetting->setting_value) ? json_decode($faqSetting->setting_value, true) : $faqSetting->setting_value;
                }
            }

            if ($manualsSaved !== null) {
                $result['custom_manuals'] = $manualsSaved;
            } else {
                $manualSetting = DB::table('school_settings')->where('setting_key', 'portal_manuals')->first();
                if ($manualSetting && $manualSetting->setting_value !== null) {
                    $result['custom_manuals'] = is_string($manualSetting->setting_value) ? json_decode($manualSetting->setting_value, true) : $manualSetting->setting_value;
                }
            }

            if ($labelsSaved !== null) {
                $result['custom_labels'] = $labelsSaved;
            } else {
                $labelsSetting = DB::table('school_settings')->where('setting_key', 'custom_labels')->first();
                if ($labelsSetting && $labelsSetting->setting_value !== null) {
                    $result['custom_labels'] = is_string($labelsSetting->setting_value) ? json_decode($labelsSetting->setting_value, true) : $labelsSetting->setting_value;
                }
            }

            $responseData = $result;
        });

        return response()->json([
            'message' => 'Pengaturan situs berhasil disimpan.',
            'data' => $responseData,
        ]);
    }

    private function replaceFile(Request $request, SiteSetting $setting, string $input, string $column, string $directory): void
    {
        if (! $request->hasFile($input)) {
            return;
        }

        if ($setting->{$column}) {
            Storage::disk('public')->delete($setting->{$column});
        }

        $setting->{$column} = $request->file($input)->store($directory, 'public');
    }

    private function saveSchoolSetting(string $key, array $value, ?string $description = null): void
    {
        $encoded = json_encode($value);
        $existing = DB::table('school_settings')->where('setting_key', $key)->first();
        if ($existing) {
            DB::table('school_settings')->where('id', $existing->id)->update([
                'setting_value' => $encoded,
                'description' => $description ?? $existing->description,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('school_settings')->insert([
                'id' => (string) Str::uuid(),
                'setting_key' => $key,
                'setting_value' => $encoded,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function recordSettingAudit(Request $request, array $oldValues, array $newValues, array $extra = []): void
    {
        $changes = [];
        $changedKeys = [];

        foreach ($newValues as $key => $newVal) {
            $oldVal = $oldValues[$key] ?? null;
            if ($oldVal != $newVal) {
                $changedKeys[] = $key;
                if ($key === 'mapbox_token') {
                    $changes[$key] = [
                        'old' => !empty($oldVal) ? '[REDACTED]' : null,
                        'new' => !empty($newVal) ? '[REDACTED]' : null,
                    ];
                } else {
                    $changes[$key] = [
                        'old' => $oldVal,
                        'new' => $newVal,
                    ];
                }
            }
        }

        $user = $request->user();
        $auditEntry = [
            'id' => (string) Str::uuid(),
            'action' => 'update_site_settings',
            'actor_id' => $user?->id,
            'actor_name' => $user?->name ?? 'Administrator',
            'actor_email' => $user?->email,
            'actor_role' => $user?->roles?->pluck('name')->first() ?? 'Admin',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'timestamp' => now()->toIso8601String(),
            'changed_fields' => $changedKeys,
            'changes' => $changes,
            'extra' => $extra,
        ];

        // 1. Structured log Laravel (token rahasia sudah disanitasi)
        Log::info('Pengaturan Situs diperbarui oleh ' . ($user?->name ?? 'Admin'), $auditEntry);

        // 2. Simpan ke capped log di school_settings (50 entri terbaru) dengan lockForUpdate
        $logRow = DB::table('school_settings')
            ->where('setting_key', 'settings_audit_log')
            ->lockForUpdate()
            ->first();

        $logs = [];
        if ($logRow && !empty($logRow->setting_value)) {
            $logs = is_string($logRow->setting_value) ? json_decode($logRow->setting_value, true) : $logRow->setting_value;
            if (!is_array($logs)) {
                $logs = [];
            }
        }
        array_unshift($logs, $auditEntry);
        $logs = array_slice($logs, 0, 50);
        $this->saveSchoolSetting('settings_audit_log', $logs, 'Histori Audit Perubahan Pengaturan Global');
    }
}
