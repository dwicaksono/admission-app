# Development Plan — Modul Penjadwalan Tes Seleksi PMB
## Nama: Dimas | Event: Vibe Coding & Venture — SEVIMA
## Tanggal: 13 Juni 2026

---

## Konteks Sistem yang Sudah Ada

Aplikasi PMB sudah berjalan dengan stack **React 18 + Tailwind CSS** (frontend) dan **Laravel 12 + SQLite** (backend).

**Fitur yang sudah berjalan:**

| Fitur | Status |
|-------|--------|
| Formulir pendaftaran online | ✅ Selesai |
| Generate nomor pendaftaran otomatis (format: `PMB-YYYY-XXXX`) | ✅ Selesai |
| Cek status pendaftaran by nomor pendaftar | ✅ Selesai |
| Dashboard admin dengan statistik per prodi & jalur | ✅ Selesai |
| Update status pendaftar (Menunggu / Lolos Seleksi / Tidak Lolos) | ✅ Selesai |
| Login admin dengan Sanctum token | ✅ Selesai |
| Export data pendaftar ke CSV | ✅ Selesai |
| Tombol heregistrasi untuk pendaftar yang Lolos Seleksi | ✅ Selesai |

**Struktur tabel existing yang relevan:**

```
pendaftars:
  id, nomor_pendaftaran, nama, nomor_hp, email,
  asal_sekolah, prodi, jalur, status, heregistrasi_at, timestamps

users:
  id, name, email, password, timestamps (admin system)
```

**Routes API existing:**

```
POST   /api/auth/login
POST   /api/auth/logout                      [auth:sanctum]
POST   /api/pendaftar
GET    /api/pendaftar/{nomorPendaftaran}
POST   /api/pendaftar/{nomorPendaftaran}/heregistrasi
GET    /api/pendaftar                        [auth:sanctum]
PATCH  /api/pendaftar/{id}/status            [auth:sanctum]
GET    /api/statistik                        [auth:sanctum]
GET    /api/pendaftar/export/csv             [auth:sanctum]
```

---

## Brief Pengembangan

> *"Sistem pendaftaran sudah berjalan bagus. Sekarang kami butuh modul untuk mengelola jadwal tes seleksi dan wawancara PMB. Saat ini semua masih manual — panitia kirim jadwal lewat WhatsApp, peserta sering tidak tahu jadwal mereka, dan banyak yang tidak hadir karena informasi tidak sampai."*

**Masalah inti yang harus diselesaikan:**
1. Jadwal tersebar manual via WhatsApp → tidak terpusat, tidak terdokumentasi
2. Peserta tidak tahu jadwal mereka → tingkat kehadiran rendah
3. Admin tidak punya visibilitas siapa yang sudah/belum konfirmasi
4. Tidak ada mekanisme reschedule yang terstruktur

---

---

## BAGIAN 1 — Analisa Teknis

---

### 1.1 Identifikasi Pengguna

| Pengguna | Peran dalam Modul Penjadwalan |
|----------|-------------------------------|
| **Admin PMB** | Membuat slot jadwal tes, meng-assign peserta ke slot, memantau kehadiran, meng-approve/reject request reschedule, mengirim ulang notifikasi |
| **Panitia Tes** *(sub-role admin)* | Menandai kehadiran peserta saat hari H (mark hadir / tidak hadir per peserta di slot tertentu) |
| **Calon Mahasiswa (Peserta)** | Melihat jadwal tes yang sudah ditetapkan untuk dirinya, mengkonfirmasi kehadiran, mengajukan request reschedule jika ada halangan |

> **Catatan desain:** Modul ini tidak membuat pengguna baru dari nol. Admin menggunakan akun `users` yang sudah ada dengan Sanctum token. Calon mahasiswa mengakses jadwal mereka via `nomor_pendaftaran` yang sudah dimiliki sejak pendaftaran — tidak perlu login terpisah.

---

### 1.2 Fitur Utama per Pengguna

#### Admin PMB

| # | Fitur Baru | Masalah yang Diselesaikan |
|---|-----------|--------------------------|
| 1 | Buat slot jadwal tes (tanggal, jam, lokasi, kapasitas, tipe tes) | Sentralisasi jadwal dari WhatsApp ke sistem |
| 2 | Publish jadwal → trigger notifikasi email otomatis ke peserta | Peserta tidak tahu jadwal mereka |
| 3 | Auto-assign: sistem otomatis memasukkan semua pendaftar berstatus "Lolos Seleksi" yang belum punya jadwal ke slot yang tersedia | Mengurangi kerja manual admin |
| 4 | Manual assign: admin memilih peserta spesifik dan slot jadwal | Fleksibilitas untuk kasus khusus |
| 5 | Dashboard jadwal: ringkasan per slot (total assigned, sudah konfirmasi, hadir/tidak hadir) | Visibilitas real-time status kehadiran |
| 6 | Kelola request reschedule: lihat alasan, approve (pilih jadwal baru) atau reject | Proses reschedule terstruktur, tidak via chat |
| 7 | Tandai kehadiran: mark hadir / tidak hadir per peserta saat hari H | Data absensi terdokumentasi di sistem |

#### Calon Mahasiswa (Peserta)

| # | Fitur Baru | Masalah yang Diselesaikan |
|---|-----------|--------------------------|
| 1 | Cek jadwal tes: tampilkan tanggal, jam, lokasi, tipe tes di halaman CekStatus | Peserta tidak perlu tanya ke panitia |
| 2 | Konfirmasi kehadiran: tombol "Konfirmasi Hadir" di halaman CekStatus | Admin tahu siapa yang pasti datang |
| 3 | Ajukan reschedule: isi alasan, kirim request ke admin | Proses reschedule tanpa WhatsApp |
| 4 | Terima notifikasi email saat jadwal dipublish atau reschedule diproses | Informasi jadwal aktif sampai ke peserta |

---

### 1.3 Tech Stack Tambahan

| Komponen | Pilihan | Alasan |
|----------|---------|--------|
| **UI Kalender Admin** | `@fullcalendar/react` + `@fullcalendar/daygrid` | Visualisasi slot jadwal per hari lebih intuitif dari tabel biasa; library matang dengan TypeScript support |
| **Date/Time picker** | `react-datepicker` | Ringan, bisa combined date+time, sudah digunakan luas di ekosistem React |
| **Toast notifikasi UI** | `react-hot-toast` | Lebih ringan dari react-toastify, API sederhana, sudah konsisten dengan gaya kode existing |
| **Email (notifikasi)** | Laravel Mailables + SMTP (Mailtrap dev / Resend prod) | Built-in di Laravel, zero dependency tambahan di backend; Mailtrap gratis untuk dev, Resend murah untuk prod |
| **Queue (async email)** | Laravel Queue dengan `database` driver | Tidak perlu Redis untuk prototype; tabel `jobs` sudah ada dari setup awal Laravel |
| **Format tanggal display** | `date-fns` (sudah tersedia di proyek React) | Konsisten dengan penggunaan existing, tidak perlu dayjs/moment yang lebih berat |

**Keputusan yang sengaja TIDAK diambil:**
- **WhatsApp API (Fonnte/WaBlas):** Berbayar per pesan, membutuhkan nomor terverifikasi. Cukup gunakan email untuk prototype — channel bisa ditambahkan belakangan tanpa ubah arsitektur.
- **Redis Queue:** Over-engineering untuk skala PMB kampus kecil. Database queue sudah cukup.
- **Real-time WebSocket:** Notifikasi admin tidak perlu real-time; polling/refresh sudah cukup.

---

### 1.4 Batasan & Asumsi

| # | Batasan / Asumsi | Dampak Desain |
|---|-----------------|---------------|
| 1 | **Hanya pendaftar berstatus "Lolos Seleksi"** yang bisa di-assign jadwal | Sistem harus memfilter `pendaftars WHERE status = 'Lolos Seleksi'` sebelum auto-assign. Pendaftar dengan status lain tidak muncul di UI pemilihan peserta |
| 2 | **Satu peserta, satu jadwal aktif** (per tipe tes) | Constraint UNIQUE di tabel `peserta_jadwal` pada kombinasi `(pendaftar_id, tipe)` mencegah double-booking untuk tipe tes yang sama |
| 3 | **Kapasitas slot bersifat hard limit** | Sebelum assign, sistem harus mengecek `COUNT(peserta_jadwal) < kapasitas` di jadwal yang dipilih. Jika penuh, assign ditolak dengan pesan jelas |
| 4 | **Jadwal hanya bisa diubah selama status `draft`** | Setelah dipublish, admin tidak bisa edit detail jadwal (hanya bisa cancel dan buat baru) untuk menjaga konsistensi data notifikasi yang sudah terkirim |
| 5 | **Tidak memodifikasi tabel `pendaftars` dan `users`** | Semua data baru di tabel terpisah; relasi via FK. Fungsionalitas lama (pendaftaran, cek status, heregistrasi, export CSV) tidak berubah |
| 6 | **Autentikasi peserta via nomor_pendaftaran** | Peserta mengakses jadwal mereka cukup dengan nomor pendaftaran — tidak perlu password terpisah. Ini konsisten dengan cara kerja `CekStatus.jsx` yang sudah ada |

---

---

## BAGIAN 2 — Bisnis Proses & Flow

---

### 2.1 Flow Utama: Penjadwalan Tes Seleksi

```
[ADMIN] → Login ke dashboard (Sanctum token) → [DASHBOARD ADMIN]

[ADMIN] → Buka menu "Jadwal Tes" → Klik "Buat Jadwal Baru"
       → Isi form: judul, tipe tes, tanggal, jam mulai/selesai, lokasi, kapasitas
       → Submit → [SISTEM] simpan ke tabel jadwal_tes (status = 'draft')
       → [RESULT] Jadwal tersimpan, tampil di daftar dengan badge "Draft"

[ADMIN] → Pilih jadwal draft → Klik "Assign Peserta"
       ↓ opsi 1: Auto-Assign
       [SISTEM] → Query pendaftars WHERE status = 'Lolos Seleksi'
               → Filter: belum punya jadwal aktif untuk tipe tes ini
               → Filter: prodi/jalur cocok dengan filter jadwal (jika ada)
               → Check: COUNT(assigned) < kapasitas
               → INSERT ke peserta_jadwal (status = 'assigned')
               → [RESULT] Daftar peserta terassign muncul, sisa kapasitas diupdate

       ↓ opsi 2: Manual Assign
       [ADMIN] → Pilih pendaftar dari dropdown (list pendaftar Lolos Seleksi)
              → Klik "Tambahkan ke Jadwal Ini"
              → [SISTEM] validasi kapasitas → INSERT ke peserta_jadwal
              → [RESULT] Peserta masuk ke daftar jadwal

[ADMIN] → Review daftar peserta yang terassign → Klik "Publish Jadwal"
       → [SISTEM] UPDATE jadwal_tes SET status = 'published', published_at = NOW()
       → [SISTEM] Trigger: generate token_konfirmasi unik per peserta
       → [SISTEM] Push job ke queue: kirim email notifikasi ke semua peserta
       → [QUEUE WORKER] Proses email satu per satu → INSERT ke notifikasi_log
       → [RESULT] Jadwal live, peserta terima email berisi detail jadwal + link konfirmasi

[PESERTA] → Buka email → Klik link konfirmasi ATAU buka halaman CekStatus
         → Input nomor_pendaftaran → [SISTEM] query peserta_jadwal JOIN jadwal_tes
         → [RESULT] Tampil kartu jadwal: tipe tes, tanggal, jam, lokasi

[PESERTA] → Klik "Konfirmasi Hadir"
         → [SISTEM] UPDATE peserta_jadwal SET status = 'confirmed', confirmed_at = NOW()
         → [RESULT] Status berubah menjadi "Dikonfirmasi ✓"

[ADMIN/PANITIA] → Hari H: buka dashboard jadwal → pilih slot jadwal
               → Daftar peserta tampil dengan tombol toggle Hadir/Tidak Hadir
               → [ADMIN] klik "Hadir" per peserta
               → [SISTEM] UPDATE peserta_jadwal SET status = 'hadir'
               → [RESULT] Progress bar kehadiran terupdate real-time
```

---

### 2.2 Flow Alternatif: Peserta Minta Reschedule

```
[PESERTA] → Buka halaman CekStatus → Input nomor_pendaftaran
         → Tampil jadwal tes + tombol "Minta Reschedule"
         → Klik tombol → Form alasan muncul

[PESERTA] → Isi alasan reschedule → Submit
         → [SISTEM] Cek: status peserta_jadwal = 'assigned' atau 'confirmed'
                       (tidak bisa request jika sudah 'hadir' atau 'tidak_hadir')
         → INSERT ke reschedule_requests (status = 'pending')
         → UPDATE peserta_jadwal SET status = 'reschedule_diminta'
         → [RESULT] Pesan "Permintaan reschedule berhasil dikirim, tunggu konfirmasi admin"

[ADMIN] → Buka menu "Reschedule Requests" → Lihat daftar request pending
       → Klik request → Tampil detail: nama peserta, jadwal lama, alasan

       ↓ Jika DISETUJUI:
       [ADMIN] → Pilih jadwal baru dari dropdown (hanya jadwal published + ada sisa kapasitas)
              → Submit approve
              → [SISTEM] UPDATE reschedule_requests SET status = 'disetujui', jadwal_baru_id = ?
              → [SISTEM] UPDATE peserta_jadwal lama SET status = 'reschedule_disetujui'
              → [SISTEM] INSERT peserta_jadwal baru (jadwal baru, status = 'assigned')
              → [SISTEM] Kirim email notifikasi jadwal baru ke peserta
              → [RESULT] Peserta punya jadwal baru, jadwal lama non-aktif

       ↓ Jika DITOLAK:
       [ADMIN] → Isi catatan alasan penolakan → Submit reject
              → [SISTEM] UPDATE reschedule_requests SET status = 'ditolak'
              → [SISTEM] UPDATE peserta_jadwal SET status = 'assigned' (kembali ke semula)
              → [SISTEM] Kirim email notifikasi penolakan + catatan admin
              → [RESULT] Peserta tahu requestnya ditolak, jadwal lama tetap berlaku
```

---

### 2.3 Happy Path vs Error Path

**Happy Path (Flow 2.1):**
```
Admin buat jadwal → Assign peserta (kapasitas cukup, semua eligible) →
Publish → Email terkirim → Peserta konfirmasi → Admin mark hadir → Selesai
```

**Error Paths:**

| Kondisi Error | Trigger | Respons Sistem |
|---------------|---------|----------------|
| **Kapasitas penuh saat assign** | `COUNT(peserta_jadwal) >= kapasitas` | Tolak assign dengan pesan: *"Slot jadwal ini sudah penuh (X/X peserta). Buat jadwal baru atau hapus peserta yang ada."* |
| **Email gagal terkirim** | SMTP timeout / invalid email | Catat di `notifikasi_log` dengan `status = 'gagal'` + `error_message`. Tampilkan badge "Notif Gagal" di dashboard admin. Sediakan tombol "Kirim Ulang Notifikasi". |
| **Peserta sudah punya jadwal saat auto-assign** | `peserta_jadwal` dengan `status != 'reschedule_disetujui'` sudah ada | Skip peserta tersebut, tampilkan log: *"X peserta dilewati karena sudah memiliki jadwal."* |
| **Jadwal baru penuh saat approve reschedule** | `COUNT >= kapasitas` pada `jadwal_baru_id` | Tolak approve dengan pesan: *"Jadwal tujuan sudah penuh. Pilih jadwal lain."* |
| **Request reschedule setelah hari H** | `tanggal_jadwal < today` | Tombol "Minta Reschedule" disembunyikan otomatis. Jika hit API langsung, kembalikan 422 dengan pesan *"Jadwal sudah lewat, reschedule tidak dapat diproses."* |

---

### 2.4 State Machine — Jadwal Tes (TAMBAHAN)

```
                    ┌──────────────┐
                    │     DRAFT    │ ← Admin buat baru
                    └──────┬───────┘
                           │ Admin publish
                           ▼
                    ┌──────────────┐
                    │  PUBLISHED   │ ← Peserta bisa lihat & konfirmasi
                    └──────┬───────┘
                    │              │
         Lewat hari H │            │ Admin cancel
                    ▼              ▼
             ┌──────────┐   ┌───────────┐
             │  SELESAI  │   │ DIBATALKAN│
             └──────────┘   └───────────┘
```

```
State Machine — Status Peserta Jadwal (peserta_jadwal.status):

ASSIGNED ──── Peserta klik konfirmasi ──→ CONFIRMED
    │                                         │
    │ Admin mark hari H                       │ Admin mark hari H
    ▼                                         ▼
TIDAK_HADIR                               HADIR

ASSIGNED ──── Peserta request ──→ RESCHEDULE_DIMINTA
                                      │         │
                             Admin reject │       │ Admin approve
                                      ▼         ▼
                                  ASSIGNED   (jadwal baru: ASSIGNED)
```

---

---

## BAGIAN 3 — Alur Data

---

### 3.1 Alur Data: Proses Penjadwalan

```
[Admin: Form Input Jadwal]
    │ React Component: JadwalForm.jsx
    │ POST /api/admin/jadwal  (Bearer token)
    ▼
[Laravel: JadwalController@store]
    │ Validate: StoreJadwalRequest
    │ INSERT INTO jadwal_tes (status = 'draft')
    ▼
[Database: tabel jadwal_tes] ──→ Response: {id, kode_jadwal, ...}
    │
    │ (setelah admin assign & publish)
    │ POST /api/admin/jadwal/{id}/publish
    ▼
[Laravel: JadwalController@publish]
    │ UPDATE jadwal_tes SET status = 'published'
    │ SELECT dari pendaftars WHERE id IN (daftar assign)
    │ INSERT INTO peserta_jadwal (status = 'assigned')
    │ Generate token_konfirmasi unik per peserta (Str::random(64))
    │ Dispatch Job: KirimNotifikasiJadwal (to queue)
    ▼
[Laravel Queue Worker]
    │ Process: KirimNotifikasiJadwal
    │ SELECT peserta_jadwal JOIN pendaftars (ambil email, nama, detail jadwal)
    │ Send: Mailable JadwalTesMail via SMTP
    │ INSERT INTO notifikasi_log (status = 'terkirim'/'gagal')
    ▼
[Peserta: Inbox Email]
    │ Email berisi: nama, detail jadwal, link konfirmasi
    │ Link: {APP_URL}/cek-status?nomor={nomor_pendaftaran}&konfirmasi=1
    ▼
[React: CekStatus.jsx]
    │ GET /api/jadwal/{nomor_pendaftaran}
    │ Tampilkan: kartu jadwal tes
    │ Tampilkan: tombol "Konfirmasi Hadir" jika status = 'assigned'
```

---

### 3.2 Alur Data: Peserta Cek Jadwal

```
[Peserta: CekStatus.jsx]
    │ Input: nomor_pendaftaran (sudah ada dari flow lama)
    │ GET /api/pendaftar/{nomor_pendaftaran}   ← endpoint existing
    │ GET /api/jadwal/{nomor_pendaftaran}       ← endpoint baru
    ▼
[Laravel: JadwalPublikController@show]
    │ SELECT jadwal_tes.*, peserta_jadwal.status as status_peserta,
    │        peserta_jadwal.token_konfirmasi
    │ FROM peserta_jadwal
    │ JOIN jadwal_tes ON jadwal_tes.id = peserta_jadwal.jadwal_tes_id
    │ JOIN pendaftars ON pendaftars.id = peserta_jadwal.pendaftar_id
    │ WHERE pendaftars.nomor_pendaftaran = ?
    │   AND jadwal_tes.status = 'published'
    │   AND peserta_jadwal.status NOT IN ('reschedule_disetujui')
    ▼
[Response JSON]
    {
      "has_jadwal": true,
      "jadwal": {
        "judul": "Tes Seleksi Gelombang 1",
        "tipe": "tes_tertulis",
        "tanggal": "2026-07-05",
        "jam_mulai": "09:00",
        "jam_selesai": "11:00",
        "lokasi": "Gedung A - Ruang 101",
        "status_peserta": "assigned"  // assigned | confirmed | hadir | tidak_hadir
      }
    }
    ▼
[React: CekStatus.jsx]
    │ Render komponen JadwalCard.jsx
    │ Tampilkan badge status: "Belum Konfirmasi" / "Dikonfirmasi ✓" / "Hadir" / "Tidak Hadir"
    │ Tampilkan tombol: "Konfirmasi Hadir" (jika assigned)
    │ Tampilkan tombol: "Minta Reschedule" (jika assigned/confirmed + tanggal belum lewat)
```

---

### 3.3 Data Sensitif

| Field | Alasan Sensitif | Perlakuan |
|-------|-----------------|-----------|
| `peserta_jadwal.token_konfirmasi` | Token unik untuk konfirmasi kehadiran — jika bocor, orang lain bisa konfirmasi atas nama peserta | Tidak pernah ditampilkan di response daftar admin; hanya dikirim via email langsung ke peserta. Di DB: VARCHAR(64) random. |
| `pendaftars.email` | Data pribadi; salah kirim berarti jadwal bocor ke orang lain | Tidak pernah di-expose ke public endpoint. Hanya digunakan backend untuk kirim email; tidak masuk response JSON publik. |
| `pendaftars.nomor_hp` | Data pribadi | Sama seperti email — tidak pernah masuk response JSON publik endpoint jadwal. |
| `notifikasi_log.error_message` | Bisa mengandung detail teknis SMTP (server, error code) | Hanya tampil di dashboard admin, tidak di respons publik. |

---

---

## BAGIAN 4 — ERD / Desain Database

---

### 4.1 Daftar Tabel

| Nama Tabel | Deskripsi |
|------------|-----------|
| `pendaftars` | **[EXISTING]** Data calon mahasiswa — tidak dimodifikasi |
| `users` | **[EXISTING]** Data admin — tidak dimodifikasi |
| `jadwal_tes` | Slot jadwal tes: waktu, lokasi, kapasitas, status lifecycle |
| `peserta_jadwal` | Relasi M:M antara pendaftar dan jadwal; menyimpan status kehadiran per peserta |
| `reschedule_requests` | Request perubahan jadwal dari peserta, beserta status approval admin |
| `notifikasi_log` | Audit trail pengiriman email notifikasi per peserta jadwal |

---

### 4.2 Struktur Tiap Tabel

#### Tabel: `jadwal_tes`

| Nama Kolom | Tipe Data | Constraint | Keterangan |
|------------|-----------|------------|------------|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `kode_jadwal` | VARCHAR(20) | UNIQUE, NOT NULL | Format: `JDW-2026-0001`, generate di app layer |
| `judul` | VARCHAR(200) | NOT NULL | Contoh: "Tes Seleksi Gelombang 1 - TI" |
| `tipe` | ENUM | NOT NULL | Nilai: `tes_tertulis`, `wawancara`, `tes_praktik` |
| `tanggal` | DATE | NOT NULL | Tanggal pelaksanaan |
| `jam_mulai` | TIME | NOT NULL | |
| `jam_selesai` | TIME | NOT NULL | Harus > jam_mulai (validasi di app layer) |
| `lokasi` | VARCHAR(150) | NOT NULL | Contoh: "Gedung A - Ruang 101" |
| `kapasitas` | INT UNSIGNED | NOT NULL | Hard limit jumlah peserta per slot |
| `prodi_filter` | VARCHAR(50) | NULLABLE | Jika NULL, semua prodi eligible |
| `jalur_filter` | VARCHAR(20) | NULLABLE | Jika NULL, semua jalur eligible |
| `status` | ENUM | NOT NULL, DEFAULT 'draft' | Nilai: `draft`, `published`, `selesai`, `dibatalkan` |
| `catatan` | TEXT | NULLABLE | Catatan opsional untuk admin |
| `created_by` | BIGINT UNSIGNED | FK → users.id, NOT NULL | Admin yang membuat |
| `published_at` | TIMESTAMP | NULLABLE | Diisi otomatis saat publish |
| `created_at` | TIMESTAMP | AUTO | |
| `updated_at` | TIMESTAMP | AUTO | |

#### Tabel: `peserta_jadwal`

| Nama Kolom | Tipe Data | Constraint | Keterangan |
|------------|-----------|------------|------------|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `jadwal_tes_id` | BIGINT UNSIGNED | FK → jadwal_tes.id, NOT NULL | |
| `pendaftar_id` | BIGINT UNSIGNED | FK → pendaftars.id, NOT NULL | |
| `status` | ENUM | NOT NULL, DEFAULT 'assigned' | Nilai: `assigned`, `confirmed`, `hadir`, `tidak_hadir`, `reschedule_diminta` |
| `token_konfirmasi` | VARCHAR(64) | UNIQUE, NULLABLE | Random token untuk link konfirmasi email |
| `notifikasi_dikirim` | BOOLEAN | NOT NULL, DEFAULT FALSE | Flag apakah email notifikasi sudah terkirim |
| `notifikasi_dikirim_at` | TIMESTAMP | NULLABLE | |
| `assigned_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Kapan peserta di-assign ke slot ini |
| `confirmed_at` | TIMESTAMP | NULLABLE | Kapan peserta klik konfirmasi |
| `catatan` | TEXT | NULLABLE | Catatan admin (contoh: "Peserta VIP, prioritas") |
| `created_at` | TIMESTAMP | AUTO | |
| `updated_at` | TIMESTAMP | AUTO | |

> **Constraint tambahan:** `UNIQUE(jadwal_tes_id, pendaftar_id)` — satu pendaftar tidak bisa muncul dua kali di slot yang sama.

#### Tabel: `reschedule_requests`

| Nama Kolom | Tipe Data | Constraint | Keterangan |
|------------|-----------|------------|------------|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `peserta_jadwal_id` | BIGINT UNSIGNED | FK → peserta_jadwal.id, NOT NULL | Jadwal lama yang ingin diubah |
| `alasan` | TEXT | NOT NULL | Alasan peserta minta reschedule |
| `jadwal_baru_id` | BIGINT UNSIGNED | FK → jadwal_tes.id, NULLABLE | Diisi admin saat approve |
| `status` | ENUM | NOT NULL, DEFAULT 'pending' | Nilai: `pending`, `disetujui`, `ditolak` |
| `catatan_admin` | TEXT | NULLABLE | Alasan jika ditolak, atau catatan tambahan |
| `diproses_oleh` | BIGINT UNSIGNED | FK → users.id, NULLABLE | Admin yang memproses |
| `diproses_at` | TIMESTAMP | NULLABLE | Kapan request diproses |
| `created_at` | TIMESTAMP | AUTO | |
| `updated_at` | TIMESTAMP | AUTO | |

#### Tabel: `notifikasi_log`

| Nama Kolom | Tipe Data | Constraint | Keterangan |
|------------|-----------|------------|------------|
| `id` | BIGINT UNSIGNED | PK, AUTO_INCREMENT | |
| `peserta_jadwal_id` | BIGINT UNSIGNED | FK → peserta_jadwal.id, NOT NULL | |
| `channel` | ENUM | NOT NULL | Nilai: `email`, `in_app` (extensible ke whatsapp) |
| `subjek` | VARCHAR(200) | NULLABLE | Subject email |
| `pesan` | TEXT | NOT NULL | Preview isi pesan |
| `status` | ENUM | NOT NULL, DEFAULT 'pending' | Nilai: `pending`, `terkirim`, `gagal` |
| `error_message` | TEXT | NULLABLE | Detail error jika gagal |
| `dikirim_at` | TIMESTAMP | NULLABLE | |
| `created_at` | TIMESTAMP | AUTO | |
| `updated_at` | TIMESTAMP | AUTO | |

---

### 4.3 Relasi Antar Tabel

```
[pendaftars] ---(one-to-many)--- [peserta_jadwal]
Keterangan: Satu pendaftar bisa memiliki beberapa entri peserta_jadwal (untuk tipe tes berbeda,
            atau setelah reschedule disetujui jadwal lama non-aktif dan jadwal baru dibuat).
            FK: peserta_jadwal.pendaftar_id → pendaftars.id

[jadwal_tes] ---(one-to-many)--- [peserta_jadwal]
Keterangan: Satu slot jadwal menampung banyak peserta, dibatasi oleh kolom kapasitas.
            FK: peserta_jadwal.jadwal_tes_id → jadwal_tes.id

[peserta_jadwal] ---(one-to-many)--- [reschedule_requests]
Keterangan: Satu entri peserta_jadwal bisa memiliki lebih dari satu request reschedule
            (misalnya pertama ditolak, lalu request lagi dengan alasan berbeda).
            FK: reschedule_requests.peserta_jadwal_id → peserta_jadwal.id

[jadwal_tes] ---(one-to-many, via FK jadwal_baru_id)--- [reschedule_requests]
Keterangan: Saat approve reschedule, admin memilih jadwal tujuan yang valid.
            FK: reschedule_requests.jadwal_baru_id → jadwal_tes.id (NULLABLE)

[users] ---(one-to-many)--- [jadwal_tes]
Keterangan: Setiap jadwal tercatat siapa admin yang membuatnya untuk audit trail.
            FK: jadwal_tes.created_by → users.id

[users] ---(one-to-many)--- [reschedule_requests]
Keterangan: Setiap keputusan approve/reject tercatat siapa admin yang memproses.
            FK: reschedule_requests.diproses_oleh → users.id (NULLABLE)

[peserta_jadwal] ---(one-to-many)--- [notifikasi_log]
Keterangan: Setiap pengiriman notifikasi (termasuk resend) dicatat untuk audit.
            FK: notifikasi_log.peserta_jadwal_id → peserta_jadwal.id
```

**Diagram ringkas:**

```
users ─────────────────────┐
  │                        │ created_by
  │ diproses_oleh          ▼
  ▼                   jadwal_tes
reschedule_requests ◄──────┤
  │  jadwal_baru_id        │ one-to-many
  │                        ▼
  └──────────────► peserta_jadwal ◄──── pendaftars
                        │
                   one-to-many
                        │
                        ▼
                  notifikasi_log
```

---

### 4.4 Indexing

| Tabel | Kolom yang Di-index | Alasan |
|-------|---------------------|--------|
| `jadwal_tes` | `status` | Query paling sering: `WHERE status = 'published'` untuk public endpoint |
| `jadwal_tes` | `tanggal` | Filter jadwal berdasarkan rentang tanggal di dashboard admin |
| `jadwal_tes` | `kode_jadwal` | UNIQUE constraint sudah membuat index otomatis |
| `peserta_jadwal` | `pendaftar_id` | Query utama: cari jadwal milik pendaftar tertentu (`WHERE pendaftar_id = ?`) |
| `peserta_jadwal` | `jadwal_tes_id` | Query: hitung peserta per slot (`WHERE jadwal_tes_id = ?`) |
| `peserta_jadwal` | `status` | Filter dashboard admin: berapa yang sudah confirmed, hadir, dll. |
| `peserta_jadwal` | `token_konfirmasi` | UNIQUE constraint sudah membuat index; dipakai di link konfirmasi |
| `peserta_jadwal` | `(jadwal_tes_id, pendaftar_id)` | UNIQUE constraint, mencegah double-assign |
| `reschedule_requests` | `status` | Filter: `WHERE status = 'pending'` untuk list request yang perlu diproses admin |
| `reschedule_requests` | `peserta_jadwal_id` | Join untuk tampilkan detail request |
| `notifikasi_log` | `status` | Filter: temukan yang `gagal` untuk resend |
| `notifikasi_log` | `peserta_jadwal_id` | JOIN untuk tampilkan log notifikasi per peserta |

---

### 4.5 API Contract (Tambahan)

#### Admin Endpoints — Protected by `auth:sanctum`

```
# Kelola Jadwal
GET    /api/admin/jadwal
       Response: [{id, kode_jadwal, judul, tipe, tanggal, jam_mulai, jam_selesai,
                   lokasi, kapasitas, status, total_peserta, total_confirmed}]

POST   /api/admin/jadwal
       Body: {judul, tipe, tanggal, jam_mulai, jam_selesai, lokasi, kapasitas,
              prodi_filter?, jalur_filter?, catatan?}
       Response: {id, kode_jadwal, ...}

PUT    /api/admin/jadwal/{id}
       Body: (sama seperti POST, hanya boleh jika status = 'draft')
       Response: {updated jadwal}

POST   /api/admin/jadwal/{id}/publish
       Response: {message: "Jadwal dipublish, notifikasi dikirim ke X peserta"}

DELETE /api/admin/jadwal/{id}
       Response: 200 (hanya jika draft atau belum ada peserta assigned)

# Kelola Peserta di Jadwal
GET    /api/admin/jadwal/{id}/peserta
       Response: [{peserta_jadwal.id, pendaftar: {nama, nomor_pendaftaran, prodi, jalur},
                   status, notifikasi_dikirim, confirmed_at}]

POST   /api/admin/jadwal/{id}/assign
       Body: {pendaftar_ids: [1, 2, 3]}
       Response: {assigned: 3, skipped: [{id, alasan}]}

POST   /api/admin/jadwal/{id}/assign-auto
       Response: {assigned: X, skipped: Y, message: "..."}

DELETE /api/admin/jadwal/{jadwal_id}/peserta/{peserta_jadwal_id}
       Response: 200

PATCH  /api/admin/jadwal/{jadwal_id}/peserta/{peserta_jadwal_id}/kehadiran
       Body: {status: "hadir" | "tidak_hadir"}
       Response: {updated peserta_jadwal}

POST   /api/admin/jadwal/{id}/kirim-notifikasi
       Response: {terkirim: X, gagal: Y}

# Kelola Reschedule
GET    /api/admin/reschedule
       Query: ?status=pending|disetujui|ditolak
       Response: [{id, peserta_jadwal: {...}, jadwal_lama: {...}, alasan, status}]

PATCH  /api/admin/reschedule/{id}
       Body: {status: "disetujui"|"ditolak", jadwal_baru_id?: int, catatan_admin?: string}
       Response: {updated reschedule_request}
```

#### Public Endpoints — Auth via `nomor_pendaftaran`

```
GET    /api/jadwal/{nomor_pendaftaran}
       Response: {has_jadwal: bool, jadwal?: {judul, tipe, tanggal, jam_mulai,
                  jam_selesai, lokasi, status_peserta, has_reschedule_pending}}

POST   /api/jadwal/{nomor_pendaftaran}/konfirmasi
       Body: {token_konfirmasi: string}
       Response: {message: "Kehadiran dikonfirmasi"}

POST   /api/jadwal/{nomor_pendaftaran}/reschedule
       Body: {alasan: string}
       Response: {message: "Request reschedule berhasil dikirim"}
```

---

---

## BAGIAN 5 — Prompt Siap Pakai untuk AI

```
[KONTEKS]
Saya sedang mengembangkan lanjutan dari sistem PMB (Penerimaan Mahasiswa Baru)
yang sudah berjalan. Stack: React 18 + Tailwind CSS (frontend, Vite), Laravel 12
+ SQLite (backend). Sistem ini sudah memiliki fitur: pendaftaran online, generate
nomor pendaftaran otomatis (format PMB-YYYY-XXXX), cek status by nomor pendaftar,
dashboard admin dengan statistik, update status pendaftar (Menunggu/Lolos Seleksi/
Tidak Lolos), login admin via Laravel Sanctum token, export CSV, dan tombol
heregistrasi. Tabel existing: `pendaftars` (id, nomor_pendaftaran, nama, nomor_hp,
email, asal_sekolah, prodi, jalur, status, heregistrasi_at) dan `users` (admin).
Konvensi kode: snake_case untuk field API, functional components + hooks di React,
Tailwind utility classes, tidak ada UI library tambahan selain Tailwind.

[TUJUAN]
Tambahkan modul penjadwalan tes seleksi ke sistem yang sudah ada. Modul ini
memungkinkan admin membuat slot jadwal, meng-assign peserta Lolos Seleksi ke slot
tersebut, mengirim notifikasi email otomatis saat jadwal dipublish, dan memantau
kehadiran. Peserta dapat melihat jadwal mereka di halaman CekStatus yang sudah ada
(tanpa login baru), mengkonfirmasi kehadiran, dan mengajukan reschedule. JANGAN
membangun dari nol — gunakan komponen, routes, middleware Sanctum, dan konvensi
yang sudah ada.

[FITUR]
Backend (Laravel):
1. Migration baru: jadwal_tes, peserta_jadwal, reschedule_requests, notifikasi_log
   (skema terlampir — jangan ubah tabel pendaftars dan users)
2. JadwalController (admin): CRUD jadwal, publish (trigger email queue),
   auto-assign & manual-assign peserta, mark kehadiran, resend notifikasi
3. JadwalPublikController: GET jadwal by nomor_pendaftaran, POST konfirmasi,
   POST reschedule request
4. RescheduleController (admin): list request, approve/reject
5. Mailable: JadwalTesMail (berisi detail jadwal + link konfirmasi)
6. Job: KirimNotifikasiJadwal (queue: database driver, sudah dikonfigurasi di .env)
7. Validasi kapasitas: tolak assign jika slot penuh (count >= kapasitas)
8. Routes baru ditambahkan ke routes/api.php existing, bukan file baru

Frontend (React):
1. JadwalManagement.jsx (page admin): kalender slot + tabel peserta per slot +
   tombol publish/assign/mark kehadiran
2. RescheduleManagement.jsx (page admin): list request pending dengan approve/reject
3. JadwalCard.jsx (komponen): kartu jadwal di halaman CekStatus yang sudah ada,
   tampilkan status badge, tombol konfirmasi, tombol minta reschedule
4. Perbarui CekStatus.jsx: tambahkan GET /api/jadwal/{nomor} dan render JadwalCard
5. Perbarui Admin.jsx: tambahkan menu navigasi ke JadwalManagement dan RescheduleManagement
6. Perbarui api.js: tambahkan fungsi jadwalApi, rescheduleApi (jangan ubah yang lama)

[CONSTRAINT]
- Tabel baru: jadwal_tes (id, kode_jadwal UNIQUE, judul, tipe ENUM[tes_tertulis/
  wawancara/tes_praktik], tanggal DATE, jam_mulai TIME, jam_selesai TIME, lokasi,
  kapasitas INT, prodi_filter NULLABLE, jalur_filter NULLABLE, status ENUM[draft/
  published/selesai/dibatalkan] DEFAULT draft, created_by FK users.id, published_at)
- Tabel baru: peserta_jadwal (id, jadwal_tes_id FK, pendaftar_id FK, status ENUM
  [assigned/confirmed/hadir/tidak_hadir/reschedule_diminta] DEFAULT assigned,
  token_konfirmasi VARCHAR(64) UNIQUE NULLABLE, notifikasi_dikirim BOOL DEFAULT 0,
  assigned_at, confirmed_at, UNIQUE(jadwal_tes_id, pendaftar_id))
- Tabel baru: reschedule_requests (id, peserta_jadwal_id FK, alasan TEXT, jadwal_baru_id
  FK NULLABLE, status ENUM[pending/disetujui/ditolak] DEFAULT pending, catatan_admin,
  diproses_oleh FK users.id NULLABLE, diproses_at)
- Tabel baru: notifikasi_log (id, peserta_jadwal_id FK, channel ENUM[email/in_app],
  subjek, pesan TEXT, status ENUM[pending/terkirim/gagal] DEFAULT pending, error_message,
  dikirim_at)
- Endpoint admin: semua di bawah middleware auth:sanctum (ikuti pola existing)
- Endpoint publik: GET|POST /api/jadwal/{nomor_pendaftaran}/... tidak butuh token
- Auto-assign hanya untuk pendaftar dengan status = 'Lolos Seleksi' (ikuti konstanta
  Pendaftar::STATUS_LOLOS di model existing)
- Queue: gunakan QUEUE_CONNECTION=database (sudah ada tabel jobs dari setup awal)
- Jangan install package tambahan backend. Gunakan Laravel Mail, Queue, dan Str yang
  sudah tersedia
- Frontend: gunakan fetch di api.js (bukan axios); token dari sessionStorage dengan key
  yang sama seperti kode existing; tambah @fullcalendar/react dan react-datepicker
  sebagai satu-satunya dependency frontend baru

[TAMPILAN]
- Ikuti design language yang sudah ada: warna biru (blue-600/700) untuk aksi utama,
  Tailwind utility classes, card dengan rounded-xl shadow, tabel dengan hover states
- StatusBadge pattern sudah ada: buat JadwalStatusBadge dengan pola sama
  (draft=gray, published=blue, selesai=green, dibatalkan=red)
- PesertaStatusBadge: assigned=yellow, confirmed=blue, hadir=green, tidak_hadir=red
- JadwalCard di CekStatus: kartu biru muda dengan ikon kalender, tampilkan semua info
  jadwal, tombol "Konfirmasi Hadir" (primary button, hilang jika sudah confirmed),
  tombol "Minta Reschedule" (secondary/outline button, hilang jika jadwal sudah lewat)
- Dashboard jadwal admin: tabel utama + kolom progress bar kapasitas (X/Y peserta),
  badge status, aksi per baris (Publish, Lihat Peserta, Hapus)
- Form buat jadwal: modal/drawer dengan validasi inline, date picker terintegrasi
- Mobile-first: JadwalCard harus tetap readable di layar 375px
- Tidak ada dark mode (konsisten dengan sistem existing yang tidak mengimplementasikan)
```

---

---

## BAGIAN 6 — Jalankan Prompt & Evaluasi Hasil

> *Bagian ini diisi setelah prompt Bagian 5 dijalankan ke AI dan hasilnya diimplementasikan.*

---

### 6.1 Log Prompt & Iterasi

#### Prompt Utama
*(Kirim prompt Bagian 5 ke Claude/Cursor)*

#### Iterasi 1 — *(isi alasan iterasi diperlukan)*
```
[Prompt iterasi 1]
```

#### Iterasi 2 — *(isi alasan iterasi diperlukan)*
```
[Prompt iterasi 2]
```

---

### 6.2 Tabel Evaluasi Kesesuaian

| Poin Plan | Fitur / Flow | Status | Catatan |
|-----------|--------------|--------|---------|
| **1.2 Admin** | Buat slot jadwal | ⬜ Belum diuji | |
| **1.2 Admin** | Publish + kirim email | ⬜ Belum diuji | |
| **1.2 Admin** | Auto-assign peserta Lolos | ⬜ Belum diuji | |
| **1.2 Admin** | Manual assign | ⬜ Belum diuji | |
| **1.2 Admin** | Dashboard per slot (confirmed/hadir) | ⬜ Belum diuji | |
| **1.2 Admin** | Kelola reschedule request | ⬜ Belum diuji | |
| **1.2 Admin** | Mark kehadiran hari H | ⬜ Belum diuji | |
| **1.2 Peserta** | Cek jadwal di CekStatus | ⬜ Belum diuji | |
| **1.2 Peserta** | Konfirmasi kehadiran | ⬜ Belum diuji | |
| **1.2 Peserta** | Ajukan reschedule | ⬜ Belum diuji | |
| **1.2 Peserta** | Terima email notifikasi | ⬜ Belum diuji | |
| **2.1 Flow** | Hard limit kapasitas saat assign | ⬜ Belum diuji | |
| **2.3 Error** | Email gagal → notif gagal di dashboard | ⬜ Belum diuji | |
| **4.x DB** | Migrasi jadwal_tes | ⬜ Belum diuji | |
| **4.x DB** | Migrasi peserta_jadwal | ⬜ Belum diuji | |
| **4.x DB** | Migrasi reschedule_requests | ⬜ Belum diuji | |
| **4.x DB** | Migrasi notifikasi_log | ⬜ Belum diuji | |
| **Regresi** | Form pendaftaran masih berjalan | ⬜ Belum diuji | |
| **Regresi** | Cek status (non-jadwal) masih berjalan | ⬜ Belum diuji | |
| **Regresi** | Dashboard admin lama masih berjalan | ⬜ Belum diuji | |
| **Regresi** | Login Sanctum masih berjalan | ⬜ Belum diuji | |
| **Regresi** | Export CSV masih berjalan | ⬜ Belum diuji | |
| **Regresi** | Heregistrasi masih berjalan | ⬜ Belum diuji | |

*Status: ✅ Sesuai plan | ⚠️ Sebagian sesuai | ❌ Tidak sesuai | ⬜ Belum diuji*

---

### 6.3 Kesimpulan Evaluasi

*(Isi setelah implementasi dan pengujian selesai)*

**Perbedaan spesifik antara plan dan hasil:**
- ...

**Mengapa perbedaan itu terjadi:**
- ...

**Konfirmasi regresi fitur lama:**
- [ ] Tidak ada regresi ditemukan
- [ ] Ada regresi: *(sebutkan)*

---

*File ini dibuat sebagai Development Plan untuk event Vibe Coding & Venture — SEVIMA.*
*Last updated: 2026-06-13*
