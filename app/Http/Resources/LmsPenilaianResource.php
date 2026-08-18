<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LmsPenilaianResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $meta = $this->metadata ?? [];

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'subject_id' => $this->subject_id,
            'academic_year_id' => $this->academic_year_id,
            'semester_id' => $this->semester_id,
            'kelas_id' => $this->kelas_id,

            'score_assignment' => (float) $this->score_assignment,
            'score_quiz' => (float) $this->score_quiz,
            'score_midterm' => (float) $this->score_midterm,
            'score_final' => (float) $this->score_final,

            'final_score' => (float) $this->final_score,
            'grade_letter' => $this->grade_letter,
            'is_passed' => (bool) $this->is_passed,
            'notes' => $this->notes,

            'weights_config' => [
                'bobot_tugas' => (float) ($meta['bobot_tugas'] ?? 20.0),
                'bobot_uh' => (float) ($meta['bobot_uh'] ?? 25.0),
                'bobot_uts' => (float) ($meta['bobot_uts'] ?? 25.0),
                'bobot_uas' => (float) ($meta['bobot_uas'] ?? 30.0),
                'nilai_kkm' => (float) ($meta['nilai_kkm'] ?? 75.0),
                'synced_at' => $meta['synced_at'] ?? null,
            ],

            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'full_name' => $this->student->full_name,
                    'nis' => $this->student->nisn ?? $this->student->nis ?? '',
                ];
            }),

            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                    'code' => $this->subject->code,
                ];
            }),

            'kelas' => $this->whenLoaded('kelas', function () {
                return [
                    'id' => $this->kelas->id,
                    'nama_kelas' => $this->kelas->nama_kelas,
                ];
            }),

            'semester' => $this->whenLoaded('semester', function () {
                return [
                    'id' => $this->semester->id,
                    'nama_semester' => $this->semester->nama_semester,
                ];
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
