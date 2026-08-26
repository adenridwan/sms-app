import 'dart:async';

import 'package:app_links/app_links.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/auth_controller.dart';
import 'app_router.dart';

/// Handler untuk deep link `smsapp://provision?token=xxx`.
///
/// Mendengarkan link yang masuk saat app terbuka (stream) dan link yang
/// dipakai untuk membuka app (initial). Token diekstrak lalu dinavigasikan
/// ke provision scanner dengan token sebagai argumen.
class DeepLinkHandler {
  DeepLinkHandler(this._ref) {
    _init();
  }

  final Ref _ref;
  final _appLinks = AppLinks();
  StreamSubscription<Uri>? _sub;

  /// Router untuk navigasi.
  GoRouter get _router => _ref.read(routerProvider);

  void _init() {
    // Link yang dipakai untuk membuka app (cold start).
    _appLinks.getInitialLink().then((uri) {
      if (uri != null) _handleUri(uri);
    });

    // Link yang masuk saat app sudah terbuka (warm start).
    _sub = _appLinks.uriLinkStream.listen(_handleUri);
  }

  void _handleUri(Uri uri) {
    debugPrint('[DeepLink] Received: $uri');

    // Format: smsapp://provision?token=xxx
    if (uri.scheme == 'smsapp' && uri.host == 'provision') {
      final token = uri.queryParameters['token'];
      if (token != null && token.length == 64) {
        // Cek apakah user sudah login
        final authState = _ref.read(authControllerProvider);
        if (authState.isAuthenticated) {
          // Sudah login, tidak perlu provision
          debugPrint('[DeepLink] Already authenticated, ignoring provision link');
          return;
        }

        // Navigasi ke provision scanner dengan token
        _router.go('/provision-scan?token=$token');
      }
    }
  }

  void dispose() {
    _sub?.cancel();
  }
}

/// Provider untuk DeepLinkHandler.
final deepLinkHandlerProvider = Provider<DeepLinkHandler>((ref) {
  final handler = DeepLinkHandler(ref);
  ref.onDispose(() => handler.dispose());
  return handler;
});
