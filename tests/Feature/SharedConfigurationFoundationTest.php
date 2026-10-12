<?php

namespace Tests\Feature;

use App\Models\EducationUnit;
use App\Models\MutabaahAgendaItem;
use App\Models\MutabaahCategory;
use App\Models\MutabaahInputRule;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SharedConfigurationFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Admin');

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('Guru');
    }

    /** 1. GET /api/site-settings membaca custom_faqs dan custom_manuals dari school_settings */
    public function test_site_settings_show_includes_faqs_and_manuals(): void
    {
        DB::table('school_settings')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'setting_key' => 'portal_faqs',
            'setting_value' => json_encode([
                ['id' => 'faq-test-1', 'question' => 'Apa itu SIMSIT?', 'answer' => 'Sistem Sekolah.'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('school_settings')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'setting_key' => 'portal_manuals',
            'setting_value' => json_encode([
                ['id' => 'man-test-1', 'title' => 'Panduan Guru', 'version' => 'v1.0'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/site-settings');

        $response->assertStatus(200)
            ->assertJsonPath('data.custom_faqs.0.id', 'faq-test-1')
            ->assertJsonPath('data.custom_manuals.0.title', 'Panduan Guru');
    }

    /** 2. Request publik & non-admin TIDAK menerima mapbox_token, tetapi menerima branding */
    public function test_public_and_regular_user_do_not_receive_mapbox_token(): void
    {
        SiteSetting::current()->update([
            'application_name' => 'SIMSIT Dar el-Iman',
            'school_name' => 'Yayasan Dar el-Iman',
            'mapbox_token' => 'pk.eyJ1Ijoic2VjcmV0LW1hcGJveC10b2tlbiJ9.test',
        ]);

        // A. Request publik (unauthenticated)
        $publicRes = $this->getJson('/api/site-settings');
        $publicRes->assertStatus(200)
            ->assertJsonPath('data.application_name', 'SIMSIT Dar el-Iman')
            ->assertJsonPath('data.school_name', 'Yayasan Dar el-Iman')
            ->assertJsonMissingPath('data.mapbox_token');

        // B. Request user tanpa permission sistem.pengaturan (Guru)
        $userRes = $this->actingAs($this->regularUser, 'sanctum')->getJson('/api/site-settings');
        $userRes->assertStatus(200)
            ->assertJsonPath('data.application_name', 'SIMSIT Dar el-Iman')
            ->assertJsonMissingPath('data.mapbox_token');

        // C. Super Admin yang memiliki hak akses sistem.pengaturan BISA melihat mapbox_token
        $adminRes = $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/site-settings');
        $adminRes->assertStatus(200)
            ->assertJsonPath('data.mapbox_token', 'pk.eyJ1Ijoic2VjcmV0LW1hcGJveC10b2tlbiJ9.test');
    }

    /** 3. POST /api/site-settings menyimpan FAQ/manuals ke school_settings dan mencatat audit trail */
    public function test_site_settings_update_persists_faqs_manuals_and_records_audit_trail(): void
    {
        $payload = [
            'application_name' => 'SIMSIT Dar el-Iman Terpadu',
            'school_name' => 'Yayasan Dar el-Iman Padang',
            'logo_text' => 'YDE',
            'header_style' => 'light',
            'header_sticky' => true,
            'sidebar_style' => 'gradient',
            'sidebar_position' => 'left',
            'sidebar_collapsed' => false,
            'template' => 'modern',
            'sidebar_color' => '#0E5C44',
            'sidebar_accent_color' => '#3FBF75',
            'body_color' => '#F7F9FC',
            'header_color' => '#FFFFFF',
            'custom_faqs' => [
                ['id' => 'faq-new-1', 'question' => 'Bagaimana cara izin?', 'answer' => 'Melalui menu permohonan izin.'],
            ],
            'custom_manuals' => [
                ['id' => 'man-new-1', 'title' => 'Buku Panduan Wali Murid', 'version' => 'v2.0'],
            ],
        ];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/site-settings', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.application_name', 'SIMSIT Dar el-Iman Terpadu')
            ->assertJsonPath('data.custom_faqs.0.id', 'faq-new-1')
            ->assertJsonPath('data.custom_manuals.0.id', 'man-new-1');

        // Pastikan tersimpan di tabel school_settings
        $faqRow = DB::table('school_settings')->where('setting_key', 'portal_faqs')->first();
        $this->assertNotNull($faqRow);
        $this->assertStringContainsString('faq-new-1', $faqRow->setting_value);

        $auditRow = DB::table('school_settings')->where('setting_key', 'settings_audit_log')->first();
        $this->assertNotNull($auditRow);
        $logs = json_decode($auditRow->setting_value, true);
        $this->assertIsArray($logs);
        $this->assertEquals('update_site_settings', $logs[0]['action']);
        $this->assertEquals($this->superAdmin->id, $logs[0]['actor_id']);
        $this->assertArrayHasKey('changes', $logs[0]);
    }

    /** 4. Menyimpan array kosong [] mempertahankan status kosong dan tidak mengembalikan default */
    public function test_empty_faqs_and_manuals_are_persisted_as_empty_arrays(): void
    {
        $payload = [
            'application_name' => 'SIMSIT Dar el-Iman',
            'school_name' => 'Yayasan Dar el-Iman',
            'logo_text' => 'YDE',
            'header_style' => 'light',
            'header_sticky' => true,
            'sidebar_style' => 'solid',
            'sidebar_position' => 'left',
            'sidebar_collapsed' => false,
            'template' => 'modern',
            'sidebar_color' => '#0E5C44',
            'sidebar_accent_color' => '#3FBF75',
            'body_color' => '#F7F9FC',
            'header_color' => '#FFFFFF',
            'custom_faqs' => [],
            'custom_manuals' => [],
        ];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/site-settings', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.custom_faqs', [])
            ->assertJsonPath('data.custom_manuals', []);

        // Verifikasi di DB school_settings
        $faqRow = DB::table('school_settings')->where('setting_key', 'portal_faqs')->first();
        $this->assertEquals('[]', $faqRow->setting_value);

        // Subsequent show request juga mengembalikan []
        $showRes = $this->getJson('/api/site-settings');
        $showRes->assertStatus(200)
            ->assertJsonPath('data.custom_faqs', [])
            ->assertJsonPath('data.custom_manuals', []);
    }

    /** 5. Audit trail mencatat nilai perubahan lama dan baru dengan redaksi token */
    public function test_audit_trail_records_old_and_new_values_with_sanitization(): void
    {
        SiteSetting::current()->update([
            'school_name' => 'Nama Sekolah Lama',
            'mapbox_token' => 'pk.token_rahasia_lama',
        ]);

        $payload = [
            'application_name' => 'SIMSIT Dar el-Iman',
            'school_name' => 'Nama Sekolah Baru',
            'logo_text' => 'YDE',
            'header_style' => 'light',
            'header_sticky' => true,
            'sidebar_style' => 'solid',
            'sidebar_position' => 'left',
            'sidebar_collapsed' => false,
            'template' => 'modern',
            'sidebar_color' => '#0E5C44',
            'sidebar_accent_color' => '#3FBF75',
            'body_color' => '#F7F9FC',
            'header_color' => '#FFFFFF',
            'mapbox_token' => 'pk.token_rahasia_baru',
        ];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/site-settings', $payload);

        $response->assertStatus(200);

        $auditRow = DB::table('school_settings')->where('setting_key', 'settings_audit_log')->first();
        $this->assertNotNull($auditRow);
        $logs = json_decode($auditRow->setting_value, true);

        // Nilai lama dan baru untuk field biasa tercatat akurat
        $this->assertEquals('Nama Sekolah Lama', $logs[0]['changes']['school_name']['old']);
        $this->assertEquals('Nama Sekolah Baru', $logs[0]['changes']['school_name']['new']);

        // Nilai sensitif (mapbox_token) disanitasi menjadi [REDACTED]
        $this->assertEquals('[REDACTED]', $logs[0]['changes']['mapbox_token']['old']);
        $this->assertEquals('[REDACTED]', $logs[0]['changes']['mapbox_token']['new']);

        // Token asli sama sekali tidak bocor ke string JSON audit log
        $this->assertStringNotContainsString('pk.token_rahasia_lama', $auditRow->setting_value);
        $this->assertStringNotContainsString('pk.token_rahasia_baru', $auditRow->setting_value);
    }

    /** 6. User tanpa permission sistem.pengaturan ditolak (HTTP 403) */
    public function test_unauthorized_user_cannot_update_site_settings(): void
    {
        $payload = [
            'application_name' => 'Hacked Name',
            'school_name' => 'Hacked School',
            'logo_text' => 'HACK',
            'header_style' => 'light',
            'header_sticky' => true,
            'sidebar_style' => 'solid',
            'sidebar_position' => 'left',
            'sidebar_collapsed' => false,
            'template' => 'modern',
            'sidebar_color' => '#0E5C44',
            'sidebar_accent_color' => '#3FBF75',
            'body_color' => '#F7F9FC',
            'header_color' => '#FFFFFF',
        ];

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/api/site-settings', $payload);

        $response->assertStatus(403);
    }

    /** 7. MutabaahInputRule menginvalidasi cache saat dibuat, dihapus, dipulihkan, dan bulk update */
    public function test_mutabaah_input_rule_invalidates_cache_on_lifecycle(): void
    {
        $catId = (string) \Illuminate\Support\Str::uuid();
        DB::table('mutabaah_categories')->insert([
            'id' => $catId,
            'code' => 'IBADAH_TEST',
            'name' => 'Ibadah Test',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $agendaId = (string) \Illuminate\Support\Str::uuid();
        DB::table('mutabaah_agenda_items')->insert([
            'id' => $agendaId,
            'category_id' => $catId,
            'code' => 'SHOLAT_SUBUH_TEST',
            'name' => 'Shalat Subuh Test',
            'input_type' => 'yes_no',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simulasikan cache aktif
        Cache::put('mutabaah_input_rules_fullday', 'old_cached_rules', 3600);
        Cache::put('mutabaah_input_rules_boarding', 'old_cached_rules', 3600);
        $this->assertTrue(Cache::has('mutabaah_input_rules_fullday'));

        // 1. Buat rule baru
        $rule = MutabaahInputRule::create([
            'agenda_item_id' => $agendaId,
            'program_type' => 'fullday',
            'input_source' => 'parent',
            'location' => 'home',
            'is_active' => true,
            'created_by' => $this->superAdmin->id,
            'updated_by' => $this->superAdmin->id,
        ]);

        // Cache harus otomatis terhapus
        $this->assertFalse(Cache::has('mutabaah_input_rules_fullday'));
        $this->assertFalse(Cache::has('mutabaah_input_rules_boarding'));

        // 2. Isi cache lagi, lalu hapus rule
        Cache::put('mutabaah_input_rules_fullday', 'cached_again', 3600);
        $this->assertTrue(Cache::has('mutabaah_input_rules_fullday'));

        $rule->delete();

        // Cache harus otomatis terhapus kembali
        $this->assertFalse(Cache::has('mutabaah_input_rules_fullday'));

        // 3. Test restore rule juga menginvalidasi cache
        Cache::put('mutabaah_input_rules_fullday', 'cached_for_restore', 3600);
        $this->assertTrue(Cache::has('mutabaah_input_rules_fullday'));
        $rule->restore();
        $this->assertFalse(Cache::has('mutabaah_input_rules_fullday'));

        // 4. Test bulk update juga menginvalidasi cache
        Cache::put('mutabaah_input_rules_fullday', 'cached_for_bulk', 3600);
        $this->assertTrue(Cache::has('mutabaah_input_rules_fullday'));
        MutabaahInputRule::bulkUpdateRules([$rule->id], ['requires_verification' => true]);
        $this->assertFalse(Cache::has('mutabaah_input_rules_fullday'));

        // 5. Test transaksi rollback TIDAK menginvalidasi cache
        Cache::put('mutabaah_input_rules_fullday', 'must_stay_cached', 3600);
        DB::beginTransaction();
        $rule->update(['priority' => 99]);
        DB::rollBack();
        $this->assertTrue(Cache::has('mutabaah_input_rules_fullday'));
    }
}
