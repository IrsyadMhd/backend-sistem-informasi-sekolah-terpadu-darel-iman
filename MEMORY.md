# MEMORY.md — Backend (Laravel)

Memori proyek yang dibagikan lintas sesi (di-commit bersama kode). Isinya fakta tahan lama, keputusan, dan jebakan — **bukan** log kerja harian. Ikuti "Aturan Memperbarui" di bawah.

## Gambaran Produk

SIMS (Sistem Informasi Sekolah Terpadu) **Darel Iman** — ERP sekolah Islam terpadu multi-unit (TKIT/TAUD/SDIT/MIT/SMPIT/SMAIT/Ponpes). Modul: master data (unit, kelas, pegawai, siswa, orang tua, alumni), akademik (jadwal, kurikulum, CP/TP, modul semester), presensi (gerbang QR, pembelajaran/KBM, ibadah, izin sakit), LMS (modul ajar, materi, tugas, ujian CBT, bank soal, kisi-kisi, rapor), Tahfizh, Mutaba'ah (ibadah harian), keuangan (tagihan SPP), chat pegawai, dashboard per-role, portal orang tua/siswa/alumni, navigasi dinamis web+mobile.

Tiga repo: backend (ini), `web-dashboard-…`, `web-socket`. Tidak ada git di folder induk `EDU-VENDOR`; tiap repo punya `.git` sendiri.

## Fakta Teknis

- Laravel 12 / PHP 8.2+, PostgreSQL 17 (UUID PK, JSONB, soft delete, tabel partisi bulanan), Sanctum (token Bearer; ada `ExtractBearerTokenFromQuery` untuk kasus unduhan/stream), Spatie Permission dengan model `Role`/`Permission` kustom.
- Route: `routes/api.php` (~1540 baris). Grup publik: `/site-settings`, `/mobile/config`, `/equran/*`, `/doa*`, `/wilayah/*`, `/shalat*`. Sisanya di bawah `auth:sanctum`; sebagian dobel prefix `/v1/...` dan tanpa `/v1`.
- ~97 controller di `Api/V1`, ~100 model, ~100 file test di `tests/Feature`.
- Role kanonik 20+ (Super Admin, Yayasan, Kepala Sekolah, Guru, TU, Musyrif, Wali Kelas, Siswa, dst.). Dashboard khusus per role (`*DashboardController` + `*DashboardService`).
- Integrasi eksternal: eQuran (surah/doa/jadwal sholat, sinkron via `EQuranSyncService`), SIPDEI (`SIPDEI_*`), Google Sheet import (seeder `ImportGoogleSheet*`).
- Realtime: `RealtimeBroadcastService` simpan ke `realtime_events` lalu POST ke gateway `web-socket`. Channel: `user.{id}` (chat), `student.{id}` (event `student.activity.logged`).
- Tahun ajaran simulasi aktif: **2026/2027** (Juli 2026 – Juli 2027).

## Jebakan Umum (baca sebelum coding)

1. **README usang**: masih menyebut struktur `backend/ web-dashboard/ mobile-app/ infra/` dan "Tahap 1" dengan endpoint placeholder. Fakta ada di kode & memori ini; hanya bagian "Panduan Seeder" yang masih akurat.
2. **`env()` di `RealtimeBroadcastService`** (`env('WEBSOCKET_GATEWAY_URL')`) akan `null` setelah `config:cache` → realtime mati diam-diam. Perbaikan terencana (A1–A5 di `../web-socket/CHECKLIST-PERBAIKAN.md`): pindah ke `config/services.php`, kirim `Authorization: Bearer`, log kegagalan, `connectTimeout(1)->timeout(1)` (bukan `0.5`). Status per sesi ini: **belum dikerjakan**.
3. **Test SQLite vs prod PostgreSQL**: lulus di SQLite ≠ aman di PG (partisi, JSONB, case-sensitivity). Uji `phpunit.pgsql.xml` untuk perubahan skema/query khusus PG.
4. **Skrip root** (`audit_*.php`, `fix_materi_guru.php`, `purge_dummy_data.php`, `reseed_materi.php`, `simulate_flow.php`, `seed_complete_classes_simulation.php`, `complete_all_schedules_and_rombels.php`, `test_pg.php`) = alat sekali-pakai, beberapa menulis/menghapus data, ada yang hard-code email/ID tertentu. Jangan jadikan pola; fitur permanen taruh di Service/Command/Seeder.
5. Artefak lokal di root: `routes_api.json` (dump route, 260KB), `school_management` (berkas DB), `storage/parse_log.py`. Bukan bagian aplikasi — jangan diedit/dijadikan acuan.
6. Nama role bervariasi (spasi/underscore) → selalu lewat `RoleName::matchesAny/userHasAny`.
7. Dua gaya route (`/v1/...` dan tanpa) hidup berdampingan demi kompatibilitas mobile/web; **jangan menghapus salah satu** tanpa cek konsumennya.
8. Seeder dummy bergantung urutan (Kelas → Jadwal → Presensi → LMS). Jalankan via `DatabaseSeeder`/command simulasi, bukan acak.

## Keputusan yang Sudah Diambil

- Live vs Simulasi dipisah lewat `SEED_DUMMY_DATA` (lihat AGENTS.md "Aturan Data").
- Arsitektur Service + Repository untuk modul LMS/Mutabaah/master; modul lama bisa langsung di controller — migrasi bertahap, jangan refactor massal tanpa diminta.
- Penghapusan data sensitif memakai alur persetujuan (`Approval/DeleteRequestController`, model `DeleteRequest`), bukan hard delete langsung.
- Gateway realtime bersifat best-effort; kegagalan tidak boleh menggagalkan request API (fallback polling/SSE).

## Status & Keputusan Terbaru

_(Tambahkan baris baru di atas, format `YYYY-MM-DD — ringkas`. Pangkas yang sudah usang.)_

- 2026-11-06 — AGENTS.md/MEMORY.md/TOOLS.md dibuat untuk ketiga repo. Riwayat commit terakhir: hak akses, pengaturan sistem & user, import siswa & maintenance, import/export data.

## Aturan Memperbarui Berkas Ini

- Tambah hanya yang **tidak bisa diturunkan dari kode/git**: keputusan + alasannya, jebakan yang pernah menggigit, status pekerjaan lintas-sesi.
- Jangan simpan secret, password, data pribadi, atau log sesi. Jangan salin isi kode.
- Verifikasi fakta (file/fungsi masih ada) sebelum mengandalkannya; hapus entri yang sudah salah.
- Perubahan lintas-repo: catat singkat di sini dan di MEMORY.md repo terkait.
