# TOOLS.md — Backend (Laravel)

Perintah dan alat yang dipakai di repo ini. Shell utama di mesin dev: Windows (PowerShell / Git Bash). Jalankan semua dari root repo backend.

## Setup Lokal

```bash
composer install
cp .env.example .env          # lalu isi DB_* (PostgreSQL), REDIS_*, WEBSOCKET_GATEWAY_URL
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve             # http://localhost:8000 (dashboard mengharapkan port ini)
```

`composer dev` menjalankan serve + queue:listen + pail + vite sekaligus (butuh `npx concurrently`).
Queue memakai Redis (`QUEUE_CONNECTION=redis`); untuk dev tanpa Redis, set `QUEUE_CONNECTION=sync`.

## Test & Kualitas

| Tujuan | Perintah |
|---|---|
| Semua test | `php artisan test` |
| Satu file / filter | `php artisan test --filter=AdminNavigationTest` |
| Test di PostgreSQL | `./vendor/bin/phpunit -c phpunit.pgsql.xml` |
| Format (hanya berkas berubah) | `./vendor/bin/pint --dirty` |
| Log live | `php artisan pail` |
| Daftar route | `php artisan route:list --path=api/v1` |

`phpunit.xml` default = SQLite `:memory:`, `QUEUE=sync`, `CACHE=array`. Lulus di SQLite **belum menjamin** lulus di PostgreSQL → untuk perubahan query/migrasi khusus PG, jalankan juga `phpunit.pgsql.xml`.

## Artisan Khusus Proyek (`app/Console/Commands`)

| Command | Fungsi |
|---|---|
| `simulate:academic-year-2026-2027 --module=all\|schedules\|attendance\|lms\|grades\|rapor\|tahfizh\|mutabaah\|finance` | Generate data simulasi 1 tahun ajaran. `--rollback` hapus data bertanda `is_simulation`. |
| `cbt:auto-timeout` | Tutup sesi CBT yang lewat batas waktu (terjadwal tiap menit). |
| `kbm:auto-close-sessions` | Tutup sesi KBM tak tertutup (terjadwal 17:00 & 23:59). |
| `ProcessUnscannedAttendanceCutoff` | Proses cutoff presensi yang tidak ter-scan. |
| `ResolveScheduleConflictsCommand` | Selesaikan bentrok jadwal. |
| `ExportMasterDataFilesCommand` | Ekspor 7 tabel master ke Excel. |
| `ManageTestRoleAccountsCommand`, `FixSeedPasswords` | Kelola akun uji / perbaiki password seed (DEV saja). |
| `VerifyPortalSecurityCommand` | Verifikasi keamanan portal. |

Cek nama signature pasti dengan `php artisan list` sebelum memakai.

## Seeding

- Live bersih: `.env` → `APP_ENV=production`, `APP_DEBUG=false`, `SEED_DUMMY_DATA=false`, lalu `php artisan migrate:fresh --seed` (**hanya DB baru/kosong**).
- Seeder tunggal: `php artisan db:seed --class=RolePermissionSeeder`. Setelah menambah permission/menu: jalankan ulang `RolePermissionSeeder` / `AppNavigationSeeder`.

## Tinker & Skrip Ad-hoc

- Eksplorasi data: `php artisan tinker --execute="..."` (read-only dulu).
- Skrip root `audit_*.php`, `fix_*.php`, `simulate_flow.php`, `seed_complete_*.php`, `purge_dummy_data.php`, `reseed_materi.php`, `complete_all_schedules_and_rombels.php` adalah **alat sekali-pakai, sebagian destruktif**. Baca isinya dulu; jalankan hanya di DB lokal. Pola bootstrap-nya: `require vendor/autoload.php` + `bootstrap/app.php` + `Kernel->bootstrap()`.
- `query_db.js` (Node) dan `scripts/postgresql_bootstrap.sh` (path macOS/XAMPP lama — sesuaikan dulu) adalah utilitas lokal.

## Docker

`Dockerfile`: php:8.3-fpm + pdo_pgsql + zip + redis; `upload_max_filesize=50M`, `memory_limit=512M`. Tidak ada docker-compose di repo ini (nginx/DB/Redis disediakan di luar, mis. Easypanel).

## Variabel Lingkungan Penting

`DB_*` (pgsql), `REDIS_*`, `QUEUE_CONNECTION`, `SANCTUM_STATEFUL_DOMAINS`, `SEED_DUMMY_DATA`, `SIPDEI_BASE_URL`/`SIPDEI_API_KEY`/`SIPDEI_CACHE_TTL` (integrasi SIPDEI), `WEBSOCKET_GATEWAY_URL` (+ token gateway, lihat `web-socket/CHECKLIST-PERBAIKAN.md`). Baca lewat `config()`, bukan `env()`.

## Alat Agent yang Disarankan

- Cari route/controller: Grep di `routes/api.php` (1500+ baris, dikelompokkan per middleware).
- Pahami model: baca `app/Models/<X>.php` + migrasi terkait di `database/migrations`.
- Selalu pakai path absolut & jalur Windows/Git Bash yang valid; hindari skrip `.sh` yang berpath macOS.
