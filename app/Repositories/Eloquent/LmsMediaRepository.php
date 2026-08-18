<?php

namespace App\Repositories\Eloquent;

use App\Models\LmsMateri;
use App\Models\LmsMedia;
use App\Repositories\Contracts\LmsMediaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class LmsMediaRepository implements LmsMediaRepositoryInterface
{
    public function getFiltered(array $filters = [], int $perPage = 15, string $orderBy = 'urutan', string $orderDir = 'asc'): LengthAwarePaginator
    {
        $query = LmsMedia::with(['materi', 'materi.modulAjar', 'materi.subject']);

        if (! empty($filters['materi_id'])) {
            $query->where('materi_id', $filters['materi_id']);
        }

        if (! empty($filters['guru_id'])) {
            $query->whereHas('materi', fn ($materiQuery) => $materiQuery->where('guru_id', $filters['guru_id']));
        }

        if (! empty($filters['tipe_file'])) {
            $query->where('tipe_file', strtolower($filters['tipe_file']));
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($search) {
                $q->where('nama_file', 'like', $search)
                    ->orWhere('deskripsi', 'like', $search)
                    ->orWhereHas('materi', function ($mq) use ($search) {
                        $mq->where('judul', 'like', $search);
                    });
            });
        }

        return $query->orderBy($orderBy, $orderDir)->paginate($perPage);
    }

    public function findById(string $id): ?LmsMedia
    {
        return LmsMedia::with(['materi', 'materi.modulAjar', 'materi.subject'])->find($id);
    }

    public function getByMateriId(string $materiId): Collection
    {
        return LmsMedia::with(['materi'])
            ->where('materi_id', $materiId)
            ->orderBy('urutan', 'asc')
            ->get();
    }

    public function create(array $data): LmsMedia
    {
        return LmsMedia::create($data);
    }

    public function update(string $id, array $data): ?LmsMedia
    {
        $media = $this->findById($id);
        if (! $media) {
            return null;
        }
        $media->update($data);

        return $media->fresh(['materi', 'materi.modulAjar']);
    }

    public function delete(string $id): bool
    {
        $media = $this->findById($id);
        if (! $media) {
            return false;
        }

        return (bool) $media->delete();
    }

    public function reorder(array $orders): bool
    {
        foreach ($orders as $item) {
            if (isset($item['id'], $item['urutan'])) {
                LmsMedia::where('id', $item['id'])->update(['urutan' => $item['urutan']]);
            }
        }

        return true;
    }

    public function getStats(): array
    {
        return [
            'total_media' => LmsMedia::count(),
            'total_pdf' => LmsMedia::where('tipe_file', 'pdf')->count(),
            'total_video' => LmsMedia::where('tipe_file', 'video')->count(),
            'total_audio' => LmsMedia::where('tipe_file', 'audio')->count(),
            'total_ppt' => LmsMedia::where('tipe_file', 'ppt')->count(),
            'total_word' => LmsMedia::where('tipe_file', 'word')->count(),
            'total_image' => LmsMedia::where('tipe_file', 'image')->count(),
            'total_link' => LmsMedia::where('tipe_file', 'link')->count(),
            'total_materi' => LmsMateri::count(),
        ];
    }
}
