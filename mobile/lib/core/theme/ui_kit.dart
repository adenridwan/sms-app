import 'package:flutter/material.dart';

/// Komponen bersama untuk rujukan desain "Absensi Siswa, QR & Ref ID".
///
/// Alasan ini satu berkas: sebelumnya tiap layar menggambar kartu, banner, dan
/// pemilih segmennya sendiri, sehingga radius dan jarak pelan-pelan menyimpang.
/// Bentuk rujukan hanya ada tiga — **panel putih datar**, **rel pesan satu
/// baris**, dan **pil pemilih** — jadi ketiganya ditetapkan di sini sekali.

/// Panel putih datar bersudut 16, pengganti [Card] + [Padding] berulang.
class Panel extends StatelessWidget {
  const Panel({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.onTap,
    this.tinted = false,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;

  /// Latar aksen lembut — untuk panel yang sedang aktif/terpilih.
  final bool tinted;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final radius = BorderRadius.circular(16);
    final body = Padding(padding: padding, child: child);

    return Container(
      decoration: BoxDecoration(
        color: tinted ? scheme.primaryContainer : scheme.surface,
        borderRadius: radius,
      ),
      clipBehavior: Clip.antiAlias,
      child: onTap == null
          ? body
          : Material(
              color: Colors.transparent,
              child: InkWell(onTap: onTap, child: body),
            ),
    );
  }
}

/// Pesan satu baris: rel warna tipis di kiri, tanpa ikon dan tanpa bayangan.
///
/// Rujukan tidak memakai banner berikon — pesan tampil sebagai teks bertanda
/// rel, supaya tidak bersaing dengan tombol aksi.
class InfoStrip extends StatelessWidget {
  const InfoStrip({
    super.key,
    required this.text,
    this.tone = StripTone.accent,
    this.onTap,
    this.margin = const EdgeInsets.only(bottom: 14),
  });

  final String text;
  final StripTone tone;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry margin;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final (rail, bg, fg) = switch (tone) {
      StripTone.accent => (
          scheme.primary,
          scheme.primaryContainer,
          scheme.onPrimaryContainer
        ),
      StripTone.neutral => (
          scheme.outlineVariant,
          scheme.surfaceContainerHighest,
          scheme.onSurface
        ),
    };

    final body = Container(
      padding: const EdgeInsets.fromLTRB(12, 11, 13, 11),
      decoration: BoxDecoration(
        color: bg,
        border: Border(left: BorderSide(color: rail, width: 3)),
        borderRadius: const BorderRadius.horizontal(right: Radius.circular(12)),
      ),
      child: Text(
        text,
        style: TextStyle(fontSize: 11.5, height: 1.35, color: fg),
      ),
    );

    return Container(
      margin: margin,
      child: onTap == null
          ? body
          : InkWell(
              onTap: onTap,
              borderRadius: BorderRadius.circular(12),
              child: body,
            ),
    );
  }
}

enum StripTone { accent, neutral }

/// Pemilih dua-tiga pilihan berbentuk pil, pengganti [SegmentedButton].
///
/// [SegmentedButton] bawaan M3 membawa garis tepi dan ikon centang yang tidak
/// ada pada rujukan; di sini yang terpilih ditandai bidang gelap penuh.
class PillTabs<T> extends StatelessWidget {
  const PillTabs({
    super.key,
    required this.options,
    required this.selected,
    required this.onChanged,
    this.labelOf,
  });

  final List<T> options;
  final T selected;
  final ValueChanged<T> onChanged;
  final String Function(T)? labelOf;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        children: [
          for (final o in options)
            Expanded(
              child: GestureDetector(
                onTap: () => onChanged(o),
                behavior: HitTestBehavior.opaque,
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 140),
                  padding: const EdgeInsets.symmetric(
                      vertical: 10, horizontal: 6),
                  decoration: BoxDecoration(
                    color: o == selected ? scheme.onSurface : Colors.transparent,
                    borderRadius: BorderRadius.circular(999),
                  ),
                  // Empat pilihan pada layar 360dp membuat label seperti
                  // "Absensi Saya" meluber. Menyusutkan huruf lebih baik
                  // daripada memotongnya — nama metode harus tetap terbaca utuh.
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      labelOf?.call(o) ?? '$o',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: o == selected
                            ? scheme.surface
                            : scheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Label huruf kapital kecil di atas satu kelompok kendali.
class FieldLabel extends StatelessWidget {
  const FieldLabel(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) => Text(
        text.toUpperCase(),
        style: TextStyle(
          fontSize: 10.5,
          letterSpacing: 1.1,
          fontWeight: FontWeight.w700,
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
      );
}

/// Keadaan kosong bergaya editorial: judul tebal, satu kalimat penjelas.
class EmptyNote extends StatelessWidget {
  const EmptyNote({super.key, required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 48),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 6),
          Text(
            body,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 12.5,
              height: 1.45,
              color: scheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }
}
