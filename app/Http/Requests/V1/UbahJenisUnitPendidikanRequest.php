<?php

namespace App\Http\Requests\V1;

use App\Models\JenisUnitPendidikan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbahJenisUnitPendidikanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $updates = [];
        if ($this->has('kode_jenis')) {
            $kode = trim((string) $this->kode_jenis);
            $kode = preg_replace('/\s+/', ' ', $kode);
            $updates['kode_jenis'] = strtoupper($kode);
        }
        if ($this->has('nama_jenis')) {
            $nama = trim((string) $this->nama_jenis);
            $nama = preg_replace('/\s+/', ' ', $nama);
            $updates['nama_jenis'] = $nama;
        }
        if ($this->has('singkatan')) {
            $singkatan = trim((string) $this->singkatan);
            $singkatan = preg_replace('/\s+/', ' ', $singkatan);
            $updates['singkatan'] = $singkatan !== '' ? $singkatan : null;
        }
        if (! empty($updates)) {
            $this->merge($updates);
        }
    }

    public function rules(): array
    {
        $id = $this->route('jenis_unit') ?? $this->route('id');
        $model = JenisUnitPendidikan::withTrashed()
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', (int) $id);
                } else {
                    $q->where('uuid', $id)->orWhere('kode_jenis', $id);
                }
            })
            ->first();

        $realId = $model ? $model->id : $id;

        return [
            'kode_jenis' => ['required', 'string', 'max:20', Rule::unique('master_jenis_unit_pendidikan', 'kode_jenis')->ignore($realId)],
            'nama_jenis' => ['required', 'string', 'max:150', Rule::unique('master_jenis_unit_pendidikan', 'nama_jenis')->ignore($realId)],
            'singkatan' => ['nullable', 'string', 'max:50'],
            'jenjang' => ['required', 'string', 'in:PAUD,TK,SD,MI,SMP,MTs,SMA,MA,Pondok Pesantren,Mahad'],
            'urutan' => ['required', 'numeric', 'integer', 'min:1'],
            'warna_badge' => ['nullable', 'string', 'max:30'],
            'icon' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode_jenis.required' => 'Kode wajib diisi',
            'kode_jenis.unique' => 'Kode harus unik',
            'kode_jenis.max' => 'Kode maksimal 20 karakter',
            'nama_jenis.required' => 'Nama wajib diisi',
            'nama_jenis.unique' => 'Nama tidak boleh sama',
            'nama_jenis.max' => 'Nama maksimal 150 karakter',
            'jenjang.required' => 'Jenjang pendidikan wajib dipilih',
            'jenjang.in' => 'Jenjang pendidikan tidak valid',
            'urutan.required' => 'Urutan harus angka',
            'urutan.numeric' => 'Urutan harus angka',
            'urutan.integer' => 'Urutan harus angka',
        ];
    }
}
