import 'scan_time.dart';

/// Satu scan yang tertahan offline, disimpan persisten untuk disinkron nanti.
class QueuedScan {
  QueuedScan({
    required this.id,
    required this.uniqueCode,
    required this.waktu,
    required this.scannedAt,
    this.latitude,
    this.longitude,
  });

  final String id;
  final String uniqueCode;
  final ScanTime waktu;
  final DateTime scannedAt;
  final double? latitude;
  final double? longitude;

  /// Format untuk `POST /scan/sync-offline` (`scanned_at` = `Y-m-d H:i:s`).
  Map<String, dynamic> toSyncJson() => {
        'unique_code': uniqueCode,
        'waktu': waktu.api,
        'scanned_at': _fmt(scannedAt),
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
      };

  Map<String, dynamic> toJson() => {
        'id': id,
        'unique_code': uniqueCode,
        'waktu': waktu.name,
        'scanned_at': scannedAt.toIso8601String(),
        'latitude': latitude,
        'longitude': longitude,
      };

  factory QueuedScan.fromJson(Map<String, dynamic> json) => QueuedScan(
        id: json['id'].toString(),
        uniqueCode: json['unique_code'].toString(),
        waktu: json['waktu'] == 'pulang' ? ScanTime.pulang : ScanTime.masuk,
        scannedAt: DateTime.parse(json['scanned_at'].toString()),
        latitude: (json['latitude'] as num?)?.toDouble(),
        longitude: (json['longitude'] as num?)?.toDouble(),
      );

  static String _fmt(DateTime d) {
    String two(int n) => n.toString().padLeft(2, '0');
    return '${d.year}-${two(d.month)}-${two(d.day)} '
        '${two(d.hour)}:${two(d.minute)}:${two(d.second)}';
  }
}
