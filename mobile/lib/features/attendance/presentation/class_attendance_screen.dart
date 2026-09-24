import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/backend_status_dot.dart';
import '../../../core/theme/ui_kit.dart';
import '../../home/presentation/widgets/attendance_bar.dart';
import '../models/class_attendance.dart';
import 'class_attendance_controller.dart';

/// Layar penuh ceklis kelas, dipakai lewat rute `/class-attendance`.
class ClassAttendanceScreen extends StatelessWidget {
  const ClassAttendanceScreen({super.key, this.initialClassroomId});

  final String? initialClassroomId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Absen Kelas'),
        actions: const [
          Center(child: BackendStatusDot()),
          SizedBox(width: 16),
        ],
      ),
      body: ClassAttendanceView(initialClassroomId: initialClassroomId),
    );
  }
}

/// Ceklis manual oleh guru, tanpa Scaffold sehingga bisa ditanam di tab
/// Absensi — rujukan desain menukar isi layar di tempat, bukan membuka layar
/// baru.
///
/// Semua siswa mulai berstatus **Hadir**; guru hanya menandai pengecualian.
/// Daftar kelas & siswa dibatasi server sesuai peran — guru hanya melihat
/// kelas yang ia ampu, admin melihat semuanya.
class ClassAttendanceView extends ConsumerStatefulWidget {
  const ClassAttendanceView({super.key, this.initialClassroomId});

  final String? initialClassroomId;

  @override
  ConsumerState<ClassAttendanceView> createState() =>
      _ClassAttendanceViewState();
}

class _ClassAttendanceViewState extends ConsumerState<ClassAttendanceView> {
  final _searchCtrl = TextEditingController();

  /// Kata kunci pencarian, disimpan di `State` layar dan bukan di controller:
  /// ini murni penyaring tampilan, tidak ikut disimpan maupun dikirim, dan
  /// guru mengharapkannya kosong lagi begitu meninggalkan daftar.
  String _query = '';

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  /// Cocokkan nama **atau** NIS. Guru menghafal nama, tapi papan absen dan
  /// kartu siswa memakai NIS — keduanya dipakai untuk mencari orang yang sama.
  List<ClassStudent> _visible(List<ClassStudent> students) {
    final q = _query.trim().toLowerCase();
    if (q.isEmpty) return students;

    return [
      for (final s in students)
        if (s.name.toLowerCase().contains(q) ||
            (s.nis?.toLowerCase().contains(q) ?? false))
          s,
    ];
  }

  @override
  Widget build(BuildContext context) {
    final provider =
        classAttendanceControllerProvider(widget.initialClassroomId);
    final state = ref.watch(provider);
    final controller = ref.read(provider.notifier);
    final scheme = Theme.of(context).colorScheme;

    // Ganti kelas berarti daftar siswanya lain sama sekali; menyisakan kata
    // kunci lama akan menyambut guru dengan daftar kosong yang seolah salah.
    ref.listen(provider.select((s) => s.selectedClassId), (_, __) {
      if (_query.isEmpty) return;
      _searchCtrl.clear();
      setState(() => _query = '');
    });

    final visible = _visible(state.students);

    return Column(
      children: [
        _Toolbar(
          state: state,
          controller: controller,
          searchController: _searchCtrl,
          onSearchChanged: (v) => setState(() => _query = v),
        ),
        Expanded(
          child: _Body(
            state: state,
            controller: controller,
            students: visible,
            searching: _query.trim().isNotEmpty,
          ),
        ),
        if (state.students.isNotEmpty)
          _SaveBar(
            state: state,
            onSave: () async {
              final outcome = await controller.save();
              if (!context.mounted) return;
              ScaffoldMessenger.of(context)
                ..hideCurrentSnackBar()
                ..showSnackBar(SnackBar(
                  content: Text(outcome.label),
                  // Masuk antrean bukan kegagalan — jangan diwarnai merah,
                  // supaya guru tidak merasa pekerjaannya hilang.
                  backgroundColor: outcome.isFailure ? scheme.error : null,
                  duration: outcome.kind == SaveOutcomeKind.queued
                      ? const Duration(seconds: 5)
                      : const Duration(seconds: 3),
                ));
            },
          ),
      ],
    );
  }
}

class _Toolbar extends StatelessWidget {
  const _Toolbar({
    required this.state,
    required this.controller,
    required this.searchController,
    required this.onSearchChanged,
  });

  final ClassAttendanceState state;
  final ClassAttendanceController controller;
  final TextEditingController searchController;
  final ValueChanged<String> onSearchChanged;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final date = state.date ?? DateTime.now();

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: scheme.outlineVariant)),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                flex: 3,
                child: _Field(
                  label: 'Kelas',
                  child: state.loadingClasses
                      ? const Text('Memuat…', style: TextStyle(fontSize: 12.5))
                      : DropdownButtonHideUnderline(
                          child: DropdownButton<String>(
                            value: state.selectedClassId,
                            isExpanded: true,
                            isDense: true,
                            hint: const Text('Pilih kelas',
                                style: TextStyle(fontSize: 12.5)),
                            style: TextStyle(
                                fontSize: 12.5, color: scheme.onSurface),
                            items: [
                              for (final c in state.classes)
                                DropdownMenuItem(
                                  value: c.id,
                                  child: Text(c.name,
                                      overflow: TextOverflow.ellipsis),
                                ),
                            ],
                            onChanged: (v) =>
                                v == null ? null : controller.selectClass(v),
                          ),
                        ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                flex: 2,
                child: InkWell(
                  onTap: () async {
                    final picked = await showDatePicker(
                      context: context,
                      initialDate: date,
                      firstDate: DateTime(date.year - 1),
                      lastDate: DateTime.now(),
                    );
                    if (picked != null) controller.setDate(picked);
                  },
                  child: _Field(
                    label: 'Tanggal',
                    child: Text(
                      '${date.day}/${date.month}/${date.year}',
                      style: const TextStyle(fontSize: 12.5),
                    ),
                  ),
                ),
              ),
            ],
          ),
          if (state.students.isNotEmpty) ...[
            const SizedBox(height: 8),
            TextField(
              controller: searchController,
              onChanged: onSearchChanged,
              textInputAction: TextInputAction.search,
              style: const TextStyle(fontSize: 13),
              decoration: InputDecoration(
                hintText: 'Cari siswa',
                hintStyle:
                    TextStyle(fontSize: 13, color: scheme.onSurfaceVariant),
                prefixIcon: Icon(Icons.search_rounded,
                    size: 18, color: scheme.onSurfaceVariant),
                prefixIconConstraints:
                    const BoxConstraints(minWidth: 34, minHeight: 34),
                // Tombol bersihkan hanya muncul saat ada isinya — kalau selalu
                // tampil, ia terbaca sebagai "tutup pencarian" dan bikin ragu.
                suffixIcon: searchController.text.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close_rounded, size: 16),
                        visualDensity: VisualDensity.compact,
                        onPressed: () {
                          searchController.clear();
                          onSearchChanged('');
                        },
                      ),
                isDense: true,
                filled: true,
                fillColor: scheme.surfaceContainerHighest,
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide.none,
                ),
              ),
            ),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: () => controller.markAll(AttendanceMark.hadir),
                icon: const Icon(Icons.done_all_rounded, size: 16),
                label: const Text('Tandai Semua Hadir',
                    style: TextStyle(fontSize: 11.5)),
                style: TextButton.styleFrom(
                  visualDensity: VisualDensity.compact,
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _Field extends StatelessWidget {
  const _Field({required this.label, required this.child});

  final String label;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.fromLTRB(11, 6, 11, 7),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(9),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label,
              style: TextStyle(fontSize: 9.5, color: scheme.onSurfaceVariant)),
          const SizedBox(height: 1),
          child,
        ],
      ),
    );
  }
}

class _Body extends StatelessWidget {
  const _Body({
    required this.state,
    required this.controller,
    required this.students,
    required this.searching,
  });

  final ClassAttendanceState state;
  final ClassAttendanceController controller;

  /// Siswa yang lolos pencarian. Dipisah dari `state.students` supaya ringkasan
  /// dan penyimpanan tetap memakai **seluruh** kelas — menyaring tampilan tidak
  /// boleh diam-diam mempersempit apa yang tersimpan.
  final List<ClassStudent> students;
  final bool searching;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    if (state.loadingClasses || state.loadingStudents) {
      return const Center(child: CircularProgressIndicator());
    }

    if (state.classes.isEmpty) {
      return Center(
        child: EmptyNote(
          title: 'Belum ada kelas',
          body: state.error ??
              'Tidak ada kelas yang bisa Anda absen. Akun Anda mungkin belum '
                  'terhubung ke kelas mana pun.',
        ),
      );
    }

    if (state.students.isEmpty) {
      return Center(
        child: EmptyNote(
          title: 'Kelas kosong',
          body: state.error ?? 'Kelas ini belum memiliki siswa aktif.',
        ),
      );
    }

    if (students.isEmpty && searching) {
      return const Center(
        child: EmptyNote(
          title: 'Tidak ditemukan',
          body: 'Tidak ada siswa yang cocok dengan kata kunci itu. '
              'Coba potongan nama atau NIS-nya saja.',
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      itemCount: students.length,
      separatorBuilder: (_, __) =>
          Divider(height: 1, color: scheme.outlineVariant),
      itemBuilder: (context, i) {
        final s = students[i];
        final mark = state.marks[s.id] ?? AttendanceMark.hadir;

        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 7),
          child: Row(
            children: [
              CircleAvatar(
                radius: 16,
                backgroundColor: scheme.surfaceContainerHighest,
                child: Text(s.initials,
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                      color: scheme.onSurfaceVariant,
                    )),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(s.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            fontSize: 12.5, fontWeight: FontWeight.w600)),
                    if (s.nis != null)
                      Text(s.nis!,
                          style: TextStyle(
                              fontSize: 10, color: scheme.onSurfaceVariant)),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              _MarkSelector(
                value: mark,
                onChanged: (m) => controller.setMark(s.id, m),
              ),
            ],
          ),
        );
      },
    );
  }
}

/// Empat tombol huruf — sekali ketuk, langsung terlihat. Dropdown per siswa
/// akan berarti 30+ kali dua ketukan plus menunggu animasi.
class _MarkSelector extends StatelessWidget {
  const _MarkSelector({required this.value, required this.onChanged});

  final AttendanceMark value;
  final ValueChanged<AttendanceMark> onChanged;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (final m in AttendanceMark.values)
          Padding(
            padding: const EdgeInsets.only(left: 3),
            child: InkWell(
              onTap: () => onChanged(m),
              borderRadius: BorderRadius.circular(7),
              child: Container(
                width: 27,
                height: 27,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: value == m
                      ? kAttendanceColors[_colorKey(m)]
                      : scheme.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(7),
                ),
                child: Text(
                  m.short,
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: value == m ? Colors.white : scheme.onSurfaceVariant,
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }

  static String _colorKey(AttendanceMark m) => switch (m) {
        AttendanceMark.hadir => 'hadir',
        AttendanceMark.sakit => 'sakit',
        AttendanceMark.izin => 'izin',
        AttendanceMark.alfa => 'alfa',
      };
}

/// Ringkasan menempel di tombol simpan — salah tandai ketahuan sebelum
/// terkirim, bukan sesudah.
class _SaveBar extends StatelessWidget {
  const _SaveBar({required this.state, required this.onSave});

  final ClassAttendanceState state;
  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final tally = state.tally;
    final parts = [
      for (final m in AttendanceMark.values)
        if ((tally[m] ?? 0) > 0) '${tally[m]} ${m.label.toLowerCase()}',
    ];

    return Container(
      padding: EdgeInsets.fromLTRB(
        16,
        10,
        16,
        10 + MediaQuery.of(context).padding.bottom,
      ),
      decoration: BoxDecoration(
        color: scheme.surface,
        border: Border(top: BorderSide(color: scheme.outlineVariant)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(parts.join(' · '),
                    style: const TextStyle(
                        fontSize: 11.5, fontWeight: FontWeight.w600)),
                Text('${state.students.length} siswa',
                    style: TextStyle(
                        fontSize: 10, color: scheme.onSurfaceVariant)),
              ],
            ),
          ),
          const SizedBox(width: 12),
          FilledButton(
            onPressed: state.canSave ? onSave : null,
            child: state.saving
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                        strokeWidth: 2.2, color: Colors.white),
                  )
                : const Text('Simpan'),
          ),
        ],
      ),
    );
  }
}
