<?php

namespace App\Http\Resources;

use App\Services\Auth\PortalResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = \App\Models\Employee::with('unit')->where('user_id', $this->id)->first();
        $student = \App\Models\Student::with('educationUnit')->where('user_id', $this->id)->first();
        $parent = \App\Models\ParentModel::where('user_id', $this->id)->first();
        $unit = $employee?->unit ?? $student?->educationUnit;
        $unitId = $unit?->id ?? $employee?->unit_id ?? $student?->unit_id ?? data_get($this->metadata, 'education_unit_id') ?? data_get($this->metadata, 'unit_id');
        if (! $unit && $unitId) {
            $unit = \App\Models\EducationUnit::find($unitId);
        }
        $portal = app(PortalResolver::class)->resolve($this->resource);

        $photoUrl = $this->photo_url ?? $employee?->photo_url ?? $student?->photo_url;
        $avatarUrl = $this->avatar_url ?? $photoUrl;

        $employeePayload = $employee ? [
            'id' => $employee->id,
            'nama_lengkap' => $employee->nama_lengkap,
            'foto' => $employee->foto,
            'photo_url' => $employee->photo_url,
            'avatar_url' => $employee->avatar_url,
        ] : null;

        $userData = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'photo_url' => $photoUrl,
            'avatar_url' => $avatarUrl,
            'is_active' => $this->is_active,
            'unit_name' => $unit?->name,
            'unit_code' => $unit?->code,
            'employee' => $employeePayload,
        ];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'photo_url' => $photoUrl,
            'avatar_url' => $avatarUrl,
            'employee' => $employeePayload,
            'is_active' => $this->is_active,
            'unit' => $unit ? [
                'id' => $unit->id,
                'name' => $unit->name,
                'code' => $unit->code,
                'level' => $unit->level,
                'logo_url' => $unit->logo_url,
                'latitude' => $unit->latitude !== null ? (float) $unit->latitude : null,
                'longitude' => $unit->longitude !== null ? (float) $unit->longitude : null,
                'radius_meter' => $unit->radius_meter !== null ? (int) $unit->radius_meter : 30,
            ] : null,
            'unit_name' => $unit?->name,
            'unit_code' => $unit?->code,
            'user' => $userData,
            'roles' => $this->getRoleNames(),
            'role_level' => $this->roles->min('level') !== null ? (int) $this->roles->min('level') : 99,
            'role_levels' => $this->roles->pluck('level')->filter()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->values(),
            'default_portal' => $portal['default_portal'],
            'default_redirect' => $portal['default_redirect'],
            'available_workspaces' => $portal['available_workspaces'],
            'scope' => [
                'unit_id' => $unitId,
                'employee_id' => $employee?->id,
                'student_id' => $student?->id,
                'parent_id' => $parent?->id,
            ],
            'metadata' => $this->metadata,
        ];
    }
}
