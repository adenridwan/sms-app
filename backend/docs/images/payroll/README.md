# Screenshot Placeholder - Modul Penggajian

Folder ini berisi screenshot untuk user manual modul penggajian.

## Daftar Screenshot yang Dibutuhkan

Ambil screenshot dari aplikasi yang sedang berjalan untuk halaman-halaman berikut:

### Menu & Navigasi
| File | URL | Keterangan |
|------|-----|------------|
| `menu-payroll.png` | Sidebar | Screenshot menu Penggajian di sidebar |

### Golongan Gaji
| File | URL | Keterangan |
|------|-----|------------|
| `salary-grades-list.png` | `/payroll/salary-grades` | Daftar golongan gaji |
| `salary-grades-form.png` | `/payroll/salary-grades` | Modal form tambah/edit |

### Komponen Gaji
| File | URL | Keterangan |
|------|-----|------------|
| `salary-components-list.png` | `/payroll/salary-components` | Daftar komponen gaji |
| `salary-components-form.png` | `/payroll/salary-components` | Modal form tambah/edit |

### Tarif BPJS
| File | URL | Keterangan |
|------|-----|------------|
| `bpjs-rates-list.png` | `/payroll/bpjs-rates` | Daftar tarif BPJS |
| `bpjs-rates-form.png` | `/payroll/bpjs-rates` | Modal form edit |

### Bracket Pajak
| File | URL | Keterangan |
|------|-----|------------|
| `tax-brackets-list.png` | `/payroll/tax-brackets` | Daftar bracket pajak |
| `tax-brackets-form.png` | `/payroll/tax-brackets` | Modal form tambah |

### Pengaturan Pajak
| File | URL | Keterangan |
|------|-----|------------|
| `tax-settings-list.png` | `/payroll/tax-settings` | Daftar pengaturan pajak |

### Gaji Karyawan
| File | URL | Keterangan |
|------|-----|------------|
| `employee-salaries-list.png` | `/payroll/employee-salaries` | Daftar gaji karyawan |
| `employee-salaries-form.png` | `/payroll/employee-salaries` | Modal form setup gaji |
| `employee-salary-components.png` | `/payroll/employee-salaries` | Tab komponen gaji |
| `salary-history.png` | `/payroll/employee-salaries` | Tab riwayat gaji |

### Periode Gaji
| File | URL | Keterangan |
|------|-----|------------|
| `payroll-periods-list.png` | `/payroll/periods` | Daftar periode gaji |
| `payroll-periods-form.png` | `/payroll/periods` | Modal form buat periode |
| `generate-slips.png` | `/payroll/periods` | Proses generate slip |
| `approval.png` | `/payroll/periods` | Halaman approval |

### Slip Gaji
| File | URL | Keterangan |
|------|-----|------------|
| `payroll-slips-list.png` | `/payroll/slips/{periodId}` | Daftar slip dalam periode |
| `slip-detail.png` | `/payroll/slips/{periodId}` | Detail slip gaji |
| `slip-edit-items.png` | `/payroll/slips/{periodId}` | Edit item slip |
| `print-slip.png` | `/payroll/slips/{periodId}` | Preview cetak slip |

### Laporan
| File | URL | Keterangan |
|------|-----|------------|
| `reports.png` | `/payroll/reports` | Halaman laporan |

## Cara Mengambil Screenshot

### Windows
1. Tekan `Win + Shift + S` untuk Snipping Tool
2. Pilih area yang ingin di-capture
3. Simpan dengan nama sesuai tabel di atas

### Menggunakan Chrome DevTools
1. Buka Chrome DevTools (F12)
2. Tekan `Ctrl + Shift + P`
3. Ketik "screenshot"
4. Pilih "Capture full size screenshot" atau "Capture node screenshot"

### Tips
- Gunakan resolusi layar 1920x1080 atau lebih tinggi
- Pastikan data dummy sudah terisi agar screenshot lebih informatif
- Sembunyikan informasi sensitif jika ada
- Format: PNG dengan kualitas tinggi
- Dimensi yang disarankan: lebar 1200px - 1600px

## Konversi ke DOCX

Setelah semua screenshot tersedia, konversi markdown ke DOCX:

```bash
# Menggunakan Pandoc
pandoc docs/USER-MANUAL-PENGGAJIAN.md -o docs/USER-MANUAL-PENGGAJIAN.docx

# Dengan table of contents
pandoc docs/USER-MANUAL-PENGGAJIAN.md --toc -o docs/USER-MANUAL-PENGGAJIAN.docx

# Dengan styling
pandoc docs/USER-MANUAL-PENGGAJIAN.md --toc --reference-doc=template.docx -o docs/USER-MANUAL-PENGGAJIAN.docx
```

Atau gunakan:
- VS Code extension "Markdown PDF"
- Online converter: https://cloudconvert.com/md-to-docx
- Microsoft Word: Open .md file dan Save As .docx
