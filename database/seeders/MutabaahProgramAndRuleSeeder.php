<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\MutabaahAgendaItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MutabaahProgramAndRuleSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = DB::table('users')->value('id') ?? (string) Str::uuid();

        // 1. Inisialisasi Program Settings (Fullday vs Boarding) untuk setiap Unit Pendidikan
        $units = EducationUnit::all();
        foreach ($units as $unit) {
            $isBoarding = Str::contains(strtoupper($unit->name . ' ' . $unit->code . ' ' . ($unit->level ?? '')), ['PONPES', 'MAHAD', 'PESANTREN']);
            $programType = $isBoarding ? 'boarding' : 'fullday';

            $existing = DB::table('education_program_settings')
                ->where('education_unit_id', $unit->id)
                ->whereNull('class_id')
                ->first();

            $data = [
                'program_type' => $programType,
                'school_weekdays' => json_encode([1, 2, 3, 4, 5, 6]),
                'is_active' => true,
                'effective_from' => '2026-07-01',
                'effective_until' => '2027-06-30',
                'metadata' => json_encode([
                    'unit_name' => $unit->name,
                    'unit_code' => $unit->code,
                    'scope' => $isBoarding ? 'Asrama 24 Jam' : 'Sekolah Fullday',
                ]),
                'updated_by' => $adminId,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('education_program_settings')
                    ->where('id', $existing->id)
                    ->update($data);
            } else {
                DB::table('education_program_settings')->insert(array_merge($data, [
                    'id' => (string) Str::uuid(),
                    'education_unit_id' => $unit->id,
                    'class_id' => null,
                    'created_by' => $adminId,
                    'created_at' => now(),
                ]));
            }
        }

        // 2. Daftar Pemetaan 29 Agenda Resmi Formulir Mutaba'ah Yaumiyyah
        // Agenda Rumah (Diisi Orang Tua pada Fullday)
        $homeAgendaCodes = [
            'TAHAJUD-WITIR',
            'SUBUH',
            'SHOLAT-SUNNAH-FAJAR',
            'WUDHU-SEBELUM-TIDUR',
            'DOA-ZIKIR-SEBELUM-TIDUR',
            'DOA-BANGUN-TIDUR',
            'SALAM-ORANG-TUA',
            'DOA-KELUAR-RUMAH',
            'DOA-NAIK-KENDARAAN',
            'ZIKIR-PETANG',
            'BAKDIYAH-MAGRIB',
            'MAGHRIB',
            'ISYA',
            'MENDOAKAN-ORTU',
            'MURAJAAH-DENGAN-ORTU',
            'BACA-SURAT-ALKAHFI',
            'PUASA-SUNNAH',
        ];

        // Agenda Sekolah (Diisi Guru / Wali Kelas pada Fullday)
        $schoolAgendaCodes = [
            'ZIKIR-PAGI',
            'DHUHA',
            'ZUHUR',
            'RAWATIB-ZUHUR',
            'ASHAR',
            'QOBLIYAH-ASHAR',
            'SEDEKAH',
            'MENEBAR-SALAM',
            'KAJIAN',
            'HAFALAN-DOA',
            'HAFALAN-HADIST',
            'KOSA-KATA-BAHASA',
            'TILAWAH-QURAN',
            'MURAJAAH',
            'TEPAT-WAKTU',
            'JAGA-KEBERSIHAN',
        ];

        $allAgendas = MutabaahAgendaItem::all()->keyBy('code');

        // 3. Masukkan Aturan ke Tabel `mutabaah_input_rules`
        // A. Aturan untuk Program Fullday (Orang Tua vs Guru)
        foreach ($homeAgendaCodes as $priority => $code) {
            $agenda = $allAgendas->get($code);
            if (! $agenda) continue;

            $existing = DB::table('mutabaah_input_rules')
                ->where('program_type', 'fullday')
                ->where('agenda_item_id', $agenda->id)
                ->first();

            $ruleData = [
                'assessment_period_id' => null,
                'input_source' => 'parent',
                'location' => 'home',
                'weekdays' => null,
                'school_day_only' => false,
                'requires_verification' => true,
                'priority' => 100 - $priority,
                'is_active' => true,
                'metadata' => json_encode([
                    'scope' => 'home',
                    'role' => 'parent',
                    'label' => 'Amalan Rumah',
                ]),
                'updated_by' => $adminId,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('mutabaah_input_rules')->where('id', $existing->id)->update($ruleData);
            } else {
                DB::table('mutabaah_input_rules')->insert(array_merge($ruleData, [
                    'id' => (string) Str::uuid(),
                    'program_type' => 'fullday',
                    'agenda_item_id' => $agenda->id,
                    'created_by' => $adminId,
                    'created_at' => now(),
                ]));
            }
        }

        foreach ($schoolAgendaCodes as $priority => $code) {
            $agenda = $allAgendas->get($code);
            if (! $agenda) continue;

            $existing = DB::table('mutabaah_input_rules')
                ->where('program_type', 'fullday')
                ->where('agenda_item_id', $agenda->id)
                ->first();

            $ruleData = [
                'assessment_period_id' => null,
                'input_source' => 'school',
                'location' => 'school',
                'weekdays' => json_encode([1, 2, 3, 4, 5, 6]),
                'school_day_only' => true,
                'requires_verification' => false,
                'priority' => 50 - $priority,
                'is_active' => true,
                'metadata' => json_encode([
                    'scope' => 'school',
                    'role' => 'teacher',
                    'label' => 'Amalan Sekolah',
                ]),
                'updated_by' => $adminId,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('mutabaah_input_rules')->where('id', $existing->id)->update($ruleData);
            } else {
                DB::table('mutabaah_input_rules')->insert(array_merge($ruleData, [
                    'id' => (string) Str::uuid(),
                    'program_type' => 'fullday',
                    'agenda_item_id' => $agenda->id,
                    'created_by' => $adminId,
                    'created_at' => now(),
                ]));
            }
        }

        // B. Aturan untuk Program Boarding / Pesantren (Seluruh Agenda diisi Musyrif & Guru)
        foreach ($allAgendas as $agenda) {
            $existing = DB::table('mutabaah_input_rules')
                ->where('program_type', 'boarding')
                ->where('agenda_item_id', $agenda->id)
                ->first();

            $ruleData = [
                'assessment_period_id' => null,
                'input_source' => 'supervisor',
                'location' => 'any',
                'weekdays' => null,
                'school_day_only' => false,
                'requires_verification' => false,
                'priority' => 10,
                'is_active' => true,
                'metadata' => json_encode([
                    'scope' => 'boarding',
                    'role' => 'musyrif',
                    'label' => 'Amalan Asrama / Pondok',
                ]),
                'updated_by' => $adminId,
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('mutabaah_input_rules')->where('id', $existing->id)->update($ruleData);
            } else {
                DB::table('mutabaah_input_rules')->insert(array_merge($ruleData, [
                    'id' => (string) Str::uuid(),
                    'program_type' => 'boarding',
                    'agenda_item_id' => $agenda->id,
                    'created_by' => $adminId,
                    'created_at' => now(),
                ]));
            }
        }

        $this->command?->info('MutabaahProgramAndRuleSeeder berhasil: Program settings & input rules terpasang untuk Fullday & Boarding.');
    }
}
