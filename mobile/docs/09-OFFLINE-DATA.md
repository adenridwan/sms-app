# 09 — Konsep Data Offline (snapshot master + sinkron delta)

> Status: **konsep, belum diimplementasikan.** Angka di sini diukur dari
> `sms_demo` (540 siswa, 18 kelas, 20 guru) pada 2026-08-25.

---

## 1. Masalahnya sekarang

Aplikasi sudah tahan mati-server untuk **transaksi**: sesi bertahan, absensi
masuk antrean, dan terkirim sendiri begitu server hidup
([09-ERROR-HANDLING.md §6a](../../docs/09-ERROR-HANDLING.md)).

Yang belum: **data rujukan**. Semuanya masih diambil saat dibutuhkan, jadi tanpa
server:

| Yang dicoba petugas | Yang terjadi sekarang |
|---|---|
| Ketik Ref ID lalu **Periksa** | gagal — identitas tak bisa dipratinjau, hanya bisa disimpan buta |
| Buka **Checklist** | daftar kelas kosong; daftar siswa hanya bisa dimuat kalau sempat online |
| Pindai QR | kode tak bisa divalidasi; benar/salah baru ketahuan saat sinkron |
| **Absensi Saya** | QR & riwayat kosong |
| Beranda | angka ringkasan jadi `—` |

Akibat terburuknya bukan tampilan kosong, tapi ini: petugas mengetik kode yang
salah, aplikasi menerimanya karena tak bisa memeriksa, dan kesalahan itu baru
muncul berjam-jam kemudian sebagai kegagalan sinkron — saat siswanya sudah
pulang.

---

## 2. Dua kelas data, diperlakukan berbeda

| | **Master (rujukan)** | **Transaksi** |
|---|---|---|
| Contoh | siswa, `unique_code`, `rfid_code`, kelas, guru, pengaturan absensi | hasil pindai, ceklis kelas, verifikasi pembayaran |
| Arah | server → perangkat | perangkat → server |
| Frekuensi ubah | jarang (mingguan/semesteran) | terus-menerus |
| Kalau hilang | bisa diambil ulang | **hilang permanen** |
| Status | **belum ada** | sudah jalan (dua antrean + sinkron otomatis) |

Inilah yang Anda maksud: master ditarik **sekali di awal**, lalu diperbarui
**hanya saat diminta**; transaksi tetap seperti sekarang.

Pembedaan ini bukan sekadar rapi — keduanya butuh jaminan berbeda. Transaksi
tak boleh hilang, jadi antreannya konservatif (entri gagal tetap disimpan).
Master boleh basi sejenak, jadi boleh ditimpa habis tanpa takut kehilangan apa
pun.

---

## 3. Rancangan

### 3.1 Snapshot lokal

Satu berkas snapshot per jenis master, berisi **proyeksi ramping** — bukan
seluruh field dari API:

```
students:  id · nis · unique_code · rfid_code · nama · classroom_id · status
classrooms: id · nama · homeroom_teacher_id · is_active
teachers:  id · user_id · nip · unique_code · rfid_code · nama
settings:  jam masuk/pulang · radius geofence · hari libur (30 hari ke depan)
```

**Kenapa diramping** — diukur dari endpoint yang ada sekarang:

| | per siswa | 540 siswa | 5.000 siswa |
|---|---|---|---|
| Payload API apa adanya | ~1.187 B | **625 KB** | **5,7 MB** |
| Proyeksi ramping | ~200 B | **~105 KB** | **~1 MB** |

Selisihnya enam kali lipat. Untuk sekolah 5.000 siswa, itu beda antara sinkron
yang lewat begitu saja dan sinkron yang bikin petugas menunggu di gerbang.
Field seperti alamat, agama, foto, dan tempat lahir tak pernah dipakai layar
mana pun di aplikasi ini.

### 3.2 Sinkron delta, bukan tarik ulang

Tiap snapshot menyimpan `synced_at`. Sinkron berikutnya hanya meminta yang
berubah sejak itu:

```
GET /sync/students?updated_since=2026-08-25T14:00:00Z
→ { updated: [...], deleted: [id, ...], server_time: "..." }
```

Tiga hal yang membuat ini benar dan sering dilupakan:

1. **`deleted` wajib ada.** Tanpa daftar id yang dihapus, siswa yang sudah
   pindah sekolah akan tetap bisa diabsen di perangkat selamanya. Butuh
   *tombstone* di server (`deleted_at`, yang sudah ada karena SoftDeletes).
2. **Watermark memakai `server_time` dari respons**, bukan jam perangkat. Jam
   HP sering meleset; memakainya berarti melewatkan perubahan.
3. **Watermark disimpan hanya kalau seluruh batch berhasil ditulis.** Gagal di
   tengah lalu menyimpan watermark = perubahan itu hilang diam-diam,
   selamanya.

### 3.3 Kapan sinkron jalan

| Pemicu | Yang ditarik |
|---|---|
| Login online pertama di perangkat | **snapshot penuh** (sekali, dengan progres) |
| Tombol **Sinkronkan dengan Server API** di Pengaturan | delta master + kirim antrean transaksi |
| Server kembali hidup (`/ping` → `online`) | antrean transaksi saja — **bukan** master |
| Buka layar | tidak ada |

Master **tidak** ikut ditarik otomatis saat koneksi pulih. Alasannya: pemulihan
koneksi bisa terjadi puluhan kali sehari di gerbang sekolah, dan menarik master
tiap kali membuang kuota untuk data yang berubah sebulan sekali. Perubahan
master hampir selalu punya pemicu manusia ("ada siswa baru"), jadi tombol
manual lebih jujur — dan itu persis yang Anda usulkan.

### 3.4 Menunjukkan umur data

Setiap layar yang memakai snapshot menampilkan umurnya, dan Pengaturan
menampilkan per jenis:

```
Siswa      540 · disinkronkan 2 hari lalu
Kelas       18 · disinkronkan 2 hari lalu
Guru        20 · disinkronkan 2 hari lalu
```

Lewat 7 hari, tampilkan peringatan lembut. **Jangan** blokir pemakaian: data
basi tetap jauh lebih berguna daripada layar kosong, dan petugas di gerbang
tidak punya pilihan lain.

---

## 4. Tempat menyimpannya

Sekarang semua penyimpanan lokal memakai `SharedPreferences` (JSON string).
Untuk snapshot master itu **tidak cukup**:

| | SharedPreferences | SQLite (`sqflite`/`drift`) |
|---|---|---|
| Cara kerja | seluruh berkas dibaca & ditulis sekaligus | baca/tulis per baris |
| Cari kode saat pindai | urai 105 KB JSON tiap kali | indeks pada `unique_code`, O(log n) |
| 5.000 siswa | kemungkinan besar tersendat | wajar |
| Sinkron delta | tulis ulang seluruh berkas | `upsert` baris yang berubah saja |

**Rekomendasi: SQLite untuk snapshot master**, `SharedPreferences` tetap untuk
yang kecil (token, antrean, watermark). Pemindaian QR menuntut jawaban dalam
hitungan milidetik sambil antrean siswa memanjang; mengurai JSON penuh tiap
pindai tak akan sanggup.

---

## 5. Yang harus dikerjakan di backend

Ini bagian terbesarnya, dan **belum ada sama sekali** — tidak satu pun endpoint
mendukung filter waktu (sudah dicek di seluruh `app/Http/Controllers/Api/V1/`).

| Endpoint baru | Isi |
|---|---|
| `GET /sync/manifest` | jumlah baris + `updated_at` maksimum per jenis — agar aplikasi tahu ada perubahan **sebelum** menarik apa pun |
| `GET /sync/students?updated_since=` | proyeksi ramping + `deleted[]` + `server_time` |
| `GET /sync/classrooms?updated_since=` | idem |
| `GET /sync/teachers?updated_since=` | idem |
| `GET /sync/settings` | jam, geofence, hari libur — kecil, tarik utuh |

Semua discope `visibleTo($request->user())` seperti endpoint lain: guru hanya
menerima kelas yang ia ampu, bukan seluruh sekolah. Snapshot **tidak boleh**
memperluas akses hanya karena datanya disalin ke perangkat.

---

## 6. Yang dibuka setelah ini ada

- **Ref ID offline benar-benar berguna** — nama siswa muncul saat diketik, kode
  salah ditolak seketika, bukan berjam-jam kemudian.
- **Checklist offline penuh** — pilih kelas, daftar siswa lengkap, tandai,
  antre.
- **Pindai QR tervalidasi offline** — kartu asing ditolak di tempat.
- **Beranda** menampilkan angka terakhir yang diketahui, bukan `—`.

---

## 7. Risiko dan keputusan yang perlu Anda ambil

**a. Seluruh daftar siswa tersimpan di perangkat — KEPUTUSAN SUDAH DIAMBIL.**
Nama, NIS, dan kode QR satu sekolah ada di HP yang berpindah tangan. Snapshot
**tidak** dihapus saat logout (agar login offline tetap mungkin) dan **tidak**
dienkripsi. Sebagai gantinya: **kunci menganggur 30 detik**, sudah dibangun —
lihat §7a.1.

#### 7a.1 Kunci menganggur (terimplementasi 2026-08-25)

Aplikasi **tidak bisa** memaksa kunci layar OS jadi 30 detik: mengubah
`SCREEN_OFF_TIMEOUT` menuntut izin khusus, dan layar mati pun belum tentu
terkunci — masih ada jeda "lock after screen timeout" milik pengguna. Satu-
satunya batas yang bisa dijamin adalah batas milik aplikasi sendiri, jadi
kuncinya dipasang di dalam aplikasi
([app_lock_gate.dart](../lib/core/security/app_lock_gate.dart)).

| Aspek | Perilaku |
|---|---|
| Batas | `AppConfig.idleLockTimeout` = **30 detik** |
| Cakupan | dipasang lewat `MaterialApp.builder`, jadi **semua** rute tertutup — termasuk kamera pindai dan antrean yang berada di luar shell |
| Membuka | password akun, diverifikasi **lokal** (PBKDF2) — jalan tanpa server |
| Sesi | **tidak** diakhiri; antrean absensi tetap utuh |
| Latar belakang | waktu di latar ikut dihitung — HP di saku sama rawannya dengan di meja |
| Jalan kapan | hanya saat ada sesi; layar login tak dikunci |
| Jalan keluar | "Keluar dari akun ini", supaya tak ada yang terjebak |

**Memindai dihitung sebagai aktivitas.** Petugas gerbang memindai tanpa
menyentuh layar; tanpa perlakuan khusus ia akan terkunci tiap 30 detik justru
saat paling sibuk. `ScanCameraScreen` memanggil `poke()` tiap deteksi.

**30 detik itu ketat, dan itu memang disengaja.** Risikonya: guru yang berhenti
sebentar di tengah ceklis kelas akan terkunci. Kalau di lapangan terasa
mengganggu, angkanya ada di satu tempat (`AppConfig.idleLockTimeout`) — tapi
menaikkannya berarti memperlebar jendela ketika HP hilang, jadi ubahlah dengan
sadar, bukan karena kesal.

**b. Data basi menghasilkan absensi yang salah.** Siswa yang pindah kelas
kemarin akan tercatat di kelas lama sampai disinkronkan. Umur data yang selalu
terlihat (§3.4) mengurangi ini, tapi tidak menghilangkannya.

**c. Sinkron pertama butuh waktu dan kuota.** Untuk 5.000 siswa, ~1 MB. Harus
punya indikator progres dan bisa diulang kalau putus di tengah.

**d. Kode ganda — sudah aman.** Snapshot memaksa `unique_code` benar-benar
unik, karena pencarian offline tak punya server untuk menengahi kalau ada dua
yang sama. Sudah dicek: `students_unique_code_unique` dan
`teachers_unique_code_unique` keduanya indeks **unik**, dan tidak ada duplikat
di data. Jadi ini bukan penghalang — cukup dijaga jangan sampai hilang.

---

## 8. Tahapan

| Tahap | Isi | Perkiraan |
|---|---|---|
| **1** | Endpoint `/sync/*` + proyeksi ramping + tombstone di backend | paling besar; tanpa ini tahap lain tak bisa jalan |
| **2** | SQLite lokal + snapshot penuh saat login pertama + umur data di Pengaturan | sedang |
| **3** | Sinkron delta lewat tombol + `deleted[]` + watermark aman | sedang |
| **4** | Sambungkan ke layar: pratinjau Ref ID, daftar Checklist, validasi pindai | kecil, ini panennya |

Tahap 1–2 sudah membuat Checklist dan pratinjau Ref ID jalan offline. Tahap 3–4
yang membuatnya tetap benar seiring waktu.

**Prasyarat (7a) sudah beres:** snapshot diterima apa adanya, dilindungi kunci
menganggur 30 detik yang sudah dibangun. Tahap 1 bisa dimulai kapan saja.
