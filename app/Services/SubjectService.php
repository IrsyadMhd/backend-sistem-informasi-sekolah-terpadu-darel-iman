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
    public function dapatkanStatistik(): array
    {
        return $this->subjectRepository->getStats();
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

    public function prosesImport(array $rows, ?string $userId = null): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $payload = [
                'unit_pendidikan_id' => $row['unit_pendidikan_id'] ?? null,
                'kurikulum_id' => $row['kurikulum_id'] ?? null,
                'kode_mapel' => trim((string) ($row['kode_mapel'] ?? $row['code'] ?? '')),
                'nama_mapel' => trim((string) ($row['nama_mapel'] ?? $row['name'] ?? '')),
                'kelompok_mapel' => $row['kelompok_mapel'] ?? 'Kelompok A',
                'kategori' => $row['kategori'] ?? 'Wajib',
                'jenjang' => $row['jenjang'] ?? 'SD',
                'tingkat_kelas' => $row['tingkat_kelas'] ?? 'All',
                'jam_pelajaran' => $row['jam_pelajaran'] ?? 2,
                'kkm' => $row['kkm'] ?? 75,
                'status' => $this->toBoolean($row['status'] ?? true),
                'deskripsi' => $row['deskripsi'] ?? $row['description'] ?? null,
            ];
            $payload['code'] = $payload['kode_mapel'];
            $payload['name'] = $payload['nama_mapel'];

            $validation = Validator::make($payload, [
                'unit_pendidikan_id' => ['required', 'uuid', 'exists:education_units,id'],
                'kurikulum_id' => ['required', 'uuid', 'exists:master_kurikulum,id'],
                'kode_mapel' => ['required', 'string', 'max:50'],
                'nama_mapel' => ['required', 'string', 'max:150'],
                'jam_pelajaran' => ['required', 'integer', 'min:1', 'max:40'],
                'kkm' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);

            if ($validation->fails()) {
                $errors[] = ['row' => $line, 'message' => $validation->errors()->first()];
                continue;
            }

            try {
                $kurikulum = MasterKurikulum::query()
                    ->whereKey($payload['kurikulum_id'])
                    ->where('unit_pendidikan_id', $payload['unit_pendidikan_id'])
                    ->first();
                if (! $kurikulum) {
                    throw new \RuntimeException('Kurikulum tidak berasal dari unit pendidikan yang dipilih.');
                }

                DB::transaction(function () use ($payload, $userId, &$created, &$updated): void {
                    $subject = Subject::withTrashed()
                        ->where('code', $payload['code'])
                        ->first();
                    if ($subject?->trashed()) {
                        $subject->restore();
                    }

                    if ($subject) {
                        $subject->update(array_merge($payload, ['updated_by' => $userId]));
                        $updated++;
                    } else {
                        Subject::create(array_merge($payload, ['created_by' => $userId, 'updated_by' => $userId]));
                        $created++;
                    }
                });
            } catch (\Throwable $error) {
                $errors[] = ['row' => $line, 'message' => $error->getMessage()];
            }
        }

        return [
            'total' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'imported_rows' => $created + $updated,
            'errors' => $errors,
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
