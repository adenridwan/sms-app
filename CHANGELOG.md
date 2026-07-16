# Changelog

Semua perubahan penting pada proyek ini didokumentasikan di file ini.

## [Unreleased] - 2026-07-16

### Perbaikan UI Sidebar & Layout

#### Sidebar (`backend/resources/js/components/ui/sidebar.tsx`)
- **Collapse rapi (icon-only)**: saat sidebar ditutup, hanya icon yang terlihat — teks, chevron, dan submenu otomatis tersembunyi; icon di tengah; lebar collapsed `3.5rem`; `overflow-hidden` agar tidak ada konten bocor; transisi lebar lebih halus (`300ms ease-in-out`).
- **Hover menarik**: menu hover memakai `bg-primary/10 + text-primary`; menu aktif ditandai latar `primary` penuh dengan shadow; submenu aktif memakai `bg-primary/15 + text-primary`.
- **Scroll vertikal**: area menu (`SidebarContent`) memakai `overflow-y-auto` dengan scrollbar tipis; header dan footer sidebar tetap (sticky).
- Tooltip nama menu otomatis muncul saat sidebar dalam mode icon-only.

#### Komponen Baru
- `backend/resources/js/components/ui/collapsible.tsx` — wrapper Radix Collapsible dengan animasi expand/collapse (`collapsible-down/up`).
- `backend/resources/js/vite-env.d.ts` — deklarasi tipe `vite/client` (memperbaiki error `import.meta.env` / `import.meta.glob`).

#### MainLayout (`backend/resources/js/layouts/MainLayout.tsx`) — ditulis ulang
- **Menu expand/shrink**: menu utama yang punya submenu kini collapsible (komponen `NavGroup`) dengan chevron berputar; grup otomatis terbuka jika salah satu submenunya sedang aktif (deteksi dari URL); saat sidebar collapsed, klik grup akan membuka kembali sidebar sekaligus submenunya.
- **Deteksi menu aktif**: berdasarkan URL saat ini; hanya submenu dengan kecocokan path terpanjang yang ditandai aktif.
- **Header sidebar**: menampilkan logo sekolah (`tenant.logo`) jika tersedia; default tetap icon `GraduationCap` + nama sekolah (`tenant.name` / nama aplikasi).
- **Footer sidebar**: avatar user (link ke profil), nama + email (truncate), dan tombol logout dengan icon `LogOut` (hover merah). Saat collapsed berubah jadi susunan vertikal icon.
- **Navbar (header)**: di ujung kanan ada dropdown berisi:
  - Nama + email user login
  - Link "Profil Saya"
  - Pilihan tema **Mode Terang / Mode Gelap** (icon Sun/Moon, tanda centang pada tema aktif)
  - Tombol **Keluar** (merah, `POST /logout`)
- **Tema persist**: pilihan dark/light disimpan di `localStorage`, di-apply sebelum render pertama di `app.tsx` (tanpa flash), fallback ke `prefers-color-scheme`.
- **Footer section**: footer di bawah konten utama — `© {tahun} {nama sekolah}. Hak cipta dilindungi.` + label "School Management System".

### Halaman Error Elegan (mengganti blank hitam 404)
- **Baru** `backend/resources/js/pages/Error.tsx` — halaman error full-screen dengan gradient dekoratif, icon per status, kode status besar, dan tombol "Kembali" & "Ke Beranda". Mendukung status 401, 403, 404, 419, 429, 500, 503, plus tampilan default "Segera Hadir" untuk halaman yang masih dalam pengembangan.
- `backend/resources/js/app.tsx` — jika komponen halaman Inertia belum dibuat, otomatis fallback ke halaman Error ("Segera Hadir") alih-alih layar hitam yang tidak bisa ditutup.
- `backend/routes/web.php` — `Route::fallback` merender halaman Error 404 untuk URL yang tidak dikenal.
- `backend/bootstrap/app.php` — `$exceptions->respond()`: semua error web (401/403/404/419/429/500/503) dirender lewat halaman Error Inertia; 419 di-redirect back dengan pesan "sesi berakhir"; halaman debug tetap tampil untuk error 500/503 saat `APP_DEBUG=true`.

### Perbaikan Error TypeScript (pre-existing)
Type-check `tsc --noEmit` kini **0 error**; `npm run build` lolos penuh.

| File | Perbaikan |
|---|---|
| `hooks/useOfflineRoster.ts` | Hapus interface `RosterMetadata` yang tidak terpakai |
| `pages/attendance/holidays/Index.tsx` | Hapus import `Calendar` yang tidak terpakai |
| `pages/attendance/permissions/Index.tsx` | Hapus import `Input`, `FileText` yang tidak terpakai; fungsi `handleDelete` **dipulihkan & difungsikan** (lihat bagian Kepatuhan Spesifikasi) |
| `pages/attendance/qr-codes/Index.tsx` | Hapus import `Badge` yang tidak terpakai |
| `pages/attendance/settings/Index.tsx` | Hapus import `Textarea`, `CheckCircle` yang tidak terpakai |
| `pages/attendance/students/Index.tsx` | Hapus import `router`; pasang tombol edit (icon pensil, kolom "Aksi") sehingga dialog "Edit Absensi" (status + catatan) yang sudah ada kini berfungsi |
| `pages/scanner/Index.tsx` | Hapus import `CardDescription` dan fungsi `formatTime` yang tidak terpakai |
| `pages/students/Create.tsx` | Hapus import `router` dan prop `classRooms` yang tidak terpakai |
| `services/api.ts` | Hapus import `AxiosResponse` yang tidak terpakai |
| `services/attendance.ts` | `attendanceSettingsApi.update`: tipe `location_radius`, `school_latitude`, `school_longitude` menjadi `number \| null` (selaras dengan `AttendanceSettings`) |
| `types/index.ts` | Tambah `user_type?: string` pada `User`; tambah index signature `[key: string]: unknown` pada `PageProps` (kompatibel dengan constraint Inertia `usePage`) |

### Kepatuhan Spesifikasi (audit terhadap APPLICATION_SPEC.md)

Semua perbaikan TypeScript di atas diaudit ulang terhadap logika bisnis di `APPLICATION_SPEC.md`:

- **Dipulihkan** — `pages/attendance/permissions/Index.tsx`: spec §5.1 menyatakan pengajuan izin *"bisa dihapus"*, sedangkan fungsi `handleDelete` sempat dihapus karena tidak terpakai. Fungsi ini dipulihkan sekaligus difungsikan: tombol hapus (icon `Trash2`) di kolom Aksi untuk semua status, dengan dialog konfirmasi `AlertDialog` ("Hapus Perizinan") sebelum memanggil `leavePermissionApi.delete`.
- **Sesuai spec** — tombol edit yang ditambahkan di `pages/attendance/students/Index.tsx` selaras dengan spec §5.1 (aksi "Ubah Kehadiran", modal edit manual §4.4).
- **Tidak berdampak** — penghapusan prop `classRooms` di `pages/students/Create.tsx` aman karena field "Kelas Masuk" berupa input teks bebas (`entry_class`), bukan dropdown. Penghapusan lain (icon, `formatTime`, `RosterMetadata`, `AxiosResponse`, dsb.) hanya kosmetik tanpa dampak logika bisnis.

### Fitur Baru: Pengaturan Kelas & Jurusan (submenu + CRUD + Import/Export)

#### Menu (`backend/resources/js/layouts/MainLayout.tsx`)
- Menu **Pengaturan** diubah dari link tunggal menjadi grup collapsible dengan submenu:
  - **Umum** (`/settings`, permission `settings.view`)
  - **Kelas** (`/settings/class-rooms`, permission `classrooms.view`)
  - **Jurusan** (`/settings/majors`, permission `majors.view`)
  - **Pengguna** (`/settings/users`, permission `users.view`)

#### Backend — Model & Resource (baru)
- `app/Infrastructure/Persistence/Eloquent/Academic/` — `Major`, `GradeLevel`, `AcademicYear`, `Semester`, `Classroom` (pola HasUuid + BelongsToTenant + SoftDeletes, mengikuti struktur migrasi `create_academic_tables`).
- `app/Http/Resources/Academic/` — `MajorResource`, `GradeLevelResource`, `ClassroomResource` (relasi nested `academic_year`/`grade_level`/`major`/`homeroom_teacher` via `whenLoaded`, `students_count` via `whenCounted`).
- Perbaikan: import model di `AcademicYearController` & `SemesterController` diarahkan ke namespace Eloquent yang benar (sebelumnya menunjuk `App\Models\Academic\*` yang tidak ada).

#### Backend — Controller & Route API (baru)
- `app/Http/Controllers/Api/V1/Academic/MajorController.php` — CRUD jurusan (search, filter `is_active`, pagination), guard hapus jika masih dipakai kelas, plus endpoint `export`, `template`, `import`.
- `app/Http/Controllers/Api/V1/Academic/ClassroomController.php` — CRUD kelas (validasi tahun ajaran/tingkat wajib, kode unik per tahun ajaran), guard hapus jika ada siswa terdaftar, endpoint `students`, `export`, `template`, `import` (import terikat ke tahun ajaran aktif).
- `app/Http/Controllers/Api/V1/Academic/GradeLevelController.php` — CRUD tingkat.
- `routes/api_v1.php` — route statis `majors/template|export|import` dan `classrooms/template|export|import` didaftarkan **sebelum** `apiResource` (agar tidak tertangkap `{param}`); import memakai throttle `uploads`.

#### Backend — Import/Export Excel (maatwebsite/excel)
- `app/Exports/Academic/` — `MajorsExport`, `MajorsTemplateExport` (kolom: kode, nama, deskripsi, aktif + 2 baris contoh), `ClassroomsExport`, `ClassroomsTemplateExport` (kolom: kode, nama, tingkat, jurusan, ruangan, kapasitas, aktif + contoh X-IPA-1/X-IPS-1).
- `app/Imports/Academic/MajorsImport.php` & `ClassroomsImport.php` — upsert berdasarkan kode (restore jika soft-deleted), laporan hasil `{created, updated, errors[]}` dengan pesan error per baris ("Baris {n}: ..."); import kelas memvalidasi kode tingkat/jurusan terhadap data master.

#### Backend — Web Route, Page & Seeder
- `routes/web.php` — route `GET /settings/class-rooms` dan `GET /settings/majors`.
- `PageController` — method `settingsClassRooms()` dan `settingsMajors()`.
- `PermissionSeeder` — tambah `majors.view` & `majors.manage`.
- `RoleSeeder` — `admin` +majors.view/manage; `kepala_sekolah` +majors.view; `wakil_kepala_sekolah` +majors.view/manage.

#### Frontend (baru)
- `services/api.ts` — service baru `majorsApi`, `gradeLevelsApi`, `classroomsApi` (endpoint `/academic/*`; export/template pakai `responseType: 'blob'`, import multipart); tipe `ImportResult`; tipe respons `academicYearsApi.list` dikoreksi ke `ApiResponse<PaginatedResponse<...>>`.
- `types/index.ts` — interface baru `Major`, `GradeLevel`, `Classroom` (selaras dengan resource backend).
- `pages/settings/Majors.tsx` — halaman Jurusan: tabel + pencarian + pagination, dialog tambah/edit (kode, nama, deskripsi, switch aktif), konfirmasi hapus (AlertDialog), tombol **Export** (`jurusan.xlsx`), dialog **Import** dengan tombol **Download Template** (`template-import-jurusan.xlsx`), pilih file (.xlsx/.xls/.csv), dan ringkasan hasil import (ditambahkan/diperbarui/daftar baris gagal).
- `pages/settings/ClassRooms.tsx` — halaman Kelas: tabel (kode, nama, tingkat, jurusan, tahun ajaran, ruangan, kapasitas, status) + pencarian + pagination, dialog tambah/edit dengan dropdown Tahun Ajaran (auto-pilih yang aktif)/Tingkat/Jurusan (opsional "Tanpa Jurusan"), konfirmasi hapus, tombol **Export** (`kelas.xlsx`), dialog **Import** + **Download Template** (`template-import-kelas.xlsx`) + ringkasan hasil import.
- Verifikasi: `tsc --noEmit` 0 error; `vite build` sukses.

### Bugfix: 500 saat super admin menyimpan Jurusan/Kelas (tenant_id null)

Root cause: akun `super_admin` memiliki `tenant_id = NULL`, sedangkan trait `BelongsToTenant` langsung mengembalikan `tenant_id` user meskipun null — fallback header `X-Tenant-ID` tidak pernah tercapai, dan frontend juga tidak pernah mengirim header tersebut → insert `majors` gagal (constraint `NOT NULL tenant_id` → HTTP 500).

- `BelongsToTenant.php` — hanya pakai `tenant_id` user jika terisi; super admin fall-through ke container/header `X-Tenant-ID`. Relasi `tenant()` diarahkan ke `App\Models\Tenant` (kelas `Infrastructure\...\Tenant\Tenant` tidak ada).
- `EnsureTenantMiddleware.php` — lookup subdomain juga diarahkan ke `App\Models\Tenant`.
- `ApiController.php` — helper baru `currentTenantId()` (tenant user → header `X-Tenant-ID`).
- `MajorController`, `ClassroomController`, `GradeLevelController` — guard di `store()`/`import()`: 422 dengan pesan jelas ("Konteks sekolah (tenant) tidak ditemukan...") alih-alih 500.
- `HandleInertiaRequests.php` — share prop `tenants` (id, name, logo) khusus super admin.
- `MainLayout.tsx` — **tenant switcher** di navbar (hanya super admin): dropdown daftar sekolah, pilihan disimpan di `localStorage` (`active_tenant_id`, default sekolah pertama), ganti sekolah me-reload halaman; nama sekolah terpilih tampil di header sidebar.
- `services/api.ts` — interceptor axios mengirim header `X-Tenant-ID` dari `localStorage` di semua request API (aman untuk user biasa: server selalu memprioritaskan `tenant_id` milik user).
- `types/index.ts` — `PageProps.tenants?: Tenant[] | null`.
- Verifikasi: `tsc` 0 error, `vite build` sukses, `php -l` bersih, dan uji tinker: create `Major` dengan header `X-Tenant-ID` → `tenant_id` terisi benar.

### Catatan
- Route `POST /logout` sudah tersedia di `backend/routes/auth.php` (dipakai tombol logout di sidebar & navbar).
- Notifikasi bell di navbar masih hardcoded (badge "3") — belum ada sistem notifikasi.
- Jalankan ulang seeder permission/role (`php artisan db:seed --class=PermissionSeeder && php artisan db:seed --class=RoleSeeder`) agar permission `majors.*` tersedia.
