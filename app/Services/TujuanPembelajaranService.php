<?php

namespace App\Services;

use App\Models\CapaianPembelajaran;
use App\Models\TujuanPembelajaran;
use App\Repositories\Contracts\TujuanPembelajaranRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TujuanPembelajaranService
{
    public function __construct(
        protected TujuanPembelajaranRepositoryInterface $tpRepository
    ) {}

    public function dapatkanDaftar(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        return $this->tpRepository->getFiltered($filters, $perPage, $orderBy, $orderDir);
    }

    public function cariBerdasarkanId(string $id, bool $withTrashed = false): ?TujuanPembelajaran
    {
        return $this->tpRepository->findById($id, $withTrashed);
    }

    public function dapatkanBerdasarkanCp(string $cpId): Collection
    {
        return $this->tpRepository->getByCpId($cpId);
    }

    public function simpan(array $data): TujuanPembelajaran
    {
        if (empty($data['cp_id'])) {
            throw ValidationException::withMessages([
                'cp_id' => ['Capaian Pembelajaran (CP) harus dipilih.'],
            ]);
        }

        $cp = CapaianPembelajaran::with(['subject', 'unitPendidikan'])->find($data['cp_id']);
        if (! $cp) {
            throw ValidationException::withMessages([
                'cp_id' => ['Capaian Pembelajaran (CP) tidak ditemukan.'],
            ]);
        }

        if (! $cp->status) {
            throw ValidationException::withMessages([
                'cp_id' => ['Tujuan Pembelajaran tidak dapat dibuat karena Capaian Pembelajaran (CP) yang dipilih berstatus tidak aktif.'],
            ]);
        }

        // Auto Generate Kode TP jika kosong atau generik
        if (empty($data['kode_tp']) || str_starts_with($data['kode_tp'], 'TP-') === false || strlen($data['kode_tp']) < 6) {
            $kodeMapel = strtoupper($cp->subject?->kode_mapel ?? $cp->subject?->code ?? 'MAPEL');
            $kodeUnit = strtoupper($cp->unitPendidikan?->code ?? $cp->unitPendidikan?->level ?? 'SD');

            $countExisting = TujuanPembelajaran::where('cp_id', $cp->id)->count();
            $seq = str_pad($countExisting + 1, 3, '0', STR_PAD_LEFT);

            $data['kode_tp'] = "TP-{$kodeMapel}-{$kodeUnit}-{$seq}";
        }

        if (isset($data['deskripsi_tp']) && empty($data['deskripsi'])) {
            $data['deskripsi'] = $data['deskripsi_tp'];
        }

        if (empty($data['nama_tp']) && ! empty($data['deskripsi'])) {
            $data['nama_tp'] = Str::limit($data['deskripsi'], 240);
        }

        if (! isset($data['status'])) {
            $data['status'] = true;
        }

        if (empty($data['urutan'])) {
            $maxUrutan = TujuanPembelajaran::where('cp_id', $data['cp_id'])->max('urutan') ?? 0;
            $data['urutan'] = $maxUrutan + 1;
        }

        Log::info('[AUDIT LOG] Membuat Tujuan Pembelajaran Baru', [
            'kode_tp' => $data['kode_tp'],
            'cp_id' => $data['cp_id'],
            'user_id' => auth()->id(),
        ]);

        return $this->tpRepository->create($data);
    }

    public function ubah(string $id, array $data): ?TujuanPembelajaran
    {
        $existing = $this->tpRepository->findById($id);
        if (! $existing) {
            return null;
        }

        if (! empty($data['cp_id'])) {
            $cp = CapaianPembelajaran::find($data['cp_id']);
            if (! $cp) {
                throw ValidationException::withMessages([
                    'cp_id' => ['Capaian Pembelajaran (CP) tidak ditemukan.'],
                ]);
            }
            if (! $cp->status) {
                throw ValidationException::withMessages([
                    'cp_id' => ['Tujuan Pembelajaran tidak dapat diubah ke Capaian Pembelajaran (CP) yang berstatus tidak aktif.'],
                ]);
            }
        }

        if (isset($data['deskripsi_tp'])) {
            $data['deskripsi'] = $data['deskripsi_tp'];
            if (empty($data['nama_tp'])) {
                $data['nama_tp'] = Str::limit($data['deskripsi_tp'], 240);
            }
        }

        Log::info('[AUDIT LOG] Memperbarui Tujuan Pembelajaran', [
            'id' => $id,
            'kode_tp' => $existing->kode_tp,
            'user_id' => auth()->id(),
        ]);

        return $this->tpRepository->update($id, $data);
    }

    public function hapus(string $id): bool
    {
        Log::info('[AUDIT LOG] Menghapus (Soft Delete) Tujuan Pembelajaran', [
            'id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->tpRepository->delete($id);
    }

    public function pulihkan(string $id): bool
    {
        Log::info('[AUDIT LOG] Memulihkan Tujuan Pembelajaran', [
            'id' => $id,
            'user_id' => auth()->id(),
        ]);

        return $this->tpRepository->restore($id);
    }

    public function statistik(): array
    {
        return [
            'total_tp' => TujuanPembelajaran::count(),
            'total_tp_aktif' => TujuanPembelajaran::where('status', true)->count(),
            'total_cp' => CapaianPembelajaran::count(),
            'cp_ber_tp' => CapaianPembelajaran::has('tujuanPembelajaran')->count(),
        ];
    }
}
