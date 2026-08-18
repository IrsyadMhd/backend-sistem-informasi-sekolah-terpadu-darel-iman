<?php

namespace App\Http\Resources\V1;

use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JabatanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_jabatan' => $this->code,
            'code' => $this->code,
            'nama_jabatan' => $this->name,
            'name' => $this->name,
            'satuan_kerja' => $this->satuan_kerja,
            'unit_sekolah_id' => $this->unit_sekolah_id,
            'unit_sekolah' => $this->relationLoaded('unitSekolah') && $this->unitSekolah ? [
                'id' => $this->unitSekolah->id,
                'nama' => $this->unitSekolah->nama_unit ?? $this->unitSekolah->name,
                'kode' => $this->unitSekolah->kode_unit ?? $this->unitSekolah->code,
            ] : null,
            'level_jabatan' => $this->level_jabatan ?? 8,
            'level_label' => Position::LEVEL_JABATAN_MAP[$this->level_jabatan ?? 8] ?? 'Guru',
            'atasan_langsung_id' => $this->atasan_langsung_id,
            'atasan_langsung' => $this->relationLoaded('atasanLangsung') && $this->atasanLangsung ? [
                'id' => $this->atasanLangsung->id,
                'kode_jabatan' => $this->atasanLangsung->code,
                'nama_jabatan' => $this->atasanLangsung->name,
            ] : null,
            'atasan_pegawai_id' => $this->atasan_pegawai_id,
            'atasan_pegawai' => $this->relationLoaded('atasanPegawai') && $this->atasanPegawai ? [
                'id' => $this->atasanPegawai->id,
                'nama_pegawai' => $this->atasanPegawai->nama_lengkap,
                'niy' => $this->atasanPegawai->niy,
            ] : null,
            'role_sistem_id' => $this->role_sistem_id,
            'role_sistem' => $this->relationLoaded('roleSistem') && $this->roleSistem ? [
                'id' => $this->roleSistem->id,
                'name' => $this->roleSistem->name,
            ] : null,
            'scope_akses' => $this->scope_akses,
            'scope_akses_label' => Position::SCOPE_AKSES_OPTIONS[$this->scope_akses] ?? $this->scope_akses,
            'urutan' => $this->urutan ?? 0,
            'warna' => $this->warna ?? '#3B82F6',
            'ikon' => $this->ikon ?? 'UserCheck',
            'deskripsi' => $this->deskripsi ?? $this->description,
            'description' => $this->description ?? $this->deskripsi,
            'status' => $this->is_active ? 'Aktif' : 'Nonaktif',
            'is_active' => (bool) $this->is_active,
            'tampil_struktur' => (bool) ($this->tampil_struktur ?? true),
            'boleh_login' => (bool) ($this->boleh_login ?? false),
            'jumlah_pegawai' => $this->relationLoaded('employees') ? $this->employees->count() : ($this->employees_count ?? 0),
            'metadata' => $this->metadata,
            'terhapus' => $this->trashed(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_by' => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null,
            'updated_by' => $this->updater ? [
                'id' => $this->updater->id,
                'name' => $this->updater->name,
            ] : null,
        ];
    }
}
