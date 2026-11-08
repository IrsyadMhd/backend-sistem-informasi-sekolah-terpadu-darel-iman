<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\MasterKurikulum;
use App\Repositories\Contracts\SubjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Class SubjectService
 * Layer bisnis logic untuk pengelolaan data Master Mata Pelajaran (Subject).
 */
class SubjectService
{
    public function __construct(
        protected SubjectRepositoryInterface $subjectRepository
    ) {}

    /**
     * Dapatkan daftar mata pelajaran terpaginasi.
     */
    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'created_at', string $orderDir = 'desc'): LengthAwarePaginator
    {
        return $this->subjectRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    /**
     * Cari detail mata pelajaran berdasarkan ID UUID.
     */
    public function cariBerdasarkanId(string $id): ?Subject
    {
        return $this->subjectRepository->findById($id);
    }

    /**
     * Simpan data mata pelajaran baru.
     */
    public function simpan(array $data): Subject
    {
        Log::info('Membuat mata pelajaran baru', ['code' => $data['code'] ?? null, 'name' => $data['name'] ?? null]);

        return $this->subjectRepository->create($data);
    }

    /**
     * Perbarui data mata pelajaran.
     */
    public function ubah(string $id, array $data): ?Subject
    {
        Log::info("Memperbarui mata pelajaran ID: {$id}", $data);

        return $this->subjectRepository->update($id, $data);
    }

    /**
     * Hapus data mata pelajaran (Soft Delete).
     */
    public function hapus(string $id): bool
    {
        Log::info("Menghapus mata pelajaran ID: {$id}");

        $subject = $this->subjectRepository->findById($id);
        if (! $subject) {
            return false;
        }

        // Rapor is an aggregate per student and academic period; it has no
        // subject foreign key. Subject usage is guarded by student grades.
        foreach (['schedules', 'capaianPembelajaran', 'tujuanPembelajaran', 'modulAjar', 'materi', 'penugasan', 'kisiKisi', 'bankSoal', 'cbt', 'penilaian', 'teachers', 'classes', 'rombel'] as $relation) {
            if ($subject->{$relation}()->exists()) {
                throw ValidationException::withMessages([
                    'subject' => 'Mata pelajaran sudah digunakan pada data akademik dan tidak dapat dihapus. Nonaktifkan data jika tidak lagi digunakan.',
                ]);
            }
        }

        return $this->subjectRepository->delete($id);
    }

    /**
     * Pulihkan data mata pelajaran dari sampah.
     */
    public function pulihkan(string $id): bool
    {
        Log::info("Memulihkan mata pelajaran ID: {$id}");

        return $this->subjectRepository->restore($id);
    }

    /**
     * Dapatkan statistik ringkasan mata pelajaran.
     */
    public function dapatkanStatistik(?string $unitId = null): array
    {
        return $this->subjectRepository->getStats($unitId);
    }

    /**
     * Dapatkan opsi dropdown master mata pelajaran.
     */
    public function dapatkanOpsiDropdown(array $filters = []): Collection
    {
        return $this->subjectRepository->getDropdownOptions($filters);
    }

    /**
     * Ubah status secara massal (Bulk Status Toggle).
     */
    public function ubahStatusMassal(array $ids, bool $status): int
    {
        Log::info('Memperbarui status mata pelajaran secara massal', ['ids' => $ids, 'status' => $status]);

        return $this->subjectRepository->bulkStatusUpdate($ids, $status);
    }

    /**
     * Hapus data mata pelajaran secara massal (Bulk Delete).
     */
    public function hapusMassal(array $ids): int
    {
        Log::info('Menghapus data mata pelajaran secara massal', ['ids' => $ids]);

        return $this->subjectRepository->bulkDelete($ids);
    }

    /**
     * Dapatkan data seluruh mata pelajaran untuk ekspor Excel / PDF.
     */
    public function dapatkanDataEkspor(array $filters = []): Collection
    {
        return $this->subjectRepository->getAllFilteredForExport($filters);
    }

    public function prosesImport(array $rows, ?string $userId = null, bool $overwrite = false): array
    {
        $created = 0;
        $updated = 0;
        $duplikat = 0;
        $gagal = 0;
        $errors = [];
        $duplicates = [];

        // Preload default active curriculum and education units if needed
        $defaultUnit = \App\Models\EducationUnit::first();
        $defaultKurikulum = MasterKurikulum::first();

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $unitId = $row['unit_pendidikan_id'] ?? null;
            if (!$unitId || !\Illuminate\Support\Str::isUuid((string)$unitId)) {
                $unitId = $defaultUnit?->id;
            }

            $kurikulumId = $row['kurikulum_id'] ?? null;
            if (!$kurikulumId || !\Illuminate\Support\Str::isUuid((string)$kurikulumId)) {
                $kurikulumId = MasterKurikulum::where('unit_pendidikan_id', $unitId)->first()?->id ?? $defaultKurikulum?->id;
            }

            $payload = [
                'unit_pendidikan_id' => $unitId,
                'kurikulum_id' => $kurikulumId,
                'kode_mapel' => trim((string) ($row['kode_mapel'] ?? $row['code'] ?? $row['kode'] ?? '')),
                'nama_mapel' => trim((string) ($row['nama_mapel'] ?? $row['name'] ?? $row['nama'] ?? '')),
                'kelompok_mapel' => $row['kelompok_mapel'] ?? 'Pelajaran Wajib',
                'kategori' => $row['kategori'] ?? 'Wajib',
                'jenjang' => $row['jenjang'] ?? 'SD',
                'tingkat_kelas' => $row['tingkat_kelas'] ?? 'All',
                'jam_pelajaran' => !empty($row['jam_pelajaran']) ? (int)$row['jam_pelajaran'] : 2,
                'kkm' => !empty($row['kkm']) ? (float)$row['kkm'] : 75,
                'status' => $this->toBoolean($row['status'] ?? true),
                'deskripsi' => $row['deskripsi'] ?? $row['description'] ?? null,
            ];
            $payload['code'] = $payload['kode_mapel'];
            $payload['name'] = $payload['nama_mapel'];

            $validation = Validator::make($payload, [
                'kode_mapel' => ['required', 'string', 'max:50'],
                'nama_mapel' => ['required', 'string', 'max:150'],
                'jam_pelajaran' => ['required', 'integer', 'min:1', 'max:40'],
                'kkm' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            if ($validation->fails()) {
                $gagal++;
                $errors[] = ['row' => $line, 'message' => $validation->errors()->first()];
                continue;
            }

            try {
                $subject = Subject::withTrashed()
                    ->where('code', $payload['code'])
                    ->first();

                if ($subject) {
                    if ($overwrite) {
                        if ($subject->trashed()) {
                            $subject->restore();
                        }
                        $subject->update(array_merge($payload, ['updated_by' => $userId]));
                        $updated++;
                        $duplicates[] = [
                            'baris' => $line,
                            'kode' => $payload['code'],
                            'nama' => $payload['name'],
                            'status' => 'Ditimpa',
                            'keterangan' => 'Mata pelajaran berhasil diperbarui (ditimpa).',
                        ];
                    } else {
                        $duplikat++;
                        $duplicates[] = [
                            'baris' => $line,
                            'kode' => $payload['code'],
                            'nama' => $payload['name'],
                            'status' => 'Dilewati',
                            'keterangan' => "Kode mata pelajaran '{$payload['code']}' sudah ada (mode timpa tidak aktif).",
                        ];
                    }
                } else {
                    Subject::create(array_merge($payload, ['created_by' => $userId, 'updated_by' => $userId]));
                    $created++;
                }
            } catch (\Throwable $error) {
                $gagal++;
                $errors[] = ['row' => $line, 'message' => $error->getMessage()];
            }
        }

        return [
            'total' => count($rows),
            'berhasil' => $created,
            'diperbarui' => $updated,
            'duplikat' => $duplikat,
            'gagal' => $gagal,
            'overwrite' => $overwrite,
            'imported_rows' => $created + $updated,
            'duplicates' => $duplicates,
            'errors' => array_map(fn($e) => "Baris {$e['row']}: {$e['message']}", $errors),
        ];
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'ya', 'yes', 'aktif', 'active'], true);
    }
}
