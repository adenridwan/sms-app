import 'package:flutter/material.dart';

import '../../models/scan_result.dart';

/// Warna & ikon status hasil scan.
({Color color, IconData icon}) _visual(ScanResult r, ColorScheme s) {
  if (r.queued) {
    return (color: const Color(0xFF3B7DC4), icon: Icons.cloud_upload_rounded);
  }
  if (!r.success) return (color: s.error, icon: Icons.close_rounded);
  if (r.needsVerification) {
    return (color: const Color(0xFFC9871F), icon: Icons.info_outline_rounded);
  }
  if (r.late) {
    return (color: const Color(0xFFC9871F), icon: Icons.schedule_rounded);
  }
  return (color: const Color(0xFF2E9E63), icon: Icons.check_rounded);
}

/// Tampilkan kartu hasil sebagai modal bottom sheet. Auto-tutup opsional.
Future<void> showResultSheet(
  BuildContext context,
  ScanResult result, {
  Duration? autoDismiss = const Duration(milliseconds: 2200),
}) async {
  await showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (ctx) {
      if (autoDismiss != null) {
        final navigator = Navigator.of(ctx);
        Future.delayed(autoDismiss, () {
          if (navigator.canPop()) navigator.pop();
        });
      }
      return _ResultCard(result: result);
    },
  );
}

class _ResultCard extends StatelessWidget {
  const _ResultCard({required this.result});
  final ScanResult result;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final v = _visual(result, scheme);

    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 4,
        bottom: 20 + MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          CircleAvatar(
            radius: 30,
            backgroundColor: v.color.withOpacity(0.15),
            child: Icon(v.icon, color: v.color, size: 34),
          ),
          const SizedBox(height: 14),
          if (result.name != null) ...[
            Text(
              result.name!,
              style: Theme.of(context).textTheme.titleLarge,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 2),
            Text(
              [
                if (result.identifier != null) result.identifier,
                if (result.classroom != null) result.classroom,
              ].whereType<String>().join(' · '),
              style: Theme.of(context)
                  .textTheme
                  .bodySmall
                  ?.copyWith(color: scheme.onSurfaceVariant),
            ),
            const SizedBox(height: 12),
          ],
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
            decoration: BoxDecoration(
              color: v.color.withOpacity(0.14),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Text(
              result.time != null
                  ? '${result.statusLabel} · ${result.time}'
                  : result.statusLabel,
              style: TextStyle(color: v.color, fontWeight: FontWeight.w700),
            ),
          ),
          const SizedBox(height: 12),
          Text(
            result.message,
            textAlign: TextAlign.center,
            style: Theme.of(context)
                .textTheme
                .bodyMedium
                ?.copyWith(color: scheme.onSurfaceVariant),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: () => Navigator.of(context).maybePop(),
              child: const Text('Lanjut'),
            ),
          ),
        ],
      ),
    );
  }
}
