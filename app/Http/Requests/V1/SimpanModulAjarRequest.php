<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class SimpanModulAjarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $updates = [];
        if ($this->has('judul_modul')) {
            $judul = trim((string) $this->judul_modul);
            $judul = preg_replace('/\s+/', ' ', $judul);
            $updates['judul_modul'] = $judul;
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
            'unit_pendidikan_id' => ['nullable', 'uuid'],
            'tahun_ajaran_id' => ['required', 'uuid'],
            'semester_id' => ['required', 'uuid'],
            'kurikulum_id' => ['required', 'uuid'],
            'mata_pelajaran_id' => ['required', 'uuid'],
            'guru_id' => ['required', 'uuid'],
            'kelas_id' => ['required', 'uuid'],
            'rombel_id' => ['nullable', 'uuid'],
            'cp_id' => ['nullable', 'uuid'],
            'tp_id' => ['nullable', 'uuid'],
            'cp_ids' => ['nullable', 'array'],
            'cp_ids.*' => ['uuid'],
            'tp_ids' => ['nullable', 'array'],
            'tp_ids.*' => ['uuid'],
            'kode_modul' => ['nullable', 'string', 'max:50'],
            'judul_modul' => ['required', 'string', 'max:200'],
            'fase' => ['required', 'string', 'max:20'],
            'semester' => ['nullable', 'string', 'max:20'],
            'alokasi_waktu_jp' => ['required', 'integer', 'min:1', 'max:100'],
            'tujuan_pembelajaran' => ['nullable', 'string'],
            'profil_pelajar_pancasila' => ['nullable', 'string'],
            'target_peserta_didik' => ['nullable', 'string'],
            'model_pembelajaran' => ['nullable', 'string', 'max:100'],
            'metode_pembelajaran' => ['nullable', 'string'],
            'media_pembelajaran' => ['nullable', 'string'],
            'sumber_belajar' => ['nullable', 'string'],
            'kegiatan_pendahuluan' => ['nullable', 'string'],
            'kegiatan_inti' => ['nullable', 'string'],
            'kegiatan_penutup' => ['nullable', 'string'],
            'asesmen_awal' => ['nullable', 'string'],
            'asesmen_proses' => ['nullable', 'string'],
            'asesmen_akhir' => ['nullable', 'string'],
            'rencana_penilaian' => ['nullable', 'string'],
            'refleksi_guru' => ['nullable', 'string'],
            'lampiran' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'in:Draft,Review,Publish,Arsip,draft,review,published,archived'],
            'deskripsi' => ['nullable', 'string'],
            'versi' => ['nullable', 'string', 'max:20'],
        ];
    }
}
