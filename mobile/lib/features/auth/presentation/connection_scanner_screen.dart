import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/auth/local_session_controller.dart';
import '../../../core/qr/qr_image_decoder.dart';
import '../../attendance/presentation/class_attendance_queue_controller.dart';
import '../../attendance/presentation/scan_controller.dart';
import '../models/user.dart';
import 'auth_controller.dart';

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

      // Didekode di Dart, bukan lewat `_controller.analyzeImage`: jalur plugin
      // itu tidak ada di Windows dan melempar MissingPluginException walau
      // dialog pilih berkas berhasil.
      final raw = await decodeQrFromImageFile(image.path);

      if (!mounted) return;

      if (raw == null) {
        setState(() {
          _error = 'Tidak dapat menemukan QR code dalam gambar.';
          _processing = false;
        });
        return;
      }

      await _processQrContent(raw);
    } on QrImageReadException catch (e) {
      setState(() {
        _error = e.message;
        _processing = false;
      });
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

      // Dibaca sebelum `connectToBackend` menutup sesi akun lokal.
      final hadLocalAccount = ref.read(localSessionProvider).isLocalLoggedIn;

      // Save connection
      final user =
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

      if (user == null) {
        setState(() {
          _error = ref.read(localSessionProvider).error ??
              'Gagal menyimpan koneksi.';
          _processing = false;
        });
        return;
      }

      await _finishConnected(
        schoolName: data['school'] as String?,
        user: user,
        hadLocalAccount: hadLocalAccount,
      );
    } catch (e) {
      setState(() {
        _error = 'Error: $e';
        _processing = false;
      });
    }
  }

  /// Langkah setelah koneksi tersimpan: akui sesinya, lalu **langsung masuk**.
  ///
  /// Sesi harus diakui di [AuthController] — bukan hanya dicatat sebagai
  /// koneksi — karena auth gate dan seluruh fitur absensi membaca sesi itu.
  /// Tanpa langkah ini, penebusan token yang sudah berhasil tetap berakhir di
  /// layar login dengan pesan "Email atau password salah".
  ///
  /// Tidak ada yang ditanyakan di sini. Tawaran menyimpan password untuk mode
  /// offline muncul sebagai baris yang bisa diabaikan di Beranda: memindai QR
  /// justru dipilih supaya tak perlu mengetik password, jadi memunculkan kotak
  /// password sebelum Beranda membuat seolah masuknya gagal.
  Future<void> _finishConnected({
    required String? schoolName,
    required User user,
    bool hadLocalAccount = false,
  }) async {
    final auth = ref.read(authControllerProvider.notifier);

    await auth.adoptSession(user);
    if (!mounted) return;

    // `adoptSession` bisa menolak persona non-petugas; jangan berpura-pura
    // berhasil kalau gerbangnya menutup.
    final authState = ref.read(authControllerProvider);
    if (!authState.isAuthenticated) {
      setState(() {
        _error = authState.error ?? 'Akun ini tidak bisa memakai aplikasi.';
        _processing = false;
      });
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('Terhubung ke ${schoolName ?? 'server'}'),
        backgroundColor: Colors.green,
        duration: const Duration(seconds: 2),
      ),
    );

    // Perangkat yang tadinya dipakai dengan akun lokal perlu diberi tahu dua
    // hal: akun itu sudah dihapus, dan antrean yang terkumpul akan tercatat
    // atas nama siapa. Yang kedua bukan basa-basi — antrean tidak menyimpan
    // pemilik, dan server menetapkan pencatatnya dari token pengirim
    // (`recorded_by` = pemilik token). Jadi memindahkan kepemilikan diam-diam
    // adalah hal yang paling mudah terjadi di sini, dan paling sulit
    // ditelusuri setelahnya.
    if (hadLocalAccount) {
      final pending = ref.read(scanControllerProvider).queueCount +
          ref.read(classAttendanceQueueProvider).count;

      final buffer = StringBuffer()
        ..write('Perangkat ini sekarang terhubung ke ')
        ..write(schoolName ?? 'server sekolah')
        ..write('. Mulai sekarang gunakan user dan password akun sekolah '
            'Anda — akun lokal yang dipakai untuk menyiapkan perangkat sudah '
            'dihapus.');

      if (pending > 0) {
        buffer
          ..writeln()
          ..writeln()
          ..write('$pending catatan absensi yang belum terkirim tetap aman, ')
          ..write('dan akan tercatat atas nama ${user.fullName}.');
      }

      await showDialog<void>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Akun lokal tidak berlaku lagi'),
          content: Text(buffer.toString()),
          actions: [
            FilledButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Mengerti'),
            ),
          ],
        ),
      );
      if (!mounted) return;
    }

    // `go`, bukan `pop`: layar ini bisa dibuka dari login (tidak ada yang
    // di-pop ke mana-mana) maupun dari Profil.
    context.go('/home');
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
                  'Pindai QR dari admin sekolah untuk menghubungkan aplikasi ini.',
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
                  labelText: 'Alamat Sekolah',
                  hintText: 'http://192.168.1.100:8080/api/v1',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: tokenCtrl,
                decoration: const InputDecoration(
                  labelText: 'Kode Koneksi',
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
                setState(() => _error = 'Alamat Sekolah dan Kode Koneksi wajib diisi');
                return;
              }

              setState(() => _processing = true);

              final school = schoolCtrl.text.trim().isEmpty
                  ? null
                  : schoolCtrl.text.trim();

              final hadLocal = ref.read(localSessionProvider).isLocalLoggedIn;

              final user = await ref
                  .read(localSessionProvider.notifier)
                  .connectToBackend(
                    apiUrl: apiCtrl.text.trim(),
                    syncToken: tokenCtrl.text.trim(),
                    schoolName: school,
                  );

              if (!mounted) return;

              if (user == null) {
                setState(() {
                  _error = ref.read(localSessionProvider).error ??
                      'Gagal menyimpan koneksi.';
                  _processing = false;
                });
                return;
              }

              await _finishConnected(
                schoolName: school,
                user: user,
                hadLocalAccount: hadLocal,
              );
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
  }
}
