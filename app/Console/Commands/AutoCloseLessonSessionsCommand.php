<?php

namespace App\Console\Commands;

use App\Models\ClassSchedule;
use App\Models\LessonAttendanceSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoCloseLessonSessionsCommand extends Command
{
    protected $signature = 'kbm:auto-close-sessions {--date=} {--dry-run}';

    protected $description = 'Otomatis menutup sesi KBM yang telah melewati jam pelajaran / akhir hari sekolah, serta menandai sesi yang belum memiliki rangkuman materi.';

    public function handle(): int
    {
        $targetDate = $this->option('date') ? Carbon::parse($this->option('date'))->toDateString() : today()->toDateString();
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("Menjalankan Auto-Close Sesi KBM untuk tanggal: {$targetDate}...");

        // 1. Ambil semua sesi aktif/draft yang belum selesai pada tanggal target atau tanggal sebelumnya
        $openSessions = LessonAttendanceSession::query()
            ->with(['schedule.subject', 'schedule.kelas', 'schedule.employee'])
            ->whereDate('attendance_date', '<=', $targetDate)
            ->where(function ($q) {
                $q->whereNull('teaching_session_status')
                    ->orWhere('teaching_session_status', '!=', 'completed')
                    ->orWhere('status', '!=', 'final');
            })
            ->get();

        $closedCount = 0;
        $missingNotesCount = 0;

        foreach ($openSessions as $session) {
            $hasNotes = ! empty(trim((string) ($session->meeting_notes ?? '')));
            if (! $hasNotes) {
                $missingNotesCount++;
            }

            if (! $isDryRun) {
                $existingMeta = is_array($session->metadata) ? $session->metadata : [];
                $existingMeta['auto_closed'] = true;
                $existingMeta['auto_closed_at'] = now()->toIso8601String();
                if (! $hasNotes) {
                    $existingMeta['requires_journal'] = true;
                }

                $session->update([
                    'teaching_session_status' => 'completed',
                    'session_closed_at' => $session->session_closed_at ?: now(),
                    'status' => 'final',
                    'finalized_at' => $session->finalized_at ?: now(),
                    'metadata' => $existingMeta,
                ]);
            }

            $closedCount++;
        }

        $this->info("✓ Selesai memproses sesi KBM.");
        $this->line("  • Total sesi ditutup otomatis: {$closedCount}");
        $this->line("  • Sesi memerlukan rangkuman materi/jurnal: {$missingNotesCount}");

        Log::info('kbm:auto-close-sessions executed', [
            'date' => $targetDate,
            'closed_count' => $closedCount,
            'missing_notes' => $missingNotesCount,
            'dry_run' => $isDryRun,
        ]);

        return Command::SUCCESS;
    }
}
