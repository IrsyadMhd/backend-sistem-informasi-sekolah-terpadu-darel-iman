<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MutabaahCrudRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resource = $this->route('resource');
        $action = $this->isMethod('post') ? 'create' : 'update';
        $permission = match ($resource) {
            'categories' => "mutabaah.category.{$action}",
            'agendas' => "mutabaah.agenda.{$action}",
            'templates', 'template-items' => "mutabaah.template.{$action}",
            'template-assignments' => 'mutabaah.template.assign',
            'supervisor-assignments' => "mutabaah.supervisor.{$action}",
            default => null,
        };

        $user = $this->user();
        $hasRoleWrite = $user?->hasRole([
            'Super Admin', 'Admin', 'Tata Usaha', 'TU', 'Operator',
            'Wali Kelas', 'Guru', 'Musyrif', 'Musyrifah', 'Pembimbing',
            'Kepala Sekolah', 'Divisi Pendidikan', 'Yayasan',
        ]);

        return $permission && ($hasRoleWrite || $user?->can($permission));
    }

    public function rules(): array
    {
        $resource = $this->route('resource');
        $id = $this->route('id');
        $unique = fn (string $table, string $column = 'code') => Rule::unique($table, $column)->ignore($id);

        return match ($resource) {
            'categories' => [
                'code' => ['required', 'string', 'max:30', $unique('mutabaah_categories')],
                'name' => ['required', 'string', 'max:100'],
                'icon' => ['nullable', 'string', 'max:60'], 'color' => ['nullable', 'string', 'max:20'],
                'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
                'is_active' => ['required', 'boolean'], 'description' => ['nullable', 'string', 'max:1000'],
            ],
            'agendas' => [
                'category_id' => ['required', 'uuid', 'exists:mutabaah_categories,id'],
                'code' => ['required', 'string', 'max:40', $unique('mutabaah_agenda_items')],
                'name' => ['required', 'string', 'max:180'],
                'input_type' => ['required', Rule::in(['status', 'yes_no', 'checklist', 'number', 'duration', 'pages', 'verses', 'text'])],
                'weight' => ['required', 'numeric', 'min:0'],
                'icon' => ['nullable', 'string', 'max:60'], 'color' => ['nullable', 'string', 'max:20'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
                'is_active' => ['required', 'boolean'], 'description' => ['nullable', 'string', 'max:1000'],
            ],
            'templates' => [
                'code' => ['required', 'string', 'max:40', $unique('mutabaah_templates')],
                'name' => ['required', 'string', 'max:150'], 'education_unit_id' => ['nullable', 'uuid', 'exists:education_units,id'],
                'education_level' => ['required', 'string', 'max:50'], 'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
                'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'], 'status' => ['required', Rule::in(['active', 'inactive'])],
                'start_date' => ['required', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                'description' => ['nullable', 'string', 'max:1000'],
            ],
            'template-items' => [
                'agenda_item_id' => ['sometimes', 'required', 'uuid', 'exists:mutabaah_agenda_items,id'],
                'sort_order' => ['sometimes', 'integer', 'min:0'], 'weight' => ['sometimes', 'numeric', 'min:0'],
                'target_value' => ['nullable', 'numeric', 'min:0'], 'is_required' => ['sometimes', 'boolean'],
                'requires_parent_signature' => ['sometimes', 'boolean'], 'instruction' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'template-assignments' => [
                'template_id' => ['required', 'uuid', 'exists:mutabaah_templates,id'],
                'education_unit_id' => ['required', 'uuid', 'exists:education_units,id'], 'education_level' => ['nullable', 'string', 'max:50'],
                'kelas_id' => ['nullable', 'uuid', 'exists:classes,id'], 'rombel_id' => ['nullable', 'uuid', 'exists:tbl_kelas,id'],
                'student_id' => ['nullable', 'uuid', 'exists:students,id'], 'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
                'semester_id' => ['required', 'uuid', 'exists:semesters,id'], 'priority' => ['sometimes', 'integer', 'min:0'],
                'status' => ['required', Rule::in(['active', 'inactive'])], 'start_date' => ['required', 'date'],
                'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            ],
            'supervisor-assignments' => [
                'employee_id' => ['required', 'uuid', 'exists:employees,id'],
                'supervisor_type' => ['required', Rule::in(['pembimbing', 'wali_kelas', 'guru_pai', 'guru_tahfizh', 'musyrif', 'musyrifah'])],
                'education_unit_id' => ['required', 'uuid', 'exists:education_units,id'],
                'kelas_id' => ['nullable', 'uuid', 'exists:classes,id'], 'rombel_id' => ['nullable', 'uuid', 'exists:tbl_kelas,id'],
                'dormitory_id' => ['nullable', 'uuid'], 'room_id' => ['nullable', 'uuid'], 'mentoring_group' => ['nullable', 'string', 'max:100'],
                'template_id' => ['nullable', 'uuid', 'exists:mutabaah_templates,id'],
                'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'], 'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
                'start_date' => ['required', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
                'is_primary' => ['sometimes', 'boolean'], 'can_input' => ['sometimes', 'boolean'], 'can_edit' => ['sometimes', 'boolean'],
                'can_finalize' => ['sometimes', 'boolean'], 'can_view_report' => ['sometimes', 'boolean'],
                'status' => ['required', Rule::in(['active', 'inactive'])],
            ],
            default => ['resource' => ['prohibited']],
        };
    }
}
