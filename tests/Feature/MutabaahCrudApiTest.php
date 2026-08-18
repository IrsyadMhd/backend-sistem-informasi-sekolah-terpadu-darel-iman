<?php

namespace Tests\Feature;

use App\Models\MutabaahCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\MutabaahEnterpriseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class MutabaahCrudApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('mutabaah_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
        });
        $user = User::factory()->make(['id' => '019fb1a6-d432-72ff-8b76-ade4f1394e6c']);
        $role = new Role(['name' => 'Super Admin', 'guard_name' => 'web']);
        $user->setRelation('roles', collect([$role]));
        $this->actingAs($user, 'sanctum');
    }

    public function test_category_list_uses_standard_response_and_pagination(): void
    {
        $category = new MutabaahCategory(['code' => 'SHALAT', 'name' => 'Shalat', 'is_active' => true]);
        $category->id = '019fb1a6-d432-72ff-8b76-ade4f1394e6d';
        $service = Mockery::mock(MutabaahEnterpriseService::class);
        $service->shouldReceive('paginate')->once()->with('categories', Mockery::type('array'))
            ->andReturn(new LengthAwarePaginator([$category], 1, 15, 1));
        $this->app->instance(MutabaahEnterpriseService::class, $service);

        $this->getJson('/api/mutabaah/categories?search=shalat&sort=name&direction=asc')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.code', 'SHALAT')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_category_create_validates_and_returns_created_response(): void
    {
        $category = new MutabaahCategory(['code' => 'TEST', 'name' => 'Testing', 'is_active' => true]);
        $category->id = '019fb1a6-d432-72ff-8b76-ade4f1394e6d';
        $service = Mockery::mock(MutabaahEnterpriseService::class);
        $service->shouldReceive('create')->once()->with('categories', Mockery::on(fn ($data) => $data['code'] === 'TEST'))->andReturn($category);
        $this->app->instance(MutabaahEnterpriseService::class, $service);

        $this->postJson('/api/mutabaah/categories', ['code' => 'TEST', 'name' => 'Testing', 'is_active' => true])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'TEST');
    }

    public function test_duplicate_category_code_is_rejected(): void
    {
        \DB::table('mutabaah_categories')->insert(['id' => '019fb1a6-d432-72ff-8b76-ade4f1394e6d', 'code' => 'DUP', 'name' => 'Existing', 'is_active' => true]);
        $this->postJson('/api/mutabaah/categories', ['code' => 'DUP', 'name' => 'Duplicate', 'is_active' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }
}
