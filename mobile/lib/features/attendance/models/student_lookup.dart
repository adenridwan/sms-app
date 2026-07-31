/// Pratinjau pemilik kode dari `POST /scan/lookup`.
class LookupResult {
  const LookupResult({
    required this.type,
    required this.name,
    this.identifier,
    this.classroom,
    this.status,
  });

  final String type; // student | teacher
  final String name;
  final String? identifier; // NIS / NIP
  final String? classroom;
  final String? status;

  bool get isStudent => type == 'student';
  bool get isActive => status == 'active';

  factory LookupResult.fromJson(Map<String, dynamic> data) {
    return LookupResult(
      type: (data['type'] ?? 'student').toString(),
      name: (data['name'] ?? '-').toString(),
      identifier: (data['nis'] ?? data['nip'])?.toString(),
      classroom: data['classroom']?.toString(),
      status: data['status']?.toString(),
    );
  }
}
