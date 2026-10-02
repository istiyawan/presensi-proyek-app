# Implementation Plan — Aplikasi Presensi Karyawan Proyek

> Backend: Laravel + jQuery (web admin) + MySQL · Mobile: Flutter (Android & iOS)
> Acuan: dokumen `ABSENSI BULAN 1.pdf` (Laporan Absensi Harian, periode 25/02/2026 – 26/03/2026)
> Revisi 3 — disesuaikan dengan keputusan pada §11 (single-tenant per deployment, multi proyek)

---

## 1. Hasil Analisis Dokumen Acuan

### 1.1 Struktur laporan
| Bagian | Isi |
|---|---|
| Judul | **Laporan Absensi Harian** |
| Periode | `25/02/2026 - 26/03/2026` (30 hari, **cut-off tanggal 25 s/d 26**, bukan bulan kalender) |
| Tipe Laporan | `Individual` (1 karyawan per bagian, ganti halaman per karyawan) |
| Kolom tabel | Tanggal · Nama · Jabatan · Shift · Check-in · Check-out · Koordinat Check-in · Koordinat Check-out · Status · Durasi · Foto (CI & CO) |
| Footer | Kota + tanggal (`Surabaya, 26 Maret 2026`) · "Dibuat" · nama perusahaan KSO · nama penandatangan + jabatan |

### 1.2 Data yang ditemukan
- **9 karyawan × 30 hari = 270 baris**, 9 bagian (± 3–4 halaman per karyawan).
- Karyawan & jabatan:

| Nama | Jabatan |
|---|---|
| Edi Santoso, ST | Team Leader |
| Puguh Sarwono | Water Resources Engineer |
| Danang Ady Trisno, ST | Ahli Hidrologi |
| Bambang Rianto, ST | Ahli Hidromekanikal |
| Edy Jayanto, ST | Ahli Geologi/Geoteknik |
| Helmy Mukti Wijaya, ST | Ahli Geodesi |
| Nabilah Ismiradiana | CAD Operator 1 |
| Agus Irawan | Juru Ukur 1 |
| Budiarto | Administrasi dan Keuangan |

- Perusahaan: **KSO** — PT. Brahma Seta Indonesia – PT. INAKKO Internasional Konsulindo – PT. Ika Adya Perkasa. Penandatangan: **Edi Santoso, S.T., M.T. (Team Leader)**.
- Shift: hanya **"Jam Kerja Standard"**. Check-in ±07:00–10:30, check-out ±17:00–18:00.
- **Kerja 7 hari/minggu** — Sabtu & Minggu ikut tercatat.
- Status: semua "Hadir" (check-in 10:30 pun Hadir).
- Koordinat: semua `-7.3154, 112.6855` (1 lokasi, ditampilkan 4 desimal).
- Durasi = check-out − check-in, format `9 jam 28 menit`.
- Foto: selfie saat check-in (CI) dan check-out (CO), thumbnail.

---

## 2. Prinsip Desain (hasil keputusan)

| # | Keputusan | Dampak desain |
|---|---|---|
| 1 | Status terlambat diatur di web | Toggle `late_enabled` + jam masuk + toleransi **per shift**. Jika off → semua yang check-in = "Hadir" (seperti acuan). |
| 2 | Radius & presensi dalam/luar lokasi diatur di web; luar lokasi cukup ditandai | Proyek bisa punya **banyak titik lokasi** dengan radius masing-masing; mode **Dalam Lokasi** / **Luar Lokasi**. Luar lokasi **hanya ditandai** (default), approval Team Leader tersedia sebagai setting (default off). |
| 3 | 7 hari kerja + opsi libur nasional | Default 7 hari; toggle `use_national_holidays` per proyek; kalender libur dikelola di web. |
| 4 | Offline mode wajib | Antrian lokal di HP + **waktu terpercaya** (offset server + monotonic clock) + sinkron idempotent. |
| 5 | Periode default 25–26 (ikut laporan, boleh tumpang tindih) | Periode hanya **filter laporan**, presensi tidak "dimiliki" periode → tumpang tindih tidak menimbulkan data ganda. Laporan juga bisa dicetak dengan **rentang tanggal bebas**. |
| 6 | Penandatangan bisa bertambah | Tabel penandatangan per proyek (label, nama, jabatan, urutan); layout footer menyesuaikan 1–4 kolom. |
| 7 | Multi proyek; klien lain = **app & server terpisah** | **Single-tenant per deployment** (1 server = 1 perusahaan/KSO, banyak proyek). Kode disiapkan untuk **white-label**: branding dari setting/`.env`, Flutter memakai *flavor* per klien. |
| 8 | VPS atau shared hosting | **Harus jalan di shared hosting**: tanpa Redis, tanpa Node/Chrome di server, queue via cron. |
| 9 | Android & iOS | Flutter untuk keduanya; perhatikan batasan iOS (background sync, deteksi fake GPS, distribusi App Store). Akun toko aplikasi diatur kemudian. |

---

## 3. Ruang Lingkup

### 3.1 Aplikasi Mobile (Flutter, Android & iOS) — karyawan
- Login, sesi tersimpan aman, pilih proyek aktif (jika ditugaskan di >1 proyek).
- Beranda: jam (tersinkron server), status hari ini, lokasi terdekat + jarak, indikator online/offline & jumlah data belum tersinkron.
- Check-in / Check-out:
  - **Dalam Lokasi** — GPS di dalam radius salah satu titik lokasi proyek.
  - **Luar Lokasi** — jika diizinkan proyek/karyawan: tercatat dengan tanda "Luar Lokasi" + keterangan (mis. "Survei titik BM-12"); approval Team Leader hanya jika setting diaktifkan.
  - Selfie kamera depan wajib.
  - Tetap bisa dilakukan saat **offline**.
- Riwayat presensi per periode + detail (foto, peta, status sinkron).
- Pengajuan Izin / Sakit / Cuti.
- Profil & ganti password.
- Menu Team Leader: kehadiran tim hari ini, approval izin & presensi luar lokasi.

### 3.2 Web Admin (Laravel Blade + jQuery)
- Dashboard per proyek: hadir / terlambat / luar lokasi / belum hadir / izin hari ini.
- **Master**: Proyek, Titik Lokasi (peta), Jabatan, Shift, Karyawan, Penugasan, Libur Nasional, Penandatangan.
- **Pengaturan aplikasi** (profil perusahaan/KSO, logo, warna) & **pengaturan proyek** (lihat §5).
- Monitoring presensi (DataTables server-side, filter, foto, peta CI/CO, badge Dalam/Luar Lokasi & Offline).
- Koreksi manual + audit log; approval izin & presensi luar lokasi.
- **Laporan**: Individual (format acuan), gabungan semua karyawan, Rekap (PDF & Excel) — filter berdasarkan **periode** ("Bulan ke-N") atau **rentang tanggal bebas**.
- Manajemen user & role.

### 3.3 Di luar lingkup fase awal
Payroll/lembur, face recognition, multi-tenant dalam satu server (tidak diperlukan — tiap klien di-deploy terpisah).

---

## 4. Arsitektur

```
┌──────────────────────┐  HTTPS/JSON (Sanctum)   ┌────────────────────────────────┐
│ Flutter (Android/iOS)│ ──────────────────────▶ │ Laravel                         │
│  └ SQLite offline    │                          │ ├─ routes/api.php  (mobile)     │
│    queue (drift)     │                          │ ├─ routes/web.php  (admin)      │
└──────────────────────┘                          │ │   Blade + jQuery + AJAX       │
┌──────────────────────┐  Session (web guard)    │ ├─ Queue (driver database)      │
│ Browser Admin        │ ──────────────────────▶ │ ├─ Scheduler (cron tiap menit)  │
└──────────────────────┘                          │ └─ Storage privat (foto)        │
                                                  └───────────────┬────────────────┘
                                                            ┌─────▼─────┐
                                                            │  MySQL 8  │
                                                            └───────────┘
```

Monorepo:
```
presensi-proyek-app/
├─ backend/        # Laravel
├─ mobile/         # Flutter
└─ docs/           # dokumen acuan, API spec, ERD, panduan deploy
```

### 4.1 Single-tenant, multi proyek, siap white-label
- **1 deployment = 1 perusahaan/KSO** (server, database, dan app sendiri). Di dalamnya bisa ada **banyak proyek**; data & akses dibatasi per proyek.
- Role global (spatie/laravel-permission); akses per proyek ditentukan tabel penugasan: `project_user` (admin proyek) dan `project_employee.is_team_leader` (Team Leader):

| Role | Akses |
|---|---|
| `super_admin` | Seluruh aplikasi: pengaturan aplikasi, semua proyek, user |
| `project_admin` | Proyek yang ditugaskan: master, setting, monitoring, laporan |
| `team_leader` | Monitoring tim proyeknya, approval (jika diaktifkan), penandatangan laporan |
| `employee` | Hanya aplikasi mobile |

- Satu karyawan bisa ditugaskan ke beberapa proyek (pindah proyek tanpa akun baru).
- **Siap white-label untuk klien berikutnya:**
  - Backend: nama perusahaan, logo, warna, nama aplikasi disimpan di tabel `app_settings` (diisi lewat web), bukan hardcode. Data awal klien lewat seeder/installer (`php artisan app:install`).
  - Flutter: **flavors** (Android `productFlavors`, iOS schemes) + file konfigurasi per klien (`config/<klien>.json`: nama app, bundle id, base URL API, logo, warna, ikon). Build klien baru cukup: `flutter build apk --flavor <klien>`.
  - Dokumen `docs/onboarding-klien-baru.md` berisi checklist deploy server + build app untuk klien baru.

### 4.2 Stack & paket

**Backend** (dipilih agar kompatibel shared hosting)
| Kebutuhan | Pilihan |
|---|---|
| Framework | Laravel 13, **PHP 8.3+** (pastikan hosting mendukung) |
| Auth API | Laravel Sanctum |
| Role | spatie/laravel-permission |
| Admin UI | Blade + Bootstrap 5 dengan tema kustom (tanpa AdminLTE) + jQuery 3.7, DataTables 3 (yajra server-side), Select2, SweetAlert2, Flatpickr, Leaflet, Chart.js; aset di-build lokal dengan Vite |
| Gambar | intervention/image dengan **driver GD** (Imagick sering tidak ada di shared hosting) |
| PDF | barryvdh/laravel-dompdf (murni PHP — **jangan** Browsershot/wkhtmltopdf) |
| Excel | maatwebsite/excel |
| Queue & cache | driver `database` (tanpa Redis) |
| Audit | spatie/laravel-activitylog |
| Setting | tabel `project_settings` + cache |

Aset frontend (CSS/JS) di-build di lokal (Vite) dan hasil `public/build` ikut di-deploy → server tidak butuh Node.js.

**Mobile (Flutter 3.x)**
| Kebutuhan | Paket |
|---|---|
| State | flutter_riverpod |
| HTTP | dio |
| Lokasi | geolocator (`isMocked` di Android) |
| Kamera | camera (kamera depan, tanpa galeri) |
| Kompres foto | flutter_image_compress |
| Token | flutter_secure_storage |
| DB offline | drift (SQLite) |
| Konektivitas | connectivity_plus |
| Sinkron latar | workmanager (Android) / background_fetch (iOS, best-effort) |
| Keamanan perangkat | safe_device (root/jailbreak, developer mode, mock location) |
| Peta | flutter_map |
| Info | device_info_plus, package_info_plus, uuid |

---

## 5. Pengaturan per Proyek (di Web Admin)

| Grup | Setting | Default |
|---|---|---|
| **Periode** | `period_start_day`, `period_end_day` (bulan berikutnya) | 25 / 26 |
| | `contract_start_date` (untuk penamaan "Bulan ke-N") | 25/02/2026 (proyek acuan) |
| **Hari kerja** | `working_days` (Senin–Minggu, checkbox) | 7 hari |
| | `use_national_holidays` | off |
| | Presensi di hari libur → dicatat `hadir` (ditandai "hari libur") | ya |
| **Terlambat** (per shift) | `late_enabled` | off |
| | `start_time`, `late_tolerance_min` | 08:00 / 0 |
| **Lokasi** | Titik lokasi (nama, lat, lng, `radius_m`) — bisa banyak | 1 titik, 100 m |
| | `allow_offsite` (izinkan presensi Luar Lokasi) | off |
| | `offsite_scope`: semua karyawan / karyawan tertentu | tertentu |
| | `offsite_requires_approval` (approval Team Leader) | **off** — cukup ditandai |
| | `offsite_requires_note` | ya |
| | `max_gps_accuracy_m` (tolak jika GPS kurang akurat) | 50 |
| | `block_mock_location` | ya |
| **Offline** | `allow_offline` | ya |
| | `offline_max_hours` (batas umur data offline yang diterima) | 72 |
| **Check-out** | `require_checkout_in_location` | ya |
| | `auto_close_missing_checkout` (tandai "lupa check-out") | ya |
| **Laporan** | kota laporan, urutan tanggal (desc/asc), tampilkan foto | Surabaya, desc, ya |
| | Penandatangan (lihat §8) | 1 orang |

---

## 6. Desain Database (MySQL)

```
app_settings          id, key, value(JSON)    -- nama perusahaan/KSO, logo, warna, nama app
projects              id, code, name, client_name, contractor_name (nama KSO, multi-baris), city,
                      contract_start_date, contract_end_date, timezone, is_active
project_settings      id, project_id, key, value(JSON)          -- §5, di-cache
project_locations     id, project_id, name, latitude DECIMAL(10,7), longitude DECIMAL(10,7),
                      radius_m, is_active
positions             id, name
shifts                id, project_id, name ("Jam Kerja Standard"), start_time, end_time,
                      late_enabled, late_tolerance_min, checkin_open_time, checkin_close_time
employees             id, user_id, nik, full_name, title_prefix, title_suffix,
                      position_id, phone, photo, is_active
users                 id, name, username, email, password, is_active
user_devices          id, user_id, device_id, platform (android/ios), model, app_version,
                      is_active, last_seen_at                    -- device binding
project_employee      id, project_id, employee_id, shift_id, start_date, end_date,
                      allow_offsite (override per karyawan)
attendances           id, uuid (client-generated, UNIQUE → idempotent),
                      project_id, employee_id, shift_id, work_date DATE,
                      -- check-in
                      check_in_at, check_in_lat, check_in_lng, check_in_accuracy,
                      check_in_location_id NULL, check_in_distance_m,
                      check_in_mode ENUM('onsite','offsite'), check_in_note,
                      check_in_photo, check_in_thumb, check_in_offline BOOL,
                      check_in_device_time, check_in_received_at,
                      -- check-out (kolom serupa, prefix check_out_)
                      ...
                      status ENUM('hadir','terlambat','izin','sakit','cuti','alpha','libur'),
                      late_minutes, duration_minutes, is_holiday_work BOOL,
                      offsite_approval ENUM('none','pending','approved','rejected'),
                      offsite_approved_by, is_mock_location, is_manual, corrected_by, note,
                      flags JSON  (mis. time_suspicious, low_accuracy)
                      UNIQUE(employee_id, project_id, work_date)
leave_requests        id, project_id, employee_id, type, start_date, end_date,
                      reason, attachment, status, approved_by, approved_at
holidays              id, date, name, type ENUM('national','cuti_bersama','project'),
                      project_id NULL
report_signatories    id, project_id, label ("Dibuat"/"Diperiksa"/"Mengetahui"/"Disetujui"),
                      employee_id NULL, name, title, organization (multi-baris), sort_order, is_active
report_jobs           id, project_id, requested_by, type, params JSON, status, file_path
activity_log          (spatie)
```

Index utama: `(project_id, work_date)`, `(employee_id, work_date)`, `uuid`.

---

## 7. Aturan Bisnis

### 7.1 Check-in / Check-out
1. Waktu acuan = **waktu server** (online) atau **waktu terpercaya** (offline, §7.3). Zona dari setting proyek (default `Asia/Jakarta`).
2. Check-in maksimal 1× per hari per proyek; check-out wajib setelah check-in.
3. Validasi lokasi:
   - Hitung jarak (haversine) ke setiap titik `project_locations` → ambil terdekat.
   - Di dalam radius → `mode = onsite`.
   - Di luar radius → jika `allow_offsite` berlaku untuk karyawan tsb → `mode = offsite`, **langsung tercatat** dengan tanda "Luar Lokasi" (`offsite_approval = none`); hanya jika `offsite_requires_approval` aktif → `pending` sampai disetujui TL. Jika tidak diizinkan → **ditolak** dengan pesan jarak.
   - Tolak jika `isMocked` (bila `block_mock_location`) atau akurasi > `max_gps_accuracy_m`.
4. Foto selfie wajib, disimpan + thumbnail (±120px) untuk laporan.

### 7.2 Status
- Shift `late_enabled = off` → `hadir`.
- `late_enabled = on` → check-in > `start_time + toleransi` → `terlambat` (`late_minutes` dicatat).
- Izin/sakit/cuti yang disetujui → status sesuai jenis.
- **Scheduler harian** (00:30, untuk hari sebelumnya): karyawan tanpa presensi → `libur` jika hari tsb bukan hari kerja / libur nasional aktif, selain itu `alpha`. Karena data offline bisa masuk terlambat, status `alpha` akan **di-overwrite** otomatis bila data offline untuk tanggal tsb tiba belakangan.
- Lupa check-out → `duration_minutes` kosong, flag `missing_checkout`, muncul di daftar koreksi admin. Ditandai oleh scheduler **per jam** setelah melewati `max_work_hours` sejak check-in — *bukan* saat tutup hari 00:30 (lihat §7.7).

### 7.3 Offline mode
- **Waktu terpercaya**: setiap kali online, app menyimpan `serverTime − deviceTime` dan `elapsedRealtime` (jam monotonic sejak boot). Saat offline, waktu presensi = `serverTimeTerakhir + (elapsedSekarang − elapsedSaatSinkron)`. Jika HP reboot sejak sinkron terakhir, presensi tetap disimpan tapi diberi flag `time_suspicious`.
- Data disimpan di SQLite lokal (drift) beserta foto terkompres, koordinat, akurasi, dan `uuid` unik.
- Sinkron otomatis saat koneksi kembali (connectivity_plus) + saat app dibuka + background task (Android workmanager; iOS best-effort via background_fetch).
- Server: upsert berdasarkan `uuid` (aman jika terkirim dua kali), validasi ulang geofence, tolak data lebih tua dari `offline_max_hours`, tandai `check_in_offline = true`.
- Data proyek (titik lokasi, radius, shift, setting) di-cache di HP agar validasi radius tetap jalan saat offline.
- Laporan & dashboard menampilkan ikon "offline" untuk transparansi.

### 7.4 Periode laporan
- Periode ke-N: `period_start_day` bulan M s/d `period_end_day` bulan M+1, dihitung dari `contract_start_date` (Bulan 1 = 25/02/2026 – 26/03/2026, Bulan 2 = 25/03/2026 – 26/04/2026, dst.).
- Tanggal 25–26 muncul di dua periode berurutan — **disengaja, mengikuti format laporan acuan**. Karena periode hanya filter tampilan laporan (bukan atribut data presensi), tidak ada data ganda di database.
- Saat cetak, admin memilih: **Periode** ("Bulan ke-1, ke-2, …") **atau Rentang tanggal** (date range picker bebas). Teks "Periode: …" di header laporan mengikuti pilihan tsb.

### 7.5 Libur nasional
- Tabel `holidays` diisi lewat web: input manual, **import CSV/Excel**, atau tombol "Impor libur nasional tahun X" dari file JSON yang disertakan di aplikasi (diperbarui tiap tahun sesuai SKB 3 Menteri). Tidak bergantung API eksternal agar aman di shared hosting.
- Ditampilkan dalam kalender 12 bulan per tahun (dirender langsung, tanpa FullCalendar).

### 7.6 Device binding
1 akun = 1 perangkat aktif; login di HP lain ditolak sampai admin mereset di web.

### 7.7 Check-out lewat tengah malam (tanpa hitungan lembur)

**Skenario:** 2 Okt 2026 masuk 08:00 → pulang **3 Okt 01:00**; 3 Okt masuk 08:00 → pulang 17:00.

**Prinsip:** presensi milik **tanggal kerja check-in** (`work_date`). Check-out sesudah tengah malam tetap menutup presensi tanggal check-in selama masih di dalam **jendela jam kerja** (`max_work_hours`, default 20 jam, bisa diatur 12–36 jam per proyek).

| Waktu | Kejadian | Hasil |
|---|---|---|
| 2 Okt 08:00 | Check-in | Baris `work_date = 2 Okt`, status Hadir |
| 3 Okt 00:30 | Scheduler tutup hari 2 Okt | Tidak ada alpha (sudah check-in); **tidak** ditandai lupa check-out karena shift masih berjalan |
| 3 Okt 00:45 | Karyawan membuka aplikasi | `GET /attendance/today` → `attendance: null` (3 Okt belum ada) + **`open_attendance`: 2 Okt** → aplikasi menampilkan tombol **Check-out**, bukan Check-in |
| 3 Okt 01:00 | Check-out | Menutup baris **2 Okt**: durasi **17 jam 0 menit**, `check_out.next_day = true` (tampil "01:00 **+1 hari**") |
| 3 Okt 08:00 | Check-in | Baris baru `work_date = 3 Okt` (status dihitung normal, terlambat bila diaktifkan) |
| 3 Okt 17:00 | Check-out | Menutup baris 3 Okt: durasi **9 jam 0 menit** |

**Tanpa lembur:** durasi dicatat apa adanya (17 jam), tidak dipotong ke jam shift dan tidak dipecah ke dua tanggal. Kelak lembur dapat dihitung = durasi − jam kerja shift, tanpa mengubah data.

**Kasus tepi yang ditangani:**
1. **Check-in saat shift kemarin masih terbuka** (mis. lupa check-out lalu check-in pagi berikutnya, masih di dalam jendela) → ditolak `409 open_previous_shift` beserta data presensi terbuka. Aplikasi menawarkan dua pilihan: *Check-out shift sebelumnya* atau *Lupa check-out, mulai presensi baru* (`abandon_previous=true` → presensi lama ditandai `missing_checkout`, presensi baru dibuat).
2. **Check-out setelah jendela lewat** → ditolak `no_checkin`; presensi lama sudah ditandai lupa check-out dan dikoreksi admin.
3. **Koreksi manual di web**: jam check-out lebih kecil dari check-in (08:00 → 01:00) otomatis dianggap hari berikutnya; durasi dihitung ulang dan penanda lupa check-out dihapus.
4. **Offline**: logika sama memakai waktu terpercaya `captured_at`.
5. **Laporan**: kolom check-out menampilkan penanda tanggal berikutnya (mis. "01:00 (+1)") agar tidak terbaca sebagai pulang dini hari tanggal yang sama.

Ditetapkan oleh automated test `OvernightShiftTest` (4 skenario) dan `AdminPanelTest::test_manual_correction_handles_checkout_after_midnight`.

---

## 8. Laporan

### 8.1 Laporan Individual (replikasi dokumen acuan)
- A4 landscape, 1 karyawan = 1 section, `page-break` antar karyawan, header tabel diulang tiap halaman.
- Header: logo perusahaan (opsional, dari `app_settings`) · "Laporan Absensi Harian" · "Periode: dd/mm/yyyy - dd/mm/yyyy" · "Tipe Laporan: Individual".
- Kolom sama dengan acuan, ditambah penanda kecil bila relevan: **LL** (Luar Lokasi), **OFF** (offline), keterangan libur.
- Koordinat 4 desimal; durasi `X jam Y menit`; thumbnail **CI**/**CO**.
- Footer: `{kota}, {tanggal Indonesia}` lalu **kolom penandatangan dinamis** dari `report_signatories` (1 orang = rata kanan seperti acuan; 2–4 orang = dibagi rata dalam satu baris), masing-masing: label · organisasi · ruang tanda tangan · nama · jabatan.

### 8.2 Laporan lain
- PDF gabungan semua karyawan dalam satu proyek.
- Rekap periode: matriks karyawan × tanggal + total H/T/I/S/C/A/L/LL — PDF & Excel.
- Export data mentah Excel.

### 8.3 Pembuatan PDF di shared hosting
- PDF gabungan (±270 foto) dibuat oleh **queue job** → status di tabel `report_jobs` → admin mengunduh setelah selesai (polling jQuery).
- Job dipecah per karyawan lalu digabung, agar tidak melewati batas `max_execution_time` / memori shared hosting.

---

## 9. API Mobile (`/api/v1`)

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/auth/login` | Token + profil + device binding |
| POST | `/auth/logout` | Hapus token |
| GET | `/time` | Waktu server (sinkron jam offline) |
| GET | `/me` | Profil + daftar proyek yang ditugaskan |
| GET | `/projects/{id}/config` | Titik lokasi, radius, shift, setting (di-cache offline) |
| GET | `/attendance/today?project_id=` | Status hari ini |
| POST | `/attendance/check-in` | multipart: `uuid, project_id, photo, lat, lng, accuracy, is_mock, mode, note, captured_at, device_time, is_offline, flags` |
| POST | `/attendance/check-out` | idem |
| POST | `/attendance/sync` | Kirim batch data offline (per item: hasil sukses/ditolak + alasan) |
| GET | `/attendance/history?project_id=&period=` | Riwayat per periode |
| GET | `/attendance/{id}` | Detail |
| GET | `/attendance/{id}/photo/{in\|out}?thumb=1` | Foto presensi (hanya pemilik & Team Leader) |
| GET | `/projects/{id}/periods` | Daftar periode "Bulan ke-N" |
| GET | `/app/info` | Nama app, perusahaan, warna, versi minimum |
| GET/POST | `/leave-requests` | List & ajukan |
| GET | `/team/today` | (TL) kehadiran tim, termasuk tanda Luar Lokasi |
| GET | `/approvals` | (TL) izin + presensi luar lokasi yang pending (yang kedua hanya jika approval diaktifkan) |
| PATCH | `/approvals/{type}/{id}` | (TL) approve / reject |
| POST | `/me/password` | Ganti password |

Format respons: `{ success, message, data, errors }`. Versi API di-prefix agar app versi lama tetap jalan; endpoint `/app/version` untuk paksa update.

---

## 10. Tahapan Implementasi

> **Status (02/10/2026):** Fase 0 (bagian backend), Fase 1, Fase 2, dan **Fase 3 selesai** — 48 feature test lolos. Ditambahkan juga penanganan check-out lewat tengah malam (§7.7). Berikutnya: Fase 4 (Flutter, ditunda atas permintaan) atau Fase 5–6 (UAT & deployment).

### Fase 0 — Persiapan (± 1 minggu)
- [x] Monorepo + Git, Laravel 13, MySQL lokal (Laragon).
- [ ] Flutter project dengan **flavor** awal — bundle id Android & iOS sementara. *(Folder `mobile/` baru berisi template `flutter create`, org `id.co.nindya`; dilanjutkan di Fase 4.)*
- [ ] Akses Mac atau CI cloud (Codemagic / GitHub Actions macOS) untuk build & uji iOS.
- [ ] *(Ditunda, wajib sebelum Fase 6)* Akun **Apple Developer** (USD 99/tahun) & **Google Play Console** (USD 25 sekali). Selama pengembangan: Android pakai APK langsung, iOS pakai simulator / Apple ID gratis (sertifikat 7 hari) di iPhone fisik.
- [ ] Cek spesifikasi target hosting: versi PHP, ekstensi GD/zip/fileinfo, cron, SSH, batas upload & `max_execution_time`.
- [ ] Mockup layar mobile & layout admin.

### Fase 1 — Backend inti (± 2 minggu)
- [x] Migration, model, seeder (data dari PDF: proyek "Nindya - ITP KSO Kedurus", 9 karyawan, shift standard, 1 penandatangan KSO, **270 presensi demo**, libur nasional 2026). Tanggal kontrak sementara 01/02/2026 → Bulan 1 = 25/02–26/03/2026.
- [x] Role global + akses per proyek via penugasan (`project_user`, `is_team_leader`).
- [x] `SettingService` (`app_settings` + default `config/presensi.php` + override per proyek + cache).
- [x] Command `php artisan app:install` untuk deployment klien baru; `holidays:import {tahun}`.
- [x] `AttendanceService`: geofence multi-titik, mode onsite/offsite, status & terlambat, durasi, foto + thumbnail (GD), idempotensi `uuid`.
- [x] Endpoint API §9 + endpoint sinkron batch offline (hasil per item).
- [x] Scheduler `attendance:close-day` (00:30): alpha/libur, tandai lupa check-out; data offline yang masuk belakangan menimpa alpha.
- [x] Feature test: login & device binding, geofence, offsite (ditandai vs approval), mock GPS, terlambat on/off, idempotensi, offline (kadaluarsa/masa depan/flag), periode 25→26, libur nasional, izin & approval TL, akses antar-proyek.
- [ ] *(Sisa, dikerjakan bersama Fase 2)* Verifikasi daftar libur 2026 dengan SKB resmi.

### Fase 2 — Web Admin (± 2,5 minggu)
- [x] Layout premium kustom (sidebar gelap, topbar dengan pemilih proyek & jam proyek), halaman login, ganti password, responsif HP.
- [x] Hak akses web: super admin (semua), admin proyek (proyek ditugaskan), Team Leader (pantau + approval saja). Akun karyawan ditolak masuk web.
- [x] Halaman **Pengaturan Aplikasi** (nama aplikasi/perusahaan, logo, warna utama + pratinjau langsung, device binding, versi minimum app).
- [x] CRUD jQuery AJAX + DataTables server-side + modal: Karyawan (akun, penugasan, TL, luar lokasi, reset password/perangkat, tambah dari proyek lain), Shift, Jabatan, Proyek, Pengguna Admin.
- [x] Halaman **Pengaturan Proyek** 7 tab (Informasi, Periode + pratinjau Bulan ke-N, Hari Kerja & libur, Lokasi & presensi + batas jam kerja, Offline, Laporan, Penandatangan maks. 4).
- [x] Titik lokasi dengan peta Leaflet (klik/seret titik, slider radius, tempel koordinat Google Maps).
- [x] Kalender libur 12 bulan + impor libur nasional + libur khusus proyek.
- [x] Monitoring presensi (filter periode/tanggal/karyawan/status/lokasi/penanda), panel detail (foto CI/CO, peta, perangkat, audit log), koreksi & presensi manual.
- [x] Approval izin/sakit/cuti & presensi luar lokasi (web, juga untuk TL).
- [x] Dashboard per proyek: kartu statistik, tren 14 hari (palet tervalidasi aksesibilitas + tampilan tabel), aktivitas terbaru, peta check-in, daftar perlu tindakan.

### Fase 3 — Laporan (± 1,5 minggu)
- [x] Laporan Individual (§8.1) — dibandingkan berdampingan dengan `ABSENSI BULAN 1.pdf` (render halaman): kolom, header biru, baris berselang, header diulang tiap halaman, foto CI/CO, blok tanda tangan serif. PDF gabungan data demo = **36 halaman, sama dengan acuan**.
- [x] Filter cetak: Periode ("Bulan ke-N") atau rentang tanggal bebas (maks. 93 hari).
- [x] Footer penandatangan dinamis 1–4 kolom (1 orang rata kanan seperti acuan).
- [x] Penanda check-out hari berikutnya pada kolom check-out ("01:00:00 (+1)", §7.7) + keterangan Luar lokasi / Hari libur / Lupa check-out di kolom Status.
- [x] PDF gabungan + `report_jobs`: diproses langsung bila kecil, masuk antrean bila besar (bobot karyawan × hari, ×2 bila berfoto; ambang `REPORT_SYNC_MAX_ROWS`, default 600). Riwayat laporan dengan status otomatis, Lihat/Unduh, berkas dihapus otomatis setelah 14 hari.
- [x] Rekap periode: PDF (matriks H/T/I/S/C/A/L, * luar lokasi, hari libur diarsir, total jam) + Excel (sheet Rekap berwarna + sheet Detail ber-filter).

### Fase 4 — Aplikasi Flutter (± 3,5 minggu, paralel dengan Fase 2–3)
- [ ] Struktur `lib/core` + `lib/features/{auth,project,attendance,offline,history,leave,team,profile}`.
- [ ] Konfigurasi **flavor** (nama app, bundle id, base URL, logo, warna, ikon dari `config/<klien>.json`) — tidak ada branding yang di-hardcode.
- [ ] Login, device binding, pilih proyek.
- [ ] Sinkron waktu server & cache config proyek.
- [ ] Alur presensi Dalam / Luar Lokasi (GPS → validasi → selfie → kompres → simpan lokal → upload).
- [ ] **Offline engine**: drift, antrian, waktu terpercaya, sinkron otomatis, layar "Data belum terkirim".
- [ ] Deteksi fake GPS, root/jailbreak, developer mode.
- [ ] Riwayat, pengajuan izin, menu Team Leader & approval.
- [ ] Penyesuaian iOS: teks izin di `Info.plist` (lokasi, kamera), background fetch, uji di iPhone fisik.

### Fase 5 — Pengujian & UAT (± 1,5 minggu)
- [ ] Uji lapangan: sinyal hilang/timbul, mode pesawat, ganti jam HP, reboot saat offline.
- [ ] Uji perangkat: Android kelas bawah + beberapa iPhone.
- [ ] Uji di hosting target (shared hosting & VPS): queue via cron, PDF gabungan, upload foto.
- [ ] UAT dengan Team Leader; cocokkan format laporan.

### Fase 6 — Deployment & Rilis (± 1 minggu)
- [ ] Deploy sesuai §13.
- [ ] *(Butuh akun toko aplikasi)* Rilis Android (Play Store / internal testing) & iOS (TestFlight → App Store; siapkan kebijakan privasi & alasan penggunaan lokasi untuk review Apple).
- [ ] Import data, pelatihan, pendampingan minggu pertama.
- [ ] Tulis `docs/onboarding-klien-baru.md` (checklist deploy server + build flavor baru).

**Total estimasi: ± 10–12 minggu** untuk 1 developer full-stack; ± 7–8 minggu jika backend & mobile dikerjakan 2 orang secara paralel. (Sedikit lebih ringan dari revisi 2 karena tidak perlu multi-tenant.)

---

## 11. Keputusan yang Sudah Diambil

| # | Topik | Keputusan |
|---|---|---|
| 1 | Status terlambat | Setting di web (per shift), bisa on/off |
| 2 | Geofence | Radius diatur di web; tersedia mode Dalam Lokasi & Luar Lokasi |
| 3 | Hari kerja | 7 hari/minggu; kalender libur nasional opsional |
| 4 | Offline | Wajib |
| 5 | Periode | Default 25 → 26 bulan berikutnya, ikut laporan acuan (tanggal 25–26 boleh masuk dua periode); bisa diubah di web; laporan juga bisa per rentang tanggal bebas |
| 6 | Penandatangan | 1 orang dulu, bisa ditambah via setting |
| 7 | Skala | Multi proyek dalam satu deployment; klien lain = **app & server terpisah** (white-label) |
| 8 | Hosting | VPS atau shared hosting |
| 9 | Platform | Android & iOS |
| 10 | Presensi Luar Lokasi | Cukup ditandai; approval Team Leader tersedia sebagai setting (default off) |
| 11 | Akun Apple / Google Play | Diatur kemudian (wajib ada sebelum Fase 6) |

---

## 12. Pertanyaan Tersisa
Tidak ada yang memblokir Fase 0–1. Hal kecil yang bisa dijawab sambil jalan:
1. Apakah 25/02/2026 benar tanggal mulai kontrak proyek acuan (untuk penomoran "Bulan ke-N")?
2. Nama resmi proyek & kode proyek untuk data awal (seeder).

---

## 13. Keamanan & Deployment

### 13.1 Keamanan
- HTTPS wajib; token Sanctum berkedaluwarsa; rate limit login & presensi.
- Foto di storage privat, diakses via route terotentikasi (tidak bisa ditebak URL-nya).
- Akses antar-proyek diuji otomatis (admin/TL proyek A tidak bisa melihat data proyek B).
- Setiap klien di server & database sendiri → data antar-klien terpisah secara fisik.
- Anti-kecurangan: kamera langsung, deteksi mock/root/jailbreak, device binding, waktu terpercaya, audit log koreksi.
- Catatan iOS: deteksi lokasi palsu terbatas → andalkan deteksi jailbreak + akurasi GPS + konsistensi data.

### 13.2 Shared hosting (cPanel)
- Document root diarahkan ke `public/` (atau subdomain → `public/`); jika tidak bisa, gunakan pola `public_html` + folder app di luar root.
- Satu cron job: `* * * * * php /path/artisan schedule:run`; scheduler menjalankan `queue:work --stop-when-empty --max-time=50` setiap menit.
- Upload via Git (jika ada SSH) atau zip; `vendor/` & `public/build` disiapkan dari lokal bila Composer/Node tidak tersedia.
- `storage:link` diganti route/symlink manual bila `symlink()` diblokir.
- Pantau kuota disk (foto ± 150–300 KB × 2 × karyawan × hari) — siapkan kebijakan arsip foto lama.

### 13.3 VPS
- Ubuntu + Nginx + PHP-FPM + MySQL 8, Supervisor untuk queue worker, cron scheduler, Let's Encrypt.
- Backup harian database + folder foto ke penyimpanan terpisah.
