# Email Otomatis untuk Akun Siswa & Guru

Dibuat 2026-08-09.

## Masalah

Field email wajib diisi saat menambah siswa/guru, padahal untuk ratusan siswa —
terutama jenjang TK — email pribadi itu tidak ada. Mengisinya manual satu per
satu tidak realistis, dan mengarang alamat palsu satu per satu justru
menciptakan data sampah yang tidak bisa dilacak.

Kenapa tidak dikosongkan saja: `users.email` adalah **NOT NULL + UNIQUE global**
(`0001_01_01_000002_create_users_table.php:16`) dan login satu-satunya adalah
`Auth::attempt(['email','password'])` (`AuthController::login`). Email di sini
bukan alamat surat — ia **identitas login**. Jadi solusinya bukan menghapus
email, melainkan membuat identitas login berbentuk email secara otomatis.

## Keputusan

### 1. Dua kolom dengan peran berbeda

| Kolom | Peran | Unik? | Dikirimi surat? |
|---|---|---|---|
| `users.email` | Identitas login. Boleh sintetis. | Ya (global, sudah ada) | **Tidak pernah** |
| `users.contact_email` (baru) | Alamat surat sungguhan: OTP, reset password, notifikasi | **Tidak** | Ya |

**`contact_email` sengaja tidak unik.** Satu orang tua dengan 3 anak memakai satu
alamat untuk 3 akun. Kalau dibuat unik, anak kedua dan ketiga tidak bisa punya
alamat pemulihan sama sekali — mengulang persis masalah yang sedang diselesaikan.

Keamanannya tidak bergantung pada keunikan itu, melainkan pada aturan berikut:

> **Jangan pernah mencari user hanya dengan `contact_email`.**

`contact_email` tidak pernah diadu dengan password, jadi berbagi alamat tidak
membuat satu akun bisa memasuki akun lain. Untuk alur OTP nanti, penunjuk akunnya
adalah **NIS (atau email login) + contact_email** — dicek berpasangan, dengan
respons yang identik antara "cocok" dan "tidak cocok" supaya endpoint-nya tidak
jadi alat menebak akun mana yang terdaftar (konvensi yang sudah dipakai
`AuthController::activate()` dan `loginWithOtp()`).

Catatan skema untuk fase OTP: `password_reset_tokens` ber-primary key `email`
(`create_users_table.php:36`). Yang disimpan di sana **harus email login** yang
unik — kalau `contact_email` yang masuk, permintaan reset satu anak akan menimpa
token saudaranya.

### 2. Bentuk email yang digenerate

```
siswa : {kata-pertama-nama-depan}.{nis}@{domain}   → ahmad.2024001@almuawanah.school
guru  : {username}@{domain}                        → ahmad.fauzi@almuawanah.school
```

**Bagian nama** diambil dari `first_name`, **hanya kata sebelum spasi pertama**.
`nama_depan = "Ahmad Ridwan"`, `nama_belakang = "Hidayat"` → `ahmad`. Lalu
transliterasi ASCII, huruf kecil, buang selain `a-z0-9`, potong maksimum
**20 karakter**; kalau tidak tersisa karakter valid → `siswa` / `guru`.

| `nama_depan` | NIS | Hasil |
|---|---|---|
| `Muhammad Zaidan Arkananta` | 2024001 | `muhammad.2024001` |
| `Nur 'Aini` | 2024002 | `nur.2024002` |
| `Al-Fatih` | 2024003 | `alfatih.2024003` |
| `Abdurrahmanshidiqullah` | 2024005 | `abdurrahmanshidiqull.2024005` |
| `王` (tanpa huruf latin) | 2024006 | `siswa.2024006` |

Panjang terburuk 20 + 1 + 20 (`nis` maks 20) = 41 karakter, aman di bawah batas
64 karakter local part (RFC 5321).

**Kenapa NIS, bukan angka urut.** NIS sudah unik per tenant
(`students` UNIQUE `(tenant_id, nis)`), jadi email pasti unik tanpa query
apa pun — dan yang terpenting, admin bisa **menghitung ulang** email seorang
siswa cukup dari NIS di kartunya, tanpa membuka aplikasi. Format `ahmad7@`,
`ahmad23@` gagal di titik ini: nomornya tidak bisa ditebak siapa pun.

**Kenapa guru berbeda.** `teachers.nip` nullable, jadi tidak bisa jadi pembeda.
Guru jumlahnya puluhan dan `username` sudah dijamin unik global oleh registrar.

Tetap ada jaring pengaman: cek `User::withoutTenant()->where('email', ...)` lalu
tambah sufiks angka, plus retry saat unique violation — mengikuti pola
`MAX_USERNAME_ATTEMPTS` yang sudah ada di kedua registrar. Diperlukan karena dua
sekolah bisa saja mengetik domain yang sama.

### 3. Domain disimpan per sekolah

Tabel `settings` (`group='account'`, `key='email_domain'`, `tenant_id` terisi),
diatur dari **Pengaturan → Umum (Profil Sekolah)** yang sudah punya
`permission:settings.school`.

**Bukan** `tenants.domain` — kolom itu untuk resolusi tenant berbasis host
(`tenant_domains`), urusan yang berbeda; menumpanginya akan mencampur dua konsep.

### 4. Import

**Siswa** — kolom `email` **dihapus dari template**, diganti `email_kontak`
(opsional). Email login **selalu** hasil generate untuk baris baru; tidak ada
jalan memasukkannya dari file. Baris update (NIS sudah ada) tidak pernah
menyentuh email login.

**Guru** — kolom `email` tetap ada tapi jadi **opsional** (kosong = digenerate),
ditambah `email_kontak`. Guru sering punya alamat asli dan wajar ingin login
dengannya, dan notifikasi kehadiran guru memang dikirim ke alamat itu.

**Export tetap memuat kolom `email`** (informasi login yang perlu dibagikan ke
siswa), sehingga heading export dan heading template **tidak lagi identik** —
`StudentsExport::HEADINGS` dan `StudentsTemplateExport::HEADINGS` dipisah. Alur
"export → sunting → import balik" tetap jalan karena import siswa **mengabaikan**
kolom `email`. Ini penting: kalau import menafsirkan kolom itu sebagai
`email_kontak`, setiap re-import file hasil export akan mengisi `contact_email`
seluruh siswa dengan alamat sintetis.

Kalau domain belum diatur dan ada baris yang butuh generate, **seluruh file
ditolak sekali** dengan satu pesan yang menunjuk ke Pengaturan → Umum — bukan
ratusan error per baris yang menyembunyikan penyebab sebenarnya.

### 5. Perbaikan yang wajib ikut

`NotificationDispatcher` mengirim notifikasi kehadiran guru ke
`teacher->user->email` (baris 150 & 182). Begitu email guru boleh sintetis, baris
itu harus memakai `contact_email`, dan migrasinya menyalin `email → contact_email`
untuk semua user yang sudah ada supaya notifikasi yang berjalan hari ini tidak
diam-diam berhenti.

## Langkah implementasi

1. **Migrasi** `users`: `contact_email` (nullable), `contact_email_verified_at`
   (nullable), `email_is_generated` (boolean, default false). Backfill
   `contact_email = email` untuk semua baris yang ada.
2. **`Setting`**: tambah `getForTenant()` / `setForTenant()` (yang ada sekarang
   hanya varian global).
3. **`App\Services\EmailGenerator`** (baru): `localPart()`, `forStudent()`,
   `forTeacher()`, `domainFor()`, plus pencarian sufiks bila bentrok.
4. **`StudentRegistrar` / `TeacherRegistrar`**: email kosong → generate, isi
   `contact_email` & `email_is_generated`.
5. **FormRequest** siswa & guru (store + update): `email` jadi `nullable`,
   tambah aturan `contact_email`.
6. **`StudentController::store`**: jalur form lama ikut memakai `EmailGenerator`
   bila email dikosongkan.
7. **Import** siswa & guru sesuai §4, termasuk preflight domain.
8. **Export & template** siswa/guru: pisahkan heading, tambah `email_kontak`.
9. **`NotificationDispatcher`**: tujuan email guru → `contact_email`.
10. **`SchoolProfileController`**: baca/tulis `email_domain`.
11. **Frontend**: field domain di `settings/General.tsx`; email jadi opsional +
    field email kontak di form siswa & guru; kolom email kontak di resource.
12. **Test**: `EmailGeneratorTest` (unit-ish) + `GeneratedEmailTest` (feature:
    form, import, preflight domain, contact_email tidak unik).

## Belum dikerjakan (fase berikutnya)

- **Pengiriman OTP lewat email.** Hari ini OTP hanya dibuat admin
  (`OtpService::generateFor()` butuh objek admin) dan kode polosnya muncul di
  layar admin untuk disampaikan lewat kanal luar.
- **`/forgot-password` dan `/reset-password` mati.** `routes/api_v1.php:32-38`
  menunjuk `PasswordController::forgot` dan `::reset` yang **tidak ada** di
  kelasnya — endpoint itu 500 kalau dipanggil. Perlu ditambal bersama fase OTP.
- **Verifikasi `contact_email`.** Kolom `contact_email_verified_at` sudah
  disiapkan tapi belum dipakai. Kalau nanti pemilik akun boleh mengubah alamat
  kontaknya sendiri, alamat itu **wajib** diverifikasi sebelum dipercaya sebagai
  tujuan reset password — kalau tidak, siapa pun yang sempat memegang sesi
  terbuka bisa mengambil alih akun secara permanen. Selama pengisiannya
  dibatasi admin, verifikasi boleh ditunda.
- **Regenerasi email massal** saat domain sekolah diganti. Email lama sengaja
  **tidak** ikut berubah otomatis: email adalah kredensial, mengubahnya diam-diam
  akan mengunci semua siswa sekaligus.
