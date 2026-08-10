import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../data/attendance_providers.dart';
import '../models/student_lookup.dart';
import 'scan_controller.dart';
import 'widgets/result_sheet.dart';

/// Input kode Ref ID (unique_code / RFID) manual + pratinjau identitas.
///
/// Pratinjau bersifat **opsional**: ia hanya membantu memastikan kode yang
/// diketik benar. Absensi tetap bisa dicatat tanpa pratinjau — kalau tidak,
/// petugas mustahil mencatat apa pun saat server tak terjangkau, padahal
/// antrean offline justru dibuat untuk keadaan itu.
class ManualInputScreen extends ConsumerStatefulWidget {
  const ManualInputScreen({super.key});

  @override
  ConsumerState<ManualInputScreen> createState() => _ManualInputScreenState();
}

class _ManualInputScreenState extends ConsumerState<ManualInputScreen> {
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
        if (res == null) _error = 'Kode tidak ditemukan.';
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
    final result = await ref.read(scanControllerProvider.notifier).submit(_code);
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
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: Text('Input Ref ID · ${mode.label}')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          TextField(
            controller: _ctrl,
            autofocus: true,
            textInputAction: TextInputAction.search,
            // Tombol simpan bergantung pada isi field, jadi perlu rebuild.
            onChanged: (_) => setState(() {}),
            onSubmitted: (_) => _lookup(),
            decoration: InputDecoration(
              labelText: 'Kode Ref ID / RFID',
              hintText: 'STU-XXXX atau RF-XXXX',
              prefixIcon: const Icon(Icons.badge_outlined),
              suffixIcon: _looking
                  ? const Padding(
                      padding: EdgeInsets.all(12),
                      child: SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2.2)),
                    )
                  : IconButton(
                      icon: const Icon(Icons.search_rounded),
                      onPressed: _lookup,
                    ),
            ),
          ),
          const SizedBox(height: 16),
          if (_error != null)
            _InfoBox(
              color: scheme.error,
              icon: Icons.error_outline_rounded,
              text: _error!,
            ),

          // Offline bukan kegagalan — beri tahu apa yang akan terjadi, dan
          // biarkan tombol simpan tetap hidup.
          if (_offline)
            const _InfoBox(
              color: Color(0xFFD97706),
              icon: Icons.cloud_off_rounded,
              text: 'Tidak terhubung ke server, jadi identitas tidak bisa '
                  'dipratinjau. Absensi tetap bisa dicatat dan akan masuk '
                  'antrean untuk disinkronkan nanti.',
            ),

          if (_preview != null) _PreviewCard(preview: _preview!),
          const SizedBox(height: 20),
          FilledButton.icon(
            onPressed: _canSubmit ? _submit : null,
            icon: _submitting
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                        strokeWidth: 2.4, color: Colors.white))
                : const Icon(Icons.check_rounded),
            label: Text(_submitting ? 'Menyimpan…' : 'Catat Absensi'),
          ),
        ],
      ),
    );
  }
}

class _PreviewCard extends StatelessWidget {
  const _PreviewCard({required this.preview});
  final LookupResult preview;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: scheme.primaryContainer,
          child: Icon(
            preview.isStudent ? Icons.school_rounded : Icons.person_rounded,
            color: scheme.onPrimaryContainer,
          ),
        ),
        title: Text(preview.name),
        subtitle: Text([
          if (preview.identifier != null) preview.identifier,
          if (preview.classroom != null) preview.classroom,
        ].whereType<String>().join(' · ')),
        trailing: preview.isActive
            ? null
            : Chip(
                label: const Text('Nonaktif'),
                backgroundColor: scheme.errorContainer,
              ),
      ),
    );
  }
}

class _InfoBox extends StatelessWidget {
  const _InfoBox({required this.color, required this.icon, required this.text});
  final Color color;
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 20),
          const SizedBox(width: 10),
          Expanded(
              child: Text(text, style: TextStyle(color: color, fontSize: 13))),
        ],
      ),
    );
  }
}
