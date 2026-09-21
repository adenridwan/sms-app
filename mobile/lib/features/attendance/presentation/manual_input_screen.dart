import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../data/attendance_providers.dart';
import '../models/scan_time.dart';
import '../models/student_lookup.dart';
import '../../../core/theme/ui_kit.dart';
import 'scan_controller.dart';
import 'widgets/result_sheet.dart';

/// Input kode Ref ID (unique_code / RFID) manual + pratinjau identitas.
///
/// Pratinjau bersifat **opsional**: ia hanya membantu memastikan kode yang
/// diketik benar. Absensi tetap bisa dicatat tanpa pratinjau — kalau tidak,
/// petugas mustahil mencatat apa pun saat server tak terjangkau, padahal
/// antrean offline justru dibuat untuk keadaan itu.
/// Layar penuh, dipakai saat Ref ID dibuka lewat rute `/manual`.
class ManualInputScreen extends ConsumerWidget {
  const ManualInputScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(scanControllerProvider).mode;
    return Scaffold(
      appBar: AppBar(title: Text('Input Ref ID · ${mode.label}')),
      body: const ManualInputView(),
    );
  }
}

/// Isi Ref ID tanpa Scaffold, supaya bisa ditanam langsung di tab Absensi —
/// rujukan desain menukar isi layar di tempat, bukan membuka layar baru.
class ManualInputView extends ConsumerStatefulWidget {
  const ManualInputView({super.key});

  @override
  ConsumerState<ManualInputView> createState() => _ManualInputViewState();
}

class _ManualInputViewState extends ConsumerState<ManualInputView> {
  final _ctrl = TextEditingController();
  LookupResult? _preview;
  bool _looking = false;
  bool _submitting = false;
  String? _error;

  /// True bila pratinjau gagal karena **jaringan**, bukan karena kodenya salah.
  /// Membedakan keduanya penting: offline tetap boleh menyimpan (masuk antrean),
  /// sedangkan kode yang benar-benar tak dikenal sebaiknya dicegah lebih awal.
  bool _offline = false;

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  String get _code => _ctrl.text.trim();

  /// Boleh menyimpan bila ada kode dan pratinjau **tidak** menyatakan kodenya
  /// tak dikenal. Saat offline `_error` sengaja dibiarkan null, sehingga
  /// penyimpanan tetap terbuka dan hasilnya masuk antrean.
  bool get _canSubmit =>
      _code.isNotEmpty && !_submitting && !_looking && _error == null;

  Future<void> _lookup() async {
    if (_code.isEmpty) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _looking = true;
      _error = null;
      _offline = false;
      _preview = null;
    });
    try {
      final res = await ref.read(attendanceRepositoryProvider).lookup(_code);
      setState(() {
        _preview = res;
        if (res == null) {
          _error = 'Kode tidak ditemukan. Periksa ejaan dan coba lagi.';
        }
      });
    } on ApiException catch (e) {
      setState(() {
        _offline = e.isNetwork;
        _error = e.isNetwork ? null : e.message;
      });
    } finally {
      if (mounted) setState(() => _looking = false);
    }
  }

  Future<void> _submit() async {
    if (_code.isEmpty) return;
    setState(() => _submitting = true);
    final result =
        await ref.read(scanControllerProvider.notifier).submit(_code);
    if (mounted) await showResultSheet(context, result);
    if (mounted) {
      setState(() {
        _submitting = false;
        _preview = null;
        _error = null;
        _offline = false;
        _ctrl.clear();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final mode = ref.watch(scanControllerProvider).mode;

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
      children: [
        // Sesi ikut dipilih di sini, seperti pada rujukan: Ref ID dipakai saat
        // kartu tak terbaca, dan petugas tetap perlu menentukan masuk/pulang.
        PillTabs<ScanTime>(
          options: const [ScanTime.masuk, ScanTime.pulang],
          selected: mode,
          labelOf: (m) => m == ScanTime.masuk ? 'Masuk' : 'Pulang',
          onChanged: (m) =>
              ref.read(scanControllerProvider.notifier).setMode(m),
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _ctrl,
          autofocus: false,
          textInputAction: TextInputAction.search,
          // Tombol simpan bergantung pada isi field, jadi perlu rebuild.
          onChanged: (_) => setState(() {}),
          onSubmitted: (_) => _lookup(),
          decoration: const InputDecoration(
            labelText: 'Kode Referensi / RFID',
            hintText: 'STU-XXXX atau RF-XXXX',
          ),
        ),
        const SizedBox(height: 10),
        OutlinedButton(
          onPressed: _looking || _code.isEmpty ? null : _lookup,
          child: _looking
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2.2))
              : const Text('Periksa'),
        ),
        const SizedBox(height: 16),
        if (_error != null) InfoStrip(text: _error!),

        // Offline bukan kegagalan — beri tahu apa yang akan terjadi, dan
        // biarkan tombol simpan tetap hidup.
        if (_offline)
          const InfoStrip(
            text: 'Tidak terhubung ke sekolah, jadi identitas tidak bisa '
                'dipratinjau. Absensi tetap bisa dicatat dan akan masuk '
                'antrean untuk disinkronkan nanti.',
          ),

        if (_preview != null) _PreviewCard(preview: _preview!),
        const SizedBox(height: 20),
        FilledButton(
          onPressed: _canSubmit ? _submit : null,
          child: _submitting
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(
                      strokeWidth: 2.4, color: Colors.white))
              : const Text('Catat Absensi'),
        ),
      ],
    );
  }
}

class _PreviewCard extends StatelessWidget {
  const _PreviewCard({required this.preview});
  final LookupResult preview;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final meta = [
      if (preview.identifier != null) preview.identifier,
      if (preview.classroom != null) preview.classroom,
    ].whereType<String>().join(' · ');

    return Panel(
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(preview.name,
                    style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        letterSpacing: -.3)),
                if (meta.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(meta,
                      style: TextStyle(
                          fontSize: 11.5, color: scheme.onSurfaceVariant)),
                ],
              ],
            ),
          ),
          if (!preview.isActive)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
              decoration: BoxDecoration(
                color: scheme.primaryContainer,
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text('NONAKTIF',
                  style: TextStyle(
                    fontSize: 9.5,
                    letterSpacing: .8,
                    fontWeight: FontWeight.w700,
                    color: scheme.onPrimaryContainer,
                  )),
            ),
        ],
      ),
    );
  }
}
