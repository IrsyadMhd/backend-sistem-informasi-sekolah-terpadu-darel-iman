<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class LmsPenugasanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $mergeData = [];

        if ($this->has('judul') && ! $this->has('judul_tugas')) {
            $mergeData['judul_tugas'] = $this->input('judul');
        }
        if ($this->has('tipe') && ! $this->has('tipe_tugas')) {
            $mergeData['tipe_tugas'] = $this->input('tipe');
        }
        if ($this->has('tanggal_selesai') && ! $this->has('deadline')) {
            $mergeData['deadline'] = $this->input('tanggal_selesai');
        }
        if ($this->has('lampiran') && ! $this->has('file_lampiran')) {
            $mergeData['file_lampiran'] = $this->input('lampiran');
        }
        if ($this->has('status') && ! $this->has('is_published')) {
            $statusVal = strtolower((string) $this->input('status'));
            $mergeData['is_published'] = in_array($statusVal, ['dipublikasikan', 'published', '1', 'true', 'active'], true);
        }

        if (! empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    public function rules(): array
    {
        return [
            'modul_ajar_id' => ['nullable', 'string', 'exists:lms_modul_ajar,id'],
            'mata_pelajaran_id' => ['nullable', 'string', 'exists:subjects,id'],
            'kelas_id' => ['nullable', 'string', 'exists:tbl_kelas,id'],
            'guru_id' => ['nullable', 'string', 'exists:employees,id'],
            'semester_id' => ['nullable', 'string', 'exists:semesters,id'],
            'tahun_ajaran_id' => ['nullable', 'string', 'exists:academic_years,id'],
            'judul' => ['nullable', 'string', 'max:200'],
            'judul_tugas' => ['required_without:judul', 'string', 'max:200'],
            'deskripsi' => ['nullable', 'string'],
            'instruksi' => ['nullable', 'string'],
            'tipe' => ['nullable', 'string', 'in:individu,kelompok'],
            'tipe_tugas' => ['nullable', 'string', 'in:individu,kelompok'],
            'jenis_tugas' => ['nullable', 'string', 'max:20'],
            'nilai_maksimal' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'bobot_persen' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'izin_kumpul_terlambat' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string'],
            'lampiran' => ['nullable', 'string', 'max:500'],
            'file_lampiran' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul_tugas.required_without' => 'Judul penugasan wajib diisi.',
            'modul_ajar_id.exists' => 'Modul Ajar tidak valid.',
            'kelas_id.exists' => 'Kelas tidak valid.',
            'guru_id.exists' => 'Guru tidak valid.',
            'mata_pelajaran_id.exists' => 'Mata pelajaran tidak valid.',
        ];
    }
}
