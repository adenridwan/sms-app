import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'theme_controller.dart';

/// Tombol pemilih tema (Sistem / Terang / Gelap) untuk AppBar.
class ThemeModeButton extends ConsumerWidget {
  const ThemeModeButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(themeModeProvider);
    return PopupMenuButton<ThemeMode>(
      tooltip: 'Tema',
      icon: Icon(mode.icon),
      onSelected: (m) => ref.read(themeModeProvider.notifier).setMode(m),
      itemBuilder: (context) => [
        for (final m in ThemeMode.values)
          PopupMenuItem<ThemeMode>(
            value: m,
            child: Row(
              children: [
                Icon(m.icon, size: 20),
                const SizedBox(width: 12),
                Text(m.label),
                if (m == mode) ...[
                  const Spacer(),
                  Icon(Icons.check_rounded,
                      size: 18, color: Theme.of(context).colorScheme.primary),
                ],
              ],
            ),
          ),
      ],
    );
  }
}
