import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../features/auth/data/auth_repository.dart';
import 'network/api_client.dart';
import 'storage/token_storage.dart';

/// Penyimpanan token (secure storage).
final tokenStorageProvider = Provider<TokenStorage>((ref) => TokenStorage());

/// Instance Dio terkonfigurasi (base URL, header, Bearer interceptor).
final dioProvider = Provider<Dio>((ref) {
  final storage = ref.watch(tokenStorageProvider);
  return buildDio(storage);
});

/// Repository autentikasi.
final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    dio: ref.watch(dioProvider),
    tokenStorage: ref.watch(tokenStorageProvider),
  );
});
