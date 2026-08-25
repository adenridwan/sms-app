import 'package:flutter/material.dart';

/// Tema aplikasi — mengikuti rujukan desain "Absensi Siswa, QR & Ref ID"
/// (2026-08-22): netral hangat, aksen merah-oranye, permukaan datar tanpa
/// bayangan, sudut membulat 16, dan tipografi Archivo.
///
/// Ciri khas rujukan yang sengaja dipertahankan:
///  - **Latar bukan putih.** Kartu putih-kepucatan mengambang di atas latar
///    hangat; itu yang memberi kedalaman, bukan bayangan.
///  - **Tanpa elevation.** Semua permukaan rata; pemisah memakai garis tipis.
///  - **Aksen dipakai hemat** — hanya untuk tindakan utama dan penanda aktif,
///    sehingga warnanya tetap berarti.
class AppTheme {
  const AppTheme._();

  /// Aksen utama (ubah ini untuk mengganti nuansa aplikasi).
  static const Color accent = Color(0xFFEC3013);
  static const Color accentSoft = Color(0xFFE15B47);

  // Tangga tonal aksen — dari rujukan, dipakai untuk latar lembut & teks aksen.
  static const Color accent100 = Color(0xFFFFF2EF);
  static const Color accent200 = Color(0xFFFFE0D9);
  static const Color accent600 = Color(0xFFDD2B0F);
  static const Color accent800 = Color(0xFF7C1405);

  // Netral hangat (bukan abu-abu biru).
  static const Color _lightBg = Color(0xFFF3F2F2);
  static const Color _lightSurface = Color(0xFFFFFFFF);
  static const Color _lightSurfaceAlt = Color(0xFFEAE9E9);
  static const Color _lightText = Color(0xFF201E1D);
  static const Color _lightLine = Color(0xFFD7D3D3);
  static const Color _lightMuted = Color(0xFF7D7979);

  static const Color _darkBg = Color(0xFF1A1817);
  static const Color _darkSurface = Color(0xFF242121);
  static const Color _darkSurfaceAlt = Color(0xFF2D2B2B);
  static const Color _darkText = Color(0xFFF8F4F4);
  static const Color _darkLine = Color(0xFF444141);
  static const Color _darkMuted = Color(0xFF9B9797);

  /// Radius kartu pada rujukan.
  static const double radius = 16;

  static const String fontFamily = 'Archivo';

  static ThemeData light() => _build(Brightness.light);
  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isLight = brightness == Brightness.light;
    final bg = isLight ? _lightBg : _darkBg;
    final surface = isLight ? _lightSurface : _darkSurface;
    final surfaceAlt = isLight ? _lightSurfaceAlt : _darkSurfaceAlt;
    final text = isLight ? _lightText : _darkText;
    final line = isLight ? _lightLine : _darkLine;
    final muted = isLight ? _lightMuted : _darkMuted;

    final scheme = ColorScheme.fromSeed(
      seedColor: accent,
      brightness: brightness,
    ).copyWith(
      // Nilai aksen dikunci: `fromSeed` memetakan seed ke palet tonal M3 yang
      // membuat merah-oranye ini keluar jadi cokelat kusam.
      primary: isLight ? accent : accentSoft,
      onPrimary: Colors.white,
      primaryContainer: isLight ? accent100 : accent800,
      onPrimaryContainer: isLight ? accent800 : accent100,
      surface: surface,
      onSurface: text,
      surfaceContainerHighest: surfaceAlt,
      surfaceContainerLowest: surface,
      onSurfaceVariant: muted,
      outlineVariant: line,
    );

    OutlineInputBorder border(Color c, [double w = 1]) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: c, width: w),
        );

    TextStyle t(double size, FontWeight weight, {double spacing = 0}) =>
        TextStyle(
          fontFamily: fontFamily,
          fontSize: size,
          fontWeight: weight,
          letterSpacing: spacing,
          color: text,
        );

    return ThemeData(
      colorScheme: scheme,
      useMaterial3: true,
      fontFamily: fontFamily,
      scaffoldBackgroundColor: bg,

      // Judul besar berbobot berat — ciri paling menonjol pada rujukan.
      textTheme: TextTheme(
        displaySmall: t(34, FontWeight.w800, spacing: -0.9),
        headlineMedium: t(26, FontWeight.w800, spacing: -0.6),
        headlineSmall: t(22, FontWeight.w700, spacing: -0.4),
        titleLarge: t(19, FontWeight.w700, spacing: -0.3),
        titleMedium: t(16, FontWeight.w700, spacing: -0.2),
        titleSmall: t(14, FontWeight.w600, spacing: -0.1),
        bodyLarge: t(15, FontWeight.w400),
        bodyMedium: t(13.5, FontWeight.w400),
        bodySmall: t(12, FontWeight.w400).copyWith(color: muted),
        labelLarge: t(14, FontWeight.w600),
      ),

      appBarTheme: AppBarTheme(
        backgroundColor: bg,
        surfaceTintColor: Colors.transparent,
        foregroundColor: text,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: t(20, FontWeight.w800, spacing: -0.4),
      ),

      // Datar dengan garis tipis — tanpa bayangan, sesuai rujukan.
      cardTheme: CardThemeData(
        elevation: 0,
        color: surface,
        surfaceTintColor: Colors.transparent,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(radius),
        ),
      ),

      dividerTheme: DividerThemeData(color: line, thickness: 1, space: 1),

      listTileTheme: ListTileThemeData(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
        ),
        titleTextStyle: t(14, FontWeight.w600),
        subtitleTextStyle: t(12, FontWeight.w400).copyWith(color: muted),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surfaceAlt,
        border: border(Colors.transparent),
        enabledBorder: border(Colors.transparent),
        focusedBorder: border(scheme.primary, 1.6),
        labelStyle: TextStyle(fontFamily: fontFamily, color: muted),
        hintStyle: TextStyle(fontFamily: fontFamily, color: muted),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
      ),

      // Tinggi minimum saja — **bukan** `Size.fromHeight`, yang berarti lebar
      // tak hingga dan meledak (`BoxConstraints forces an infinite width`)
      // begitu tombol dipakai di dalam `Row`, mis. bilah simpan Absen Kelas
      // dan baris aksi dialog. Di `ListView`/bilah bawah tombol tetap selebar
      // layar karena induknya sudah memberi lebar penuh.
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(64, 52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          textStyle: t(15, FontWeight.w700).copyWith(color: Colors.white),
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(64, 48),
          side: BorderSide(color: line),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          textStyle: t(14, FontWeight.w600),
        ),
      ),

      chipTheme: ChipThemeData(
        side: BorderSide(color: line),
        backgroundColor: surfaceAlt,
        labelStyle: t(12, FontWeight.w600),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(999),
        ),
      ),

      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        contentTextStyle: TextStyle(fontFamily: fontFamily, color: bg),
        backgroundColor: text,
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }
}
