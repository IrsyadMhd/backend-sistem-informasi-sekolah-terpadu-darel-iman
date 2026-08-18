<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MutabaahDailyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $permission = match ($this->route()?->getActionMethod()) {
            'finalizeStudent', 'finalizeBulk' => 'mutabaah.daily.finalize',
            'reopen' => 'mutabaah.daily.reopen',
            'saveCell', 'bulkSave', 'copyPreviousDay' => 'mutabaah.daily.input',
            default => 'mutabaah.daily.view',
        };

        return $user && ($user->hasRole('Super Admin') || $user->can($permission));
    }

    public function rules(): array
    {
        $common = [
            'activity_date' => ['required', 'date'],
            'supervisor_assignment_id' => ['required', 'uuid', 'exists:mutabaah_supervisor_assignments,id'],
        ];

        return $common + match ($this->route()->getActionMethod()) {
            'saveCell' => [
                'student_id' => ['required', 'uuid', 'exists:students,id'],
                'template_item_id' => ['required', 'uuid', 'exists:mutabaah_template_items,id'],
                'status_value' => ['nullable', Rule::in(['good', 'less', 'not_done', 'na'])],
                'numeric_value' => ['nullable', 'numeric'], 'text_value' => ['nullable', 'string', 'max:4000'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            'bulkSave' => [
                'student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['uuid', 'distinct'],
                'template_item_id' => ['required', 'uuid', 'exists:mutabaah_template_items,id'],
                'value' => ['required', 'array'], 'value.status_value' => ['nullable', Rule::in(['good', 'less', 'not_done', 'na'])],
                'value.numeric_value' => ['nullable', 'numeric'], 'value.text_value' => ['nullable', 'string', 'max:4000'],
            ],
            'copyPreviousDay', 'finalizeBulk' => ['student_ids' => ['required', 'array', 'min:1'], 'student_ids.*' => ['uuid', 'distinct']],
            'finalizeStudent' => ['student_id' => ['required', 'uuid', 'exists:students,id']],
            'reopen' => ['student_id' => ['required', 'uuid', 'exists:students,id'], 'reason' => ['required', 'string', 'max:500']],
            default => [],
        };
    }
}
