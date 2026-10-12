<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileAppConfig;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MobileAppConfigController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $setting = MobileAppConfig::android();

        abort_unless($setting->is_published, 404);

        $etag = '"android-config-'.$setting->version.'"';
        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304)->header('ETag', $etag);
        }

        return response()->json(['data' => $this->payload($setting, $request)])
            ->header('Cache-Control', 'public, max-age=300')
            ->header('ETag', $etag);
    }

    public function adminShow(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload(MobileAppConfig::android(), $request)]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $setting = MobileAppConfig::android();
        $currentConfig = $setting->config ?? MobileAppConfig::defaults();

        $mergedConfig = $currentConfig;
        foreach ($validated as $key => $value) {
            if (is_array($value) && isset($mergedConfig[$key]) && is_array($mergedConfig[$key])) {
                if (array_is_list($value)) {
                    $mergedConfig[$key] = $value;
                } else {
                    $mergedConfig[$key] = array_replace_recursive($mergedConfig[$key], $value);
                }
            } else {
                $mergedConfig[$key] = $value;
            }
        }

        $setting->update([
            'config' => $mergedConfig,
            'version' => $setting->version + 1,
            'is_published' => true,
            'published_at' => now(),
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Konfigurasi tampilan Android berhasil dipublikasikan.',
            'data' => $this->payload($setting->fresh(), $request),
        ]);
    }

    public function uploadMedia(Request $request): JsonResponse
    {
        $type = $request->input('type');
        $isBanner = $type === 'login_banner';

        $rules = [
            'type' => ['required', Rule::in(['logo_header', 'logo_login', 'logo_footer', 'login_banner'])],
            'file' => [
                'required',
                'file',
                'image',
                $isBanner ? 'mimes:png,jpg,jpeg,webp' : 'mimes:png,jpg,jpeg,webp,svg',
                $isBanner ? 'max:4096' : 'max:2048',
            ],
        ];

        $request->validate($rules);

        $setting = MobileAppConfig::android();
        $config = $setting->config ?? MobileAppConfig::defaults();

        $oldPath = $config['media_paths'][$type] ?? null;

        // Store new asset in public disk under mobile-config/{type}
        $newPath = $request->file('file')->store("mobile-config/{$type}", 'public');
        $newUrl = url('/storage/'.$newPath);

        if ($isBanner) {
            $config['theme']['login_banner_url'] = $newUrl;
        } else {
            $config['branding']["{$type}_url"] = $newUrl;
        }

        if (!isset($config['media_paths']) || !is_array($config['media_paths'])) {
            $config['media_paths'] = [];
        }
        $config['media_paths'][$type] = $newPath;

        $setting->update([
            'config' => $config,
            'version' => $setting->version + 1,
            'is_published' => true,
            'published_at' => now(),
            'updated_by' => $request->user()?->id,
        ]);

        // Safely delete previous asset only if it belongs to mobile-config namespace
        if ($oldPath && $oldPath !== $newPath && str_starts_with($oldPath, 'mobile-config/') && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'message' => 'Asset mobile berhasil diunggah dan dipublikasikan.',
            'data' => $this->payload($setting->fresh(), $request),
        ]);
    }

    public function deleteMedia(Request $request, string $type): JsonResponse
    {
        if (!in_array($type, ['logo_header', 'logo_login', 'logo_footer', 'login_banner'])) {
            return response()->json(['message' => 'Tipe asset tidak valid.'], 422);
        }

        $setting = MobileAppConfig::android();
        $config = $setting->config ?? MobileAppConfig::defaults();

        $oldPath = $config['media_paths'][$type] ?? null;

        if ($type === 'login_banner') {
            $config['theme']['login_banner_url'] = null;
        } else {
            $config['branding']["{$type}_url"] = null;
        }
        if (isset($config['media_paths'][$type])) {
            unset($config['media_paths'][$type]);
        }

        $setting->update([
            'config' => $config,
            'version' => $setting->version + 1,
            'is_published' => true,
            'published_at' => now(),
            'updated_by' => $request->user()?->id,
        ]);

        if ($oldPath && str_starts_with($oldPath, 'mobile-config/') && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'message' => 'Asset mobile berhasil dihapus dan dikembalikan ke fallback.',
            'data' => $this->payload($setting->fresh(), $request),
        ]);
    }

    private function payload(MobileAppConfig $setting, Request $request): array
    {
        $defaults = MobileAppConfig::defaults();
        $config = $setting->config ?? [];

        $theme = array_replace_recursive($defaults['theme'], $config['theme'] ?? []);
        $branding = array_replace_recursive($defaults['branding'], $config['branding'] ?? []);
        $navigation = array_replace_recursive($defaults['navigation'], $config['navigation'] ?? []);
        if (isset($config['navigation']['items']) && is_array($config['navigation']['items'])) {
            $navigation['items'] = $config['navigation']['items'];
        }

        $homeLayout = array_replace_recursive($defaults['home_layout'], $config['home_layout'] ?? []);
        if (isset($config['home_layout']['sections']) && is_array($config['home_layout']['sections'])) {
            $homeLayout['sections'] = $config['home_layout']['sections'];
        }

        $roleHomeLayouts = $defaults['role_home_layouts'];
        if (isset($config['role_home_layouts']) && is_array($config['role_home_layouts'])) {
            foreach ($config['role_home_layouts'] as $role => $layout) {
                if (isset($layout['sections']) && is_array($layout['sections'])) {
                    $roleHomeLayouts[$role] = $layout;
                }
            }
        }

        $features = array_replace_recursive($defaults['features'], $config['features'] ?? []);
        $system = array_replace_recursive($defaults['system'], $config['system'] ?? []);

        $siteSetting = SiteSetting::current();
        $system['mapbox_token'] = $siteSetting->mapbox_token;
        $fallbackLogoUrl = $siteSetting->logo_url
            ? $request->getSchemeAndHttpHost().$siteSetting->logo_url
            : null;

        $branding['has_dedicated_logo_header'] = !empty($config['branding']['logo_header_url']);
        $branding['has_dedicated_logo_login'] = !empty($config['branding']['logo_login_url']);
        $branding['has_dedicated_logo_footer'] = !empty($config['branding']['logo_footer_url']);
        $branding['fallback_logo_url'] = $fallbackLogoUrl;

        $theme['has_dedicated_login_banner'] = !empty($config['theme']['login_banner_url']);

        $branding['logo_url'] = !empty($branding['logo_url']) ? $branding['logo_url'] : $fallbackLogoUrl;
        $branding['logo_header_url'] = !empty($branding['logo_header_url']) ? $branding['logo_header_url'] : $branding['logo_url'];
        $branding['logo_login_url'] = !empty($branding['logo_login_url']) ? $branding['logo_login_url'] : $branding['logo_url'];
        $branding['logo_footer_url'] = !empty($branding['logo_footer_url']) ? $branding['logo_footer_url'] : $branding['logo_url'];

        return [
            'platform' => 'android',
            'version' => $setting->version,
            'theme' => $theme,
            'branding' => $branding,
            'navigation' => $navigation,
            'home_layout' => $homeLayout,
            'role_home_layouts' => $roleHomeLayouts,
            'features' => $features,
            'system' => $system,
            'mapbox_token' => $siteSetting->mapbox_token,
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ];
    }

    private function rules(): array
    {
        $hex = ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $hexNullable = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $urlNullable = ['nullable', 'url', 'max:500'];

        return [
            'theme' => ['sometimes', 'required', 'array'],
            'theme.primary_color' => $hex,
            'theme.secondary_color' => $hex,
            'theme.accent_color' => $hexNullable,
            'theme.background_color' => $hex,
            'theme.background_gradient_enabled' => ['sometimes', 'required', 'boolean'],
            'theme.background_gradient_start' => $hex,
            'theme.background_gradient_end' => $hex,
            'theme.background_gradient_direction' => ['sometimes', 'required', Rule::in(['vertical', 'horizontal', 'diagonal'])],
            'theme.surface_color' => $hex,
            'theme.text_color' => $hex,
            'theme.muted_text_color' => $hex,
            'theme.font_family' => ['sometimes', 'required', Rule::in(['system', 'Poppins', 'Nunito'])],
            'theme.font_scale' => ['sometimes', 'required', Rule::in(['compact', 'normal', 'large'])],
            'theme.button_radius' => ['sometimes', 'required', 'integer', 'between:0,30'],
            'theme.card_radius' => ['sometimes', 'required', 'integer', 'between:0,32'],
            'theme.welcome_text' => ['nullable', 'string', 'max:255'],
            'theme.login_banner_url' => $urlNullable,
            'branding' => ['sometimes', 'required', 'array'],
            'branding.app_name' => ['sometimes', 'required', 'string', 'max:100'],
            'branding.school_name' => ['sometimes', 'required', 'string', 'max:150'],
            'branding.splash_background_color' => $hex,
            'branding.logo_url' => $urlNullable,
            'branding.logo_header_url' => $urlNullable,
            'branding.logo_login_url' => $urlNullable,
            'branding.logo_footer_url' => $urlNullable,
            'navigation' => ['sometimes', 'required', 'array'],
            'navigation.style' => ['sometimes', 'required', Rule::in(['bottom_tabs'])],
            'navigation.show_labels' => ['sometimes', 'required', 'boolean'],
            'navigation.items' => ['sometimes', 'required', 'array', 'min:1', 'max:8'],
            'navigation.items.*.key' => ['required_with:navigation.items', Rule::in(['home', 'notifications', 'qr', 'profile', 'more'])],
            'navigation.items.*.label' => ['required_with:navigation.items', 'string', 'max:20'],
            'navigation.items.*.icon' => ['required_with:navigation.items', 'string', 'max:50'],
            'navigation.items.*.enabled' => ['required_with:navigation.items', 'boolean'],
            'navigation.items.*.order' => ['required_with:navigation.items', 'integer', 'between:1,20'],
            'home_layout' => ['sometimes', 'required', 'array'],
            'home_layout.template' => ['sometimes', 'required', Rule::in(['dashboard_default', 'dashboard_compact'])],
            'home_layout.sections' => ['sometimes', 'required', 'array', 'min:1', 'max:8'],
            'home_layout.sections.*.type' => ['required_with:home_layout.sections', Rule::in(['announcements', 'quick_menu', 'metrics', 'schedule'])],
            'home_layout.sections.*.enabled' => ['required_with:home_layout.sections', 'boolean'],
            'home_layout.sections.*.order' => ['required_with:home_layout.sections', 'integer', 'between:1,20'],
            'role_home_layouts' => ['sometimes', 'required', 'array:super_admin,foundation,principal,teacher,parent,student,staff'],
            'role_home_layouts.*' => ['sometimes', 'required', 'array'],
            'role_home_layouts.*.template' => ['sometimes', 'required', Rule::in(['dashboard_default', 'dashboard_compact'])],
            'role_home_layouts.*.sections' => ['sometimes', 'required', 'array', 'min:1', 'max:8'],
            'role_home_layouts.*.sections.*.type' => ['required_with:role_home_layouts.*.sections', Rule::in(['announcements', 'quick_menu', 'metrics', 'schedule'])],
            'role_home_layouts.*.sections.*.enabled' => ['required_with:role_home_layouts.*.sections', 'boolean'],
            'role_home_layouts.*.sections.*.order' => ['required_with:role_home_layouts.*.sections', 'integer', 'between:1,20'],
            'features' => ['sometimes', 'nullable', 'array'],
            'features.qr_login' => ['nullable', 'boolean'],
            'features.qr_attendance' => ['nullable', 'boolean'],
            'features.chat' => ['nullable', 'boolean'],
            'features.notifications' => ['nullable', 'boolean'],
            'features.tahfizh' => ['nullable', 'boolean'],
            'features.mutabaah' => ['nullable', 'boolean'],
            'features.cbt' => ['nullable', 'boolean'],
            'features.school_info' => ['nullable', 'boolean'],
            'system' => ['sometimes', 'nullable', 'array'],
            'system.min_app_version' => [
                'nullable',
                'regex:/^\d+\.\d+(\.\d+)?$/',
                function ($attribute, $value, $fail) {
                    $latest = request()->input('system.latest_app_version');
                    if ($latest && version_compare($value, $latest, '>')) {
                        $fail('Versi minimum aplikasi tidak boleh lebih tinggi dari versi rilis terbaru.');
                    }
                },
            ],
            'system.latest_app_version' => ['nullable', 'regex:/^\d+\.\d+(\.\d+)?$/'],
            'system.maintenance_mode' => ['nullable', 'boolean'],
            'system.maintenance_message' => [
                'nullable',
                'string',
                'max:500',
                'required_if:system.maintenance_mode,true',
            ],
            'system.force_update' => ['nullable', 'boolean'],
            'system.update_url' => [
                'nullable',
                'url',
                'max:500',
                'required_if:system.force_update,true',
            ],
        ];
    }
}
