<?php

namespace App\Console\Commands;

use App\Repositories\Eloquent\LmsUjianRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CbtAutoTimeout extends Command
{
    protected $signature = 'cbt:auto-timeout {--limit=100 : Maksimal jumlah sesi yang diproses per run}';

    protected $description = 'Auto-submit sesi CBT yang masih berjalan setelah batas waktu habis (status menjadi timeout).';

    public function handle(LmsUjianRepository $repository): int
    {
        $this->info('Memeriksa sesi CBT yang melewati batas waktu...');

        $result = $repository->autoSubmitExpiredSessions((int) $this->option('limit'));

        $this->info(sprintf(
            'Selesai. Kedaluwarsa: %d, disubmit otomatis: %d, dilewati: %d.',
            $result['expired'],
            $result['submitted'],
            $result['skipped'],
        ));

        if ($result['submitted'] > 0) {
            Log::info('cbt:auto-timeout selesai.', $result);
        }

        return Command::SUCCESS;
    }
}
