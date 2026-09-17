import 'package:drift/drift.dart';

import '../../../features/finance/models/finance.dart';
import '../database.dart';

/// Local data source for finance data.
class FinanceLocalSource {
  FinanceLocalSource(this._db);

  final AppDatabase _db;

  /// Staleness threshold for finance cache.
  static const staleDuration = Duration(hours: 1);

  /// Get cached fee summary.
  Future<FeeSummary?> getFeeSummary(String userId) async {
    final row = await _db.getFeeSummary(userId);
    if (row == null) return null;
    return FeeSummary(
      billed: row.billed,
      paid: row.paid,
      remaining: row.remaining,
      countOverdue: row.countOverdue,
      countPartial: row.countPartial,
    );
  }

  /// Check if fee summary is stale.
  Future<bool> isFeeSummaryStale(String userId) async {
    final row = await _db.getFeeSummary(userId);
    if (row == null) return true;
    return DateTime.now().difference(row.cachedAt) > staleDuration;
  }

  /// Save fee summary to cache.
  Future<void> saveFeeSummary(String userId, FeeSummary summary) async {
    await _db.upsertFeeSummary(FeeSummaryCacheCompanion(
      userId: Value(userId),
      billed: Value(summary.billed),
      paid: Value(summary.paid),
      remaining: Value(summary.remaining),
      countOverdue: Value(summary.countOverdue),
      countPartial: Value(summary.countPartial),
      cachedAt: Value(DateTime.now()),
    ));
  }

  /// Get cached payment entries.
  Future<List<PaymentEntry>> getPaymentEntries() async {
    final rows = await _db.getPaymentEntries();
    return rows.map(_rowToPaymentEntry).toList();
  }

  /// Save payment entries to cache.
  Future<void> savePaymentEntries(List<PaymentEntry> entries) async {
    final companions = entries.map((e) => PaymentEntriesCompanion(
          id: Value(e.id),
          studentName: Value(e.studentName),
          amount: Value(e.amount),
          classroom: Value(e.classroom),
          method: Value(e.method),
          invoiceNumber: Value(e.invoiceNumber),
          statusLabel: Value(e.statusLabel),
          paidAt: Value(e.paidAt),
          canVerify: Value(e.canVerify),
          syncedAt: Value(DateTime.now()),
        ));

    await _db.upsertPaymentEntries(companions.toList());
  }

  /// Queue a payment verification action.
  Future<void> queuePaymentVerification(String paymentId) async {
    await _db.addPendingAction(PendingActionsCompanion(
      actionType: const Value('payment_verify'),
      payloadJson: Value('{"payment_id": "$paymentId"}'),
      createdAt: Value(DateTime.now()),
    ));
  }

  /// Clear finance cache for user.
  Future<void> clearCache(String userId) async {
    await _db.clearFinanceCache(userId);
  }

  PaymentEntry _rowToPaymentEntry(PaymentEntryRow row) {
    return PaymentEntry(
      id: row.id,
      studentName: row.studentName,
      amount: row.amount,
      classroom: row.classroom,
      method: row.method,
      invoiceNumber: row.invoiceNumber,
      statusLabel: row.statusLabel,
      paidAt: row.paidAt,
      canVerify: row.canVerify,
    );
  }
}
