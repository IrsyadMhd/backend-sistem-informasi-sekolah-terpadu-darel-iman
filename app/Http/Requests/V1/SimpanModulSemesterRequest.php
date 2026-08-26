<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SimpanModulSemesterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $updates = [];
        if ($this->has('nama_modul')) {
            $nama = trim((string) $this->nama_modul);
            $nama = preg_replace('/\s+/', ' ', $nama);
            $updates['nama_modul'] = $nama;
        }
        if ($this->has('kode_modul')) {
            $kode = trim((string) $this->kode_modul);
            $kode = preg_replace('/\s+/', ' ', $kode);
            $updates['kode_modul'] = $kode !== '' ? strtoupper($kode) : null;
        }
        if (! empty($updates)) {
            $this->merge($updates);
        }
    }

    public function rules(): array
    {
        return [
            'tahun_ajaran_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'semester_id' => ['required', 'uuid', 'exists:semesters,id'],
            'unit_pendidikan_id' => ['nullable', 'uuid', 'exists:education_units,id'],
            'kelas_id' => ['required', 'uuid', 'exists:tbl_kelas,id'],
            'mata_pelajaran_id' => ['required', 'uuid', 'exists:subjects,id'],
            'guru_id' => ['required', 'uuid', 'exists:employees,id'],

            'kode_modul' => ['nullable', 'string', 'max:50', 'unique:modul_semesters,kode_modul'],
            'nama_modul' => ['required', 'string', 'max:150'],
            'jenjang' => ['nullable', 'string', 'max:50'],
            'kurikulum' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:Aktif,Nonaktif,Arsip'],

            // Pembelajaran
            'atp' => ['nullable', 'string'],
            'cp' => ['nullable', 'string'],
            'tujuan_pembelajaran' => ['nullable', 'string'],
            'alokasi_jam' => ['nullable', 'integer', 'min:1'],
            'jumlah_pertemuan' => ['nullable', 'integer', 'min:1'],
            'metode_pembelajaran' => ['nullable', 'string'],
            'model_pembelajaran' => ['nullable', 'string', 'max:100'],
            'media_pembelajaran' => ['nullable', 'string'],
            'sumber_belajar' => ['nullable', 'string'],

            // Target
            'target_nilai_minimum' => ['nullable', 'numeric', 'between:0,100'],
            'target_kehadiran' => ['nullable', 'numeric', 'between:0,100'],
            'target_hafalan' => ['nullable', 'string'],
            'target_proyek' => ['nullable', 'string'],

            // Pengaturan
            'berlaku_mulai' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_mulai'],
            'ditampilkan_di_portal_ortu' => ['nullable', 'boolean'],
            'ditampilkan_di_aplikasi_siswa' => ['nullable', 'boolean'],
            'arsip_otomatis' => ['nullable', 'boolean'],

            // Bobot Penilaian
            'bobot_tugas' => ['required', 'numeric', 'between:0,100'],
            'bobot_quiz' => ['required', 'numeric', 'between:0,100'],
            'bobot_projek' => ['required', 'numeric', 'between:0,100'],
            'bobot_uts' => ['required', 'numeric', 'between:0,100'],
            'bobot_uas' => ['required', 'numeric', 'between:0,100'],

            // Detail Materi
            'details' => ['nullable', 'array'],
            'details.*.minggu' => ['nullable', 'integer', 'min:1'],
            'details.*.materi' => ['required_with:details', 'string', 'max:255'],
            'details.*.atp' => ['nullable', 'string'],
            'details.*.cp' => ['nullable', 'string'],
            'details.*.jp' => ['nullable', 'integer', 'min:1'],
            'details.*.keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'tahun_ajaran_id.required' => 'Tahun Ajaran wajib dipilih.',
            'semester_id.required' => 'Semester wajib dipilih.',
            'kelas_id.required' => 'Kelas wajib dipilih.',
            'mata_pelajaran_id.required' => 'Mata Pelajaran wajib dipilih.',
            'guru_id.required' => 'Guru Pengampu wajib dipilih.',
            'nama_modul.required' => 'Nama Modul Semester wajib diisi.',
            'kode_modul.unique' => 'Kode Modul sudah digunakan.',
        ];
    }
}
