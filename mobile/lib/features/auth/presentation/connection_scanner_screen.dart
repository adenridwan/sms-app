import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
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
  final ImagePicker _imagePicker = ImagePicker();
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

    await _processQrContent(barcode.rawValue!);
  }

  /// Pick image from gallery and scan QR from it
  Future<void> _pickImageAndScan() async {
    try {
      final XFile? image = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 100,
      );

      if (image == null) return;

      setState(() {
        _processing = true;
        _error = null;
      });

      // In mobile_scanner 3.x, analyzeImage returns bool and triggers onDetect callback
      // The detected barcode will be handled by _onDetect method
      final bool success = await _controller.analyzeImage(image.path);

      if (!success) {
        setState(() {
          _error = 'Tidak dapat menemukan QR code dalam gambar.';
          _processing = false;
        });
      }
      // If success, _onDetect will be triggered automatically
    } catch (e) {
      setState(() {
        _error = 'Gagal memproses gambar: $e';
        _processing = false;
      });
    }
  }

  /// Process QR content (from camera or image)
  Future<void> _processQrContent(String raw) async {
    setState(() {
      _processing = true;
      _error = null;
    });

    try {
      final data = _parseQrData(raw);

      if (data == null) {
        // Show what was scanned for debugging
        final preview = raw.length > 100 ? '${raw.substring(0, 100)}...' : raw;
        setState(() {
          _error = 'QR tidak valid. Pastikan QR dari admin sekolah.\n\nKonten: $preview';
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
    // Debug: print raw content
    debugPrint('QR Raw: $raw');

    // Try JSON format first: {"api": "url", "token": "...", "school": "..."}
    try {
      final data = jsonDecode(raw) as Map<String, dynamic>;
      if (data.containsKey('api') && data.containsKey('token')) {
        debugPrint('QR Parsed as JSON');
        return data;
      }
    } catch (e) {
      debugPrint('QR Not JSON: $e');
    }

    // Try deep link format: smsapp://provision?token=...&server=...
    try {
      final uri = Uri.parse(raw);
      debugPrint('QR URI - scheme: ${uri.scheme}, host: ${uri.host}');
      debugPrint('QR URI - queryParams: ${uri.queryParameters}');

      if (uri.scheme == 'smsapp' && uri.host == 'provision') {
        final token = uri.queryParameters['token'];
        final server = uri.queryParameters['server'];
        debugPrint('QR token: $token, server: $server');

        if (token != null && server != null) {
          return {
            'api': server,
            'token': token,
            'school': uri.queryParameters['device_name'],
            'user_id': uri.queryParameters['user_id'],
            'user_name': uri.queryParameters['user_name'],
            'user_email': uri.queryParameters['user_email'],
          };
        }
      }
    } catch (e) {
      debugPrint('QR URI parse error: $e');
    }

    return null;
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
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(Icons.error, color: Colors.red),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              _error!,
                              style: const TextStyle(color: Colors.red, fontSize: 12),
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

          // Bottom actions
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  // Upload from gallery
                  TextButton.icon(
                    onPressed: _processing ? null : _pickImageAndScan,
                    icon: const Icon(Icons.photo_library_outlined),
                    label: const Text('Upload Gambar'),
                  ),
                  const SizedBox(width: 16),
                  // Manual input
                  TextButton.icon(
                    onPressed: _processing ? null : () => _showManualInput(context),
                    icon: const Icon(Icons.keyboard),
                    label: const Text('Input Manual'),
                  ),
                ],
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
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: apiCtrl,
                decoration: const InputDecoration(
                  labelText: 'API URL',
                  hintText: 'http://192.168.1.100:8080/api/v1',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: tokenCtrl,
                decoration: const InputDecoration(
                  labelText: 'Sync Token',
                ),
                maxLines: 2,
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
