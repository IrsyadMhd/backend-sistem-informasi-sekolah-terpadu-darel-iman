# 🚀 PANDUAN LENGKAP DEPLOY KE ENVIRONMENT STAGING DARELIMAN EDU

> **JAMINAN KEAMANAN LOKAL (ZERO LOCAL IMPACT):**  
> Mengikuti panduan ini menjamin **100% lingkungan lokal Anda TIDAK AKAN BERUBAH sedikit pun**. Database lokal, kode sumber di branch kerja Anda, file `.env` lokal, dan seluruh konfigurasi lokal tetap membeku (*frozen*) dan aman.

---

## 📦 ISI STAGING DEPLOYMENT KIT (`staging-deploy-kit/`)

| File / Artefak | Fungsi & Deskripsi |
| :--- | :--- |
| `database_golden_audit.dump` | **Snapshot Golden Database Lokal (6.4 MB)**: Cadangan kompresi biner PostgreSQL resmi hasil audit bersih (2.555 santri, 0 dummy, 0 data uji). |
| `.env.staging.example` | Template konfigurasi `.env` khusus server staging dengan `SEED_DUMMY_DATA=false` dan konfigurasi SIPDEI live. |
| `01_deploy_backend_staging.sh` | Skrip otomatis untuk setup backend, migrasi bersih, seeding resmi, dan optimasi cache di server staging. |
| `02_verify_staging_seeder.php` | Skrip verifikasi otomatis pasca-seeding untuk memastikan 2.549 santri riil, 0 dummy data, 16 unit, 7 role akun, dan live SIPDEI API. |
| `03_build_frontend_staging.sh` | Skrip kompilasi `web-dashboard` dengan target endpoint staging API. |

---

## 🛠️ LANGKAH 1: PERSIAPAN DI REPOSITORI (LOCAL)

Pastikan seluruh perbaikan audit sudah tersimpan rapi di Git:

1. Buat branch khusus staging agar tidak mengganggu branch lokal Anda:
   ```bash
   git checkout -b staging
   git add staging-deploy-kit/
   git commit -m "feat(staging): add staging deployment kit and golden dump"
   git push origin staging
   ```
2. Anda tetap bisa kembali ke branch lokal kapan saja:
   ```bash
   git checkout refactor
   ```

---

## 🖥️ LANGKAH 2: SETUP BACKEND DI SERVER STAGING

### Opsi A: Eksekusi Seeder Resmi (Sesuai Permintaan)
*Metode ini membangun database staging dari nol menggunakan pipeline migrasi & seeder Laravel yang sudah diaudit bersih.*

1. **Clone branch staging di server staging:**
   ```bash
   cd /var/www
   git clone -b staging <URL_GIT_REPOSITORY> dareliman-staging
   cd dareliman-staging/backend
   ```

2. **Pasang konfigurasi `.env`:**
   ```bash
   cp ../staging-deploy-kit/.env.staging.example .env
   nano .env
   ```
   *Isi kredensial database PostgreSQL staging Anda dan pastikan `SEED_DUMMY_DATA=false`.*

3. **Jalankan Skrip Deploy & Seeder:**
   ```bash
   chmod +x ../staging-deploy-kit/01_deploy_backend_staging.sh
   ../staging-deploy-kit/01_deploy_backend_staging.sh
   ```
   *Skrip akan menjalankan `php artisan migrate:fresh --seed --force` secara aman.*

4. **Konfigurasi Nginx Backend API (`demoapi.dareliman.sch.id`):**
   ```nginx
   server {
       listen 80;
       server_name demoapi.dareliman.sch.id;
       return 301 https://$host$request_uri;
   }

   server {
       listen 443 ssl http2;
       server_name demoapi.dareliman.sch.id;

       ssl_certificate /etc/letsencrypt/live/demoapi.dareliman.sch.id/fullchain.pem;
       ssl_certificate_key /etc/letsencrypt/live/demoapi.dareliman.sch.id/privkey.pem;

       root /var/www/dareliman-staging/backend/public;
       index index.php;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.2-fpm.sock; # Sesuaikan socket PHP FPM Anda
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }
   }
   ```

---

### Opsi B: Restore dari Golden Dump (`database_golden_audit.dump`)
*Jika Anda menginginkan database staging 100% byte-for-byte identik dengan database lokal saat detik audit selesai:*

```bash
# Buat database kosong di PostgreSQL staging:
createdb -U postgres -h 127.0.0.1 school_management_staging

# Restore langsung dari file dump golden:
pg_restore -U postgres -h 127.0.0.1 -d school_management_staging -v ../staging-deploy-kit/database_golden_audit.dump
```

---

## 🔍 LANGKAH 3: VERIFIKASI SEEDER DI STAGING

Jalankan skrip verifikasi otomatis di dalam folder `backend/` server staging:

```bash
php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); require '../staging-deploy-kit/02_verify_staging_seeder.php';"
```

**Target Verifikasi Sukses (100% Green):**
- Total Santri: `2555`
- Santri Riil SIPDEI: `2549`
- Santri Dummy / Palsu: `0`
- Unit Pendidikan: `16 Unit`
- Akun 7 Role Resmi: `Lengkap [OK]`
- Integrasi SIPDEI Partner API: `HTTP 200 (Terhubung Live)`

---

## 🌐 LANGKAH 4: BUILD & DEPLOY WEB DASHBOARD

1. Masuk ke folder `web-dashboard`:
   ```bash
   cd ../web-dashboard
   chmod +x ../staging-deploy-kit/03_build_frontend_staging.sh
   ../staging-deploy-kit/03_build_frontend_staging.sh "https://demoapi.dareliman.sch.id/api/v1"
   ```
2. Arahkan Nginx di server staging ke direktori `dist/` untuk domain **`demoedu.dareliman.sch.id`**:
   ```nginx
   server {
       listen 80;
       server_name demoedu.dareliman.sch.id;
       return 301 https://$host$request_uri;
   }

   server {
       listen 443 ssl http2;
       server_name demoedu.dareliman.sch.id;

       # SSL Certificates
       ssl_certificate /etc/letsencrypt/live/demoedu.dareliman.sch.id/fullchain.pem;
       ssl_certificate_key /etc/letsencrypt/live/demoedu.dareliman.sch.id/privkey.pem;

       root /var/www/dareliman-staging/web-dashboard/dist;
       index index.html;

       location / {
           try_files $uri $uri/ /index.html;
       }

       location /dashboard {
           try_files $uri $uri/ /index.html;
       }
   }
   ```
   *Dashboard dapat diakses langsung melalui: **`https://demoedu.dareliman.sch.id/dashboard`***

---

## 📱 LANGKAH 5: BUILD & DISTRIBUSI MOBILE APP (TESTING)

1. Masuk ke direktori `mobile-app`:
   ```bash
   cd ../mobile-app
   ```
2. Di file `.env` atau `src/services/api/client.ts`, pasang URL staging API:
   ```env
   EXPO_PUBLIC_API_URL=https://demoapi.dareliman.sch.id/api/v1
   ```
3. Export bundle atau buat APK staging:
   ```bash
   # Opsi 1: Export Metro Static Bundle
   npx expo export

   # Opsi 2: Build Standalone Testing APK via EAS
   eas build --profile staging --platform android
   ```

