<?php

namespace App\Http\Requests\V1;

use App\Models\Kelas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Class SimpanKelasRequest
 * Validasi penambahan data kelas / rombel baru.
 */
class SimpanKelasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_pendidikan_id' => ['required', 'uuid', 'exists:education_units,id'],
            'tahun_ajaran_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'jenjang' => ['required', 'string', 'max:50'],
            'tingkat' => ['required', 'string', 'max:20'],
            'kode_kelas' => ['required', 'string', 'max:50', 'unique:tbl_kelas,kode_kelas'],
            'nama_kelas' => ['required', 'string', 'max:100'],
            'wali_kelas_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:200'],
            'ruangan' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', Rule::in(['Aktif', 'Nonaktif'])],
        ];
    }

    public function messages(): array
    {
        return [
            'unit_pendidikan_id.required' => 'Unit pendidikan wajib dipilih.',
            'tahun_ajaran_id.required' => 'Tahun ajaran wajib dipilih.',
            'semester_id.required' => 'Semester wajib dipilih.',
            'jenjang.required' => 'Jenjang kelas wajib diisi.',
            'tingkat.required' => 'Tingkat kelas wajib diisi.',
            'kode_kelas.required' => 'Kode kelas wajib diisi.',
            'kode_kelas.unique' => 'Kode kelas sudah digunakan. Gunakan kode kelas yang unik.',
            'nama_kelas.required' => 'Nama kelas wajib diisi.',
            'kapasitas.required' => 'Kapasitas kelas wajib diisi.',
            'kapasitas.min' => 'Kapasitas kelas minimal 1 siswa.',
            'status.in' => 'Status harus berupa Aktif atau Nonaktif.',
        ];
    }

    /**
     * Custom validation callback untuk aturan bisnis kompleks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $unitId = $this->input('unit_pendidikan_id');
            $tahunId = $this->input('tahun_ajaran_id');
            $semesterId = $this->input('semester_id');
            $namaKelas = $this->input('nama_kelas');
            $waliId = $this->input('wali_kelas_id');

            // 1. Cek duplikasi Nama Kelas pada Unit, Tahun Ajaran, & Semester yang sama
            if ($unitId && $tahunId && $semesterId && $namaKelas) {
                $duplikatNama = Kelas::where('unit_pendidikan_id', $unitId)
                    ->where('tahun_ajaran_id', $tahunId)
                    ->where('semester_id', $semesterId)
                    ->whereRaw('LOWER(nama_kelas) = ?', [strtolower(trim($namaKelas))])
                    ->exists();

                if ($duplikatNama) {
                    $validator->errors()->add(
                        'nama_kelas',
                        'Nama kelas "'.$namaKelas.'" sudah ada pada unit dan semester tahun ajaran ini.'
                    );
                }
            }

            // 2. Cek Wali Kelas: Satu guru/employee hanya boleh menjadi wali satu kelas pada satu tahun ajaran
            if ($waliId && $tahunId) {
                $duplikatWali = Kelas::where('tahun_ajaran_id', $tahunId)
                    ->where('wali_kelas_id', $waliId)
                    ->exists();

                if ($duplikatWali) {
                    $validator->errors()->add(
                        'wali_kelas_id',
                        'Guru/Pegawai yang dipilih sudah menjadi Wali Kelas untuk kelas lain pada tahun ajaran ini.'
                    );
                }
            }
        });
    }
}
