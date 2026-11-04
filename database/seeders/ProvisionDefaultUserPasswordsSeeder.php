<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionDefaultUserPasswordsSeeder extends Seeder
{
    public const PEGAWAI_DEFAULT_PASSWORD = 'Pegawai@2026!';
    public const SISWA_PARENT_DEFAULT_PASSWORD = 'Siswa@2026!';

    public function run(): void
    {
        $this->command?->info('=== MEMULAI GENERASI AKUN LOGIN & PASSWORD DEFAULT ===');
        $this->command?->info('Password Pegawai/Guru   : ' . self::PEGAWAI_DEFAULT_PASSWORD);
        $this->command?->info('Password Siswa/Orang Tua: ' . self::SISWA_PARENT_DEFAULT_PASSWORD);

        // Precompute password hashes once for speed (takes ~0.2s instead of 20 minutes)
        $pegawaiHash = Hash::make(self::PEGAWAI_DEFAULT_PASSWORD);
        $siswaParentHash = Hash::make(self::SISWA_PARENT_DEFAULT_PASSWORD);

        // Cache existing roles
        $roles = Role::where('guard_name', 'web')->get()->keyBy('name');

        // Cache existing emails to guarantee global uniqueness
        $existingEmails = User::pluck('email')->map(fn($e) => strtolower(trim($e)))->flip()->toArray();

        // ==========================================
        // 1. PROVISI AKUN PEGAWAI & GURU
        // ==========================================
        $employeesWithoutUser = Employee::with('position')
            ->whereNull('user_id')
            ->get();

        $this->command?->info("Memproses {$employeesWithoutUser->count()} Pegawai/Guru tanpa akun...");
        $pegawaiCreated = 0;

        foreach ($employeesWithoutUser->chunk(100) as $chunk) {
            DB::transaction(function () use ($chunk, $pegawaiHash, $roles, &$existingEmails, &$pegawaiCreated) {
                foreach ($chunk as $emp) {
                    $emailCandidate = ! empty($emp->email) && filter_var($emp->email, FILTER_VALIDATE_EMAIL)
                        ? strtolower(trim($emp->email))
                        : null;

                    if (! $emailCandidate || isset($existingEmails[$emailCandidate])) {
                        $cleanNiy = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($emp->niy));
                        $emailCandidate = $cleanNiy . '@pegawai.dareliman.sch.id';
                        $counter = 1;
                        while (isset($existingEmails[$emailCandidate])) {
                            $emailCandidate = $cleanNiy . '.' . $counter++ . '@pegawai.dareliman.sch.id';
                        }
                    }

                    $existingEmails[$emailCandidate] = true;

                    // Tentukan Role Sistem
                    $jabatanName = strtolower($emp->position?->name ?? ($emp->metadata['jabatan_raw'] ?? ''));
                    $targetRole = 'Guru';
                    if (str_contains($jabatanName, 'kepala sekolah') || str_contains($jabatanName, 'pimpinan pondok')) {
                        $targetRole = 'Kepala Sekolah';
                    } elseif (str_contains($jabatanName, 'operator')) {
                        $targetRole = 'Operator';
                    } elseif (str_contains($jabatanName, 'tata usaha') || str_contains($jabatanName, 'tu')) {
                        $targetRole = 'Tata Usaha';
                    } elseif (str_contains($jabatanName, 'musyrifah')) {
                        $targetRole = 'Musyrifah';
                    } elseif (str_contains($jabatanName, 'musyrif')) {
                        $targetRole = 'Musyrif';
                    } elseif (str_contains($jabatanName, 'tahfizh') || str_contains($jabatanName, 'tahsin')) {
                        $targetRole = 'Guru Tahfizh';
                    } elseif (str_contains($jabatanName, 'bk')) {
                        $targetRole = 'Guru BK';
                    } elseif (str_contains($jabatanName, 'wali kelas') || str_contains($jabatanName, 'walas')) {
                        $targetRole = 'Wali Kelas';
                    }

                    $roleModel = $roles->get($targetRole) ?? $roles->get('Guru');

                    $user = User::create([
                        'name' => $emp->nama_lengkap,
                        'email' => $emailCandidate,
                        'phone' => $emp->no_hp,
                        'password' => $pegawaiHash,
                        'is_active' => true,
                        'metadata' => [
                            'source' => 'ProvisionDefaultUserPasswordsSeeder',
                            'role' => $targetRole,
                            'unit_id' => $emp->unit_id,
                            'niy' => $emp->niy,
                            'default_password' => self::PEGAWAI_DEFAULT_PASSWORD,
                        ],
                    ]);

                    if ($roleModel) {
                        $user->assignRole($roleModel);
                    }

                    $emp->update(['user_id' => $user->id]);

                    Teacher::where('employee_id', $emp->id)->update(['user_id' => $user->id]);

                    $pegawaiCreated++;
                }
            });
        }
        $this->command?->info("Selesai: {$pegawaiCreated} akun Pegawai/Guru berhasil dibuat.");

        // ==========================================
        // 2. PROVISI AKUN ORANG TUA
        // ==========================================
        $parentsWithoutUser = ParentModel::whereNull('user_id')->get();
        $this->command?->info("Memproses {$parentsWithoutUser->count()} Orang Tua tanpa akun...");
        $parentRole = $roles->get('Orang Tua');
        $parentCreated = 0;

        foreach ($parentsWithoutUser->chunk(150) as $chunk) {
            DB::transaction(function () use ($chunk, $siswaParentHash, $parentRole, &$existingEmails, &$parentCreated) {
                foreach ($chunk as $prt) {
                    $emailCandidate = ! empty($prt->email) && filter_var($prt->email, FILTER_VALIDATE_EMAIL)
                        ? strtolower(trim($prt->email))
                        : null;

                    if (! $emailCandidate || isset($existingEmails[$emailCandidate])) {
                        $cleanId = $prt->nik ? preg_replace('/[^a-zA-Z0-9]/', '', $prt->nik) : substr($prt->id, 0, 8);
                        $emailCandidate = 'ortu.' . $cleanId . '@parent.dareliman.sch.id';
                        $counter = 1;
                        while (isset($existingEmails[$emailCandidate])) {
                            $emailCandidate = 'ortu.' . $cleanId . '.' . $counter++ . '@parent.dareliman.sch.id';
                        }
                    }

                    $existingEmails[$emailCandidate] = true;

                    $user = User::create([
                        'name' => $prt->full_name,
                        'email' => $emailCandidate,
                        'phone' => $prt->phone,
                        'password' => $siswaParentHash,
                        'is_active' => true,
                        'metadata' => [
                            'source' => 'ProvisionDefaultUserPasswordsSeeder',
                            'role' => 'Orang Tua',
                            'parent_id' => $prt->id,
                            'nik' => $prt->nik,
                            'default_password' => self::SISWA_PARENT_DEFAULT_PASSWORD,
                        ],
                    ]);

                    if ($parentRole) {
                        $user->assignRole($parentRole);
                    }

                    $prt->update(['user_id' => $user->id]);
                    $parentCreated++;
                }
            });
        }
        $this->command?->info("Selesai: {$parentCreated} akun Orang Tua berhasil dibuat.");

        // ==========================================
        // 3. PROVISI AKUN SISWA
        // ==========================================
        $studentsWithoutUser = Student::whereNull('user_id')->get();
        $this->command?->info("Memproses {$studentsWithoutUser->count()} Siswa tanpa akun...");
        $siswaRole = $roles->get('Siswa');
        $siswaCreated = 0;

        foreach ($studentsWithoutUser->chunk(200) as $chunk) {
            DB::transaction(function () use ($chunk, $siswaParentHash, $siswaRole, &$existingEmails, &$siswaCreated) {
                foreach ($chunk as $stu) {
                    $stuEmail = $stu->metadata['email'] ?? null;
                    $emailCandidate = ! empty($stuEmail) && filter_var($stuEmail, FILTER_VALIDATE_EMAIL)
                        ? strtolower(trim($stuEmail))
                        : null;

                    if (! $emailCandidate || isset($existingEmails[$emailCandidate])) {
                        $cleanId = $stu->nis ? preg_replace('/[^a-zA-Z0-9]/', '', strtolower($stu->nis)) : substr($stu->id, 0, 8);
                        $emailCandidate = $cleanId . '@student.dareliman.sch.id';
                        $counter = 1;
                        while (isset($existingEmails[$emailCandidate])) {
                            $emailCandidate = $cleanId . '.' . $counter++ . '@student.dareliman.sch.id';
                        }
                    }

                    $existingEmails[$emailCandidate] = true;

                    $user = User::create([
                        'name' => $stu->full_name,
                        'email' => $emailCandidate,
                        'phone' => null,
                        'password' => $siswaParentHash,
                        'is_active' => $stu->is_active ?? true,
                        'metadata' => [
                            'source' => 'ProvisionDefaultUserPasswordsSeeder',
                            'role' => 'Siswa',
                            'student_id' => $stu->id,
                            'nis' => $stu->nis,
                            'unit_id' => $stu->unit_id,
                            'default_password' => self::SISWA_PARENT_DEFAULT_PASSWORD,
                        ],
                    ]);

                    if ($siswaRole) {
                        $user->assignRole($siswaRole);
                    }

                    $stu->update(['user_id' => $user->id]);
                    $siswaCreated++;
                }
            });
        }
        $this->command?->info("Selesai: {$siswaCreated} akun Siswa berhasil dibuat.");

        // ==========================================
        // 4. SINKRONISASI PASSWORD AKUN EKSISITING
        // ==========================================
        // Pastikan seluruh akun yang sudah ada sebelumnya juga tersinkronisasi
        $empUserIds = Employee::whereNotNull('user_id')->pluck('user_id');
        User::whereIn('id', $empUserIds)->update(['password' => $pegawaiHash]);

        $stdUserIds = Student::whereNotNull('user_id')->pluck('user_id');
        User::whereIn('id', $stdUserIds)->update(['password' => $siswaParentHash]);

        $prtUserIds = ParentModel::whereNotNull('user_id')->pluck('user_id');
        User::whereIn('id', $prtUserIds)->update(['password' => $siswaParentHash]);

        $this->command?->info('=== SEMUA AKUN & PASSWORD DEFAULT BERHASIL DIPROVISI & DISINKRONKAN ===');
        $this->command?->info('Total User Sistem di DB: ' . User::count());
    }
}
