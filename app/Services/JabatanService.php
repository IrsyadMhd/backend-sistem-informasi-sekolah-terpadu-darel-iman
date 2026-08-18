<?php

namespace App\Services;

use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class JabatanService
{
    /**
     * Dapatkan daftar jabatan berpaginasi.
     */
    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        $query = Position::with(['unitSekolah', 'atasanLangsung', 'atasanPegawai', 'roleSistem'])
            ->withCount('employees')
            ->filter($filters);

        $allowedSorts = ['code', 'name', 'level_jabatan', 'urutan', 'created_at', 'is_active'];
        if (! in_array($orderBy, $allowedSorts)) {
            $orderBy = 'urutan';
        }

        return $query->orderBy($orderBy, strtolower($orderDir) === 'desc' ? 'desc' : 'asc')
            ->paginate($perPage);
    }

    /**
     * Dapatkan ringkasan statistik master jabatan.
     */
    public function dapatkanStatistik(): array
    {
        $total = Position::count();
        $aktif = Position::where('is_active', true)->count();
        $nonaktif = Position::where('is_active', false)->count();
        $tampilStruktur = Position::where('tampil_struktur', true)->count();
        $bolehLogin = Position::where('boleh_login', true)->count();
        $trash = Position::onlyTrashed()->count();

        return [
            'total_jabatan' => $total,
            'aktif' => $aktif,
            'nonaktif' => $nonaktif,
            'tampil_struktur' => $tampilStruktur,
            'boleh_login' => $bolehLogin,
            'terhapus' => $trash,
        ];
    }

    /**
     * Dapatkan opsi pilihan master data untuk dropdown form.
     */
    public function dapatkanOpsiMaster(): array
    {
        $units = EducationUnit::select('id', 'name', 'code', 'level')
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'nama' => $u->name,
                'kode' => $u->code,
            ]);

        // Atasan langsung diambil dari tabel pegawai
        $pegawaiList = Employee::with(['position'])
            ->where('status', 'Aktif')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'niy', 'jabatan_id'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'nama_pegawai' => $p->nama_lengkap,
                'niy' => $p->niy,
                'nama_jabatan' => $p->position?->name,
            ]);

        $roles = Role::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
            ]);

        $levelMap = [];
        foreach (Position::LEVEL_JABATAN_MAP as $key => $val) {
            $levelMap[] = [
                'value' => $key,
                'label' => "Level {$key} - {$val}",
                'nama' => $val,
            ];
        }

        return [
            'unit_sekolah' => $units,
            'atasan_langsung' => $pegawaiList,
            'role_sistem' => $roles,
            'level_jabatan' => $levelMap,
            'satuan_kerja' => collect(Position::SATUAN_KERJA_OPTIONS)
                ->map(fn ($label, $value) => compact('value', 'label'))
                ->values(),
            'scope_akses' => collect(Position::SCOPE_AKSES_OPTIONS)
                ->map(fn ($label, $value) => compact('value', 'label'))
                ->values(),
        ];
    }

    /**
     * Simpan data jabatan baru.
     */
    public function simpan(array $data, ?string $userId = null): Position
    {
        return DB::transaction(function () use ($data, $userId) {
            $kode = ! empty($data['kode_jabatan']) ? $data['kode_jabatan'] : Position::generateKode();

            $isActive = true;
            if (isset($data['status'])) {
                $isActive = ($data['status'] === 'Aktif');
            } elseif (isset($data['is_active'])) {
                $isActive = (bool) $data['is_active'];
            }

            $jabatan = Position::create([
                'code' => $kode,
                'name' => $data['nama_jabatan'],
                'satuan_kerja' => $data['satuan_kerja'],
                'unit_sekolah_id' => $data['unit_sekolah_id'] ?? null,
                'level_jabatan' => $data['level_jabatan'] ?? 8,
                'atasan_langsung_id' => $data['atasan_langsung_id'] ?? null,
                'atasan_pegawai_id' => $data['atasan_pegawai_id'] ?? null,
                'role_sistem_id' => $data['role_sistem_id'] ?? null,
                'scope_akses' => $data['scope_akses'],
                'urutan' => $data['urutan'] ?? 0,
                'warna' => $data['warna'] ?? '#3B82F6',
                'ikon' => $data['ikon'] ?? 'UserCheck',
                'description' => $data['deskripsi'] ?? $data['description'] ?? null,
                'is_active' => $isActive,
                'tampil_struktur' => $data['tampil_struktur'] ?? true,
                'boleh_login' => $data['boleh_login'] ?? false,
                'metadata' => $data['metadata'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            return $jabatan->load(['unitSekolah', 'atasanLangsung', 'atasanPegawai', 'roleSistem']);
        });
    }

    /**
     * Cari detail jabatan berdasarkan ID.
     */
    public function cariBerdasarkanId(string $id): ?Position
    {
        return Position::withTrashed()
            ->with(['unitSekolah', 'atasanLangsung', 'atasanPegawai', 'roleSistem', 'creator', 'updater'])
            ->withCount('employees')
            ->find($id);
    }

    /**
     * Perbarui data jabatan.
     */
    public function ubah(string $id, array $data, ?string $userId = null): Position
    {
        $jabatan = Position::withTrashed()->findOrFail($id);

        return DB::transaction(function () use ($jabatan, $data, $userId) {
            $payload = [];

            if (array_key_exists('kode_jabatan', $data) && ! empty($data['kode_jabatan'])) {
                $payload['code'] = $data['kode_jabatan'];
            }
            if (array_key_exists('nama_jabatan', $data)) {
                $payload['name'] = $data['nama_jabatan'];
            }
            if (array_key_exists('unit_sekolah_id', $data)) {
                $payload['unit_sekolah_id'] = $data['unit_sekolah_id'];
            }
            if (array_key_exists('satuan_kerja', $data)) {
                $payload['satuan_kerja'] = $data['satuan_kerja'];
            }
            if (array_key_exists('level_jabatan', $data)) {
                $payload['level_jabatan'] = (int) $data['level_jabatan'];
            }
            if (array_key_exists('atasan_langsung_id', $data)) {
                $payload['atasan_langsung_id'] = $data['atasan_langsung_id'];
            }
            if (array_key_exists('atasan_pegawai_id', $data)) {
                $payload['atasan_pegawai_id'] = $data['atasan_pegawai_id'] ?: null;
            }
            if (array_key_exists('role_sistem_id', $data)) {
                $payload['role_sistem_id'] = $data['role_sistem_id'];
            }
            if (array_key_exists('scope_akses', $data)) {
                $payload['scope_akses'] = $data['scope_akses'];
            }
            if (array_key_exists('urutan', $data)) {
                $payload['urutan'] = (int) $data['urutan'];
            }
            if (array_key_exists('warna', $data)) {
                $payload['warna'] = $data['warna'];
            }
            if (array_key_exists('ikon', $data)) {
                $payload['ikon'] = $data['ikon'];
            }
            if (array_key_exists('deskripsi', $data)) {
                $payload['description'] = $data['deskripsi'];
            } elseif (array_key_exists('description', $data)) {
                $payload['description'] = $data['description'];
            }
            if (array_key_exists('status', $data)) {
                $payload['is_active'] = ($data['status'] === 'Aktif');
            } elseif (array_key_exists('is_active', $data)) {
                $payload['is_active'] = (bool) $data['is_active'];
            }
            if (array_key_exists('tampil_struktur', $data)) {
                $payload['tampil_struktur'] = (bool) $data['tampil_struktur'];
            }
            if (array_key_exists('boleh_login', $data)) {
                $payload['boleh_login'] = (bool) $data['boleh_login'];
            }
            if (array_key_exists('metadata', $data)) {
                $payload['metadata'] = $data['metadata'];
            }

            if ($userId) {
                $payload['updated_by'] = $userId;
            }

            $jabatan->update($payload);

            return $jabatan->load(['unitSekolah', 'atasanLangsung', 'atasanPegawai', 'roleSistem']);
        });
    }

    /**
     * Hapus data jabatan (Soft Delete).
     */
    public function hapus(string $id): bool
    {
        $jabatan = Position::find($id);
        if (! $jabatan) {
            return false;
        }

        return (bool) $jabatan->delete();
    }

    /**
     * Pulihkan data jabatan terhapus.
     */
    public function pulihkan(string $id): bool
    {
        $jabatan = Position::onlyTrashed()->find($id);
        if (! $jabatan) {
            return false;
        }

        return (bool) $jabatan->restore();
    }

    /**
     * Impor kumpulan data jabatan.
     */
    public function prosesImport(array $rows, ?string $userId = null): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                if (empty($row['nama_jabatan'])) {
                    $gagal++;
                    $errors[] = 'Baris '.($index + 1).': Nama jabatan kosong.';

                    continue;
                }

                $this->simpan([
                    'kode_jabatan' => $row['kode_jabatan'] ?? null,
                    'nama_jabatan' => $row['nama_jabatan'],
                    'satuan_kerja' => $row['satuan_kerja'] ?? 'Unit Pendidikan',
                    'scope_akses' => $row['scope_akses'] ?? 'unit_sendiri',
                    'level_jabatan' => $row['level_jabatan'] ?? 8,
                    'unit_sekolah_id' => $row['unit_sekolah_id'] ?? null,
                    'urutan' => $row['urutan'] ?? 0,
                    'warna' => $row['warna'] ?? '#3B82F6',
                    'ikon' => $row['ikon'] ?? 'UserCheck',
                    'deskripsi' => $row['deskripsi'] ?? null,
                    'status' => $row['status'] ?? 'Aktif',
                    'tampil_struktur' => isset($row['tampil_struktur']) ? (bool) $row['tampil_struktur'] : true,
                    'boleh_login' => isset($row['boleh_login']) ? (bool) $row['boleh_login'] : false,
                ], $userId);

                $berhasil++;
            } catch (\Exception $e) {
                $gagal++;
                $errors[] = 'Baris '.($index + 1).': '.$e->getMessage();
            }
        }

        return [
            'berhasil' => $berhasil,
            'gagal' => $gagal,
            'errors' => $errors,
        ];
    }

    /**
     * Ekspor data jabatan tanpa pagination.
     */
    public function eksporData(array $filters = []): array
    {
        return Position::with(['unitSekolah', 'atasanLangsung', 'atasanPegawai', 'roleSistem'])
            ->withCount('employees')
            ->filter($filters)
            ->orderBy('urutan')
            ->get()
            ->map(function ($j) {
                return [
                    'kode_jabatan' => $j->code,
                    'nama_jabatan' => $j->name,
                    'satuan_kerja' => $j->satuan_kerja ?? '-',
                    'level_jabatan' => $j->level_jabatan,
                    'level_label' => Position::LEVEL_JABATAN_MAP[$j->level_jabatan] ?? "Level {$j->level_jabatan}",
                    'unit_sekolah' => $j->unitSekolah?->name ?? '-',
                    'atasan_langsung' => $j->atasanPegawai?->nama_lengkap ?? $j->atasanLangsung?->name ?? '-',
                    'role_sistem' => $j->roleSistem?->name ?? '-',
                    'scope_akses' => $j->scope_akses ?? '-',
                    'urutan' => $j->urutan,
                    'status' => $j->is_active ? 'Aktif' : 'Nonaktif',
                    'tampil_struktur' => $j->tampil_struktur ? 'Ya' : 'Tidak',
                    'boleh_login' => $j->boleh_login ? 'Ya' : 'Tidak',
                    'jumlah_pegawai' => $j->employees_count,
                    'deskripsi' => $j->description ?? '-',
                ];
            })
            ->toArray();
    }
}
