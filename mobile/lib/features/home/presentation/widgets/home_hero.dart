import 'package:flutter/material.dart';

/// Satu angka ringkasan di header (mis. "540 Siswa").
class HeroFigure {
  const HeroFigure({required this.value, required this.label});

  final String value;
  final String label;
}

/// Blok header Beranda: sapaan, peran, dan angka ringkasan.
///
/// Ini satu-satunya permukaan berwarna di layar — jangkar visual yang
/// menggantikan tumpukan kartu, sekaligus menjadikan angka ringkasan terbaca
/// tanpa bingkai per angka.
class HomeHero extends StatelessWidget {
  const HomeHero({
    super.key,
    required this.name,
    required this.persona,
    required this.figures,
    this.today,
  });

  final String name;
  final String persona;
  final String? today;
  final List<HeroFigure> figures;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final onHero = scheme.onPrimary;
    final subtitle = [persona, if (today != null) today!]
        .where((e) => e.isNotEmpty)
        .join(' · ');

    return Container(
      width: double.infinity,
      padding: EdgeInsets.fromLTRB(
        20,
        MediaQuery.of(context).padding.top + 16,
        20,
        20,
      ),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            scheme.primary,
            Color.lerp(scheme.primary, Colors.black, 0.32)!,
          ],
        ),
        borderRadius: const BorderRadius.vertical(
          bottom: Radius.circular(22),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Halo, $name',
            style: TextStyle(
              color: onHero,
              fontSize: 19,
              fontWeight: FontWeight.w700,
              letterSpacing: -.3,
            ),
          ),
          if (subtitle.isNotEmpty) ...[
            const SizedBox(height: 2),
            Text(
              subtitle,
              style: TextStyle(
                color: onHero.withOpacity(.82),
                fontSize: 11.5,
              ),
            ),
          ],
          if (figures.isNotEmpty) ...[
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.only(top: 13),
              decoration: BoxDecoration(
                border: Border(
                  top: BorderSide(color: onHero.withOpacity(.18)),
                ),
              ),
              child: Row(
                children: [
                  for (var i = 0; i < figures.length; i++)
                    Expanded(
                      child: Container(
                        padding: EdgeInsets.only(left: i == 0 ? 0 : 12),
                        decoration: i == 0
                            ? null
                            : BoxDecoration(
                                border: Border(
                                  left: BorderSide(
                                      color: onHero.withOpacity(.18)),
                                ),
                              ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              figures[i].value,
                              style: TextStyle(
                                color: onHero,
                                fontSize: 18,
                                fontWeight: FontWeight.w700,
                                letterSpacing: -.4,
                                fontFeatures: const [
                                  FontFeature.tabularFigures()
                                ],
                              ),
                            ),
                            Text(
                              figures[i].label.toUpperCase(),
                              style: TextStyle(
                                color: onHero.withOpacity(.75),
                                fontSize: 9.5,
                                letterSpacing: .6,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}
