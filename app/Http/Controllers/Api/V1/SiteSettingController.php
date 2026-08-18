<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SiteSettingController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(['data' => SiteSetting::current()]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'application_name' => ['required', 'string', 'max:100'],
            'school_name' => ['required', 'string', 'max:150'],
            'logo_text' => ['required', 'string', 'max:20'],
            'footer_text' => ['nullable', 'string', 'max:255'],
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
        ]);

        $setting = SiteSetting::current();
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

        unset($data['logo'], $data['favicon'], $data['remove_logo'], $data['remove_favicon']);
        $setting->fill($data)->save();

        return response()->json([
            'message' => 'Pengaturan situs berhasil disimpan.',
            'data' => $setting->fresh(),
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
}
