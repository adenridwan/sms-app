import 'dart:convert';

import 'package:drift/drift.dart';

import '../../../features/auth/models/user.dart';
import '../database.dart';

/// Local data source for user data.
///
/// Replaces [UserCacheStore] with SQLite-backed storage.
class UserLocalSource {
  UserLocalSource(this._db);

  final AppDatabase _db;

  /// Get cached user by ID.
  Future<User?> getUser(String id) async {
    final row = await _db.getUserById(id);
    if (row == null) return null;
    return _rowToUser(row);
  }

  /// Save user to local database.
  Future<void> saveUser(User user) async {
    await _db.upsertUser(CachedUsersCompanion(
      id: Value(user.id),
      email: Value(user.email),
      fullName: Value(user.fullName),
      username: Value(user.username),
      userType: Value(user.userType),
      avatarUrl: Value(user.avatarUrl),
      tenantId: Value(user.tenantId),
      rolesJson: Value(jsonEncode(user.roles)),
      permissionsJson: Value(jsonEncode(user.permissions)),
      syncedAt: Value(DateTime.now()),
    ));
  }

  /// Delete user from cache.
  Future<void> deleteUser(String id) async {
    await _db.deleteUser(id);
  }

  /// Clear all cached users.
  Future<void> clearAll() async {
    await _db.clearUsers();
  }

  User _rowToUser(CachedUser row) {
    List<String> parseList(String json) {
      try {
        final list = jsonDecode(json) as List;
        return list.map((e) => e.toString()).toList();
      } catch (_) {
        return [];
      }
    }

    return User(
      id: row.id,
      email: row.email,
      fullName: row.fullName,
      username: row.username,
      userType: row.userType,
      avatarUrl: row.avatarUrl,
      tenantId: row.tenantId,
      roles: parseList(row.rolesJson),
      permissions: parseList(row.permissionsJson),
    );
  }
}
