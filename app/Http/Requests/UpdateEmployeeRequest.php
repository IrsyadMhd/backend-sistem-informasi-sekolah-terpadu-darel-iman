<?php

namespace App\Http\Requests;

use App\Services\AccessScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Rules\ActiveBusinessOption;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $updates = [];
        if ($this->has('nama_lengkap')) {
            $nama = trim((string) $this->nama_lengkap);
            $nama = preg_replace('/\s+/', ' ', $nama);
            $updates['nama_lengkap'] = $nama;
        }
        if ($this->has('niy')) {
            $niy = trim((string) $this->niy);
            $niy = preg_replace('/\s+/', ' ', $niy);
            $updates['niy'] = $niy !== '' ? $niy : null;
        }
        if ($this->has('nik')) {
            $nik = trim((string) $this->nik);
            $nik = preg_replace('/\s+/', ' ', $nik);
            $updates['nik'] = $nik !== '' ? $nik : null;
        }

        $metadata = $this->input('metadata', []);
        if (! is_array($metadata)) {
            $metadata = [];
        }
        if ($this->filled('status_perkawinan') && empty($metadata['status_perkawinan'])) {
            $metadata['status_perkawinan'] = trim((string) $this->status_perkawinan);
        }
        if ($this->filled('pendidikan_terakhir') && empty($metadata['pendidikan_terakhir'])) {
            $metadata['pendidikan_terakhir'] = trim((string) $this->pendidikan_terakhir);
        }
        if (! empty($metadata)) {
            $updates['metadata'] = $metadata;
        }

        if (! empty($updates)) {
            $this->merge($updates);
        }
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee') ?? $this->route('id');

        $accessScope = app(AccessScopeService::class);
        if ($this->user() && $accessScope->canManageUnitPositions($this->user()) && ! $accessScope->canManageGlobalAccess($this->user())) {
            return [
                'jabatan_id' => ['required', 'uuid', 'exists:positions,id'],
            ];
        }

        return [
            'niy' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'niy')->ignore($employeeId)],
            'nik' => 'nullable|string|max:32',
            'nama_lengkap' => 'required|string|max:255',
            'nama_panggilan' => 'nullable|string|max:100',
            'gelar_depan' => 'nullable|string|max:30',
            'gelar_belakang' => 'nullable|string|max:30',
            'jenis_kelamin' => 'required|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'agama' => 'nullable|string|max:30',
            'status_perkawinan' => ['nullable', 'string', 'max:50', new ActiveBusinessOption('marital_status')],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:50', new ActiveBusinessOption('last_education')],
            'foto' => 'nullable|string',
            'unit_id' => 'nullable|uuid|exists:education_units,id',
            'jabatan_id' => 'nullable|uuid|exists:positions,id',
            'status_pegawai' => ['nullable', 'string', 'max:50', new ActiveBusinessOption('employee_status')],
            'tanggal_masuk' => 'nullable|date',
            'tanggal_keluar' => 'nullable|date',
            'status' => 'nullable|string|max:30',
            'no_hp' => 'nullable|string|max:32',
            'email' => 'nullable|email|max:255',
            'alamat' => 'nullable|string',
            'provinsi' => 'nullable|string',
            'kota' => 'nullable|string',
            'kecamatan' => 'nullable|string',
            'kelurahan' => 'nullable|string',
            'kode_pos' => 'nullable|string|max:10',
            'user_id' => 'nullable|uuid|exists:users,id',
            'role_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
            'metadata.status_perkawinan' => ['nullable', 'string', 'max:50', new ActiveBusinessOption('marital_status')],
            'metadata.pendidikan_terakhir' => ['nullable', 'string', 'max:50', new ActiveBusinessOption('last_education')],
            'teachings' => 'nullable|array',
        ];
    }
}
