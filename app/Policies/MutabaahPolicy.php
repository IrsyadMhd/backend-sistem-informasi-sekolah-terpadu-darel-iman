<?php

namespace App\Policies;

use App\Models\MutabaahAgendaItem;
use App\Models\MutabaahCategory;
use App\Models\MutabaahSupervisorAssignment;
use App\Models\MutabaahTemplate;
use App\Models\MutabaahTemplateAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MutabaahPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('Super Admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $this->hasAny($user, 'view');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->permission($model, 'view'));
    }

    public function create(User $user): bool
    {
        return $this->hasAny($user, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->permission($model, 'update'));
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->permission($model, 'delete'));
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->can($this->permission($model, $model instanceof MutabaahCategory || $model instanceof MutabaahAgendaItem ? 'restore' : 'update'));
    }

    public function forceDelete(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    private function hasAny(User $user, string $action): bool
    {
        return collect(['category', 'agenda', 'template', 'supervisor'])->contains(fn ($resource) => $user->can("mutabaah.{$resource}.{$action}"));
    }

    private function permission(Model $model, string $action): string
    {
        $resource = match (true) {
            $model instanceof MutabaahCategory => 'category',
            $model instanceof MutabaahAgendaItem => 'agenda',
            $model instanceof MutabaahTemplate => 'template',
            $model instanceof MutabaahTemplateAssignment => 'template',
            $model instanceof MutabaahSupervisorAssignment => 'supervisor',
            default => 'daily',
        };
        if ($model instanceof MutabaahTemplateAssignment && $action !== 'view') {
            return 'mutabaah.template.assign';
        }

        return "mutabaah.{$resource}.{$action}";
    }
}
