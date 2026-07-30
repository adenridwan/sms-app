# Rencana Eksekusi: Modul Guru (Master Data, Dokumen, & Penataan Tahunan)

> Status: **FASE G1–G4 SELESAI** — 54 test Pest baru (total 102 lulus) +
> 69 uji E2E lulus. Bug logout (ditemukan usai G1) juga sudah diperbaiki.
> Setelah G3, juga dikerjakan penataan menu Kelas/Jurusan (Pengaturan →
> Akademik) + master data Tingkat Kelas baru — lihat §9.
> Kelanjutan dari [ROLE-ACCESS-PLAN.md](ROLE-ACCESS-PLAN.md) (Fase 1–4 selesai).
> Disusun: 18 Juli 2026. Diperbarui: 19 Juli 2026.
>
> **Catatan implementasi G1** (bug lama yang ikut ditemukan & diperbaiki
> selama pengerjaan, di luar yang sudah tercatat di §1):
> - `TeacherController` lama memakai `App\Models\User` dan menulis field yang
>   tidak ada di skema (`gender`/`birth_date`/`photo` ke tabel `teachers`,
>   `first_name` langsung ke `users`) — pola sama seperti bug T2 di
>   ROLE-ACCESS-PLAN.md. Ditulis ulang penuh sesuai skema nyata.
> - Route `{teacher}/subjects`, `/schedule`, `/classrooms`,
>   `/assign-subjects` memanggil method yang tidak ada di controller (fatal
>   bila dipanggil) — **dihapus**, sejalan dengan keputusan §2 (penempatan
>   kelas & mapel bukan urusan modul ini).
> - `Teacher::subjects()` merujuk `TeacherSubject::class`, sebuah model yang
>   tidak pernah dibuat — bug tidak disentuh (di luar lingkup G1, relevan
>   untuk menu Mata Pelajaran nanti).
> - Route API `auth.password.update` memanggil method `update()` yang tidak
>   ada di `PasswordController` (hanya ada `change()`) — selalu 500. Diperbaiki
>   agar rute memanggil `change()`.
> - `authApi.changePassword` di frontend memakai `POST`, padahal rute
>   sebenarnya `PUT` — selalu 405. Diperbaiki.
> - `routes/auth.php` (berisi rute `/logout`, verify-email, forgot/reset
>   password) **tidak pernah dimuat** oleh `bootstrap/app.php` — sepenuhnya
>   mati. Tombol Logout di sidebar saat ini menuju rute yang tidak
>   terdaftar. **Belum diperbaiki** (di luar lingkup G1); perlu ditangani
>   terpisah karena berdampak ke seluruh aplikasi, bukan cuma modul guru.
> - Password baru (`Password::defaults()`) hanya mensyaratkan 8+ karakter di
>   luar production; di production otomatis menambah syarat huruf besar/kecil,
>   angka, simbol, dan cek kebocoran (`uncompromised()`).
>
> **Catatan implementasi G2** (bug lama & baru yang ditemukan & diperbaiki):
> - Migrasi `media` bawaan Spatie (`$table->morphs('model')`) memakai
>   `model_id` bertipe **bigint** — semua model di app ini (Teacher, dst.)
>   berkunci **UUID**. Setiap upload dokumen akan gagal INSERT
>   (`invalid input syntax for type bigint`). Diganti `uuidMorphs('model')`.
>   Migrasi publish Spatie juga tidak membawa method `down()` — ditambahkan.
> - **Bug nyata sejak G1, baru ketahuan sekarang**: `PageController` mengirim
>   `new TeacherResource($teacher)` LANGSUNG sebagai prop Inertia (bukan
>   `->resolve()`). Inertia memperlakukan objek `Responsable` (termasuk
>   `JsonResource`) lewat `toResponse()`, yang membungkusnya
>   `{"data": {...}}` — sehingga prop `teacher` di frontend menjadi
>   `teacher.data.xxx`, bukan `teacher.xxx`. Form **Edit Guru dari G1
>   kemungkinan selalu tampil kosong** sejak dirilis (field mengakses
>   `teacher.first_name` yang sebenarnya `undefined`), lolos dari G1 karena
>   test E2E saat itu hanya `str_contains()` mentah pada HTML, tidak
>   memeriksa struktur JSON. Diperbaiki di `editTeacher()` **dan**
>   `showTeacher()` dengan `(new TeacherResource($teacher))->resolve()`.
> - Symlink `public/storage` (dibuat `storage:link` di awal sesi) ternyata
>   tidak benar-benar tersimpan di disk (kemungkinan gagal senyap karena
>   keterbatasan izin symlink di Windows tanpa Developer Mode/Admin) — dokumen
>   ter-upload tapi tidak bisa diunduh (404) sampai `storage:link` dijalankan
>   ulang dan diverifikasi via PowerShell (`Get-Item` mengenali *Junction*,
>   fallback Laravel di Windows saat symlink asli tak diizinkan).
> - `UploadedFile::fake()->create()` di test mengisi file dengan byte kosong;
>   medialibrary mendeteksi mime dari ISI file (finfo), bukan label yang
>   diklaim, sehingga file "pdf" palsu terdeteksi `application/x-empty` dan
>   ditolak validasi. Helper test `fakePdf()` (byte awal `%PDF-`) dan
>   `UploadedFile::fake()->image()` dipakai sebagai gantinya — bukan bug
>   aplikasi, murni pola test yang perlu konten realistis.
> - Keputusan desain: "hapus guru" (soft delete, sudah ada sejak G1) SENGAJA
>   **tidak** menghapus dokumen — rencana awal menyebut "media ikut
>   terhapus", tapi itu tidak konsisten dengan soft-delete yang reversibel
>   (guru bisa "dipulihkan"). Dokumen baru ikut hilang bila suatu saat ada
>   force-delete sungguhan (belum ada endpoint-nya).
>
> **Perbaikan bug Logout (dikerjakan setelah G2, sesuai permintaan)**:
> `routes/auth.php` (berisi `/logout`, dan duplikat path-case salah untuk
> `/login`/`/register`, plus `/forgot-password`, `/reset-password/{token}`,
> `/verify-email` yang merender komponen Inertia yang tak pernah ada) semula
> dikira mati total — ternyata **dimuat oleh `app/Providers/RouteServiceProvider.php`**
> (konvensi Laravel lama, terpisah dari `bootstrap/app.php`), sesuatu yang
> luput dari pencarian awal karena hanya menelusuri `bootstrap/app.php` dan
> file `routes/*.php` itu sendiri, tidak menelusuri Providers. Akibatnya
> menghapus file itu langsung **merusak seluruh aplikasi** (setiap request
> gagal karena provider mencoba `require` file yang sudah tak ada) — insiden
> ini langsung ketahuan lewat test suite penuh & diperbaiki dalam sesi yang
> sama, tidak sempat ter-commit dalam keadaan rusak. Perbaikan akhir: route
> `/logout` (closure sederhana, satu-satunya bagian file lama yang benar-benar
> berfungsi) dipindah langsung ke `web.php`; baris yang me-require
> `routes/auth.php` dihapus dari `RouteServiceProvider`; file lama dihapus
> untuk baik. Diuji: 3 test Pest (`tests/Feature/LogoutTest.php`) + 4 uji E2E
> sesi cookie nyata di server hidup (login → dashboard 200 → logout → 302 ke
> /login → dashboard ditolak). `/forgot-password` dkk. tetap belum
> berfungsi (di luar lingkup — perlu komponen halaman baru + alur reset
> password lewat email, bukan sekadar rewiring rute). `/profile` juga
> ditemukan mengarah ke komponen Inertia yang tidak ada (`Profile/Edit.tsx`)
> — dicatat, tidak diperbaiki (di luar lingkup permintaan).
>
> **Catatan implementasi G3** (bug lama & baru yang ditemukan & diperbaiki):
> - **Dua controller kelas berbeda ternyata ada di proyek ini**:
>   `Api\V1\MasterData\ClassRoomController` (yang punya bug T5 dari §1) TIDAK
>   PERNAH DIROUTE — sepenuhnya mati, begitu juga `classRoomsApi` (huruf R
>   besar) di frontend yang menunjuk ke sana. Controller yang benar-benar
>   dipakai `settings/ClassRooms.tsx` adalah `Api\V1\Academic\ClassroomController`
>   (`classroomsApi`, huruf r kecil), dan controller itu **sudah benar** sejak
>   awal (`exists:users,id`, bukan `exists:teachers,id`) — jadi T5 tidak
>   pernah nyata bagi pengguna, hanya ada di kode yang tak terpakai. G3
>   sepenuhnya dikerjakan di controller yang aktif; yang mati dibiarkan
>   (dicatat, bukan dihapus, di luar lingkup permintaan).
> - **Bug nyata, ditemukan lewat test G3**: `User::teachingClassroomIds()`
>   (dibuat Fase 1) memakai `static $cache` yang dikira "sekali per
>   request" tapi sebenarnya bertahan sepanjang umur proses PHP — begitu
>   penugasan kelas guru berubah (mis. lewat sinkron guru pengampu), guru itu
>   tetap terbaca punya akses lama selama proses PHP yang sama masih hidup
>   (worker Octane/queue, atau beberapa request tersimulasi dalam satu test).
>   Cache statis itu dihapus — query ekstra per pemanggilan lebih murah
>   daripada scope akses yang salah. Ditemukan otomatis oleh test
>   "menghapus guru dari pengampu menghilangkan akses" yang gagal sebelum
>   perbaikan ini.
> - `homeroomTeacher` (relasi ke `users`) tidak pernah di-eager-load
>   `.profile` di `index()`/`show()` — mengakses `full_name` di
>   `ClassroomResource` akan memicu lazy-load (dilarang di non-production)
>   begitu daftar kelas menampilkan wali kelas. Ditambahkan `homeroomTeacher.profile`.
> - Validasi baru: satu guru tidak boleh jadi wali di dua kelas pada tahun
>   ajaran yang sama (belum pernah divalidasi sebelumnya) — via
>   `Rule::unique` di-scope ke `tenant_id` + `academic_year_id`, mengizinkan
>   guru yang sama jadi wali di tahun ajaran berbeda.

---

## 1. Latar Belakang & Temuan

| # | Temuan | Dampak |
|---|--------|--------|
| T1 | Tombol Tambah/Edit guru menuju `/teachers/create` dan `/teachers/{id}/edit`, tapi semua sub-route dirender `PageController@teachers` (halaman daftar). Folder `pages/teachers/` hanya berisi `Index.tsx` | Tambah/Edit guru tidak berfungsi sama sekali |
| T2 | `Api\V1\Teacher\TeacherController@store/update` ditulis untuk skema yang tidak pernah ada: menulis `first_name`/`is_active` ke `users` (kolom tidak ada), `gender`/`birth_date`/`photo`/`position` ke `teachers` (kolom tidak ada), `employment_status='active'` (bukan nilai enum), tidak membuat `user_profiles` | API guru 500 bila dipanggil — rusak sejak awal |
| T3 | Dua pintu input data kepegawaian: seksi "Data Guru" di menu Pengguna (Fase 4) dan menu Data Guru | Input dobel, rawan tidak sinkron |
| T4 | Menu Kelas (frontend `settings/ClassRooms.tsx`) tidak punya field wali kelas maupun guru pengampu | Penempatan kelas (`teacher_classrooms`) tidak punya UI — selama ini diisi manual |
| T5 | `ClassRoomController` memvalidasi `homeroom_teacher_id` dengan `exists:teachers,id` padahal kolom DB merujuk **users.id** | Penetapan wali kelas via API gagal/salah sasaran |
| T6 | `spatie/laravel-medialibrary` terpasang di composer tapi tidak pernah dikonfigurasi (tanpa config, migrasi, model HasMedia) | Belum ada penyimpanan dokumen |
| T7 | Halaman Mata Pelajaran tidak pernah dibuat (route ada, komponen React tidak ada) | Kompetensi mapel guru belum bisa dikelola |

## 2. Keputusan Desain (final, sudah disepakati)

1. **Pemisahan master vs tahunan** — tabel `teachers` = master data lintas tahun
   (identitas, kepegawaian, dokumen). Semua penataan tahunan dikelola di menu
   per-tahun sehingga tabel guru tidak berubah tiap tahun ajaran:

   | Data | Dikelola di | Tabel |
   |---|---|---|
   | Identitas, kepegawaian, foto, dokumen | Menu Data Guru | `users`, `user_profiles`, `teachers`, `media` |
   | Wali kelas | Menu Kelas | `classrooms.homeroom_teacher_id` (→ users.id) |
   | Guru pengampu kelas | Menu Kelas | `teacher_classrooms` (per tahun ajaran) |
   | Kompetensi mapel guru | Menu Mata Pelajaran (menyusul) | `teacher_subjects` |
   | Penugasan mengajar riil | Menu Jadwal (menyusul) | `schedules` |

2. **Akun otomatis dari form guru**: email menjadi login, role `guru` otomatis,
   `user_type='teacher'`.
3. **Password awal = tanggal lahir format `ddmmyyyy`** (unik per orang, bukan
   default massal). Konsekuensi: field tanggal lahir **wajib** di form guru.
4. **Wajib ganti password saat login pertama**: penanda
   `users.preferences.must_change_password = true` (kolom JSON sudah ada, tanpa
   migrasi). Middleware mengarahkan ke halaman ganti password sebelum bisa ke
   halaman lain. Tombol "Reset Password" admin menyalakan lagi penanda ini.
5. **Foto guru** = `users.avatar` (satu sumber, dipakai sidebar & dashboard).
6. **Dokumen pemberkasan** via medialibrary, semua **opsional**:
   koleksi `ijazah`, `ktp`, `npwp`, `sertifikat_pendidik`, `surat_penugasan`,
   `lainnya`. KTP & NPWP `singleFile` (upload baru menimpa); sisanya multi-file.
   Validasi: PDF/JPG/PNG maks 5 MB.
7. **Menu Pengguna disusutkan**: urusan akun saja. Seksi Data Guru menjadi
   read-only (NIP, status kepegawaian, dsb. dibaca dari `teachers`) + tautan
   "Kelola di Data Guru". Seksi Data Staf & pemilih anak orang tua tetap.

## 3. Rancangan Form Guru (Tambah/Edit) — 3 Tab Master

### Tab 1 — Akun & Pribadi → `users` + `user_profiles`
| Field | Wajib | Tujuan kolom |
|---|---|---|
| Nama depan / belakang | ya / tidak | `user_profiles.first_name/last_name` |
| Email | ya (unik) | `users.email` (login) |
| Username | otomatis dari nama (bisa diubah) | `users.username` |
| No. HP | tidak | `user_profiles.phone` + `teachers.no_hp` |
| Jenis kelamin | ya | `user_profiles.gender` |
| Tempat / **tanggal lahir** | tidak / **ya** (sumber password awal) | `user_profiles.birth_place/birth_date` |
| Agama, alamat | tidak | `user_profiles.*` |
| NIK | tidak | `user_profiles.id_number` |

### Tab 2 — Kepegawaian → `teachers`
| Field | Wajib | Kolom |
|---|---|---|
| NIP / NUPTK | tidak (unik per tenant bila diisi) | `nip`, `nuptk` |
| Tanggal masuk | ya (default hari ini) | `join_date` |
| Status kepegawaian | ya (tetap/kontrak/honorer/paruh waktu) | `employment_status` |
| Status guru | ya (aktif/nonaktif/cuti/pensiun/berhenti) | `status` |
| Sertifikasi (status + nomor) | tidak | `certification_status`, `certification_number` |
| Pendidikan (jenjang, jurusan, universitas) | tidak | `education_level/major`, `university` |
| Pengalaman mengajar (tahun) | tidak | `teaching_experience_years` |

### Tab 3 — Foto & Dokumen (semua opsional)
Foto profil (→ `users.avatar`, crop/preview) + upload per koleksi dokumen
(lihat keputusan #6) dengan daftar file terunggah + hapus/unduh.

**Perilaku simpan**: satu transaksi `users` + `user_profiles` + `teachers` +
role + penanda password. Sukses → toast berisi username & password awal
(`ddmmyyyy`) agar admin bisa menyampaikan ke guru.

## 4. Halaman Detail Guru (`/teachers/{id}`)

- Kartu identitas (foto, nama, NIP, status) + data kepegawaian lengkap.
- **Penugasan (read-only, dari rumus R3)**: kelas diampu tahun aktif, wali
  kelas (bila ada), mapel kompetensi, jadwal mengajar — dengan tautan
  "atur di menu Kelas/Jadwal". Tidak bisa diubah dari sini.
- Galeri dokumen per koleksi (preview/unduh).
- Aksi: Edit, Reset Password, Nonaktifkan.

## 5. Perubahan Menu Kelas (penataan tahunan)

1. Form kelas: tambah field **Wali Kelas** (pilih dari guru aktif; validasi
   satu guru maksimal satu kelas perwalian per tahun ajaran).
2. Detail/form kelas: bagian **Guru Pengampu** — multi-pilih guru →
   `teacher_classrooms` (kelas sudah membawa `academic_year_id`).
3. **Perbaiki T5**: validasi `homeroom_teacher_id` → `exists:users,id`
   (+ pastikan user tersebut punya record guru aktif), konsisten dengan
   `schedules` dan rumus R3.

## 6. Tahapan Eksekusi

> Setiap fase diuji (Pest + E2E) sebelum lanjut, pola sama dengan
> ROLE-ACCESS-PLAN. Test dunia uji memakai `tests/WorldHelpers.php` yang ada.

### Fase G1 — API Guru benar + Form 2 tab pertama — ✅ SELESAI
**File**: `Api/V1/Teacher/TeacherController` (ditulis ulang penuh),
`StoreTeacherRequest`/`UpdateTeacherRequest`, `TeacherResource`, halaman
`teachers/Create.tsx` & `teachers/Edit.tsx` (Tabs shadcn, tab 1–2), route web
`/teachers/create|{id}/edit` → `PageController::createTeacher/editTeacher`,
`teachersApi` di `services/api.ts`, tipe `Teacher`/`TeacherFormData`.
**Termasuk**: akun otomatis (username auto-slug + suffix bila bentrok) +
password awal `ddmmyyyy` dari tanggal lahir (kini wajib diisi) +
`User::markPasswordMustChange()/clearPasswordMustChange()/mustChangePassword()`
(disimpan di `users.preferences.must_change_password`), middleware
`EnsurePasswordIsCurrent` (alias `password.current`) pada grup route
`web.php` yang butuh sesi, halaman `auth/ChangePasswordRequired.tsx` di rute
`/change-password` (di luar grup guarded, mencegah redirect loop), tombol
Reset Password admin (`Admin\UserController::resetPassword`) kini juga
menyalakan penanda.
**Hasil uji**: 14 test Pest baru (`tests/Feature/TeacherModuleTest.php`,
total suite 62 lulus) + 13 uji E2E lewat sesi cookie nyata (bukan token) —
mencakup seluruh alur: admin buat guru → guru login pakai password tanggal
lahir → dipaksa ke `/change-password` → ganti sendiri → akses normal → admin
reset → dipaksa ganti lagi. NIP duplikat, tanggal lahir wajib, dan guru
biasa ditolak menambah guru (403) turut diuji.

### Fase G2 — Medialibrary + Tab 3 + Detail Guru — ✅ SELESAI
**File**: migrasi `media` (uuidMorphs, lihat catatan implementasi), `Teacher`
model `HasMedia`/`InteractsWithMedia` (koleksi §2.6 via konstanta
`DOCUMENT_COLLECTIONS`, method `documentsSummary()`), `TeacherAssignmentService`
(ringkasan penugasan read-only — kelas diampu, wali kelas, kompetensi mapel
dari `teacher_subjects`, jadwal — sengaja terpisah dari `TeacherResource`
supaya daftar guru tidak kena N+1 join), endpoint
`GET {teacher}/assignment`, `POST/DELETE {teacher}/photo`,
`POST {teacher}/documents`, `DELETE {teacher}/documents/{media}`,
`teachers/Show.tsx` (detail §4, identitas+kepegawaian+penugasan read-only+
galeri dokumen+aksi Edit/Reset Password/Nonaktifkan), tab "Foto & Dokumen" di
`teachers/Edit.tsx` (unggah langsung per file, KTP/NPWP single-slot vs
ijazah/sertifikat/dst. multi-file), `teachersApi` diperluas,
`storage:link` dijalankan ulang & diverifikasi.
**Hasil uji**: 15 test Pest baru (`tests/Feature/TeacherDocumentsTest.php`,
total suite 77 lulus) + 9 uji E2E via sesi cookie nyata — upload foto/dokumen
sungguhan (PDF & JPG asli, bukan file kosong), dokumen benar-benar bisa
diunduh lewat symlink publik, KTP menimpa vs ijazah menambah, hapus dokumen,
dokumen milik guru lain tidak bisa dihapus (404), guru tanpa izin ditolak
(403), soft-delete guru TIDAK menghapus dokumen (lihat keputusan desain di
atas), assignment kosong jujur untuk guru baru, halaman Detail & Edit
merender data yang benar (termasuk regresi bungkus `{data:...}` dari G1).

### Fase G3 — Menu Kelas: wali + guru pengampu — ✅ SELESAI
**File**: `Api\V1\Academic\ClassroomController` (eager-load
`homeroomTeacher.profile` di index/show; validasi wali dobel per tahun ajaran
via `Rule::unique`; endpoint baru `GET/PUT academic/classrooms/{id}/teachers`
→ sinkron `teacher_classrooms`, scoped ke tahun ajaran kelas itu),
`settings/ClassRooms.tsx` (field Select Wali Kelas di form, kolom Wali Kelas
di tabel, dialog "Kelola Pengampu" baru dengan pencarian + checklist guru),
`classroomsApi` (`getTeachers`/`syncTeachers`), `User::teachingClassroomIds()`
(hapus cache statis yang salah — lihat catatan implementasi).
**Hasil uji**: 12 test Pest baru (`tests/Feature/ClassroomTeacherAssignmentTest.php`,
total suite 92 lulus) + 15 uji E2E sesi cookie nyata — tetapkan wali kelas →
scope R4 langsung terbuka untuk guru itu; wali dobel di tahun sama ditolak
422, di tahun berbeda diizinkan; sinkron pengampu menambah & melepas akses;
dashboard guru memuat kelas barunya; guru tanpa `classrooms.manage` ditolak;
id yang bukan guru ditolak.

### Fase G4 — Susutkan seksi Data Guru di menu Pengguna — ✅ SELESAI
**File**: `settings/Users.tsx` (seksi Data Guru diganti kartu read-only —
NIP/status/kepegawaian/pendidikan — + tombol "Kelola di Data Guru" ke
`/teachers/{teacher.id}/edit` bila record ada, atau pesan arahan ke menu
Data Guru bila belum ada; input NIP/status/pendidikan/tgl masuk dan switch
"Data Guru" dihapus dari form ini, `teacher: {...}` juga tidak lagi dikirim
dari form Pengguna), `UserResource` (menambah `teacher.id` dan `teacher.status`
supaya link "Kelola di Data Guru" valid), `Admin/UserController` TIDAK diubah
— validasi & `syncLinkedRecords()` tetap menerima payload `teacher` lama demi
kompatibilitas API, hanya UI Pengguna yang tidak lagi mengirimkannya.
**Hasil uji**: regresi test Fase 4/G1 tetap lulus (payload `teacher` masih
diterima API — dibuktikan test lama "membuat user guru sekaligus membuat
record teachers" & "lengkapi data: update user lama menambahkan record
teachers" masih hijau tanpa perubahan) + uji E2E baru: buat guru via Data
Guru → `admin/users/{id}` menyertakan `teacher.id` & `teacher.nip` yang benar
→ `/teachers/{teacher.id}/edit` (tautan "Kelola di Data Guru") merender 200.

## 9. Penataan Menu Kelas/Jurusan & Master Data Tingkat Kelas (di luar rencana awal, dikerjakan sesudah G3)

Diminta terpisah dari rencana G4, tapi dikerjakan dalam sesi yang sama karena
saling terkait dengan menu Kelas:

1. **Kelas & Jurusan dipindah dari Pengaturan ke Akademik** (menghindari menu
   dobel — sebelumnya `Pengaturan → Kelas/Jurusan` adalah satu-satunya yang
   benar-benar berfungsi, sementara `Akademik → Kelas` sudah ada di sidebar
   tapi rutenya tidak pernah didaftarkan sama sekali, murni 404).
   Halaman `settings/ClassRooms.tsx` & `settings/Majors.tsx` dipindah jadi
   `academic/ClassRooms.tsx` & `academic/Majors.tsx`. Rute baru
   `/academic/classrooms` & `/academic/majors` (`PageController::academicClassRooms/academicMajors`);
   rute lama `/settings/class-rooms` & `/settings/majors` **tidak dihapus**,
   diubah jadi `Route::redirect(...)` supaya bookmark/tautan lama tidak
   berujung 404 ("non aktifkan", bukan "hapus", sesuai permintaan).
2. **Master data Tingkat Kelas (grade_levels) baru dibuat** — sebelumnya API
   CRUD (`GradeLevelController`) sudah ada tapi TIDAK PERNAH punya antarmuka
   pengelolaan sama sekali (tidak ada halaman, tidak ada menu) dan tabelnya
   tidak punya kolom `is_active`, sehingga dropdown "Tingkat" di popup Tambah
   Kelas selama ini menampilkan seluruh baris apa adanya tanpa cara
   menonaktifkan yang sudah tidak dipakai. Ditambahkan: migrasi
   `is_active` (default true), validasi & filter di `GradeLevelController`
   (`?is_active=1`, sama seperti pola `MajorController`), otorisasi
   `grade-levels.manage` (belum ada middleware permission sebelumnya — pola
   ini konsisten dengan `abort_unless('classrooms.manage')` yang sudah ada di
   `ClassroomController::syncTeachers`), halaman baru `academic/GradeLevels.tsx`
   (pola sama seperti `Majors.tsx`, tanpa import/export karena tidak diminta),
   menu baru "Tingkat Kelas" di Akademik.
3. **Popup Tambah/Edit Kelas** (`academic/ClassRooms.tsx`):
   - Tahun Ajaran: sudah benar sejak G3 — mengambil dari `academicYearsApi`
     (sumber sama dengan submenu Tahun Ajaran di Akademik) dan default ke
     tahun yang `is_active`; tidak ada perubahan diperlukan.
   - **Bug diperbaiki**: dropdown Jurusan (dan kini Tingkat) memuat SEMUA
     baris tanpa filter `is_active`, sehingga jurusan/tingkat yang sudah
     dinonaktifkan tetap muncul sebagai pilihan baru. Diperbaiki dengan
     `gradeLevelsApi.list({ is_active: true })` /
     `majorsApi.list({ is_active: true })`. Kelas yang SUDAH memakai
     tingkat/jurusan yang kini nonaktif tetap bisa tampil benar saat diedit
     (disisipkan kembali ke daftar pilihan dengan label "(Nonaktif)"), supaya
     form edit tidak tampak kosong/rusak.
4. **Catatan di luar lingkup, belum diperbaiki**: halaman Akademik lain
   (`academic/Years.tsx` untuk Tahun Ajaran, `academic/Schedules.tsx` untuk
   Jadwal, Kurikulum, Mata Pelajaran) masih belum punya komponen React sama
   sekali meski rutenya terdaftar — bug pra-existing, tidak diminta, tidak
   disentuh sesi ini.

**File**: migrasi `0001_01_01_000018_add_is_active_to_grade_levels_table`,
`GradeLevel` model/`GradeLevelResource`/`GradeLevelController`,
`PermissionSeeder`/`RoleSeeder` (permission `grade-levels.view`/`.manage`),
`routes/web.php`, `PageController` (`academicClassRooms/academicMajors/academicGradeLevels`),
`resources/js/pages/academic/{ClassRooms,Majors,GradeLevels}.tsx`,
`resources/js/layouts/MainLayout.tsx`, `types/index.ts`.
**Hasil uji**: 10 test Pest baru (`tests/Feature/AcademicMenuRestructureTest.php`,
total suite 102 lulus) + 11 uji E2E sesi cookie nyata — halaman baru
render 200, rute lama redirect (bukan 404), filter `is_active` menyembunyikan
tingkat/jurusan nonaktif dari popup, guru tanpa `grade-levels.manage` ditolak
403.

## 7. Kriteria Selesai Keseluruhan

- [x] Tambah/Edit guru berfungsi end-to-end dari menu Data Guru. (G1)
- [x] Guru baru langsung punya akun; login pertama dipaksa ganti password. (G1)
- [x] Dokumen pemberkasan bisa diunggah/diunduh, semuanya opsional. (G2)
- [x] Wali kelas & guru pengampu diatur dari menu Kelas; tabel `teachers`
      tidak berubah saat pergantian tahun ajaran. (G3)
- [x] Tidak ada input ganda antara menu Pengguna dan Data Guru. (G4 — seksi
      guru di menu Pengguna kini read-only + tautan ke Data Guru)
- [x] Seluruh test suite lama (48) + test baru lulus. (102 lulus setelah G4 + §9)

## 8. Di Luar Lingkup (dicatat untuk nanti)

- Halaman Mata Pelajaran (kompetensi mapel guru, `teacher_subjects`) — T7.
- Modul Jadwal (penugasan mengajar riil per semester).
- Export ZIP pemberkasan per guru (fondasi dokumen sudah disiapkan G2).
- Import massal guru dari Excel (pola import Majors bisa ditiru).
