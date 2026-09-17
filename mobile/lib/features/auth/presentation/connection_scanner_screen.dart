import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/auth/local_session_controller.dart';

/// Screen for scanning QR code to connect to school backend.
///
/// QR contains: { "api": "url", "token": "sync_token", "school": "name" }
class ConnectionScannerScreen extends ConsumerStatefulWidget {
  const ConnectionScannerScreen({super.key});

  @override
  ConsumerState<ConnectionScannerScreen> createState() =>
      _ConnectionScannerScreenState();
}

class _ConnectionScannerScreenState
    extends ConsumerState<ConnectionScannerScreen> {
  final MobileScannerController _controller = MobileScannerController();
  bool _processing = false;
  String? _error;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_processing) return;

    final barcode = capture.barcodes.firstOrNull;
    if (barcode == null || barcode.rawValue == null) return;

    setState(() {
      _processing = true;
      _error = null;
    });

    try {
      final data = _parseQrData(barcode.rawValue!);

      if (data == null) {
        setState(() {
          _error = 'QR tidak valid. Pastikan QR dari admin sekolah.';
          _processing = false;
        });
        return;
      }

      // Save connection
      final success =
          await ref.read(localSessionProvider.notifier).connectToBackend(
                apiUrl: data['api'] as String,
                syncToken: data['token'] as String,
                schoolName: data['school'] as String?,
                backendUserId: data['user_id'] as String?,
                backendUserName: data['user_name'] as String?,
                backendUserEmail: data['user_email'] as String?,
                permissions: (data['permissions'] as List?)?.cast<String>() ?? [],
              );

      if (!mounted) return;

      if (success) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Terhubung ke ${data['school'] ?? 'server'}',
            ),
            backgroundColor: Colors.green,
          ),
        );
        context.pop(true);
      } else {
        setState(() {
          _error = 'Gagal menyimpan koneksi.';
          _processing = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = 'Error: $e';
        _processing = false;
      });
    }
  }

  Map<String, dynamic>? _parseQrData(String raw) {
    try {
      final data = jsonDecode(raw) as Map<String, dynamic>;

      // Must have api and token
      if (!data.containsKey('api') || !data.containsKey('token')) {
        return null;
      }

      return data;
    } catch (_) {
      return null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Scan QR Koneksi'),
        backgroundColor: Colors.transparent,
      ),
      body: Column(
        children: [
          // Info
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            color: scheme.primaryContainer.withValues(alpha: 0.3),
            child: Column(
              children: [
                Icon(Icons.qr_code_2, size: 32, color: scheme.primary),
                const SizedBox(height: 8),
                Text(
                  'Scan QR dari admin sekolah untuk menghubungkan aplikasi ke server.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),

          // Scanner
          Expanded(
            child: Stack(
              children: [
                MobileScanner(
                  controller: _controller,
                  onDetect: _onDetect,
                ),

                // Overlay
                Center(
                  child: Container(
                    width: 250,
                    height: 250,
                    decoration: BoxDecoration(
                      border: Border.all(
                        color: _processing ? Colors.green : Colors.white,
                        width: 3,
                      ),
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),

                // Processing indicator
                if (_processing)
                  Container(
                    color: Colors.black54,
                    child: const Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          CircularProgressIndicator(color: Colors.white),
                          SizedBox(height: 16),
                          Text(
                            'Menghubungkan...',
                            style: TextStyle(color: Colors.white),
                          ),
                        ],
                      ),
                    ),
                  ),

                // Error
                if (_error != null)
                  Positioned(
                    bottom: 100,
                    left: 20,
                    right: 20,
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.red.shade100,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.error, color: Colors.red),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              _error!,
                              style: const TextStyle(color: Colors.red),
                            ),
                          ),
                          IconButton(
                            onPressed: () => setState(() {
                              _error = null;
                              _processing = false;
                            }),
                            icon: const Icon(Icons.close, color: Colors.red),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          ),

          // Manual input option
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: TextButton.icon(
                onPressed: () => _showManualInput(context),
                icon: const Icon(Icons.keyboard),
                label: const Text('Input Manual'),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _showManualInput(BuildContext context) {
    final apiCtrl = TextEditingController();
    final tokenCtrl = TextEditingController();
    final schoolCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Input Manual'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: apiCtrl,
              decoration: const InputDecoration(
                labelText: 'API URL',
                hintText: 'https://sekolah.sch.id/api/v1',
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: tokenCtrl,
              decoration: const InputDecoration(
                labelText: 'Sync Token',
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: schoolCtrl,
              decoration: const InputDecoration(
                labelText: 'Nama Sekolah (opsional)',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () async {
              Navigator.pop(ctx);

              if (apiCtrl.text.isEmpty || tokenCtrl.text.isEmpty) {
                setState(() => _error = 'API URL dan Token wajib diisi');
                return;
              }

              setState(() => _processing = true);

              final success = await ref
                  .read(localSessionProvider.notifier)
                  .connectToBackend(
                    apiUrl: apiCtrl.text.trim(),
                    syncToken: tokenCtrl.text.trim(),
                    schoolName: schoolCtrl.text.trim().isEmpty
                        ? null
                        : schoolCtrl.text.trim(),
                  );

              if (mounted) {
                if (success) {
                  context.pop(true);
                } else {
                  setState(() {
                    _error = 'Gagal menyimpan koneksi.';
                    _processing = false;
                  });
                }
              }
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
  }
}
