/// Model pengguna terautentikasi, dipetakan dari `UserResource` backend.
class User {
  const User({
    required this.id,
    required this.fullName,
    required this.email,
    this.username,
    this.userType,
    this.avatarUrl,
    this.tenantId,
    this.roles = const [],
    this.permissions = const [],
  });

  final String id;
  final String fullName;
  final String email;
  final String? username;
  final String? userType;
  final String? avatarUrl;
  final String? tenantId;
  final List<String> roles;
  final List<String> permissions;

  bool get isAdmin => userType == 'admin' || roles.contains('admin');
  bool get isSuperAdmin => userType == 'super_admin';

  /// Persona yang boleh memakai aplikasi absensi (admin/guru/staf).
  bool get canUseAttendanceApp =>
      const {'admin', 'staff', 'teacher', 'super_admin'}.contains(userType);

  bool hasPermission(String p) => isSuperAdmin || permissions.contains(p);

  factory User.fromJson(
    Map<String, dynamic> json, {
    List<String>? permissions,
    List<String>? roles,
  }) {
    List<String> asStringList(dynamic v) =>
        v is List ? v.map((e) => e.toString()).toList() : const [];

    return User(
      id: json['id']?.toString() ?? '',
      fullName: (json['full_name'] ?? '').toString(),
      email: (json['email'] ?? '').toString(),
      username: json['username']?.toString(),
      userType: json['user_type']?.toString(),
      avatarUrl: json['avatar_url']?.toString(),
      tenantId: json['tenant_id']?.toString(),
      roles: roles ?? asStringList(json['roles']),
      permissions: permissions ?? asStringList(json['permissions']),
    );
  }

  /// Untuk cache lokal (bukan bentuk API) — dibaca kembali lewat [User.fromJson]
  /// karena bentuknya sama persis (permissions/roles sudah flat di sini).
  Map<String, dynamic> toJson() => {
        'id': id,
        'full_name': fullName,
        'email': email,
        'username': username,
        'user_type': userType,
        'avatar_url': avatarUrl,
        'tenant_id': tenantId,
        'roles': roles,
        'permissions': permissions,
      };
}
