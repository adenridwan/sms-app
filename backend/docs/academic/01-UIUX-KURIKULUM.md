# Plan UI/UX — Halaman Kurikulum & Mata Pelajaran

Mengacu pola yang sudah dipakai di `Majors.tsx`, `Years.tsx`, `GradeLevels.tsx`, `ClassRooms.tsx` — semua pakai **shadcn/ui**, Card+Table+Dialog+AlertDialog, tanpa navigasi halaman terpisah untuk create/edit.

## Keputusan final: dua halaman terpisah, bukan nested-expand

Rancangan awal (di bawah, §3) mengusulkan Mata Pelajaran sebagai *expandable row* di dalam halaman Kurikulum. Setelah dicek ulang, `app/Support/MenuRegistry.php` **sudah lebih dulu** mendefinisikan "Kurikulum" (`academic.curricula`, permission `curricula.view/manage`) dan "Mata Pelajaran" (`academic.subjects`, permission `subjects.view/manage`) sebagai **dua item sidebar terpisah**. Supaya konsisten dengan struktur menu & permission yang sudah ada (bukan didesain ulang tanpa alasan kuat), implementasi final memakai:

- **Halaman Kurikulum** (`/academic/curricula`) — flat CRUD sederhana, identik `Majors.tsx` (lihat §2).
- **Halaman Mata Pelajaran** (`/academic/subjects`) — flat CRUD dengan `Select` untuk memilih Kurikulum, persis pola `ClassRooms.tsx` yang punya `Select` untuk Jurusan/Tingkat Kelas (lihat §3, direvisi).

§1 dan §3 di bawah tetap didokumentasikan sebagai referensi wireframe, hanya bagian "expand" yang diganti jadi halaman `/academic/subjects` mandiri.

## 1. Layout halaman list (`/academic/curricula`)

```
Kurikulum                                          [+ Tambah Kurikulum]
Kelola kurikulum dan mata pelajaran yang menyertainya

┌─ Card ──────────────────────────────────────────────────────────────┐
│ [🔍 Cari kurikulum...]                                    [⟳]       │
│                                                                       │
│ ▸ Nama              Kode   Mapel   Status     Aksi                  │
│ ────────────────────────────────────────────────────────────────── │
│ ▸ Kurikulum Merdeka  KM     12     [Aktif]     [Edit][⋮]            │
│ ▾ Kurikulum 2013 R.  K13R    7     [Aktif]     [Edit][⋮]            │
│   └─ (expanded — lihat §3)                                          │
│ ────────────────────────────────────────────────────────────────── │
│ Menampilkan 1-2 dari 2 data          [Sebelumnya] [Berikutnya]      │
└────────────────────────────────────────────────────────────────────┘
```

- Kolom **Mapel**: badge angka (`subjects_count`), klik row atau chevron (▸/▾) untuk expand.
- Aksi `⋮` (DropdownMenu): "Edit", "Kelola Mata Pelajaran" (shortcut expand), "Hapus". Hapus di-guard: kalau `subjects_count > 0`, tombol Hapus disabled + tooltip "Masih ada N mata pelajaran terkait" (pola sama dengan `ChecksReferentialUsage` untuk Tahun Ajaran/Semester).

## 2. Dialog Tambah/Edit Kurikulum

| Field | Komponen | Wajib | Catatan |
|---|---|---|---|
| Nama Kurikulum | `Input` | ya | placeholder "Kurikulum Merdeka" |
| Kode | `Input` | opsional | placeholder "KM", auto-uppercase saat blur |
| Deskripsi | `Textarea` | opsional | 2-3 baris |
| Status Aktif | `Switch` (label+deskripsi kiri, switch kanan) | — | default `true` saat tambah baru |

Pola identik `Majors.tsx` — kurikulum entitas ringan, tidak perlu field lain.

## 3. Expanded row → Mata Pelajaran per Kurikulum

```
▾ Kurikulum 2013 Revisi   K13R   7   [Aktif]   [Edit][⋮]
  ┌─ Mata Pelajaran ────────────────────────── [+ Tambah Mapel] ─┐
  │ Nama              Kode   Kategori         Status   Aksi      │
  │ ─────────────────────────────────────────────────────────── │
  │ Matematika Wajib   MTK    Wajib            Aktif    [✎][🗑]  │
  │ Fisika              FIS   Peminatan IPA    Aktif    [✎][🗑]  │
  │ Bahasa Daerah       MLK   Muatan Lokal     Aktif    [✎][🗑]  │
  └────────────────────────────────────────────────────────────┘
```

Mini-dialog Tambah/Edit Mata Pelajaran (`curriculum_id` otomatis terisi dari konteks kurikulum yang sedang di-expand):

| Field | Komponen | Wajib | Catatan |
|---|---|---|---|
| Nama Mata Pelajaran | `Input` | ya | |
| Kode | `Input` | opsional | |
| Kategori | `Select` | ya | opsi: Wajib / Peminatan IPA / Peminatan IPS / Muatan Lokal — enum tetap (bukan free text) supaya konsisten & bisa badge warna beda per kategori |
| Deskripsi | `Textarea` | opsional | |
| Status Aktif | `Switch` | — | |

### Di luar scope (fase berikutnya)

- Relasi Mata Pelajaran ↔ Tingkat Kelas/Jurusan (`subject_grade_levels`: jam pelajaran, KKM) — lebih pas jadi dialog "Kelola Tingkat Kelas & KKM" di halaman/detail Mata Pelajaran tersendiri.
- Assign guru ke mapel (`teacher_subjects`) — pola serupa "Kelola Guru Pengampu" di ClassRooms, tapi lebih pas ada di sisi Guru atau Mata Pelajaran, bukan di Kurikulum.

## 4. Konsistensi teknis

- Icon: `lucide-react` (`Plus`, `Pencil`, `Trash2`, `ChevronRight`, `RefreshCw`, `Search`, `MoreHorizontal`).
- Toast: `sonner` (`toast.success` / `toast.error`) + helper `getErrorMessage` yang gali pesan 422 dari Laravel (disalin dari `Years.tsx`).
- Search + pagination manual (state `page`/`search`), identik ke-4 halaman lain.
- Delete pakai `AlertDialog`, guard ganda (`deletingRef` + disable saat proses) seperti versi hardened di `ClassRooms.tsx`.
- Tipe baru `Curriculum`/`Subject` di `resources/js/types/index.ts`, service `curriculaApi`/`subjectsApi` di `resources/js/services/api.ts`, mengikuti bentuk `majorsApi`.
- Perbaikan link sidebar (`MainLayout.tsx`) dan `routes/web.php` supaya `/academic/curricula` benar-benar terdaftar (sebelumnya 404).
