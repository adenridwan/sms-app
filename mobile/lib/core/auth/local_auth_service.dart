import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:drift/drift.dart';
import 'package:uuid/uuid.dart';

import '../database/database.dart';

/// Local authentication service for fully independent login.
///
/// Users can:
/// - Create local accounts (no backend needed)
/// - Login with local credentials
/// - Change password locally
///
/// Backend connection is separate and optional (via QR scan).
class LocalAuthService {
  LocalAuthService(this._db);

  final AppDatabase _db;

  static const _iterations = 50000;
  static const _keyLength = 32;

  // ===========================================================================
  // ACCOUNT MANAGEMENT
  // ===========================================================================

  /// Create a new local account.
  Future<LocalAccount> createAccount({
    required String email,
    required String fullName,
    required String password,
  }) async {
    // Check if email already exists
    final existing = await getAccountByEmail(email);
    if (existing != null) {
      throw LocalAuthException('Email sudah terdaftar.');
    }

    final id = const Uuid().v4();
    final salt = _generateSalt();
    final hash = _hashPassword(password, salt);
    final now = DateTime.now();

    final account = LocalAccountsCompanion(
      id: Value(id),
      email: Value(email.toLowerCase().trim()),
      fullName: Value(fullName.trim()),
      passwordHash: Value(base64Encode(hash)),
      passwordSalt: Value(base64Encode(salt)),
      createdAt: Value(now),
      updatedAt: Value(now),
    );

    await _db.into(_db.localAccounts).insert(account);

    return (await getAccountByEmail(email))!;
  }

  /// Get account by email.
  Future<LocalAccount?> getAccountByEmail(String email) async {
    return await (_db.select(_db.localAccounts)
          ..where((t) => t.email.equals(email.toLowerCase().trim())))
        .getSingleOrNull();
  }

  /// Get account by ID.
  Future<LocalAccount?> getAccountById(String id) async {
    return await (_db.select(_db.localAccounts)
          ..where((t) => t.id.equals(id)))
        .getSingleOrNull();
  }

  /// Get all local accounts (for account switcher).
  Future<List<LocalAccount>> getAllAccounts() async {
    return await _db.select(_db.localAccounts).get();
  }

  /// Update account profile.
  Future<void> updateProfile({
    required String id,
    required String fullName,
  }) async {
    await (_db.update(_db.localAccounts)..where((t) => t.id.equals(id))).write(
      LocalAccountsCompanion(
        fullName: Value(fullName.trim()),
        updatedAt: Value(DateTime.now()),
      ),
    );
  }

  /// Change password.
  Future<void> changePassword({
    required String id,
    required String currentPassword,
    required String newPassword,
  }) async {
    final account = await getAccountById(id);
    if (account == null) {
      throw LocalAuthException('Akun tidak ditemukan.');
    }

    // Verify current password
    if (!_verifyPassword(currentPassword, account)) {
      throw LocalAuthException('Password saat ini salah.');
    }

    // Update with new password
    final salt = _generateSalt();
    final hash = _hashPassword(newPassword, salt);

    await (_db.update(_db.localAccounts)..where((t) => t.id.equals(id))).write(
      LocalAccountsCompanion(
        passwordHash: Value(base64Encode(hash)),
        passwordSalt: Value(base64Encode(salt)),
        updatedAt: Value(DateTime.now()),
      ),
    );
  }

  /// Delete account.
  Future<void> deleteAccount(String id) async {
    await (_db.delete(_db.localAccounts)..where((t) => t.id.equals(id))).go();
  }

  // ===========================================================================
  // LOGIN
  // ===========================================================================

  /// Login with email and password.
  /// Returns account if successful, null if failed.
  Future<LocalAccount?> login(String email, String password) async {
    final account = await getAccountByEmail(email);
    if (account == null) return null;

    if (!_verifyPassword(password, account)) return null;

    return account;
  }

  // ===========================================================================
  // BACKEND CONNECTION
  // ===========================================================================

  /// Save backend connection from QR scan.
  Future<void> saveConnection({
    required String apiUrl,
    required String syncToken,
    String? schoolName,
    String? backendUserId,
    String? backendUserName,
    String? backendUserEmail,
    List<String> permissions = const [],
    List<String> roles = const [],
  }) async {
    await _db.into(_db.backendConnection).insertOnConflictUpdate(
          BackendConnectionCompanion(
            id: const Value(1), // Singleton
            apiUrl: Value(apiUrl),
            syncToken: Value(syncToken),
            schoolName: Value(schoolName),
            backendUserId: Value(backendUserId),
            backendUserName: Value(backendUserName),
            backendUserEmail: Value(backendUserEmail),
            permissionsJson: Value(jsonEncode(permissions)),
            rolesJson: Value(jsonEncode(roles)),
            connectedAt: Value(DateTime.now()),
          ),
        );
  }

  /// Get current backend connection.
  Future<BackendConnectionData?> getConnection() async {
    return await (_db.select(_db.backendConnection)
          ..where((t) => t.id.equals(1)))
        .getSingleOrNull();
  }

  /// Check if connected to backend.
  Future<bool> isConnected() async {
    final conn = await getConnection();
    return conn != null && conn.syncToken.isNotEmpty;
  }

  /// Update last sync time.
  Future<void> updateLastSyncTime() async {
    await (_db.update(_db.backendConnection)..where((t) => t.id.equals(1)))
        .write(BackendConnectionCompanion(
      lastSyncAt: Value(DateTime.now()),
    ));
  }

  /// Disconnect from backend (remove connection).
  Future<void> disconnect() async {
    await (_db.delete(_db.backendConnection)..where((t) => t.id.equals(1)))
        .go();
  }

  /// Get backend permissions.
  Future<List<String>> getBackendPermissions() async {
    final conn = await getConnection();
    if (conn == null) return [];

    try {
      return (jsonDecode(conn.permissionsJson) as List).cast<String>();
    } catch (_) {
      return [];
    }
  }

  // ===========================================================================
  // PRIVATE HELPERS
  // ===========================================================================

  List<int> _generateSalt() {
    final random = Random.secure();
    return List.generate(16, (_) => random.nextInt(256));
  }

  List<int> _hashPassword(String password, List<int> salt) {
    var result = [...utf8.encode(password), ...salt];

    for (var i = 0; i < _iterations; i++) {
      result = sha256.convert(result).bytes;
    }

    return result.sublist(0, _keyLength);
  }

  bool _verifyPassword(String password, LocalAccount account) {
    final salt = base64Decode(account.passwordSalt);
    final storedHash = base64Decode(account.passwordHash);
    final inputHash = _hashPassword(password, salt);

    return _constantTimeEquals(storedHash, inputHash);
  }

  bool _constantTimeEquals(List<int> a, List<int> b) {
    if (a.length != b.length) return false;
    var result = 0;
    for (var i = 0; i < a.length; i++) {
      result |= a[i] ^ b[i];
    }
    return result == 0;
  }
}

/// Exception for local auth errors.
class LocalAuthException implements Exception {
  LocalAuthException(this.message);
  final String message;

  @override
  String toString() => message;
}
