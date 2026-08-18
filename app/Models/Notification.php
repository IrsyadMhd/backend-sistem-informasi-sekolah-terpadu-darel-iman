<?php

namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory, HasUuidPrimaryKey;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'is_read',
        'read_at',
        'metadata',
        'academic_year_id',
        'semester_id',
        'month',
        'notifiable_id',
        'notifiable_type',
        'body',
        'channel',
    ];


    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static bool $canonicalResolved = false;

    protected static bool $canonicalSchema = true;

    /**
     * Deteksi skema `notifications` (kanonik partitioned vs legacy user-scoped)
     * di-cache dalam static agar tidak memanggil Schema::hasColumn berulang
     * per request. Sumber kebenaran utama = skema kanonik (partitioned).
     */
    public static function usesCanonicalSchema(): bool
    {
        if (! static::$canonicalResolved) {
            static::$canonicalSchema = ! \Illuminate\Support\Facades\Schema::hasColumn('notifications', 'user_id');
            static::$canonicalResolved = true;
        }

        return static::$canonicalSchema;
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Accessors for Schema Compatibility
    public function getMessageAttribute(): ?string
    {
        return $this->attributes['message'] ?? $this->attributes['body'] ?? null;
    }

    /**
     * Kirim notifikasi ke satu pengguna memakai SKEMA KANONIK (partitioned).
     *
     * Tabel `notifications` di PostgreSQL bersifat partitioned: kolom
     * academic_year_id / semester_id / month adalah bagian dari primary key
     * dan foreign key, sehingga WAJIB terisi. Konteks akademik aktif
     * (tahun ajaran + semester berjalan) di-resolve di sini; bila belum ada
     * konteks akademik aktif, notifikasi dilewati dengan aman (null) karena
     * tidak mungkin memenuhi partition key pada mesin produksi.
     */
    public static function deliver(string $userId, string $title, string $body, string $channel, array $metadata = []): ?self
    {
        $activeAy = AcademicYear::query()->where('is_active', true)->first();
        $activeSem = $activeAy
            ? Semester::query()
                ->where('academic_year_id', $activeAy->id)
                ->where('is_active', true)
                ->orderBy('sequence', 'desc')
                ->first()
            : null;

        if (! $activeAy || ! $activeSem) {
            return null;
        }

        return static::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'academic_year_id' => $activeAy->id,
            'semester_id' => $activeSem->id,
            'month' => now()->month,
            'notifiable_id' => $userId,
            'notifiable_type' => User::class,
            'title' => $title,
            'body' => $body,
            'channel' => $channel,
            'metadata' => $metadata,
            'read_at' => null,
        ]);
    }

    /**
     * Bangun query notifikasi milik satu pengguna dengan filter umum
     * (search / type / is_read). Sumber kebenaran tunggal agar perilaku
     * identik di semua kanal API (dashboard, portal guru, portal wali/siswa).
     */
    public static function userQuery(string $userId, array $filters = []): \Illuminate\Database\Eloquent\Builder
    {
        $query = static::byUser($userId);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $operator = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $canonical = static::usesCanonicalSchema();
            $query->where(function ($q) use ($search, $operator, $canonical) {
                $q->where('title', $operator, "%{$search}%")
                  ->orWhere('body', $operator, "%{$search}%");
                if (! $canonical) {
                    $q->orWhere('message', $operator, "%{$search}%");
                }
            });
        }

        $type = $filters['type'] ?? null;
        if ($type && $type !== 'all') {
            if (static::usesCanonicalSchema()) {
                $query->where('channel', $type);
            } else {
                $query->where('type', $type);
            }
        }

        $isReadParam = $filters['is_read'] ?? null;
        if ($isReadParam !== null && $isReadParam !== '' && $isReadParam !== 'all') {
            $isReadBool = filter_var($isReadParam, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isReadBool !== null) {
                if ($isReadBool) {
                    $query->whereNotNull('read_at');
                } else {
                    $query->unread();
                }
            }
        }

        return $query;
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeByUser($query, string $userId)
    {
        // Skema kanonik (partitioned) memakai notifiable_id; kolom user_id
        // hanya ada di skema legacy. Mengambil kolom yang salah di PostgreSQL
        // menghasilkan error "column user_id does not exist".
        if (static::usesCanonicalSchema()) {
            return $query->where('notifiable_id', $userId);
        }

        return $query->where('user_id', $userId);
    }
}

