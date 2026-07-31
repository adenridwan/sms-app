import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../data/auth_repository.dart';
import '../models/user.dart';

enum AuthStatus { unknown, unauthenticated, authenticating, authenticated }

/// State autentikasi global yang mengendalikan routing (auth gate).
class AuthState {
  const AuthState({
    this.status = AuthStatus.unknown,
    this.user,
    this.error,
  });

  final AuthStatus status;
  final User? user;
  final String? error;

  bool get isAuthenticated => status == AuthStatus.authenticated;
  bool get isBusy => status == AuthStatus.authenticating;

  AuthState copyWith({AuthStatus? status, User? user, String? error}) {
    return AuthState(
      status: status ?? this.status,
      user: user ?? this.user,
      error: error,
    );
  }
}

class AuthController extends StateNotifier<AuthState> {
  AuthController(this._repo) : super(const AuthState()) {
    _bootstrap();
  }

  final AuthRepository _repo;

  /// Dipanggil sekali saat start: tentukan sesi dari token tersimpan.
  Future<void> _bootstrap() async {
    final token = await _repo.currentToken();
    if (token == null || token.isEmpty) {
      state = const AuthState(status: AuthStatus.unauthenticated);
      return;
    }
    try {
      final user = await _repo.me();
      state = AuthState(status: AuthStatus.authenticated, user: user);
    } on ApiException catch (e) {
      // Token tidak valid / kadaluarsa → paksa login.
      if (e.isUnauthorized) {
        await _repo.clearToken();
      }
      state = AuthState(
        status: AuthStatus.unauthenticated,
        error: e.isNetwork ? e.message : null,
      );
    }
  }

  /// Login dengan email + password, lalu muat profil lengkap (permissions).
  Future<void> login({
    required String email,
    required String password,
    bool remember = true,
  }) async {
    state = const AuthState(status: AuthStatus.authenticating);
    try {
      await _repo.login(email: email, password: password, remember: remember);
      final user = await _repo.me();

      if (!user.canUseAttendanceApp) {
        // Persona tak berhak (mis. siswa/orang tua) — tolak di klien.
        await _repo.logout();
        state = const AuthState(
          status: AuthStatus.unauthenticated,
          error:
              'Akun ini tidak memiliki akses ke aplikasi absensi petugas.',
        );
        return;
      }

      state = AuthState(status: AuthStatus.authenticated, user: user);
    } on ApiException catch (e) {
      state = AuthState(status: AuthStatus.unauthenticated, error: e.message);
    }
  }

  Future<void> logout() async {
    await _repo.logout();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }

  /// Dibersihkan saat mendeteksi 401 di tempat lain.
  Future<void> forceLogout() async {
    await _repo.clearToken();
    state = const AuthState(status: AuthStatus.unauthenticated);
  }
}

final authControllerProvider =
    StateNotifierProvider<AuthController, AuthState>((ref) {
  return AuthController(ref.watch(authRepositoryProvider));
});
