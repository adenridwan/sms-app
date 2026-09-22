import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/config/app_config.dart';
import '../../../core/providers.dart';
import '../../../core/theme/ui_kit.dart';

/// Layar konfigurasi server manual.
///
/// User bisa memasukkan URL server API secara manual tanpa harus:
/// - Rebuild APK dengan --dart-define
/// - Scan QR provisioning
///
/// Berguna untuk:
/// - Setup awal di perangkat baru
/// - Pindah server/jaringan
/// - Troubleshooting koneksi
class ServerConfigScreen extends ConsumerStatefulWidget {
  const ServerConfigScreen({super.key});

  @override
  ConsumerState<ServerConfigScreen> createState() => _ServerConfigScreenState();
}

class _ServerConfigScreenState extends ConsumerState<ServerConfigScreen> {
  final _formKey = GlobalKey<FormState>();
  final _urlCtrl = TextEditingController();

  bool _testing = false;
  bool _saving = false;
  _TestResult? _testResult;

  @override
  void initState() {
    super.initState();
    _urlCtrl.text = AppConfig.baseUrl;
  }

  @override
  void dispose() {
    _urlCtrl.dispose();
    super.dispose();
  }

  /// Test koneksi ke server dengan memanggil /ping endpoint.
  Future<void> _testConnection() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _testing = true;
      _testResult = null;
    });

    final url = _normalizeUrl(_urlCtrl.text.trim());

    try {
      final dio = Dio(BaseOptions(
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 10),
      ));

      final stopwatch = Stopwatch()..start();
      final response = await dio.get('$url/ping');
      stopwatch.stop();

      if (response.statusCode == 200) {
        setState(() {
          _testResult = _TestResult(
            success: true,
            message: 'Server terhubung! (${stopwatch.elapsedMilliseconds}ms)',
            serverInfo: response.data is Map
                ? response.data['message']?.toString()
                : null,
          );
        });
      } else {
        setState(() {
          _testResult = _TestResult(
            success: false,
            message: 'Server merespons dengan status ${response.statusCode}',
          );
        });
      }
    } on DioException catch (e) {
      String message;
      if (e.type == DioExceptionType.connectionTimeout) {
        message = 'Timeout - server tidak merespons dalam 10 detik';
      } else if (e.type == DioExceptionType.connectionError) {
        message = 'Tidak dapat terhubung ke server. Periksa:\n'
            '• URL sudah benar\n'
            '• Server sedang berjalan\n'
            '• Perangkat terhubung ke jaringan yang sama';
      } else if (e.response != null) {
        message = 'Server error: ${e.response?.statusCode}';
      } else {
        message = 'Gagal: ${e.message}';
      }

      setState(() {
        _testResult = _TestResult(success: false, message: message);
      });
    } finally {
      setState(() => _testing = false);
    }
  }

  /// Simpan URL dan update Dio baseUrl.
  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;

    // Sarankan test dulu jika belum
    if (_testResult == null) {
      final proceed = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Belum Di-test'),
          content: const Text(
            'Anda belum mengetes koneksi ke server ini. '
            'Yakin ingin menyimpan tanpa test?',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Test Dulu'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Simpan Saja'),
            ),
          ],
        ),
      );
      if (proceed != true) return;
    }

    // Jika test gagal, konfirmasi
    if (_testResult != null && !_testResult!.success) {
      final proceed = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Test Gagal'),
          content: const Text(
            'Test koneksi terakhir gagal. '
            'Yakin ingin menyimpan URL ini?',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              style: FilledButton.styleFrom(backgroundColor: Colors.orange),
              child: const Text('Simpan'),
            ),
          ],
        ),
      );
      if (proceed != true) return;
    }

    setState(() => _saving = true);

    try {
      final url = _normalizeUrl(_urlCtrl.text.trim());

      // Simpan ke secure storage
      await AppConfig.setServerUrl(url);

      // Update Dio instance yang sedang dipakai
      final dio = ref.read(dioProvider);
      dio.options.baseUrl = url;

      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(const SnackBar(
            content: Text('URL server berhasil disimpan'),
            backgroundColor: Colors.green,
          ));
        context.pop();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(
            content: Text('Gagal menyimpan: $e'),
            backgroundColor: Colors.red,
          ));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  /// Reset ke URL default (dari build-time).
  Future<void> _reset() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Reset ke Default'),
        content: Text(
          'Kembalikan URL ke default build-time?\n\n'
          'Default: ${const String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000/api/v1')}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Reset'),
          ),
        ],
      ),
    );

    if (ok == true) {
      await AppConfig.resetServerUrl();
      setState(() {
        _urlCtrl.text = AppConfig.baseUrl;
        _testResult = null;
      });

      // Update Dio
      final dio = ref.read(dioProvider);
      dio.options.baseUrl = AppConfig.baseUrl;

      if (mounted) {
        ScaffoldMessenger.of(context)
          ..hideCurrentSnackBar()
          ..showSnackBar(const SnackBar(
            content: Text('URL direset ke default'),
          ));
      }
    }
  }

  /// Normalisasi URL: hapus trailing slash, pastikan ada /api/v1.
  String _normalizeUrl(String url) {
    var normalized = url.trim();

    // Hapus trailing slash
    while (normalized.endsWith('/')) {
      normalized = normalized.substring(0, normalized.length - 1);
    }

    // Jika belum ada /api/v1, tambahkan
    if (!normalized.endsWith('/api/v1')) {
      if (normalized.endsWith('/api')) {
        normalized = '$normalized/v1';
      } else if (!normalized.contains('/api')) {
        normalized = '$normalized/api/v1';
      }
    }

    return normalized;
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final isProvisioned = AppConfig.isProvisioned;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Konfigurasi Server'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          // Info
          InfoStrip(
            margin: EdgeInsets.zero,
            tone: StripTone.neutral,
            text: 'Masukkan URL server API sekolah Anda. '
                'Format: http://IP:PORT/api/v1 atau https://domain.sch.id/api/v1',
          ),

          const SizedBox(height: 20),

          // Status saat ini
          Panel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(
                      isProvisioned ? Icons.cloud_done : Icons.cloud_off,
                      size: 18,
                      color: isProvisioned ? Colors.green : Colors.grey,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      isProvisioned
                          ? 'Menggunakan URL kustom'
                          : 'Menggunakan URL default',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  'Aktif: ${AppConfig.baseUrl}',
                  style: TextStyle(
                    fontSize: 11,
                    color: scheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 20),

          // Form input URL
          Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const FieldLabel('URL Server API'),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _urlCtrl,
                  enabled: !_testing && !_saving,
                  keyboardType: TextInputType.url,
                  autocorrect: false,
                  decoration: InputDecoration(
                    hintText: 'http://192.168.1.100:8080/api/v1',
                    prefixIcon: const Icon(Icons.link),
                    suffixIcon: _urlCtrl.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear),
                            onPressed: () {
                              _urlCtrl.clear();
                              setState(() => _testResult = null);
                            },
                          )
                        : null,
                  ),
                  validator: (v) {
                    final s = (v ?? '').trim();
                    if (s.isEmpty) return 'URL wajib diisi';
                    if (!s.startsWith('http://') && !s.startsWith('https://')) {
                      return 'URL harus dimulai dengan http:// atau https://';
                    }
                    final uri = Uri.tryParse(s);
                    if (uri == null || !uri.hasAuthority) {
                      return 'Format URL tidak valid';
                    }
                    return null;
                  },
                  onChanged: (_) => setState(() => _testResult = null),
                ),

                const SizedBox(height: 16),

                // Tombol test
                OutlinedButton.icon(
                  onPressed: _testing || _saving ? null : _testConnection,
                  icon: _testing
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.wifi_find, size: 18),
                  label: Text(_testing ? 'Mengetes...' : 'Test Koneksi'),
                ),

                // Hasil test
                if (_testResult != null) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: _testResult!.success
                          ? Colors.green.withOpacity(0.1)
                          : Colors.red.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                        color: _testResult!.success
                            ? Colors.green.withOpacity(0.3)
                            : Colors.red.withOpacity(0.3),
                      ),
                    ),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          _testResult!.success
                              ? Icons.check_circle
                              : Icons.error,
                          color:
                              _testResult!.success ? Colors.green : Colors.red,
                          size: 20,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _testResult!.message,
                                style: TextStyle(
                                  fontSize: 12,
                                  color: _testResult!.success
                                      ? Colors.green.shade800
                                      : Colors.red.shade800,
                                ),
                              ),
                              if (_testResult!.serverInfo != null) ...[
                                const SizedBox(height: 4),
                                Text(
                                  _testResult!.serverInfo!,
                                  style: TextStyle(
                                    fontSize: 11,
                                    color: scheme.onSurfaceVariant,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ],

                const SizedBox(height: 24),

                // Tombol simpan
                FilledButton.icon(
                  onPressed: _testing || _saving ? null : _save,
                  icon: _saving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.save, size: 18),
                  label: Text(_saving ? 'Menyimpan...' : 'Simpan'),
                ),

                const SizedBox(height: 8),

                // Tombol reset
                if (isProvisioned)
                  TextButton.icon(
                    onPressed: _testing || _saving ? null : _reset,
                    icon: const Icon(Icons.restore, size: 18),
                    label: const Text('Reset ke Default'),
                  ),
              ],
            ),
          ),

          const SizedBox(height: 24),

          // Tips
          const FieldLabel('Tips'),
          const SizedBox(height: 8),
          Panel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _TipRow(
                  icon: Icons.computer,
                  text: 'Untuk emulator Android, gunakan 10.0.2.2 '
                      '(bukan localhost)',
                ),
                const SizedBox(height: 8),
                _TipRow(
                  icon: Icons.phone_android,
                  text: 'Untuk HP fisik, gunakan IP komputer server '
                      '(misal 192.168.1.100)',
                ),
                const SizedBox(height: 8),
                _TipRow(
                  icon: Icons.wifi,
                  text: 'Pastikan HP dan server di jaringan WiFi yang sama',
                ),
                const SizedBox(height: 8),
                _TipRow(
                  icon: Icons.lock,
                  text: 'Untuk produksi, gunakan HTTPS dan domain '
                      '(bukan IP)',
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TestResult {
  const _TestResult({
    required this.success,
    required this.message,
    this.serverInfo,
  });

  final bool success;
  final String message;
  final String? serverInfo;
}

class _TipRow extends StatelessWidget {
  const _TipRow({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 16, color: scheme.primary),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            text,
            style: TextStyle(
              fontSize: 12,
              color: scheme.onSurfaceVariant,
            ),
          ),
        ),
      ],
    );
  }
}
