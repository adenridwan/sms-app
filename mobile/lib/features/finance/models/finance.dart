/// Ringkasan tagihan seluruh sekolah (`GET /finance/fees/summary`).
///
/// Nilai rupiah dipakai apa adanya dari server (`*_formatted`): pemformatan
/// mata uang punya aturan lokal yang sudah benar di sana, dan menghitung ulang
/// di klien hanya menambah satu tempat lagi yang bisa berbeda.
class FeeSummary {
  const FeeSummary({
    required this.billed,
    required this.paid,
    required this.remaining,
    this.countOverdue = 0,
    this.countPartial = 0,
  });

  final String billed;
  final String paid;
  final String remaining;
  final int countOverdue;
  final int countPartial;

  factory FeeSummary.fromJson(Map<String, dynamic> json) {
    int asInt(Object? v) => v is num ? v.toInt() : 0;
    return FeeSummary(
      billed: (json['total_billed_formatted'] ?? '-').toString(),
      paid: (json['total_paid_formatted'] ?? '-').toString(),
      remaining: (json['total_remaining_formatted'] ?? '-').toString(),
      countOverdue: asInt(json['count_overdue']),
      countPartial: asInt(json['count_partial']),
    );
  }
}

/// Satu pembayaran pada antrean verifikasi.
class PaymentEntry {
  const PaymentEntry({
    required this.id,
    required this.studentName,
    required this.amount,
    this.classroom,
    this.method,
    this.invoiceNumber,
    this.statusLabel,
    this.paidAt,
    this.canVerify = false,
  });

  final String id;
  final String studentName;
  final String amount;
  final String? classroom;
  final String? method;
  final String? invoiceNumber;
  final String? statusLabel;
  final String? paidAt;
  final bool canVerify;

  /// `2026-03-10T17:00:00.000000Z` → `10/03/2026`.
  String? get paidDate {
    final raw = paidAt;
    if (raw == null || raw.isEmpty) return null;
    final d = DateTime.tryParse(raw);
    if (d == null) return raw;
    String two(int n) => n.toString().padLeft(2, '0');
    return '${two(d.day)}/${two(d.month)}/${d.year}';
  }

  factory PaymentEntry.fromJson(Map<String, dynamic> json) {
    final student = json['student'];
    final classroom = student is Map ? student['classroom'] : null;
    final method = json['payment_method'];

    return PaymentEntry(
      id: json['id']?.toString() ?? '',
      studentName: student is Map
          ? (student['name'] ?? '(tanpa nama)').toString()
          : '(tanpa nama)',
      classroom: classroom is Map ? classroom['name']?.toString() : null,
      amount: (json['grand_total_formatted'] ?? json['grand_total'] ?? '-')
          .toString(),
      method: method is Map ? method['name']?.toString() : null,
      invoiceNumber: json['invoice_number']?.toString(),
      statusLabel: json['status_label']?.toString(),
      paidAt: json['paid_at']?.toString(),
      canVerify: json['can_be_verified'] == true,
    );
  }
}
