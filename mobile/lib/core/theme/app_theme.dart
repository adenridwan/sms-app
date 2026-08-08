import 'package:flutter/material.dart';

/// Tema aplikasi — palet **royal blue** dengan netral biru-abu, mengikuti
/// rujukan desain dashboard (2026-08-02). Warna terpusat di sini; ubah [_seed]
/// atau token netral di bawah untuk menyetel keseluruhan tampilan.
class AppTheme {
  const AppTheme._();

  /// Warna aksen utama (ubah ini untuk mengganti nuansa aplikasi).
  static const Color _seed = Color(0xFF2563EB); // royal blue

  /// Ujung gelap gradien header. Dipakai [HomeHero] agar bidang biru punya
  /// kedalaman, bukan blok warna datar.
  static const Color heroGradientEnd = Color(0xFF1E40AF);

  // Netral berbias biru — latar sengaja bukan putih agar kartu putih di
  // atasnya "mengambang", persis seperti pada rujukan desain.
  static const _lightBg = Color(0xFFEEF2F9); // latar biru-abu lembut
  static const _lightSurface = Color(0xFFFFFFFF); // kartu putih bersih
  static const _lightField = Color(0xFFF1F5FB); // isian input
  static const _lightLine = Color(0xFFE2E8F2); // garis hairline

  static const _darkBg = Color(0xFF0B1020);
  static const _darkSurface = Color(0xFF141B2E);
  static const _darkField = Color(0xFF19223A);
  static const _darkLine = Color(0xFF25304C);

  static ThemeData light() => _build(Brightness.light);
  static ThemeData dark() => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isLight = brightness == Brightness.light;
    final bg = isLight ? _lightBg : _darkBg;
    final surface = isLight ? _lightSurface : _darkSurface;
    final field = isLight ? _lightField : _darkField;
    final line = isLight ? _lightLine : _darkLine;

    final scheme = ColorScheme.fromSeed(
      seedColor: _seed,
      brightness: brightness,
    ).copyWith(
      // `fromSeed` memetakan seed ke palet tonal M3, yang membuat biru merek
      // ini keluar sebagai biru-abu kusam. Untuk warna aksen kita kunci nilai
      // aslinya agar bidang biru benar-benar hidup seperti rujukan desain.
      primary: isLight ? _seed : const Color(0xFF60A5FA),
      onPrimary: isLight ? Colors.white : const Color(0xFF0B1020),
      primaryContainer: isLight
          ? const Color(0xFFDBE7FE)
          : const Color(0xFF1E3A8A),
      onPrimaryContainer: isLight
          ? const Color(0xFF17337A)
          : const Color(0xFFDBE7FE),
      surface: surface,
      surfaceContainerLowest: surface,
      outlineVariant: line,
    );

    OutlineInputBorder border(Color c, [double w = 1]) => OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: c, width: w),
        );

    return ThemeData(
      colorScheme: scheme,
      useMaterial3: true,
      scaffoldBackgroundColor: bg,
      // Hilangkan tint permukaan M3 yang membuat kesan keabu-abuan.
      appBarTheme: AppBarTheme(
        backgroundColor: bg,
        surfaceTintColor: Colors.transparent,
        foregroundColor: scheme.onSurface,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        titleTextStyle: TextStyle(
          color: scheme.onSurface,
          fontSize: 20,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.2,
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: surface,
        surfaceTintColor: Colors.transparent,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: line),
        ),
      ),
      dividerTheme: DividerThemeData(color: line, thickness: 1, space: 1),
      listTileTheme: const ListTileThemeData(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(12)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: field,
        border: border(Colors.transparent),
        enabledBorder: border(Colors.transparent),
        focusedBorder: border(scheme.primary, 1.6),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
        ),
      ),
      chipTheme: ChipThemeData(
        side: BorderSide(color: line),
        backgroundColor: isLight ? _lightField : _darkField,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(999),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
    );
  }
}
