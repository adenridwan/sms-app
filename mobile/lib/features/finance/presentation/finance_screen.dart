import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/network/backend_status_dot.dart';
import '../../../core/theme/ui_kit.dart';
import '../data/finance_repository.dart';
import '../models/finance.dart';

/// Tab Keuangan.
///
/// Mengikuti rujukan desain (`tabIsFinance`, varian `financeIsAdmin`): kartu
/// gelap **Sisa Tagihan**, lalu daftar **Pembayaran untuk Diverifikasi** dengan
/// tombol verifikasi per baris.
///
/// Varian `financeIsTeacher` pada rujukan — ceklis "sudah bayar" per siswa per
/// item per bulan — **tidak** dibuat: backend tak punya konsep itu. Yang ada
/// adalah tagihan (`student_fees`) dan pembayaran (`payments`); menandai lunas
/// berarti membuat transaksi pembayaran sungguhan, dan mengarang alur uang di
/// klien jauh lebih berbahaya daripada belum menyediakannya.
class FinanceScreen extends ConsumerWidget {
  const FinanceScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final summary = ref.watch(feeSummaryProvider);
    final queue = ref.watch(verificationQueueProvider);
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Keuangan'),
        actions: const [
          Center(child: BackendStatusDot()),
          SizedBox(width: 20),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async => invalidateFinance(ref),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
          children: [
            switch (summary) {
              AsyncData(:final value) => _SummaryCard(data: value),
              AsyncError() => const InfoStrip(
                  margin: EdgeInsets.zero,
                  text: 'Ringkasan tagihan tidak bisa dimuat saat offline.',
                ),
              _ => const Padding(
                  padding: EdgeInsets.symmetric(vertical: 40),
                  child: Center(child: CircularProgressIndicator()),
                ),
            },
            const SizedBox(height: 22),
            const FieldLabel('Pembayaran untuk diverifikasi'),
            const SizedBox(height: 8),
            switch (queue) {
              AsyncData(:final value) when value.isEmpty => Text(
                  'Tidak ada pembayaran yang menunggu verifikasi.',
                  style:
                      TextStyle(fontSize: 12, color: scheme.onSurfaceVariant),
                ),
              AsyncData(:final value) => Column(
                  children: [
                    for (final p in value)
                      _PaymentRow(
                        payment: p,
                        onVerify: () => _verify(context, ref, p),
                      ),
                  ],
                ),
              AsyncError() => Text(
                  'Daftar tidak bisa dimuat saat offline.',
                  style:
                      TextStyle(fontSize: 12, color: scheme.onSurfaceVariant),
                ),
              _ => const Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Center(child: CircularProgressIndicator()),
                ),
            },
          ],
        ),
      ),
    );
  }

  /// Verifikasi punya **dua arah** — setujui atau tolak — karena itulah yang
  /// dituntut endpoint. Selalu lewat konfirmasi: ini menyangkut uang dan tak
  /// ada tombol urung setelahnya di aplikasi.
  Future<void> _verify(
      BuildContext context, WidgetRef ref, PaymentEntry p) async {
    final approved = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Verifikasi pembayaran'),
        content: Text(
          '${p.amount} dari ${p.studentName}'
          '${p.method == null ? '' : ' lewat ${p.method}'}.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Tolak'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Setujui'),
          ),
        ],
      ),
    );
    if (approved == null || !context.mounted) return;

    final messenger = ScaffoldMessenger.of(context);
    try {
      await ref
          .read(financeRepositoryProvider)
          .verify(p.id, approved: approved);
      invalidateFinance(ref);
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(
          content: Text(approved
              ? 'Pembayaran ${p.studentName} disetujui.'
              : 'Pembayaran ${p.studentName} ditolak.'),
        ));
    } on ApiException catch (e) {
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(e.message)));
    }
  }
}

/// Kartu gelap ringkasan — satu-satunya bidang gelap di layar, seperti pada
/// Beranda dan Absensi.
class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.data});

  final FeeSummary data;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final onDark = scheme.surface;
    final muted = onDark.withValues(alpha: .62);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: scheme.onSurface,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'SISA TAGIHAN',
            style: TextStyle(
              fontSize: 11,
              letterSpacing: 1.1,
              fontWeight: FontWeight.w600,
              color: muted,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            data.remaining,
            style: TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w800,
              letterSpacing: -.6,
              color: scheme.primary,
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              _Figure(label: 'Tertagih', value: data.billed, muted: muted),
              const SizedBox(width: 22),
              _Figure(label: 'Terbayar', value: data.paid, muted: muted),
            ],
          ),
          if (data.countOverdue > 0) ...[
            const SizedBox(height: 12),
            Text(
              '${data.countOverdue} tagihan lewat jatuh tempo'
              '${data.countPartial > 0 ? ' · ${data.countPartial} dibayar sebagian' : ''}',
              style: TextStyle(fontSize: 11, color: muted),
            ),
          ],
        ],
      ),
    );
  }
}

class _Figure extends StatelessWidget {
  const _Figure({
    required this.label,
    required this.value,
    required this.muted,
  });

  final String label;
  final String value;
  final Color muted;

  @override
  Widget build(BuildContext context) {
    final onDark = Theme.of(context).colorScheme.surface;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          value,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: onDark,
          ),
        ),
        const SizedBox(height: 2),
        Text(label, style: TextStyle(fontSize: 11, color: muted)),
      ],
    );
  }
}

class _PaymentRow extends StatelessWidget {
  const _PaymentRow({required this.payment, required this.onVerify});

  final PaymentEntry payment;
  final VoidCallback onVerify;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final meta = [
      if (payment.classroom != null) payment.classroom!,
      if (payment.method != null) payment.method!,
      if (payment.paidDate != null) payment.paidDate!,
    ].join(' · ');

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      child: Panel(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    payment.studentName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -.2,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Text(
                  payment.amount,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: scheme.primary,
                  ),
                ),
              ],
            ),
            if (meta.isNotEmpty) ...[
              const SizedBox(height: 3),
              Text(
                meta,
                style:
                    TextStyle(fontSize: 11.5, color: scheme.onSurfaceVariant),
              ),
            ],
            const SizedBox(height: 12),
            if (payment.canVerify)
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  style: FilledButton.styleFrom(
                    minimumSize: const Size(64, 40),
                  ),
                  onPressed: onVerify,
                  child: const Text('Verifikasi Pembayaran'),
                ),
              )
            else
              Text(
                payment.statusLabel ?? 'Terverifikasi',
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w600,
                  color: scheme.onSurfaceVariant,
                ),
              ),
          ],
        ),
      ),
    );
  }
}
