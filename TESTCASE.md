# Test Case — SMS Enterprise (Sistem Manajemen Sekolah)

Dokumen ini berisi test case pengujian fungsional & non-fungsional untuk aplikasi SMS Enterprise (Laravel 12 + Inertia React + PostgreSQL 16), disusun berdasarkan rute API `backend/routes/api_v1.php` dan logika bisnis di `APPLICATION_SPEC.md`.

**Prioritas:** P1 = kritikal (bisnis inti / keamanan), P2 = penting, P3 = normal.

## Prasyarat Umum

1. Aplikasi berjalan (`SETUP.md` — lokal atau Docker), database ter-migrate dan ter-seed (`php artisan migrate --seed`).
2. Akun seed default (semua password: `password`):

| Email | Role |
|---|---|
| `superadmin@sms.local` | super_admin |
| `admin@demo.sms.local` | admin |
| `kepsek@demo.sms.local` | kepala_sekolah |
| `guru1@demo.sms.local` | guru |
| `guru2@demo.sms.local` | wali_kelas |
| `tu@demo.sms.local` | tata_usaha |
| `bendahara@demo.sms.local` | bendahara |
| `pustakawan@demo.sms.local` | pustakawan |
| `siswa1@demo.sms.local` | siswa |
| `ortu1@demo.sms.local` | orang_tua |

3. Semua endpoint API berprefix `/api/v1/`. Endpoint terproteksi butuh header `Authorization: Bearer {token}` (Sanctum).

---

## 1. Autentikasi (AUTH)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| AUTH-01 | Login sukses | POST `/auth/login` dengan kredensial valid | `admin@demo.sms.local` / `password` | 200; response berisi token dan data user beserta role | P1 |
| AUTH-02 | Login password salah | POST `/auth/login` dengan password salah | password `salah123` | 401/422; pesan error; token tidak diterbitkan | P1 |
| AUTH-03 | Login email tidak terdaftar | POST `/auth/login` email acak | `tidakada@x.com` | 401/422; tidak membocorkan apakah email terdaftar | P2 |
| AUTH-04 | Validasi field kosong | POST `/auth/login` tanpa email/password | body kosong | 422 dengan error validasi per field | P2 |
| AUTH-05 | Rate limit login | POST `/auth/login` berulang dengan password salah melebihi limit throttle `auth` | ≥ limit percobaan/menit | 429 Too Many Requests | P1 |
| AUTH-06 | Get profil sendiri | GET `/auth/me` dengan token valid | token AUTH-01 | 200; data user yang sedang login | P2 |
| AUTH-07 | Akses tanpa token | GET `/auth/me` tanpa header Authorization | — | 401 Unauthenticated | P1 |
| AUTH-08 | Logout | POST `/auth/logout`, lalu pakai ulang token yang sama | token AUTH-01 | Logout 200; request berikutnya dengan token itu → 401 | P1 |
| AUTH-09 | Ubah password | PUT `/auth/password` dengan password lama benar; login ulang dengan password baru | old: `password`, new: `Password!23` | 200; login dengan password baru sukses, password lama gagal | P2 |
| AUTH-10 | Ubah password — password lama salah | PUT `/auth/password` password lama salah | old: `salah` | 422; password tidak berubah | P2 |
| AUTH-11 | Forgot & reset password | POST `/auth/forgot-password` lalu POST `/auth/reset-password` dengan token reset | email seed valid | Email/token reset terkirim; reset sukses; bisa login dengan password baru | P2 |
| AUTH-12 | Update profil | PUT `/auth/profile` ubah nama | nama baru | 200; GET `/auth/me` menampilkan nama baru | P3 |

## 2. Otorisasi & Role (RBAC)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| RBAC-01 | Admin akses rute admin | GET `/admin/users` sebagai admin | token admin | 200; daftar user | P1 |
| RBAC-02 | Non-admin ditolak dari rute admin | GET `/admin/users` sebagai guru/siswa | token guru1 | 403 Forbidden | P1 |
| RBAC-03 | Rute super-admin ditolak untuk admin biasa | GET `/super-admin/tenants` sebagai admin | token admin | 403 Forbidden | P1 |
| RBAC-04 | Super admin akses tenant | GET `/super-admin/tenants` sebagai super_admin | token superadmin | 200; daftar tenant | P1 |
| RBAC-05 | Wali kelas hanya melihat kelasnya | Login `guru2` (wali_kelas), akses data presensi/izin siswa | kelas yang diampu vs kelas lain | Data kelas lain tidak muncul; aksi tulis ke siswa kelas lain ditolak di server (bukan hanya disembunyikan di UI) | P1 |
| RBAC-06 | IDOR antar-tenant | Sebagai admin tenant A, GET/PUT resource milik tenant lain via ID langsung | id siswa tenant lain | 403/404; data tenant lain tidak bocor | P1 |
| RBAC-07 | Kepala sekolah read-only | Login kepsek, coba edit data master (POST/PUT siswa/guru) | token kepsek | Ditolak sesuai permission; GET laporan/presensi sukses | P2 |

## 3. Master Data Akademik (ACD)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| ACD-01 | CRUD tahun ajaran | POST/GET/PUT/DELETE `/academic/years` | "2026/2027" | Semua operasi sukses dengan validasi format | P2 |
| ACD-02 | Aktivasi tahun ajaran | POST `/academic/years/{id}/activate` | tahun baru | Tahun tsb aktif; tahun aktif sebelumnya otomatis non-aktif (hanya 1 aktif) | P1 |
| ACD-03 | CRUD jurusan | POST `/academic/majors` nama unik, lalu POST duplikat | "RPL" 2× | Create pertama 201; duplikat 422 (unique) | P2 |
| ACD-04 | Hapus jurusan yang masih punya kelas | DELETE jurusan yang direferensikan kelas | jurusan seed | Ditolak dengan pesan jelas (restrict), bukan error 500 | P1 |
| ACD-05 | CRUD kelas + wali kelas | POST `/academic/classrooms` dengan tingkat, jurusan, indeks, wali kelas | "X RPL A", wali guru2 | 201; label kelas tampil format "{tingkat} {jurusan} {indeks}" | P2 |
| ACD-06 | Hapus kelas yang masih punya siswa | DELETE classroom berisi siswa | kelas seed | Ditolak dengan pesan jelas | P1 |
| ACD-07 | Import jurusan/kelas via CSV | GET template → isi → POST `/academic/majors/import` | file dari template | Baris valid masuk; baris invalid dilaporkan (row error), tidak menggagalkan seluruh file | P2 |
| ACD-08 | Import file salah format | POST import dengan file .exe / CSV kolom salah | file invalid | 422; pesan jelas; tidak ada data partial yang korup | P2 |
| ACD-09 | Export data | GET `/academic/majors/export`, `/academic/classrooms/export` | — | File terunduh; isi sesuai data di DB | P3 |
| ACD-10 | CRUD mapel, kurikulum, tingkat, slot waktu | apiResource `subjects`, `curricula`, `grade-levels`, `time-slots` | data valid | CRUD normal + validasi | P3 |
| ACD-11 | CRUD & generate jadwal | POST `/academic/schedules` manual; POST `/academic/schedules/generate` | jadwal bentrok (guru sama, jam sama) | Jadwal bentrok ditolak/terdeteksi; generate menghasilkan jadwal tanpa konflik | P2 |
| ACD-12 | Daftar siswa & jadwal per kelas | GET `/academic/classrooms/{id}/students` dan `/schedule` | kelas seed | 200; hanya siswa/jadwal kelas tsb | P3 |

## 4. Siswa & Wali (STD)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| STD-01 | Create siswa lengkap | POST `/students` semua field wajib | NIS unik, nama, kelas, JK, no HP | 201; `unique_code` QR ter-generate otomatis | P1 |
| STD-02 | NIS duplikat | POST `/students` dengan NIS yang sudah ada | NIS seed | 422 unique | P1 |
| STD-03 | Pencarian case-insensitive | GET `/students?search=` huruf kecil untuk nama yang tersimpan kapital | "budi" vs "BUDI" | Hasil sama, tidak sensitif kapital | P2 |
| STD-04 | Pagination | GET `/students?page=2&per_page=10` | seed >10 siswa | Meta pagination benar (total, last_page); tidak ada data duplikat antar halaman | P2 |
| STD-05 | Update & pindah kelas | PUT `/students/{id}` ganti kelas | kelas lain | 200; presensi lama tetap menyimpan snapshot kelas lama | P2 |
| STD-06 | Hapus siswa | DELETE `/students/{id}` | siswa uji | Terhapus; relasi (fee, presensi) diperlakukan sesuai aturan cascade tanpa error | P2 |
| STD-07 | Relasi detail siswa | GET `{id}/guardians`, `/enrollments`, `/grades`, `/attendance`, `/fees`, `/achievements` | siswa seed | 200; masing-masing hanya data siswa tsb | P2 |
| STD-08 | Enroll siswa | POST `/students/{id}/enroll` ke kelas & tahun ajaran | tahun aktif | 201; enrollment ganda di tahun sama ditolak | P2 |
| STD-09 | CRUD wali/guardian | apiResource `/students/guardians` | data wali | CRUD normal; satu wali bisa terhubung ke >1 siswa | P3 |
| STD-10 | Validasi RFID unik lintas tabel | Set `rfid_code` siswa dengan kode yang sudah dipakai guru (dan sebaliknya) | kode RFID guru | 422; kode RFID harus unik lintas siswa+guru | P1 |

## 5. Guru & Staff (TCH)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| TCH-01 | CRUD guru | POST/GET/PUT/DELETE `/teachers` | NUPTK, nama, JK, alamat, no HP | CRUD sukses; `unique_code` auto-generate saat create | P1 |
| TCH-02 | Hapus guru yang masih wali kelas | DELETE guru yang jadi wali kelas | guru2 | Ditolak (restrict) dengan pesan jelas | P1 |
| TCH-03 | Assign mapel ke guru | POST `/teachers/{id}/assign-subjects` | 2 mapel | 200; GET `{id}/subjects` menampilkan mapel tsb | P2 |
| TCH-04 | Jadwal & kelas guru | GET `/teachers/{id}/schedule`, `/classrooms` | guru seed | 200; hanya jadwal/kelas guru tsb | P3 |
| TCH-05 | CRUD staff, departemen, posisi | apiResource `/staff`, `/staff/departments`, `/staff/positions` | data valid | CRUD normal | P3 |
| TCH-06 | Cuti staff: approve/reject | POST leave-request → approve → coba approve ulang | rentang tanggal valid | Approve sukses sekali; approve/reject atas request yang sudah final ditolak | P2 |

## 6. Absensi — Scan QR/RFID (SCN) — *bisnis inti*

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| SCN-01 | Check-in siswa tepat waktu | POST `/scan` dengan `unique_code` siswa sebelum `jam_masuk_limit` | scan 06:45, limit 07:00 | Presensi Hadir dibuat, `jam_masuk` terisi, `menit_keterlambatan`=0 | P1 |
| SCN-02 | Check-in terlambat | POST `/scan` setelah `jam_masuk_limit` | scan 07:20, limit 07:00 | `menit_keterlambatan`=20; keterangan "Terlambat 20 menit"; `poin_pelanggaran` siswa bertambah 20 | P1 |
| SCN-03 | Scan ganda hari sama | POST `/scan` check-in 2× untuk siswa sama di hari sama | kode SCN-01 | Scan kedua ditolak: "sudah absen hari ini"; tidak ada baris presensi ganda (unique siswa+tanggal) | P1 |
| SCN-04 | Check-out | POST `/scan` mode pulang setelah check-in | kode SCN-01 | `jam_keluar` terisi; keterangan di-reset | P1 |
| SCN-05 | Check-out tanpa check-in | POST `/scan` mode pulang untuk siswa yang belum check-in | siswa lain | Ditolak: "belum absen hari ini" | P1 |
| SCN-06 | Scan di hari libur | Tambah hari ini ke holidays → POST `/scan` | tanggal hari ini | Ditolak dengan pesan berisi keterangan libur; tidak ada presensi tertulis | P1 |
| SCN-07 | Scan kode tidak dikenal | POST `/scan` dengan kode acak | `xxx-000` | 404/422; pesan "kode tidak ditemukan"; tanpa error 500 | P1 |
| SCN-08 | Scan via RFID | POST `/scan` dengan `rfid_code` (bukan unique_code) | RFID siswa & guru | Orang teridentifikasi benar (siswa dicek dulu, lalu guru) | P1 |
| SCN-09 | Scan guru tidak menghitung keterlambatan | Check-in guru setelah jam limit | scan guru 07:30 | Presensi guru dibuat tanpa poin pelanggaran (khusus siswa saja) | P2 |
| SCN-10 | Sync offline pakai `scanned_at` | POST `/scan/sync-offline` dengan antrian scan berisi `scanned_at` kemarin/jam lampau | scanned_at 07:05 kemarin, sync hari ini | Presensi tercatat pada tanggal & jam `scanned_at`, bukan waktu server saat sync; keterlambatan dihitung dari `scanned_at` | P1 |
| SCN-11 | Sync offline idempoten | Kirim batch sync yang sama 2× | batch SCN-10 | Tidak ada duplikat; item yang sudah masuk dilaporkan skip/failed tanpa merusak yang lain | P1 |
| SCN-12 | Lookup & bootstrap scanner | GET `/scan/bootstrap`, POST `/scan/lookup` | kode valid | 200; data identitas untuk layar konfirmasi kiosk | P3 |
| SCN-13 | Notifikasi WA/Telegram gagal tidak membatalkan scan | Set kredensial WA salah → scan | provider invalid | Scan tetap sukses tercatat; kegagalan notifikasi hanya masuk log | P1 |

## 7. Absensi — Manual & Rekap (ATT)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| ATT-01 | Presensi harian per kelas | GET `/attendance/students/daily?date=&classroom=` | tanggal + kelas seed | Semua siswa kelas tampil dengan status; yang tanpa baris = "Belum Scan" (sebelum jam pulang) atau "Alfa" (setelahnya / tanggal lampau) | P1 |
| ATT-02 | Aturan Belum Scan vs Alfa | Cek siswa tanpa presensi: (a) hari ini sebelum `jam_pulang_standard`, (b) sesudahnya, (c) tanggal kemarin | jam_pulang 14:00 | (a) Belum Scan, (b) Alfa, (c) Alfa | P1 |
| ATT-03 | Input bulk presensi | POST `/attendance/students/bulk` satu kelas sekaligus | mix Hadir/Sakit/Izin | Semua baris tersimpan; upsert (tidak duplikat untuk siswa+tanggal) | P1 |
| ATT-04 | Edit manual presensi | PUT `/attendance/students/{id}` ubah status; kirim `jam_masuk` kosong | jam kosong | Status berubah; jam lama TIDAK tertimpa null; 1 baris audit log tertulis (data lama vs baru) | P1 |
| ATT-05 | Rekap & widget | GET `summary`, `consecutive-absences`, `top-late`, `/attendance/reports/weekly-trend` | data seed | Angka konsisten dengan data presensi; absen beruntun dihitung dari N tanggal yang ada di tabel (bukan kalender); siswa tanpa riwayat dikecualikan | P2 |
| ATT-06 | Presensi guru | GET/PUT `/attendance/teachers*` | data guru | Sama dengan siswa (tanpa poin pelanggaran) | P2 |

## 8. Perizinan (IZN)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| IZN-01 | Portal publik: submit izin | POST `/public/izin/lookup` (NIS) → `/public/izin/submit` dengan foto bukti | NIS valid, rentang 2 hari, foto jpg | 201; status Pending; file bukti tersimpan | P1 |
| IZN-02 | Submit tanpa login = throttled | Spam POST `/public/izin/submit` >20×/menit | request beruntun | 429 setelah limit | P2 |
| IZN-03 | Upload bukti non-gambar | Submit dengan file .php/.exe | file berbahaya | 422; file ditolak, tidak tersimpan di storage publik | P1 |
| IZN-04 | Approve izin multi-hari | POST `/attendance/permissions/{id}/approve` untuk izin 3 hari | Senin–Rabu | 3 baris presensi ter-upsert (status = tipe izin, keterangan = alasan); menimpa presensi yang sudah ada; 1 audit log; semua dalam 1 transaksi | P1 |
| IZN-05 | Reject izin | POST `/permissions/{id}/reject` | izin Pending | Status Ditolak; tabel presensi TIDAK berubah | P1 |
| IZN-06 | Approve ulang izin final | Approve izin yang sudah Disetujui/Ditolak | izin IZN-04 | Ditolak/no-op; presensi tidak dobel | P2 |
| IZN-07 | Cek status via portal | POST `/public/izin/status` | id/NIS pengajuan | Status terbaru tampil tanpa login | P3 |
| IZN-08 | Badge pending count | GET `/attendance/permissions-pending-count` | ada izin pending | Jumlah sesuai | P3 |
| IZN-09 | Portal cek kehadiran publik | POST `/public/cek-kehadiran`, `/public/riwayat-kehadiran` | NIS valid & invalid | Valid: riwayat tampil; invalid: error sopan; rate limit aktif | P2 |

## 9. Hari Libur (HOL)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| HOL-01 | CRUD hari libur | apiResource `/attendance/holidays`; tanggal duplikat | tanggal sama 2× | Duplikat ditolak (unique tanggal) | P2 |
| HOL-02 | Generate akhir pekan | POST `/holidays/generate-weekends` untuk 1 bulan | rentang 1 bulan | Semua Sabtu & Minggu jadi baris libur; jalankan 2× tidak duplikat | P2 |
| HOL-03 | Bulk delete & check | POST `/holidays/bulk-delete`, `/holidays/check` | tanggal libur | Terhapus; check mengembalikan status libur dengan keterangan | P3 |
| HOL-04 | `hari_kerja` settings tidak mem-block scan | Set hari kerja=Senin–Jumat, scan di Sabtu yang TIDAK ada di tabel holidays | Sabtu tanpa baris libur | Scan tetap diterima (gating hanya dari tabel holidays) | P2 |

## 10. QR Code (QR)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| QR-01 | Generate QR siswa/guru | GET `/attendance/qr/students/{id}`, `/qr/teachers/{id}` | seed | Gambar QR; payload = `unique_code`; QR siswa & guru bisa dibedakan (warna) | P2 |
| QR-02 | Download & bulk | GET `.../download`, `/qr/students/bulk?classroom=` | 1 kelas | File terunduh; bulk berisi semua siswa kelas tsb | P3 |
| QR-03 | Regenerate mengubah kode | POST `.../regenerate` lalu scan pakai kode lama | kode lama | Kode baru unik; kode lama tidak lagi bisa dipakai scan | P1 |

## 11. Ujian, Nilai & Rapor (EXM)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| EXM-01 | CRUD tipe ujian & ujian | apiResource `/exams/types`, `/exams` | UTS mapel X | CRUD normal | P3 |
| EXM-02 | Input nilai bulk | POST `/exams/{id}/scores/bulk` satu kelas | nilai 0–100 | Tersimpan; nilai di luar rentang (mis. 150, -5) → 422 | P1 |
| EXM-03 | Nilai per siswa/kelas | GET `/grades/student/{id}`, `/grades/classroom/{id}` | data EXM-02 | Nilai tampil benar per siswa/kelas | P2 |
| EXM-04 | Finalisasi nilai | POST `/grades/finalize`; lalu coba edit nilai yang sudah final | semester aktif | Setelah final, perubahan nilai ditolak/butuh un-finalize | P1 |
| EXM-05 | Generate & approve rapor | POST `/reports/report-cards/generate` → approve → GET `.../pdf` | siswa + semester | Rapor ter-generate dari nilai final; PDF terunduh; approve dobel ditolak | P2 |

## 12. Keuangan (FIN)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| FIN-01 | CRUD tipe & struktur biaya | apiResource `fee-types`, `fee-structures` | SPP 150.000/bulan | CRUD normal; nominal negatif ditolak | P2 |
| FIN-02 | Generate tagihan massal | POST `/finance/fees/generate` untuk kelas/tahun | struktur FIN-01 | Tagihan tercipta per siswa; generate ulang tidak menduplikasi tagihan | P1 |
| FIN-03 | Pembayaran penuh | POST `/finance/payments` sejumlah tagihan | bayar 150.000 | Status tagihan lunas; sisa 0 | P1 |
| FIN-04 | Pembayaran sebagian & lebih | Bayar 50.000; lalu coba bayar melebihi sisa | 50.000 lalu 200.000 | Sisa terhitung benar; pembayaran melebihi sisa ditolak/dikoreksi | P1 |
| FIN-05 | Verifikasi & kuitansi | POST `/payments/{id}/verify` → GET `/receipt` | pembayaran FIN-03 | Terverifikasi (tercatat siapa); kuitansi berisi nominal benar; verify dobel ditolak | P2 |
| FIN-06 | Diskon | Buat discount, terapkan ke tagihan siswa | diskon 50% | Sisa tagihan terpotong benar | P2 |
| FIN-07 | Laporan keuangan | GET `/finance/reports/summary`, `/monthly`, `/outstanding` | data FIN-02..04 | Total pemasukan & tunggakan cocok dengan transaksi | P2 |
| FIN-08 | Akses lintas role | Bendahara kelola pembayaran; guru/siswa coba POST payment | token guru | Bendahara sukses; role tak berwenang → 403 | P1 |

## 13. Perpustakaan (LIB)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| LIB-01 | CRUD kategori, buku, anggota | apiResource terkait; ISBN duplikat | buku + 2 kopi | CRUD normal; duplikat ditolak | P3 |
| LIB-02 | Peminjaman | POST `/library/loans` untuk kopi tersedia; pinjam kopi yang sedang dipinjam | kopi sama 2× | Pinjam pertama sukses (stok berkurang); kopi terpinjam ditolak | P1 |
| LIB-03 | Pengembalian & denda | POST `/loans/{id}/return` tepat waktu dan terlambat | lewat jatuh tempo | Tepat waktu: tanpa denda; terlambat: denda sesuai setting perpustakaan | P1 |
| LIB-04 | Perpanjangan | POST `/loans/{id}/extend`; melebihi batas perpanjangan | extend 2× | Jatuh tempo mundur; melebihi batas ditolak | P2 |
| LIB-05 | Reservasi | Buat reservation utk buku habis; buku kembali | buku LIB-02 | Reservasi tercatat; pemesan mendapat prioritas/notifikasi | P3 |

## 14. Notifikasi & Pengumuman (NTF)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| NTF-01 | Daftar & unread count | GET `/notifications`, `/notifications/unread-count` | user dengan notifikasi | Jumlah unread benar | P3 |
| NTF-02 | Tandai dibaca | POST `{id}/read`, `read-all` | notifikasi NTF-01 | Unread count berkurang sesuai | P3 |
| NTF-03 | Pengumuman: draft → publish | POST announcement → publish | target semua/role | Sebelum publish tidak tampil ke target; sesudahnya tampil | P2 |
| NTF-04 | Notifikasi milik user lain | POST `/notifications/{id}/read` untuk id milik user lain | id user B, token user A | 403/404 (tidak bisa memanipulasi notifikasi orang lain) | P1 |

## 15. Pengaturan & Admin (ADM)

| ID | Skenario | Langkah Uji | Data Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|---|
| ADM-01 | Pengaturan absensi | GET/PUT `/attendance/settings` ubah `jam_masuk_limit` & `jam_pulang_standard` | 07:30 / 15:00 | Tersimpan; perhitungan terlambat (SCN-02) & Alfa (ATT-02) mengikuti nilai baru | P1 |
| ADM-02 | Test WA/Telegram | POST `settings/test-whatsapp`, `test-telegram` dengan kredensial valid & invalid | token provider | Valid: pesan uji terkirim; invalid: error rapi tanpa 500 | P3 |
| ADM-03 | CRUD user + role | POST `/admin/users` dengan role; user login | akun baru role guru | Login sukses; hanya menu sesuai role | P1 |
| ADM-04 | Deactivate user | POST `/admin/users/{id}/deactivate`; user coba login/refresh | user ADM-03 | Login ditolak; sesi/token aktif tidak bisa dipakai lagi | P1 |
| ADM-05 | Reset password oleh admin | POST `/admin/users/{id}/reset-password` | user ADM-03 | User bisa login dengan password baru | P2 |
| ADM-06 | Audit log tercatat & immutable | Lakukan ATT-04 & IZN-04 → GET `/admin/audit-logs` | aksi tsb | Log berisi aksi, user, IP, snapshot lama/baru; tidak ada endpoint edit/hapus log | P1 |
| ADM-07 | Manajemen tenant | POST/suspend/activate `/super-admin/tenants` | tenant baru | User tenant suspended tidak bisa mengakses aplikasi; setelah activate normal kembali | P1 |
| ADM-08 | Health & metrics | GET `/super-admin/health`, `/metrics` | token superadmin | 200; status DB/cache/queue | P3 |

## 16. Non-Fungsional (NF)

| ID | Skenario | Langkah Uji | Hasil yang Diharapkan | Prioritas |
|---|---|---|---|---|
| NF-01 | SQL Injection | Kirim `' OR 1=1--` di parameter search/login | Diperlakukan sebagai string biasa; tanpa error DB | P1 |
| NF-02 | XSS tersimpan | Simpan nama siswa/pengumuman berisi `<script>alert(1)</script>` lalu render di frontend | Ter-escape, script tidak dieksekusi | P1 |
| NF-03 | Mass assignment | Sertakan field terlarang di body (mis. `role`, `tenant_id`, `poin_pelanggaran`) saat update profil/siswa | Field terlarang diabaikan | P1 |
| NF-04 | Ukuran upload | Upload bukti izin / logo melebihi batas ukuran | 422 dengan pesan jelas | P2 |
| NF-05 | Performa daftar besar | GET `/students` dengan ≥1000 baris seed | Respons < 2 detik; query tidak N+1 (cek debugbar/log) | P2 |
| NF-06 | Konkurensi scan | 2 request scan kode sama secara bersamaan | Hanya 1 presensi tercipta (unique constraint menahan race) | P1 |
| NF-07 | Timezone konsisten | Scan menjelang tengah malam & sync offline lintas hari | Tanggal presensi sesuai waktu lokal sekolah, tidak bergeser UTC | P2 |
| NF-08 | Migrasi bersih | `php artisan migrate:fresh --seed` di DB kosong | Selesai tanpa error; aplikasi langsung bisa login | P1 |

---

## Ringkasan

| Modul | Jumlah TC | P1 |
|---|---|---|
| Autentikasi | 12 | 4 |
| RBAC | 7 | 5 |
| Akademik | 12 | 2 |
| Siswa | 10 | 3 |
| Guru/Staff | 6 | 2 |
| Scan Absensi | 13 | 10 |
| Absensi Manual | 6 | 4 |
| Perizinan | 9 | 4 |
| Hari Libur | 4 | 0 |
| QR | 3 | 1 |
| Ujian/Rapor | 5 | 2 |
| Keuangan | 8 | 4 |
| Perpustakaan | 5 | 2 |
| Notifikasi | 4 | 1 |
| Admin/Setting | 8 | 5 |
| Non-Fungsional | 8 | 5 |
| **Total** | **120** | **54** |

**Saran urutan eksekusi:** NF-08 (migrasi) → AUTH → RBAC → SCN/ATT (bisnis inti) → IZN → sisanya. Test case P1 wajib lulus sebelum rilis.
