<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\EducationUnit;
use App\Models\Employee;
use App\Models\Kelas;
use App\Models\LmsPengumpulanTugas;
use App\Models\LmsPenugasan;
use App\Models\ParentModel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VerifyPortalSecurityCommand extends Command
{
    protected $signature = 'simsit:verify-portal-security';
    protected $description = 'Verifikasi empiris keamanan, otorisasi, dan isolasi data portal SIMSIT';

    public function handle(): int
    {
        $this->info('Memulai verifikasi keamanan dan otorisasi API portal SIMSIT...');

        $results = [];

        // Identifikasi Akun Uji
        $parentUser = User::where('email', 'orangtua@dareliman.sch.id')->first();
        $studentUser = User::where('email', 'siswa@dareliman.sch.id')->first();

        if (!$parentUser || !$studentUser) {
            $this->error('Akun uji orang tua atau siswa tidak ditemukan di database.');
            return 1;
        }

        $parent = ParentModel::where('user_id', $parentUser->id)->first();
        $ownStudent = Student::where('user_id', $studentUser->id)->first();

        // Cari anak SD dan anak SMP milik orang tua
        $parentChildren = $parent ? $parent->students()->with(['kelas', 'educationUnit'])->get() : collect();
        $childSmp = $parentChildren->first(fn($c) => str_contains(strtolower($c->kelas?->nama_kelas ?? ''), 'vii') || str_contains(strtolower($c->educationUnit?->level ?? ''), 'smp'));
        $childSd = $parentChildren->first(fn($c) => str_contains(strtolower($c->educationUnit?->level ?? ''), 'sd') || str_contains(strtolower($c->educationUnit?->name ?? ''), 'sd'));

        // Helper to execute request with authenticated user and Accept: application/json
        $executeRequest = function (string $uri, string $method, ?User $user, array $parameters = []) {
            auth()->setUser($user);
            auth('sanctum')->setUser($user);
            $req = Request::create($uri, $method, $parameters);
            $req->headers->set('Accept', 'application/json');
            $req->setUserResolver(fn() => $user);
            return app()->handle($req);
        };

        // Skenario 1: Orang tua membaca data anak terhubung
        $resp1 = $executeRequest('/api/portal/children', 'GET', $parentUser);
        $body1 = json_decode($resp1->getContent(), true);
        $childIds = collect($body1['data'] ?? [])->pluck('id')->toArray();
        $isAllLinked = count($childIds) > 0 && collect($childIds)->every(fn($id) => in_array($id, $parentChildren->pluck('id')->toArray()));

        $results[] = [
            'scenario' => 'Orang tua membaca data anak yang terhubung',
            'endpoint' => 'GET /api/portal/children',
            'status' => $resp1->getStatusCode(),
            'expected_status' => 200,
            'response_sample' => 'Jumlah anak terdeteksi: ' . count($childIds),
            'verified' => $resp1->getStatusCode() === 200 && $isAllLinked,
        ];

        // Skenario 2: Manipulasi child_id milik anak lain oleh akun Siswa
        $unlinkedStudent = Student::whereNotIn('id', [$ownStudent?->id])->first();
        $resp2 = $executeRequest('/api/portal/dashboard?child_id=' . ($unlinkedStudent?->id ?? '00000000-0000-0000-0000-000000000000'), 'GET', $studentUser);
        $body2 = json_decode($resp2->getContent(), true);
        $activeReturnedStudentId = $body2['data']['student']['id'] ?? $body2['data']['id'] ?? null;
        $isSafe = $activeReturnedStudentId === $ownStudent?->id;

        $results[] = [
            'scenario' => 'Manipulasi child_id oleh siswa (anti-spoofing)',
            'endpoint' => 'GET /api/portal/dashboard?child_id=' . ($unlinkedStudent?->id ?? 'fake-id'),
            'status' => $resp2->getStatusCode(),
            'expected_status' => 200,
            'response_sample' => 'Returned Student ID: ' . ($activeReturnedStudentId ?: 'None') . ' (Verified milik sendiri)',
            'verified' => $resp2->getStatusCode() === 200 && $isSafe,
        ];

        // Skenario 3: Orang tua kirim tugas atas nama anak SMP (Harus Ditolak 403)
        $kelasSmp = $childSmp?->kelas_id ?? Kelas::where('nama_kelas', 'LIKE', '%VII%')->first()?->id;
        $ephemeralAssignSmp = LmsPenugasan::create([
            'kelas_id' => $kelasSmp,
            'mata_pelajaran_id' => Subject::first()?->id,
            'guru_id' => Employee::first()?->id,
            'semester_id' => Semester::first()?->id,
            'tahun_ajaran_id' => AcademicYear::first()?->id,
            'judul_tugas' => 'Tugas Ephemeral SMP Test',
            'deskripsi' => 'Uji otorisasi jenjang',
            'tipe_tugas' => 'mandiri',
            'jenis_tugas' => 'tugas',
            'nilai_maksimal' => 100,
            'deadline' => now()->addDays(2),
            'is_published' => true,
        ]);

        $initialSubCount = LmsPengumpulanTugas::where('penugasan_id', $ephemeralAssignSmp->id)->count();

        $resp3 = $executeRequest('/api/portal/assignments/' . $ephemeralAssignSmp->id . '/submit', 'POST', $parentUser, [
            'child_id' => $childSmp?->id,
            'jawaban_teks' => 'Jawaban orang tua untuk anak SMP',
        ]);
        $body3 = json_decode($resp3->getContent(), true);
        $afterSubCount3 = LmsPengumpulanTugas::where('penugasan_id', $ephemeralAssignSmp->id)->count();
        $noPartialData3 = $afterSubCount3 === $initialSubCount;

        $results[] = [
            'scenario' => 'Orang tua kirim tugas anak SMP (Dilarang Aturan Bisnis)',
            'endpoint' => 'POST /api/portal/assignments/' . $ephemeralAssignSmp->id . '/submit',
            'status' => $resp3->getStatusCode(),
            'expected_status' => 403,
            'response_sample' => $body3['message'] ?? '',
            'no_partial_data' => $noPartialData3,
            'verified' => $resp3->getStatusCode() === 403 && $noPartialData3 && str_contains($body3['message'] ?? '', 'SMP dan SMA'),
        ];

        // Skenario 4: Orang tua kirim tugas atas nama anak SD (Diizinkan 200)
        $kelasSd = $childSd?->kelas_id ?? Kelas::where('nama_kelas', 'LIKE', '%1-%')->first()?->id;
        $ephemeralAssignSd = LmsPenugasan::create([
            'kelas_id' => $kelasSd,
            'mata_pelajaran_id' => Subject::first()?->id,
            'guru_id' => Employee::first()?->id,
            'semester_id' => Semester::first()?->id,
            'tahun_ajaran_id' => AcademicYear::first()?->id,
            'judul_tugas' => 'Tugas Ephemeral SD Test',
            'deskripsi' => 'Uji otorisasi jenjang SD',
            'tipe_tugas' => 'mandiri',
            'jenis_tugas' => 'tugas',
            'nilai_maksimal' => 100,
            'deadline' => now()->addDays(2),
            'is_published' => true,
        ]);

        $resp4 = $executeRequest('/api/portal/assignments/' . $ephemeralAssignSd->id . '/submit', 'POST', $parentUser, [
            'child_id' => $childSd?->id,
            'jawaban_teks' => 'Jawaban orang tua untuk anak SD',
        ]);
        $body4 = json_decode($resp4->getContent(), true);
        $subSdCreated = LmsPengumpulanTugas::where('penugasan_id', $ephemeralAssignSd->id)->where('siswa_id', $childSd?->id)->first();

        $results[] = [
            'scenario' => 'Orang tua kirim tugas anak SD (Diizinkan Aturan Bisnis)',
            'endpoint' => 'POST /api/portal/assignments/' . $ephemeralAssignSd->id . '/submit',
            'status' => $resp4->getStatusCode(),
            'expected_status' => 200,
            'response_sample' => $body4['message'] ?? '',
            'verified' => $resp4->getStatusCode() === 200 && $subSdCreated !== null,
        ];

        // Skenario 5: Siswa kirim tugas miliknya sendiri (Diizinkan 200)
        // Hubungkan sementara kelas siswa untuk fixture ini
        $activeKelas = Kelas::where('status', 'Aktif')->first();
        $ownStudent->update(['kelas_id' => $activeKelas?->id, 'class_id' => $activeKelas?->id]);

        $ephemeralAssignStudent = LmsPenugasan::create([
            'kelas_id' => $activeKelas?->id,
            'mata_pelajaran_id' => Subject::first()?->id,
            'guru_id' => Employee::first()?->id,
            'semester_id' => Semester::first()?->id,
            'tahun_ajaran_id' => AcademicYear::first()?->id,
            'judul_tugas' => 'Tugas Mandiri Siswa Uji',
            'deskripsi' => 'Uji mandiri siswa',
            'tipe_tugas' => 'mandiri',
            'jenis_tugas' => 'tugas',
            'nilai_maksimal' => 100,
            'deadline' => now()->addDays(2),
            'is_published' => true,
        ]);

        $resp5 = $executeRequest('/api/portal/assignments/' . $ephemeralAssignStudent->id . '/submit', 'POST', $studentUser, [
            'jawaban_teks' => 'Jawaban teks mandiri siswa',
        ]);
        $body5 = json_decode($resp5->getContent(), true);
        $subStudentCreated = LmsPengumpulanTugas::where('penugasan_id', $ephemeralAssignStudent->id)->where('siswa_id', $ownStudent->id)->first();

        $results[] = [
            'scenario' => 'Siswa mengirim tugas kelasnya sendiri',
            'endpoint' => 'POST /api/portal/assignments/' . $ephemeralAssignStudent->id . '/submit',
            'status' => $resp5->getStatusCode(),
            'expected_status' => 200,
            'response_sample' => $body5['message'] ?? '',
            'verified' => $resp5->getStatusCode() === 200 && $subStudentCreated !== null,
        ];

        // Skenario 6: Siswa mencoba submit tugas kelas orang lain (Harus Ditolak 403)
        $otherKelas = Kelas::where('id', '!=', $activeKelas?->id)->first();
        $ephemeralAssignOtherClass = LmsPenugasan::create([
            'kelas_id' => $otherKelas?->id,
            'mata_pelajaran_id' => Subject::first()?->id,
            'guru_id' => Employee::first()?->id,
            'semester_id' => Semester::first()?->id,
            'tahun_ajaran_id' => AcademicYear::first()?->id,
            'judul_tugas' => 'Tugas Kelas Lain',
            'deskripsi' => 'Uji isolasi kelas',
            'tipe_tugas' => 'mandiri',
            'jenis_tugas' => 'tugas',
            'nilai_maksimal' => 100,
            'deadline' => now()->addDays(2),
            'is_published' => true,
        ]);

        $beforeOtherSubCount = LmsPengumpulanTugas::where('penugasan_id', $ephemeralAssignOtherClass->id)->count();

        $resp6 = $executeRequest('/api/portal/assignments/' . $ephemeralAssignOtherClass->id . '/submit', 'POST', $studentUser, [
            'jawaban_teks' => 'Jawaban menyusup kelas lain',
        ]);
        $body6 = json_decode($resp6->getContent(), true);
        $afterOtherSubCount = LmsPengumpulanTugas::where('penugasan_id', $ephemeralAssignOtherClass->id)->count();
        $noPartialData6 = $afterOtherSubCount === $beforeOtherSubCount;

        $results[] = [
            'scenario' => 'Siswa submit tugas kelas lain (Ditolak 403)',
            'endpoint' => 'POST /api/portal/assignments/' . $ephemeralAssignOtherClass->id . '/submit',
            'status' => $resp6->getStatusCode(),
            'expected_status' => 403,
            'response_sample' => $body6['message'] ?? '',
            'no_partial_data' => $noPartialData6,
            'verified' => $resp6->getStatusCode() === 403 && $noPartialData6 && str_contains($body6['message'] ?? '', 'bukan untuk kelas Anda'),
        ];

        // Skenario 7: Role tidak berwenang (Siswa akses endpoint khusus orang tua)
        $resp7 = $executeRequest('/api/portal/children/' . $ownStudent->id . '/worship-input', 'POST', $studentUser, [
            'tanggal' => now()->toDateString(),
            'sholat_subuh' => true,
        ]);
        $body7 = json_decode($resp7->getContent(), true);

        $results[] = [
            'scenario' => 'Siswa akses endpoint khusus orang tua (Worship Input)',
            'endpoint' => 'POST /api/portal/children/{id}/worship-input',
            'status' => $resp7->getStatusCode(),
            'expected_status' => 403,
            'response_sample' => $body7['message'] ?? 'Forbidden / UnauthorizedException',
            'verified' => $resp7->getStatusCode() === 403,
        ];

        // Skenario 8: Penugasan draft (belum dipublikasikan) ditolak submit-nya
        $ephemeralAssignDraft = LmsPenugasan::create([
            'kelas_id' => $activeKelas?->id,
            'mata_pelajaran_id' => Subject::first()?->id,
            'guru_id' => Employee::first()?->id,
            'semester_id' => Semester::first()?->id,
            'tahun_ajaran_id' => AcademicYear::first()?->id,
            'judul_tugas' => 'Tugas Draft Belum Terbit',
            'deskripsi' => 'Uji status publish',
            'tipe_tugas' => 'mandiri',
            'jenis_tugas' => 'tugas',
            'nilai_maksimal' => 100,
            'deadline' => now()->addDays(2),
            'is_published' => false,
            'status' => 'draft',
        ]);

        $beforeDraftCount = LmsPengumpulanTugas::where('penugasan_id', $ephemeralAssignDraft->id)->count();

        $resp8 = $executeRequest('/api/portal/assignments/' . $ephemeralAssignDraft->id . '/submit', 'POST', $studentUser, [
            'jawaban_teks' => 'Jawaban untuk tugas draft',
        ]);
        $body8 = json_decode($resp8->getContent(), true);
        $afterDraftCount = LmsPengumpulanTugas::where('penugasan_id', $ephemeralAssignDraft->id)->count();

        $results[] = [
            'scenario' => 'Submit penugasan status draft / unpublished',
            'endpoint' => 'POST /api/portal/assignments/' . $ephemeralAssignDraft->id . '/submit',
            'status' => $resp8->getStatusCode(),
            'expected_status' => 403,
            'response_sample' => $body8['message'] ?? '',
            'no_partial_data' => $afterDraftCount === $beforeDraftCount,
            'verified' => $resp8->getStatusCode() === 403 && ($afterDraftCount === $beforeDraftCount) && str_contains($body8['message'] ?? '', 'belum dipublikasikan'),
        ];

        // CLEANUP OTOMATIS FIXTURE EPHEMERAL
        $this->info('Membersihkan seluruh fixture pengujian sementara...');
        LmsPengumpulanTugas::whereIn('penugasan_id', [
            $ephemeralAssignSmp->id,
            $ephemeralAssignSd->id,
            $ephemeralAssignStudent->id,
            $ephemeralAssignOtherClass->id,
            $ephemeralAssignDraft->id,
        ])->forceDelete();

        $ephemeralAssignSmp->forceDelete();
        $ephemeralAssignSd->forceDelete();
        $ephemeralAssignStudent->forceDelete();
        $ephemeralAssignOtherClass->forceDelete();
        $ephemeralAssignDraft->forceDelete();

        // Kembalikan kelas_id Siswa Test ke null
        $ownStudent->update(['kelas_id' => null, 'class_id' => null]);

        $this->info('Verifikasi selesai. Seluruh fixture bersih.');

        // Print Output Table
        $this->table(
            ['Skenario', 'Endpoint', 'HTTP Status', 'Status Diharapkan', 'Lolos?', 'Respon Aktual'],
            collect($results)->map(fn($r) => [
                $r['scenario'],
                $r['endpoint'],
                $r['status'],
                $r['expected_status'],
                $r['verified'] ? 'PASS' : 'FAIL',
                Str::limit($r['response_sample'], 45),
            ])
        );

        $allPassed = collect($results)->every(fn($r) => $r['verified']);
        return $allPassed ? 0 : 1;
    }
}
