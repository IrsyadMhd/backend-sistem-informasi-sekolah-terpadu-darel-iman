<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAppConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_android_can_read_published_visual_configuration_without_login(): void
    {
        $this->getJson('/api/mobile/config')
            ->assertOk()
            ->assertHeader('ETag', '"android-config-1"')
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.theme.primary_color', '#0E5C44')
            ->assertJsonPath('data.theme.background_gradient_enabled', true)
            ->assertJsonPath('data.theme.background_gradient_direction', 'diagonal')
            ->assertJsonPath('data.navigation.style', 'bottom_tabs')
            ->assertJsonPath('data.role_home_layouts.teacher.sections.0.type', 'schedule')
            ->assertJsonPath('data.role_home_layouts.parent.sections.0.type', 'announcements')
            ->assertJsonCount(4, 'data.home_layout.sections');
    }

    public function test_android_admin_configuration_requires_login(): void
    {
        $this->getJson('/api/admin/mobile-config')->assertUnauthorized();
        $this->putJson('/api/admin/mobile-config', [])->assertUnauthorized();
    }

    public function test_android_logo_is_resolved_from_site_setting_database(): void
    {
        $siteSetting = SiteSetting::current();
        $siteSetting->update(['logo_path' => 'site/logo/logo-sekolah.png']);

        $this->getJson('/api/mobile/config')
            ->assertOk()
            ->assertJsonPath(
                'data.branding.logo_url',
                url('/storage/site/logo/logo-sekolah.png'),
            );
    }
}
