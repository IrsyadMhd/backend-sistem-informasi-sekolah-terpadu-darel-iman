<?php

namespace App\Http\Requests\Lms;

use Illuminate\Foundation\Http\FormRequest;

class StoreLmsKisiKisiRequest extends FormRequest
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
            'judul_kisi' => ['required', 'string', 'max:200'],
            'mata_pelajaran_id' => ['required', 'uuid', 'exists:subjects,id'],
            'kurikulum_id' => ['nullable', 'uuid', 'exists:master_kurikulum,id'],
            'cp_id' => ['nullable', 'uuid', 'exists:lms_capaian_pembelajaran,id'],
            'tp_id' => ['nullable', 'uuid', 'exists:lms_tujuan_pembelajaran,id'],
            'kelas_id' => ['nullable', 'uuid', 'exists:tbl_kelas,id'],
            'semester_id' => ['nullable', 'uuid', 'exists:semesters,id'],
            'tahun_ajaran_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'guru_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'jenis_ujian' => ['required', 'string', 'max:30', 'in:UH,PTS,UTS,PAS,UAS,CBT,Remedial'],
            'jumlah_soal' => ['required', 'integer', 'min:1', 'max:200'],
            'alokasi_waktu_menit' => ['required', 'integer', 'min:5', 'max:600'],
            'kompetensi_dasar' => ['nullable', 'string'],
            'level_kognitif' => ['nullable', 'string', 'max:100'],
            'distribusi_bobot' => ['nullable', 'array'],
            'distribusi_bobot.pg' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'distribusi_bobot.isian' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'distribusi_bobot.esai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul_kisi.required' => 'Judul kisi-kisi wajib diisi.',
            'mata_pelajaran_id.required' => 'Mata pelajaran wajib dipilih.',
            'mata_pelajaran_id.exists' => 'Mata pelajaran tidak ditemukan.',
            'jenis_ujian.required' => 'Jenis ujian wajib dipilih.',
            'jenis_ujian.in' => 'Jenis ujian tidak valid.',
            'jumlah_soal.required' => 'Jumlah soal wajib diisi.',
            'alokasi_waktu_menit.required' => 'Alokasi waktu wajib diisi.',
        ];
    }
}
