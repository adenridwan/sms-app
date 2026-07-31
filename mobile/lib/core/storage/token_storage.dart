import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Penyimpanan token Bearer di secure storage (Keystore/Keychain).
///
/// Token TIDAK pernah ditulis ke log atau shared preferences biasa.
class TokenStorage {
  TokenStorage([FlutterSecureStorage? storage])
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(encryptedSharedPreferences: true),
            );

  final FlutterSecureStorage _storage;
  static const _kToken = 'auth_token';

  Future<String?> read() => _storage.read(key: _kToken);

  Future<void> save(String token) => _storage.write(key: _kToken, value: token);

  Future<void> clear() => _storage.delete(key: _kToken);
}
