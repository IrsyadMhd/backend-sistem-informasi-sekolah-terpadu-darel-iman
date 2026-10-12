<?php

namespace App\Console\Commands;

use Database\Seeders\TestingRoleAccountsSeeder;
use Illuminate\Console\Command;

class ManageTestRoleAccountsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simsit:test-accounts 
                            {--clean : Bersihkan seluruh akun uji coba tanpa membuat yang baru}
                            {--status : Tampilkan status keberadaan akun uji coba}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kelola akun uji coba multi-role (seed, status, dan pembersihan) secara aman dan idempoten.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('PERINGATAN: Perintah ini ditolak keras di environment produksi.');
            return 1;
        }

        if ($this->option('clean')) {
            $this->warn('Membersihkan akun uji coba...');
            $count = TestingRoleAccountsSeeder::cleanup();
            $this->info("Berhasil membersihkan {$count} akun uji coba.");
            return 0;
        }

        if ($this->option('status')) {
            $this->info('Memeriksa status akun uji coba:');
            $rows = [];
            foreach (TestingRoleAccountsSeeder::TEST_ACCOUNTS as $role => $cfg) {
                $user = \App\Models\User::where('email', $cfg['email'])->first();
                $rows[] = [
                    $role,
                    $cfg['email'],
                    $user ? '<fg=green>Tersedia</>' : '<fg=red>Belum Ada</>',
                    $cfg['scope'],
                    $cfg['unit_code'] ?? 'Global',
                ];
            }
            $this->table(['Role', 'Email', 'Status', 'Data Scope', 'Unit'], $rows);
            return 0;
        }

        $this->info('Membuat/memperbarui akun uji coba role (idempoten)...');
        $seeder = new TestingRoleAccountsSeeder();
        $seeder->run();

        $this->info('Akun uji coba berhasil disiapkan!');
        $this->call('simsit:test-accounts', ['--status' => true]);

        return 0;
    }
}
