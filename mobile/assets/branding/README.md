# Aset merek

Sumber ikon peluncur aplikasi. Bukan aset runtime — tidak didaftarkan di
`flutter: assets:`, hanya dibaca `flutter_launcher_icons` saat generate.

| Berkas | Dipakai untuk |
| --- | --- |
| `logo.png` | Ikon legacy (Android < 8, iOS, web). Logo penuh, tepi ke tepi, 1024×1024. |
| `logo_foreground.png` | Lapisan depan adaptive icon Android 8+. Logo pada skala 0,90 di atas kanvas transparan. |

## Kenapa skalanya 0,90

Adaptive icon dipotong sistem sesuai bentuk peluncur (bulat / squircle), jadi
isi harus jatuh di dalam *safe zone* 66dp dari kanvas 108dp = **61%**.
`flutter_launcher_icons` sudah membungkus foreground dengan inset 16% —
menyisakan 68% kanvas. Sisanya tinggal 0,61 / 0,68 = **0,90**, itulah skala
gambar di berkas ini. Menaruh logo penuh (skala 1,0) membuat tulisan
"School Management System" terpotong di peluncur bermask bulat.

Warna latar adaptive icon (`#292929`, lihat `pubspec.yaml` dan
`android/app/src/main/res/values/colors.xml`) sengaja disamakan dengan warna
dasar logo supaya batas kotaknya tidak terlihat setelah dipotong.

## Regenerasi

Sumber aslinya gambar JPEG persegi-hampir (1648×1682) tanpa kanal alfa. Kedua
PNG di atas dihasilkan dengan memadatkannya ke kanvas persegi — sisi pendek
diberi padding `#292929`, bukan dipotong, supaya tak ada bagian logo yang
hilang.

Setelah berkas di sini berubah:

```bash
dart run flutter_launcher_icons
```

Ikon dibaca saat build, bukan runtime — perlu install ulang (bukan hot reload)
supaya perubahannya terlihat di peluncur.
