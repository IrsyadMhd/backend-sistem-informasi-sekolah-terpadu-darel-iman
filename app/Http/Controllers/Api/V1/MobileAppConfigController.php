<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileAppConfig;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $setting->update([
            'config' => $validated,
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

    private function payload(MobileAppConfig $setting, Request $request): array
    {
        $config = $setting->config;
        $siteSetting = SiteSetting::current();
        $config['branding']['logo_url'] = $siteSetting->logo_url
            ? $request->getSchemeAndHttpHost().$siteSetting->logo_url
            : null;

        return array_merge($config, [
            'platform' => 'android',
            'version' => $setting->version,
            'updated_at' => $setting->updated_at?->toIso8601String(),
        ]);
    }

    private function rules(): array
    {
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'theme' => ['required', 'array'],
            'theme.primary_color' => $hex,
            'theme.secondary_color' => $hex,
            'theme.background_color' => $hex,
            'theme.background_gradient_enabled' => ['required', 'boolean'],
            'theme.background_gradient_start' => $hex,
            'theme.background_gradient_end' => $hex,
            'theme.background_gradient_direction' => ['required', Rule::in(['vertical', 'horizontal', 'diagonal'])],
            'theme.surface_color' => $hex,
            'theme.text_color' => $hex,
            'theme.muted_text_color' => $hex,
            'theme.font_family' => ['required', Rule::in(['system', 'Poppins', 'Nunito'])],
            'theme.font_scale' => ['required', Rule::in(['compact', 'normal', 'large'])],
            'theme.button_radius' => ['required', 'integer', 'between:0,30'],
            'theme.card_radius' => ['required', 'integer', 'between:0,32'],
            'branding' => ['required', 'array'],
            'branding.app_name' => ['required', 'string', 'max:100'],
            'branding.school_name' => ['required', 'string', 'max:150'],
            'branding.splash_background_color' => $hex,
            'navigation' => ['required', 'array'],
            'navigation.style' => ['required', Rule::in(['bottom_tabs'])],
            'navigation.show_labels' => ['required', 'boolean'],
            'navigation.items' => ['required', 'array', 'min:1', 'max:8'],
            'navigation.items.*.key' => ['required', Rule::in(['home', 'notifications', 'qr', 'profile', 'more'])],
            'navigation.items.*.label' => ['required', 'string', 'max:20'],
            'navigation.items.*.icon' => ['required', 'string', 'max:50'],
            'navigation.items.*.enabled' => ['required', 'boolean'],
            'navigation.items.*.order' => ['required', 'integer', 'between:1,20'],
            'home_layout' => ['required', 'array'],
            'home_layout.template' => ['required', Rule::in(['dashboard_default', 'dashboard_compact'])],
            'home_layout.sections' => ['required', 'array', 'min:1', 'max:8'],
            'home_layout.sections.*.type' => ['required', Rule::in(['announcements', 'quick_menu', 'metrics', 'schedule'])],
            'home_layout.sections.*.enabled' => ['required', 'boolean'],
            'home_layout.sections.*.order' => ['required', 'integer', 'between:1,20'],
            'role_home_layouts' => ['required', 'array:super_admin,foundation,principal,teacher,parent,student,staff'],
            'role_home_layouts.*' => ['required', 'array'],
            'role_home_layouts.*.template' => ['required', Rule::in(['dashboard_default', 'dashboard_compact'])],
            'role_home_layouts.*.sections' => ['required', 'array', 'min:1', 'max:8'],
            'role_home_layouts.*.sections.*.type' => ['required', Rule::in(['announcements', 'quick_menu', 'metrics', 'schedule'])],
            'role_home_layouts.*.sections.*.enabled' => ['required', 'boolean'],
            'role_home_layouts.*.sections.*.order' => ['required', 'integer', 'between:1,20'],
        ];
    }
}
