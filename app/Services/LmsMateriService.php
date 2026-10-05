<?php

namespace App\Services;

use App\Models\LmsMateri;
use App\Models\LmsModulAjar;
use App\Repositories\Contracts\LmsMateriRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LmsMateriService
{
    public function __construct(
        protected LmsMateriRepositoryInterface $materiRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        return $this->materiRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id, bool $withTrashed = false): ?LmsMateri
    {
        return $this->materiRepository->findById($id, $withTrashed);
    }

    public function simpan(array $data, ?UploadedFile $file = null): LmsMateri
    {
        if ($file) {
            $path = $file->store('materi_files', 'public');
            $data['file'] = $path;
        } elseif (!empty($data['file_url'])) {
            $data['file'] = $data['file_url'];
        }

        if (empty($data['tipe'])) {
            $data['tipe'] = 'teks';
        }

        if (empty($data['status'])) {
            $data['status'] = 'aktif';
        }

        if (! isset($data['urutan']) || $data['urutan'] === null) {
            $maxUrutan = LmsMateri::where('modul_ajar_id', $data['modul_ajar_id'])->max('urutan') ?? 0;
            $data['urutan'] = $maxUrutan + 1;
        }

        // Set inherited metadata from Modul Ajar if available
        $modul = LmsModulAjar::find($data['modul_ajar_id']);
        if ($modul) {
            if (empty($data['mata_pelajaran_id'])) {
                $data['mata_pelajaran_id'] = $modul->mata_pelajaran_id;
            }
            if (empty($data['guru_id'])) {
                $data['guru_id'] = $modul->guru_id;
            }
        }

        $materi = $this->materiRepository->create($data);

        Log::info('[AUDIT LOG] Membuat Materi Pembelajaran Baru', [
            'materi_id' => $materi->id,
            'modul_ajar_id' => $materi->modul_ajar_id,
            'judul' => $materi->judul,
            'tipe' => $materi->tipe,
            'user_id' => auth()->id(),
        ]);

        return $materi;
    }

    public function ubah(string $id, array $data, ?UploadedFile $file = null): ?LmsMateri
    {
        $existing = $this->materiRepository->findById($id);
        if (! $existing) {
            return null;
        }

        if ($file) {
            if ($existing->file && !str_starts_with($existing->file, 'http') && !str_starts_with($existing->file, '/storage/') && Storage::disk('public')->exists($existing->file)) {
                Storage::disk('public')->delete($existing->file);
            }
            $path = $file->store('materi_files', 'public');
            $data['file'] = $path;
        } elseif (array_key_exists('file_url', $data) && !empty($data['file_url'])) {
            $data['file'] = $data['file_url'];
        }

        $updated = $this->materiRepository->update($id, $data);

        Log::info('[AUDIT LOG] Memperbarui Materi Pembelajaran', [
            'materi_id' => $id,
            'judul_sebelum' => $existing->judul,
            'judul_sesudah' => $updated->judul ?? $existing->judul,
            'user_id' => auth()->id(),
        ]);

        return $updated;
    }

    public function hapus(string $id): bool
    {
        $materi = $this->materiRepository->findById($id);
        if (! $materi) {
            return false;
        }

        Log::info('[AUDIT LOG] Menghapus (Soft Delete) Materi Pembelajaran', [
            'materi_id' => $id,
            'judul' => $materi->judul,
            'user_id' => auth()->id(),
        ]);

        return $this->materiRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('[AUDIT LOG] Memulihkan Materi Pembelajaran', [
            'materi_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->materiRepository->restore($id);
    }

    public function statistik(array $filters = []): array
    {
        return $this->materiRepository->getStats($filters);
    }

    public function opsi(?\App\Models\User $user = null, ?string $search = null, ?int $limit = null, ?string $includeId = null, ?string $unitId = null, array $unitIds = []): array
    {
        $query = LmsModulAjar::with('subject')
            ->select('id', 'kode_modul', 'judul_modul', 'mata_pelajaran_id', 'fase', 'guru_id', 'unit_pendidikan_id');

        if (! empty($unitId)) {
            $query->where('unit_pendidikan_id', $unitId);
        } elseif (! empty($unitIds)) {
            $query->whereIn('unit_pendidikan_id', $unitIds);
        }

        $isGlobalOrLeadership = $user && (
            $user->hasAnyRole([
                'Super Admin', 'super_admin', 'Superadmin', 'super-admin',
                'Admin', 'admin',
                'Yayasan', 'Ketua Yayasan', 'ketua_yayasan',
                'pengurus_yayasan', 'Pengurus Yayasan',
                'Sekretaris Yayasan', 'sekretaris_yayasan',
                'Bendahara Yayasan', 'bendahara_yayasan',
                'Kepala Bidang Pendidikan', 'Divisi Pendidikan', 'divisi_pendidikan',
                'Kepala Sekolah', 'kepala_sekolah', 'Kepsek', 'kepsek',
                'Waka Kurikulum', 'waka_kurikulum', 'Waka Kesiswaan', 'waka_kesiswaan',
                'Tata Usaha', 'tata_usaha', 'TU', 'tu',
            ])
        );

        if ($user && ! $isGlobalOrLeadership && $user->hasRole(['Guru', 'guru', 'Guru Mata Pelajaran', 'guru_mata_pelajaran'])) {
            $employeeId = \App\Models\Employee::where('user_id', $user->id)->value('id');
            if ($employeeId) {
                $query->where('guru_id', $employeeId);
            }
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('judul_modul', 'ilike', "%{$search}%")
                  ->orWhere('kode_modul', 'ilike', "%{$search}%");
            });
        }

        if ($limit !== null && $limit > 0) {
            $query->take($limit);
        }

        $modulAjars = $query->get();

        if ($includeId && ! $modulAjars->contains('id', $includeId)) {
            $extra = LmsModulAjar::with('subject')
                ->select('id', 'kode_modul', 'judul_modul', 'mata_pelajaran_id', 'fase', 'guru_id')
                ->find($includeId);
            if ($extra) {
                $modulAjars->prepend($extra);
            }
        }

        return [
            'modul_ajar' => $modulAjars,
            'tipe_options' => [
                ['id' => 'teks', 'nama' => 'Teks / Ringkasan'],
                ['id' => 'dokumen', 'nama' => 'Dokumen / PDF / Office'],
                ['id' => 'video', 'nama' => 'Video Pembelajaran'],
                ['id' => 'link', 'nama' => 'Tautan / Link Eksternal'],
                ['id' => 'presentasi', 'nama' => 'Slide Presentasi'],
            ],
            'status_options' => [
                ['id' => 'aktif', 'nama' => 'Aktif'],
                ['id' => 'published', 'nama' => 'Published'],
                ['id' => 'draft', 'nama' => 'Draft'],
                ['id' => 'nonaktif', 'nama' => 'Nonaktif'],
            ],
        ];
    }
}
