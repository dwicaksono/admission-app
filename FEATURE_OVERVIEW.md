# Penjelasan Fitur Sistem PMB
## Prototype Penerimaan Mahasiswa Baru — SEVIMA Vibe Coding

---

## Sistem Lama (Fase 1–3)

Sistem awal dibangun untuk menangani proses pendaftaran mahasiswa baru dari awal hingga pengumuman hasil seleksi.

### Alur Sistem Lama

```
Calon Mahasiswa           Admin PMB
      │                      │
      ▼                      │
 Isi Formulir               │
 Pendaftaran ────────────►  Terima data
      │                      │
      │                      ▼
      │                 Dashboard Admin
      │                 (lihat semua pendaftar)
      │                      │
      │                      ▼
      │                 Ubah Status:
      │                 • Menunggu
      │                 • Lolos Seleksi
      │                 • Tidak Lolos
      │                      │
      ▼                      │
 Cek Status ◄─────────────────┘
      │
      ▼ (jika Lolos Seleksi)
 Tombol Heregistrasi
```

### Fitur yang Ada

| Fitur | Deskripsi | Teknologi |
|-------|-----------|-----------|
| **Formulir Pendaftaran** | Calon mahasiswa mengisi data diri, pilih prodi & jalur masuk | React form → Laravel API |
| **Nomor Pendaftaran** | Auto-generate format `PMB-2025-XXXX`, unik per pendaftar | Generate di backend |
| **Cek Status** | Calon mahasiswa cek status pendaftaran via nomor daftar | Public API endpoint |
| **Login Admin** | Autentikasi admin menggunakan Laravel Sanctum + token | `sessionStorage` |
| **Dashboard Admin** | Lihat semua pendaftar, filter real-time, ubah status | React tabel |
| **Statistik** | Jumlah pendaftar per prodi dan per jalur (progress bar) | Dedicated API |
| **Export CSV** | Download data pendaftar lengkap (protected endpoint) | Blob download |
| **Heregistrasi** | Mahasiswa lolos seleksi konfirmasi kehadiran via tombol | PATCH endpoint |

### Keterbatasan Sistem Lama

- Tidak ada mekanisme penjadwalan tes seleksi
- Admin harus menghubungi peserta secara manual untuk info tes
- Tidak ada notifikasi otomatis ke calon mahasiswa
- Tidak ada cara bagi peserta untuk meminta perubahan jadwal

---

## Sistem Baru — Modul Penjadwalan Tes (Fase 4)

Modul baru menambahkan seluruh alur penjadwalan tes seleksi: dari pembuatan slot jadwal oleh admin, penugasan peserta secara otomatis, pengiriman notifikasi email, konfirmasi kehadiran oleh peserta, hingga penanganan permintaan reschedule.

### Alur Sistem Baru

```
Admin                           Sistem                    Peserta
  │                               │                          │
  ▼                               │                          │
Buat Jadwal Tes                   │                          │
(judul, tipe, tanggal,            │                          │
 jam, lokasi, kapasitas)          │                          │
  │                               │                          │
  ▼                               │                          │
Auto-Assign Peserta               │                          │
(ambil semua 'Lolos Seleksi',     │                          │
 filter prodi/jalur, isi slot)    │                          │
  │                               │                          │
  ▼                               │                          │
Publish Jadwal ──────────────► Generate token               │
                               konfirmasi (64 char)         │
                               + Kirim Email ──────────────►│
                                  │                     Terima email
                                  │                     berisi detail
                                  │                     jadwal & token
                                  │                          │
                                  │                          ▼
                                  │                     Buka halaman
                                  │                     Cek Status
                                  │                          │
                                  │                          ▼
                                  │                     Lihat Jadwal Tes
                                  │                     (judul, tanggal,
                                  │                      jam, lokasi)
                                  │                          │
                                  │              ┌───────────┴───────────┐
                                  │              ▼                       ▼
                                  │       Konfirmasi Kehadiran    Minta Reschedule
                                  │       (input token dari email) (isi alasan)
                                  │              │                       │
                                  │              ▼                       ▼
                                  │       Status: confirmed        Status: reschedule_diminta
                                  │                                       │
  │                               │                                       ▼
  ▼                               │                               Admin lihat request
Catat Kehadiran                   │                               (tab Reschedule)
(hadir / tidak hadir)             │                                       │
  │                               │                         ┌─────────────┴─────────────┐
  │                               │                         ▼                           ▼
  │                               │                    Setujui                      Tolak
  │                               │                    (pilih jadwal baru)          (isi alasan)
  │                               │                         │                           │
  │                               │                         ▼                           ▼
  │                               │               Peserta dipindah ke           Peserta kembali
  │                               │               jadwal baru + email baru      ke jadwal semula
```

---

### Fitur Baru: Admin

#### 1. Manajemen Jadwal Tes
- **Buat Jadwal** — Form dengan field: judul, jenis tes (Tertulis/Wawancara/Praktik), tanggal, jam mulai & selesai, lokasi, kapasitas, filter prodi/jalur
- **Kode Jadwal Otomatis** — Format `JDW-2025-XXXX`, unik per tahun
- **Status Jadwal** — State machine: `Draft → Published → Selesai/Dibatalkan`
- **Tabel Jadwal** — Tampil kode, judul, tipe, tanggal, progress bar kapasitas (terisi/total), status badge
- **Hapus Jadwal** — Hanya bisa jika status masih Draft dan belum ada peserta

#### 2. Pengelolaan Peserta
- **Auto-Assign** — Satu klik: sistem otomatis ambil pendaftar `Lolos Seleksi` yang belum dijadwalkan, filter sesuai prodi/jalur jadwal, isi sampai kapasitas penuh
- **Tabel Peserta** — Nama, nomor pendaftaran, prodi, status kehadiran, status pengiriman notifikasi
- **Catat Kehadiran** — Tombol Hadir / Tidak Hadir per peserta (aktif setelah jadwal published)
- **Hapus Peserta** — Hanya bisa saat status jadwal masih Draft

#### 3. Publish & Notifikasi Email
- **Publish** — Mengubah status Draft → Published, generate token konfirmasi 64 karakter (unik per peserta), kirim email otomatis via queue job
- **Kirim Ulang Notifikasi** — Kirim ulang hanya ke peserta yang belum menerima email
- **Queue dengan Retry** — Email dikirim via background job, otomatis dicoba ulang 3 kali jika gagal (backoff 30 dan 60 detik)
- **Log Notifikasi** — Setiap pengiriman email dicatat (pending → terkirim/gagal + pesan error)

#### 4. Manajemen Reschedule
- **Tab Filter** — Lihat request berdasarkan status: Menunggu / Disetujui / Ditolak / Semua
- **Tabel Request** — Info peserta, jadwal lama, alasan, status, tanggal permintaan
- **Modal Proses** — Admin pilih jadwal baru (hanya jadwal Published + sisa kapasitas), isi catatan, klik Setujui atau Tolak
- **Setujui** — Peserta dipindah ke jadwal baru (slot lama dihapus, slot baru dibuat, email notifikasi jadwal baru dikirim)
- **Tolak** — Peserta dikembalikan ke jadwal semula dengan status assigned, catatan penolakan tersimpan

---

### Fitur Baru: Peserta (Publik)

#### Di halaman Cek Status (setelah input nomor pendaftaran)
- **Kartu Jadwal Tes** — Muncul di bawah kartu status jika jadwal sudah di-publish. Menampilkan:
  - Jenis tes, tanggal lengkap (hari + tanggal), waktu, lokasi
  - Badge status peserta (Belum Konfirmasi / Dikonfirmasi / Hadir / Tidak Hadir / Minta Reschedule)

- **Konfirmasi Kehadiran** — Tombol muncul jika status `assigned`. Peserta input token dari email → sistem validasi → status berubah ke `confirmed`

- **Minta Reschedule** — Tombol muncul jika status `assigned`/`confirmed` dan jadwal belum lewat dan belum ada request pending. Peserta isi alasan (min 10 karakter) → admin akan memproses

---

### Struktur Database Baru

```
jadwal_tes
├── kode_jadwal (unique)
├── judul, tipe, tanggal, jam_mulai, jam_selesai
├── lokasi, kapasitas
├── prodi_filter, jalur_filter (nullable)
├── status (draft/published/selesai/dibatalkan)
└── created_by → users.id

peserta_jadwal
├── jadwal_tes_id → jadwal_tes.id
├── pendaftar_id → pendaftars.id
├── status (assigned/confirmed/hadir/tidak_hadir/reschedule_diminta)
├── token_konfirmasi (unique, 64 char)
├── notifikasi_dikirim, notifikasi_dikirim_at
└── UNIQUE(jadwal_tes_id, pendaftar_id)

reschedule_requests
├── peserta_jadwal_id → peserta_jadwal.id
├── alasan (text)
├── jadwal_baru_id → jadwal_tes.id (nullable)
├── status (pending/disetujui/ditolak)
└── diproses_oleh → users.id

notifikasi_log
├── peserta_jadwal_id → peserta_jadwal.id
├── channel (email/in_app)
├── subjek, pesan
├── status (pending/terkirim/gagal)
└── error_message, dikirim_at
```

---

### Endpoint API Baru

#### Admin (protected — Bearer token Sanctum)

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/admin/jadwal` | Daftar semua jadwal + jumlah peserta |
| POST | `/api/admin/jadwal` | Buat jadwal baru |
| GET | `/api/admin/jadwal/{id}` | Detail jadwal |
| PUT | `/api/admin/jadwal/{id}` | Update jadwal (hanya draft) |
| DELETE | `/api/admin/jadwal/{id}` | Hapus jadwal (hanya draft, tanpa peserta) |
| POST | `/api/admin/jadwal/{id}/publish` | Publish + kirim notifikasi |
| POST | `/api/admin/jadwal/{id}/assign-auto` | Auto-assign peserta Lolos Seleksi |
| POST | `/api/admin/jadwal/{id}/assign` | Manual assign by ID |
| GET | `/api/admin/jadwal/{id}/peserta` | Daftar peserta jadwal |
| DELETE | `/api/admin/jadwal/{jadwalId}/peserta/{pjId}` | Hapus peserta dari jadwal |
| PATCH | `/api/admin/jadwal/{jadwalId}/peserta/{pjId}/kehadiran` | Catat hadir/tidak hadir |
| POST | `/api/admin/jadwal/{id}/kirim-notifikasi` | Kirim ulang notifikasi |
| GET | `/api/admin/reschedule` | Daftar request reschedule |
| PATCH | `/api/admin/reschedule/{id}` | Proses reschedule (setujui/tolak) |

#### Publik (tanpa auth, dengan rate limiting)

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/jadwal/{nomorPendaftaran}` | Lihat jadwal peserta |
| POST | `/api/jadwal/{nomorPendaftaran}/konfirmasi` | Konfirmasi kehadiran via token |
| POST | `/api/jadwal/{nomorPendaftaran}/reschedule` | Ajukan permintaan reschedule |

---

### Keamanan yang Diterapkan

| Aspek | Sistem Lama | Sistem Baru |
|-------|-------------|-------------|
| Auth admin | Sanctum token | Sanctum token (sama) |
| Token storage | `sessionStorage` | `sessionStorage` (sama) |
| Rate limiting | Tidak ada | Login: 10/menit, public: 60/menit, reschedule: 5/menit |
| Email token | — | `Str::random(64)`, unique constraint di DB |
| IDOR protection | Nomor pendaftaran di URL | Same + token konfirmasi terpisah dari data publik |
| Mass assignment | `$fillable` di semua model | `$fillable` di semua model baru |
| SQL injection | Eloquent ORM | Eloquent ORM (semua query parameterized) |
| Input validation | Form Request | Form Request + after:jam_mulai + enum checks |
| Job retry | — | 3 kali retry, backoff 30s dan 60s |
| Kapasitas enforcement | — | Accessor `sisa_kapasitas` + validasi sebelum assign |

---

### Ringkasan Perbandingan

| | Sistem Lama | Sistem Baru |
|--|-------------|-------------|
| **Pendaftaran** | ✅ | ✅ |
| **Cek status** | ✅ | ✅ (+ tampil jadwal tes) |
| **Heregistrasi** | ✅ | ✅ |
| **Dashboard admin** | ✅ | ✅ (+ tab Jadwal & Reschedule) |
| **Statistik & CSV** | ✅ | ✅ |
| **Buat jadwal tes** | ❌ | ✅ |
| **Auto-assign peserta** | ❌ | ✅ |
| **Notifikasi email** | ❌ | ✅ (queue + retry) |
| **Konfirmasi kehadiran via token** | ❌ | ✅ |
| **Minta reschedule** | ❌ | ✅ |
| **Proses reschedule (admin)** | ❌ | ✅ |
| **Catat kehadiran (admin)** | ❌ | ✅ |
| **Log notifikasi** | ❌ | ✅ |
| **Rate limiting** | ❌ | ✅ |
