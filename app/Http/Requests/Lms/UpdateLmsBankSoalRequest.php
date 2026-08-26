<?php

namespace App\Http\Requests\Lms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLmsBankSoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('kode_soal')) {
            $kode = trim((string) $this->kode_soal);
            $kode = preg_replace('/\s+/', ' ', $kode);
            $this->merge(['kode_soal' => $kode !== '' ? strtoupper($kode) : null]);
        }
    }

    public function rules(): array
    {
        return [
            'kisi_kisi_id' => ['sometimes', 'required', 'uuid', 'exists:lms_kisi_kisi,id'],
            'mata_pelajaran_id' => ['nullable', 'uuid', 'exists:subjects,id'],
            'kode_soal' => ['nullable', 'string', 'max:30'],
            'pertanyaan' => ['sometimes', 'required', 'string'],
            'tipe_soal' => ['sometimes', 'required', 'string', Rule::in(['pg', 'esai', 'benar_salah', 'menjodohkan', 'isian'])],
            'opsi_a' => ['nullable', 'string'],
            'opsi_b' => ['nullable', 'string'],
            'opsi_c' => ['nullable', 'string'],
            'opsi_d' => ['nullable', 'string'],
            'opsi_e' => ['nullable', 'string'],
            'kunci_jawaban' => ['nullable', 'string'],
            'pembahasan' => ['nullable', 'string'],
            'poin' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tingkat_kesulitan' => ['nullable', 'string', Rule::in(['mudah', 'sedang', 'sulit'])],
            'indikator' => ['nullable', 'string', 'max:500'],
            'gambar_path' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'kisi_kisi_id.required' => 'Kisi-kisi Ujian wajib dipilih.',
            'pertanyaan.required' => 'Teks pertanyaan/soal wajib diisi.',
            'tipe_soal.in' => 'Tipe soal harus salah satu dari: Pilihan Ganda, Essay, Benar Salah, atau Menjodohkan.',
        ];
    }
}
