<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Deterministic development/acceptance graph for the master-data flow.
 *
 * The project has no canonical foundation table: foundation is represented by
 * roles and education-unit ownership. This seeder therefore composes the
 * existing unit/academic/employee/parent/student seeders instead of creating
 * a parallel organization schema.
 */
class SuperAdminAcceptanceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        $this->call([
            // This standalone orchestrator must also work when invoked
            // directly, not only through DatabaseSeeder.
            RolePermissionSeeder::class,
            MasterJenisUnitPendidikanSeeder::class,
            DataDummyUnitPendidikanSeeder::class,
            MasterJabatanSeeder::class,
            DataDummyPegawaiSeeder::class,
            TeacherSeeder::class,
            // KelasSeeder owns the deterministic active academic year and
            // semester used by the remaining academic seeders.
            KelasSeeder::class,
            MasterKurikulumSeeder::class,
            SubjectSeeder::class,
            ParentSeeder::class,
            DataDummySiswaSeeder::class,
            JadwalPelajaranSeeder::class,
            DefaultRoleUserSeeder::class,
        ]);
    }
}
