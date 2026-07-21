<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $studentId = $this->route('student') ?? $this->route('id');

        return [
            'class_id' => ['nullable', 'uuid', 'exists:classes,id'],
            'nis' => [
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'nis')->ignore($studentId),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', Rule::in(['male', 'female'])],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'metadata.no_pendaftaran' => ['nullable', 'string', 'max:100'],
            'metadata.nik' => ['nullable', 'string', 'max:50'],
            'metadata.no_registrasi_akta_lahir' => ['nullable', 'string', 'max:100'],
            'metadata.no_kk' => ['nullable', 'string', 'max:50'],
            'metadata.nisn' => ['nullable', 'string', 'max:50'],
            'metadata.agama' => ['nullable', 'string', 'max:80'],
            'metadata.email' => ['nullable', 'email', 'max:120'],
            'metadata.anak_ke' => ['nullable', 'integer', 'min:1'],
            'metadata.jumlah_saudara' => ['nullable', 'integer', 'min:0'],
            'metadata.jumlah_saudara_tiri' => ['nullable', 'integer', 'min:0'],
            'metadata.berat_badan' => ['nullable', 'numeric', 'min:0'],
            'metadata.tinggi_badan' => ['nullable', 'numeric', 'min:0'],
            'metadata.riwayat_penyakit' => ['nullable', 'string'],
            'metadata.kewarganegaraan' => ['nullable', 'string', 'max:80'],
            'metadata.rt' => ['nullable', 'string', 'max:10'],
            'metadata.rw' => ['nullable', 'string', 'max:10'],
            'metadata.dusun' => ['nullable', 'string', 'max:120'],
            'metadata.kelurahan' => ['nullable', 'string', 'max:120'],
            'metadata.kecamatan' => ['nullable', 'string', 'max:120'],
            'metadata.kode_pos' => ['nullable', 'string', 'max:20'],
            'metadata.kota_kabupaten' => ['nullable', 'string', 'max:120'],
            'metadata.provinsi' => ['nullable', 'string', 'max:120'],
            'metadata.jenis_tempat_tinggal' => ['nullable', 'string', 'max:120'],
            'metadata.jarak_tempuh_ke_sekolah' => ['nullable', 'string', 'max:120'],
            'metadata.modal_transportasi' => ['nullable', 'string', 'max:120'],
            'metadata.sekolah_asal' => ['nullable', 'string', 'max:180'],
            'metadata.status_sekolah_asal' => ['nullable', 'string', 'max:80'],
            'metadata.kecamatan_sekolah_asal' => ['nullable', 'string', 'max:120'],
            'metadata.kota_kab_sekolah_asal' => ['nullable', 'string', 'max:120'],
            'metadata.nomor_hp_wa_sekolah_asal' => ['nullable', 'string', 'max:50'],
            'metadata.hobi' => ['nullable', 'string', 'max:180'],
            'metadata.cita_cita' => ['nullable', 'string', 'max:180'],
            'metadata.nominal_spp' => ['nullable', 'numeric', 'min:0'],
            'metadata.nominal_ortu_asuh' => ['nullable', 'numeric', 'min:0'],
            'metadata.penerima_kps_pkh' => ['nullable', 'boolean'],
            'metadata.apakah_punya_kip' => ['nullable', 'boolean'],
            'metadata.apakah_layak_menerima_pip' => ['nullable', 'boolean'],
            'metadata.alasan_menolak_pip' => ['nullable', 'string'],
            'metadata.ayah' => ['nullable', 'array'],
            'metadata.ibu' => ['nullable', 'array'],
            'metadata.wali' => ['nullable', 'array'],
            'metadata.akademik' => ['nullable', 'array'],
        ];
    }
}
