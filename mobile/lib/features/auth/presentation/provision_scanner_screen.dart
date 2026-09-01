import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/config/app_config.dart';
import '../../../core/providers.dart';
import 'auth_controller.dart';

/// Layar pemindai QR untuk login via provisioning.
///
/// Admin men-generate token provisioning untuk user tertentu, lalu user scan
/// QR di perangkat baru untuk langsung login tanpa ketik password.
///
/// Jika [initialToken] diberikan (dari deep link), langsung proses tanpa scan.
class ProvisionScannerScreen extends ConsumerStatefulWidget {
  const ProvisionScannerScreen({super.key, this.initialToken});

  /// Token dari deep link, jika ada. Langsung diproses tanpa perlu scan.
  final String? initialToken;

  @override
  ConsumerState<ProvisionScannerScreen> createState() =>
      _ProvisionScannerScreenState();
}

class _ProvisionScannerScreenState
    extends ConsumerState<ProvisionScannerScreen> {
  final _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    facing: CameraFacing.back,
  );

  bool _busy = false;
  String? _lastCode;
  DateTime? _lastAt;

  @override
  void initState() {
    super.initState();
    // Jika ada token dari deep link, langsung proses
    if (widget.initialToken != null && widget.initialToken!.length == 64) {
      // Delay sedikit agar widget sudah mounted
      Future.microtask(() => _processProvision({'token': widget.initialToken!}));
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  /// Proses QR provisioning (dari scan atau deep link).
  Future<void> _processProvision(Map<String, String> qrData) async {
    if (_busy || !mounted) return;

    setState(() => _busy = true);

    final token = qrData['token']!;
    final serverUrl = qrData['server'];

    debugPrint('[Provision] Token: ${token.substring(0, 10)}...');
    debugPrint('[Provision] Server URL dari QR: $serverUrl');

    // Jika ada server URL dari QR, simpan dan update Dio baseUrl langsung
    if (serverUrl != null && serverUrl.isNotEmpty) {
      await AppConfig.setServerUrl(serverUrl);
      // Update Dio baseUrl langsung (tanpa invalidate provider)
      final dio = ref.read(dioProvider);
      dio.options.baseUrl = AppConfig.baseUrl;
      debugPrint('[Provision] Dio baseUrl updated to: ${dio.options.baseUrl}');
    } else {
      debugPrint('[Provision] No server URL in QR, using: ${AppConfig.baseUrl}');
    }

    try {
      debugPrint('[Provision] Calling loginWithProvision...');
      await ref
          .read(authControllerProvider.notifier)
          .loginWithProvision(provisionToken: token);
      debugPrint('[Provision] loginWithProvision completed');
    } catch (e) {
      debugPrint('[Provision] Error during login: $e');
    }

    if (!mounted) return;

    final state = ref.read(authControllerProvider);
    debugPrint('[Provision] Auth state: ${state.status}, error: ${state.error}');

    if (state.isAuthenticated) {
      debugPrint('[Provision] Login successful, navigating to /home');
      context.go('/home');
    } else {
      setState(() => _busy = false);
      final errorMsg = state.error ?? 'Gagal login dengan QR';
      debugPrint('[Provision] Login failed: $errorMsg');
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(
          content: Text(errorMsg),
          backgroundColor: Colors.red,
        ));
    }
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_busy || !mounted) return;
    if (capture.barcodes.isEmpty) return;
    final rawValue = capture.barcodes.first.rawValue;
    if (rawValue == null || rawValue.isEmpty) return;

    // Debounce kode sama dalam 3 detik agar tidak dobel.
    final now = DateTime.now();
    if (_lastCode == rawValue &&
        _lastAt != null &&
        now.difference(_lastAt!) < const Duration(seconds: 3)) {
      return;
    }

    _lastCode = rawValue;
    _lastAt = now;

    // Parse QR: smsapp://provision?token=xxx&server=http://...
    final qrData = _parseProvisionQr(rawValue);
    if (qrData == null) {
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(const SnackBar(
            content: Text('QR ini bukan kode provisioning. '
                'Minta admin untuk generate ulang.'),
            backgroundColor: Colors.orange,
          ));
      }
      return;
    }

    await _processProvision(qrData);
  }

  /// Parse QR provisioning: smsapp://provision?token=xxx&server=http://...
  /// Returns map dengan 'token' dan optional 'server'.
  Map<String, String>? _parseProvisionQr(String rawValue) {
    debugPrint('[Provision] Raw QR value: $rawValue');

    // Support both deep link and raw token
    if (rawValue.startsWith('smsapp://provision')) {
      final uri = Uri.tryParse(rawValue);
      debugPrint('[Provision] Parsed URI: $uri');
      debugPrint('[Provision] Query params: ${uri?.queryParameters}');

      final token = uri?.queryParameters['token'];
      if (token == null || token.isEmpty) {
        debugPrint('[Provision] No token found in QR');
        return null;
      }

      final result = {
        'token': token,
        if (uri?.queryParameters['server'] != null)
          'server': uri!.queryParameters['server']!,
        if (uri?.queryParameters['device_name'] != null)
          'device_name': uri!.queryParameters['device_name']!,
        if (uri?.queryParameters['location'] != null)
          'location': uri!.queryParameters['location']!,
      };
      debugPrint('[Provision] Parsed result: $result');
      return result;
    }
    // Jika bukan deep link tapi panjangnya 64 karakter, anggap raw token
    if (rawValue.length == 64 && !rawValue.contains('/')) {
      debugPrint('[Provision] Raw token detected');
      return {'token': rawValue};
    }
    debugPrint('[Provision] Invalid QR format');
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: const Text('Scan QR Akses'),
        actions: [
          IconButton(
            tooltip: 'Senter',
            icon: const Icon(Icons.flashlight_on_rounded),
            onPressed: () => _controller.toggleTorch(),
          ),
          IconButton(
            tooltip: 'Ganti kamera',
            icon: const Icon(Icons.cameraswitch_rounded),
            onPressed: () => _controller.switchCamera(),
          ),
        ],
      ),
      body: Stack(
        children: [
          MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
            errorBuilder: (context, error, child) => Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  'Kamera tidak tersedia atau izin ditolak.\n${error.errorCode.name}',
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white70),
                ),
              ),
            ),
          ),
          // Bingkai target dengan warna aksen tema
          Center(
            child: Container(
              width: 240,
              height: 240,
              decoration: BoxDecoration(
                border: Border.all(color: scheme.primary, width: 3),
                borderRadius: BorderRadius.circular(16),
              ),
            ),
          ),
          // Instruksi
          Positioned(
            left: 24,
            right: 24,
            bottom: 100,
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.black54,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.qr_code_2_rounded, color: scheme.primary, size: 32),
                  const SizedBox(height: 8),
                  const Text(
                    'Arahkan kamera ke QR Akses\nyang diberikan administrator',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Colors.white70),
                  ),
                ],
              ),
            ),
          ),
          if (_busy)
            Container(
              color: Colors.black87,
              child: Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CircularProgressIndicator(color: scheme.primary),
                    const SizedBox(height: 16),
                    Text(
                      'Memproses login...',
                      style: TextStyle(color: Colors.white.withOpacity(0.8)),
                    ),
                  ],
                ),
              ),
            ),
          // Tombol kembali ke login manual
          Positioned(
            left: 0,
            right: 0,
            bottom: 32,
            child: Center(
              child: TextButton.icon(
                onPressed: () => context.pop(),
                icon:
                    const Icon(Icons.arrow_back_rounded, color: Colors.white70),
                label: const Text('Kembali ke login',
                    style: TextStyle(color: Colors.white70)),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
