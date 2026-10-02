# Paket Uji QA — Presensi Proyek

Cakupan: web admin, API mobile, laporan (Fase 1–3). Aplikasi Flutter belum dibuat, jadi sisi mobile diuji lewat Postman.

| Berkas | Isi |
|---|---|
| [`QA_Skenario_Uji_Presensi_Proyek.xlsx`](QA_Skenario_Uji_Presensi_Proyek.xlsx) | **125 kasus uji manual**: Web Admin (69), API Mobile (26), Laporan (17), Skenario Bisnis (13). Ada sheet Petunjuk (akun, koordinat, perintah server), Ringkasan (progres & tingkat lulus terhitung otomatis), dan Bug Log |
| [`../api/Presensi_Proyek_API.postman_collection.json`](../api/Presensi_Proyek_API.postman_collection.json) | **Uji API otomatis**: 41 request, 163 assertion (status HTTP, kode `reason`, isi data) |
| [`../api/Presensi_Proyek_Local.postman_environment.json`](../api/Presensi_Proyek_Local.postman_environment.json) | Environment: `baseUrl`, akun uji, koordinat dalam/luar radius |
| [`../api/sample-selfie.jpg`](../api/sample-selfie.jpg) | Foto contoh untuk field `photo` |
| [`../api/API_MOBILE.md`](../api/API_MOBILE.md) | Dokumentasi API (acuan hasil yang diharapkan) |

## Persiapan

```bash
cd backend
php artisan migrate:fresh --seed   # data demo bersih
php artisan serve                  # http://127.0.0.1:8000
```

Akun: `admin` / `password` (super admin web) · `edi.santoso` / `Presensi#2026` (Team Leader) · `agus.irawan` / `Presensi#2026` (karyawan).

## Menjalankan uji API otomatis

**Postman:** impor koleksi + environment → Settings › General › *Working directory* diarahkan ke folder `docs/api` (agar `sample-selfie.jpg` ditemukan) → Collection Runner → Run.

**CLI (Newman):**

```bash
cd docs/api
npx newman run Presensi_Proyek_API.postman_collection.json \
  -e Presensi_Proyek_Local.postman_environment.json --working-dir .
```

Hasil yang diharapkan: `requests 41 · assertions 163 · failed 0`.

> Koleksi membuat presensi untuk **hari ini & kemarin** serta pengajuan izin. Jalankan ulang hanya setelah `migrate:fresh --seed`, karena run kedua akan ditolak `already_checked_in`.

## Alur kerja QA

1. Jalankan API-001 (koleksi otomatis) sebagai *smoke test*.
2. Kerjakan sheet Web Admin → API Mobile → Laporan → Skenario Bisnis. Isi **Hasil aktual**, **Status**, **Penguji**, **Tanggal uji**.
3. Catat setiap kegagalan di sheet **Bug Log** dan tulis ID bug-nya di kolom *Bug / catatan* kasus terkait.
4. Kriteria lulus rilis (sheet Ringkasan): **0 Fail berprioritas Tinggi** dan **tingkat lulus ≥ 95%**.
