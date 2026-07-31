# Rencana Eksekusi: Modul Absensi (Kehadiran, Kartu ID, Notifikasi)

> Status: **FASE 0, 2, 2a, 3, 3a, 4, & 5 SELESAI**, plus **gap routing web**,
> **role-scoping A13/A14/A15/A16**, dan **halaman detail/edit siswa + UI
> RFID** dibereskan (A1 — mismatch enum status; A6 — keunikan RFID lintas
> tabel + geofencing; Kartu ID Fase A — cetak kartu CR80 berlogo; notifikasi
> hybrid batch; foto profil siswa untuk kartu ID; scoping Absensi Guru &
> dropdown kelas; QR/Cetak Kartu diperbaiki — paket hilang + backend
> imagick; detail/edit siswa + UI RFID siswa & guru; PDF laporan diperbaiki
> + export Excel baru; Template Editor Kartu ID drag-and-drop). Fase 1
> (verifikasi mobile end-to-end), rekap semester, dst. masih tahap analisa.
> Diperbarui terakhir: 20 Juli 2026.
>
> **Catatan Fase 4 (Laporan & Export, selesai)**: A3 dibereskan — dibuat
> `resources/views/reports/attendance/student-monthly.blade.php` dan
> `teacher-monthly.blade.php` yang sebelumnya tidak ada sama sekali
> (`GET /attendance/reports/pdf` selalu 500). Ditambahkan export Excel baru
> (`GET /attendance/reports/excel`, tombol "Export Excel" di
> `reports/Index.tsx`) lewat `StudentAttendanceMonthlyExport`/
> `TeacherAttendanceMonthlyExport` (`FromArray`+`WithHeadings`, karena
> datanya array hasil hitung `generateStudentReportData()`/
> `generateTeacherReportData()`, bukan Eloquent collection mentah seperti
> pola `ClassroomsExport` di modul akademik). **Bug tersembunyi kedua
> ditemukan saat menulis test**: `generateStudentReportData()` meng-eager-load
> `student.user` tanpa `.profile`, padahal `full_name` selalu membaca
> `first_name`/`last_name` dari profile — di bawah mode strict
> (`preventLazyLoading`, aktif saat testing) ini melempar
> `LazyLoadingViolationException` setiap laporan siswa diakses dengan data
> sungguhan. Diperbaiki jadi `with(['student.user.profile'])` — otomatis
> ikut memperbaiki `monthly()` (endpoint JSON yang sudah ada) juga, karena
> berbagi method private yang sama. Endpoint teacher-side sudah benar sejak
> awal (sudah `with(['user.profile'])`). Rekap per semester/tahun ajaran
> (§7) sengaja belum dikerjakan — nice-to-have terpisah. Regresi baru:
> `tests/Feature/AttendanceReportExportTest.php` (4 test). Seluruh 162 test
> backend + `npm run build` lulus.
>
> **Catatan — Detail/Edit Siswa, UI RFID, Tombol Kembali Scanner (selesai)**:
> tiga laporan pengguna dibereskan sekaligus:
> - **UI RFID tidak ada sama sekali**: endpoint `RfidController` dari Fase 2
>   sudah ada tapi tanpa UI baik untuk siswa maupun guru. Ditambahkan input
>   Kode RFID + tombol Simpan di `students/Edit.tsx` (baru) dan `teachers/Edit.tsx`.
>   `StudentResource` sebelumnya juga belum mengeluarkan `unique_code`/`rfid_code`
>   sama sekali (beda dari `TeacherResource`) — sudah ditambahkan.
> - **Detail & Edit siswa tidak ada**: `routes/web.php` untuk `/students/{id}`
>   (show) dan `/students/{id}/edit` **sama-sama memanggil method index**
>   (`PageController::students()`), bukan method terpisah seperti modul Guru
>   (`showTeacher`/`editTeacher`). Dibuat `PageController::showStudent()`/
>   `editStudent()` + halaman baru `students/Show.tsx` (identitas, data
>   pendidikan, kartu "Kredensial Presensi" QR/RFID + tombol Cetak Kartu,
>   daftar orang tua/wali) dan `students/Edit.tsx` (form + foto profil +
>   RFID), mirror pola `teachers/Show.tsx`/`Edit.tsx`.
> - **Bug tersembunyi ditemukan & diperbaiki saat membangun Edit** (baru
>   ketahuan karena sebelumnya tidak ada UI yang memanggilnya): `gender`,
>   `birth_place`, `birth_date`, `religion`, `address`, `nik` bukan kolom
>   `students` — kolom itu ada di `user_profiles`. `StudentController::store()`/
>   `update()` selama ini mengirim field-field itu ke `Student::create()`/
>   `update()` yang **diam-diam menjatuhkannya** (tidak fillable, tidak error,
>   tidak tersimpan). Diperbaiki mengikuti pola `TeacherController::update()`
>   yang sudah benar: `UserProfile::updateOrCreate(['user_id' => ...], $profileFields)`.
>   **Catatan tambahan (belum diperbaiki, di luar cakupan sesi ini)**: kolom
>   `entry_year`/`entry_class`/`entry_semester` yang divalidasi
>   `StoreStudentRequest`/`UpdateStudentRequest` **juga bukan kolom nyata**
>   (kolom aslinya `entry_date`/`entry_type`) — sama-sama diam-diam
>   terjatuh saat `store()`; dan `StudentController::store()` gagal terpisah
>   dengan error "tidak ada role `siswa` untuk guard `sanctum`" saat dites
>   lewat HTTP (kemungkinan config guard Spatie permission) — dua temuan
>   ini murni modul CRUD Siswa, bukan modul Absensi, sengaja tidak disentuh
>   di sesi ini.
> - **Scanner tanpa tombol kembali**: `scanner/Index.tsx` adalah halaman
>   kiosk mandiri tanpa `MainLayout`. Ditambahkan tombol panah kembali ke
>   `/attendance` di header.
> Regresi baru: `tests/Feature/StudentPagesTest.php` (4 test, termasuk
> regresi bug field profil). Seluruh 158 test backend + `npm run build` lulus.
>
> **Catatan Fase 5 (Template Editor Kartu ID drag-and-drop, selesai)**: kartu
> ID sejak Fase 2a memakai layout **fixed** di kode (`attendanceCardPrint.ts`).
> Dibuat tabel baru `card_templates` (`tenant_id`, `type` student/teacher,
> `layout_json` — posisi/ukuran tiap elemen dalam **persen** dimensi kartu
> CR80 85.6mm×54mm), `CardTemplateController` (`GET`/`PUT`/`DELETE
> /attendance/card-templates/{type}`), dan halaman editor baru
> `attendance/card-templates/Index.tsx` — kanvas kartu diperbesar berisi
> elemen yang bisa digeser (`framer-motion` drag, sudah terpasang sebelumnya,
> tanpa perlu library canvas baru) dan diubah ukuran/font lewat slider di
> panel samping saat dipilih, plus color picker latar kartu/header. Layout
> default persis mencerminkan CSS fixed Fase 2a — tenant yang belum pernah
> membuka editor tidak melihat perubahan visual apa pun.
> `attendanceCardPrint.ts` diperluas: `printAttendanceCards()` menerima
> `options.layout?` opsional (render posisi absolut bila diisi, fallback ke
> markup fixed lama bila tidak), plus helper baru
> `printAttendanceCardsWithTemplate(type, people, options)` yang mengambil
> layout aktif tenant lalu mencetak — dipakai oleh keempat pemanggil cetak
> kartu (`qr-codes/Index.tsx`, `students/Index.tsx`, `students/Show.tsx`,
> `teachers/Index.tsx`), menggantikan `printAttendanceCards()` langsung.
> **Bug tersembunyi ditemukan & diperbaiki saat menulis test**:
> `CardTemplateController::update()` memakai `$request->validate()` dengan
> rule bersarang (`layout_json` + `layout_json.elements`) lalu mengambil hasil
> `validated()` — ternyata Laravel **memangkas sibling key** (`cardBackground`,
> `headerBackground`) yang tidak punya rule eksplisit sendiri saat rule
> memakai dot-notation ke sub-key tertentu. Setiap simpan template kustom
> diam-diam kehilangan warna kartu/header. Diperbaiki dengan memvalidasi
> struktur lewat `$request->validate()` (untuk efek sampingnya — melempar 422)
> tapi mengambil payload dari `$request->input('layout_json')` mentah, bukan
> `validated()`. **Belum dikerjakan (dicatat sebagai lanjutan terbuka)**:
> unggah gambar background kustom (kolom belum ditambah — cakupan MVP sengaja
> dibatasi ke posisi/ukuran/warna saja). Regresi baru:
> `tests/Feature/CardTemplateTest.php` (7 test). Seluruh 169 test backend +
> `npm run build` lulus.
>
> **Catatan penting — gap routing web (ditemukan lewat laporan pengguna
> "menu absensi belum bisa digunakan")**: seluruh Fase 0–3a hanya menyentuh
> `routes/api_v1.php` (API) dan komponen React — lapisan `routes/web.php`
> yang menyajikan komponen itu lewat Inertia **tidak pernah disentuh**, dan
> ternyata memang belum lengkap sejak awal. `web.php` cuma punya satu rute
> `/attendance` (landing). Delapan kartu menu di landing page itu
> (`attendance/Index.tsx`) — Absensi Siswa, Absensi Guru, Perizinan, Hari
> Libur, QR Code, Laporan, Pengaturan, Scanner — semuanya mengarah ke rute
> yang **tidak terdaftar**, jatuh ke 404. Submenu sidebar (`MainLayout.tsx`)
> malah punya set link ketiga yang berbeda lagi, dua di antaranya salah nama
> (`/attendance/employees`, `/attendance/summary`). Sudah diperbaiki:
> 9 rute baru + method `PageController` (dengan prop `classrooms` — kelas
> aktif tahun ajaran aktif — untuk halaman Siswa/QR Code/Laporan; `students`/
> `teachers` untuk form tambah izin; halaman lain fetch sendiri via API) dan
> 2 href sidebar yang salah. Regresi baru: `tests/Feature/AttendancePagesTest.php`
> (11 test, HTTP request sungguhan ke tiap rute — bukan cuma cek "tidak
> error" tapi memverifikasi komponen & prop yang benar sampai render).
> Seluruh 145 test backend + `npm run build` lulus.
>
> **Pelajaran untuk fase berikutnya**: sebelum menandai fase manapun
> "selesai", verifikasi juga bahwa halamannya benar-benar bisa diakses dari
> UI (rute web + menu), bukan cuma API-nya berfungsi — dua lapisan ini
> ternyata bisa lolos verifikasi terpisah tanpa saling menyadari yang lain
> putus.
>
> **Catatan Fase Role-Scoping & Perbaikan Cetak Kartu (A13/A14/A15/A16, selesai)**:
> setelah menu bisa diakses, uji langsung di browser + `php artisan tinker`
> terhadap DB dev (`sms_enterprise`) menemukan & membereskan 4 hal:
> - **A15/A16 (root cause "Cetak Kartu" error)**: paket `simplesoftwareio/simple-qrcode`
>   ternyata tidak pernah ter-install sama sekali, dan setelah dipasang,
>   backend PNG-nya butuh ekstensi `imagick` yang juga tidak ada. Diselesaikan
>   dengan install paket + alihkan `generateQrImage()` ke output SVG (tak
>   butuh imagick, tak ada perubahan kontrak di frontend).
> - **A13**: `TeacherAttendanceController` (`index/daily/update/summary`)
>   diberi scoping — guru non-admin cuma lihat/edit barisnya sendiri; role
>   `Student::ALL_ACCESS_ROLES` (admin, kepala/wakil kepala sekolah,
>   **tata_usaha**, bendahara, pustakawan) tetap bebas penuh.
> - **A14**: dropdown kelas di halaman Absensi Siswa kini tersaring ke
>   `teachingClassroomIds()` guru yang login, dan otomatis ter-pilih
>   (`initialClassroom`) bila cuma satu kelas — guru tak perlu lagi memilah
>   dari daftar kelas yang bukan miliknya.
> - **Kemudahan akses scanner**: `QuickActions` (berisi tombol Absensi/
>   Scanner/Perizinan/Laporan) dipindah dari bawah ke atas halaman Dashboard
>   (`SchoolDashboard` & `TeacherDashboard`); tombol "Buka Scanner" juga
>   ditambahkan di header halaman Absensi Siswa.
> Regresi baru: `tests/Feature/TeacherAttendanceScopeTest.php` (6 test) +
> 3 kasus baru di `AttendancePagesTest.php`. Seluruh 154 test backend +
> `npm run build` lulus.
>
> **Catatan Fase 3a (Foto Profil Siswa)**: guru ternyata sudah punya foto
> profil yang bekerja penuh (`TeacherController::uploadPhoto/deletePhoto` →
> `users.avatar`, sudah diuji, sudah ada UI di `teachers/Edit.tsx`) —
> `StudentResource` bahkan **sudah** mengeluarkan field `photo_url` dari
> `$this->user->avatar`, tapi tidak ada endpoint yang pernah mengisinya.
> Ditambahkan `StudentController::uploadPhoto/deletePhoto` (mirror persis
> pola guru) + route `POST/DELETE students/{student}/photo` + dialog
> "Foto Profil" di menu Siswa (dropdown per baris, `students/Index.tsx`).
> **Kode mati dibongkar**: `StoreStudentRequest`/`UpdateStudentRequest`
> memvalidasi field `photo` dan `StudentController::store/update/destroy`
> menyimpannya ke `Storage::disk('public')->store('students', ...)` lalu
> mencoba `Student::create/update(['photo' => ...])` — padahal kolom
> `students.photo` **tidak pernah ada di migration manapun** dan `photo`
> tidak ada di `$fillable` model, jadi file ter-upload ke disk tapi
> referensinya selalu hilang (orphan file). Jalur ini dihapus sepenuhnya;
> foto sekarang murni lewat `users.avatar` via endpoint baru, konsisten
> dengan guru dan dengan `StudentResource` yang sudah ada. Kartu ID
> (`attendanceCardPrint.ts`) kini punya slot foto (18mm persegi) yang
> tampil otomatis begitu `photo_url` terisi — endpoint QR
> (`QrCodeController`, `QrCodeGeneratorService`) diperbarui menyertakan
> `photo_url` untuk siswa & guru. Regresi baru: `tests/Feature/StudentPhotoTest.php`
> (6 test). Seluruh 134 test backend + `npm run build` lulus.
>
> **Catatan Fase 3 (Notifikasi Hybrid)**: endpoint baru
> `POST attendance/students/notify-daily` (tombol "Kirim Notifikasi" di
> halaman Absensi Harian) memicu job queue `SendClassAttendanceRecap` — satu
> WA per siswa ke wali primer (`student_guardians.phone`, template
> `check_in`/`check_in_late`/`absent` sesuai status, menghormati flag
> `notify_check_in`/`notify_late`/`notify_absent`) plus satu rekap ringkas ke
> Telegram default tenant (bukan per-siswa, karena Telegram tenant cuma
> punya satu `telegram_default_chat_id`). Sakit/izin dilewati di jalur batch
> ini karena sudah dinotifikasi saat approval izin (`LeaveApprovalService`).
> Template `absent` + flag `notify_absent` yang sebelumnya ada di skema tapi
> tak terpakai kini punya pemakainya. Real-time (jalur scan) sudah
> hybrid-ready tanpa perubahan kode — tinggal admin set
> `notify_check_in=false` + `notify_late=true` di pengaturan tenant untuk
> "real-time hanya kasus kritis".
>
> **Perbaikan kontrak wire (regresi kecil dari Fase 0)**: saat menulis job ini
> ditemukan bahwa `AttendanceStatus::summaryFromRaw()`/`slug()` Fase 0 sudah
> benar untuk arah *keluar* (DB→JSON), tapi validasi `storeBulk`/`update`
> yang dipakai job ini (dan endpoint yang sama) masih memvalidasi nilai DB
> mentah (`present,sick,...`) padahal frontend selalu mengirim slug Indonesia
> (`hadir,sakit,...`). Ditambahkan `AttendanceStatus::fromSlug()`/`storableSlugs()`
> agar validasi & penulisan memakai slug Indonesia dua arah, konsisten dengan
> `daily()`/`summary()` yang sudah keluar berbahasa Indonesia sejak Fase 0.
> Test `AttendanceStatusWriteTest.php` diperbarui mengikuti kontrak yang benar
> ini (kasus lama yang mengirim `'present'` langsung kini dites HARUS ditolak).
> Regresi baru: `tests/Feature/AttendanceNotifyDailyTest.php` (6 test). Seluruh
> 128 test backend + `npm run build` lulus.
>
> **Catatan Fase 2a (Kartu ID Fase A)**: cetak kartu kini pakai layout fixed
> ukuran CR80 (85.6mm × 54mm) dengan logo sekolah (`tenants.logo`, sudah
> tersedia via props Inertia) + nama sekolah di header, QR + nama/NIS-NIP +
> kelas (siswa) / status kepegawaian (guru). Satu template bersama di
> `resources/js/lib/attendanceCardPrint.ts` — dipakai halaman QR Code
> (tombol "Cetak Semua"), menu **Siswa** (dropdown per baris "Cetak Kartu"),
> dan menu **Guru** (sama). Backend: `QrCodeController` kini menyertakan
> `classroom` (siswa) dan `employment_status_label` (guru) di response QR.
> Tidak ada migrasi/route baru. Builder drag-and-drop tetap Fase C (§4a).
>
> **Catatan Fase 2**: `rfid_code` sebelumnya sama sekali tidak bisa diisi lewat
> jalur mana pun (bukan cuma "belum unik") — ditambahkan endpoint baru
> `PUT attendance/rfid/students/{student}` dan `PUT attendance/rfid/teachers/{teacher}`
> (`RfidController.php`, mengikuti pola `QrCodeController` yang sudah ada),
> divalidasi lintas tabel lewat rule baru `App\Domain\Attendance\Rules\UniqueRfidCode`.
> Geofencing diaktifkan lewat `AttendanceSetting::distanceFromSchool()` (Haversine)
> + `AttendanceScanService::checkGeofence()`, dipanggil di titik yang **sudah**
> menerima `$location` (check-in siswa, check-in/out guru) — tidak mengubah
> field request/DB apa pun. Tenant yang belum mengaktifkan `require_location`
> (default) sama sekali tidak terpengaruh. `processStudentCheckOut()` tidak
> menerima lokasi sama sekali di kode existing — dibiarkan begitu, di luar
> cakupan "aktifkan pakai kolom yang sudah ada". Regresi baru:
> `tests/Feature/AttendanceRfidGeofenceTest.php` (10 test). Seluruh 122 test
> backend lulus setelah perubahan.
> Dokumen ini adalah panduan hasil audit langsung ke `backend/app`,
> `backend/routes/api_v1.php`, `backend/database/migrations`, serta
> `APPLICATION_SPEC.md`, `ROLE-ACCESS-PLAN.md`, `TESTCASE.md`.
>
> **Catatan Fase 0**: `AttendanceStatus` enum kini backing value bahasa Inggris
> (`present/sick/permitted/absent/alpha`, selaras `employee_attendances` &
> DB CHECK constraint); `label()`/`color()` tetap Indonesia. Ditambahkan
> `summaryFromRaw()` (satu sumber kebenaran untuk tally mentah → ringkasan
> Indonesia, dipakai `DashboardStatsService`, `StudentAttendanceController`,
> `AttendanceStatusResolver`, `AttendanceReportController`,
> `PublicAttendanceController`) dan `slug()` (menjaga kontrak JSON `status`
> tetap Indonesia di titik-titik yang sebelumnya mengembalikan `$enum->value`
> langsung ke frontend — `StudentAttendanceController::daily()` dan
> `PublicAttendanceController`). Tidak ada migrasi DB — DB memang sudah
> mengharapkan nilai Inggris sejak awal. Regresi baru: `tests/Feature/AttendanceStatusWriteTest.php`
> (9 test, mencakup bulk store, update, scan check-in, dan approval izin siswa —
> jalur yang sebelumnya sama sekali tidak diuji, temuan A9). Seluruh 111 test
> backend lulus setelah perubahan.
>
> **Bug tambahan ditemukan saat verifikasi** (di luar cakupan A1, ditemukan
> karena inilah pertama kalinya jalur tulis `storeBulk()`/scan/approval-izin
> benar-benar dieksekusi oleh test): `AcademicYear` tidak punya relasi
> `activeSemester` sama sekali, padahal dirujuk (`?->academicYear?->activeSemester?->id`)
> oleh `StudentAttendanceController::storeBulk()`, `AttendanceScanService`, dan
> `LeaveApprovalService` untuk mengisi `semester_id`. Di produksi ini gagal diam-diam
> (semester_id selalu null, kolom nullable jadi tidak error) — ditambahkan relasi
> `hasOne(Semester::class)->where('is_active', true)` di `AcademicYear.php`.

---

## 1. Latar Belakang & Temuan Kritis

| # | Temuan | Dampak |
|---|--------|--------|
| A1 | `student_attendances.status` di DB dibatasi `CHECK` dengan nilai Inggris (`present, absent, late, sick, permitted, alpha`), sedangkan **seluruh** kode aplikasi (controller, `AttendanceScanService`, `LeaveApprovalService`, frontend) menulis nilai Indonesia (`hadir, sakit, izin, tanpa_keterangan, alfa`) | Simpan absensi manual/bulk, scan check-in siswa, dan approval izin siswa berisiko gagal (constraint violation Postgres). Sudah dicatat di `ROLE-ACCESS-PLAN.md` baris 32–34, belum diperbaiki. **Blocker #1.** |
| A2 | `DashboardStatsService` membaca kolom status yang sama dengan asumsi bahasa Inggris — interpretasi ketiga yang berbeda dari modul Attendance sendiri | Dashboard bisa tidak sinkron dengan halaman absensi begitu A1 diperbaiki ke salah satu arah |
| A3 | ✅ **Selesai (Fase 4)** — dibuat kedua view Blade yang hilang | ~~Unduh laporan PDF bulanan selalu gagal (500)~~ |
| A4 | Route `/staff/*` (`StaffController`, `DepartmentController`, `PositionController`, `LeaveRequestController`) menunjuk class yang tidak ada sama sekali | Modul absensi/izin staf non-guru (TU, satpam, dll) belum ada — hanya siswa & guru tercakup |
| A5 | Sinkronisasi scan offline (`ScannerController::syncOffline`) memproses batch memakai waktu server saat sync (`now()`), bukan waktu asli scan di perangkat | Absensi dari device yang lama offline bisa tercatat di tanggal/jam sync, bukan jam kejadian sebenarnya. Menyimpang dari `APPLICATION_SPEC.md` §4.3 |
| A6 | ✅ **Selesai** — `rfid_code` kini divalidasi unik lintas tabel siswa+guru lewat endpoint `PUT attendance/rfid/{students,teachers}/{id}` baru | ~~Dua orang berbeda berpotensi punya kode kartu RFID yang sama tanpa ditolak sistem.~~ Lihat catatan Fase 2 |
| A7 | `AttendanceController.php` lama merujuk model yang tidak ada; tidak dirutekan | Kode mati, aman dihapus |
| A8 | Tabel `subject_attendances` & `attendance_summaries` ada di migration tapi tak punya model/controller | Skema belum terpakai — kandidat rollup performa laporan skala besar (lihat §7) |
| A9 | Tidak ada satu pun automated test untuk absensi; 48 skenario `TESTCASE.md` §6–10 masih rencana | Regresi sulit terdeteksi dini |
| A10 | Tidak ada kolom foto profil (siswa/guru) di database — hanya dokumen generik bertipe `photo` di tabel dokumen | Kartu ID (§4a) tidak bisa menampilkan foto asli tanpa migrasi tambahan |
| A11 | Tidak ada library canvas/template editor di frontend (`fabric`, `konva`, `html2canvas`, `jspdf`, `react-to-print` semuanya tidak terpasang) | Builder kartu ID drag-and-drop (§4a) adalah kapabilitas baru, bukan perluasan yang sudah ada |
| A12 | `QrCodeController` (`GET/POST attendance/qr/students/{student}`, `.../regenerate`) tidak melakukan scoping kepemilikan sama sekali — seluruh grup route absensi hanya dijaga `auth:sanctum`, tanpa middleware role tambahan. Berbeda dengan `StudentAttendanceController` yang sudah pakai `visibleTo($user)` | Siapa pun yang login (termasuk role `siswa`) bisa memanggil endpoint ini dengan ID siswa **lain** untuk melihat maupun **regenerate** QR-nya — celah IDOR yang jadi kritis begitu kartu digital dibuka ke login siswa sendiri (§4b), karena QR = kredensial check-in |
| A13 | ✅ **Selesai** — `TeacherAttendanceController` (`index/daily/update/summary`) sekarang di-scope lewat helper `isFullAccess()` (role `Student::ALL_ACCESS_ROLES` bebas penuh; selain itu dipaksa `user_id = auth()->id()` di query, dan `update()` menolak 403 bila bukan baris sendiri) | ~~Guru biasa bisa melihat dan mengedit absensi guru lain~~ — lihat catatan Fase Role-Scoping |
| A14 | ✅ **Selesai** — `PageController::activeClassroomsForAttendance()` kini menerima flag `$scopeToTeacher`; `attendanceStudents()` memfilter ke `teachingClassroomIds()` untuk guru/wali_kelas, dan mengirim `initialClassroom` otomatis bila cuma satu kelas | ~~Guru melihat dropdown kelas penuh berisi kelas bukan miliknya~~ — lihat catatan Fase Role-Scoping |
| A15 | ✅ **Selesai, ditemukan lewat diagnosis langsung `php artisan tinker` di DB dev sungguhan** — package `simplesoftwareio/simple-qrcode` (dipakai `QrCodeGeneratorService`, dasar semua fitur QR/kartu ID) **tidak pernah ter-install** (tidak ada di `composer.json`/`vendor/`) — ini sempat dicatat sebagai catatan "perlu dicek" jauh sebelumnya, sekarang terkonfirmasi jadi bug nyata | "Cetak Kartu" / semua endpoint QR selalu 500 (`Class "SimpleSoftwareIO\QrCode\Facades\QrCode" not found`). Diinstall via `composer require simplesoftwareio/simple-qrcode --ignore-platform-reqs` (mengabaikan syarat `ext-pcntl`/`ext-posix` milik `laravel/horizon`, dua ekstensi Unix-only yang memang tak ada di PHP Windows dan tak relevan untuk request biasa) |
| A16 | ✅ **Selesai** — setelah A15 terpasang, `generateQrImage()` masih gagal karena backend PNG bawaan paket itu butuh ekstensi PHP **imagick**, yang tidak terpasang (dan tak ada jaminan tersedia di produksi) | Diubah ke `format('svg')` (base64 `data:image/svg+xml`) — terverifikasi tanpa imagick, dan `<img src>` merender SVG data URI identik dengan PNG, jadi tidak ada perubahan di frontend |

**Catatan tambahan**: skema sudah mendukung login mandiri siswa — `students.user_id` (unique, wajib) dan role `siswa` (permission `students.view-own`) sudah di-seed di `RoleSeeder.php`. Jadi "kartu digital di login siswa" (§4b) tidak butuh model otentikasi baru, hanya perlu endpoint self-service yang diberi scoping benar (menutup A12).

**Rekomendasi urutan penanganan**: A1 wajib selesai sebelum menambah channel presensi baru apa pun — putuskan satu bahasa sumber kebenaran untuk kolom `status` (rekomendasi: selaraskan ke Inggris karena `employee_attendances` sudah konsisten begitu di semua lapisan, terjemahkan ke Indonesia hanya di Resource API/frontend).

---

## 2. Konsep & Alur Bisnis Absensi

Status kehadiran: **Hadir** (dengan atribut `menit_keterlambatan` bila lewat toleransi — terlambat bukan status terpisah), **Sakit**, **Izin** (hasil approval, bukan input langsung), **Alfa/tanpa keterangan** dan **Belum Scan** (keduanya status *virtual*, dihitung on-the-fly oleh `AttendanceStatusResolver`, bukan baris yang ditulis ke DB tiap hari — konsekuensinya semua pembaca status harus memakai resolver yang sama, lihat A2).

Alur harian: siswa scan RFID/QR di perangkat piket → `AttendanceScanService` mencatat jam masuk & keterlambatan sesuai `AttendanceSetting` tenant → event `StudentCheckedIn` (queued) memicu notifikasi → guru piket/wali kelas bisa koreksi manual lewat halaman **Absensi Harian** per kelas → izin/sakit yang disetujui otomatis mengisi baris kehadiran untuk rentang tanggal terkait (lihat §8).

Peran: **siswa** (subjek scan), **guru piket/wali kelas** (koreksi manual, approval izin kelasnya), **admin/superadmin tenant** (pengaturan, lintas kelas), **orang tua/wali** (penerima notifikasi & pengaju izin lewat portal publik tanpa login).

---

## 3. Kondisi Teknis Existing (ringkas)

Struktur sudah lengkap: migration, model Eloquent, controller, service, resource API, tipe TypeScript untuk absensi siswa/guru, izin, hari libur, QR, dan pengaturan notifikasi — semuanya ada. Yang bermasalah adalah konsistensi antar-lapisan (§1) dan beberapa endpoint yang dirutekan tapi belum benar-benar berfungsi (A3, A4). Detail arsitektur (controller/model/migration per file) sudah dipetakan di riwayat audit; lihat commit/PR terkait dokumen ini untuk detail baris kode bila diperlukan.

---

## 4. Metode Presensi (Check-in Channels)

**Sudah berjalan**: QR Code (generate on-the-fly, regenerable, unduh satuan/massal — paling matang) dan RFID (kolom `rfid_code` ada, endpoint scan menerima sebagai teks biasa dari reader keyboard-emulation, tanpa driver khusus — tapi lihat A6).

**Belum ada — Face Recognition**: perlu kamera device + model deteksi/pencocokan wajah (opsi on-device seperti ML Kit/face-api.js atau cloud seperti AWS Rekognition/Azure Face). Data wajah tergolong data biometrik/data pribadi spesifik menurut UU PDP (UU No. 27/2022); karena subjeknya anak-anak, idealnya perlu persetujuan orang tua, kebijakan retensi jelas, dan enkripsi penyimpanan. Bila dilanjutkan: lakukan pencocokan on-device dan kirim hanya embedding vector (bukan foto mentah) ke server, serta selalu sediakan fallback QR/RFID. **Prioritas: fase lanjutan**, bukan fase pertama.

**Rekomendasi tambahan (lebih murah/cepat dari face recognition)**:
- **Geofencing GPS** — kolom `latitude`/`longitude` per baris absensi dan `location_radius` + `school_latitude/longitude` + `require_location` di `AttendanceSetting` sudah ada, belum benar-benar dipakai untuk validasi. Ini "buah paling rendah" — tinggal aktifkan validasi radius saat absen dari aplikasi mobile.
- **Selfie tanpa pencocokan wajah** — bukti visual, diverifikasi manual guru piket. Kolom `check_in_photo` sudah ada di sisi guru, bisa direplikasi ke sisi siswa.
- **NFC lewat HP guru piket** — alternatif RFID fisik tanpa beli reader terpisah (banyak Android sudah punya NFC reader bawaan).
- **PIN/kode manual sebagai fallback** — penting saat reader/QR scanner rusak atau device offline.
- **Mesin fingerprint fisik** — umum dipakai sekolah, tapi jalurnya integrasi API vendor mesin absensi tertentu (hardware terpisah), bukan dibangun dari nol.

### 4a. Fitur Cetak Kartu ID (Siswa / Guru / Staff)

**Konsep.** Kartu ID berisi: foto, nama, NIS/NIP, kelas/jabatan, QR code, representasi teks nomor RFID (lihat catatan hardware di bawah), logo sekolah, background/tema kartu, dan opsional masa berlaku. Kartu siswa dan kartu guru/staff memakai **template terpisah** (field berbeda: kelas vs jabatan/departemen) tapi mekanisme builder-nya sama.

**Catatan penting soal RFID vs QR di kartu fisik** — ini krusial supaya ekspektasi realistis: QR bisa langsung dicetak dari printer biasa karena kontennya visual. RFID adalah **chip fisik** yang harus tertanam di kartu (kartu RFID blank yang dibeli dari vendor, atau stiker RFID ditempel) dan **diprogram lewat card encoder/writer terpisah** — ini hardware di luar aplikasi web, tidak bisa dilakukan lewat browser. Jadi tanggung jawab aplikasi untuk RFID hanya dua: (1) mendesain visual kartu (foto, nama, logo, dst — sama seperti kartu QR), dan (2) menyediakan/mengekspor daftar `rfid_code` per orang (sebagai teks atau barcode 1D) supaya proses encoding chip oleh vendor/petugas bisa mencocokkan nomor dengan orang yang benar. **Aplikasi tidak menulis chip RFID secara langsung** — ini perlu dikomunikasikan ke pemangku kepentingan supaya tidak salah ekspektasi.

**Penempatan menu.** Sesuai arahan: tombol "Cetak Kartu" ditempatkan kontekstual di menu masing-masing — **menu Siswa** (per-siswa di halaman detail, dan bulk per kelas di halaman index), **menu Guru** (per-guru dan bulk semua guru), dan nanti **menu Staff** begitu modul staf dibangun (lihat A4). Desain template-nya sendiri (satu desain dipakai berulang untuk semua orang di kategori yang sama) disimpan terpusat di satu **Template Editor** per jenis kartu (siswa / guru-staff) — supaya tidak duplikasi builder di banyak tempat, tapi hasil cetaknya tetap diakses dari menu yang relevan.

**Apakah posisi nama & QR bisa diedit langsung (drag-and-drop)?** Bisa, tapi ini kapabilitas baru (A11 — belum ada library canvas apa pun terpasang di frontend saat ini). Konsepnya:
- Tambah tabel baru `card_templates` (`tenant_id`, `type` [`student`/`teacher`], `layout_json`, `background_image_path`, `orientation`, `size_mm`, timestamps).
- `layout_json` menyimpan posisi tiap elemen (field key, x, y, width, height, font size/weight, warna, align) untuk elemen teks (nama, NIS/NIP, kelas/jabatan) dan elemen gambar (logo, background, placeholder foto, placeholder QR).
- Editor visual di frontend pakai library canvas berbasis komponen React, misalnya `react-konva` atau `Fabric.js`, untuk drag-drop interaktif dengan preview langsung memakai data contoh (dummy siswa).
- **Render cetak akhir** (satuan maupun massal per kelas) sebaiknya digenerate di **backend** dari `layout_json` yang sama — pakai `intervention/image-laravel` (sudah terpasang) untuk komposisi gambar, atau `barryvdh/laravel-dompdf` (sudah terpasang) untuk output PDF siap cetak — supaya hasil cetak konsisten lintas browser/printer dan foto tiap siswa otomatis tersisip di posisi yang benar. Editor di frontend untuk preview interaktif, backend untuk hasil final.

**Logo & background.** Tabel `tenants` **sudah punya kolom `logo`** (nullable, siap dipakai langsung tanpa migrasi baru). Untuk background kartu (independen dari logo instansi — misal warna korporat, pola, dsb), perlu kolom baru `background_image_path` di `card_templates`, diunggah lewat halaman Template Editor dan disimpan di storage (S3 sudah terpasang via `league/flysystem-aws-s3-v3`).

**Gap foto profil (A10).** Saat ini **tidak ada** kolom foto profil siswa maupun guru — hanya dokumen generik bertipe `photo` di tabel dokumen guru. Supaya kartu bisa menampilkan foto asli (bukan avatar generik), perlu ditambahkan field foto profil khusus, misalnya lewat `spatie/laravel-medialibrary` yang sudah menjadi dependency dan sudah dipakai untuk dokumen guru — jadi bisa dipakai ulang polanya, bukan integrasi baru dari nol.

**Ukuran & spesifikasi cetak.** Rekomendasi ukuran standar ID card **CR80 (85.6mm × 54mm)** — ukuran kartu ATM/KTP — supaya kompatibel dengan printer kartu ID (Epson/Zebra) atau dicetak di kertas lalu dilaminating/dimasukkan casing PVC. Untuk cetak massal per kelas: grid multi-kartu per lembar A4 dengan crop mark, upgrade dari grid 3 kolom polos yang ada sekarang di `resources/js/pages/attendance/qr-codes/Index.tsx` (fungsi `handlePrintAll`, saat ini hanya QR + nama + NIS tanpa logo/foto/layout proporsional).

**Fase implementasi yang disarankan (hindari overengineering di awal):**

| Fase | Cakupan | Effort |
|---|---|---|
| A | Perbaiki cetak massal existing: tambah logo sekolah (pakai `tenants.logo`), field kelas/jabatan, ukuran proporsional CR80 — layout **tetap fixed di kode**, tanpa builder. Pindahkan/tambahkan akses tombol cetak dari menu Siswa & Guru, bukan hanya dari menu QR Code terpisah | Kecil |
| B | Tambah kolom/field foto profil siswa & guru, tampilkan di kartu | Sedang |
| C | Bangun Template Editor WYSIWYG drag-and-drop (posisi nama/QR/foto/background bisa digeser bebas), simpan sebagai `layout_json` per tenant, render cetak akhir konsisten di backend | Besar |

---

### 4b. Kartu Digital di Login Mobile Siswa (Alternatif Tanpa Cetak)

**Pertanyaan yang diajukan**: bila kartu fisik belum/tidak dicetak, apakah login siswa di aplikasi mobile bisa menampilkan "kartu"-nya sendiri sebagai alternatif absen — dengan syarat sekolah mengizinkan siswa membawa HP?

**Kelayakan: ya, dan fondasinya sudah ada.** Konten QR (`unique_code`) sudah digenerate di server per siswa dan scan endpoint (`AttendanceScanService::processScan`) menerima kode itu sebagai string biasa — tidak peduli kode itu ditampilkan dari kartu cetak atau layar HP. Siswa juga sudah punya jalur login sendiri di skema (`students.user_id`, role `siswa` dengan permission `students.view-own`), jadi ini bukan pekerjaan otentikasi baru — murni menampilkan data yang sudah ada di sesi login siswa sendiri.

**Dua pendekatan teknis:**
1. **Tampilkan ulang QR yang sudah ada** — cara paling murah: layar "Kartu Saya" di app mobile memanggil endpoint self-service (baru, mis. `GET /attendance/qr/me`, scoped ke `auth()->user()->student` — **wajib** dibuat scoped, lihat A12) dan merender QR yang sama seperti di kartu cetak. Discan dengan alat pemindai yang sama seperti memindai kartu fisik. Bekerja di semua perangkat (Android & iOS) karena hanya gambar QR biasa lewat kamera.
2. **Emulasi RFID lewat NFC (HCE — Host Card Emulation)** — Android bisa membuat HP "berpura-pura" jadi kartu RFID lalu ditempelkan ke reader yang sama. **Catatan penting**: iOS membatasi HCE generik hanya untuk program tertentu (mis. Apple Wallet/transit), sehingga emulasi kartu custom **tidak bisa diandalkan bekerja seragam di iPhone**. Karena sekolah pasti punya campuran Android/iOS, jangan jadikan ini metode utama — cukup jadikan QR-di-app (opsi 1) sebagai fallback universal, NFC HCE opsional khusus Android.

**Risiko keamanan yang perlu ditangani sebelum dirilis — ini inti masalahnya, bukan sekadar "tampilkan QR":**

| Risiko | Mitigasi yang direkomendasikan |
|---|---|
| QR di layar HP jauh lebih mudah di-screenshot lalu dikirim ke teman lewat WA untuk "titip absen", dibanding meminjamkan kartu fisik yang butuh serah-terima nyata | Kode **berumur pendek & berotasi** (mis. 60–120 detik, mirip OTP) lewat endpoint baru yang menghasilkan token sementara (disimpan di Redis — sudah tersedia di stack), bukan menampilkan `unique_code` permanen. Screenshot jadi kedaluwarsa sebelum sempat dipakai ulang |
| Siapa saja yang pegang HP dalam keadaan tidak terkunci bisa buka "Kartu Saya" milik siswa lain | Wajibkan sesi login aktif tervalidasi (bukan cuma token tersimpan) setiap kali layar kartu dibuka, idealnya dengan biometrik/PIN aplikasi |
| Kode discreenshot lalu dikirim ke luar sekolah tetap valid selama masih dalam jendela waktu | Gerbang **geofencing** — kode hanya diterbitkan/valid bila GPS aplikasi menunjukkan siswa berada dalam radius sekolah (kolom `location_radius`, `school_latitude/longitude` di `AttendanceSetting` sudah ada, belum dipakai — sama seperti rekomendasi di §4). Kode yang di-screenshot dan dikirim ke luar sekolah jadi tidak berguna kecuali penerimanya juga sedang di area sekolah |
| Layar bisa difoto pakai kamera lain (screenshot-blocking tidak menutup celah ini) | `FLAG_SECURE` (Android) mengurangi screenshot biasa, tapi kombinasikan dengan mitigasi di atas — jangan andalkan satu lapis saja |

**Alternatif jangka panjang** (bukan menampilkan kode sama sekali): tombol "Absen Sekarang" langsung di app yang mengirim check-in tanpa perlu discan siapa pun, divalidasi GPS (+ opsional device-binding). ini menghapus langkah "guru piket memindai kode" sepenuhnya — mengubah model operasional dari checkpoint terpusat menjadi self-report tersebar, dengan pertimbangan anti-spoofing sendiri (aplikasi mock-GPS ada di Android/iOS) — cocok dipasangkan dengan sidak berkala guru piket, bukan pengganti total.

**Pertimbangan operasional:**
- **Wajib jadi pengaturan opt-in per tenant/kelas**, bukan asumsi berlaku semua sekolah — beberapa sekolah melarang HP total untuk jenjang tertentu, persis syarat yang disebutkan.
- **Selalu sediakan jalur cadangan** — baterai habis, HP ketinggalan, atau tidak ada sinyal di gerbang sekolah tetap harus bisa absen (kartu fisik atau override manual guru piket). Kartu digital ini bersifat **alternatif tambahan**, bukan pengganti tunggal.
- Pastikan proses pembuatan akun login untuk siswa (bukan hanya akun orang tua) sudah jadi bagian alur onboarding standar — skema mendukung (`students.user_id` wajib diisi), tapi praktik operasional pembuatan akunnya perlu dipastikan terpisah dari rencana ini.

---

## 5. Notifikasi WhatsApp / Telegram

Infrastruktur sudah matang: `NotificationDispatcher` terpusat, provider Fonnte/Wablas/Telegram, semua lewat queue (Redis + Horizon) sehingga kegagalan kirim tidak menggagalkan proses absen. Template Indonesia per tenant sudah tersedia. Yang perlu diputuskan adalah **kapan** kirim, bukan **bagaimana**.

| Model | Kelebihan | Kekurangan |
|---|---|---|
| Real-time per siswa (cara kerja sekarang) | Orang tua tahu seketika ada kejadian (telat/alfa) | Volume pesan tinggi → biaya API membengkak di sekolah besar; rawan rate limit provider |
| Batch oleh guru (belum ada endpoint-nya) | Satu kali kirim per kelas per hari, hemat biaya, guru bisa review dulu | Ada jeda informasi; bergantung guru tidak lupa kirim |

**Rekomendasi: hybrid.** Real-time hanya untuk kasus kritis (**Alfa** atau **terlambat melebihi toleransi**); untuk "hadir normal" kirim sebagai **ringkasan batch** per kelas — dipicu manual lewat tombol "Kirim Notifikasi" di halaman Absensi Harian, atau terjadwal otomatis (mis. jam 08:00). Flag granular `notify_check_in/out/late/absent` yang sudah ada cukup untuk membedakan ini; yang belum ada adalah endpoint "kirim ringkasan kelas" untuk mode batch.

---

## 6. Kesiapan API untuk Aplikasi Mobile

Menu absensi adalah fitur pertama di aplikasi mobile — urutan perbaikan yang disarankan supaya jalan end-to-end: **(1)** selesaikan A1 (blocker utama data tidak tersimpan), **(2)** verifikasi ulang alur scan termasuk perilaku timestamp offline sync (A5), **(3)** pastikan tim mobile **tidak** memanggil `/staff/*` (A4) maupun `/attendance/reports/pdf` (A3) sampai keduanya diperbaiki, **(4)** baru lanjut fitur tambahan (RFID uniqueness, geofencing, kartu ID).

Endpoint yang **siap pakai** untuk mobile: `/attendance/students/*` (setelah A1 selesai), `/scan/*`, `/attendance/qr/*`, `/attendance/reports/monthly` & `/weekly-trend` (JSON), `/attendance/permissions/*`, `/attendance/holidays/*`, `/public/cek-kehadiran`, `/public/riwayat-kehadiran`, `/public/izin/*`.

---

## 7. Laporan & Export

Harian (`/attendance/students/daily`) sudah lengkap. Bulanan JSON (`/attendance/reports/monthly`) tersedia, tapi PDF rusak (A3). **Excel belum ada sama sekali** untuk absensi — `maatwebsite/excel` sudah terpasang dan sudah dipakai untuk modul akademik, tinggal replikasi pola yang sama (rekap harian per kelas, bulanan per siswa/kelas, rekap keterlambatan). Untuk skala besar jangka panjang, tabel `attendance_summaries` (A8, sudah ada di migration tapi belum dipakai) bisa diaktifkan sebagai pre-aggregation supaya laporan tidak scan seluruh baris mentah tiap kali dibuka.

---

## 8. Alur Izin & Sakit

Alur existing (`LeavePermission` + `LeaveApprovalService`): pengajuan (portal publik atau input guru/admin) → status `pending` → cek tumpang tindih tanggal (sudah ada, praktik baik) → approve/reject oleh wali kelas/guru/admin → **jika approve, otomatis mengisi baris kehadiran** untuk rentang tanggal terkait (kena bug A1 untuk sisi siswa) → orang tua bisa cek status tanpa login.

**Gap**: staf non-guru tidak tercakup (`LeavePermission` hanya punya `student_id`/`teacher_id`, selaras dengan A4); tidak ada notifikasi otomatis saat pengajuan baru masuk (wali kelas harus cek manual badge pending); tidak ada eskalasi/pengingat untuk pengajuan yang mengendap lama.

**Rekomendasi alur** (bahan diskusi): pengajuan masuk → notifikasi real-time ke wali kelas (jarang & penting, cocok real-time); approve/reject → notifikasi otomatis ke orang tua (template `leave_approved`/`leave_rejected` sudah ada, tinggal dipasang). Untuk staf non-guru: putuskan apakah menambah `staff_id` nullable ke tabel yang sama atau membangun modul terpisah.

---

## 9. Roadmap Fase

| Fase | Fokus | Isi |
|---|---|---|
| 0 | **Blocker** | ✅ Selaraskan enum status kehadiran siswa (A1) — selesai |
| 1 | Mobile inti | Validasi ulang alur scan & endpoint absensi siswa end-to-end; kecualikan `/staff/*` & `/reports/pdf` dari mobile |
| 2 | Kanal presensi | ✅ Validasi keunikan RFID lintas tabel (A6); aktifkan geofencing (kolom sudah ada) — selesai |
| 2a | Kartu ID — Fase A | ✅ Perbaiki cetak massal existing: logo, ukuran CR80, akses dari menu Siswa/Guru — selesai |
| 2b | Kartu digital mobile | Tutup A12 (scoping `QrCodeController`), endpoint self-service `GET /attendance/qr/me`, kode berumur pendek + geofencing sebelum dirilis sebagai opsi opt-in per tenant |
| 3 | Notifikasi | ✅ Model hybrid real-time (alfa/telat) + batch (hadir normal) — selesai |
| 3a | Kartu ID — Fase B | ✅ Tambah foto profil siswa/guru, tampilkan di kartu — selesai |
| 4 | Laporan | ✅ Perbaiki PDF (A3), tambah export Excel — selesai. Rekap semester belum dikerjakan (lihat Keputusan Terbuka) |
| 5 | Kartu ID — Fase C | ✅ Template Editor WYSIWYG drag-and-drop (`card_templates`, `layout_json`) — selesai. Unggah background gambar kustom belum dikerjakan (lihat Keputusan Terbuka) |
| 6 | Jangka panjang | Face recognition (kajian privasi UU PDP terpisah), modul absensi/izin staf non-guru (A4) |

---

## 10. Keputusan Terbuka

- **#1** — ✅ **Diputuskan & selesai**: bahasa sumber kebenaran untuk kolom `status` absensi siswa adalah Inggris di DB/enum (selaras `employee_attendances`), terjemahan Indonesia hanya di layer API/frontend. Lihat catatan Fase 0 di atas.
- **#2** — Cakupan staf non-guru: tambah `staff_id` ke `leave_permissions` yang sudah ada, atau modul `/staff/*` terpisah penuh (termasuk absensi harian staf, bukan cuma izin)?
- **#3** — ✅ **Diputuskan & selesai**: Fase A (layout fixed) dan Fase C (Template Editor drag-and-drop) berdua dikerjakan berurutan — lihat catatan Fase 5 di atas. **Lanjutan terbuka**: unggah gambar background kustom per elemen kartu (bukan cuma warna solid) belum dikerjakan — cakupan MVP editor sengaja dibatasi ke posisi/ukuran/warna.
- **#4** — ✅ **Diputuskan & selesai (Fase 3)**: batch dipicu manual oleh guru (tombol "Kirim Notifikasi"). Penjadwalan otomatis (mis. jam 08:00) belum dibangun — bisa ditambah nanti sebagai `Schedule` job terpisah yang memanggil `SendClassAttendanceRecap` per kelas bila diinginkan, tanpa mengubah job yang sudah ada.
- **#5** — Face recognition: lanjutkan sebagai fitur resmi (perlu kajian consent orang tua & retensi data terpisah), atau dikeluarkan dari roadmap dan cukup QR + RFID + geofencing sebagai kanal utama?
- **#6** — Kartu digital mobile (§4b): rilis sebagai kode statis sederhana (risiko "titip absen" via screenshot lebih tinggi, effort kecil), atau langsung investasi kode berumur pendek + geofencing sejak awal (lebih aman, effort lebih besar)? Juga: opt-in ini diatur di level tenant (seluruh sekolah) atau per kelas/jenjang (mengikuti kebijakan HP yang biasanya berbeda per jenjang)?
