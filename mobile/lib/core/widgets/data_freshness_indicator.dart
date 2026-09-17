import 'package:flutter/material.dart';

/// Shows how old the cached data is.
///
/// Displays a subtle indicator like "Last updated: 5 min ago"
/// to let users know they're viewing cached data.
class DataFreshnessIndicator extends StatelessWidget {
  const DataFreshnessIndicator({
    super.key,
    required this.cacheAge,
    this.onRefresh,
  });

  /// How long ago the data was cached.
  final Duration? cacheAge;

  /// Called when user taps the refresh button.
  final VoidCallback? onRefresh;

  @override
  Widget build(BuildContext context) {
    if (cacheAge == null) return const SizedBox.shrink();

    final age = cacheAge!;
    final theme = Theme.of(context);

    // Determine staleness level.
    final isStale = age > const Duration(hours: 1);
    final isVeryStale = age > const Duration(hours: 24);

    final color = isVeryStale
        ? Colors.orange
        : isStale
            ? theme.colorScheme.onSurfaceVariant
            : theme.colorScheme.onSurface.withValues(alpha: 0.5);

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(
          Icons.access_time,
          size: 14,
          color: color,
        ),
        const SizedBox(width: 4),
        Text(
          _formatAge(age),
          style: TextStyle(
            fontSize: 12,
            color: color,
          ),
        ),
        if (onRefresh != null) ...[
          const SizedBox(width: 8),
          GestureDetector(
            onTap: onRefresh,
            child: Icon(
              Icons.refresh,
              size: 16,
              color: theme.colorScheme.primary,
            ),
          ),
        ],
      ],
    );
  }

  String _formatAge(Duration age) {
    if (age.inMinutes < 1) {
      return 'Baru saja';
    } else if (age.inMinutes < 60) {
      return '${age.inMinutes} menit lalu';
    } else if (age.inHours < 24) {
      return '${age.inHours} jam lalu';
    } else {
      return '${age.inDays} hari lalu';
    }
  }
}

/// Simple "Last synced" text.
class LastSyncedText extends StatelessWidget {
  const LastSyncedText({
    super.key,
    required this.lastSyncAt,
  });

  final DateTime? lastSyncAt;

  @override
  Widget build(BuildContext context) {
    if (lastSyncAt == null) return const SizedBox.shrink();

    final age = DateTime.now().difference(lastSyncAt!);
    final theme = Theme.of(context);

    return Text(
      'Terakhir disinkronkan: ${_formatAge(age)}',
      style: TextStyle(
        fontSize: 11,
        color: theme.colorScheme.onSurface.withValues(alpha: 0.5),
      ),
    );
  }

  String _formatAge(Duration age) {
    if (age.inMinutes < 1) {
      return 'baru saja';
    } else if (age.inMinutes < 60) {
      return '${age.inMinutes} menit lalu';
    } else if (age.inHours < 24) {
      return '${age.inHours} jam lalu';
    } else {
      return '${age.inDays} hari lalu';
    }
  }
}
