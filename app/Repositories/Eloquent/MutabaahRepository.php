<?php

namespace App\Repositories\Eloquent;

use App\Models\MutabaahAgendaItem;
use App\Models\MutabaahCategory;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateAssignment;
use App\Models\User;
use App\Repositories\Contracts\MutabaahRepositoryInterface;
use App\Services\MutabaahDataScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class MutabaahRepository implements MutabaahRepositoryInterface
{
    public function __construct(private readonly MutabaahDataScope $dataScope) {}

    private const MODELS = [
        'categories' => MutabaahCategory::class, 'agendas' => MutabaahAgendaItem::class,
        'templates' => MutabaahTemplate::class, 'template-assignments' => MutabaahTemplateAssignment::class,
        'supervisor-assignments' => MutabaahSupervisorAssignment::class,
    ];

    private const RELATIONS = [
        'categories' => ['agendaItems'], 'agendas' => ['category'],
        'templates' => ['educationUnit', 'semester', 'academicYear', 'items.agendaItem'],
        'template-assignments' => ['template', 'educationUnit', 'kelas', 'rombel', 'student', 'academicYear', 'semester'],
        'supervisor-assignments' => ['employee', 'template', 'educationUnit', 'kelas', 'rombel', 'academicYear', 'semester'],
    ];

    private function model(string $resource): string
    {
        abort_unless(isset(self::MODELS[$resource]), 404);

        return self::MODELS[$resource];
    }

    public function paginate(string $resource, array $filters): LengthAwarePaginator
    {
        $model = $this->model($resource);
        $query = $model::query()->with(self::RELATIONS[$resource]);
        if (($filters['_user'] ?? null) instanceof User) {
            $this->dataScope->applyEnterprise($query, $resource, $filters['_user']);
        }
        if (! empty($filters['with_trashed'])) {
            $query->withTrashed();
        }
        if ($search = ($filters['search'] ?? null)) {
            $query->where(function ($q) use ($resource, $search) {
                if (in_array($resource, ['categories', 'agendas', 'templates'])) {
                    $q->where('name', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%");
                } elseif ($resource === 'template-assignments') {
                    $q->where('education_level', 'ilike', "%{$search}%")->orWhereHas('template', fn ($x) => $x->where('name', 'ilike', "%{$search}%"));
                } else {
                    $q->where('mentoring_group', 'ilike', "%{$search}%")->orWhereHas('employee', fn ($x) => $x->where('nama_lengkap', 'ilike', "%{$search}%"));
                }
            });
        }
        if (($filters['status'] ?? '') !== '') {
            if (in_array($resource, ['categories', 'agendas'])) {
                $query->where('is_active', in_array($filters['status'], [1, '1', true, 'active'], true));
            } else {
                $query->where('status', $filters['status']);
            }
        }
        foreach (['education_unit_id', 'education_level', 'academic_year_id', 'semester_id', 'kelas_id', 'rombel_id', 'supervisor_type'] as $field) {
            if (($filters[$field] ?? '') !== '' && in_array($field, $model::query()->getModel()->getFillable(), true)) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($resource === 'agendas' && ! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if ($filters['date_from'] ?? null) {
            $query->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $filters['date_from']));
        }
        if ($filters['date_to'] ?? null) {
            $query->whereDate('start_date', '<=', $filters['date_to']);
        }
        $allowedSort = match ($resource) {
            'categories', 'agendas' => ['name', 'code', 'sort_order', 'created_at'],
            'templates' => ['name', 'code', 'start_date', 'created_at'],
            default => ['start_date', 'created_at'],
        };
        $sort = in_array($filters['sort'] ?? '', $allowedSort) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)->paginate(min((int) ($filters['per_page'] ?? 15), 100));
    }

    public function find(string $resource, string $id, bool $withTrashed = false): Model
    {
        $model = $this->model($resource);
        $query = $model::with(self::RELATIONS[$resource]);
        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($id);
    }

    public function create(string $resource, array $data): Model
    {
        $model = $this->model($resource);

        return $model::create($data);
    }

    public function update(string $resource, string $id, array $data): Model
    {
        $model = $this->find($resource, $id);
        $model->update($data);

        return $model->fresh(self::RELATIONS[$resource]);
    }

    public function delete(string $resource, string $id): void
    {
        $this->find($resource, $id)->delete();
    }

    public function restore(string $resource, string $id): Model
    {
        $model = $this->find($resource, $id, true);
        $model->restore();

        return $model->fresh(self::RELATIONS[$resource]);
    }

    public function forceDelete(string $resource, string $id): void
    {
        $this->find($resource, $id, true)->forceDelete();
    }
}
