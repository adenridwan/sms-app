import 'package:flutter/material.dart';

/// Tema aplikasi — palet teal institusional, tampilan **clean** untuk mode
/// terang & gelap. Warna terpusat di sini; ubah [_seed] atau token netral di
/// bawah untuk menyetel keseluruhan tampilan.
class AppTheme {
  const AppTheme._();

  /// Warna aksen utama (ubah ini untuk mengganti nuansa aplikasi).
  static const Color _seed = Color(0xFF0E7E71); // teal

  // Netral yang dipilih (sedikit bias teal) — bukan abu-abu default M3.
  static const _lightBg = Color(0xFFF5F8F7); // latar lembut
  static const _lightSurface = Color(0xFFFFFFFF); // kartu putih bersih
  static const _lightField = Color(0xFFEFF3F1); // isian input
  static const _lightLine = Color(0xFFE1E8E5); // garis hairline

  static const _darkBg = Color(0xFF0D1512);
  static const _darkSurface = Color(0xFF16201C);
  static const _darkField = Color(0xFF18241F);
  static const _darkLine = Color(0xFF223129);

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
