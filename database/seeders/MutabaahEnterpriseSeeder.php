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
            ['code' => 'MUTABAAH-YAUMIYYAH', 'name' => 'Mutaba’ah Yaumiyyah', 'icon' => 'Heart', 'color' => '#1E8E5A'],
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

        // 29 Butir Agenda Sesuai Formulir Fisik "Mutaba'ah Yaumiyyah" + Agenda Pendukung Kurikulum
        $agendaDefinitions = [
            // No. 1 - 6: Sholat
            ['SHALAT', 'TAHAJUD-WITIR', 'Tahajjud/Witir', 'status', 5],
            ['SHALAT', 'SUBUH', 'Subuh', 'status', 5],
            ['SHALAT', 'ZUHUR', 'Zuhur', 'status', 5],
            ['SHALAT', 'ASHAR', 'Ashar', 'status', 5],
            ['SHALAT', 'MAGHRIB', 'Magrib', 'status', 5],
            ['SHALAT', 'ISYA', 'Isha', 'status', 5],

            // No. 7 - 31: Mutaba'ah Yaumiyyah
            ['MUTABAAH-YAUMIYYAH', 'WUDHU-SEBELUM-TIDUR', "Berwudhu' sebelum Tidur", 'yes_no', 3],
            ['MUTABAAH-YAUMIYYAH', 'DOA-ZIKIR-SEBELUM-TIDUR', "Do'a dan zikir sebelum Tidur", 'yes_no', 3],
            ['MUTABAAH-YAUMIYYAH', 'DOA-BANGUN-TIDUR', "Do'a bangun tidur", 'yes_no', 3],
            ['SHALAT', 'SHOLAT-SUNNAH-FAJAR', 'Sholat Sunnah Fajar', 'status', 4],
            ['MUTABAAH-YAUMIYYAH', 'ZIKIR-PAGI', 'Zikir pagi', 'checklist', 4],
            ['MUTABAAH-YAUMIYYAH', 'SEDEKAH', 'Sedekah', 'yes_no', 3],
            ['MUTABAAH-YAUMIYYAH', 'SALAM-ORANG-TUA', 'Bersalaman dengan ortu', 'yes_no', 3],
            ['MUTABAAH-YAUMIYYAH', 'DOA-KELUAR-RUMAH', 'Doa keluar rumah', 'yes_no', 3],
            ['MUTABAAH-YAUMIYYAH', 'DOA-NAIK-KENDARAAN', "Do'a naik kendaraan", 'yes_no', 3],
            ['SHALAT', 'DHUHA', 'Sholat Dhuha', 'status', 4],
            ['MUTABAAH-YAUMIYYAH', 'MENEBAR-SALAM', 'Menebar salam', 'yes_no', 3],
            ['SHALAT', 'RAWATIB-ZUHUR', 'Qobla/ Bakdiyah zuhur', 'status', 4],
            ['SHALAT', 'QOBLIYAH-ASHAR', 'Qobliyah Ashar', 'status', 3],
            ['MUTABAAH-YAUMIYYAH', 'ZIKIR-PETANG', 'Zikir Sore', 'checklist', 4],
            ['SHALAT', 'BAKDIYAH-MAGRIB', 'Qobla/Bakdiyah Magrib', 'status', 4],
            ['MUTABAAH-YAUMIYYAH', 'MENDOAKAN-ORTU', 'Mendoakan Ortu', 'yes_no', 3],
            ['MUTABAAH-YAUMIYYAH', 'MURAJAAH-DENGAN-ORTU', 'Murajaah dengan Ortu', 'status', 5],
            ['MUTABAAH-YAUMIYYAH', 'BACA-SURAT-ALKAHFI', 'Membaca Surat Alkahfi', 'yes_no', 5],
            ['MUTABAAH-YAUMIYYAH', 'PUASA-SUNNAH', 'Puasa Sunnah Senin & Kamis', 'yes_no', 5],
            ['MUTABAAH-YAUMIYYAH', 'KAJIAN', 'Mendengar kajian ( ceramah agama )', 'yes_no', 4],
            ['MUTABAAH-YAUMIYYAH', 'HAFALAN-DOA', 'Hafalan Doa ( 1 doa/ hari )', 'status', 4],
            ['MUTABAAH-YAUMIYYAH', 'HAFALAN-HADIST', 'Hafalan Hadist ( 1 hadis/ Pekan)', 'status', 4],
            ['MUTABAAH-YAUMIYYAH', 'KOSA-KATA-BAHASA', 'Kosa Kata Bahasa ( 5 kosa kata/ pekanan)', 'status', 4],

            // Butir Tambahan Pendukung
            ['TILAWAH', 'TILAWAH-QURAN', 'Tilawah Al-Qur’an', 'pages', 10],
            ['TAHFIZH', 'MURAJAAH', 'Murajaah', 'verses', 10],
            ['ADAB', 'BANTU-ORANG-TUA', 'Membantu Orang Tua', 'status', 4],
            ['KEBERSIHAN', 'JAGA-KEBERSIHAN', 'Menjaga Kebersihan', 'status', 4],
            ['KEDISIPLINAN', 'TEPAT-WAKTU', 'Datang Tepat Waktu', 'status', 5],
            ['SHALAT', 'TARAWIH', 'Tarawih', 'status', 5],
            ['IBADAH-HARIAN', 'PUASA-RAMADAN', 'Puasa Ramadan', 'status', 5],
            ['TILAWAH', 'TADARUS-RAMADAN', 'Tadarus Ramadan', 'pages', 10],
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

        // 29 Agenda Resmi Formulir Fisik Mutaba'ah Yaumiyyah
        $paperFormAgendas = [
            'TAHAJUD-WITIR', 'SUBUH', 'ZUHUR', 'ASHAR', 'MAGHRIB', 'ISYA',
            'WUDHU-SEBELUM-TIDUR', 'DOA-ZIKIR-SEBELUM-TIDUR', 'DOA-BANGUN-TIDUR',
            'SHOLAT-SUNNAH-FAJAR', 'ZIKIR-PAGI', 'SEDEKAH', 'SALAM-ORANG-TUA',
            'DOA-KELUAR-RUMAH', 'DOA-NAIK-KENDARAAN', 'DHUHA', 'MENEBAR-SALAM',
            'RAWATIB-ZUHUR', 'QOBLIYAH-ASHAR', 'ZIKIR-PETANG', 'BAKDIYAH-MAGRIB',
            'MENDOAKAN-ORTU', 'MURAJAAH-DENGAN-ORTU', 'BACA-SURAT-ALKAHFI',
            'PUASA-SUNNAH', 'KAJIAN', 'HAFALAN-DOA', 'HAFALAN-HADIST', 'KOSA-KATA-BAHASA',
        ];

        $templateDefinitions = [
            'MUTABAAH-TERPADU' => ['Template Mutaba’ah Yaumiyyah Terpadu', 'Semua Jenjang', $paperFormAgendas],
            'TK-RA' => ['Template TK/RA', 'TK/RA', ['SUBUH', 'SALAM-ORANG-TUA', 'BANTU-ORANG-TUA', 'JAGA-KEBERSIHAN', 'SEDEKAH', 'DOA-BANGUN-TIDUR', 'WUDHU-SEBELUM-TIDUR', 'MENEBAR-SALAM']],
            'SD-MI' => ['Template SD/MI', 'SD/MI', $paperFormAgendas],
            'SMP-MTS' => ['Template SMP/MTs', 'SMP/MTs', $paperFormAgendas],
            'SMA-MA' => ['Template SMA/MA', 'SMA/MA', $paperFormAgendas],
            'PESANTREN-PUTRA' => ['Template Pesantren Putra', 'Pesantren Putra', $paperFormAgendas],
            'PESANTREN-PUTRI' => ['Template Pesantren Putri', 'Pesantren Putri', $paperFormAgendas],
        ];

        foreach ($templateDefinitions as $code => [$name, $level, $agendaCodes]) {
            $unit = EducationUnit::query()
                ->where('level', 'LIKE', '%'.strtok($level, '/ ').'%')
                ->first();
            $template = MutabaahTemplate::updateOrCreate(['code' => "TPL-{$code}"], [
                'name' => $name, 'education_unit_id' => $unit?->id,
                'education_level' => $level, 'academic_year_id' => $academicYear->id,
                'semester_id' => $semester->id, 'start_date' => $semester->start_date,
                'end_date' => $semester->end_date, 'description' => "Template formulir mutabaah {$level}.",
                'status' => 'active', 'is_active' => true, 'level' => $level, 'unit_id' => $unit?->id,
            ]);

            foreach ($agendaCodes as $index => $agendaCode) {
                if (!isset($agendas[$agendaCode])) continue;
                $agenda = $agendas[$agendaCode];
                $template->items()->updateOrCreate(['agenda_item_id' => $agenda->id], [
                    'sort_order' => $index + 1, 'weight' => $agenda->weight,
                    'target_value' => in_array($agenda->input_type->value, ['pages', 'verses']) ? ($level === 'TK/RA' ? 1 : 2) : null,
                    'is_required' => true,
                    'requires_parent_signature' => in_array($agendaCode, [
                        'SALAM-ORANG-TUA', 'MENDOAKAN-ORTU', 'MURAJAAH-DENGAN-ORTU',
                        'WUDHU-SEBELUM-TIDUR', 'DOA-ZIKIR-SEBELUM-TIDUR', 'DOA-BANGUN-TIDUR',
                    ]),
                    'instruction' => $this->instruction($level, $agenda->name),
                    'is_active' => true,
                ]);
            }
        }

        // Pastikan Supervisor Assignment Aktif Tersedia
        $firstEmployee = \App\Models\Employee::where('status', 'Aktif')->first();
        $firstUnit = EducationUnit::first();
        $defaultTemplate = MutabaahTemplate::where('code', 'TPL-MUTABAAH-TERPADU')->first() ?? MutabaahTemplate::first();

        if ($firstEmployee && $firstUnit && $defaultTemplate) {
            \App\Models\MutabaahSupervisorAssignment::firstOrCreate(
                [
                    'employee_id' => $firstEmployee->id,
                    'education_unit_id' => $firstUnit->id,
                    'academic_year_id' => $academicYear->id,
                    'semester_id' => $semester->id,
                ],
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'supervisor_type' => 'pembimbing',
                    'template_id' => $defaultTemplate->id,
                    'start_date' => $semester->start_date,
                    'end_date' => $semester->end_date,
                    'is_primary' => true,
                    'can_input' => true,
                    'can_edit' => true,
                    'can_finalize' => true,
                    'can_view_report' => true,
                    'status' => 'active',
                ]
            );
        }

        $supervisor = \App\Models\MutabaahSupervisorAssignment::first();

        // Seed Rekam Jejak Harian untuk Seluruh Siswa Aktif pada Tanggal Hari Ini & Pekan Berjalan
        if ($defaultTemplate && $supervisor) {
            $today = now()->toDateString();
            $targetDates = [
                $today,
                now()->subDay()->toDateString(),
                '2026-09-09',
                '2026-09-24',
            ];

            $students = \App\Models\Student::active()->limit(50)->get();
            $templateItems = $defaultTemplate->items()->with('agendaItem')->get();

            foreach ($targetDates as $targetDate) {
                foreach ($students as $student) {
                    $header = \App\Models\MutabaahDailyHeader::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'activity_date' => $targetDate,
                            'template_id' => $defaultTemplate->id,
                        ],
                        [
                            'supervisor_assignment_id' => $supervisor->id,
                            'education_unit_id' => $student->unit_id ?? $supervisor->education_unit_id,
                            'kelas_id' => $student->class_id,
                            'rombel_id' => $student->kelas_id,
                            'academic_year_id' => $academicYear->id,
                            'semester_id' => $semester->id,
                            'status' => 'finalized',
                            'total_items' => $templateItems->count(),
                            'good_count' => intval($templateItems->count() * 0.85),
                            'less_count' => intval($templateItems->count() * 0.1),
                            'not_done_count' => intval($templateItems->count() * 0.05),
                            'na_count' => 0,
                            'score' => 92.50,
                            'supervisor_notes' => 'Alhamdulillah ananda istiqamah dalam menjalankan amalan yaumiyyah pekan ini.',
                            'finalized_at' => now(),
                            'created_by' => $student->user_id ?? $firstEmployee?->user_id ?? $student->id,
                            'updated_by' => $firstEmployee?->user_id ?? $student->id,
                        ]
                    );

                    // Buat detail per butir agenda
                    foreach ($templateItems as $idx => $tItem) {
                        $statusVal = ($idx % 7 === 0) ? 'good' : (($idx % 11 === 0) ? 'less' : 'good');
                        \App\Models\MutabaahDailyDetail::updateOrCreate(
                            [
                                'daily_header_id' => $header->id,
                                'template_item_id' => $tItem->id,
                            ],
                            [
                                'agenda_item_id' => $tItem->agenda_item_id,
                                'status_value' => $statusVal,
                                'numeric_value' => null,
                                'notes' => null,
                                'input_source' => 'system',
                                'input_location' => in_array($tItem->agendaItem?->code, ['WUDHU-SEBELUM-TIDUR', 'DOA-BANGUN-TIDUR', 'MENDOAKAN-ORTU']) ? 'home' : 'school',
                                'verification_status' => 'verified',
                                'input_by' => $student->user_id ?? $student->id,
                                'input_at' => now(),
                            ]
                        );
                    }

                    // Tanda Tangan Orang Tua
                    if ($student->parent_id || $student->user_id) {
                        \App\Models\MutabaahParentSignature::updateOrCreate(
                            [
                                'daily_header_id' => $header->id,
                            ],
                            [
                                'parent_user_id' => $student->user_id ?? $header->created_by,
                                'signature_status' => 'approved',
                                'comment' => 'Alhamdulillah ananda melaksanakannya dengan tertib di rumah.',
                                'signed_at' => now(),
                            ]
                        );
                    }
                }
            }
        }

        $this->command?->info('Seeder Mutaba’ah selesai: 8 kategori, 29 agenda resmi formulir fisik, dan data harian siswa terpadu.');
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
