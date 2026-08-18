<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SESI 11 — Mencegah percobaan ujian ganda (TOCTOU).
 *
 * `lms_ujian_sesi` hanya memiliki index non-unique (ujian_id, siswa_id).
 * Dua request paralel "start" dapat membuat dua sesi `proses` sekaligus.
 * Partial unique index di bawah menjamin maksimal SATU sesi `proses`
 * per pasangan (ujian_id, siswa_id) di tingkat basis data (PG/SQLite).
 *
 * Sesi `selesai`/`timeout`/`dibatalkan` tetap boleh lebih dari satu
 * (mendukung fitur max_attempt), sehingga index hanya menjangkau `proses`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // De-duplikasi data lama: jaga sesi `proses` paling awal per
        // (ujian_id, siswa_id), sisanya dibatalkan (tidak dihitung selesai).
        $rows = DB::table('lms_ujian_sesi')
            ->where('status', 'proses')
            ->select('id', 'ujian_id', 'siswa_id', 'created_at')
            ->orderBy('created_at')
            ->get();

        $seen = [];
        foreach ($rows as $row) {
            $key = $row->ujian_id.'|'.$row->siswa_id;
            if (isset($seen[$key])) {
                DB::table('lms_ujian_sesi')
                    ->where('id', $row->id)
                    ->update(['status' => 'dibatalkan']);
                continue;
            }
            $seen[$key] = true;
        }

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX lms_sesi_proses_ujian_siswa_unique ON lms_ujian_sesi (ujian_id, siswa_id) WHERE status = \'proses\'');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS lms_sesi_proses_ujian_siswa_unique');
        }
    }
};
