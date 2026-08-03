/// Notifikasi dari `GET /notifications`.
class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.isRead,
    this.createdAt,
  });

  final String id;
  final String title;
  final String body;
  final bool isRead;
  final DateTime? createdAt;

  AppNotification copyWith({bool? isRead}) => AppNotification(
        id: id,
        title: title,
        body: body,
        isRead: isRead ?? this.isRead,
        createdAt: createdAt,
      );

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    // Backend memakai penamaan campur (judul/title, pesan/message) tergantung
    // sumber notifikasi; ambil yang pertama tersedia agar tidak kosong.
    String pick(List<String> keys) {
      for (final k in keys) {
        final v = json[k];
        if (v is String && v.isNotEmpty) return v;
      }
      return '';
    }

    final readAt = json['read_at'] ?? json['dibaca_pada'];

    return AppNotification(
      id: json['id']?.toString() ?? '',
      title: pick(['title', 'judul', 'subject']),
      body: pick(['body', 'message', 'pesan', 'isi']),
      isRead: json['is_read'] == true || readAt != null,
      createdAt: DateTime.tryParse(
          (json['created_at'] ?? json['dibuat_pada'] ?? '').toString()),
    );
  }
}
