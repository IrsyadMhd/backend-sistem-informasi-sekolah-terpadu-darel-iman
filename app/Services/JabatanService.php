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

        if (array_key_exists('allowed_unit_ids', $filters)) {
            $query->whereIn('unit_sekolah_id', $filters['allowed_unit_ids']);
        }

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
    public function dapatkanStatistik(array $filters = []): array
    {
        $query = fn () => Position::query()->when(
            array_key_exists('allowed_unit_ids', $filters),
            fn ($q) => $q->whereIn('unit_sekolah_id', $filters['allowed_unit_ids'])
        );

        $total = $query()->count();
        $aktif = $query()->where('is_active', true)->count();
        $nonaktif = $query()->where('is_active', false)->count();
        $tampilStruktur = $query()->where('tampil_struktur', true)->count();
        $bolehLogin = $query()->where('boleh_login', true)->count();
        $trash = $query()->onlyTrashed()->count();

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
    public function dapatkanOpsiMaster(?array $allowedUnitIds = null): array
    {
        $units = EducationUnit::select('id', 'name', 'code', 'level')
            ->when($allowedUnitIds !== null, fn ($query) => $query->whereIn('id', $allowedUnitIds))
            ->orderBy('name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'nama' => $u->name,
                'kode' => $u->code,
            ]);

        // Atasan langsung diambil dari tabel pegawai
        $pegawaiList = Employee::with(['position'])
            ->when($allowedUnitIds !== null, fn ($query) => $query->whereIn('unit_id', $allowedUnitIds))
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
     * Hapus banyak data jabatan (Bulk Soft Delete).
     */
    public function hapusBanyak(array $ids): array
    {
        $berhasil = 0;
        $gagal = 0;

        foreach ($ids as $id) {
            $jabatan = Position::find($id);
            if ($jabatan && $jabatan->delete()) {
                $berhasil++;
            } else {
                $gagal++;
            }
        }

        return compact('berhasil', 'gagal');
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
     * Impor kumpulan data jabatan dengan dukungan format ekspor (round-trip).
     */
    public function prosesImport(array $rows, ?string $userId = null): array
    {
        $berhasil = 0;
        $gagal = 0;
        $errors = [];

        // Preload maps untuk lookup cepat
        $unitCache = EducationUnit::all();
        $roleCache = Role::all();
        $employeeCache = Employee::select('id', 'nama_lengkap')->get();

        $parseBoolean = function ($val, $default = true) {
            if (is_null($val) || $val === '' || $val === '-') {
                return $default;
            }
            if (is_bool($val)) {
                return $val;
            }
            if (is_numeric($val)) {
                return (int) $val === 1;
            }
            $s = strtolower(trim((string) $val));
            if (in_array($s, ['ya', 'y', 'true', '1', 'aktif', 'active', 'yes'], true)) {
                return true;
            }
            if (in_array($s, ['tidak', 't', 'false', '0', 'nonaktif', 'inactive', 'no'], true)) {
                return false;
            }

            return $default;
        };

        foreach ($rows as $index => $row) {
            try {
                $nama = trim((string) ($row['nama_jabatan'] ?? $row['nama'] ?? $row['name'] ?? ''));
                if (empty($nama)) {
                    $gagal++;
                    $errors[] = 'Baris '.($index + 1).': Nama jabatan kosong.';

                    continue;
                }

                $kode = trim((string) ($row['kode_jabatan'] ?? $row['kode'] ?? $row['code'] ?? ''));
                $kode = $kode !== '' && $kode !== '-' ? strtoupper($kode) : null;

                // Satuan Kerja
                $satuanKerja = trim((string) ($row['satuan_kerja'] ?? 'Unit Pendidikan'));
                if (empty($satuanKerja) || $satuanKerja === '-') {
                    $satuanKerja = 'Unit Pendidikan';
                }

                // Scope Akses
                $scopeAkses = trim((string) ($row['scope_akses'] ?? 'unit_sendiri'));
                if (empty($scopeAkses) || $scopeAkses === '-' || ! array_key_exists($scopeAkses, Position::SCOPE_AKSES_OPTIONS)) {
                    $scopeAkses = 'unit_sendiri';
                }

                // Level Jabatan
                $level = $row['level_jabatan'] ?? $row['level'] ?? 8;
                if (is_string($level) && preg_match('/(\d+)/', $level, $m)) {
                    $level = (int) $m[1];
                }
                $level = (int) $level;
                if ($level < 1 || $level > 10) {
                    $level = 8;
                }

                // Resolusi Unit Sekolah ID
                $unitSekolahId = $row['unit_sekolah_id'] ?? $row['unit_id'] ?? null;
                if (empty($unitSekolahId) && ! empty($row['unit_sekolah']) && $row['unit_sekolah'] !== '-') {
                    $unitText = strtolower(trim((string) $row['unit_sekolah']));
                    $foundUnit = $unitCache->first(function ($u) use ($unitText) {
                        return strtolower($u->name) === $unitText || strtolower($u->code ?? '') === $unitText;
                    });
                    if ($foundUnit) {
                        $unitSekolahId = $foundUnit->id;
                    }
                }

                // Resolusi Role Sistem ID
                $roleSistemId = $row['role_sistem_id'] ?? null;
                if (empty($roleSistemId) && ! empty($row['role_sistem']) && $row['role_sistem'] !== '-') {
                    $roleText = strtolower(trim((string) $row['role_sistem']));
                    $foundRole = $roleCache->first(function ($r) use ($roleText) {
                        return strtolower($r->name) === $roleText;
                    });
                    if ($foundRole) {
                        $roleSistemId = $foundRole->id;
                    }
                }

                // Resolusi Atasan Pegawai
                $atasanPegawaiId = $row['atasan_pegawai_id'] ?? null;
                if (empty($atasanPegawaiId) && ! empty($row['atasan_langsung']) && $row['atasan_langsung'] !== '-') {
                    $atasanText = strtolower(trim((string) $row['atasan_langsung']));
                    $foundEmp = $employeeCache->first(function ($e) use ($atasanText) {
                        return strtolower($e->nama_lengkap) === $atasanText;
                    });
                    if ($foundEmp) {
                        $atasanPegawaiId = $foundEmp->id;
                    }
                }

                $deskripsi = $row['deskripsi'] ?? $row['description'] ?? null;
                if ($deskripsi === '-') {
                    $deskripsi = null;
                }

                $statusVal = $row['status'] ?? 'Aktif';
                $isActive = $parseBoolean($statusVal, true);
                $status = $isActive ? 'Aktif' : 'Nonaktif';

                $tampilStruktur = $parseBoolean($row['tampil_struktur'] ?? null, true);
                $bolehLogin = $parseBoolean($row['boleh_login'] ?? null, false);

                $payload = [
                    'kode_jabatan' => $kode,
                    'nama_jabatan' => $nama,
                    'satuan_kerja' => $satuanKerja,
                    'scope_akses' => $scopeAkses,
                    'level_jabatan' => $level,
                    'unit_sekolah_id' => $unitSekolahId,
                    'role_sistem_id' => $roleSistemId,
                    'atasan_pegawai_id' => $atasanPegawaiId,
                    'atasan_langsung_id' => $row['atasan_langsung_id'] ?? null,
                    'urutan' => isset($row['urutan']) && is_numeric($row['urutan']) ? (int) $row['urutan'] : 0,
                    'warna' => ! empty($row['warna']) && $row['warna'] !== '-' ? $row['warna'] : '#3B82F6',
                    'ikon' => ! empty($row['ikon']) && $row['ikon'] !== '-' ? $row['ikon'] : 'UserCheck',
                    'deskripsi' => $deskripsi,
                    'status' => $status,
                    'is_active' => $isActive,
                    'tampil_struktur' => $tampilStruktur,
                    'boleh_login' => $bolehLogin,
                ];

                // Upsert: jika kode_jabatan sudah ada di DB, perbarui record lama
                $existing = null;
                if ($kode) {
                    $existing = Position::withTrashed()->where('code', $kode)->first();
                }

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $this->ubah($existing->id, $payload, $userId);
                } else {
                    $this->simpan($payload, $userId);
                }

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
        $query = Position::with(['unitSekolah', 'atasanLangsung', 'atasanPegawai', 'roleSistem'])
            ->withCount('employees')
            ->filter($filters);

        if (array_key_exists('allowed_unit_ids', $filters)) {
            $query->whereIn('unit_sekolah_id', $filters['allowed_unit_ids']);
        }

        return $query
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
