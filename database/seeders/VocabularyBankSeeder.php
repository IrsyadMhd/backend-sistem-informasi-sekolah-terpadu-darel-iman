<?php

namespace Database\Seeders;

use App\Models\EducationUnit;
use App\Models\Student;
use App\Models\User;
use App\Models\VocabularyBank;
use App\Models\StudentVocabularySubmission;
use Illuminate\Database\Seeder;

class VocabularyBankSeeder extends Seeder
{
    public function run(): void
    {
        $units = EducationUnit::all();
        if ($units->isEmpty()) {
            return;
        }

        $user = User::first() ?? User::factory()->create();

        $vocabularies = [
            // Arabic - SMP
            [
                'language' => 'arab',
                'word' => 'مَكْتَبَةٌ (Maktabatun)',
                'phonetic' => 'Maktabah',
                'translation' => 'Perpustakaan',
                'example_sentence' => 'أَذْهَبُ إِلَى الْمَكْتَبَةِ لِقِرَاءَةِ الْكُتُبِ',
                'target_level' => 'SMP',
                'theme' => 'Lingkungan Sekolah',
                'status' => 'approved',
            ],
            [
                'language' => 'arab',
                'word' => 'فَصْلٌ دِرَاسِيٌّ (Faslun Dirasiyyun)',
                'phonetic' => 'Fashlun Dirasiy',
                'translation' => 'Ruang Kelas Belajar',
                'example_sentence' => 'فَصْلُنَا نَظِيْفٌ وَمُرَتَّبٌ دَائِمًا',
                'target_level' => 'SMP',
                'theme' => 'Sarana Madrasah',
                'status' => 'approved',
            ],
            [
                'language' => 'arab',
                'word' => 'حَاسُوْبٌ (Haasubun)',
                'phonetic' => 'Haasub',
                'translation' => 'Komputer / Laptop',
                'example_sentence' => 'نَتَعَلَّمُ تِكْنُولُوجِيَا الْمَعْلُومَاتِ بِالْحَاسُوبِ',
                'target_level' => 'SMP',
                'theme' => 'Teknologi & Laboratorium',
                'status' => 'pending_divisi', // Sudah diapprove Kepsek, menunggu Divisi
            ],
            [
                'language' => 'arab',
                'word' => 'مِجْهَرٌ (Mijharun)',
                'phonetic' => 'Mijhar',
                'translation' => 'Mikroskop Laboratorium',
                'example_sentence' => 'نَنْظُرُ إِلَى الْخَلَايَا الصَّغِيرَةِ بِالْمِجْهَرِ',
                'target_level' => 'SMP',
                'theme' => 'Laboratorium IPA',
                'status' => 'pending_principal', // Menunggu telaah Kepsek unit
            ],

            // English - SMA
            [
                'language' => 'inggris',
                'word' => 'Perseverance',
                'phonetic' => '/ˌpɜː.sɪˈvɪə.rəns/',
                'translation' => 'Kegigihan / Ketekunan',
                'example_sentence' => 'Success in seeking knowledge requires great perseverance and patience.',
                'target_level' => 'SMA',
                'theme' => 'Akhlaq & Karakter',
                'status' => 'approved',
            ],
            [
                'language' => 'inggris',
                'word' => 'Integrity',
                'phonetic' => '/ɪnˈteɡ.rə.ti/',
                'translation' => 'Integritas / Kejujuran',
                'example_sentence' => 'An honest Muslim student always upholds academic integrity during exams.',
                'target_level' => 'SMA',
                'theme' => 'Akhlaq & Karakter',
                'status' => 'approved',
            ],
            [
                'language' => 'inggris',
                'word' => 'Collaboration',
                'phonetic' => '/kəˌlæb.əˈreɪ.ʃən/',
                'translation' => 'Kolaborasi / Kerjasama',
                'example_sentence' => 'Effective collaboration among students yields outstanding project results.',
                'target_level' => 'SMA',
                'theme' => 'Keterampilan Abad 21',
                'status' => 'pending_divisi',
            ],
            [
                'language' => 'inggris',
                'word' => 'Hypothesis',
                'phonetic' => '/haɪˈpɒθ.ə.sɪs/',
                'translation' => 'Hipotesis / Dugaan Ilmiah',
                'example_sentence' => 'Students formulate a testable hypothesis before conducting the science experiment.',
                'target_level' => 'SMA',
                'theme' => 'Karya Ilmiah',
                'status' => 'pending_principal',
            ],

            // Arabic - Ponpes
            [
                'language' => 'arab',
                'word' => 'مُهَاجِعٌ (Muhaajiun)',
                'phonetic' => 'Muhaaji',
                'translation' => 'Kamar Asrama Santri',
                'example_sentence' => 'نُنَظِّفُ الْمُهَاجِعَ كُلَّ صَبَاحٍ بَعْدَ صَلَاةِ الْفَجْرِ',
                'target_level' => 'Ponpes',
                'theme' => 'Kehidupan Asrama',
                'status' => 'approved',
            ],
            [
                'language' => 'arab',
                'word' => 'حَلَقَةُ التَّحْفِيْظِ (Halaqotut Tahfizh)',
                'phonetic' => 'Halaqah Tahfizh',
                'translation' => 'Halaqah Setoran Qur’an',
                'example_sentence' => 'يَجْلِسُ الطُّلَّابُ فِي حَلَقَةِ التَّحْفِيْظِ بِأَدَبٍ وَخُشُوعٍ',
                'target_level' => 'Ponpes',
                'theme' => 'Aktivitas Halaqah',
                'status' => 'approved',
            ],
        ];

        foreach ($vocabularies as $index => $item) {
            $unit = $units[$index % count($units)];
            $vocab = VocabularyBank::updateOrCreate(
                [
                    'word' => $item['word'],
                    'unit_id' => $unit->id,
                ],
                [
                    'language' => $item['language'],
                    'phonetic' => $item['phonetic'],
                    'translation' => $item['translation'],
                    'example_sentence' => $item['example_sentence'],
                    'target_level' => $item['target_level'],
                    'theme' => $item['theme'],
                    'created_by' => $user->id,
                    'status' => $item['status'],
                    'principal_id' => in_array($item['status'], ['pending_divisi', 'approved']) ? $user->id : null,
                    'principal_approved_at' => in_array($item['status'], ['pending_divisi', 'approved']) ? now() : null,
                    'principal_notes' => in_array($item['status'], ['pending_divisi', 'approved']) ? 'Sesuai dengan target kurikulum bahasa unit.' : null,
                    'divisi_id' => $item['status'] === 'approved' ? $user->id : null,
                    'divisi_approved_at' => $item['status'] === 'approved' ? now() : null,
                    'divisi_notes' => $item['status'] === 'approved' ? 'Telah diverifikasi dan disahkan sebagai materi setoran resmi.' : null,
                ]
            );

            // Jika status approved, buat 1 simulasi setoran santri
            if ($vocab->status === 'approved') {
                $student = Student::where('unit_id', $unit->id)->first();
                if ($student) {
                    StudentVocabularySubmission::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'vocabulary_id' => $vocab->id,
                        ],
                        [
                            'teacher_id' => $user->id,
                            'unit_id' => $unit->id,
                            'submission_date' => now()->toDateString(),
                            'fluency_rating' => 'mutqin',
                            'score' => 95,
                            'notes' => 'Lafal makhraj sangat fasih dan hafalan mutqin.',
                        ]
                    );
                }
            }
        }
    }
}
