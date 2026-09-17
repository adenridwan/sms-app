import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../database/database_providers.dart';

/// Badge showing count of pending items to sync.
///
/// Use this on the offline queue button or settings screen
/// to indicate there are unsent items.
class PendingSyncBadge extends ConsumerWidget {
  const PendingSyncBadge({
    super.key,
    required this.child,
    this.showZero = false,
    this.alignment = Alignment.topRight,
    this.offset = const Offset(-4, 4),
  });

  final Widget child;
  final bool showZero;
  final Alignment alignment;
  final Offset offset;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final countAsync = ref.watch(totalPendingSyncCountProvider);

    return Stack(
      clipBehavior: Clip.none,
      children: [
        child,
        Positioned(
          right: offset.dx,
          top: offset.dy,
          child: countAsync.maybeWhen(
            data: (count) {
              if (count == 0 && !showZero) return const SizedBox.shrink();
              return _Badge(count: count);
            },
            orElse: () => const SizedBox.shrink(),
          ),
        ),
      ],
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.count});

  final int count;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: Colors.orange,
        borderRadius: BorderRadius.circular(10),
      ),
      constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
      child: Text(
        count > 99 ? '99+' : '$count',
        style: const TextStyle(
          color: Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.bold,
        ),
        textAlign: TextAlign.center,
      ),
    );
  }
}

/// Simple pending count display for lists.
class PendingSyncCount extends ConsumerWidget {
  const PendingSyncCount({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final counts = ref.watch(pendingSyncCountsProvider);

    return counts.maybeWhen(
      data: (data) {
        final total = data.scans + data.classAttendances + data.actions;
        if (total == 0) return const SizedBox.shrink();

        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          margin: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.orange.shade50,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: Colors.orange.shade200),
          ),
          child: Row(
            children: [
              Icon(Icons.cloud_upload, size: 20, color: Colors.orange.shade700),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'Menunggu sinkronisasi',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: Colors.orange.shade900,
                      ),
                    ),
                    Text(
                      _buildDescription(data),
                      style: TextStyle(
                        fontSize: 12,
                        color: Colors.orange.shade700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
      orElse: () => const SizedBox.shrink(),
    );
  }

  String _buildDescription(
    ({int scans, int classAttendances, int actions}) data,
  ) {
    final parts = <String>[];
    if (data.scans > 0) parts.add('${data.scans} scan');
    if (data.classAttendances > 0) {
      parts.add('${data.classAttendances} absen kelas');
    }
    if (data.actions > 0) parts.add('${data.actions} tindakan');
    return parts.join(', ');
  }
}
