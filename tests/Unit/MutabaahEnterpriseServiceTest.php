<?php

namespace Tests\Unit;

use App\Models\MutabaahCategory;
use App\Repositories\Contracts\MutabaahRepositoryInterface;
use App\Services\MutabaahEnterpriseService;
use Mockery;
use Tests\TestCase;

class MutabaahEnterpriseServiceTest extends TestCase
{
    public function test_create_uses_repository_inside_service(): void
    {
        $category = new MutabaahCategory(['code' => 'TEST', 'name' => 'Test', 'is_active' => true]);
        $category->id = '019fb1a6-d432-72ff-8b76-ade4f1394e6c';
        $repository = Mockery::mock(MutabaahRepositoryInterface::class);
        $repository->shouldReceive('create')->once()->with('categories', Mockery::on(fn ($data) => $data['code'] === 'TEST'))->andReturn($category);
        $repository->shouldReceive('find')->once()->andReturn($category);

        $result = (new MutabaahEnterpriseService($repository))->create('categories', ['code' => 'TEST', 'name' => 'Test', 'is_active' => true]);

        $this->assertSame('TEST', $result->code);
    }

    public function test_bulk_restore_restores_every_identifier(): void
    {
        $repository = Mockery::mock(MutabaahRepositoryInterface::class);
        $repository->shouldReceive('restore')->twice()->andReturn(new MutabaahCategory);
        $count = (new MutabaahEnterpriseService($repository))->bulkRestore('categories', [
            '019fb1a6-d432-72ff-8b76-ade4f1394e6c',
            '019fb1a6-d432-72ff-8b76-ade4f1394e6d',
        ]);
        $this->assertSame(2, $count);
    }
}
