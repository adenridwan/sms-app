import 'package:drift/drift.dart';

import '../../../features/attendance/models/class_attendance.dart';
import '../database.dart';

/// Local data source for classroom and student data.
class ClassroomLocalSource {
  ClassroomLocalSource(this._db);

  final AppDatabase _db;

  /// Staleness threshold for classroom data.
  static const staleDuration = Duration(hours: 24);

  /// Get all cached classrooms.
  Future<List<ClassOption>> getClassrooms() async {
    final rows = await _db.getAllClassrooms();
    return rows
        .map((r) => ClassOption(id: r.id, name: r.name))
        .toList();
  }

  /// Get classroom by ID.
  Future<ClassOption?> getClassroomById(String id) async {
    final row = await _db.getClassroomById(id);
    if (row == null) return null;
    return ClassOption(id: row.id, name: row.name);
  }

  /// Check if classrooms cache is stale.
  Future<bool> isStale() async {
    return await _db.isEntityStale('classrooms', staleDuration);
  }

  /// Save classrooms to cache.
  Future<void> saveClassrooms(List<ClassOption> classrooms) async {
    final companions = classrooms
        .map((c) => ClassroomsCompanion(
              id: Value(c.id),
              name: Value(c.name),
              syncedAt: Value(DateTime.now()),
            ))
        .toList();

    await _db.upsertClassrooms(companions);

    // Update sync metadata.
    await _db.updateSyncMetadata(SyncMetadataCompanion(
      entityType: const Value('classrooms'),
      lastSyncedAt: Value(DateTime.now()),
    ));
  }

  /// Get students for a classroom.
  Future<List<ClassStudent>> getStudents(String classroomId) async {
    final rows = await _db.getStudentsByClassroom(classroomId);
    return rows
        .map((r) => ClassStudent(id: r.id, name: r.name, nis: r.nis))
        .toList();
  }

  /// Check if students for a classroom are stale.
  Future<bool> areStudentsStale(String classroomId) async {
    return await _db.isEntityStale('students_$classroomId', staleDuration);
  }

  /// Save students for a classroom.
  Future<void> saveStudents(
    String classroomId,
    List<ClassStudent> students,
  ) async {
    // Clear existing students for this classroom first.
    await _db.clearStudentsByClassroom(classroomId);

    final companions = students
        .map((s) => StudentsCompanion(
              id: Value(s.id),
              classroomId: Value(classroomId),
              name: Value(s.name),
              nis: Value(s.nis),
              syncedAt: Value(DateTime.now()),
            ))
        .toList();

    await _db.upsertStudents(companions);

    // Update sync metadata.
    await _db.updateSyncMetadata(SyncMetadataCompanion(
      entityType: Value('students_$classroomId'),
      lastSyncedAt: Value(DateTime.now()),
    ));
  }

  /// Clear all classroom and student data.
  Future<void> clearAll() async {
    await _db.clearStudents();
    await _db.clearClassrooms();
  }
}
