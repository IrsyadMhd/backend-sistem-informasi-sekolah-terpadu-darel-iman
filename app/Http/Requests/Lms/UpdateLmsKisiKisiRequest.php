<?php

namespace App\Http\Requests\Lms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLmsKisiKisiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('judul_kisi')) {
            $judul = trim((string) $this->judul_kisi);
            $judul = preg_replace('/\s+/', ' ', $judul);
            $this->merge(['judul_kisi' => $judul]);
        }
    }

    public function rules(): array
    {
        return [
            'judul_kisi' => ['sometimes', 'required', 'string', 'max:200'],
            'mata_pelajaran_id' => ['sometimes', 'required', 'uuid', 'exists:subjects,id'],
            'kurikulum_id' => ['nullable', 'uuid', 'exists:master_kurikulum,id'],
            'cp_id' => ['nullable', 'uuid', 'exists:lms_capaian_pembelajaran,id'],
            'tp_id' => ['nullable', 'uuid', 'exists:lms_tujuan_pembelajaran,id'],
            'kelas_id' => ['nullable', 'uuid', 'exists:tbl_kelas,id'],
            'semester_id' => ['nullable', 'uuid', 'exists:semesters,id'],
            'tahun_ajaran_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'guru_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'jenis_ujian' => ['sometimes', 'required', 'string', 'max:30', 'in:UH,PTS,UTS,PAS,UAS,CBT,Remedial'],
            'jumlah_soal' => ['sometimes', 'required', 'integer', 'min:1', 'max:200'],
            'alokasi_waktu_menit' => ['sometimes', 'required', 'integer', 'min:5', 'max:600'],
            'kompetensi_dasar' => ['nullable', 'string'],
            'level_kognitif' => ['nullable', 'string', 'max:100'],
            'distribusi_bobot' => ['nullable', 'array'],
            'distribusi_bobot.pg' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'distribusi_bobot.isian' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'distribusi_bobot.esai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'boolean'],
        ];
    }
}
