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

    protected function prepareForValidation(): void
    {
        $updates = [];
        if ($this->has('full_name')) {
            $nama = trim((string) $this->full_name);
            $nama = preg_replace('/\s+/', ' ', $nama);
            $updates['full_name'] = $nama;
        }
        if ($this->has('nis')) {
            $nis = trim((string) $this->nis);
            $nis = preg_replace('/\s+/', ' ', $nis);
            $updates['nis'] = $nis;
        }
        if ($this->has('nisn')) {
            $nisn = trim((string) $this->nisn);
            $nisn = preg_replace('/\s+/', ' ', $nisn);
            $updates['nisn'] = $nisn !== '' ? $nisn : null;
        }
        if (! empty($updates)) {
            $this->merge($updates);
        }
    }

    public function rules(): array
    {
        $studentId = $this->route('student') ?? $this->route('id');

        return [
            'parent_id' => ['nullable', 'uuid', 'exists:parents,id'],
            'unit_id' => ['nullable', 'uuid', 'exists:education_units,id'],
            'kelas_id' => ['nullable', 'uuid', 'exists:tbl_kelas,id'],
            'nisn' => ['nullable', 'string', 'max:50'],
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
            'metadata.foto_url' => ['nullable', 'string', 'max:4194304'],
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
            'metadata.nama_ayah' => ['nullable', 'string', 'max:255'],
            'metadata.hp_ayah' => ['nullable', 'string', 'max:50'],
            'metadata.nomor_wa_ayah' => ['nullable', 'string', 'max:50'],
            'metadata.nama_ibu' => ['nullable', 'string', 'max:255'],
            'metadata.hp_ibu' => ['nullable', 'string', 'max:50'],
            'metadata.nomor_wa_ibu' => ['nullable', 'string', 'max:50'],
            'metadata.nama_wali' => ['nullable', 'string', 'max:255'],
            'metadata.hp_wali' => ['nullable', 'string', 'max:50'],
            'metadata.nomor_wa_wali' => ['nullable', 'string', 'max:50'],
            'metadata.orang_tua' => ['nullable', 'array'],
            'metadata.orang_tua.nama_ayah' => ['nullable', 'string', 'max:255'],
            'metadata.orang_tua.nama_ibu' => ['nullable', 'string', 'max:255'],
            'metadata.orang_tua.nama_wali' => ['nullable', 'string', 'max:255'],
            'metadata.orang_tua.no_hp' => ['nullable', 'string', 'max:50'],
        ];
    }
}
