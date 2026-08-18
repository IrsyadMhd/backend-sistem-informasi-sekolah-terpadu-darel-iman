<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MutabaahPortalService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MutabaahPortalAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skema minimal hanya dibuat bila belum ada (khusus DB testing yang
        // kosong / SQLite in-memory). Pada PostgreSQL, tabel sudah dibuat oleh
        // migrasi sehingga dipakai apa adanya.
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('parents')) {
            Schema::create('parents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('full_name');
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('education_units')) {
            Schema::create('education_units', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
            });
        }
        if (! Schema::hasTable('classes')) {
            Schema::create('classes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->nullableUuidMorphs('dummy');
            });
        }
        if (! Schema::hasTable('students')) {
            Schema::create('students', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->uuid('parent_id')->nullable();
                $table->uuid('class_id')->nullable();
                $table->uuid('unit_id')->nullable();
                $table->string('nis');
                $table->string('full_name');
                $table->string('gender');
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->softDeletes();
            });
        }
        if (! Schema::hasTable('student_parents')) {
            Schema::create('student_parents', function (Blueprint $table) {
                $table->uuid('student_id');
                $table->uuid('parent_id');
            });
        }
    }

    public function test_parent_can_only_resolve_linked_child(): void
    {
        [$user, $other] = [
            User::factory()->create(['id' => '019fb1a6-d432-72ff-8b76-ade4f1394e60']),
            User::factory()->create(['id' => '019fb1a6-d432-72ff-8b76-ade4f1394e61']),
        ];
        DB::table('parents')->insert([
            ['id' => '019fb1a6-d432-72ff-8b76-ade4f1394e70', 'user_id' => $user->id, 'full_name' => 'Wali A'],
            ['id' => '019fb1a6-d432-72ff-8b76-ade4f1394e71', 'user_id' => $other->id, 'full_name' => 'Wali B'],
        ]);
        DB::table('students')->insert([
            ['id' => '019fb1a6-d432-72ff-8b76-ade4f1394e80', 'parent_id' => '019fb1a6-d432-72ff-8b76-ade4f1394e70', 'nis' => '001', 'full_name' => 'Anak A', 'gender' => 'male', 'is_active' => true],
            ['id' => '019fb1a6-d432-72ff-8b76-ade4f1394e81', 'parent_id' => '019fb1a6-d432-72ff-8b76-ade4f1394e71', 'nis' => '002', 'full_name' => 'Anak B', 'gender' => 'female', 'is_active' => true],
        ]);
        $service = app(MutabaahPortalService::class);

        $this->assertSame('Anak A', $service->parentStudent($user, '019fb1a6-d432-72ff-8b76-ade4f1394e80')->full_name);
        $this->expectException(ModelNotFoundException::class);
        $service->parentStudent($user, '019fb1a6-d432-72ff-8b76-ade4f1394e81');
    }

    public function test_student_identity_is_resolved_from_authenticated_user(): void
    {
        $user = User::factory()->create(['id' => '019fb1a6-d432-72ff-8b76-ade4f1394e62']);
        DB::table('students')->insert(['id' => '019fb1a6-d432-72ff-8b76-ade4f1394e82', 'user_id' => $user->id, 'nis' => '003', 'full_name' => 'Siswa Login', 'gender' => 'male', 'is_active' => true]);

        $this->assertSame('019fb1a6-d432-72ff-8b76-ade4f1394e82', app(MutabaahPortalService::class)->ownStudent($user)->id);
    }
}
