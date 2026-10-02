# Presensi Proyek

Aplikasi presensi karyawan proyek — backend Laravel + web admin (jQuery), aplikasi mobile Flutter.
Rencana lengkap: [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md). Dokumen acuan: `ABSENSI BULAN 1.pdf`.

```
backend/   Laravel 13 (PHP 8.3+, MySQL 8)
mobile/    Flutter (Android & iOS) — belum dikerjakan
```

## Backend — menjalankan di lokal (Laragon)

```bash
cd backend
composer install
cp .env.example .env        # sesuaikan DB_* bila perlu
php artisan key:generate
# buat database: presensi_proyek (dan presensi_proyek_test untuk test)
php artisan migrate --seed  # data dari PDF: proyek Kedurus, 9 karyawan, 270 presensi demo
npm install && npm run build  # aset web admin (hasil public/build ikut di-deploy)
php artisan serve            # buka http://localhost:8000
```

Akun pengembangan hasil seeder:

| Akun | Username | Password |
|---|---|---|
| Super admin (web admin) | `admin` | `password` |
| Admin proyek KDR (web admin) | `admin.kdr` | `password` |
| Team Leader (web: pantau & approval; juga mobile) | `edi.santoso` | `Presensi#2026` |
| Karyawan (mobile) | `edi.santoso` (Team Leader), `agus.irawan`, `budiarto`, … | `Presensi#2026` |

> Ganti semua password sebelum dipakai di server sungguhan.

Perintah penting:

```bash
php artisan test                         # automated test (DB presensi_proyek_test)
php artisan attendance:close-day [tgl]   # tandai alpha/libur (otomatis 00:30)
php artisan attendance:flag-missing-checkout  # tandai lupa check-out setelah batas jam kerja (otomatis tiap jam)
php artisan holidays:import 2026         # impor libur nasional dari database/data/holidays/2026.json
php artisan app:install                  # inisialisasi server klien baru (tanpa data demo)
php artisan schedule:work                # lokal: jalankan scheduler (alpha, lupa check-out, antrean laporan besar)
php artisan reports:prune                # hapus berkas laporan > 14 hari (otomatis 02:00)
```

Server cukup memakai satu cron: `* * * * * php /path/backend/artisan schedule:run`.
Di VPS dengan Supervisor, set `QUEUE_VIA_SCHEDULER=false` dan jalankan `queue:work` terpisah.

## Dokumentasi

| Untuk | Berkas |
|---|---|
| Developer mobile | [docs/api/API_MOBILE.md](docs/api/API_MOBILE.md) — semua endpoint, kode `reason`, alur offline & shift malam |
| QA | [docs/qa/](docs/qa/README.md) — 125 skenario uji (Excel) + koleksi Postman otomatis (41 request) |
| Perencanaan | [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) |

## API mobile (`/api/v1`)

Format respons: `{ success, message, reason, data, errors }` — `reason` berisi kode penolakan
(mis. `outside_radius`, `mock_location`, `offline_expired`, `device_mismatch`).
Daftar endpoint: `php artisan route:list --path=api`.
