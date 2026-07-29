<?php

namespace App\Http\Requests\Lms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLmsRaporRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guru_wali_id' => 'nullable|uuid|exists:employees,id',
            'total_nilai' => 'nullable|numeric|min:0',
            'rata_rata' => 'nullable|numeric|min:0|max:100',
            'peringkat_kelas' => 'nullable|integer|min:1',
            'total_siswa_kelas' => 'nullable|integer|min:1',
            'total_mapel' => 'nullable|integer|min:0',
            'mapel_lulus' => 'nullable|integer|min:0',
            'mapel_tidak_lulus' => 'nullable|integer|min:0',
            'total_hari_efektif' => 'nullable|integer|min:0',
            'total_hadir' => 'nullable|integer|min:0',
            'total_izin' => 'nullable|integer|min:0',
            'total_sakit' => 'nullable|integer|min:0',
            'total_alpha' => 'nullable|integer|min:0',
            'catatan_wali_kelas' => 'nullable|string',
            'catatan_kepala_sekolah' => 'nullable|string',
            'status_rapor' => 'nullable|string|in:draft,final,diterbitkan,direvisi',
            'tanggal_terbit' => 'nullable|date',
            'sudah_dilihat_ortu' => 'nullable|boolean',
        ];
    }
}
