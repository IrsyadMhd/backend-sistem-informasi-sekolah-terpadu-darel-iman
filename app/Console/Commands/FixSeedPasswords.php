<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FixSeedPasswords extends Command
{
    protected $signature = 'app:fix-seed-passwords';

    protected $description = 'Fix double-hashed passwords for all seeded accounts (development only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'development', 'testing')) {
            $this->error('This command can only run in local/development/testing environment.');

            return self::FAILURE;
        }

        $this->info('=== DATABASE CHECK ===');
        $this->info('DRIVER: '.DB::connection()->getDriverName());
        $this->info('DATABASE: '.DB::connection()->getDatabaseName());

        $accounts = [
            'superadmin@school-erp.local' => env('DEFAULT_SUPER_ADMIN_PASSWORD', 'Password123!'),
            'admin@school-erp.local' => env('DEFAULT_ADMIN_PASSWORD', 'Admin@2026!'),
            'yayasan@school-erp.local' => env('DEFAULT_YAYASAN_PASSWORD', 'Yayasan@2026!'),
            'ketua.yayasan@school-erp.local' => env('DEFAULT_KETUA_YAYASAN_PASSWORD', 'Yayasan@2026!'),
            'sekretaris.yayasan@school-erp.local' => env('DEFAULT_SEKRETARIS_YAYASAN_PASSWORD', 'Yayasan@2026!'),
            'bendahara.yayasan@school-erp.local' => env('DEFAULT_BENDAHARA_YAYASAN_PASSWORD', 'Yayasan@2026!'),
            'pengurus.yayasan@school-erp.local' => env('DEFAULT_PENGURUS_YAYASAN_PASSWORD', 'Yayasan@2026!'),
            'kepsek@school-erp.local' => env('DEFAULT_KEPSEK_PASSWORD', 'Kepsek@2026!'),
            'divisi.pendidikan@school-erp.local' => env('DEFAULT_DIVISI_PASSWORD', 'Divisi@2026!'),
            'tu@school-erp.local' => env('DEFAULT_TU_PASSWORD', 'TU@2026!'),
            'guru@school-erp.local' => env('DEFAULT_GURU_PASSWORD', 'Guru@2026!'),
            'guru.tahfizh@school-erp.local' => env('DEFAULT_GURU_TAHFIZH_PASSWORD', 'Tahfizh@2026!'),
            'musyrif@school-erp.local' => env('DEFAULT_MUSYRIF_PASSWORD', 'Musyrif@2026!'),
            'orangtua@school-erp.local' => env('DEFAULT_ORANG_TUA_PASSWORD', 'OrangTua@2026!'),
            'siswa@school-erp.local' => env('DEFAULT_SISWA_PASSWORD', 'Siswa@2026!'),
        ];

        $this->info('');
        $this->info('=== FIXING PASSWORDS ===');

        $fixed = 0;
        $notFound = 0;
        $alreadyOk = 0;

        foreach ($accounts as $email => $plainPassword) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $this->warn("  SKIP: {$email} — user not found");
                $notFound++;

                continue;
            }

            // Check if current password already works
            if (Hash::check($plainPassword, $user->password)) {
                $this->info("  OK:   {$email} — password already correct");
                $alreadyOk++;

                continue;
            }

            // Fix: assign plaintext (User model 'hashed' cast will auto-hash)
            $user->password = $plainPassword;
            $user->save();

            // Verify the fix
            $user->refresh();
            if (Hash::check($plainPassword, $user->password)) {
                $this->info("  FIXED: {$email} — password corrected ✓");
                $fixed++;
            } else {
                $this->error("  ERROR: {$email} — password still invalid after fix!");
            }
        }

        $this->info('');
        $this->info("=== RESULT: {$fixed} fixed, {$alreadyOk} already OK, {$notFound} not found ===");

        // Quick superadmin trace
        $this->info('');
        $this->info('=== SUPERADMIN LOGIN TRACE ===');
        $sa = User::where('email', 'superadmin@school-erp.local')->first();
        if ($sa) {
            $this->info('USER FOUND: YES');
            $this->info('USER ID: '.$sa->id);
            $this->info('NAME: '.$sa->name);
            $this->info('EMAIL: '.$sa->email);
            $this->info('IS_ACTIVE: '.($sa->is_active ? 'TRUE' : 'FALSE'));
            $this->info('DELETED_AT: '.($sa->deleted_at ?? 'NULL'));
            $this->info('PASSWORD CHECK (Password123!): '.(Hash::check('Password123!', $sa->password) ? 'TRUE ✓' : 'FALSE ✗'));
            $this->info('ROLES: '.$sa->getRoleNames()->implode(', '));
            $this->info('PERMISSION COUNT: '.$sa->getAllPermissions()->count());
        } else {
            $this->warn('USER NOT FOUND — run: php artisan db:seed --class=DefaultRoleUserSeeder');
        }

        return self::SUCCESS;
    }
}
