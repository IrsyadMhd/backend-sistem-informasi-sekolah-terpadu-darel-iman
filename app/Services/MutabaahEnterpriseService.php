<?php

namespace App\Services;

use App\Models\EducationUnit;
use App\Models\MutabaahDailyDetail;
use App\Models\MutabaahDailyHeader;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplateAssignment;
use App\Models\MutabaahTemplateItem;
use App\Repositories\Contracts\MutabaahRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MutabaahEnterpriseService
{
    public function __construct(private readonly MutabaahRepositoryInterface $repository) {}

    public function paginate(string $resource, array $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($resource, $filters);
    }

    public function find(string $resource, string $id, bool $trashed = false): Model
    {
        return $this->repository->find($resource, $id, $trashed);
    }

    public function create(string $resource, array $data): Model
    {
        return DB::transaction(function () use ($resource, $data) {
            $items = Arr::pull($data, 'items', []);
            $this->assertNoConflict($resource, $data);
            $model = $this->repository->create($resource, $data);
            if ($resource === 'templates') {
                $model->items()->createMany($items);
            }

            return $this->repository->find($resource, $model->getKey());
        });
    }

    public function update(string $resource, string $id, array $data): Model
    {
        return DB::transaction(function () use ($resource, $id, $data) {
            $items = Arr::pull($data, 'items', null);
            $this->assertNoConflict($resource, $data, $id);
            $model = $this->repository->update($resource, $id, $data);
            if ($resource === 'templates' && is_array($items)) {
                $model->items()->delete();
                $model->items()->createMany($items);
            }

            return $this->repository->find($resource, $id);
        });
    }

    public function delete(string $resource, string $id): void
    {
        DB::transaction(fn () => $this->repository->delete($resource, $id));
    }

    public function restore(string $resource, string $id): Model
    {
        return DB::transaction(fn () => $this->repository->restore($resource, $id));
    }

    public function bulkDelete(string $resource, array $ids): int
    {
        return DB::transaction(function () use ($resource, $ids) {
            foreach ($ids as $id) {
                $this->repository->delete($resource, $id);
            }

return count($ids);
        });
    }

    public function bulkRestore(string $resource, array $ids): int
    {
        return DB::transaction(function () use ($resource, $ids) {
            foreach ($ids as $id) {
                $this->repository->restore($resource, $id);
            }

return count($ids);
        });
    }

    public function forceDelete(string $resource, string $id): void
    {
        DB::transaction(function () use ($resource, $id) {
            $this->assertNotUsed($resource, $id);
            $this->repository->forceDelete($resource, $id);
        });
    }

    public function addTemplateItem(string $templateId, array $data): MutabaahTemplateItem
    {
        return DB::transaction(function () use ($templateId, $data) {
            if (MutabaahTemplateItem::where('template_id', $templateId)->where('agenda_item_id', $data['agenda_item_id'])->exists()) {
                throw ValidationException::withMessages(['agenda_item_id' => 'Agenda sudah ada dalam template ini.']);
            }

            return MutabaahTemplateItem::create($data + ['template_id' => $templateId]);
        });
    }

    public function updateTemplateItem(string $id, array $data): MutabaahTemplateItem
    {
        return DB::transaction(function () use ($id, $data) {
            $item = MutabaahTemplateItem::findOrFail($id);
            if (isset($data['agenda_item_id']) && MutabaahTemplateItem::where('template_id', $item->template_id)->where('agenda_item_id', $data['agenda_item_id'])->whereKeyNot($id)->exists()) {
                throw ValidationException::withMessages(['agenda_item_id' => 'Agenda sudah ada dalam template ini.']);
            } $item->update($data);

            return $item->fresh('agendaItem');
        });
    }

    public function deleteTemplateItem(string $id): void
    {
        DB::transaction(function () use ($id) {
            abort_if(MutabaahDailyDetail::where('template_item_id', $id)->exists(), 409, 'Item template sudah digunakan transaksi harian dan tidak dapat dihapus.');
            MutabaahTemplateItem::findOrFail($id)->delete();
        });
    }

    public function reorderTemplate(string $templateId, array $items): void
    {
        DB::transaction(function () use ($templateId, $items) {
            foreach ($items as $item) {
                MutabaahTemplateItem::where('template_id', $templateId)->whereKey($item['id'])->update(['sort_order' => $item['sort_order']]);
            }
        });
    }

    private function assertNoConflict(string $resource, array $data, ?string $ignoreId = null): void
    {
        if (! in_array($resource, ['template-assignments', 'supervisor-assignments'])) {
            return;
        }
        $model = $resource === 'template-assignments' ? MutabaahTemplateAssignment::class : MutabaahSupervisorAssignment::class;
        $query = $model::query()->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));
        $scope = $resource === 'template-assignments'
            ? ['template_id', 'education_unit_id', 'education_level', 'kelas_id', 'rombel_id', 'student_id', 'academic_year_id', 'semester_id']
            : ['employee_id', 'supervisor_type', 'education_unit_id', 'kelas_id', 'rombel_id', 'mentoring_group', 'academic_year_id', 'semester_id'];
        foreach ($scope as $field) {
            $query->where($field, $data[$field] ?? null);
        }
        $start = $data['start_date'];
        $end = $data['end_date'] ?? '9999-12-31';
        $query->whereDate('start_date', '<=', $end)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $start));
        if ($query->exists()) {
            throw ValidationException::withMessages(['start_date' => 'Assignment dengan scope dan periode yang sama atau bertumpang tindih sudah tersedia.']);
        }

        if ($resource === 'supervisor-assignments' && in_array($data['supervisor_type'], ['musyrif', 'musyrifah'], true)) {
            $unit = EducationUnit::find($data['education_unit_id']);
            $scopeName = strtolower(($unit?->name ?? '').' '.($unit?->level ?? ''));
            if ($unit && ! str_contains($scopeName, 'pesantren') && ! str_contains($scopeName, "ma'had") && ! str_contains($scopeName, 'mahad')) {
                throw ValidationException::withMessages(['education_unit_id' => 'Musyrif/Musyrifah hanya dapat ditugaskan pada unit Pesantren atau Ma’had.']);
            }
        }
    }

    private function assertNotUsed(string $resource, string $id): void
    {
        $used = match ($resource) {
            'agendas' => MutabaahDailyDetail::where('agenda_item_id', $id)->exists(),
            'templates' => MutabaahDailyHeader::where('template_id', $id)->exists(),
            'supervisor-assignments' => MutabaahDailyHeader::where('supervisor_assignment_id', $id)->exists(),
            default => false,
        };
        abort_if($used, 409, 'Data sudah digunakan oleh transaksi harian dan tidak dapat dihapus permanen.');
    }
}
