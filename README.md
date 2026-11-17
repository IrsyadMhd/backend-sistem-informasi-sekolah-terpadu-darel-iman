Sistem Manajemen Sekolah Islam Terpadu
Monorepo ini berisi fondasi School ERP berbasis:

Backend API: Laravel 12 + PostgreSQL 17 + Sanctum + Spatie Permission
Web Dashboard: React 19 + Vite + Tailwind CSS + Recharts + Zustand
Mobile App: React Native (Expo) + React Navigation + React Native Paper
Infra: Docker Compose (Nginx + PHP-FPM + PostgreSQL + Redis)
Struktur
backend
web-dashboard
mobile-app
infra
Tahap Saat Ini
Tahap 1 sudah disiapkan:

Setup autentikasi login/logout/profile menggunakan Sanctum
Setup role/permission 7 role utama
Setup migration inti dengan UUID, JSONB, soft delete, timezone timestamp
Setup partisi PostgreSQL untuk tabel operasional bulanan
Setup endpoint dasar dashboard, students, teachers, classes
Setup UI dashboard modern (shell + statistik + chart)
Setup bottom navigation mobile untuk portal orang tua/siswa
Menjalankan Local (Manual)
1. Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve

### Perintah Simulasi Data Akademik 1 Tahun (T.A. 2026/2027)
Untuk menghasilkan data simulasi 1 tahun pelajaran penuh (Juli 2026 – Juli 2027) yang mencakup sesi mengajar, presensi gerbang, tugas LMS, nilai, e-rapor, tahfizh, mutabaah, dan tagihan SPP:

```bash
# Menjalankan seluruh simulasi data 1 tahun ajaran:
php artisan simulate:academic-year-2026-2027 --module=all

# Menjalankan per modul spesifik:
php artisan simulate:academic-year-2026-2027 --module=schedules   # Sesi Mengajar Guru
php artisan simulate:academic-year-2026-2027 --module=attendance  # Presensi Harian Siswa
php artisan simulate:academic-year-2026-2027 --module=lms         # Materi & Penugasan LMS
php artisan simulate:academic-year-2026-2027 --module=grades      # Rekap Nilai Siswa (student_grades)
php artisan simulate:academic-year-2026-2027 --module=rapor       # Buku e-Rapor Digital
php artisan simulate:academic-year-2026-2027 --module=tahfizh     # Setoran Hafalan Quran
php artisan simulate:academic-year-2026-2027 --module=mutabaah    # Rekap Ibadah Harian
php artisan simulate:academic-year-2026-2027 --module=finance     # Tagihan & Pembayaran SPP 12 Bulan

# Membersihkan / Rollback data simulasi secara aman (hanya menghapus data bertanda is_simulation):
php artisan simulate:academic-year-2026-2027 --rollback
```

---

## Panduan Seeder: Mode LIVE (Data Riil) vs Mode SIMULASI (Pengembangan)

Aplikasi ini membedakan secara tegas antara **Data Master Fondasi Sistem (System Core)** yang **wajib di-seed di server Live** dan **Data Simulasi/Dummy** yang **dilarang di-seed di server Live**.

### 1. Kategori Seeder Wajib Saat LIVE (Core System Bootstrap)
*Seeder ini adalah bagian dari infrastruktur dan kerangka aplikasi. Tanpa seeder ini, aplikasi rusak, tidak bisa login, atau navigasi sidebar kosong.*

| Seeder | Deskripsi & Urgensi |
|---|---|
| `CoreAdminUserSeeder` | Akun Super Admin & Admin awal untuk setup pertama kali. |
| `RolePermissionSeeder` | Mendaftarkan 20+ Canonical Roles (Yayasan, Kepala Sekolah, Guru, TU, Musyrif, dll) dan ribuan izin Spatie. |
| `AttendancePermissionSeeder` | Izin alur verifikasi presensi dan approval izin sakit santri. |
| `AppNavigationSeeder` | Mendaftarkan struktur modul dan seluruh pohon menu navigasi Web Dashboard & Mobile App. |
| `SiteSettingsSeeder` | Konfigurasi dasar nama lembaga, tema, logo default, dan pengaturan aplikasi. |
| `StudentCardSettingsSeeder` | Konfigurasi tata letak dan template cetak kartu santri/pelajar. |
| `MasterJenisUnitPendidikanSeeder` | Master kategori jenjang pendidikan (TKIT, TAUD, SDIT, MIT, SMPIT, SMAIT, Ponpes). |
| `TransportationModeSeeder` | Master moda transportasi santri (Jalan Kaki, Sepeda, Mobil, Antar Jemput). |
| `QuranSurahSeeder` | Master 114 Surah Al-Qur'an (nama Arab, latin, jumlah ayat, juz) untuk modul Tahfizh. |
| `DoaSeeder` | Kumpulan doa harian & adab belajar resmi Islam untuk fitur Islami di aplikasi siswa/santri. |

### 2. Kategori Seeder Template / Acuan Standar (Sangat Disarankan)
*Template master acuan agar admin tidak perlu mengetik dari nol via web:*
- `MasterJabatanSeeder` (Standar nama jabatan: Kepala Sekolah, Waka, Guru, Wali Kelas, Musyrif).
- `MasterKurikulumSeeder` (Template acuan kurikulum SIT/Merdeka).
- `PrayerScheduleSeeder` (Template acuan jadwal sholat default).
- `IndikatorKinerjaUtamaSeeder` (Template indikator IKU yayasan).

### 3. Kategori Seeder Dummy / Simulasi (DILARANG SAAT LIVE)
*Berisi ribuan murid, guru fiktif, nilai rapor fiktif, dan presensi coba-coba. Otomatis di-skip saat mode Live:*
- `UserTestSeeder`, `TestingRoleAccountsSeeder`, `AkunTestSeeder` (Akun uji coba multi-role: Super Admin, Yayasan, Kepsek/TU/Operator per unit, Musrif, Guru Boarding/Fullday, Orang Tua 4 anak)
- `DataDummyUnitPendidikanSeeder`, `DataDummyPegawaiSeeder`, `TeacherSeeder`
- `DataDummySiswaSeeder`, `ParentSeeder`, `SimulasiSiswaSemuaKelasSeeder` (1.050 siswa simulasi)
- `SimulasiTerpadu2026Seeder`, `SimulasiTahunanRamadhanSeeder`, `AlumniDanMutasiSeeder`
- `AttendanceSeeder`, `PresensiAwalJuli2026Seeder`, `PresensiPembelajaranSeeder`, `WorshipAttendanceSeeder`
- `TahfizhSeeder`, `MutabaahEnterpriseSeeder`, `MutabaahProgramAndRuleSeeder`
- `StudentGradesSeeder`, `LmsRaporSeeder`, `JadwalPelajaranSeeder`, `KelasSeeder`, `SubjectSeeder`
- Seluruh seeder bank soal, modul, materi, tugas, dan ujian LMS (`Lms*Seeder`, `Modul*Seeder`, `Materi*Seeder`)

---

### Cara Menjalankan di Server LIVE (Data Riil Bersih)

1. Pastikan di `.env` server Live:
```env
APP_ENV=production
APP_DEBUG=false
SEED_DUMMY_DATA=false
```

2. Jalankan perintah migrasi & seeding:
```bash
php artisan migrate:fresh --seed
```
*Sistem di `DatabaseSeeder.php` otomatis hanya mengeksekusi Seeder Wajib (Kategori 1) dan mengabaikan seluruh seeder dummy (Kategori 3). Database Anda akan 100% bersih, siap diisi unit, guru, dan santri riil.*

---
2. Web Dashboard
cd web-dashboard
npm install
npm run dev
3. Mobile App
cd mobile-app
npm install
npm run start
Menjalankan Dengan Docker
cd infra
docker compose up -d --build
Catatan: setelah container aktif, jalankan migrasi dari container backend atau dari host.

Endpoint Tahap 1
POST /api/auth/login
POST /api/auth/logout
GET /api/auth/profile
GET /api/dashboard
GET /api/students
GET /api/teachers
GET /api/classes
GET /api/attendance (placeholder)
GET /api/tahfizh (placeholder)
GET /api/mutabaah (placeholder)
GET /api/materials (placeholder)
GET /api/assignments (placeholder)
GET /api/exams (placeholder)
GET /api/alumni (placeholder)
GET /api/notifications (placeholder)

---

## Deployment Server: Staging & Production Setup

### Informasi Domain & Endpoint Resmi
- **Staging Backend API**: `https://demoapi.dareliman.sch.id` (Base API: `https://demoapi.dareliman.sch.id/api/v1`)
- **Staging Web Dashboard**: `https://demoedu.dareliman.sch.id/dashboard`
- **Production Backend API**: `https://api.dareliman.sch.id` (Base API: `https://api.dareliman.sch.id/api/v1`)
- **Production Web Dashboard**: `https://edu.dareliman.sch.id/dashboard`

### Konfigurasi Kunci Staging (`.env`)
```env
APP_NAME="Dareliman Edu (Staging)"
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://demoapi.dareliman.sch.id
SANCTUM_STATEFUL_DOMAINS=demoedu.dareliman.sch.id,localhost:5173
SEED_DUMMY_DATA=false
SIPDEI_BASE_URL=https://sipdei.dareliman.tech
SIPDEI_API_KEY=8c64e0685f8619a599e93a5a2c8b8c689ba42407770172cb9d89f25940c48753
```

Panduan deploy lengkap dan skrip otomatis tersedia di folder [`staging-deploy-kit/`](../staging-deploy-kit/PANDUAN_DEPLOY_STAGING.md).



### Prosedur Deploy Staging & Pindah Branch di Server

Jika server saat ini sedang berada di branch `main`, lakukan langkah berikut untuk beralih ke branch `staging` secara aman:

```bash
# 1. Masuk ke folder backend server
cd /var/www/dareliman-staging-backend

# 2. Amankan perubahan lokal sementara di server
git stash

# 3. Ambil branch staging terbaru dari GitHub
git fetch origin

# 4. Pindah ke branch staging
git checkout -B staging origin/staging
git pull origin staging

# 5. Pasang konfigurasi .env Staging
cp staging-deploy-kit/.env.staging.example .env
# Sesuaikan kredensial database staging Anda di .env

# 6. Eksekusi migrasi & seeder staging (2.555 santri = 2.549 riil + 6 test)
chmod +x staging-deploy-kit/*.sh
./staging-deploy-kit/01_deploy_backend_staging.sh

# 7. Verifikasi otomatis data seeder (100% Green)
php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); require 'staging-deploy-kit/02_verify_staging_seeder.php';"
```

### Aturan Seeder Berdasarkan Environment:
- **Staging (`APP_ENV=staging`)**: `SEED_DUMMY_DATA=false` dan `SEED_TEST_ACCOUNTS=true` ➔ Total 2.555 santri (2.549 riil SIPDEI + 6 akun test santri terpadu untuk pengujian multi-role).
- **Production (`APP_ENV=production`)**: `SEED_DUMMY_DATA=false` dan `SEED_TEST_ACCOUNTS=false` ➔ Total 2.549 santri (HANYA data master riil yayasan & santri SIPDEI, 0 akun test).
