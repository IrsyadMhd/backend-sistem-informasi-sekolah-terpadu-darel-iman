# AGENTS.md — Backend SIMS Darel Iman (Laravel API)

Panduan kerja wajib untuk agent AI di repo ini. Baca bersama [MEMORY.md](MEMORY.md) (pengetahuan & keputusan proyek) dan [TOOLS.md](TOOLS.md) (perintah). Bahasa komunikasi dengan user, komentar domain, dan pesan UI: **Bahasa Indonesia**; identifier kode mengikuti konvensi yang sudah ada (campuran Inggris/Indonesia, jangan di-rename).

## Konteks Sistem (3 repo, satu produk)

| Folder (di `EDU-VENDOR/`) | Peran |
|---|---|
| `backend-sistem-informasi-sekolah-terpadu-darel-iman` | **Repo ini.** REST API Laravel 12, PostgreSQL, Sanctum, Spatie Permission. |
| `web-dashboard-sistem-informasi-sekolah-terpadu-darel-iman` | SPA React 19 + Vite yang mengonsumsi API ini. |
| `web-socket` | Gateway realtime Node (`ws`). Laravel mem-publish via HTTP `POST /publish`; browser subscribe via WebSocket. |

Perubahan kontrak API (route, bentuk JSON, permission) **berdampak ke dashboard** — cek/sebut dampaknya. Perubahan event realtime berdampak ke `web-socket` dan dashboard (`src/services/websocketService.js`). Mobile app (React Native) ada di luar workspace ini tetapi memakai API yang sama (`/api/mobile/config`, endpoint portal) — jangan merusak endpoint publik/portal tanpa alasan.

## Stack

PHP ^8.2 (Docker: 8.3-fpm), Laravel ^12, Sanctum ^4, spatie/laravel-permission ^8, maatwebsite/excel, barryvdh/dompdf, predis (Redis queue), PHPUnit 11, Pint. DB produksi/dev: **PostgreSQL** (UUID, JSONB, soft delete). Test: **SQLite in-memory**.

## Arsitektur & Konvensi

- Alur: `routes/api.php` → Controller (`app/Http/Controllers/Api/V1/*`, LMS di `Api/` langsung, auth di `Api/Auth`, approval di `Api/Approval`) → FormRequest (`app/Http/Requests`) → **Service** (`app/Services`) → **Repository** (`app/Repositories/Contracts` + `Eloquent`) → Model.
- Controller tipis; logika bisnis di Service; query kompleks di Repository. Repository baru = interface di `Contracts` + implementasi di `Eloquent` + binding di `AppServiceProvider`.
- Model memakai trait `HasUuidPrimaryKey` (PK UUID). Jangan asumsikan ID integer.
- Otorisasi berlapis: middleware `auth:sanctum`, `RoleMiddleware`, `EnsureFoundationReadOnly`, Policies (`app/Policies`), dan scope data via `AccessScopeService` / `MutabaahDataScope`. Pencocokan nama role harus lewat `App\Support\RoleName` (normalisasi spasi/underscore/dash), bukan string-compare mentah.
- Permission/role di-seed oleh `RolePermissionSeeder` (20+ role kanonik). Menu/navigasi web & mobile di-seed oleh `AppNavigationSeeder` — endpoint baru yang butuh menu harus ikut diperbarui di sana.
- Realtime: gunakan `RealtimeBroadcastService` (tulis ke tabel `realtime_events` + best-effort POST ke gateway). Jangan memanggil gateway langsung dari controller.
- Scheduler ada di `routes/console.php` (`cbt:auto-timeout`, `kbm:auto-close-sessions`).
- Penomoran/kode otomatis lewat `CodeGeneratorService` / `CodeSequence`.
- Format: `.editorconfig` (LF, indent 4 spasi, YAML 2). Jalankan Pint sebelum selesai.

## Aturan Data (KRITIS)

1. **Data simulasi vs riil dipisah tegas.** Seeder dummy (`Simulasi*`, `DataDummy*`, `Lms*Seeder`, dst.) dilarang di Live; dikendalikan `SEED_DUMMY_DATA`. Seeder wajib-Live: lihat README bagian "Panduan Seeder".
2. Data simulasi tahun ajaran ditandai `is_simulation` dan dihapus lewat `php artisan simulate:academic-year-2026-2027 --rollback`. Jangan menghapus data dengan cara lain.
3. **Jangan menjalankan** `migrate:fresh`, `db:wipe`, `purge_dummy_data.php`, `reseed_materi.php`, atau skrip root sejenis ke DB yang bukan lokal-disposable tanpa konfirmasi eksplisit user. Cek `DB_HOST`/`APP_ENV` di `.env` dulu.
4. Migrasi: tambah migrasi baru, **jangan edit migrasi lama** yang sudah dipakai. Harus kompatibel PostgreSQL *dan* SQLite (test) — hati-hati fitur khusus PG (partisi, JSONB operator, `ILIKE`); beri guard `DB::getDriverName()` bila perlu.
5. Jangan commit `.env`, dump `.sql`, kredensial, atau data pribadi siswa/pegawai. Akun uji hanya di seeder/README, bukan di kode baru.

## Alur Kerja Setiap Sesi

1. Baca `MEMORY.md` → bagian "Status & Keputusan Terbaru" dan "Jebakan Umum". `git status` + `git log -5` untuk melihat konteks.
2. Telusuri kode yang ada sebelum menulis baru (Grep route di `routes/api.php`, cari Service/Repository sejenis). Ikuti pola modul tetangga (mis. modul `Lms*` atau `Mutabaah*`).
3. Ubah seminimal mungkin; jangan refactor di luar permintaan. Jangan menambah file ad-hoc di root repo (lihat "Skrip Root" di MEMORY.md).
4. Tambah/ubah test di `tests/Feature` untuk perilaku yang diubah (pola: `RefreshDatabase`, seed `RolePermissionSeeder`, `actingAs($user, 'sanctum')`).
5. Verifikasi: `php artisan test --filter=...` untuk area terkait, lalu `./vendor/bin/pint --dirty`. Bila tidak bisa menjalankan test (mis. DB/PHP tidak ada), katakan terus terang — jangan klaim lulus.
6. Bila endpoint/kontrak berubah: sebutkan file frontend yang perlu disesuaikan (`web-dashboard.../src/services/*Service.js`).
7. Akhir sesi: perbarui `MEMORY.md` bila ada keputusan, jebakan baru, atau status yang berubah (lihat aturan di file itu).

## Git

- Commit hanya bila diminta user. Pesan commit: singkat, imperatif (riwayat campuran Indonesia/Inggris, mis. `feat(export): ...`). Jangan `--no-verify`, jangan force-push.
- Repo ini punya remote/fork; periksa branch aktif sebelum commit.

## Larangan

- Jangan menyentuh `vendor/`, `storage/` (selain log untuk diagnosis), `routes_api.json`, `school_management` (berkas DB SQLite/lokal) kecuali diminta.
- Jangan memakai `env()` di luar `config/*` (rusak setelah `config:cache`) — pakai `config()`.
- Jangan membuat endpoint tanpa middleware auth/role kecuali memang publik (`/site-settings`, `/equran/*`, `/doa*`, `/wilayah/*`, `/mobile/config`).
