# Dokumentasi 28-Poin Master Mata Pelajaran (Master Subject Module)
Sistem Manajemen Sekolah Terpadu (ERP & LMS)

---

## 1. Overview & Business Intent
Modul Master Mata Pelajaran dirancang sebagai **Single Source of Truth** untuk seluruh aktivitas akademik dan LMS (Learning Management System). Modul ini menyediakan fondasi terstruktur untuk mengelola identitas, pengelompokan, bobot nilai, serta relasi multidimensional mata pelajaran terhadap seluruh ekosistem sekolah.

## 2. Database Schema Definition & Integrity Rules
Tabel `subjects` diperluas dengan skema data lengkap:
- `id` (UUID Primary Key)
- `unit_pendidikan_id` (FK ke `education_units`)
- `kurikulum_id` (FK ke `master_kurikulum`)
- `kode_mapel` (VARCHAR 50, Unique per Unit & Kurikulum)
- `nama_mapel` (VARCHAR 150)
- `nama_singkat` (VARCHAR 50)
- `kelompok_mapel` (Kelompok A, Kelompok B, Kelompok C, Muatan Lokal, Ekstrakurikuler)
- `kategori` (Wajib, Pilihan, Muatan Lokal)
- `jenjang` (TK, SD, SMP, SMA, SMK)
- `tingkat_kelas` (Kelas 1 - 12 / All)
- `jam_pelajaran` (Integer JP per minggu)
- `kkm` (Decimal 5,2)
- `bobot_pengetahuan` (SmallInt, default 40)
- `bobot_keterampilan` (SmallInt, default 40)
- `bobot_sikap` (SmallInt, default 20)
- `warna` (VARCHAR 20, Hex color)
- `ikon` (VARCHAR 50, Lucide Icon)
- `urutan_tampil` (Unsigned Int)
- `status` (Boolean)
- `deskripsi` (Text)

## 3. Education Unit Integration Rules
Mendukung skema Multi-Unit Pendidikan (TK, SD, SMP, SMA, SMK). Setiap mata pelajaran terikat pada satu Unit Pendidikan utama untuk memisahkan domain akademik dan hak akses antarinstitusi dalam satu yayasan.

## 4. Academic Year & Semester Multi-Tenancy Scope
Terkoneksi secara langsung maupun implisit melalui `master_kurikulum` dan jadwal ke `academic_years` dan `semesters`. Memungkinkan pemetaan mata pelajaran yang dinamis mengikuti pergantian periode akademik.

## 5. Master Curriculum Binding & Validation
Mata pelajaran terikat pada `master_kurikulum`. Sistem memvalidasi bahwa kombinasi `kode_mapel` + `kurikulum_id` + `unit_pendidikan_id` harus unik untuk mencegah tumpang tindih kurikulum.

## 6. Teacher / Instructor Multi-Assignment Mechanics
Relasi N:N (Many-to-Many) via pivot table `subject_teachers` (`subject_id`, `guru_id`). Memungkinkan satu mata pelajaran diampu oleh beberapa guru (team teaching / paralel), dan satu guru mengampu beberapa mata pelajaran.

## 7. Class & Rombel Association Architecture
Relasi N:N via pivot tables `subject_classes` (`subject_id`, `kelas_id`) dan `subject_rombel` (`subject_id`, `rombel_id`). Memfasilitasi penetapan mata pelajaran spesifik untuk tingkat kelas tertentu maupun rombel individual.

## 8. Schedule (Jadwal Pelajaran) Relation & Constraints
Mata pelajaran menjadi kunci pencocokan pada tabel `class_schedules`. Sistem memastikan bahwa alokasi jam pelajaran (JP) pada jadwal harian tidak melebihi kuota `jam_pelajaran` mingguan yang didefinisikan di Master Mata Pelajaran.

## 9. LMS Modul Ajar Linkage
Relasi 1:N ke tabel `lms_modul_ajar`. Setiap Modul Ajar (RPP / Perangkat Ajar) yang disusun oleh guru terhubung secara langsung ke mata pelajaran terkait.

## 10. LMS Learning Materials (Materi Pembelajaran) Integration
Relasi 1:N ke tabel `lms_materi`. Materi pembelajaran berupa dokumen PDF, video, link interaktif, dan slide presentasi dikelompokkan secara terstruktur per mata pelajaran.

## 11. LMS Learning Outcomes (CP) & Learning Objectives (TP) Relations
Relasi 1:N ke `lms_capaian_pembelajaran` (CP) dan `lms_tujuan_pembelajaran` (TP). Menjadi acuan ketercapaian Alur Tujuan Pembelajaran (ATP) dalam Kurikulum Merdeka maupun SIT.

## 12. Assignment (Penugasan & Pengumpulan) Integration
Relasi 1:N ke `lms_penugasan` dan `lms_pengumpulan_tugas`. Seluruh tugas harian, PR, dan proyek terikat pada mata pelajaran dan berpartisipasi dalam pembobotan nilai akhir.

## 13. Exam Specification Grid (Kisi-kisi Ujian) Integration
Relasi 1:N ke `lms_kisi_kisi`. Guru menyusun kisi-kisi soal ujian berdasarkan elemen CP/TP pada mata pelajaran yang diampu.

## 14. Item Bank (Bank Soal) Mapping
Relasi 1:N ke `lms_bank_soal`. Soal-soal pilihan ganda, esai, dan menjodohkan dikategorikan berdasarkan mata pelajaran untuk dipakai berulang kali.

## 15. CBT (Computer Based Testing) Online Exam Linkage
Relasi 1:N ke `lms_ujian` dan `lms_ujian_sesi`. Ujian online CBT (STS, SAS, PAT, Try Out) menggunakan acuan mata pelajaran untuk distribusi soal dan durasi pengerjaan.

## 16. Assessment & Grade Calculation Weights
Mengelola bobot kalkulasi nilai otomatis:
- `bobot_pengetahuan` (e.g. 40%)
- `bobot_keterampilan` (e.g. 40%)
- `bobot_sikap` (e.g. 20%)
Total komposisi pembobotan divalidasi hingga 100% untuk komputasi Nilai Akhir (NA).

## 17. Report Card (Rapor & Transkrip) Generation Pipeline
Relasi 1:N ke `lms_rapor` dan `student_grades`. Nilai mata pelajaran beserta deskripsi capaian kompetensi secara otomatis ditarik untuk dicetak pada Rapor Digital Siswa.

## 18. Academic Executive Dashboard Data Feed
Data master mata pelajaran menyuplai agregasi statistik ke Executive Dashboard (Total Mapel Aktif, Mapel per Kurikulum, Distribusi Jam Pelajaran, Rasio Guru Pengampu).

## 19. Unique Code & Duplicate Name Constraint Guardrails
Validasi ketat pada `SimpanSubjectRequest` & `UbahSubjectRequest`:
- Unique `kode_mapel` scoped per `unit_pendidikan_id` & `kurikulum_id`.
- Peringatan duplikasi nama mapel jika ada kemiripan pada jenjang yang sama.

## 20. Soft Delete, Audit Trail & Restoration Protocol
- Mendukung `SoftDeletes` (`deleted_at`).
- Audit trail mencatat `created_by`, `updated_by`, dan `deleted_by`.
- Menyediakan endpoint pemulihan data (`POST /api/master/subjects/{id}/restore`).

## 21. Bulk Action Operations (Bulk Status & Bulk Delete)
- **Bulk Status Toggle**: Mengubah status aktif/non-aktif beberapa mata pelajaran sekaligus.
- **Bulk Delete**: Soft-delete masal terhadap list UUID mata pelajaran pilihan pengguna.

## 22. Data Import (Excel/CSV) Validation & Mapping
Endpoint `POST /api/master/subjects/import` memvalidasi file Excel/CSV, melakukan mapping header otomatis, dan mencatat log kegagalan per baris data.

## 23. Data Export (Excel & PDF) Formatting Engine
- `GET /api/master/subjects/export/excel`: Mengunduh file spreadsheet `.xlsx` terformat lengkap.
- `GET /api/master/subjects/export/pdf`: Mengunduh dokumen cetak PDF dengan tata letak resmi sekolah.

## 24. RESTful API Architecture, Routing & Data Transformers
Menerapkan Clean Architecture (Controller -> Service -> Repository -> Model):
- Endpoint standar: `GET /api/master/subjects`, `POST`, `GET /{id}`, `PUT /{id}`, `DELETE /{id}`.
- Data Transformer: `SubjectResource` menyajikan payload JSON konsisten dengan metadata & statistik.

## 25. Frontend UI/UX Design System Compliance
UI Frontend [MasterSubjectPage.jsx](file:///Applications/XAMPP/xamppfiles/htdocs/Sistem-Manajemen-Sekolah-terpadu-main/web-dashboard/src/pages/MasterSubjectPage.jsx) mematuhi **`AI_RULEBOOK.md`**:
- **Primary Color**: Emerald Dark `#0E5C44`
- **Border Radius**: Rounded 18px (`rounded-[18px]`)
- **Dark Mode**: Slate Navy Dark Theme
- **Icons**: Lucide React Icons (`BookOpen`, `Plus`, `Search`, `Download`, `Upload`, `Trash2`, `ToggleLeft`, `Eye`, `Pencil`)
- **Animations**: Framer Motion smooth transitions (`AnimatePresence`, `motion.div`).

## 26. Security & Multi-Role Access Control (RBAC)
Dilindungi middleware `auth:sanctum`. Akses write/delete dibatasi untuk Admin Akademik, Kurikulum, dan Super Admin, sedangkan akses read-only diberikan kepada Guru & Staf.

## 27. Verification Suite (Unit & Feature Test Execution Results)
Seluruh skenario pengujian backend menggunakan PHPUnit / Laravel Testing suite berjalan **100% GREEN (PASS)**:
- `Tests\Unit\SubjectServiceTest`: 3/3 Passed (dapat menambah, memperbarui, soft delete & restore).
- `Tests\Feature\SubjectApiTest`: 6/6 Passed (daftar, tambah, validasi duplikasi, detail, ubah, hapus & pulihkan via API).
- Total: **9 passed, 31 assertions, 0 errors**.

## 28. Migration Guidelines & Production Deployment Plan
1. Jalankan migration database: `php artisan migrate`.
2. Jalankan seeder master mata pelajaran: `php artisan db:seed --class=SubjectSeeder`.
3. Verifikasi ketersediaan menu di sidebar `MASTER DATA` & `AKADEMIK`.
