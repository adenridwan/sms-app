import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/database/database_providers.dart';
import '../../../core/location/location_service.dart';
import '../../../core/providers.dart';
import 'attendance_repository.dart';
import 'offline_queue_store.dart';

final attendanceRepositoryProvider = Provider<AttendanceRepository>((ref) {
  return AttendanceRepository(ref.watch(dioProvider));
});

final offlineQueueStoreProvider = Provider<OfflineQueueStore>((ref) {
  return OfflineQueueStore(ref.watch(scanQueueLocalSourceProvider));
});

final locationServiceProvider =
    Provider<LocationService>((ref) => LocationService());
