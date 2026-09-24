import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../core/qr/qr_image_decoder.dart';
import '../../../core/security/app_lock.dart';
import 'scan_controller.dart';
import 'widgets/result_sheet.dart';

/// Layar pemindai QR. Deteksi otomatis → submit → tampilkan hasil → lanjut.
class ScanCameraScreen extends ConsumerStatefulWidget {
  const ScanCameraScreen({super.key});

  @override
  ConsumerState<ScanCameraScreen> createState() => _ScanCameraScreenState();
}

class _ScanCameraScreenState extends ConsumerState<ScanCameraScreen> {
  final _controller = MobileScannerController(
    // noDuplicates lebih cepat dari normal, dan sudah mencegah kode sama
    // terdeteksi berulang dalam satu sesi. Tidak perlu debounce manual.
    detectionSpeed: DetectionSpeed.noDuplicates,
    facing: CameraFacing.back,
  );

  final _imagePicker = ImagePicker();

  bool _busy = false;
  String? _lastCode;
  DateTime? _lastAt;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    // Untuk scan dari galeri, _busy sudah true. Untuk kamera live, cek _busy.
    if (!_waitingGalleryResult && _busy) return;
    if (!mounted) return;
    if (capture.barcodes.isEmpty) return;
    final code = capture.barcodes.first.rawValue;
    if (code == null || code.isEmpty) return;

    // Dengan DetectionSpeed.noDuplicates, mobile_scanner sudah mencegah kode
    // sama terdeteksi berulang. Debounce manual tetap dipertahankan sebagai
    // pengaman tambahan (misal: pengguna menggerakkan kamera keluar-masuk
    // frame QR dalam waktu singkat).
    final now = DateTime.now();
    if (!_waitingGalleryResult &&
        _lastCode == code &&
        _lastAt != null &&
        now.difference(_lastAt!) < const Duration(seconds: 2)) {
      return;
    }

    // Set busy untuk kamera live (untuk galeri sudah di-set sebelumnya)
    if (!_waitingGalleryResult) {
      setState(() => _busy = true);
    }
    _lastCode = code;
    _lastAt = now;

    // Memindai adalah aktivitas, walau layar tak disentuh. Tanpa ini petugas
    // di gerbang — yang justru paling sibuk — akan terkunci tiap 30 detik.
    ref.read(appLockProvider.notifier).poke();

    final result = await ref.read(scanControllerProvider.notifier).submit(code);
    if (mounted) await showResultSheet(context, result);
    if (mounted) {
      setState(() {
        _busy = false;
        _waitingGalleryResult = false;
      });
    }
  }

  /// Pilih gambar dari galeri dan pindai QR di dalamnya.
  ///
  /// Gambar didekode di Dart lewat [decodeQrFromImageFile], bukan lewat
  /// `MobileScannerController.analyzeImage`: jalur plugin itu tidak ada di
  /// Windows dan melempar `MissingPluginException` walau pemilih berkasnya
  /// berhasil. Karena hasilnya kembali langsung — tidak lewat callback
  /// `onDetect` — `_waitingGalleryResult` dipakai supaya `_onDetect` tahu
  /// deteksi ini berasal dari gambar, sehingga debounce kamera dilewati.
  bool _waitingGalleryResult = false;

  Future<void> _scanFromGallery() async {
    if (_busy) return;

    final XFile? image = await _imagePicker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 100,
    );
    if (image == null || !mounted) return;

    setState(() {
      _busy = true;
      _waitingGalleryResult = true;
    });

    try {
      final raw = await decodeQrFromImageFile(image.path);

      if (!mounted) return;

      if (raw == null) {
        setState(() {
          _busy = false;
          _waitingGalleryResult = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('QR Code tidak ditemukan dalam gambar'),
            backgroundColor: Colors.red,
          ),
        );
        return;
      }

      await _onDetect(
        BarcodeCapture(barcodes: [Barcode(rawValue: raw)]),
      );
    } on QrImageReadException catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _waitingGalleryResult = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.message), backgroundColor: Colors.red),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _waitingGalleryResult = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Gagal memindai gambar: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final mode = ref.watch(scanControllerProvider).mode;

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text('Scan ${mode.label}'),
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
          // Bingkai target
          Center(
            child: Container(
              width: 240,
              height: 240,
              decoration: BoxDecoration(
                border: Border.all(color: Colors.white70, width: 2),
                borderRadius: BorderRadius.circular(16),
              ),
            ),
          ),
          if (_busy)
            const ColoredBox(
              color: Colors.black45,
              child: Center(child: CircularProgressIndicator()),
            ),
          Positioned(
            left: 0,
            right: 0,
            bottom: 32,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                TextButton.icon(
                  onPressed: _busy ? null : _scanFromGallery,
                  icon: const Icon(Icons.photo_library_outlined,
                      color: Colors.white),
                  label: const Text('Galeri',
                      style: TextStyle(color: Colors.white)),
                ),
                const SizedBox(width: 24),
                TextButton.icon(
                  onPressed: () => context.pushReplacement('/manual'),
                  icon: const Icon(Icons.keyboard_alt_outlined,
                      color: Colors.white),
                  label: const Text('Input manual',
                      style: TextStyle(color: Colors.white)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
