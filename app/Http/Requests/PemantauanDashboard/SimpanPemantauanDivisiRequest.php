<?php

namespace App\Http\Requests\PemantauanDashboard;

use Illuminate\Foundation\Http\FormRequest;

class SimpanPemantauanDivisiRequest extends FormRequest
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
            'tanggal_pemantauan' => ['required', 'date'],
            'nama_divisi' => ['required', 'string', 'max:120'],
            'aspek_pemantauan' => ['required', 'string', 'max:150'],
            'persentase_capaian' => ['required', 'numeric', 'min:0', 'max:100'],
            'status_pemantauan' => ['required', 'string', 'in:proses,tercapai,terlambat,belum_tercapai'],
            'catatan' => ['nullable', 'string'],
            'data_tambahan' => ['nullable', 'array'],
        ];
    }
}
