<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\MutabaahAgendaItem;
use App\Models\MutabaahCategory;
use App\Models\MutabaahTemplate;
use App\Models\Semester;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class MutabaahEnterpriseSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('mutabaah_categories') || ! Schema::hasTable('mutabaah_agenda_items')) {
            $this->command?->warn('MutabaahEnterpriseSeeder dilewati: jalankan migration Mutaba’ah terlebih dahulu.');

            return;
        }

        $categories = collect([
            ['code' => 'SHALAT', 'name' => 'Shalat', 'icon' => 'MoonStar', 'color' => '#0E5C44'],
            ['code' => 'TILAWAH', 'name' => 'Tilawah', 'icon' => 'BookOpen', 'color' => '#1E8E5A'],
            ['code' => 'TAHFIZH', 'name' => 'Tahfizh', 'icon' => 'BookMarked', 'color' => '#3B82F6'],
            ['code' => 'IBADAH-HARIAN', 'name' => 'Ibadah Harian', 'icon' => 'Heart', 'color' => '#8B5CF6'],
            ['code' => 'ADAB', 'name' => 'Adab', 'icon' => 'HeartHandshake', 'color' => '#F59E0B'],
            ['code' => 'KEBERSIHAN', 'name' => 'Kebersihan', 'icon' => 'Sparkles', 'color' => '#06B6D4'],
            ['code' => 'KEDISIPLINAN', 'name' => 'Kedisiplinan', 'icon' => 'Clock3', 'color' => '#EF4444'],
        ])->mapWithKeys(function (array $category, int $index) {
            $model = MutabaahCategory::updateOrCreate(['code' => $category['code']], $category + [
                'sort_order' => $index + 1, 'description' => "Kategori {$category['name']} Mutaba’ah Yaumiyyah.", 'is_active' => true,
            ]);

            return [$category['code'] => $model];
        });

        $agendaDefinitions = [
            ['SHALAT', 'TAHAJUD-WITIR', 'Tahajud/Witir', 'status', 5],
            ['SHALAT', 'SUBUH', 'Subuh', 'status', 5],
            ['SHALAT', 'ZUHUR', 'Zuhur', 'status', 5],
            ['SHALAT', 'ASHAR', 'Ashar', 'status', 5],
            ['SHALAT', 'MAGHRIB', 'Maghrib', 'status', 5],
            ['SHALAT', 'ISYA', 'Isya', 'status', 5],
            ['TILAWAH', 'TILAWAH-QURAN', 'Tilawah Al-Qur’an', 'pages', 10],
            ['TAHFIZH', 'MURAJAAH', 'Murajaah', 'verses', 10],
            ['IBADAH-HARIAN', 'ZIKIR-PAGI', 'Zikir Pagi', 'checklist', 4],
            ['IBADAH-HARIAN', 'ZIKIR-PETANG', 'Zikir Petang', 'checklist', 4],
            ['IBADAH-HARIAN', 'SEDEKAH', 'Sedekah', 'yes_no', 3],
            ['ADAB', 'SALAM-ORANG-TUA', 'Bersalaman dengan Orang Tua', 'yes_no', 3],
            ['ADAB', 'BANTU-ORANG-TUA', 'Membantu Orang Tua', 'status', 4],
            ['KEBERSIHAN', 'JAGA-KEBERSIHAN', 'Menjaga Kebersihan', 'status', 4],
            ['KEDISIPLINAN', 'TEPAT-WAKTU', 'Datang Tepat Waktu', 'status', 5],
            ['IBADAH-HARIAN', 'PUASA-SUNNAH', 'Puasa Sunnah', 'yes_no', 5],
            ['IBADAH-HARIAN', 'KAJIAN', 'Mengikuti Kajian', 'duration', 5],
            ['IBADAH-HARIAN', 'REFLEKSI', 'Refleksi Harian', 'text', 3],
            ['TAHFIZH', 'HALAQAH', 'Mengikuti Halaqah', 'status', 8],
            ['KEBERSIHAN', 'KEBERSIHAN-KAMAR', 'Menjaga Kebersihan Kamar', 'status', 5],
        ];

        $agendas = collect($agendaDefinitions)->mapWithKeys(function (array $definition, int $index) use ($categories) {
            [$categoryCode, $code, $name, $inputType, $weight] = $definition;
            $agenda = MutabaahAgendaItem::updateOrCreate(['code' => $code], [
                'category_id' => $categories[$categoryCode]->id,
                'name' => $name, 'input_type' => $inputType, 'weight' => $weight,
                'sort_order' => $index + 1, 'icon' => $categories[$categoryCode]->icon,
                'color' => $categories[$categoryCode]->color, 'is_active' => true,
            ]);

            return [$code => $agenda];
        });

        $academicYear = AcademicYear::query()->where('is_active', true)->first()
            ?? AcademicYear::query()->latest('start_date')->first();
        if (! $academicYear) {
            $this->command?->warn('Kategori dan agenda berhasil dibuat, tetapi template dilewati: tahun ajaran belum tersedia.');

            return;
        }

        $semester = Semester::query()->where('academic_year_id', $academicYear->id)
            ->orderByDesc('is_active')->orderBy('sequence')->first();
        if (! $semester) {
            $this->command?->warn("Kategori dan agenda berhasil dibuat, tetapi template dilewati: semester untuk {$academicYear->name} belum tersedia.");

            return;
        }

        if (! EducationUnit::query()->exists()) {
            $this->command?->warn('Unit pendidikan belum tersedia; template contoh dibuat sebagai template lintas unit.');
        }

        $templateDefinitions = [
            'TK-RA' => ['Template TK/RA', 'TK/RA', ['SUBUH', 'SALAM-ORANG-TUA', 'BANTU-ORANG-TUA', 'JAGA-KEBERSIHAN', 'SEDEKAH']],
            'SD-MI' => ['Template SD/MI', 'SD/MI', ['SUBUH', 'ZUHUR', 'ASHAR', 'MAGHRIB', 'ISYA', 'TILAWAH-QURAN', 'MURAJAAH', 'ZIKIR-PAGI', 'ZIKIR-PETANG', 'SALAM-ORANG-TUA']],
            'SMP-MTS' => ['Template SMP/MTs', 'SMP/MTs', ['TAHAJUD-WITIR', 'SUBUH', 'ZUHUR', 'ASHAR', 'MAGHRIB', 'ISYA', 'TILAWAH-QURAN', 'MURAJAAH', 'PUASA-SUNNAH', 'TEPAT-WAKTU']],
            'SMA-MA' => ['Template SMA/MA', 'SMA/MA', ['TAHAJUD-WITIR', 'SUBUH', 'ZUHUR', 'ASHAR', 'MAGHRIB', 'ISYA', 'TILAWAH-QURAN', 'MURAJAAH', 'KAJIAN', 'REFLEKSI']],
            'PESANTREN-PUTRA' => ['Template Pesantren Putra', 'Pesantren Putra', ['TAHAJUD-WITIR', 'SUBUH', 'ZUHUR', 'ASHAR', 'MAGHRIB', 'ISYA', 'HALAQAH', 'TILAWAH-QURAN', 'MURAJAAH', 'KEBERSIHAN-KAMAR', 'TEPAT-WAKTU']],
            'PESANTREN-PUTRI' => ['Template Pesantren Putri', 'Pesantren Putri', ['TAHAJUD-WITIR', 'SUBUH', 'ZUHUR', 'ASHAR', 'MAGHRIB', 'ISYA', 'HALAQAH', 'TILAWAH-QURAN', 'MURAJAAH', 'KEBERSIHAN-KAMAR', 'TEPAT-WAKTU']],
        ];

        foreach ($templateDefinitions as $code => [$name, $level, $agendaCodes]) {
            $unit = EducationUnit::query()
                ->where('level', 'LIKE', '%'.strtok($level, '/ ').'%')
                ->first();
            $template = MutabaahTemplate::updateOrCreate(['code' => "TPL-{$code}"], [
                'name' => $name, 'education_unit_id' => $unit?->id,
                'education_level' => $level, 'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id, 'start_date' => $semester->start_date,
                'end_date' => $semester->end_date, 'description' => "Template contoh {$level}.",
                'status' => 'active', 'is_active' => true, 'level' => $level, 'unit_id' => $unit?->id,
            ]);

            foreach ($agendaCodes as $index => $agendaCode) {
                $agenda = $agendas[$agendaCode];
                $template->items()->updateOrCreate(['agenda_item_id' => $agenda->id], [
                    'sort_order' => $index + 1, 'weight' => $agenda->weight,
                    'target_value' => in_array($agenda->input_type->value, ['pages', 'verses']) ? ($level === 'TK/RA' ? 1 : 2) : null,
                    'is_required' => true,
                    'requires_parent_signature' => in_array($agendaCode, ['SALAM-ORANG-TUA', 'BANTU-ORANG-TUA']),
                    'instruction' => $this->instruction($level, $agenda->name),
                    'is_active' => true,
                ]);
            }
        }

        $this->command?->info('Seeder Mutaba’ah selesai: 7 kategori, 20 agenda, dan 6 template jenjang diproses secara idempotent.');
    }

    private function instruction(string $level, string $agenda): string
    {
        return match ($level) {
            'TK/RA' => "Dampingi anak melaksanakan {$agenda} dengan sederhana.",
            'SD/MI' => "Isi pencapaian {$agenda} dan beri motivasi bila belum konsisten.",
            'SMP/MTs' => "Nilai kemandirian dan kedisiplinan {$agenda}.",
            'SMA/MA' => "Catat target, realisasi, dan refleksi {$agenda}.",
            default => "Pantau pelaksanaan {$agenda} sesuai tata tertib pesantren.",
        };
    }
}
