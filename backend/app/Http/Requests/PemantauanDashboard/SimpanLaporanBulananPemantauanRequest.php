<?php

namespace App\Http\Requests\PemantauanDashboard;

use Illuminate\Foundation\Http\FormRequest;

class SimpanLaporanBulananPemantauanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_tahun_ajaran' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'id_semester' => ['nullable', 'uuid', 'exists:semesters,id'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'judul_laporan' => ['required', 'string', 'max:180'],
            'ringkasan_laporan' => ['required', 'string'],
            'tindak_lanjut' => ['nullable', 'string'],
            'status_validasi' => ['required', 'string', 'in:draf,diajukan,tervalidasi,revisi'],
            'id_pemeriksa' => ['nullable', 'uuid', 'exists:users,id'],
            'data_tambahan' => ['nullable', 'array'],
        ];
    }
}
