// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'database.dart';

// ignore_for_file: type=lint
class $CachedUsersTable extends CachedUsers
    with TableInfo<$CachedUsersTable, CachedUser> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedUsersTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
      'id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _emailMeta = const VerificationMeta('email');
  @override
  late final GeneratedColumn<String> email = GeneratedColumn<String>(
      'email', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _fullNameMeta =
      const VerificationMeta('fullName');
  @override
  late final GeneratedColumn<String> fullName = GeneratedColumn<String>(
      'full_name', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _usernameMeta =
      const VerificationMeta('username');
  @override
  late final GeneratedColumn<String> username = GeneratedColumn<String>(
      'username', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _userTypeMeta =
      const VerificationMeta('userType');
  @override
  late final GeneratedColumn<String> userType = GeneratedColumn<String>(
      'user_type', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _avatarUrlMeta =
      const VerificationMeta('avatarUrl');
  @override
  late final GeneratedColumn<String> avatarUrl = GeneratedColumn<String>(
      'avatar_url', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _tenantIdMeta =
      const VerificationMeta('tenantId');
  @override
  late final GeneratedColumn<String> tenantId = GeneratedColumn<String>(
      'tenant_id', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _rolesJsonMeta =
      const VerificationMeta('rolesJson');
  @override
  late final GeneratedColumn<String> rolesJson = GeneratedColumn<String>(
      'roles_json', aliasedName, false,
      type: DriftSqlType.string,
      requiredDuringInsert: false,
      defaultValue: const Constant('[]'));
  static const VerificationMeta _permissionsJsonMeta =
      const VerificationMeta('permissionsJson');
  @override
  late final GeneratedColumn<String> permissionsJson = GeneratedColumn<String>(
      'permissions_json', aliasedName, false,
      type: DriftSqlType.string,
      requiredDuringInsert: false,
      defaultValue: const Constant('[]'));
  static const VerificationMeta _syncedAtMeta =
      const VerificationMeta('syncedAt');
  @override
  late final GeneratedColumn<DateTime> syncedAt = GeneratedColumn<DateTime>(
      'synced_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  List<GeneratedColumn> get $columns => [
        id,
        email,
        fullName,
        username,
        userType,
        avatarUrl,
        tenantId,
        rolesJson,
        permissionsJson,
        syncedAt
      ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_users';
  @override
  VerificationContext validateIntegrity(Insertable<CachedUser> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('email')) {
      context.handle(
          _emailMeta, email.isAcceptableOrUnknown(data['email']!, _emailMeta));
    } else if (isInserting) {
      context.missing(_emailMeta);
    }
    if (data.containsKey('full_name')) {
      context.handle(_fullNameMeta,
          fullName.isAcceptableOrUnknown(data['full_name']!, _fullNameMeta));
    } else if (isInserting) {
      context.missing(_fullNameMeta);
    }
    if (data.containsKey('username')) {
      context.handle(_usernameMeta,
          username.isAcceptableOrUnknown(data['username']!, _usernameMeta));
    }
    if (data.containsKey('user_type')) {
      context.handle(_userTypeMeta,
          userType.isAcceptableOrUnknown(data['user_type']!, _userTypeMeta));
    }
    if (data.containsKey('avatar_url')) {
      context.handle(_avatarUrlMeta,
          avatarUrl.isAcceptableOrUnknown(data['avatar_url']!, _avatarUrlMeta));
    }
    if (data.containsKey('tenant_id')) {
      context.handle(_tenantIdMeta,
          tenantId.isAcceptableOrUnknown(data['tenant_id']!, _tenantIdMeta));
    }
    if (data.containsKey('roles_json')) {
      context.handle(_rolesJsonMeta,
          rolesJson.isAcceptableOrUnknown(data['roles_json']!, _rolesJsonMeta));
    }
    if (data.containsKey('permissions_json')) {
      context.handle(
          _permissionsJsonMeta,
          permissionsJson.isAcceptableOrUnknown(
              data['permissions_json']!, _permissionsJsonMeta));
    }
    if (data.containsKey('synced_at')) {
      context.handle(_syncedAtMeta,
          syncedAt.isAcceptableOrUnknown(data['synced_at']!, _syncedAtMeta));
    } else if (isInserting) {
      context.missing(_syncedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  CachedUser map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedUser(
      id: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}id'])!,
      email: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}email'])!,
      fullName: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}full_name'])!,
      username: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}username']),
      userType: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}user_type']),
      avatarUrl: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}avatar_url']),
      tenantId: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}tenant_id']),
      rolesJson: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}roles_json'])!,
      permissionsJson: attachedDatabase.typeMapping.read(
          DriftSqlType.string, data['${effectivePrefix}permissions_json'])!,
      syncedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}synced_at'])!,
    );
  }

  @override
  $CachedUsersTable createAlias(String alias) {
    return $CachedUsersTable(attachedDatabase, alias);
  }
}

class CachedUser extends DataClass implements Insertable<CachedUser> {
  final String id;
  final String email;
  final String fullName;
  final String? username;
  final String? userType;
  final String? avatarUrl;
  final String? tenantId;
  final String rolesJson;
  final String permissionsJson;
  final DateTime syncedAt;
  const CachedUser(
      {required this.id,
      required this.email,
      required this.fullName,
      this.username,
      this.userType,
      this.avatarUrl,
      this.tenantId,
      required this.rolesJson,
      required this.permissionsJson,
      required this.syncedAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['email'] = Variable<String>(email);
    map['full_name'] = Variable<String>(fullName);
    if (!nullToAbsent || username != null) {
      map['username'] = Variable<String>(username);
    }
    if (!nullToAbsent || userType != null) {
      map['user_type'] = Variable<String>(userType);
    }
    if (!nullToAbsent || avatarUrl != null) {
      map['avatar_url'] = Variable<String>(avatarUrl);
    }
    if (!nullToAbsent || tenantId != null) {
      map['tenant_id'] = Variable<String>(tenantId);
    }
    map['roles_json'] = Variable<String>(rolesJson);
    map['permissions_json'] = Variable<String>(permissionsJson);
    map['synced_at'] = Variable<DateTime>(syncedAt);
    return map;
  }

  CachedUsersCompanion toCompanion(bool nullToAbsent) {
    return CachedUsersCompanion(
      id: Value(id),
      email: Value(email),
      fullName: Value(fullName),
      username: username == null && nullToAbsent
          ? const Value.absent()
          : Value(username),
      userType: userType == null && nullToAbsent
          ? const Value.absent()
          : Value(userType),
      avatarUrl: avatarUrl == null && nullToAbsent
          ? const Value.absent()
          : Value(avatarUrl),
      tenantId: tenantId == null && nullToAbsent
          ? const Value.absent()
          : Value(tenantId),
      rolesJson: Value(rolesJson),
      permissionsJson: Value(permissionsJson),
      syncedAt: Value(syncedAt),
    );
  }

  factory CachedUser.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedUser(
      id: serializer.fromJson<String>(json['id']),
      email: serializer.fromJson<String>(json['email']),
      fullName: serializer.fromJson<String>(json['fullName']),
      username: serializer.fromJson<String?>(json['username']),
      userType: serializer.fromJson<String?>(json['userType']),
      avatarUrl: serializer.fromJson<String?>(json['avatarUrl']),
      tenantId: serializer.fromJson<String?>(json['tenantId']),
      rolesJson: serializer.fromJson<String>(json['rolesJson']),
      permissionsJson: serializer.fromJson<String>(json['permissionsJson']),
      syncedAt: serializer.fromJson<DateTime>(json['syncedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'email': serializer.toJson<String>(email),
      'fullName': serializer.toJson<String>(fullName),
      'username': serializer.toJson<String?>(username),
      'userType': serializer.toJson<String?>(userType),
      'avatarUrl': serializer.toJson<String?>(avatarUrl),
      'tenantId': serializer.toJson<String?>(tenantId),
      'rolesJson': serializer.toJson<String>(rolesJson),
      'permissionsJson': serializer.toJson<String>(permissionsJson),
      'syncedAt': serializer.toJson<DateTime>(syncedAt),
    };
  }

  CachedUser copyWith(
          {String? id,
          String? email,
          String? fullName,
          Value<String?> username = const Value.absent(),
          Value<String?> userType = const Value.absent(),
          Value<String?> avatarUrl = const Value.absent(),
          Value<String?> tenantId = const Value.absent(),
          String? rolesJson,
          String? permissionsJson,
          DateTime? syncedAt}) =>
      CachedUser(
        id: id ?? this.id,
        email: email ?? this.email,
        fullName: fullName ?? this.fullName,
        username: username.present ? username.value : this.username,
        userType: userType.present ? userType.value : this.userType,
        avatarUrl: avatarUrl.present ? avatarUrl.value : this.avatarUrl,
        tenantId: tenantId.present ? tenantId.value : this.tenantId,
        rolesJson: rolesJson ?? this.rolesJson,
        permissionsJson: permissionsJson ?? this.permissionsJson,
        syncedAt: syncedAt ?? this.syncedAt,
      );
  CachedUser copyWithCompanion(CachedUsersCompanion data) {
    return CachedUser(
      id: data.id.present ? data.id.value : this.id,
      email: data.email.present ? data.email.value : this.email,
      fullName: data.fullName.present ? data.fullName.value : this.fullName,
      username: data.username.present ? data.username.value : this.username,
      userType: data.userType.present ? data.userType.value : this.userType,
      avatarUrl: data.avatarUrl.present ? data.avatarUrl.value : this.avatarUrl,
      tenantId: data.tenantId.present ? data.tenantId.value : this.tenantId,
      rolesJson: data.rolesJson.present ? data.rolesJson.value : this.rolesJson,
      permissionsJson: data.permissionsJson.present
          ? data.permissionsJson.value
          : this.permissionsJson,
      syncedAt: data.syncedAt.present ? data.syncedAt.value : this.syncedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedUser(')
          ..write('id: $id, ')
          ..write('email: $email, ')
          ..write('fullName: $fullName, ')
          ..write('username: $username, ')
          ..write('userType: $userType, ')
          ..write('avatarUrl: $avatarUrl, ')
          ..write('tenantId: $tenantId, ')
          ..write('rolesJson: $rolesJson, ')
          ..write('permissionsJson: $permissionsJson, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, email, fullName, username, userType,
      avatarUrl, tenantId, rolesJson, permissionsJson, syncedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedUser &&
          other.id == this.id &&
          other.email == this.email &&
          other.fullName == this.fullName &&
          other.username == this.username &&
          other.userType == this.userType &&
          other.avatarUrl == this.avatarUrl &&
          other.tenantId == this.tenantId &&
          other.rolesJson == this.rolesJson &&
          other.permissionsJson == this.permissionsJson &&
          other.syncedAt == this.syncedAt);
}

class CachedUsersCompanion extends UpdateCompanion<CachedUser> {
  final Value<String> id;
  final Value<String> email;
  final Value<String> fullName;
  final Value<String?> username;
  final Value<String?> userType;
  final Value<String?> avatarUrl;
  final Value<String?> tenantId;
  final Value<String> rolesJson;
  final Value<String> permissionsJson;
  final Value<DateTime> syncedAt;
  final Value<int> rowid;
  const CachedUsersCompanion({
    this.id = const Value.absent(),
    this.email = const Value.absent(),
    this.fullName = const Value.absent(),
    this.username = const Value.absent(),
    this.userType = const Value.absent(),
    this.avatarUrl = const Value.absent(),
    this.tenantId = const Value.absent(),
    this.rolesJson = const Value.absent(),
    this.permissionsJson = const Value.absent(),
    this.syncedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CachedUsersCompanion.insert({
    required String id,
    required String email,
    required String fullName,
    this.username = const Value.absent(),
    this.userType = const Value.absent(),
    this.avatarUrl = const Value.absent(),
    this.tenantId = const Value.absent(),
    this.rolesJson = const Value.absent(),
    this.permissionsJson = const Value.absent(),
    required DateTime syncedAt,
    this.rowid = const Value.absent(),
  })  : id = Value(id),
        email = Value(email),
        fullName = Value(fullName),
        syncedAt = Value(syncedAt);
  static Insertable<CachedUser> custom({
    Expression<String>? id,
    Expression<String>? email,
    Expression<String>? fullName,
    Expression<String>? username,
    Expression<String>? userType,
    Expression<String>? avatarUrl,
    Expression<String>? tenantId,
    Expression<String>? rolesJson,
    Expression<String>? permissionsJson,
    Expression<DateTime>? syncedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (email != null) 'email': email,
      if (fullName != null) 'full_name': fullName,
      if (username != null) 'username': username,
      if (userType != null) 'user_type': userType,
      if (avatarUrl != null) 'avatar_url': avatarUrl,
      if (tenantId != null) 'tenant_id': tenantId,
      if (rolesJson != null) 'roles_json': rolesJson,
      if (permissionsJson != null) 'permissions_json': permissionsJson,
      if (syncedAt != null) 'synced_at': syncedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CachedUsersCompanion copyWith(
      {Value<String>? id,
      Value<String>? email,
      Value<String>? fullName,
      Value<String?>? username,
      Value<String?>? userType,
      Value<String?>? avatarUrl,
      Value<String?>? tenantId,
      Value<String>? rolesJson,
      Value<String>? permissionsJson,
      Value<DateTime>? syncedAt,
      Value<int>? rowid}) {
    return CachedUsersCompanion(
      id: id ?? this.id,
      email: email ?? this.email,
      fullName: fullName ?? this.fullName,
      username: username ?? this.username,
      userType: userType ?? this.userType,
      avatarUrl: avatarUrl ?? this.avatarUrl,
      tenantId: tenantId ?? this.tenantId,
      rolesJson: rolesJson ?? this.rolesJson,
      permissionsJson: permissionsJson ?? this.permissionsJson,
      syncedAt: syncedAt ?? this.syncedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (email.present) {
      map['email'] = Variable<String>(email.value);
    }
    if (fullName.present) {
      map['full_name'] = Variable<String>(fullName.value);
    }
    if (username.present) {
      map['username'] = Variable<String>(username.value);
    }
    if (userType.present) {
      map['user_type'] = Variable<String>(userType.value);
    }
    if (avatarUrl.present) {
      map['avatar_url'] = Variable<String>(avatarUrl.value);
    }
    if (tenantId.present) {
      map['tenant_id'] = Variable<String>(tenantId.value);
    }
    if (rolesJson.present) {
      map['roles_json'] = Variable<String>(rolesJson.value);
    }
    if (permissionsJson.present) {
      map['permissions_json'] = Variable<String>(permissionsJson.value);
    }
    if (syncedAt.present) {
      map['synced_at'] = Variable<DateTime>(syncedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedUsersCompanion(')
          ..write('id: $id, ')
          ..write('email: $email, ')
          ..write('fullName: $fullName, ')
          ..write('username: $username, ')
          ..write('userType: $userType, ')
          ..write('avatarUrl: $avatarUrl, ')
          ..write('tenantId: $tenantId, ')
          ..write('rolesJson: $rolesJson, ')
          ..write('permissionsJson: $permissionsJson, ')
          ..write('syncedAt: $syncedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $ClassroomsTable extends Classrooms
    with TableInfo<$ClassroomsTable, Classroom> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $ClassroomsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
      'id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _nameMeta = const VerificationMeta('name');
  @override
  late final GeneratedColumn<String> name = GeneratedColumn<String>(
      'name', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _academicYearMeta =
      const VerificationMeta('academicYear');
  @override
  late final GeneratedColumn<String> academicYear = GeneratedColumn<String>(
      'academic_year', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _isActiveMeta =
      const VerificationMeta('isActive');
  @override
  late final GeneratedColumn<bool> isActive = GeneratedColumn<bool>(
      'is_active', aliasedName, false,
      type: DriftSqlType.bool,
      requiredDuringInsert: false,
      defaultConstraints:
          GeneratedColumn.constraintIsAlways('CHECK ("is_active" IN (0, 1))'),
      defaultValue: const Constant(true));
  static const VerificationMeta _studentsCountMeta =
      const VerificationMeta('studentsCount');
  @override
  late final GeneratedColumn<int> studentsCount = GeneratedColumn<int>(
      'students_count', aliasedName, false,
      type: DriftSqlType.int,
      requiredDuringInsert: false,
      defaultValue: const Constant(0));
  static const VerificationMeta _syncedAtMeta =
      const VerificationMeta('syncedAt');
  @override
  late final GeneratedColumn<DateTime> syncedAt = GeneratedColumn<DateTime>(
      'synced_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  List<GeneratedColumn> get $columns =>
      [id, name, academicYear, isActive, studentsCount, syncedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'classrooms';
  @override
  VerificationContext validateIntegrity(Insertable<Classroom> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('name')) {
      context.handle(
          _nameMeta, name.isAcceptableOrUnknown(data['name']!, _nameMeta));
    } else if (isInserting) {
      context.missing(_nameMeta);
    }
    if (data.containsKey('academic_year')) {
      context.handle(
          _academicYearMeta,
          academicYear.isAcceptableOrUnknown(
              data['academic_year']!, _academicYearMeta));
    }
    if (data.containsKey('is_active')) {
      context.handle(_isActiveMeta,
          isActive.isAcceptableOrUnknown(data['is_active']!, _isActiveMeta));
    }
    if (data.containsKey('students_count')) {
      context.handle(
          _studentsCountMeta,
          studentsCount.isAcceptableOrUnknown(
              data['students_count']!, _studentsCountMeta));
    }
    if (data.containsKey('synced_at')) {
      context.handle(_syncedAtMeta,
          syncedAt.isAcceptableOrUnknown(data['synced_at']!, _syncedAtMeta));
    } else if (isInserting) {
      context.missing(_syncedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Classroom map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Classroom(
      id: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}id'])!,
      name: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}name'])!,
      academicYear: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}academic_year']),
      isActive: attachedDatabase.typeMapping
          .read(DriftSqlType.bool, data['${effectivePrefix}is_active'])!,
      studentsCount: attachedDatabase.typeMapping
          .read(DriftSqlType.int, data['${effectivePrefix}students_count'])!,
      syncedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}synced_at'])!,
    );
  }

  @override
  $ClassroomsTable createAlias(String alias) {
    return $ClassroomsTable(attachedDatabase, alias);
  }
}

class Classroom extends DataClass implements Insertable<Classroom> {
  final String id;
  final String name;
  final String? academicYear;
  final bool isActive;
  final int studentsCount;
  final DateTime syncedAt;
  const Classroom(
      {required this.id,
      required this.name,
      this.academicYear,
      required this.isActive,
      required this.studentsCount,
      required this.syncedAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['name'] = Variable<String>(name);
    if (!nullToAbsent || academicYear != null) {
      map['academic_year'] = Variable<String>(academicYear);
    }
    map['is_active'] = Variable<bool>(isActive);
    map['students_count'] = Variable<int>(studentsCount);
    map['synced_at'] = Variable<DateTime>(syncedAt);
    return map;
  }

  ClassroomsCompanion toCompanion(bool nullToAbsent) {
    return ClassroomsCompanion(
      id: Value(id),
      name: Value(name),
      academicYear: academicYear == null && nullToAbsent
          ? const Value.absent()
          : Value(academicYear),
      isActive: Value(isActive),
      studentsCount: Value(studentsCount),
      syncedAt: Value(syncedAt),
    );
  }

  factory Classroom.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Classroom(
      id: serializer.fromJson<String>(json['id']),
      name: serializer.fromJson<String>(json['name']),
      academicYear: serializer.fromJson<String?>(json['academicYear']),
      isActive: serializer.fromJson<bool>(json['isActive']),
      studentsCount: serializer.fromJson<int>(json['studentsCount']),
      syncedAt: serializer.fromJson<DateTime>(json['syncedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'name': serializer.toJson<String>(name),
      'academicYear': serializer.toJson<String?>(academicYear),
      'isActive': serializer.toJson<bool>(isActive),
      'studentsCount': serializer.toJson<int>(studentsCount),
      'syncedAt': serializer.toJson<DateTime>(syncedAt),
    };
  }

  Classroom copyWith(
          {String? id,
          String? name,
          Value<String?> academicYear = const Value.absent(),
          bool? isActive,
          int? studentsCount,
          DateTime? syncedAt}) =>
      Classroom(
        id: id ?? this.id,
        name: name ?? this.name,
        academicYear:
            academicYear.present ? academicYear.value : this.academicYear,
        isActive: isActive ?? this.isActive,
        studentsCount: studentsCount ?? this.studentsCount,
        syncedAt: syncedAt ?? this.syncedAt,
      );
  Classroom copyWithCompanion(ClassroomsCompanion data) {
    return Classroom(
      id: data.id.present ? data.id.value : this.id,
      name: data.name.present ? data.name.value : this.name,
      academicYear: data.academicYear.present
          ? data.academicYear.value
          : this.academicYear,
      isActive: data.isActive.present ? data.isActive.value : this.isActive,
      studentsCount: data.studentsCount.present
          ? data.studentsCount.value
          : this.studentsCount,
      syncedAt: data.syncedAt.present ? data.syncedAt.value : this.syncedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Classroom(')
          ..write('id: $id, ')
          ..write('name: $name, ')
          ..write('academicYear: $academicYear, ')
          ..write('isActive: $isActive, ')
          ..write('studentsCount: $studentsCount, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode =>
      Object.hash(id, name, academicYear, isActive, studentsCount, syncedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Classroom &&
          other.id == this.id &&
          other.name == this.name &&
          other.academicYear == this.academicYear &&
          other.isActive == this.isActive &&
          other.studentsCount == this.studentsCount &&
          other.syncedAt == this.syncedAt);
}

class ClassroomsCompanion extends UpdateCompanion<Classroom> {
  final Value<String> id;
  final Value<String> name;
  final Value<String?> academicYear;
  final Value<bool> isActive;
  final Value<int> studentsCount;
  final Value<DateTime> syncedAt;
  final Value<int> rowid;
  const ClassroomsCompanion({
    this.id = const Value.absent(),
    this.name = const Value.absent(),
    this.academicYear = const Value.absent(),
    this.isActive = const Value.absent(),
    this.studentsCount = const Value.absent(),
    this.syncedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  ClassroomsCompanion.insert({
    required String id,
    required String name,
    this.academicYear = const Value.absent(),
    this.isActive = const Value.absent(),
    this.studentsCount = const Value.absent(),
    required DateTime syncedAt,
    this.rowid = const Value.absent(),
  })  : id = Value(id),
        name = Value(name),
        syncedAt = Value(syncedAt);
  static Insertable<Classroom> custom({
    Expression<String>? id,
    Expression<String>? name,
    Expression<String>? academicYear,
    Expression<bool>? isActive,
    Expression<int>? studentsCount,
    Expression<DateTime>? syncedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (name != null) 'name': name,
      if (academicYear != null) 'academic_year': academicYear,
      if (isActive != null) 'is_active': isActive,
      if (studentsCount != null) 'students_count': studentsCount,
      if (syncedAt != null) 'synced_at': syncedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  ClassroomsCompanion copyWith(
      {Value<String>? id,
      Value<String>? name,
      Value<String?>? academicYear,
      Value<bool>? isActive,
      Value<int>? studentsCount,
      Value<DateTime>? syncedAt,
      Value<int>? rowid}) {
    return ClassroomsCompanion(
      id: id ?? this.id,
      name: name ?? this.name,
      academicYear: academicYear ?? this.academicYear,
      isActive: isActive ?? this.isActive,
      studentsCount: studentsCount ?? this.studentsCount,
      syncedAt: syncedAt ?? this.syncedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (name.present) {
      map['name'] = Variable<String>(name.value);
    }
    if (academicYear.present) {
      map['academic_year'] = Variable<String>(academicYear.value);
    }
    if (isActive.present) {
      map['is_active'] = Variable<bool>(isActive.value);
    }
    if (studentsCount.present) {
      map['students_count'] = Variable<int>(studentsCount.value);
    }
    if (syncedAt.present) {
      map['synced_at'] = Variable<DateTime>(syncedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('ClassroomsCompanion(')
          ..write('id: $id, ')
          ..write('name: $name, ')
          ..write('academicYear: $academicYear, ')
          ..write('isActive: $isActive, ')
          ..write('studentsCount: $studentsCount, ')
          ..write('syncedAt: $syncedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $StudentsTable extends Students with TableInfo<$StudentsTable, Student> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $StudentsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
      'id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _classroomIdMeta =
      const VerificationMeta('classroomId');
  @override
  late final GeneratedColumn<String> classroomId = GeneratedColumn<String>(
      'classroom_id', aliasedName, false,
      type: DriftSqlType.string,
      requiredDuringInsert: true,
      defaultConstraints:
          GeneratedColumn.constraintIsAlways('REFERENCES classrooms (id)'));
  static const VerificationMeta _nisMeta = const VerificationMeta('nis');
  @override
  late final GeneratedColumn<String> nis = GeneratedColumn<String>(
      'nis', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _nameMeta = const VerificationMeta('name');
  @override
  late final GeneratedColumn<String> name = GeneratedColumn<String>(
      'name', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _syncedAtMeta =
      const VerificationMeta('syncedAt');
  @override
  late final GeneratedColumn<DateTime> syncedAt = GeneratedColumn<DateTime>(
      'synced_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  List<GeneratedColumn> get $columns => [id, classroomId, nis, name, syncedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'students';
  @override
  VerificationContext validateIntegrity(Insertable<Student> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('classroom_id')) {
      context.handle(
          _classroomIdMeta,
          classroomId.isAcceptableOrUnknown(
              data['classroom_id']!, _classroomIdMeta));
    } else if (isInserting) {
      context.missing(_classroomIdMeta);
    }
    if (data.containsKey('nis')) {
      context.handle(
          _nisMeta, nis.isAcceptableOrUnknown(data['nis']!, _nisMeta));
    }
    if (data.containsKey('name')) {
      context.handle(
          _nameMeta, name.isAcceptableOrUnknown(data['name']!, _nameMeta));
    } else if (isInserting) {
      context.missing(_nameMeta);
    }
    if (data.containsKey('synced_at')) {
      context.handle(_syncedAtMeta,
          syncedAt.isAcceptableOrUnknown(data['synced_at']!, _syncedAtMeta));
    } else if (isInserting) {
      context.missing(_syncedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  Student map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return Student(
      id: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}id'])!,
      classroomId: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}classroom_id'])!,
      nis: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}nis']),
      name: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}name'])!,
      syncedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}synced_at'])!,
    );
  }

  @override
  $StudentsTable createAlias(String alias) {
    return $StudentsTable(attachedDatabase, alias);
  }
}

class Student extends DataClass implements Insertable<Student> {
  final String id;
  final String classroomId;
  final String? nis;
  final String name;
  final DateTime syncedAt;
  const Student(
      {required this.id,
      required this.classroomId,
      this.nis,
      required this.name,
      required this.syncedAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['classroom_id'] = Variable<String>(classroomId);
    if (!nullToAbsent || nis != null) {
      map['nis'] = Variable<String>(nis);
    }
    map['name'] = Variable<String>(name);
    map['synced_at'] = Variable<DateTime>(syncedAt);
    return map;
  }

  StudentsCompanion toCompanion(bool nullToAbsent) {
    return StudentsCompanion(
      id: Value(id),
      classroomId: Value(classroomId),
      nis: nis == null && nullToAbsent ? const Value.absent() : Value(nis),
      name: Value(name),
      syncedAt: Value(syncedAt),
    );
  }

  factory Student.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return Student(
      id: serializer.fromJson<String>(json['id']),
      classroomId: serializer.fromJson<String>(json['classroomId']),
      nis: serializer.fromJson<String?>(json['nis']),
      name: serializer.fromJson<String>(json['name']),
      syncedAt: serializer.fromJson<DateTime>(json['syncedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'classroomId': serializer.toJson<String>(classroomId),
      'nis': serializer.toJson<String?>(nis),
      'name': serializer.toJson<String>(name),
      'syncedAt': serializer.toJson<DateTime>(syncedAt),
    };
  }

  Student copyWith(
          {String? id,
          String? classroomId,
          Value<String?> nis = const Value.absent(),
          String? name,
          DateTime? syncedAt}) =>
      Student(
        id: id ?? this.id,
        classroomId: classroomId ?? this.classroomId,
        nis: nis.present ? nis.value : this.nis,
        name: name ?? this.name,
        syncedAt: syncedAt ?? this.syncedAt,
      );
  Student copyWithCompanion(StudentsCompanion data) {
    return Student(
      id: data.id.present ? data.id.value : this.id,
      classroomId:
          data.classroomId.present ? data.classroomId.value : this.classroomId,
      nis: data.nis.present ? data.nis.value : this.nis,
      name: data.name.present ? data.name.value : this.name,
      syncedAt: data.syncedAt.present ? data.syncedAt.value : this.syncedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('Student(')
          ..write('id: $id, ')
          ..write('classroomId: $classroomId, ')
          ..write('nis: $nis, ')
          ..write('name: $name, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, classroomId, nis, name, syncedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is Student &&
          other.id == this.id &&
          other.classroomId == this.classroomId &&
          other.nis == this.nis &&
          other.name == this.name &&
          other.syncedAt == this.syncedAt);
}

class StudentsCompanion extends UpdateCompanion<Student> {
  final Value<String> id;
  final Value<String> classroomId;
  final Value<String?> nis;
  final Value<String> name;
  final Value<DateTime> syncedAt;
  final Value<int> rowid;
  const StudentsCompanion({
    this.id = const Value.absent(),
    this.classroomId = const Value.absent(),
    this.nis = const Value.absent(),
    this.name = const Value.absent(),
    this.syncedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  StudentsCompanion.insert({
    required String id,
    required String classroomId,
    this.nis = const Value.absent(),
    required String name,
    required DateTime syncedAt,
    this.rowid = const Value.absent(),
  })  : id = Value(id),
        classroomId = Value(classroomId),
        name = Value(name),
        syncedAt = Value(syncedAt);
  static Insertable<Student> custom({
    Expression<String>? id,
    Expression<String>? classroomId,
    Expression<String>? nis,
    Expression<String>? name,
    Expression<DateTime>? syncedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (classroomId != null) 'classroom_id': classroomId,
      if (nis != null) 'nis': nis,
      if (name != null) 'name': name,
      if (syncedAt != null) 'synced_at': syncedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  StudentsCompanion copyWith(
      {Value<String>? id,
      Value<String>? classroomId,
      Value<String?>? nis,
      Value<String>? name,
      Value<DateTime>? syncedAt,
      Value<int>? rowid}) {
    return StudentsCompanion(
      id: id ?? this.id,
      classroomId: classroomId ?? this.classroomId,
      nis: nis ?? this.nis,
      name: name ?? this.name,
      syncedAt: syncedAt ?? this.syncedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (classroomId.present) {
      map['classroom_id'] = Variable<String>(classroomId.value);
    }
    if (nis.present) {
      map['nis'] = Variable<String>(nis.value);
    }
    if (name.present) {
      map['name'] = Variable<String>(name.value);
    }
    if (syncedAt.present) {
      map['synced_at'] = Variable<DateTime>(syncedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('StudentsCompanion(')
          ..write('id: $id, ')
          ..write('classroomId: $classroomId, ')
          ..write('nis: $nis, ')
          ..write('name: $name, ')
          ..write('syncedAt: $syncedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $DashboardCacheTable extends DashboardCache
    with TableInfo<$DashboardCacheTable, DashboardCacheData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $DashboardCacheTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _userIdMeta = const VerificationMeta('userId');
  @override
  late final GeneratedColumn<String> userId = GeneratedColumn<String>(
      'user_id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _packageMeta =
      const VerificationMeta('package');
  @override
  late final GeneratedColumn<String> package = GeneratedColumn<String>(
      'package', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _jsonDataMeta =
      const VerificationMeta('jsonData');
  @override
  late final GeneratedColumn<String> jsonData = GeneratedColumn<String>(
      'json_data', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _cachedAtMeta =
      const VerificationMeta('cachedAt');
  @override
  late final GeneratedColumn<DateTime> cachedAt = GeneratedColumn<DateTime>(
      'cached_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  List<GeneratedColumn> get $columns => [userId, package, jsonData, cachedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'dashboard_cache';
  @override
  VerificationContext validateIntegrity(Insertable<DashboardCacheData> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('user_id')) {
      context.handle(_userIdMeta,
          userId.isAcceptableOrUnknown(data['user_id']!, _userIdMeta));
    } else if (isInserting) {
      context.missing(_userIdMeta);
    }
    if (data.containsKey('package')) {
      context.handle(_packageMeta,
          package.isAcceptableOrUnknown(data['package']!, _packageMeta));
    } else if (isInserting) {
      context.missing(_packageMeta);
    }
    if (data.containsKey('json_data')) {
      context.handle(_jsonDataMeta,
          jsonData.isAcceptableOrUnknown(data['json_data']!, _jsonDataMeta));
    } else if (isInserting) {
      context.missing(_jsonDataMeta);
    }
    if (data.containsKey('cached_at')) {
      context.handle(_cachedAtMeta,
          cachedAt.isAcceptableOrUnknown(data['cached_at']!, _cachedAtMeta));
    } else if (isInserting) {
      context.missing(_cachedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {userId, package};
  @override
  DashboardCacheData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return DashboardCacheData(
      userId: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}user_id'])!,
      package: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}package'])!,
      jsonData: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}json_data'])!,
      cachedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}cached_at'])!,
    );
  }

  @override
  $DashboardCacheTable createAlias(String alias) {
    return $DashboardCacheTable(attachedDatabase, alias);
  }
}

class DashboardCacheData extends DataClass
    implements Insertable<DashboardCacheData> {
  final String userId;
  final String package;
  final String jsonData;
  final DateTime cachedAt;
  const DashboardCacheData(
      {required this.userId,
      required this.package,
      required this.jsonData,
      required this.cachedAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['user_id'] = Variable<String>(userId);
    map['package'] = Variable<String>(package);
    map['json_data'] = Variable<String>(jsonData);
    map['cached_at'] = Variable<DateTime>(cachedAt);
    return map;
  }

  DashboardCacheCompanion toCompanion(bool nullToAbsent) {
    return DashboardCacheCompanion(
      userId: Value(userId),
      package: Value(package),
      jsonData: Value(jsonData),
      cachedAt: Value(cachedAt),
    );
  }

  factory DashboardCacheData.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return DashboardCacheData(
      userId: serializer.fromJson<String>(json['userId']),
      package: serializer.fromJson<String>(json['package']),
      jsonData: serializer.fromJson<String>(json['jsonData']),
      cachedAt: serializer.fromJson<DateTime>(json['cachedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'userId': serializer.toJson<String>(userId),
      'package': serializer.toJson<String>(package),
      'jsonData': serializer.toJson<String>(jsonData),
      'cachedAt': serializer.toJson<DateTime>(cachedAt),
    };
  }

  DashboardCacheData copyWith(
          {String? userId,
          String? package,
          String? jsonData,
          DateTime? cachedAt}) =>
      DashboardCacheData(
        userId: userId ?? this.userId,
        package: package ?? this.package,
        jsonData: jsonData ?? this.jsonData,
        cachedAt: cachedAt ?? this.cachedAt,
      );
  DashboardCacheData copyWithCompanion(DashboardCacheCompanion data) {
    return DashboardCacheData(
      userId: data.userId.present ? data.userId.value : this.userId,
      package: data.package.present ? data.package.value : this.package,
      jsonData: data.jsonData.present ? data.jsonData.value : this.jsonData,
      cachedAt: data.cachedAt.present ? data.cachedAt.value : this.cachedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('DashboardCacheData(')
          ..write('userId: $userId, ')
          ..write('package: $package, ')
          ..write('jsonData: $jsonData, ')
          ..write('cachedAt: $cachedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(userId, package, jsonData, cachedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is DashboardCacheData &&
          other.userId == this.userId &&
          other.package == this.package &&
          other.jsonData == this.jsonData &&
          other.cachedAt == this.cachedAt);
}

class DashboardCacheCompanion extends UpdateCompanion<DashboardCacheData> {
  final Value<String> userId;
  final Value<String> package;
  final Value<String> jsonData;
  final Value<DateTime> cachedAt;
  final Value<int> rowid;
  const DashboardCacheCompanion({
    this.userId = const Value.absent(),
    this.package = const Value.absent(),
    this.jsonData = const Value.absent(),
    this.cachedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  DashboardCacheCompanion.insert({
    required String userId,
    required String package,
    required String jsonData,
    required DateTime cachedAt,
    this.rowid = const Value.absent(),
  })  : userId = Value(userId),
        package = Value(package),
        jsonData = Value(jsonData),
        cachedAt = Value(cachedAt);
  static Insertable<DashboardCacheData> custom({
    Expression<String>? userId,
    Expression<String>? package,
    Expression<String>? jsonData,
    Expression<DateTime>? cachedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (userId != null) 'user_id': userId,
      if (package != null) 'package': package,
      if (jsonData != null) 'json_data': jsonData,
      if (cachedAt != null) 'cached_at': cachedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  DashboardCacheCompanion copyWith(
      {Value<String>? userId,
      Value<String>? package,
      Value<String>? jsonData,
      Value<DateTime>? cachedAt,
      Value<int>? rowid}) {
    return DashboardCacheCompanion(
      userId: userId ?? this.userId,
      package: package ?? this.package,
      jsonData: jsonData ?? this.jsonData,
      cachedAt: cachedAt ?? this.cachedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (userId.present) {
      map['user_id'] = Variable<String>(userId.value);
    }
    if (package.present) {
      map['package'] = Variable<String>(package.value);
    }
    if (jsonData.present) {
      map['json_data'] = Variable<String>(jsonData.value);
    }
    if (cachedAt.present) {
      map['cached_at'] = Variable<DateTime>(cachedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('DashboardCacheCompanion(')
          ..write('userId: $userId, ')
          ..write('package: $package, ')
          ..write('jsonData: $jsonData, ')
          ..write('cachedAt: $cachedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $MyAttendanceCacheTable extends MyAttendanceCache
    with TableInfo<$MyAttendanceCacheTable, MyAttendanceCacheData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $MyAttendanceCacheTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _userIdMeta = const VerificationMeta('userId');
  @override
  late final GeneratedColumn<String> userId = GeneratedColumn<String>(
      'user_id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _dateMeta = const VerificationMeta('date');
  @override
  late final GeneratedColumn<String> date = GeneratedColumn<String>(
      'date', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
      'status', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _checkInTimeMeta =
      const VerificationMeta('checkInTime');
  @override
  late final GeneratedColumn<String> checkInTime = GeneratedColumn<String>(
      'check_in_time', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _checkOutTimeMeta =
      const VerificationMeta('checkOutTime');
  @override
  late final GeneratedColumn<String> checkOutTime = GeneratedColumn<String>(
      'check_out_time', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _lateMinutesMeta =
      const VerificationMeta('lateMinutes');
  @override
  late final GeneratedColumn<int> lateMinutes = GeneratedColumn<int>(
      'late_minutes', aliasedName, false,
      type: DriftSqlType.int,
      requiredDuringInsert: false,
      defaultValue: const Constant(0));
  static const VerificationMeta _identityNumberMeta =
      const VerificationMeta('identityNumber');
  @override
  late final GeneratedColumn<String> identityNumber = GeneratedColumn<String>(
      'identity_number', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _uniqueCodeMeta =
      const VerificationMeta('uniqueCode');
  @override
  late final GeneratedColumn<String> uniqueCode = GeneratedColumn<String>(
      'unique_code', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _rfidCodeMeta =
      const VerificationMeta('rfidCode');
  @override
  late final GeneratedColumn<String> rfidCode = GeneratedColumn<String>(
      'rfid_code', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _dateLabelMeta =
      const VerificationMeta('dateLabel');
  @override
  late final GeneratedColumn<String> dateLabel = GeneratedColumn<String>(
      'date_label', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _syncedAtMeta =
      const VerificationMeta('syncedAt');
  @override
  late final GeneratedColumn<DateTime> syncedAt = GeneratedColumn<DateTime>(
      'synced_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  List<GeneratedColumn> get $columns => [
        userId,
        date,
        status,
        checkInTime,
        checkOutTime,
        lateMinutes,
        identityNumber,
        uniqueCode,
        rfidCode,
        dateLabel,
        syncedAt
      ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'my_attendance_cache';
  @override
  VerificationContext validateIntegrity(
      Insertable<MyAttendanceCacheData> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('user_id')) {
      context.handle(_userIdMeta,
          userId.isAcceptableOrUnknown(data['user_id']!, _userIdMeta));
    } else if (isInserting) {
      context.missing(_userIdMeta);
    }
    if (data.containsKey('date')) {
      context.handle(
          _dateMeta, date.isAcceptableOrUnknown(data['date']!, _dateMeta));
    } else if (isInserting) {
      context.missing(_dateMeta);
    }
    if (data.containsKey('status')) {
      context.handle(_statusMeta,
          status.isAcceptableOrUnknown(data['status']!, _statusMeta));
    } else if (isInserting) {
      context.missing(_statusMeta);
    }
    if (data.containsKey('check_in_time')) {
      context.handle(
          _checkInTimeMeta,
          checkInTime.isAcceptableOrUnknown(
              data['check_in_time']!, _checkInTimeMeta));
    }
    if (data.containsKey('check_out_time')) {
      context.handle(
          _checkOutTimeMeta,
          checkOutTime.isAcceptableOrUnknown(
              data['check_out_time']!, _checkOutTimeMeta));
    }
    if (data.containsKey('late_minutes')) {
      context.handle(
          _lateMinutesMeta,
          lateMinutes.isAcceptableOrUnknown(
              data['late_minutes']!, _lateMinutesMeta));
    }
    if (data.containsKey('identity_number')) {
      context.handle(
          _identityNumberMeta,
          identityNumber.isAcceptableOrUnknown(
              data['identity_number']!, _identityNumberMeta));
    }
    if (data.containsKey('unique_code')) {
      context.handle(
          _uniqueCodeMeta,
          uniqueCode.isAcceptableOrUnknown(
              data['unique_code']!, _uniqueCodeMeta));
    }
    if (data.containsKey('rfid_code')) {
      context.handle(_rfidCodeMeta,
          rfidCode.isAcceptableOrUnknown(data['rfid_code']!, _rfidCodeMeta));
    }
    if (data.containsKey('date_label')) {
      context.handle(_dateLabelMeta,
          dateLabel.isAcceptableOrUnknown(data['date_label']!, _dateLabelMeta));
    }
    if (data.containsKey('synced_at')) {
      context.handle(_syncedAtMeta,
          syncedAt.isAcceptableOrUnknown(data['synced_at']!, _syncedAtMeta));
    } else if (isInserting) {
      context.missing(_syncedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {userId, date};
  @override
  MyAttendanceCacheData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return MyAttendanceCacheData(
      userId: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}user_id'])!,
      date: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}date'])!,
      status: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}status'])!,
      checkInTime: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}check_in_time']),
      checkOutTime: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}check_out_time']),
      lateMinutes: attachedDatabase.typeMapping
          .read(DriftSqlType.int, data['${effectivePrefix}late_minutes'])!,
      identityNumber: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}identity_number']),
      uniqueCode: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}unique_code']),
      rfidCode: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}rfid_code']),
      dateLabel: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}date_label']),
      syncedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}synced_at'])!,
    );
  }

  @override
  $MyAttendanceCacheTable createAlias(String alias) {
    return $MyAttendanceCacheTable(attachedDatabase, alias);
  }
}

class MyAttendanceCacheData extends DataClass
    implements Insertable<MyAttendanceCacheData> {
  final String userId;
  final String date;
  final String status;
  final String? checkInTime;
  final String? checkOutTime;
  final int lateMinutes;
  final String? identityNumber;
  final String? uniqueCode;
  final String? rfidCode;
  final String? dateLabel;
  final DateTime syncedAt;
  const MyAttendanceCacheData(
      {required this.userId,
      required this.date,
      required this.status,
      this.checkInTime,
      this.checkOutTime,
      required this.lateMinutes,
      this.identityNumber,
      this.uniqueCode,
      this.rfidCode,
      this.dateLabel,
      required this.syncedAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['user_id'] = Variable<String>(userId);
    map['date'] = Variable<String>(date);
    map['status'] = Variable<String>(status);
    if (!nullToAbsent || checkInTime != null) {
      map['check_in_time'] = Variable<String>(checkInTime);
    }
    if (!nullToAbsent || checkOutTime != null) {
      map['check_out_time'] = Variable<String>(checkOutTime);
    }
    map['late_minutes'] = Variable<int>(lateMinutes);
    if (!nullToAbsent || identityNumber != null) {
      map['identity_number'] = Variable<String>(identityNumber);
    }
    if (!nullToAbsent || uniqueCode != null) {
      map['unique_code'] = Variable<String>(uniqueCode);
    }
    if (!nullToAbsent || rfidCode != null) {
      map['rfid_code'] = Variable<String>(rfidCode);
    }
    if (!nullToAbsent || dateLabel != null) {
      map['date_label'] = Variable<String>(dateLabel);
    }
    map['synced_at'] = Variable<DateTime>(syncedAt);
    return map;
  }

  MyAttendanceCacheCompanion toCompanion(bool nullToAbsent) {
    return MyAttendanceCacheCompanion(
      userId: Value(userId),
      date: Value(date),
      status: Value(status),
      checkInTime: checkInTime == null && nullToAbsent
          ? const Value.absent()
          : Value(checkInTime),
      checkOutTime: checkOutTime == null && nullToAbsent
          ? const Value.absent()
          : Value(checkOutTime),
      lateMinutes: Value(lateMinutes),
      identityNumber: identityNumber == null && nullToAbsent
          ? const Value.absent()
          : Value(identityNumber),
      uniqueCode: uniqueCode == null && nullToAbsent
          ? const Value.absent()
          : Value(uniqueCode),
      rfidCode: rfidCode == null && nullToAbsent
          ? const Value.absent()
          : Value(rfidCode),
      dateLabel: dateLabel == null && nullToAbsent
          ? const Value.absent()
          : Value(dateLabel),
      syncedAt: Value(syncedAt),
    );
  }

  factory MyAttendanceCacheData.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return MyAttendanceCacheData(
      userId: serializer.fromJson<String>(json['userId']),
      date: serializer.fromJson<String>(json['date']),
      status: serializer.fromJson<String>(json['status']),
      checkInTime: serializer.fromJson<String?>(json['checkInTime']),
      checkOutTime: serializer.fromJson<String?>(json['checkOutTime']),
      lateMinutes: serializer.fromJson<int>(json['lateMinutes']),
      identityNumber: serializer.fromJson<String?>(json['identityNumber']),
      uniqueCode: serializer.fromJson<String?>(json['uniqueCode']),
      rfidCode: serializer.fromJson<String?>(json['rfidCode']),
      dateLabel: serializer.fromJson<String?>(json['dateLabel']),
      syncedAt: serializer.fromJson<DateTime>(json['syncedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'userId': serializer.toJson<String>(userId),
      'date': serializer.toJson<String>(date),
      'status': serializer.toJson<String>(status),
      'checkInTime': serializer.toJson<String?>(checkInTime),
      'checkOutTime': serializer.toJson<String?>(checkOutTime),
      'lateMinutes': serializer.toJson<int>(lateMinutes),
      'identityNumber': serializer.toJson<String?>(identityNumber),
      'uniqueCode': serializer.toJson<String?>(uniqueCode),
      'rfidCode': serializer.toJson<String?>(rfidCode),
      'dateLabel': serializer.toJson<String?>(dateLabel),
      'syncedAt': serializer.toJson<DateTime>(syncedAt),
    };
  }

  MyAttendanceCacheData copyWith(
          {String? userId,
          String? date,
          String? status,
          Value<String?> checkInTime = const Value.absent(),
          Value<String?> checkOutTime = const Value.absent(),
          int? lateMinutes,
          Value<String?> identityNumber = const Value.absent(),
          Value<String?> uniqueCode = const Value.absent(),
          Value<String?> rfidCode = const Value.absent(),
          Value<String?> dateLabel = const Value.absent(),
          DateTime? syncedAt}) =>
      MyAttendanceCacheData(
        userId: userId ?? this.userId,
        date: date ?? this.date,
        status: status ?? this.status,
        checkInTime: checkInTime.present ? checkInTime.value : this.checkInTime,
        checkOutTime:
            checkOutTime.present ? checkOutTime.value : this.checkOutTime,
        lateMinutes: lateMinutes ?? this.lateMinutes,
        identityNumber:
            identityNumber.present ? identityNumber.value : this.identityNumber,
        uniqueCode: uniqueCode.present ? uniqueCode.value : this.uniqueCode,
        rfidCode: rfidCode.present ? rfidCode.value : this.rfidCode,
        dateLabel: dateLabel.present ? dateLabel.value : this.dateLabel,
        syncedAt: syncedAt ?? this.syncedAt,
      );
  MyAttendanceCacheData copyWithCompanion(MyAttendanceCacheCompanion data) {
    return MyAttendanceCacheData(
      userId: data.userId.present ? data.userId.value : this.userId,
      date: data.date.present ? data.date.value : this.date,
      status: data.status.present ? data.status.value : this.status,
      checkInTime:
          data.checkInTime.present ? data.checkInTime.value : this.checkInTime,
      checkOutTime: data.checkOutTime.present
          ? data.checkOutTime.value
          : this.checkOutTime,
      lateMinutes:
          data.lateMinutes.present ? data.lateMinutes.value : this.lateMinutes,
      identityNumber: data.identityNumber.present
          ? data.identityNumber.value
          : this.identityNumber,
      uniqueCode:
          data.uniqueCode.present ? data.uniqueCode.value : this.uniqueCode,
      rfidCode: data.rfidCode.present ? data.rfidCode.value : this.rfidCode,
      dateLabel: data.dateLabel.present ? data.dateLabel.value : this.dateLabel,
      syncedAt: data.syncedAt.present ? data.syncedAt.value : this.syncedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('MyAttendanceCacheData(')
          ..write('userId: $userId, ')
          ..write('date: $date, ')
          ..write('status: $status, ')
          ..write('checkInTime: $checkInTime, ')
          ..write('checkOutTime: $checkOutTime, ')
          ..write('lateMinutes: $lateMinutes, ')
          ..write('identityNumber: $identityNumber, ')
          ..write('uniqueCode: $uniqueCode, ')
          ..write('rfidCode: $rfidCode, ')
          ..write('dateLabel: $dateLabel, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
      userId,
      date,
      status,
      checkInTime,
      checkOutTime,
      lateMinutes,
      identityNumber,
      uniqueCode,
      rfidCode,
      dateLabel,
      syncedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is MyAttendanceCacheData &&
          other.userId == this.userId &&
          other.date == this.date &&
          other.status == this.status &&
          other.checkInTime == this.checkInTime &&
          other.checkOutTime == this.checkOutTime &&
          other.lateMinutes == this.lateMinutes &&
          other.identityNumber == this.identityNumber &&
          other.uniqueCode == this.uniqueCode &&
          other.rfidCode == this.rfidCode &&
          other.dateLabel == this.dateLabel &&
          other.syncedAt == this.syncedAt);
}

class MyAttendanceCacheCompanion
    extends UpdateCompanion<MyAttendanceCacheData> {
  final Value<String> userId;
  final Value<String> date;
  final Value<String> status;
  final Value<String?> checkInTime;
  final Value<String?> checkOutTime;
  final Value<int> lateMinutes;
  final Value<String?> identityNumber;
  final Value<String?> uniqueCode;
  final Value<String?> rfidCode;
  final Value<String?> dateLabel;
  final Value<DateTime> syncedAt;
  final Value<int> rowid;
  const MyAttendanceCacheCompanion({
    this.userId = const Value.absent(),
    this.date = const Value.absent(),
    this.status = const Value.absent(),
    this.checkInTime = const Value.absent(),
    this.checkOutTime = const Value.absent(),
    this.lateMinutes = const Value.absent(),
    this.identityNumber = const Value.absent(),
    this.uniqueCode = const Value.absent(),
    this.rfidCode = const Value.absent(),
    this.dateLabel = const Value.absent(),
    this.syncedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  MyAttendanceCacheCompanion.insert({
    required String userId,
    required String date,
    required String status,
    this.checkInTime = const Value.absent(),
    this.checkOutTime = const Value.absent(),
    this.lateMinutes = const Value.absent(),
    this.identityNumber = const Value.absent(),
    this.uniqueCode = const Value.absent(),
    this.rfidCode = const Value.absent(),
    this.dateLabel = const Value.absent(),
    required DateTime syncedAt,
    this.rowid = const Value.absent(),
  })  : userId = Value(userId),
        date = Value(date),
        status = Value(status),
        syncedAt = Value(syncedAt);
  static Insertable<MyAttendanceCacheData> custom({
    Expression<String>? userId,
    Expression<String>? date,
    Expression<String>? status,
    Expression<String>? checkInTime,
    Expression<String>? checkOutTime,
    Expression<int>? lateMinutes,
    Expression<String>? identityNumber,
    Expression<String>? uniqueCode,
    Expression<String>? rfidCode,
    Expression<String>? dateLabel,
    Expression<DateTime>? syncedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (userId != null) 'user_id': userId,
      if (date != null) 'date': date,
      if (status != null) 'status': status,
      if (checkInTime != null) 'check_in_time': checkInTime,
      if (checkOutTime != null) 'check_out_time': checkOutTime,
      if (lateMinutes != null) 'late_minutes': lateMinutes,
      if (identityNumber != null) 'identity_number': identityNumber,
      if (uniqueCode != null) 'unique_code': uniqueCode,
      if (rfidCode != null) 'rfid_code': rfidCode,
      if (dateLabel != null) 'date_label': dateLabel,
      if (syncedAt != null) 'synced_at': syncedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  MyAttendanceCacheCompanion copyWith(
      {Value<String>? userId,
      Value<String>? date,
      Value<String>? status,
      Value<String?>? checkInTime,
      Value<String?>? checkOutTime,
      Value<int>? lateMinutes,
      Value<String?>? identityNumber,
      Value<String?>? uniqueCode,
      Value<String?>? rfidCode,
      Value<String?>? dateLabel,
      Value<DateTime>? syncedAt,
      Value<int>? rowid}) {
    return MyAttendanceCacheCompanion(
      userId: userId ?? this.userId,
      date: date ?? this.date,
      status: status ?? this.status,
      checkInTime: checkInTime ?? this.checkInTime,
      checkOutTime: checkOutTime ?? this.checkOutTime,
      lateMinutes: lateMinutes ?? this.lateMinutes,
      identityNumber: identityNumber ?? this.identityNumber,
      uniqueCode: uniqueCode ?? this.uniqueCode,
      rfidCode: rfidCode ?? this.rfidCode,
      dateLabel: dateLabel ?? this.dateLabel,
      syncedAt: syncedAt ?? this.syncedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (userId.present) {
      map['user_id'] = Variable<String>(userId.value);
    }
    if (date.present) {
      map['date'] = Variable<String>(date.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (checkInTime.present) {
      map['check_in_time'] = Variable<String>(checkInTime.value);
    }
    if (checkOutTime.present) {
      map['check_out_time'] = Variable<String>(checkOutTime.value);
    }
    if (lateMinutes.present) {
      map['late_minutes'] = Variable<int>(lateMinutes.value);
    }
    if (identityNumber.present) {
      map['identity_number'] = Variable<String>(identityNumber.value);
    }
    if (uniqueCode.present) {
      map['unique_code'] = Variable<String>(uniqueCode.value);
    }
    if (rfidCode.present) {
      map['rfid_code'] = Variable<String>(rfidCode.value);
    }
    if (dateLabel.present) {
      map['date_label'] = Variable<String>(dateLabel.value);
    }
    if (syncedAt.present) {
      map['synced_at'] = Variable<DateTime>(syncedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('MyAttendanceCacheCompanion(')
          ..write('userId: $userId, ')
          ..write('date: $date, ')
          ..write('status: $status, ')
          ..write('checkInTime: $checkInTime, ')
          ..write('checkOutTime: $checkOutTime, ')
          ..write('lateMinutes: $lateMinutes, ')
          ..write('identityNumber: $identityNumber, ')
          ..write('uniqueCode: $uniqueCode, ')
          ..write('rfidCode: $rfidCode, ')
          ..write('dateLabel: $dateLabel, ')
          ..write('syncedAt: $syncedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $MyAttendanceHistoryTable extends MyAttendanceHistory
    with TableInfo<$MyAttendanceHistoryTable, MyAttendanceHistoryData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $MyAttendanceHistoryTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _rowIdMeta = const VerificationMeta('rowId');
  @override
  late final GeneratedColumn<int> rowId = GeneratedColumn<int>(
      'row_id', aliasedName, false,
      hasAutoIncrement: true,
      type: DriftSqlType.int,
      requiredDuringInsert: false,
      defaultConstraints:
          GeneratedColumn.constraintIsAlways('PRIMARY KEY AUTOINCREMENT'));
  static const VerificationMeta _userIdMeta = const VerificationMeta('userId');
  @override
  late final GeneratedColumn<String> userId = GeneratedColumn<String>(
      'user_id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _dateMeta = const VerificationMeta('date');
  @override
  late final GeneratedColumn<String> date = GeneratedColumn<String>(
      'date', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _statusMeta = const VerificationMeta('status');
  @override
  late final GeneratedColumn<String> status = GeneratedColumn<String>(
      'status', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _labelMeta = const VerificationMeta('label');
  @override
  late final GeneratedColumn<String> label = GeneratedColumn<String>(
      'label', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _syncedAtMeta =
      const VerificationMeta('syncedAt');
  @override
  late final GeneratedColumn<DateTime> syncedAt = GeneratedColumn<DateTime>(
      'synced_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  List<GeneratedColumn> get $columns =>
      [rowId, userId, date, status, label, syncedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'my_attendance_history';
  @override
  VerificationContext validateIntegrity(
      Insertable<MyAttendanceHistoryData> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('row_id')) {
      context.handle(
          _rowIdMeta, rowId.isAcceptableOrUnknown(data['row_id']!, _rowIdMeta));
    }
    if (data.containsKey('user_id')) {
      context.handle(_userIdMeta,
          userId.isAcceptableOrUnknown(data['user_id']!, _userIdMeta));
    } else if (isInserting) {
      context.missing(_userIdMeta);
    }
    if (data.containsKey('date')) {
      context.handle(
          _dateMeta, date.isAcceptableOrUnknown(data['date']!, _dateMeta));
    } else if (isInserting) {
      context.missing(_dateMeta);
    }
    if (data.containsKey('status')) {
      context.handle(_statusMeta,
          status.isAcceptableOrUnknown(data['status']!, _statusMeta));
    } else if (isInserting) {
      context.missing(_statusMeta);
    }
    if (data.containsKey('label')) {
      context.handle(
          _labelMeta, label.isAcceptableOrUnknown(data['label']!, _labelMeta));
    }
    if (data.containsKey('synced_at')) {
      context.handle(_syncedAtMeta,
          syncedAt.isAcceptableOrUnknown(data['synced_at']!, _syncedAtMeta));
    } else if (isInserting) {
      context.missing(_syncedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {rowId};
  @override
  MyAttendanceHistoryData map(Map<String, dynamic> data,
      {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return MyAttendanceHistoryData(
      rowId: attachedDatabase.typeMapping
          .read(DriftSqlType.int, data['${effectivePrefix}row_id'])!,
      userId: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}user_id'])!,
      date: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}date'])!,
      status: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}status'])!,
      label: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}label']),
      syncedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}synced_at'])!,
    );
  }

  @override
  $MyAttendanceHistoryTable createAlias(String alias) {
    return $MyAttendanceHistoryTable(attachedDatabase, alias);
  }
}

class MyAttendanceHistoryData extends DataClass
    implements Insertable<MyAttendanceHistoryData> {
  final int rowId;
  final String userId;
  final String date;
  final String status;
  final String? label;
  final DateTime syncedAt;
  const MyAttendanceHistoryData(
      {required this.rowId,
      required this.userId,
      required this.date,
      required this.status,
      this.label,
      required this.syncedAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['row_id'] = Variable<int>(rowId);
    map['user_id'] = Variable<String>(userId);
    map['date'] = Variable<String>(date);
    map['status'] = Variable<String>(status);
    if (!nullToAbsent || label != null) {
      map['label'] = Variable<String>(label);
    }
    map['synced_at'] = Variable<DateTime>(syncedAt);
    return map;
  }

  MyAttendanceHistoryCompanion toCompanion(bool nullToAbsent) {
    return MyAttendanceHistoryCompanion(
      rowId: Value(rowId),
      userId: Value(userId),
      date: Value(date),
      status: Value(status),
      label:
          label == null && nullToAbsent ? const Value.absent() : Value(label),
      syncedAt: Value(syncedAt),
    );
  }

  factory MyAttendanceHistoryData.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return MyAttendanceHistoryData(
      rowId: serializer.fromJson<int>(json['rowId']),
      userId: serializer.fromJson<String>(json['userId']),
      date: serializer.fromJson<String>(json['date']),
      status: serializer.fromJson<String>(json['status']),
      label: serializer.fromJson<String?>(json['label']),
      syncedAt: serializer.fromJson<DateTime>(json['syncedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'rowId': serializer.toJson<int>(rowId),
      'userId': serializer.toJson<String>(userId),
      'date': serializer.toJson<String>(date),
      'status': serializer.toJson<String>(status),
      'label': serializer.toJson<String?>(label),
      'syncedAt': serializer.toJson<DateTime>(syncedAt),
    };
  }

  MyAttendanceHistoryData copyWith(
          {int? rowId,
          String? userId,
          String? date,
          String? status,
          Value<String?> label = const Value.absent(),
          DateTime? syncedAt}) =>
      MyAttendanceHistoryData(
        rowId: rowId ?? this.rowId,
        userId: userId ?? this.userId,
        date: date ?? this.date,
        status: status ?? this.status,
        label: label.present ? label.value : this.label,
        syncedAt: syncedAt ?? this.syncedAt,
      );
  MyAttendanceHistoryData copyWithCompanion(MyAttendanceHistoryCompanion data) {
    return MyAttendanceHistoryData(
      rowId: data.rowId.present ? data.rowId.value : this.rowId,
      userId: data.userId.present ? data.userId.value : this.userId,
      date: data.date.present ? data.date.value : this.date,
      status: data.status.present ? data.status.value : this.status,
      label: data.label.present ? data.label.value : this.label,
      syncedAt: data.syncedAt.present ? data.syncedAt.value : this.syncedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('MyAttendanceHistoryData(')
          ..write('rowId: $rowId, ')
          ..write('userId: $userId, ')
          ..write('date: $date, ')
          ..write('status: $status, ')
          ..write('label: $label, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(rowId, userId, date, status, label, syncedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is MyAttendanceHistoryData &&
          other.rowId == this.rowId &&
          other.userId == this.userId &&
          other.date == this.date &&
          other.status == this.status &&
          other.label == this.label &&
          other.syncedAt == this.syncedAt);
}

class MyAttendanceHistoryCompanion
    extends UpdateCompanion<MyAttendanceHistoryData> {
  final Value<int> rowId;
  final Value<String> userId;
  final Value<String> date;
  final Value<String> status;
  final Value<String?> label;
  final Value<DateTime> syncedAt;
  const MyAttendanceHistoryCompanion({
    this.rowId = const Value.absent(),
    this.userId = const Value.absent(),
    this.date = const Value.absent(),
    this.status = const Value.absent(),
    this.label = const Value.absent(),
    this.syncedAt = const Value.absent(),
  });
  MyAttendanceHistoryCompanion.insert({
    this.rowId = const Value.absent(),
    required String userId,
    required String date,
    required String status,
    this.label = const Value.absent(),
    required DateTime syncedAt,
  })  : userId = Value(userId),
        date = Value(date),
        status = Value(status),
        syncedAt = Value(syncedAt);
  static Insertable<MyAttendanceHistoryData> custom({
    Expression<int>? rowId,
    Expression<String>? userId,
    Expression<String>? date,
    Expression<String>? status,
    Expression<String>? label,
    Expression<DateTime>? syncedAt,
  }) {
    return RawValuesInsertable({
      if (rowId != null) 'row_id': rowId,
      if (userId != null) 'user_id': userId,
      if (date != null) 'date': date,
      if (status != null) 'status': status,
      if (label != null) 'label': label,
      if (syncedAt != null) 'synced_at': syncedAt,
    });
  }

  MyAttendanceHistoryCompanion copyWith(
      {Value<int>? rowId,
      Value<String>? userId,
      Value<String>? date,
      Value<String>? status,
      Value<String?>? label,
      Value<DateTime>? syncedAt}) {
    return MyAttendanceHistoryCompanion(
      rowId: rowId ?? this.rowId,
      userId: userId ?? this.userId,
      date: date ?? this.date,
      status: status ?? this.status,
      label: label ?? this.label,
      syncedAt: syncedAt ?? this.syncedAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (rowId.present) {
      map['row_id'] = Variable<int>(rowId.value);
    }
    if (userId.present) {
      map['user_id'] = Variable<String>(userId.value);
    }
    if (date.present) {
      map['date'] = Variable<String>(date.value);
    }
    if (status.present) {
      map['status'] = Variable<String>(status.value);
    }
    if (label.present) {
      map['label'] = Variable<String>(label.value);
    }
    if (syncedAt.present) {
      map['synced_at'] = Variable<DateTime>(syncedAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('MyAttendanceHistoryCompanion(')
          ..write('rowId: $rowId, ')
          ..write('userId: $userId, ')
          ..write('date: $date, ')
          ..write('status: $status, ')
          ..write('label: $label, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }
}

class $CachedNotificationsTable extends CachedNotifications
    with TableInfo<$CachedNotificationsTable, CachedNotification> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $CachedNotificationsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
      'id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _userIdMeta = const VerificationMeta('userId');
  @override
  late final GeneratedColumn<String> userId = GeneratedColumn<String>(
      'user_id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _titleMeta = const VerificationMeta('title');
  @override
  late final GeneratedColumn<String> title = GeneratedColumn<String>(
      'title', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _bodyMeta = const VerificationMeta('body');
  @override
  late final GeneratedColumn<String> body = GeneratedColumn<String>(
      'body', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _isReadMeta = const VerificationMeta('isRead');
  @override
  late final GeneratedColumn<bool> isRead = GeneratedColumn<bool>(
      'is_read', aliasedName, false,
      type: DriftSqlType.bool,
      requiredDuringInsert: false,
      defaultConstraints:
          GeneratedColumn.constraintIsAlways('CHECK ("is_read" IN (0, 1))'),
      defaultValue: const Constant(false));
  static const VerificationMeta _createdAtMeta =
      const VerificationMeta('createdAt');
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
      'created_at', aliasedName, true,
      type: DriftSqlType.dateTime, requiredDuringInsert: false);
  static const VerificationMeta _syncedAtMeta =
      const VerificationMeta('syncedAt');
  @override
  late final GeneratedColumn<DateTime> syncedAt = GeneratedColumn<DateTime>(
      'synced_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  List<GeneratedColumn> get $columns =>
      [id, userId, title, body, isRead, createdAt, syncedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'cached_notifications';
  @override
  VerificationContext validateIntegrity(Insertable<CachedNotification> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('user_id')) {
      context.handle(_userIdMeta,
          userId.isAcceptableOrUnknown(data['user_id']!, _userIdMeta));
    } else if (isInserting) {
      context.missing(_userIdMeta);
    }
    if (data.containsKey('title')) {
      context.handle(
          _titleMeta, title.isAcceptableOrUnknown(data['title']!, _titleMeta));
    } else if (isInserting) {
      context.missing(_titleMeta);
    }
    if (data.containsKey('body')) {
      context.handle(
          _bodyMeta, body.isAcceptableOrUnknown(data['body']!, _bodyMeta));
    } else if (isInserting) {
      context.missing(_bodyMeta);
    }
    if (data.containsKey('is_read')) {
      context.handle(_isReadMeta,
          isRead.isAcceptableOrUnknown(data['is_read']!, _isReadMeta));
    }
    if (data.containsKey('created_at')) {
      context.handle(_createdAtMeta,
          createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta));
    }
    if (data.containsKey('synced_at')) {
      context.handle(_syncedAtMeta,
          syncedAt.isAcceptableOrUnknown(data['synced_at']!, _syncedAtMeta));
    } else if (isInserting) {
      context.missing(_syncedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  CachedNotification map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return CachedNotification(
      id: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}id'])!,
      userId: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}user_id'])!,
      title: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}title'])!,
      body: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}body'])!,
      isRead: attachedDatabase.typeMapping
          .read(DriftSqlType.bool, data['${effectivePrefix}is_read'])!,
      createdAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}created_at']),
      syncedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}synced_at'])!,
    );
  }

  @override
  $CachedNotificationsTable createAlias(String alias) {
    return $CachedNotificationsTable(attachedDatabase, alias);
  }
}

class CachedNotification extends DataClass
    implements Insertable<CachedNotification> {
  final String id;
  final String userId;
  final String title;
  final String body;
  final bool isRead;
  final DateTime? createdAt;
  final DateTime syncedAt;
  const CachedNotification(
      {required this.id,
      required this.userId,
      required this.title,
      required this.body,
      required this.isRead,
      this.createdAt,
      required this.syncedAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['user_id'] = Variable<String>(userId);
    map['title'] = Variable<String>(title);
    map['body'] = Variable<String>(body);
    map['is_read'] = Variable<bool>(isRead);
    if (!nullToAbsent || createdAt != null) {
      map['created_at'] = Variable<DateTime>(createdAt);
    }
    map['synced_at'] = Variable<DateTime>(syncedAt);
    return map;
  }

  CachedNotificationsCompanion toCompanion(bool nullToAbsent) {
    return CachedNotificationsCompanion(
      id: Value(id),
      userId: Value(userId),
      title: Value(title),
      body: Value(body),
      isRead: Value(isRead),
      createdAt: createdAt == null && nullToAbsent
          ? const Value.absent()
          : Value(createdAt),
      syncedAt: Value(syncedAt),
    );
  }

  factory CachedNotification.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return CachedNotification(
      id: serializer.fromJson<String>(json['id']),
      userId: serializer.fromJson<String>(json['userId']),
      title: serializer.fromJson<String>(json['title']),
      body: serializer.fromJson<String>(json['body']),
      isRead: serializer.fromJson<bool>(json['isRead']),
      createdAt: serializer.fromJson<DateTime?>(json['createdAt']),
      syncedAt: serializer.fromJson<DateTime>(json['syncedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'userId': serializer.toJson<String>(userId),
      'title': serializer.toJson<String>(title),
      'body': serializer.toJson<String>(body),
      'isRead': serializer.toJson<bool>(isRead),
      'createdAt': serializer.toJson<DateTime?>(createdAt),
      'syncedAt': serializer.toJson<DateTime>(syncedAt),
    };
  }

  CachedNotification copyWith(
          {String? id,
          String? userId,
          String? title,
          String? body,
          bool? isRead,
          Value<DateTime?> createdAt = const Value.absent(),
          DateTime? syncedAt}) =>
      CachedNotification(
        id: id ?? this.id,
        userId: userId ?? this.userId,
        title: title ?? this.title,
        body: body ?? this.body,
        isRead: isRead ?? this.isRead,
        createdAt: createdAt.present ? createdAt.value : this.createdAt,
        syncedAt: syncedAt ?? this.syncedAt,
      );
  CachedNotification copyWithCompanion(CachedNotificationsCompanion data) {
    return CachedNotification(
      id: data.id.present ? data.id.value : this.id,
      userId: data.userId.present ? data.userId.value : this.userId,
      title: data.title.present ? data.title.value : this.title,
      body: data.body.present ? data.body.value : this.body,
      isRead: data.isRead.present ? data.isRead.value : this.isRead,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      syncedAt: data.syncedAt.present ? data.syncedAt.value : this.syncedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('CachedNotification(')
          ..write('id: $id, ')
          ..write('userId: $userId, ')
          ..write('title: $title, ')
          ..write('body: $body, ')
          ..write('isRead: $isRead, ')
          ..write('createdAt: $createdAt, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode =>
      Object.hash(id, userId, title, body, isRead, createdAt, syncedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is CachedNotification &&
          other.id == this.id &&
          other.userId == this.userId &&
          other.title == this.title &&
          other.body == this.body &&
          other.isRead == this.isRead &&
          other.createdAt == this.createdAt &&
          other.syncedAt == this.syncedAt);
}

class CachedNotificationsCompanion extends UpdateCompanion<CachedNotification> {
  final Value<String> id;
  final Value<String> userId;
  final Value<String> title;
  final Value<String> body;
  final Value<bool> isRead;
  final Value<DateTime?> createdAt;
  final Value<DateTime> syncedAt;
  final Value<int> rowid;
  const CachedNotificationsCompanion({
    this.id = const Value.absent(),
    this.userId = const Value.absent(),
    this.title = const Value.absent(),
    this.body = const Value.absent(),
    this.isRead = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.syncedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  CachedNotificationsCompanion.insert({
    required String id,
    required String userId,
    required String title,
    required String body,
    this.isRead = const Value.absent(),
    this.createdAt = const Value.absent(),
    required DateTime syncedAt,
    this.rowid = const Value.absent(),
  })  : id = Value(id),
        userId = Value(userId),
        title = Value(title),
        body = Value(body),
        syncedAt = Value(syncedAt);
  static Insertable<CachedNotification> custom({
    Expression<String>? id,
    Expression<String>? userId,
    Expression<String>? title,
    Expression<String>? body,
    Expression<bool>? isRead,
    Expression<DateTime>? createdAt,
    Expression<DateTime>? syncedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (userId != null) 'user_id': userId,
      if (title != null) 'title': title,
      if (body != null) 'body': body,
      if (isRead != null) 'is_read': isRead,
      if (createdAt != null) 'created_at': createdAt,
      if (syncedAt != null) 'synced_at': syncedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  CachedNotificationsCompanion copyWith(
      {Value<String>? id,
      Value<String>? userId,
      Value<String>? title,
      Value<String>? body,
      Value<bool>? isRead,
      Value<DateTime?>? createdAt,
      Value<DateTime>? syncedAt,
      Value<int>? rowid}) {
    return CachedNotificationsCompanion(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      title: title ?? this.title,
      body: body ?? this.body,
      isRead: isRead ?? this.isRead,
      createdAt: createdAt ?? this.createdAt,
      syncedAt: syncedAt ?? this.syncedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (userId.present) {
      map['user_id'] = Variable<String>(userId.value);
    }
    if (title.present) {
      map['title'] = Variable<String>(title.value);
    }
    if (body.present) {
      map['body'] = Variable<String>(body.value);
    }
    if (isRead.present) {
      map['is_read'] = Variable<bool>(isRead.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (syncedAt.present) {
      map['synced_at'] = Variable<DateTime>(syncedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('CachedNotificationsCompanion(')
          ..write('id: $id, ')
          ..write('userId: $userId, ')
          ..write('title: $title, ')
          ..write('body: $body, ')
          ..write('isRead: $isRead, ')
          ..write('createdAt: $createdAt, ')
          ..write('syncedAt: $syncedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $FeeSummaryCacheTable extends FeeSummaryCache
    with TableInfo<$FeeSummaryCacheTable, FeeSummaryCacheData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $FeeSummaryCacheTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _userIdMeta = const VerificationMeta('userId');
  @override
  late final GeneratedColumn<String> userId = GeneratedColumn<String>(
      'user_id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _billedMeta = const VerificationMeta('billed');
  @override
  late final GeneratedColumn<String> billed = GeneratedColumn<String>(
      'billed', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _paidMeta = const VerificationMeta('paid');
  @override
  late final GeneratedColumn<String> paid = GeneratedColumn<String>(
      'paid', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _remainingMeta =
      const VerificationMeta('remaining');
  @override
  late final GeneratedColumn<String> remaining = GeneratedColumn<String>(
      'remaining', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _countOverdueMeta =
      const VerificationMeta('countOverdue');
  @override
  late final GeneratedColumn<int> countOverdue = GeneratedColumn<int>(
      'count_overdue', aliasedName, false,
      type: DriftSqlType.int,
      requiredDuringInsert: false,
      defaultValue: const Constant(0));
  static const VerificationMeta _countPartialMeta =
      const VerificationMeta('countPartial');
  @override
  late final GeneratedColumn<int> countPartial = GeneratedColumn<int>(
      'count_partial', aliasedName, false,
      type: DriftSqlType.int,
      requiredDuringInsert: false,
      defaultValue: const Constant(0));
  static const VerificationMeta _cachedAtMeta =
      const VerificationMeta('cachedAt');
  @override
  late final GeneratedColumn<DateTime> cachedAt = GeneratedColumn<DateTime>(
      'cached_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  List<GeneratedColumn> get $columns =>
      [userId, billed, paid, remaining, countOverdue, countPartial, cachedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'fee_summary_cache';
  @override
  VerificationContext validateIntegrity(
      Insertable<FeeSummaryCacheData> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('user_id')) {
      context.handle(_userIdMeta,
          userId.isAcceptableOrUnknown(data['user_id']!, _userIdMeta));
    } else if (isInserting) {
      context.missing(_userIdMeta);
    }
    if (data.containsKey('billed')) {
      context.handle(_billedMeta,
          billed.isAcceptableOrUnknown(data['billed']!, _billedMeta));
    } else if (isInserting) {
      context.missing(_billedMeta);
    }
    if (data.containsKey('paid')) {
      context.handle(
          _paidMeta, paid.isAcceptableOrUnknown(data['paid']!, _paidMeta));
    } else if (isInserting) {
      context.missing(_paidMeta);
    }
    if (data.containsKey('remaining')) {
      context.handle(_remainingMeta,
          remaining.isAcceptableOrUnknown(data['remaining']!, _remainingMeta));
    } else if (isInserting) {
      context.missing(_remainingMeta);
    }
    if (data.containsKey('count_overdue')) {
      context.handle(
          _countOverdueMeta,
          countOverdue.isAcceptableOrUnknown(
              data['count_overdue']!, _countOverdueMeta));
    }
    if (data.containsKey('count_partial')) {
      context.handle(
          _countPartialMeta,
          countPartial.isAcceptableOrUnknown(
              data['count_partial']!, _countPartialMeta));
    }
    if (data.containsKey('cached_at')) {
      context.handle(_cachedAtMeta,
          cachedAt.isAcceptableOrUnknown(data['cached_at']!, _cachedAtMeta));
    } else if (isInserting) {
      context.missing(_cachedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {userId};
  @override
  FeeSummaryCacheData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return FeeSummaryCacheData(
      userId: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}user_id'])!,
      billed: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}billed'])!,
      paid: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}paid'])!,
      remaining: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}remaining'])!,
      countOverdue: attachedDatabase.typeMapping
          .read(DriftSqlType.int, data['${effectivePrefix}count_overdue'])!,
      countPartial: attachedDatabase.typeMapping
          .read(DriftSqlType.int, data['${effectivePrefix}count_partial'])!,
      cachedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}cached_at'])!,
    );
  }

  @override
  $FeeSummaryCacheTable createAlias(String alias) {
    return $FeeSummaryCacheTable(attachedDatabase, alias);
  }
}

class FeeSummaryCacheData extends DataClass
    implements Insertable<FeeSummaryCacheData> {
  final String userId;
  final String billed;
  final String paid;
  final String remaining;
  final int countOverdue;
  final int countPartial;
  final DateTime cachedAt;
  const FeeSummaryCacheData(
      {required this.userId,
      required this.billed,
      required this.paid,
      required this.remaining,
      required this.countOverdue,
      required this.countPartial,
      required this.cachedAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['user_id'] = Variable<String>(userId);
    map['billed'] = Variable<String>(billed);
    map['paid'] = Variable<String>(paid);
    map['remaining'] = Variable<String>(remaining);
    map['count_overdue'] = Variable<int>(countOverdue);
    map['count_partial'] = Variable<int>(countPartial);
    map['cached_at'] = Variable<DateTime>(cachedAt);
    return map;
  }

  FeeSummaryCacheCompanion toCompanion(bool nullToAbsent) {
    return FeeSummaryCacheCompanion(
      userId: Value(userId),
      billed: Value(billed),
      paid: Value(paid),
      remaining: Value(remaining),
      countOverdue: Value(countOverdue),
      countPartial: Value(countPartial),
      cachedAt: Value(cachedAt),
    );
  }

  factory FeeSummaryCacheData.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return FeeSummaryCacheData(
      userId: serializer.fromJson<String>(json['userId']),
      billed: serializer.fromJson<String>(json['billed']),
      paid: serializer.fromJson<String>(json['paid']),
      remaining: serializer.fromJson<String>(json['remaining']),
      countOverdue: serializer.fromJson<int>(json['countOverdue']),
      countPartial: serializer.fromJson<int>(json['countPartial']),
      cachedAt: serializer.fromJson<DateTime>(json['cachedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'userId': serializer.toJson<String>(userId),
      'billed': serializer.toJson<String>(billed),
      'paid': serializer.toJson<String>(paid),
      'remaining': serializer.toJson<String>(remaining),
      'countOverdue': serializer.toJson<int>(countOverdue),
      'countPartial': serializer.toJson<int>(countPartial),
      'cachedAt': serializer.toJson<DateTime>(cachedAt),
    };
  }

  FeeSummaryCacheData copyWith(
          {String? userId,
          String? billed,
          String? paid,
          String? remaining,
          int? countOverdue,
          int? countPartial,
          DateTime? cachedAt}) =>
      FeeSummaryCacheData(
        userId: userId ?? this.userId,
        billed: billed ?? this.billed,
        paid: paid ?? this.paid,
        remaining: remaining ?? this.remaining,
        countOverdue: countOverdue ?? this.countOverdue,
        countPartial: countPartial ?? this.countPartial,
        cachedAt: cachedAt ?? this.cachedAt,
      );
  FeeSummaryCacheData copyWithCompanion(FeeSummaryCacheCompanion data) {
    return FeeSummaryCacheData(
      userId: data.userId.present ? data.userId.value : this.userId,
      billed: data.billed.present ? data.billed.value : this.billed,
      paid: data.paid.present ? data.paid.value : this.paid,
      remaining: data.remaining.present ? data.remaining.value : this.remaining,
      countOverdue: data.countOverdue.present
          ? data.countOverdue.value
          : this.countOverdue,
      countPartial: data.countPartial.present
          ? data.countPartial.value
          : this.countPartial,
      cachedAt: data.cachedAt.present ? data.cachedAt.value : this.cachedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('FeeSummaryCacheData(')
          ..write('userId: $userId, ')
          ..write('billed: $billed, ')
          ..write('paid: $paid, ')
          ..write('remaining: $remaining, ')
          ..write('countOverdue: $countOverdue, ')
          ..write('countPartial: $countPartial, ')
          ..write('cachedAt: $cachedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
      userId, billed, paid, remaining, countOverdue, countPartial, cachedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is FeeSummaryCacheData &&
          other.userId == this.userId &&
          other.billed == this.billed &&
          other.paid == this.paid &&
          other.remaining == this.remaining &&
          other.countOverdue == this.countOverdue &&
          other.countPartial == this.countPartial &&
          other.cachedAt == this.cachedAt);
}

class FeeSummaryCacheCompanion extends UpdateCompanion<FeeSummaryCacheData> {
  final Value<String> userId;
  final Value<String> billed;
  final Value<String> paid;
  final Value<String> remaining;
  final Value<int> countOverdue;
  final Value<int> countPartial;
  final Value<DateTime> cachedAt;
  final Value<int> rowid;
  const FeeSummaryCacheCompanion({
    this.userId = const Value.absent(),
    this.billed = const Value.absent(),
    this.paid = const Value.absent(),
    this.remaining = const Value.absent(),
    this.countOverdue = const Value.absent(),
    this.countPartial = const Value.absent(),
    this.cachedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  FeeSummaryCacheCompanion.insert({
    required String userId,
    required String billed,
    required String paid,
    required String remaining,
    this.countOverdue = const Value.absent(),
    this.countPartial = const Value.absent(),
    required DateTime cachedAt,
    this.rowid = const Value.absent(),
  })  : userId = Value(userId),
        billed = Value(billed),
        paid = Value(paid),
        remaining = Value(remaining),
        cachedAt = Value(cachedAt);
  static Insertable<FeeSummaryCacheData> custom({
    Expression<String>? userId,
    Expression<String>? billed,
    Expression<String>? paid,
    Expression<String>? remaining,
    Expression<int>? countOverdue,
    Expression<int>? countPartial,
    Expression<DateTime>? cachedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (userId != null) 'user_id': userId,
      if (billed != null) 'billed': billed,
      if (paid != null) 'paid': paid,
      if (remaining != null) 'remaining': remaining,
      if (countOverdue != null) 'count_overdue': countOverdue,
      if (countPartial != null) 'count_partial': countPartial,
      if (cachedAt != null) 'cached_at': cachedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  FeeSummaryCacheCompanion copyWith(
      {Value<String>? userId,
      Value<String>? billed,
      Value<String>? paid,
      Value<String>? remaining,
      Value<int>? countOverdue,
      Value<int>? countPartial,
      Value<DateTime>? cachedAt,
      Value<int>? rowid}) {
    return FeeSummaryCacheCompanion(
      userId: userId ?? this.userId,
      billed: billed ?? this.billed,
      paid: paid ?? this.paid,
      remaining: remaining ?? this.remaining,
      countOverdue: countOverdue ?? this.countOverdue,
      countPartial: countPartial ?? this.countPartial,
      cachedAt: cachedAt ?? this.cachedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (userId.present) {
      map['user_id'] = Variable<String>(userId.value);
    }
    if (billed.present) {
      map['billed'] = Variable<String>(billed.value);
    }
    if (paid.present) {
      map['paid'] = Variable<String>(paid.value);
    }
    if (remaining.present) {
      map['remaining'] = Variable<String>(remaining.value);
    }
    if (countOverdue.present) {
      map['count_overdue'] = Variable<int>(countOverdue.value);
    }
    if (countPartial.present) {
      map['count_partial'] = Variable<int>(countPartial.value);
    }
    if (cachedAt.present) {
      map['cached_at'] = Variable<DateTime>(cachedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('FeeSummaryCacheCompanion(')
          ..write('userId: $userId, ')
          ..write('billed: $billed, ')
          ..write('paid: $paid, ')
          ..write('remaining: $remaining, ')
          ..write('countOverdue: $countOverdue, ')
          ..write('countPartial: $countPartial, ')
          ..write('cachedAt: $cachedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $PaymentEntriesTable extends PaymentEntries
    with TableInfo<$PaymentEntriesTable, PaymentEntryRow> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PaymentEntriesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
      'id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _studentNameMeta =
      const VerificationMeta('studentName');
  @override
  late final GeneratedColumn<String> studentName = GeneratedColumn<String>(
      'student_name', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _amountMeta = const VerificationMeta('amount');
  @override
  late final GeneratedColumn<String> amount = GeneratedColumn<String>(
      'amount', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _classroomMeta =
      const VerificationMeta('classroom');
  @override
  late final GeneratedColumn<String> classroom = GeneratedColumn<String>(
      'classroom', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _methodMeta = const VerificationMeta('method');
  @override
  late final GeneratedColumn<String> method = GeneratedColumn<String>(
      'method', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _invoiceNumberMeta =
      const VerificationMeta('invoiceNumber');
  @override
  late final GeneratedColumn<String> invoiceNumber = GeneratedColumn<String>(
      'invoice_number', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _statusLabelMeta =
      const VerificationMeta('statusLabel');
  @override
  late final GeneratedColumn<String> statusLabel = GeneratedColumn<String>(
      'status_label', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _paidAtMeta = const VerificationMeta('paidAt');
  @override
  late final GeneratedColumn<String> paidAt = GeneratedColumn<String>(
      'paid_at', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _canVerifyMeta =
      const VerificationMeta('canVerify');
  @override
  late final GeneratedColumn<bool> canVerify = GeneratedColumn<bool>(
      'can_verify', aliasedName, false,
      type: DriftSqlType.bool,
      requiredDuringInsert: false,
      defaultConstraints:
          GeneratedColumn.constraintIsAlways('CHECK ("can_verify" IN (0, 1))'),
      defaultValue: const Constant(false));
  static const VerificationMeta _syncedAtMeta =
      const VerificationMeta('syncedAt');
  @override
  late final GeneratedColumn<DateTime> syncedAt = GeneratedColumn<DateTime>(
      'synced_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  List<GeneratedColumn> get $columns => [
        id,
        studentName,
        amount,
        classroom,
        method,
        invoiceNumber,
        statusLabel,
        paidAt,
        canVerify,
        syncedAt
      ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'payment_entries';
  @override
  VerificationContext validateIntegrity(Insertable<PaymentEntryRow> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('student_name')) {
      context.handle(
          _studentNameMeta,
          studentName.isAcceptableOrUnknown(
              data['student_name']!, _studentNameMeta));
    } else if (isInserting) {
      context.missing(_studentNameMeta);
    }
    if (data.containsKey('amount')) {
      context.handle(_amountMeta,
          amount.isAcceptableOrUnknown(data['amount']!, _amountMeta));
    } else if (isInserting) {
      context.missing(_amountMeta);
    }
    if (data.containsKey('classroom')) {
      context.handle(_classroomMeta,
          classroom.isAcceptableOrUnknown(data['classroom']!, _classroomMeta));
    }
    if (data.containsKey('method')) {
      context.handle(_methodMeta,
          method.isAcceptableOrUnknown(data['method']!, _methodMeta));
    }
    if (data.containsKey('invoice_number')) {
      context.handle(
          _invoiceNumberMeta,
          invoiceNumber.isAcceptableOrUnknown(
              data['invoice_number']!, _invoiceNumberMeta));
    }
    if (data.containsKey('status_label')) {
      context.handle(
          _statusLabelMeta,
          statusLabel.isAcceptableOrUnknown(
              data['status_label']!, _statusLabelMeta));
    }
    if (data.containsKey('paid_at')) {
      context.handle(_paidAtMeta,
          paidAt.isAcceptableOrUnknown(data['paid_at']!, _paidAtMeta));
    }
    if (data.containsKey('can_verify')) {
      context.handle(_canVerifyMeta,
          canVerify.isAcceptableOrUnknown(data['can_verify']!, _canVerifyMeta));
    }
    if (data.containsKey('synced_at')) {
      context.handle(_syncedAtMeta,
          syncedAt.isAcceptableOrUnknown(data['synced_at']!, _syncedAtMeta));
    } else if (isInserting) {
      context.missing(_syncedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  PaymentEntryRow map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return PaymentEntryRow(
      id: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}id'])!,
      studentName: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}student_name'])!,
      amount: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}amount'])!,
      classroom: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}classroom']),
      method: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}method']),
      invoiceNumber: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}invoice_number']),
      statusLabel: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}status_label']),
      paidAt: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}paid_at']),
      canVerify: attachedDatabase.typeMapping
          .read(DriftSqlType.bool, data['${effectivePrefix}can_verify'])!,
      syncedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}synced_at'])!,
    );
  }

  @override
  $PaymentEntriesTable createAlias(String alias) {
    return $PaymentEntriesTable(attachedDatabase, alias);
  }
}

class PaymentEntryRow extends DataClass implements Insertable<PaymentEntryRow> {
  final String id;
  final String studentName;
  final String amount;
  final String? classroom;
  final String? method;
  final String? invoiceNumber;
  final String? statusLabel;
  final String? paidAt;
  final bool canVerify;
  final DateTime syncedAt;
  const PaymentEntryRow(
      {required this.id,
      required this.studentName,
      required this.amount,
      this.classroom,
      this.method,
      this.invoiceNumber,
      this.statusLabel,
      this.paidAt,
      required this.canVerify,
      required this.syncedAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['student_name'] = Variable<String>(studentName);
    map['amount'] = Variable<String>(amount);
    if (!nullToAbsent || classroom != null) {
      map['classroom'] = Variable<String>(classroom);
    }
    if (!nullToAbsent || method != null) {
      map['method'] = Variable<String>(method);
    }
    if (!nullToAbsent || invoiceNumber != null) {
      map['invoice_number'] = Variable<String>(invoiceNumber);
    }
    if (!nullToAbsent || statusLabel != null) {
      map['status_label'] = Variable<String>(statusLabel);
    }
    if (!nullToAbsent || paidAt != null) {
      map['paid_at'] = Variable<String>(paidAt);
    }
    map['can_verify'] = Variable<bool>(canVerify);
    map['synced_at'] = Variable<DateTime>(syncedAt);
    return map;
  }

  PaymentEntriesCompanion toCompanion(bool nullToAbsent) {
    return PaymentEntriesCompanion(
      id: Value(id),
      studentName: Value(studentName),
      amount: Value(amount),
      classroom: classroom == null && nullToAbsent
          ? const Value.absent()
          : Value(classroom),
      method:
          method == null && nullToAbsent ? const Value.absent() : Value(method),
      invoiceNumber: invoiceNumber == null && nullToAbsent
          ? const Value.absent()
          : Value(invoiceNumber),
      statusLabel: statusLabel == null && nullToAbsent
          ? const Value.absent()
          : Value(statusLabel),
      paidAt:
          paidAt == null && nullToAbsent ? const Value.absent() : Value(paidAt),
      canVerify: Value(canVerify),
      syncedAt: Value(syncedAt),
    );
  }

  factory PaymentEntryRow.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return PaymentEntryRow(
      id: serializer.fromJson<String>(json['id']),
      studentName: serializer.fromJson<String>(json['studentName']),
      amount: serializer.fromJson<String>(json['amount']),
      classroom: serializer.fromJson<String?>(json['classroom']),
      method: serializer.fromJson<String?>(json['method']),
      invoiceNumber: serializer.fromJson<String?>(json['invoiceNumber']),
      statusLabel: serializer.fromJson<String?>(json['statusLabel']),
      paidAt: serializer.fromJson<String?>(json['paidAt']),
      canVerify: serializer.fromJson<bool>(json['canVerify']),
      syncedAt: serializer.fromJson<DateTime>(json['syncedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'studentName': serializer.toJson<String>(studentName),
      'amount': serializer.toJson<String>(amount),
      'classroom': serializer.toJson<String?>(classroom),
      'method': serializer.toJson<String?>(method),
      'invoiceNumber': serializer.toJson<String?>(invoiceNumber),
      'statusLabel': serializer.toJson<String?>(statusLabel),
      'paidAt': serializer.toJson<String?>(paidAt),
      'canVerify': serializer.toJson<bool>(canVerify),
      'syncedAt': serializer.toJson<DateTime>(syncedAt),
    };
  }

  PaymentEntryRow copyWith(
          {String? id,
          String? studentName,
          String? amount,
          Value<String?> classroom = const Value.absent(),
          Value<String?> method = const Value.absent(),
          Value<String?> invoiceNumber = const Value.absent(),
          Value<String?> statusLabel = const Value.absent(),
          Value<String?> paidAt = const Value.absent(),
          bool? canVerify,
          DateTime? syncedAt}) =>
      PaymentEntryRow(
        id: id ?? this.id,
        studentName: studentName ?? this.studentName,
        amount: amount ?? this.amount,
        classroom: classroom.present ? classroom.value : this.classroom,
        method: method.present ? method.value : this.method,
        invoiceNumber:
            invoiceNumber.present ? invoiceNumber.value : this.invoiceNumber,
        statusLabel: statusLabel.present ? statusLabel.value : this.statusLabel,
        paidAt: paidAt.present ? paidAt.value : this.paidAt,
        canVerify: canVerify ?? this.canVerify,
        syncedAt: syncedAt ?? this.syncedAt,
      );
  PaymentEntryRow copyWithCompanion(PaymentEntriesCompanion data) {
    return PaymentEntryRow(
      id: data.id.present ? data.id.value : this.id,
      studentName:
          data.studentName.present ? data.studentName.value : this.studentName,
      amount: data.amount.present ? data.amount.value : this.amount,
      classroom: data.classroom.present ? data.classroom.value : this.classroom,
      method: data.method.present ? data.method.value : this.method,
      invoiceNumber: data.invoiceNumber.present
          ? data.invoiceNumber.value
          : this.invoiceNumber,
      statusLabel:
          data.statusLabel.present ? data.statusLabel.value : this.statusLabel,
      paidAt: data.paidAt.present ? data.paidAt.value : this.paidAt,
      canVerify: data.canVerify.present ? data.canVerify.value : this.canVerify,
      syncedAt: data.syncedAt.present ? data.syncedAt.value : this.syncedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('PaymentEntryRow(')
          ..write('id: $id, ')
          ..write('studentName: $studentName, ')
          ..write('amount: $amount, ')
          ..write('classroom: $classroom, ')
          ..write('method: $method, ')
          ..write('invoiceNumber: $invoiceNumber, ')
          ..write('statusLabel: $statusLabel, ')
          ..write('paidAt: $paidAt, ')
          ..write('canVerify: $canVerify, ')
          ..write('syncedAt: $syncedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, studentName, amount, classroom, method,
      invoiceNumber, statusLabel, paidAt, canVerify, syncedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is PaymentEntryRow &&
          other.id == this.id &&
          other.studentName == this.studentName &&
          other.amount == this.amount &&
          other.classroom == this.classroom &&
          other.method == this.method &&
          other.invoiceNumber == this.invoiceNumber &&
          other.statusLabel == this.statusLabel &&
          other.paidAt == this.paidAt &&
          other.canVerify == this.canVerify &&
          other.syncedAt == this.syncedAt);
}

class PaymentEntriesCompanion extends UpdateCompanion<PaymentEntryRow> {
  final Value<String> id;
  final Value<String> studentName;
  final Value<String> amount;
  final Value<String?> classroom;
  final Value<String?> method;
  final Value<String?> invoiceNumber;
  final Value<String?> statusLabel;
  final Value<String?> paidAt;
  final Value<bool> canVerify;
  final Value<DateTime> syncedAt;
  final Value<int> rowid;
  const PaymentEntriesCompanion({
    this.id = const Value.absent(),
    this.studentName = const Value.absent(),
    this.amount = const Value.absent(),
    this.classroom = const Value.absent(),
    this.method = const Value.absent(),
    this.invoiceNumber = const Value.absent(),
    this.statusLabel = const Value.absent(),
    this.paidAt = const Value.absent(),
    this.canVerify = const Value.absent(),
    this.syncedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  PaymentEntriesCompanion.insert({
    required String id,
    required String studentName,
    required String amount,
    this.classroom = const Value.absent(),
    this.method = const Value.absent(),
    this.invoiceNumber = const Value.absent(),
    this.statusLabel = const Value.absent(),
    this.paidAt = const Value.absent(),
    this.canVerify = const Value.absent(),
    required DateTime syncedAt,
    this.rowid = const Value.absent(),
  })  : id = Value(id),
        studentName = Value(studentName),
        amount = Value(amount),
        syncedAt = Value(syncedAt);
  static Insertable<PaymentEntryRow> custom({
    Expression<String>? id,
    Expression<String>? studentName,
    Expression<String>? amount,
    Expression<String>? classroom,
    Expression<String>? method,
    Expression<String>? invoiceNumber,
    Expression<String>? statusLabel,
    Expression<String>? paidAt,
    Expression<bool>? canVerify,
    Expression<DateTime>? syncedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (studentName != null) 'student_name': studentName,
      if (amount != null) 'amount': amount,
      if (classroom != null) 'classroom': classroom,
      if (method != null) 'method': method,
      if (invoiceNumber != null) 'invoice_number': invoiceNumber,
      if (statusLabel != null) 'status_label': statusLabel,
      if (paidAt != null) 'paid_at': paidAt,
      if (canVerify != null) 'can_verify': canVerify,
      if (syncedAt != null) 'synced_at': syncedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  PaymentEntriesCompanion copyWith(
      {Value<String>? id,
      Value<String>? studentName,
      Value<String>? amount,
      Value<String?>? classroom,
      Value<String?>? method,
      Value<String?>? invoiceNumber,
      Value<String?>? statusLabel,
      Value<String?>? paidAt,
      Value<bool>? canVerify,
      Value<DateTime>? syncedAt,
      Value<int>? rowid}) {
    return PaymentEntriesCompanion(
      id: id ?? this.id,
      studentName: studentName ?? this.studentName,
      amount: amount ?? this.amount,
      classroom: classroom ?? this.classroom,
      method: method ?? this.method,
      invoiceNumber: invoiceNumber ?? this.invoiceNumber,
      statusLabel: statusLabel ?? this.statusLabel,
      paidAt: paidAt ?? this.paidAt,
      canVerify: canVerify ?? this.canVerify,
      syncedAt: syncedAt ?? this.syncedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (studentName.present) {
      map['student_name'] = Variable<String>(studentName.value);
    }
    if (amount.present) {
      map['amount'] = Variable<String>(amount.value);
    }
    if (classroom.present) {
      map['classroom'] = Variable<String>(classroom.value);
    }
    if (method.present) {
      map['method'] = Variable<String>(method.value);
    }
    if (invoiceNumber.present) {
      map['invoice_number'] = Variable<String>(invoiceNumber.value);
    }
    if (statusLabel.present) {
      map['status_label'] = Variable<String>(statusLabel.value);
    }
    if (paidAt.present) {
      map['paid_at'] = Variable<String>(paidAt.value);
    }
    if (canVerify.present) {
      map['can_verify'] = Variable<bool>(canVerify.value);
    }
    if (syncedAt.present) {
      map['synced_at'] = Variable<DateTime>(syncedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PaymentEntriesCompanion(')
          ..write('id: $id, ')
          ..write('studentName: $studentName, ')
          ..write('amount: $amount, ')
          ..write('classroom: $classroom, ')
          ..write('method: $method, ')
          ..write('invoiceNumber: $invoiceNumber, ')
          ..write('statusLabel: $statusLabel, ')
          ..write('paidAt: $paidAt, ')
          ..write('canVerify: $canVerify, ')
          ..write('syncedAt: $syncedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $QueuedScansTable extends QueuedScans
    with TableInfo<$QueuedScansTable, QueuedScanRow> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $QueuedScansTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
      'id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _uniqueCodeMeta =
      const VerificationMeta('uniqueCode');
  @override
  late final GeneratedColumn<String> uniqueCode = GeneratedColumn<String>(
      'unique_code', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _waktuMeta = const VerificationMeta('waktu');
  @override
  late final GeneratedColumn<String> waktu = GeneratedColumn<String>(
      'waktu', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _scannedAtMeta =
      const VerificationMeta('scannedAt');
  @override
  late final GeneratedColumn<DateTime> scannedAt = GeneratedColumn<DateTime>(
      'scanned_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  static const VerificationMeta _latitudeMeta =
      const VerificationMeta('latitude');
  @override
  late final GeneratedColumn<double> latitude = GeneratedColumn<double>(
      'latitude', aliasedName, true,
      type: DriftSqlType.double, requiredDuringInsert: false);
  static const VerificationMeta _longitudeMeta =
      const VerificationMeta('longitude');
  @override
  late final GeneratedColumn<double> longitude = GeneratedColumn<double>(
      'longitude', aliasedName, true,
      type: DriftSqlType.double, requiredDuringInsert: false);
  @override
  late final GeneratedColumnWithTypeConverter<SyncStatus, String> syncStatus =
      GeneratedColumn<String>('sync_status', aliasedName, false,
              type: DriftSqlType.string,
              requiredDuringInsert: false,
              defaultValue: const Constant('pending'))
          .withConverter<SyncStatus>($QueuedScansTable.$convertersyncStatus);
  static const VerificationMeta _syncErrorMeta =
      const VerificationMeta('syncError');
  @override
  late final GeneratedColumn<String> syncError = GeneratedColumn<String>(
      'sync_error', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _syncAttemptsMeta =
      const VerificationMeta('syncAttempts');
  @override
  late final GeneratedColumn<int> syncAttempts = GeneratedColumn<int>(
      'sync_attempts', aliasedName, false,
      type: DriftSqlType.int,
      requiredDuringInsert: false,
      defaultValue: const Constant(0));
  @override
  List<GeneratedColumn> get $columns => [
        id,
        uniqueCode,
        waktu,
        scannedAt,
        latitude,
        longitude,
        syncStatus,
        syncError,
        syncAttempts
      ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'queued_scans';
  @override
  VerificationContext validateIntegrity(Insertable<QueuedScanRow> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('unique_code')) {
      context.handle(
          _uniqueCodeMeta,
          uniqueCode.isAcceptableOrUnknown(
              data['unique_code']!, _uniqueCodeMeta));
    } else if (isInserting) {
      context.missing(_uniqueCodeMeta);
    }
    if (data.containsKey('waktu')) {
      context.handle(
          _waktuMeta, waktu.isAcceptableOrUnknown(data['waktu']!, _waktuMeta));
    } else if (isInserting) {
      context.missing(_waktuMeta);
    }
    if (data.containsKey('scanned_at')) {
      context.handle(_scannedAtMeta,
          scannedAt.isAcceptableOrUnknown(data['scanned_at']!, _scannedAtMeta));
    } else if (isInserting) {
      context.missing(_scannedAtMeta);
    }
    if (data.containsKey('latitude')) {
      context.handle(_latitudeMeta,
          latitude.isAcceptableOrUnknown(data['latitude']!, _latitudeMeta));
    }
    if (data.containsKey('longitude')) {
      context.handle(_longitudeMeta,
          longitude.isAcceptableOrUnknown(data['longitude']!, _longitudeMeta));
    }
    if (data.containsKey('sync_error')) {
      context.handle(_syncErrorMeta,
          syncError.isAcceptableOrUnknown(data['sync_error']!, _syncErrorMeta));
    }
    if (data.containsKey('sync_attempts')) {
      context.handle(
          _syncAttemptsMeta,
          syncAttempts.isAcceptableOrUnknown(
              data['sync_attempts']!, _syncAttemptsMeta));
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  QueuedScanRow map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return QueuedScanRow(
      id: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}id'])!,
      uniqueCode: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}unique_code'])!,
      waktu: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}waktu'])!,
      scannedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}scanned_at'])!,
      latitude: attachedDatabase.typeMapping
          .read(DriftSqlType.double, data['${effectivePrefix}latitude']),
      longitude: attachedDatabase.typeMapping
          .read(DriftSqlType.double, data['${effectivePrefix}longitude']),
      syncStatus: $QueuedScansTable.$convertersyncStatus.fromSql(
          attachedDatabase.typeMapping.read(
              DriftSqlType.string, data['${effectivePrefix}sync_status'])!),
      syncError: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}sync_error']),
      syncAttempts: attachedDatabase.typeMapping
          .read(DriftSqlType.int, data['${effectivePrefix}sync_attempts'])!,
    );
  }

  @override
  $QueuedScansTable createAlias(String alias) {
    return $QueuedScansTable(attachedDatabase, alias);
  }

  static TypeConverter<SyncStatus, String> $convertersyncStatus =
      const SyncStatusConverter();
}

class QueuedScanRow extends DataClass implements Insertable<QueuedScanRow> {
  final String id;
  final String uniqueCode;
  final String waktu;
  final DateTime scannedAt;
  final double? latitude;
  final double? longitude;
  final SyncStatus syncStatus;
  final String? syncError;
  final int syncAttempts;
  const QueuedScanRow(
      {required this.id,
      required this.uniqueCode,
      required this.waktu,
      required this.scannedAt,
      this.latitude,
      this.longitude,
      required this.syncStatus,
      this.syncError,
      required this.syncAttempts});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['unique_code'] = Variable<String>(uniqueCode);
    map['waktu'] = Variable<String>(waktu);
    map['scanned_at'] = Variable<DateTime>(scannedAt);
    if (!nullToAbsent || latitude != null) {
      map['latitude'] = Variable<double>(latitude);
    }
    if (!nullToAbsent || longitude != null) {
      map['longitude'] = Variable<double>(longitude);
    }
    {
      map['sync_status'] = Variable<String>(
          $QueuedScansTable.$convertersyncStatus.toSql(syncStatus));
    }
    if (!nullToAbsent || syncError != null) {
      map['sync_error'] = Variable<String>(syncError);
    }
    map['sync_attempts'] = Variable<int>(syncAttempts);
    return map;
  }

  QueuedScansCompanion toCompanion(bool nullToAbsent) {
    return QueuedScansCompanion(
      id: Value(id),
      uniqueCode: Value(uniqueCode),
      waktu: Value(waktu),
      scannedAt: Value(scannedAt),
      latitude: latitude == null && nullToAbsent
          ? const Value.absent()
          : Value(latitude),
      longitude: longitude == null && nullToAbsent
          ? const Value.absent()
          : Value(longitude),
      syncStatus: Value(syncStatus),
      syncError: syncError == null && nullToAbsent
          ? const Value.absent()
          : Value(syncError),
      syncAttempts: Value(syncAttempts),
    );
  }

  factory QueuedScanRow.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return QueuedScanRow(
      id: serializer.fromJson<String>(json['id']),
      uniqueCode: serializer.fromJson<String>(json['uniqueCode']),
      waktu: serializer.fromJson<String>(json['waktu']),
      scannedAt: serializer.fromJson<DateTime>(json['scannedAt']),
      latitude: serializer.fromJson<double?>(json['latitude']),
      longitude: serializer.fromJson<double?>(json['longitude']),
      syncStatus: serializer.fromJson<SyncStatus>(json['syncStatus']),
      syncError: serializer.fromJson<String?>(json['syncError']),
      syncAttempts: serializer.fromJson<int>(json['syncAttempts']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'uniqueCode': serializer.toJson<String>(uniqueCode),
      'waktu': serializer.toJson<String>(waktu),
      'scannedAt': serializer.toJson<DateTime>(scannedAt),
      'latitude': serializer.toJson<double?>(latitude),
      'longitude': serializer.toJson<double?>(longitude),
      'syncStatus': serializer.toJson<SyncStatus>(syncStatus),
      'syncError': serializer.toJson<String?>(syncError),
      'syncAttempts': serializer.toJson<int>(syncAttempts),
    };
  }

  QueuedScanRow copyWith(
          {String? id,
          String? uniqueCode,
          String? waktu,
          DateTime? scannedAt,
          Value<double?> latitude = const Value.absent(),
          Value<double?> longitude = const Value.absent(),
          SyncStatus? syncStatus,
          Value<String?> syncError = const Value.absent(),
          int? syncAttempts}) =>
      QueuedScanRow(
        id: id ?? this.id,
        uniqueCode: uniqueCode ?? this.uniqueCode,
        waktu: waktu ?? this.waktu,
        scannedAt: scannedAt ?? this.scannedAt,
        latitude: latitude.present ? latitude.value : this.latitude,
        longitude: longitude.present ? longitude.value : this.longitude,
        syncStatus: syncStatus ?? this.syncStatus,
        syncError: syncError.present ? syncError.value : this.syncError,
        syncAttempts: syncAttempts ?? this.syncAttempts,
      );
  QueuedScanRow copyWithCompanion(QueuedScansCompanion data) {
    return QueuedScanRow(
      id: data.id.present ? data.id.value : this.id,
      uniqueCode:
          data.uniqueCode.present ? data.uniqueCode.value : this.uniqueCode,
      waktu: data.waktu.present ? data.waktu.value : this.waktu,
      scannedAt: data.scannedAt.present ? data.scannedAt.value : this.scannedAt,
      latitude: data.latitude.present ? data.latitude.value : this.latitude,
      longitude: data.longitude.present ? data.longitude.value : this.longitude,
      syncStatus:
          data.syncStatus.present ? data.syncStatus.value : this.syncStatus,
      syncError: data.syncError.present ? data.syncError.value : this.syncError,
      syncAttempts: data.syncAttempts.present
          ? data.syncAttempts.value
          : this.syncAttempts,
    );
  }

  @override
  String toString() {
    return (StringBuffer('QueuedScanRow(')
          ..write('id: $id, ')
          ..write('uniqueCode: $uniqueCode, ')
          ..write('waktu: $waktu, ')
          ..write('scannedAt: $scannedAt, ')
          ..write('latitude: $latitude, ')
          ..write('longitude: $longitude, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('syncError: $syncError, ')
          ..write('syncAttempts: $syncAttempts')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, uniqueCode, waktu, scannedAt, latitude,
      longitude, syncStatus, syncError, syncAttempts);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is QueuedScanRow &&
          other.id == this.id &&
          other.uniqueCode == this.uniqueCode &&
          other.waktu == this.waktu &&
          other.scannedAt == this.scannedAt &&
          other.latitude == this.latitude &&
          other.longitude == this.longitude &&
          other.syncStatus == this.syncStatus &&
          other.syncError == this.syncError &&
          other.syncAttempts == this.syncAttempts);
}

class QueuedScansCompanion extends UpdateCompanion<QueuedScanRow> {
  final Value<String> id;
  final Value<String> uniqueCode;
  final Value<String> waktu;
  final Value<DateTime> scannedAt;
  final Value<double?> latitude;
  final Value<double?> longitude;
  final Value<SyncStatus> syncStatus;
  final Value<String?> syncError;
  final Value<int> syncAttempts;
  final Value<int> rowid;
  const QueuedScansCompanion({
    this.id = const Value.absent(),
    this.uniqueCode = const Value.absent(),
    this.waktu = const Value.absent(),
    this.scannedAt = const Value.absent(),
    this.latitude = const Value.absent(),
    this.longitude = const Value.absent(),
    this.syncStatus = const Value.absent(),
    this.syncError = const Value.absent(),
    this.syncAttempts = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  QueuedScansCompanion.insert({
    required String id,
    required String uniqueCode,
    required String waktu,
    required DateTime scannedAt,
    this.latitude = const Value.absent(),
    this.longitude = const Value.absent(),
    this.syncStatus = const Value.absent(),
    this.syncError = const Value.absent(),
    this.syncAttempts = const Value.absent(),
    this.rowid = const Value.absent(),
  })  : id = Value(id),
        uniqueCode = Value(uniqueCode),
        waktu = Value(waktu),
        scannedAt = Value(scannedAt);
  static Insertable<QueuedScanRow> custom({
    Expression<String>? id,
    Expression<String>? uniqueCode,
    Expression<String>? waktu,
    Expression<DateTime>? scannedAt,
    Expression<double>? latitude,
    Expression<double>? longitude,
    Expression<String>? syncStatus,
    Expression<String>? syncError,
    Expression<int>? syncAttempts,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (uniqueCode != null) 'unique_code': uniqueCode,
      if (waktu != null) 'waktu': waktu,
      if (scannedAt != null) 'scanned_at': scannedAt,
      if (latitude != null) 'latitude': latitude,
      if (longitude != null) 'longitude': longitude,
      if (syncStatus != null) 'sync_status': syncStatus,
      if (syncError != null) 'sync_error': syncError,
      if (syncAttempts != null) 'sync_attempts': syncAttempts,
      if (rowid != null) 'rowid': rowid,
    });
  }

  QueuedScansCompanion copyWith(
      {Value<String>? id,
      Value<String>? uniqueCode,
      Value<String>? waktu,
      Value<DateTime>? scannedAt,
      Value<double?>? latitude,
      Value<double?>? longitude,
      Value<SyncStatus>? syncStatus,
      Value<String?>? syncError,
      Value<int>? syncAttempts,
      Value<int>? rowid}) {
    return QueuedScansCompanion(
      id: id ?? this.id,
      uniqueCode: uniqueCode ?? this.uniqueCode,
      waktu: waktu ?? this.waktu,
      scannedAt: scannedAt ?? this.scannedAt,
      latitude: latitude ?? this.latitude,
      longitude: longitude ?? this.longitude,
      syncStatus: syncStatus ?? this.syncStatus,
      syncError: syncError ?? this.syncError,
      syncAttempts: syncAttempts ?? this.syncAttempts,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (uniqueCode.present) {
      map['unique_code'] = Variable<String>(uniqueCode.value);
    }
    if (waktu.present) {
      map['waktu'] = Variable<String>(waktu.value);
    }
    if (scannedAt.present) {
      map['scanned_at'] = Variable<DateTime>(scannedAt.value);
    }
    if (latitude.present) {
      map['latitude'] = Variable<double>(latitude.value);
    }
    if (longitude.present) {
      map['longitude'] = Variable<double>(longitude.value);
    }
    if (syncStatus.present) {
      map['sync_status'] = Variable<String>(
          $QueuedScansTable.$convertersyncStatus.toSql(syncStatus.value));
    }
    if (syncError.present) {
      map['sync_error'] = Variable<String>(syncError.value);
    }
    if (syncAttempts.present) {
      map['sync_attempts'] = Variable<int>(syncAttempts.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('QueuedScansCompanion(')
          ..write('id: $id, ')
          ..write('uniqueCode: $uniqueCode, ')
          ..write('waktu: $waktu, ')
          ..write('scannedAt: $scannedAt, ')
          ..write('latitude: $latitude, ')
          ..write('longitude: $longitude, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('syncError: $syncError, ')
          ..write('syncAttempts: $syncAttempts, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $QueuedClassAttendancesTable extends QueuedClassAttendances
    with TableInfo<$QueuedClassAttendancesTable, QueuedClassAttendanceRow> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $QueuedClassAttendancesTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
      'id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _classroomIdMeta =
      const VerificationMeta('classroomId');
  @override
  late final GeneratedColumn<String> classroomId = GeneratedColumn<String>(
      'classroom_id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _classroomNameMeta =
      const VerificationMeta('classroomName');
  @override
  late final GeneratedColumn<String> classroomName = GeneratedColumn<String>(
      'classroom_name', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _dateMeta = const VerificationMeta('date');
  @override
  late final GeneratedColumn<DateTime> date = GeneratedColumn<DateTime>(
      'date', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  static const VerificationMeta _marksJsonMeta =
      const VerificationMeta('marksJson');
  @override
  late final GeneratedColumn<String> marksJson = GeneratedColumn<String>(
      'marks_json', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _savedAtMeta =
      const VerificationMeta('savedAt');
  @override
  late final GeneratedColumn<DateTime> savedAt = GeneratedColumn<DateTime>(
      'saved_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  late final GeneratedColumnWithTypeConverter<SyncStatus, String> syncStatus =
      GeneratedColumn<String>('sync_status', aliasedName, false,
              type: DriftSqlType.string,
              requiredDuringInsert: false,
              defaultValue: const Constant('pending'))
          .withConverter<SyncStatus>(
              $QueuedClassAttendancesTable.$convertersyncStatus);
  static const VerificationMeta _syncErrorMeta =
      const VerificationMeta('syncError');
  @override
  late final GeneratedColumn<String> syncError = GeneratedColumn<String>(
      'sync_error', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  @override
  List<GeneratedColumn> get $columns => [
        id,
        classroomId,
        classroomName,
        date,
        marksJson,
        savedAt,
        syncStatus,
        syncError
      ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'queued_class_attendances';
  @override
  VerificationContext validateIntegrity(
      Insertable<QueuedClassAttendanceRow> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('classroom_id')) {
      context.handle(
          _classroomIdMeta,
          classroomId.isAcceptableOrUnknown(
              data['classroom_id']!, _classroomIdMeta));
    } else if (isInserting) {
      context.missing(_classroomIdMeta);
    }
    if (data.containsKey('classroom_name')) {
      context.handle(
          _classroomNameMeta,
          classroomName.isAcceptableOrUnknown(
              data['classroom_name']!, _classroomNameMeta));
    } else if (isInserting) {
      context.missing(_classroomNameMeta);
    }
    if (data.containsKey('date')) {
      context.handle(
          _dateMeta, date.isAcceptableOrUnknown(data['date']!, _dateMeta));
    } else if (isInserting) {
      context.missing(_dateMeta);
    }
    if (data.containsKey('marks_json')) {
      context.handle(_marksJsonMeta,
          marksJson.isAcceptableOrUnknown(data['marks_json']!, _marksJsonMeta));
    } else if (isInserting) {
      context.missing(_marksJsonMeta);
    }
    if (data.containsKey('saved_at')) {
      context.handle(_savedAtMeta,
          savedAt.isAcceptableOrUnknown(data['saved_at']!, _savedAtMeta));
    } else if (isInserting) {
      context.missing(_savedAtMeta);
    }
    if (data.containsKey('sync_error')) {
      context.handle(_syncErrorMeta,
          syncError.isAcceptableOrUnknown(data['sync_error']!, _syncErrorMeta));
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  QueuedClassAttendanceRow map(Map<String, dynamic> data,
      {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return QueuedClassAttendanceRow(
      id: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}id'])!,
      classroomId: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}classroom_id'])!,
      classroomName: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}classroom_name'])!,
      date: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}date'])!,
      marksJson: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}marks_json'])!,
      savedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}saved_at'])!,
      syncStatus: $QueuedClassAttendancesTable.$convertersyncStatus.fromSql(
          attachedDatabase.typeMapping.read(
              DriftSqlType.string, data['${effectivePrefix}sync_status'])!),
      syncError: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}sync_error']),
    );
  }

  @override
  $QueuedClassAttendancesTable createAlias(String alias) {
    return $QueuedClassAttendancesTable(attachedDatabase, alias);
  }

  static TypeConverter<SyncStatus, String> $convertersyncStatus =
      const SyncStatusConverter();
}

class QueuedClassAttendanceRow extends DataClass
    implements Insertable<QueuedClassAttendanceRow> {
  final String id;
  final String classroomId;
  final String classroomName;
  final DateTime date;
  final String marksJson;
  final DateTime savedAt;
  final SyncStatus syncStatus;
  final String? syncError;
  const QueuedClassAttendanceRow(
      {required this.id,
      required this.classroomId,
      required this.classroomName,
      required this.date,
      required this.marksJson,
      required this.savedAt,
      required this.syncStatus,
      this.syncError});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['classroom_id'] = Variable<String>(classroomId);
    map['classroom_name'] = Variable<String>(classroomName);
    map['date'] = Variable<DateTime>(date);
    map['marks_json'] = Variable<String>(marksJson);
    map['saved_at'] = Variable<DateTime>(savedAt);
    {
      map['sync_status'] = Variable<String>(
          $QueuedClassAttendancesTable.$convertersyncStatus.toSql(syncStatus));
    }
    if (!nullToAbsent || syncError != null) {
      map['sync_error'] = Variable<String>(syncError);
    }
    return map;
  }

  QueuedClassAttendancesCompanion toCompanion(bool nullToAbsent) {
    return QueuedClassAttendancesCompanion(
      id: Value(id),
      classroomId: Value(classroomId),
      classroomName: Value(classroomName),
      date: Value(date),
      marksJson: Value(marksJson),
      savedAt: Value(savedAt),
      syncStatus: Value(syncStatus),
      syncError: syncError == null && nullToAbsent
          ? const Value.absent()
          : Value(syncError),
    );
  }

  factory QueuedClassAttendanceRow.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return QueuedClassAttendanceRow(
      id: serializer.fromJson<String>(json['id']),
      classroomId: serializer.fromJson<String>(json['classroomId']),
      classroomName: serializer.fromJson<String>(json['classroomName']),
      date: serializer.fromJson<DateTime>(json['date']),
      marksJson: serializer.fromJson<String>(json['marksJson']),
      savedAt: serializer.fromJson<DateTime>(json['savedAt']),
      syncStatus: serializer.fromJson<SyncStatus>(json['syncStatus']),
      syncError: serializer.fromJson<String?>(json['syncError']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'classroomId': serializer.toJson<String>(classroomId),
      'classroomName': serializer.toJson<String>(classroomName),
      'date': serializer.toJson<DateTime>(date),
      'marksJson': serializer.toJson<String>(marksJson),
      'savedAt': serializer.toJson<DateTime>(savedAt),
      'syncStatus': serializer.toJson<SyncStatus>(syncStatus),
      'syncError': serializer.toJson<String?>(syncError),
    };
  }

  QueuedClassAttendanceRow copyWith(
          {String? id,
          String? classroomId,
          String? classroomName,
          DateTime? date,
          String? marksJson,
          DateTime? savedAt,
          SyncStatus? syncStatus,
          Value<String?> syncError = const Value.absent()}) =>
      QueuedClassAttendanceRow(
        id: id ?? this.id,
        classroomId: classroomId ?? this.classroomId,
        classroomName: classroomName ?? this.classroomName,
        date: date ?? this.date,
        marksJson: marksJson ?? this.marksJson,
        savedAt: savedAt ?? this.savedAt,
        syncStatus: syncStatus ?? this.syncStatus,
        syncError: syncError.present ? syncError.value : this.syncError,
      );
  QueuedClassAttendanceRow copyWithCompanion(
      QueuedClassAttendancesCompanion data) {
    return QueuedClassAttendanceRow(
      id: data.id.present ? data.id.value : this.id,
      classroomId:
          data.classroomId.present ? data.classroomId.value : this.classroomId,
      classroomName: data.classroomName.present
          ? data.classroomName.value
          : this.classroomName,
      date: data.date.present ? data.date.value : this.date,
      marksJson: data.marksJson.present ? data.marksJson.value : this.marksJson,
      savedAt: data.savedAt.present ? data.savedAt.value : this.savedAt,
      syncStatus:
          data.syncStatus.present ? data.syncStatus.value : this.syncStatus,
      syncError: data.syncError.present ? data.syncError.value : this.syncError,
    );
  }

  @override
  String toString() {
    return (StringBuffer('QueuedClassAttendanceRow(')
          ..write('id: $id, ')
          ..write('classroomId: $classroomId, ')
          ..write('classroomName: $classroomName, ')
          ..write('date: $date, ')
          ..write('marksJson: $marksJson, ')
          ..write('savedAt: $savedAt, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('syncError: $syncError')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, classroomId, classroomName, date,
      marksJson, savedAt, syncStatus, syncError);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is QueuedClassAttendanceRow &&
          other.id == this.id &&
          other.classroomId == this.classroomId &&
          other.classroomName == this.classroomName &&
          other.date == this.date &&
          other.marksJson == this.marksJson &&
          other.savedAt == this.savedAt &&
          other.syncStatus == this.syncStatus &&
          other.syncError == this.syncError);
}

class QueuedClassAttendancesCompanion
    extends UpdateCompanion<QueuedClassAttendanceRow> {
  final Value<String> id;
  final Value<String> classroomId;
  final Value<String> classroomName;
  final Value<DateTime> date;
  final Value<String> marksJson;
  final Value<DateTime> savedAt;
  final Value<SyncStatus> syncStatus;
  final Value<String?> syncError;
  final Value<int> rowid;
  const QueuedClassAttendancesCompanion({
    this.id = const Value.absent(),
    this.classroomId = const Value.absent(),
    this.classroomName = const Value.absent(),
    this.date = const Value.absent(),
    this.marksJson = const Value.absent(),
    this.savedAt = const Value.absent(),
    this.syncStatus = const Value.absent(),
    this.syncError = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  QueuedClassAttendancesCompanion.insert({
    required String id,
    required String classroomId,
    required String classroomName,
    required DateTime date,
    required String marksJson,
    required DateTime savedAt,
    this.syncStatus = const Value.absent(),
    this.syncError = const Value.absent(),
    this.rowid = const Value.absent(),
  })  : id = Value(id),
        classroomId = Value(classroomId),
        classroomName = Value(classroomName),
        date = Value(date),
        marksJson = Value(marksJson),
        savedAt = Value(savedAt);
  static Insertable<QueuedClassAttendanceRow> custom({
    Expression<String>? id,
    Expression<String>? classroomId,
    Expression<String>? classroomName,
    Expression<DateTime>? date,
    Expression<String>? marksJson,
    Expression<DateTime>? savedAt,
    Expression<String>? syncStatus,
    Expression<String>? syncError,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (classroomId != null) 'classroom_id': classroomId,
      if (classroomName != null) 'classroom_name': classroomName,
      if (date != null) 'date': date,
      if (marksJson != null) 'marks_json': marksJson,
      if (savedAt != null) 'saved_at': savedAt,
      if (syncStatus != null) 'sync_status': syncStatus,
      if (syncError != null) 'sync_error': syncError,
      if (rowid != null) 'rowid': rowid,
    });
  }

  QueuedClassAttendancesCompanion copyWith(
      {Value<String>? id,
      Value<String>? classroomId,
      Value<String>? classroomName,
      Value<DateTime>? date,
      Value<String>? marksJson,
      Value<DateTime>? savedAt,
      Value<SyncStatus>? syncStatus,
      Value<String?>? syncError,
      Value<int>? rowid}) {
    return QueuedClassAttendancesCompanion(
      id: id ?? this.id,
      classroomId: classroomId ?? this.classroomId,
      classroomName: classroomName ?? this.classroomName,
      date: date ?? this.date,
      marksJson: marksJson ?? this.marksJson,
      savedAt: savedAt ?? this.savedAt,
      syncStatus: syncStatus ?? this.syncStatus,
      syncError: syncError ?? this.syncError,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (classroomId.present) {
      map['classroom_id'] = Variable<String>(classroomId.value);
    }
    if (classroomName.present) {
      map['classroom_name'] = Variable<String>(classroomName.value);
    }
    if (date.present) {
      map['date'] = Variable<DateTime>(date.value);
    }
    if (marksJson.present) {
      map['marks_json'] = Variable<String>(marksJson.value);
    }
    if (savedAt.present) {
      map['saved_at'] = Variable<DateTime>(savedAt.value);
    }
    if (syncStatus.present) {
      map['sync_status'] = Variable<String>($QueuedClassAttendancesTable
          .$convertersyncStatus
          .toSql(syncStatus.value));
    }
    if (syncError.present) {
      map['sync_error'] = Variable<String>(syncError.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('QueuedClassAttendancesCompanion(')
          ..write('id: $id, ')
          ..write('classroomId: $classroomId, ')
          ..write('classroomName: $classroomName, ')
          ..write('date: $date, ')
          ..write('marksJson: $marksJson, ')
          ..write('savedAt: $savedAt, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('syncError: $syncError, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $PendingActionsTable extends PendingActions
    with TableInfo<$PendingActionsTable, PendingAction> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $PendingActionsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
      'id', aliasedName, false,
      hasAutoIncrement: true,
      type: DriftSqlType.int,
      requiredDuringInsert: false,
      defaultConstraints:
          GeneratedColumn.constraintIsAlways('PRIMARY KEY AUTOINCREMENT'));
  static const VerificationMeta _actionTypeMeta =
      const VerificationMeta('actionType');
  @override
  late final GeneratedColumn<String> actionType = GeneratedColumn<String>(
      'action_type', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _payloadJsonMeta =
      const VerificationMeta('payloadJson');
  @override
  late final GeneratedColumn<String> payloadJson = GeneratedColumn<String>(
      'payload_json', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _createdAtMeta =
      const VerificationMeta('createdAt');
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
      'created_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  late final GeneratedColumnWithTypeConverter<SyncStatus, String> syncStatus =
      GeneratedColumn<String>('sync_status', aliasedName, false,
              type: DriftSqlType.string,
              requiredDuringInsert: false,
              defaultValue: const Constant('pending'))
          .withConverter<SyncStatus>($PendingActionsTable.$convertersyncStatus);
  static const VerificationMeta _syncErrorMeta =
      const VerificationMeta('syncError');
  @override
  late final GeneratedColumn<String> syncError = GeneratedColumn<String>(
      'sync_error', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _syncAttemptsMeta =
      const VerificationMeta('syncAttempts');
  @override
  late final GeneratedColumn<int> syncAttempts = GeneratedColumn<int>(
      'sync_attempts', aliasedName, false,
      type: DriftSqlType.int,
      requiredDuringInsert: false,
      defaultValue: const Constant(0));
  @override
  List<GeneratedColumn> get $columns => [
        id,
        actionType,
        payloadJson,
        createdAt,
        syncStatus,
        syncError,
        syncAttempts
      ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'pending_actions';
  @override
  VerificationContext validateIntegrity(Insertable<PendingAction> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('action_type')) {
      context.handle(
          _actionTypeMeta,
          actionType.isAcceptableOrUnknown(
              data['action_type']!, _actionTypeMeta));
    } else if (isInserting) {
      context.missing(_actionTypeMeta);
    }
    if (data.containsKey('payload_json')) {
      context.handle(
          _payloadJsonMeta,
          payloadJson.isAcceptableOrUnknown(
              data['payload_json']!, _payloadJsonMeta));
    } else if (isInserting) {
      context.missing(_payloadJsonMeta);
    }
    if (data.containsKey('created_at')) {
      context.handle(_createdAtMeta,
          createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta));
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    if (data.containsKey('sync_error')) {
      context.handle(_syncErrorMeta,
          syncError.isAcceptableOrUnknown(data['sync_error']!, _syncErrorMeta));
    }
    if (data.containsKey('sync_attempts')) {
      context.handle(
          _syncAttemptsMeta,
          syncAttempts.isAcceptableOrUnknown(
              data['sync_attempts']!, _syncAttemptsMeta));
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  PendingAction map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return PendingAction(
      id: attachedDatabase.typeMapping
          .read(DriftSqlType.int, data['${effectivePrefix}id'])!,
      actionType: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}action_type'])!,
      payloadJson: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}payload_json'])!,
      createdAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}created_at'])!,
      syncStatus: $PendingActionsTable.$convertersyncStatus.fromSql(
          attachedDatabase.typeMapping.read(
              DriftSqlType.string, data['${effectivePrefix}sync_status'])!),
      syncError: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}sync_error']),
      syncAttempts: attachedDatabase.typeMapping
          .read(DriftSqlType.int, data['${effectivePrefix}sync_attempts'])!,
    );
  }

  @override
  $PendingActionsTable createAlias(String alias) {
    return $PendingActionsTable(attachedDatabase, alias);
  }

  static TypeConverter<SyncStatus, String> $convertersyncStatus =
      const SyncStatusConverter();
}

class PendingAction extends DataClass implements Insertable<PendingAction> {
  final int id;
  final String actionType;
  final String payloadJson;
  final DateTime createdAt;
  final SyncStatus syncStatus;
  final String? syncError;
  final int syncAttempts;
  const PendingAction(
      {required this.id,
      required this.actionType,
      required this.payloadJson,
      required this.createdAt,
      required this.syncStatus,
      this.syncError,
      required this.syncAttempts});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['action_type'] = Variable<String>(actionType);
    map['payload_json'] = Variable<String>(payloadJson);
    map['created_at'] = Variable<DateTime>(createdAt);
    {
      map['sync_status'] = Variable<String>(
          $PendingActionsTable.$convertersyncStatus.toSql(syncStatus));
    }
    if (!nullToAbsent || syncError != null) {
      map['sync_error'] = Variable<String>(syncError);
    }
    map['sync_attempts'] = Variable<int>(syncAttempts);
    return map;
  }

  PendingActionsCompanion toCompanion(bool nullToAbsent) {
    return PendingActionsCompanion(
      id: Value(id),
      actionType: Value(actionType),
      payloadJson: Value(payloadJson),
      createdAt: Value(createdAt),
      syncStatus: Value(syncStatus),
      syncError: syncError == null && nullToAbsent
          ? const Value.absent()
          : Value(syncError),
      syncAttempts: Value(syncAttempts),
    );
  }

  factory PendingAction.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return PendingAction(
      id: serializer.fromJson<int>(json['id']),
      actionType: serializer.fromJson<String>(json['actionType']),
      payloadJson: serializer.fromJson<String>(json['payloadJson']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
      syncStatus: serializer.fromJson<SyncStatus>(json['syncStatus']),
      syncError: serializer.fromJson<String?>(json['syncError']),
      syncAttempts: serializer.fromJson<int>(json['syncAttempts']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'actionType': serializer.toJson<String>(actionType),
      'payloadJson': serializer.toJson<String>(payloadJson),
      'createdAt': serializer.toJson<DateTime>(createdAt),
      'syncStatus': serializer.toJson<SyncStatus>(syncStatus),
      'syncError': serializer.toJson<String?>(syncError),
      'syncAttempts': serializer.toJson<int>(syncAttempts),
    };
  }

  PendingAction copyWith(
          {int? id,
          String? actionType,
          String? payloadJson,
          DateTime? createdAt,
          SyncStatus? syncStatus,
          Value<String?> syncError = const Value.absent(),
          int? syncAttempts}) =>
      PendingAction(
        id: id ?? this.id,
        actionType: actionType ?? this.actionType,
        payloadJson: payloadJson ?? this.payloadJson,
        createdAt: createdAt ?? this.createdAt,
        syncStatus: syncStatus ?? this.syncStatus,
        syncError: syncError.present ? syncError.value : this.syncError,
        syncAttempts: syncAttempts ?? this.syncAttempts,
      );
  PendingAction copyWithCompanion(PendingActionsCompanion data) {
    return PendingAction(
      id: data.id.present ? data.id.value : this.id,
      actionType:
          data.actionType.present ? data.actionType.value : this.actionType,
      payloadJson:
          data.payloadJson.present ? data.payloadJson.value : this.payloadJson,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      syncStatus:
          data.syncStatus.present ? data.syncStatus.value : this.syncStatus,
      syncError: data.syncError.present ? data.syncError.value : this.syncError,
      syncAttempts: data.syncAttempts.present
          ? data.syncAttempts.value
          : this.syncAttempts,
    );
  }

  @override
  String toString() {
    return (StringBuffer('PendingAction(')
          ..write('id: $id, ')
          ..write('actionType: $actionType, ')
          ..write('payloadJson: $payloadJson, ')
          ..write('createdAt: $createdAt, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('syncError: $syncError, ')
          ..write('syncAttempts: $syncAttempts')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(id, actionType, payloadJson, createdAt,
      syncStatus, syncError, syncAttempts);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is PendingAction &&
          other.id == this.id &&
          other.actionType == this.actionType &&
          other.payloadJson == this.payloadJson &&
          other.createdAt == this.createdAt &&
          other.syncStatus == this.syncStatus &&
          other.syncError == this.syncError &&
          other.syncAttempts == this.syncAttempts);
}

class PendingActionsCompanion extends UpdateCompanion<PendingAction> {
  final Value<int> id;
  final Value<String> actionType;
  final Value<String> payloadJson;
  final Value<DateTime> createdAt;
  final Value<SyncStatus> syncStatus;
  final Value<String?> syncError;
  final Value<int> syncAttempts;
  const PendingActionsCompanion({
    this.id = const Value.absent(),
    this.actionType = const Value.absent(),
    this.payloadJson = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.syncStatus = const Value.absent(),
    this.syncError = const Value.absent(),
    this.syncAttempts = const Value.absent(),
  });
  PendingActionsCompanion.insert({
    this.id = const Value.absent(),
    required String actionType,
    required String payloadJson,
    required DateTime createdAt,
    this.syncStatus = const Value.absent(),
    this.syncError = const Value.absent(),
    this.syncAttempts = const Value.absent(),
  })  : actionType = Value(actionType),
        payloadJson = Value(payloadJson),
        createdAt = Value(createdAt);
  static Insertable<PendingAction> custom({
    Expression<int>? id,
    Expression<String>? actionType,
    Expression<String>? payloadJson,
    Expression<DateTime>? createdAt,
    Expression<String>? syncStatus,
    Expression<String>? syncError,
    Expression<int>? syncAttempts,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (actionType != null) 'action_type': actionType,
      if (payloadJson != null) 'payload_json': payloadJson,
      if (createdAt != null) 'created_at': createdAt,
      if (syncStatus != null) 'sync_status': syncStatus,
      if (syncError != null) 'sync_error': syncError,
      if (syncAttempts != null) 'sync_attempts': syncAttempts,
    });
  }

  PendingActionsCompanion copyWith(
      {Value<int>? id,
      Value<String>? actionType,
      Value<String>? payloadJson,
      Value<DateTime>? createdAt,
      Value<SyncStatus>? syncStatus,
      Value<String?>? syncError,
      Value<int>? syncAttempts}) {
    return PendingActionsCompanion(
      id: id ?? this.id,
      actionType: actionType ?? this.actionType,
      payloadJson: payloadJson ?? this.payloadJson,
      createdAt: createdAt ?? this.createdAt,
      syncStatus: syncStatus ?? this.syncStatus,
      syncError: syncError ?? this.syncError,
      syncAttempts: syncAttempts ?? this.syncAttempts,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (actionType.present) {
      map['action_type'] = Variable<String>(actionType.value);
    }
    if (payloadJson.present) {
      map['payload_json'] = Variable<String>(payloadJson.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (syncStatus.present) {
      map['sync_status'] = Variable<String>(
          $PendingActionsTable.$convertersyncStatus.toSql(syncStatus.value));
    }
    if (syncError.present) {
      map['sync_error'] = Variable<String>(syncError.value);
    }
    if (syncAttempts.present) {
      map['sync_attempts'] = Variable<int>(syncAttempts.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('PendingActionsCompanion(')
          ..write('id: $id, ')
          ..write('actionType: $actionType, ')
          ..write('payloadJson: $payloadJson, ')
          ..write('createdAt: $createdAt, ')
          ..write('syncStatus: $syncStatus, ')
          ..write('syncError: $syncError, ')
          ..write('syncAttempts: $syncAttempts')
          ..write(')'))
        .toString();
  }
}

class $SyncMetadataTable extends SyncMetadata
    with TableInfo<$SyncMetadataTable, SyncMetadataData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $SyncMetadataTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _entityTypeMeta =
      const VerificationMeta('entityType');
  @override
  late final GeneratedColumn<String> entityType = GeneratedColumn<String>(
      'entity_type', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _lastSyncedAtMeta =
      const VerificationMeta('lastSyncedAt');
  @override
  late final GeneratedColumn<DateTime> lastSyncedAt = GeneratedColumn<DateTime>(
      'last_synced_at', aliasedName, true,
      type: DriftSqlType.dateTime, requiredDuringInsert: false);
  static const VerificationMeta _lastFullSyncAtMeta =
      const VerificationMeta('lastFullSyncAt');
  @override
  late final GeneratedColumn<DateTime> lastFullSyncAt =
      GeneratedColumn<DateTime>('last_full_sync_at', aliasedName, true,
          type: DriftSqlType.dateTime, requiredDuringInsert: false);
  static const VerificationMeta _syncCursorMeta =
      const VerificationMeta('syncCursor');
  @override
  late final GeneratedColumn<String> syncCursor = GeneratedColumn<String>(
      'sync_cursor', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  @override
  List<GeneratedColumn> get $columns =>
      [entityType, lastSyncedAt, lastFullSyncAt, syncCursor];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'sync_metadata';
  @override
  VerificationContext validateIntegrity(Insertable<SyncMetadataData> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('entity_type')) {
      context.handle(
          _entityTypeMeta,
          entityType.isAcceptableOrUnknown(
              data['entity_type']!, _entityTypeMeta));
    } else if (isInserting) {
      context.missing(_entityTypeMeta);
    }
    if (data.containsKey('last_synced_at')) {
      context.handle(
          _lastSyncedAtMeta,
          lastSyncedAt.isAcceptableOrUnknown(
              data['last_synced_at']!, _lastSyncedAtMeta));
    }
    if (data.containsKey('last_full_sync_at')) {
      context.handle(
          _lastFullSyncAtMeta,
          lastFullSyncAt.isAcceptableOrUnknown(
              data['last_full_sync_at']!, _lastFullSyncAtMeta));
    }
    if (data.containsKey('sync_cursor')) {
      context.handle(
          _syncCursorMeta,
          syncCursor.isAcceptableOrUnknown(
              data['sync_cursor']!, _syncCursorMeta));
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {entityType};
  @override
  SyncMetadataData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return SyncMetadataData(
      entityType: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}entity_type'])!,
      lastSyncedAt: attachedDatabase.typeMapping.read(
          DriftSqlType.dateTime, data['${effectivePrefix}last_synced_at']),
      lastFullSyncAt: attachedDatabase.typeMapping.read(
          DriftSqlType.dateTime, data['${effectivePrefix}last_full_sync_at']),
      syncCursor: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}sync_cursor']),
    );
  }

  @override
  $SyncMetadataTable createAlias(String alias) {
    return $SyncMetadataTable(attachedDatabase, alias);
  }
}

class SyncMetadataData extends DataClass
    implements Insertable<SyncMetadataData> {
  final String entityType;
  final DateTime? lastSyncedAt;
  final DateTime? lastFullSyncAt;
  final String? syncCursor;
  const SyncMetadataData(
      {required this.entityType,
      this.lastSyncedAt,
      this.lastFullSyncAt,
      this.syncCursor});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['entity_type'] = Variable<String>(entityType);
    if (!nullToAbsent || lastSyncedAt != null) {
      map['last_synced_at'] = Variable<DateTime>(lastSyncedAt);
    }
    if (!nullToAbsent || lastFullSyncAt != null) {
      map['last_full_sync_at'] = Variable<DateTime>(lastFullSyncAt);
    }
    if (!nullToAbsent || syncCursor != null) {
      map['sync_cursor'] = Variable<String>(syncCursor);
    }
    return map;
  }

  SyncMetadataCompanion toCompanion(bool nullToAbsent) {
    return SyncMetadataCompanion(
      entityType: Value(entityType),
      lastSyncedAt: lastSyncedAt == null && nullToAbsent
          ? const Value.absent()
          : Value(lastSyncedAt),
      lastFullSyncAt: lastFullSyncAt == null && nullToAbsent
          ? const Value.absent()
          : Value(lastFullSyncAt),
      syncCursor: syncCursor == null && nullToAbsent
          ? const Value.absent()
          : Value(syncCursor),
    );
  }

  factory SyncMetadataData.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return SyncMetadataData(
      entityType: serializer.fromJson<String>(json['entityType']),
      lastSyncedAt: serializer.fromJson<DateTime?>(json['lastSyncedAt']),
      lastFullSyncAt: serializer.fromJson<DateTime?>(json['lastFullSyncAt']),
      syncCursor: serializer.fromJson<String?>(json['syncCursor']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'entityType': serializer.toJson<String>(entityType),
      'lastSyncedAt': serializer.toJson<DateTime?>(lastSyncedAt),
      'lastFullSyncAt': serializer.toJson<DateTime?>(lastFullSyncAt),
      'syncCursor': serializer.toJson<String?>(syncCursor),
    };
  }

  SyncMetadataData copyWith(
          {String? entityType,
          Value<DateTime?> lastSyncedAt = const Value.absent(),
          Value<DateTime?> lastFullSyncAt = const Value.absent(),
          Value<String?> syncCursor = const Value.absent()}) =>
      SyncMetadataData(
        entityType: entityType ?? this.entityType,
        lastSyncedAt:
            lastSyncedAt.present ? lastSyncedAt.value : this.lastSyncedAt,
        lastFullSyncAt:
            lastFullSyncAt.present ? lastFullSyncAt.value : this.lastFullSyncAt,
        syncCursor: syncCursor.present ? syncCursor.value : this.syncCursor,
      );
  SyncMetadataData copyWithCompanion(SyncMetadataCompanion data) {
    return SyncMetadataData(
      entityType:
          data.entityType.present ? data.entityType.value : this.entityType,
      lastSyncedAt: data.lastSyncedAt.present
          ? data.lastSyncedAt.value
          : this.lastSyncedAt,
      lastFullSyncAt: data.lastFullSyncAt.present
          ? data.lastFullSyncAt.value
          : this.lastFullSyncAt,
      syncCursor:
          data.syncCursor.present ? data.syncCursor.value : this.syncCursor,
    );
  }

  @override
  String toString() {
    return (StringBuffer('SyncMetadataData(')
          ..write('entityType: $entityType, ')
          ..write('lastSyncedAt: $lastSyncedAt, ')
          ..write('lastFullSyncAt: $lastFullSyncAt, ')
          ..write('syncCursor: $syncCursor')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode =>
      Object.hash(entityType, lastSyncedAt, lastFullSyncAt, syncCursor);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is SyncMetadataData &&
          other.entityType == this.entityType &&
          other.lastSyncedAt == this.lastSyncedAt &&
          other.lastFullSyncAt == this.lastFullSyncAt &&
          other.syncCursor == this.syncCursor);
}

class SyncMetadataCompanion extends UpdateCompanion<SyncMetadataData> {
  final Value<String> entityType;
  final Value<DateTime?> lastSyncedAt;
  final Value<DateTime?> lastFullSyncAt;
  final Value<String?> syncCursor;
  final Value<int> rowid;
  const SyncMetadataCompanion({
    this.entityType = const Value.absent(),
    this.lastSyncedAt = const Value.absent(),
    this.lastFullSyncAt = const Value.absent(),
    this.syncCursor = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  SyncMetadataCompanion.insert({
    required String entityType,
    this.lastSyncedAt = const Value.absent(),
    this.lastFullSyncAt = const Value.absent(),
    this.syncCursor = const Value.absent(),
    this.rowid = const Value.absent(),
  }) : entityType = Value(entityType);
  static Insertable<SyncMetadataData> custom({
    Expression<String>? entityType,
    Expression<DateTime>? lastSyncedAt,
    Expression<DateTime>? lastFullSyncAt,
    Expression<String>? syncCursor,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (entityType != null) 'entity_type': entityType,
      if (lastSyncedAt != null) 'last_synced_at': lastSyncedAt,
      if (lastFullSyncAt != null) 'last_full_sync_at': lastFullSyncAt,
      if (syncCursor != null) 'sync_cursor': syncCursor,
      if (rowid != null) 'rowid': rowid,
    });
  }

  SyncMetadataCompanion copyWith(
      {Value<String>? entityType,
      Value<DateTime?>? lastSyncedAt,
      Value<DateTime?>? lastFullSyncAt,
      Value<String?>? syncCursor,
      Value<int>? rowid}) {
    return SyncMetadataCompanion(
      entityType: entityType ?? this.entityType,
      lastSyncedAt: lastSyncedAt ?? this.lastSyncedAt,
      lastFullSyncAt: lastFullSyncAt ?? this.lastFullSyncAt,
      syncCursor: syncCursor ?? this.syncCursor,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (entityType.present) {
      map['entity_type'] = Variable<String>(entityType.value);
    }
    if (lastSyncedAt.present) {
      map['last_synced_at'] = Variable<DateTime>(lastSyncedAt.value);
    }
    if (lastFullSyncAt.present) {
      map['last_full_sync_at'] = Variable<DateTime>(lastFullSyncAt.value);
    }
    if (syncCursor.present) {
      map['sync_cursor'] = Variable<String>(syncCursor.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('SyncMetadataCompanion(')
          ..write('entityType: $entityType, ')
          ..write('lastSyncedAt: $lastSyncedAt, ')
          ..write('lastFullSyncAt: $lastFullSyncAt, ')
          ..write('syncCursor: $syncCursor, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $LocalAccountsTable extends LocalAccounts
    with TableInfo<$LocalAccountsTable, LocalAccount> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $LocalAccountsTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<String> id = GeneratedColumn<String>(
      'id', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _emailMeta = const VerificationMeta('email');
  @override
  late final GeneratedColumn<String> email = GeneratedColumn<String>(
      'email', aliasedName, false,
      type: DriftSqlType.string,
      requiredDuringInsert: true,
      defaultConstraints: GeneratedColumn.constraintIsAlways('UNIQUE'));
  static const VerificationMeta _fullNameMeta =
      const VerificationMeta('fullName');
  @override
  late final GeneratedColumn<String> fullName = GeneratedColumn<String>(
      'full_name', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _passwordHashMeta =
      const VerificationMeta('passwordHash');
  @override
  late final GeneratedColumn<String> passwordHash = GeneratedColumn<String>(
      'password_hash', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _passwordSaltMeta =
      const VerificationMeta('passwordSalt');
  @override
  late final GeneratedColumn<String> passwordSalt = GeneratedColumn<String>(
      'password_salt', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _createdAtMeta =
      const VerificationMeta('createdAt');
  @override
  late final GeneratedColumn<DateTime> createdAt = GeneratedColumn<DateTime>(
      'created_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  static const VerificationMeta _updatedAtMeta =
      const VerificationMeta('updatedAt');
  @override
  late final GeneratedColumn<DateTime> updatedAt = GeneratedColumn<DateTime>(
      'updated_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  @override
  List<GeneratedColumn> get $columns =>
      [id, email, fullName, passwordHash, passwordSalt, createdAt, updatedAt];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'local_accounts';
  @override
  VerificationContext validateIntegrity(Insertable<LocalAccount> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    } else if (isInserting) {
      context.missing(_idMeta);
    }
    if (data.containsKey('email')) {
      context.handle(
          _emailMeta, email.isAcceptableOrUnknown(data['email']!, _emailMeta));
    } else if (isInserting) {
      context.missing(_emailMeta);
    }
    if (data.containsKey('full_name')) {
      context.handle(_fullNameMeta,
          fullName.isAcceptableOrUnknown(data['full_name']!, _fullNameMeta));
    } else if (isInserting) {
      context.missing(_fullNameMeta);
    }
    if (data.containsKey('password_hash')) {
      context.handle(
          _passwordHashMeta,
          passwordHash.isAcceptableOrUnknown(
              data['password_hash']!, _passwordHashMeta));
    } else if (isInserting) {
      context.missing(_passwordHashMeta);
    }
    if (data.containsKey('password_salt')) {
      context.handle(
          _passwordSaltMeta,
          passwordSalt.isAcceptableOrUnknown(
              data['password_salt']!, _passwordSaltMeta));
    } else if (isInserting) {
      context.missing(_passwordSaltMeta);
    }
    if (data.containsKey('created_at')) {
      context.handle(_createdAtMeta,
          createdAt.isAcceptableOrUnknown(data['created_at']!, _createdAtMeta));
    } else if (isInserting) {
      context.missing(_createdAtMeta);
    }
    if (data.containsKey('updated_at')) {
      context.handle(_updatedAtMeta,
          updatedAt.isAcceptableOrUnknown(data['updated_at']!, _updatedAtMeta));
    } else if (isInserting) {
      context.missing(_updatedAtMeta);
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  LocalAccount map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return LocalAccount(
      id: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}id'])!,
      email: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}email'])!,
      fullName: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}full_name'])!,
      passwordHash: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}password_hash'])!,
      passwordSalt: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}password_salt'])!,
      createdAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}created_at'])!,
      updatedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}updated_at'])!,
    );
  }

  @override
  $LocalAccountsTable createAlias(String alias) {
    return $LocalAccountsTable(attachedDatabase, alias);
  }
}

class LocalAccount extends DataClass implements Insertable<LocalAccount> {
  final String id;
  final String email;
  final String fullName;
  final String passwordHash;
  final String passwordSalt;
  final DateTime createdAt;
  final DateTime updatedAt;
  const LocalAccount(
      {required this.id,
      required this.email,
      required this.fullName,
      required this.passwordHash,
      required this.passwordSalt,
      required this.createdAt,
      required this.updatedAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<String>(id);
    map['email'] = Variable<String>(email);
    map['full_name'] = Variable<String>(fullName);
    map['password_hash'] = Variable<String>(passwordHash);
    map['password_salt'] = Variable<String>(passwordSalt);
    map['created_at'] = Variable<DateTime>(createdAt);
    map['updated_at'] = Variable<DateTime>(updatedAt);
    return map;
  }

  LocalAccountsCompanion toCompanion(bool nullToAbsent) {
    return LocalAccountsCompanion(
      id: Value(id),
      email: Value(email),
      fullName: Value(fullName),
      passwordHash: Value(passwordHash),
      passwordSalt: Value(passwordSalt),
      createdAt: Value(createdAt),
      updatedAt: Value(updatedAt),
    );
  }

  factory LocalAccount.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return LocalAccount(
      id: serializer.fromJson<String>(json['id']),
      email: serializer.fromJson<String>(json['email']),
      fullName: serializer.fromJson<String>(json['fullName']),
      passwordHash: serializer.fromJson<String>(json['passwordHash']),
      passwordSalt: serializer.fromJson<String>(json['passwordSalt']),
      createdAt: serializer.fromJson<DateTime>(json['createdAt']),
      updatedAt: serializer.fromJson<DateTime>(json['updatedAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<String>(id),
      'email': serializer.toJson<String>(email),
      'fullName': serializer.toJson<String>(fullName),
      'passwordHash': serializer.toJson<String>(passwordHash),
      'passwordSalt': serializer.toJson<String>(passwordSalt),
      'createdAt': serializer.toJson<DateTime>(createdAt),
      'updatedAt': serializer.toJson<DateTime>(updatedAt),
    };
  }

  LocalAccount copyWith(
          {String? id,
          String? email,
          String? fullName,
          String? passwordHash,
          String? passwordSalt,
          DateTime? createdAt,
          DateTime? updatedAt}) =>
      LocalAccount(
        id: id ?? this.id,
        email: email ?? this.email,
        fullName: fullName ?? this.fullName,
        passwordHash: passwordHash ?? this.passwordHash,
        passwordSalt: passwordSalt ?? this.passwordSalt,
        createdAt: createdAt ?? this.createdAt,
        updatedAt: updatedAt ?? this.updatedAt,
      );
  LocalAccount copyWithCompanion(LocalAccountsCompanion data) {
    return LocalAccount(
      id: data.id.present ? data.id.value : this.id,
      email: data.email.present ? data.email.value : this.email,
      fullName: data.fullName.present ? data.fullName.value : this.fullName,
      passwordHash: data.passwordHash.present
          ? data.passwordHash.value
          : this.passwordHash,
      passwordSalt: data.passwordSalt.present
          ? data.passwordSalt.value
          : this.passwordSalt,
      createdAt: data.createdAt.present ? data.createdAt.value : this.createdAt,
      updatedAt: data.updatedAt.present ? data.updatedAt.value : this.updatedAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('LocalAccount(')
          ..write('id: $id, ')
          ..write('email: $email, ')
          ..write('fullName: $fullName, ')
          ..write('passwordHash: $passwordHash, ')
          ..write('passwordSalt: $passwordSalt, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
      id, email, fullName, passwordHash, passwordSalt, createdAt, updatedAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is LocalAccount &&
          other.id == this.id &&
          other.email == this.email &&
          other.fullName == this.fullName &&
          other.passwordHash == this.passwordHash &&
          other.passwordSalt == this.passwordSalt &&
          other.createdAt == this.createdAt &&
          other.updatedAt == this.updatedAt);
}

class LocalAccountsCompanion extends UpdateCompanion<LocalAccount> {
  final Value<String> id;
  final Value<String> email;
  final Value<String> fullName;
  final Value<String> passwordHash;
  final Value<String> passwordSalt;
  final Value<DateTime> createdAt;
  final Value<DateTime> updatedAt;
  final Value<int> rowid;
  const LocalAccountsCompanion({
    this.id = const Value.absent(),
    this.email = const Value.absent(),
    this.fullName = const Value.absent(),
    this.passwordHash = const Value.absent(),
    this.passwordSalt = const Value.absent(),
    this.createdAt = const Value.absent(),
    this.updatedAt = const Value.absent(),
    this.rowid = const Value.absent(),
  });
  LocalAccountsCompanion.insert({
    required String id,
    required String email,
    required String fullName,
    required String passwordHash,
    required String passwordSalt,
    required DateTime createdAt,
    required DateTime updatedAt,
    this.rowid = const Value.absent(),
  })  : id = Value(id),
        email = Value(email),
        fullName = Value(fullName),
        passwordHash = Value(passwordHash),
        passwordSalt = Value(passwordSalt),
        createdAt = Value(createdAt),
        updatedAt = Value(updatedAt);
  static Insertable<LocalAccount> custom({
    Expression<String>? id,
    Expression<String>? email,
    Expression<String>? fullName,
    Expression<String>? passwordHash,
    Expression<String>? passwordSalt,
    Expression<DateTime>? createdAt,
    Expression<DateTime>? updatedAt,
    Expression<int>? rowid,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (email != null) 'email': email,
      if (fullName != null) 'full_name': fullName,
      if (passwordHash != null) 'password_hash': passwordHash,
      if (passwordSalt != null) 'password_salt': passwordSalt,
      if (createdAt != null) 'created_at': createdAt,
      if (updatedAt != null) 'updated_at': updatedAt,
      if (rowid != null) 'rowid': rowid,
    });
  }

  LocalAccountsCompanion copyWith(
      {Value<String>? id,
      Value<String>? email,
      Value<String>? fullName,
      Value<String>? passwordHash,
      Value<String>? passwordSalt,
      Value<DateTime>? createdAt,
      Value<DateTime>? updatedAt,
      Value<int>? rowid}) {
    return LocalAccountsCompanion(
      id: id ?? this.id,
      email: email ?? this.email,
      fullName: fullName ?? this.fullName,
      passwordHash: passwordHash ?? this.passwordHash,
      passwordSalt: passwordSalt ?? this.passwordSalt,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      rowid: rowid ?? this.rowid,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<String>(id.value);
    }
    if (email.present) {
      map['email'] = Variable<String>(email.value);
    }
    if (fullName.present) {
      map['full_name'] = Variable<String>(fullName.value);
    }
    if (passwordHash.present) {
      map['password_hash'] = Variable<String>(passwordHash.value);
    }
    if (passwordSalt.present) {
      map['password_salt'] = Variable<String>(passwordSalt.value);
    }
    if (createdAt.present) {
      map['created_at'] = Variable<DateTime>(createdAt.value);
    }
    if (updatedAt.present) {
      map['updated_at'] = Variable<DateTime>(updatedAt.value);
    }
    if (rowid.present) {
      map['rowid'] = Variable<int>(rowid.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('LocalAccountsCompanion(')
          ..write('id: $id, ')
          ..write('email: $email, ')
          ..write('fullName: $fullName, ')
          ..write('passwordHash: $passwordHash, ')
          ..write('passwordSalt: $passwordSalt, ')
          ..write('createdAt: $createdAt, ')
          ..write('updatedAt: $updatedAt, ')
          ..write('rowid: $rowid')
          ..write(')'))
        .toString();
  }
}

class $BackendConnectionTable extends BackendConnection
    with TableInfo<$BackendConnectionTable, BackendConnectionData> {
  @override
  final GeneratedDatabase attachedDatabase;
  final String? _alias;
  $BackendConnectionTable(this.attachedDatabase, [this._alias]);
  static const VerificationMeta _idMeta = const VerificationMeta('id');
  @override
  late final GeneratedColumn<int> id = GeneratedColumn<int>(
      'id', aliasedName, false,
      type: DriftSqlType.int,
      requiredDuringInsert: false,
      defaultValue: const Constant(1));
  static const VerificationMeta _apiUrlMeta = const VerificationMeta('apiUrl');
  @override
  late final GeneratedColumn<String> apiUrl = GeneratedColumn<String>(
      'api_url', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _syncTokenMeta =
      const VerificationMeta('syncToken');
  @override
  late final GeneratedColumn<String> syncToken = GeneratedColumn<String>(
      'sync_token', aliasedName, false,
      type: DriftSqlType.string, requiredDuringInsert: true);
  static const VerificationMeta _schoolNameMeta =
      const VerificationMeta('schoolName');
  @override
  late final GeneratedColumn<String> schoolName = GeneratedColumn<String>(
      'school_name', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _backendUserIdMeta =
      const VerificationMeta('backendUserId');
  @override
  late final GeneratedColumn<String> backendUserId = GeneratedColumn<String>(
      'backend_user_id', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _backendUserNameMeta =
      const VerificationMeta('backendUserName');
  @override
  late final GeneratedColumn<String> backendUserName = GeneratedColumn<String>(
      'backend_user_name', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _backendUserEmailMeta =
      const VerificationMeta('backendUserEmail');
  @override
  late final GeneratedColumn<String> backendUserEmail = GeneratedColumn<String>(
      'backend_user_email', aliasedName, true,
      type: DriftSqlType.string, requiredDuringInsert: false);
  static const VerificationMeta _permissionsJsonMeta =
      const VerificationMeta('permissionsJson');
  @override
  late final GeneratedColumn<String> permissionsJson = GeneratedColumn<String>(
      'permissions_json', aliasedName, false,
      type: DriftSqlType.string,
      requiredDuringInsert: false,
      defaultValue: const Constant('[]'));
  static const VerificationMeta _connectedAtMeta =
      const VerificationMeta('connectedAt');
  @override
  late final GeneratedColumn<DateTime> connectedAt = GeneratedColumn<DateTime>(
      'connected_at', aliasedName, false,
      type: DriftSqlType.dateTime, requiredDuringInsert: true);
  static const VerificationMeta _lastSyncAtMeta =
      const VerificationMeta('lastSyncAt');
  @override
  late final GeneratedColumn<DateTime> lastSyncAt = GeneratedColumn<DateTime>(
      'last_sync_at', aliasedName, true,
      type: DriftSqlType.dateTime, requiredDuringInsert: false);
  @override
  List<GeneratedColumn> get $columns => [
        id,
        apiUrl,
        syncToken,
        schoolName,
        backendUserId,
        backendUserName,
        backendUserEmail,
        permissionsJson,
        connectedAt,
        lastSyncAt
      ];
  @override
  String get aliasedName => _alias ?? actualTableName;
  @override
  String get actualTableName => $name;
  static const String $name = 'backend_connection';
  @override
  VerificationContext validateIntegrity(
      Insertable<BackendConnectionData> instance,
      {bool isInserting = false}) {
    final context = VerificationContext();
    final data = instance.toColumns(true);
    if (data.containsKey('id')) {
      context.handle(_idMeta, id.isAcceptableOrUnknown(data['id']!, _idMeta));
    }
    if (data.containsKey('api_url')) {
      context.handle(_apiUrlMeta,
          apiUrl.isAcceptableOrUnknown(data['api_url']!, _apiUrlMeta));
    } else if (isInserting) {
      context.missing(_apiUrlMeta);
    }
    if (data.containsKey('sync_token')) {
      context.handle(_syncTokenMeta,
          syncToken.isAcceptableOrUnknown(data['sync_token']!, _syncTokenMeta));
    } else if (isInserting) {
      context.missing(_syncTokenMeta);
    }
    if (data.containsKey('school_name')) {
      context.handle(
          _schoolNameMeta,
          schoolName.isAcceptableOrUnknown(
              data['school_name']!, _schoolNameMeta));
    }
    if (data.containsKey('backend_user_id')) {
      context.handle(
          _backendUserIdMeta,
          backendUserId.isAcceptableOrUnknown(
              data['backend_user_id']!, _backendUserIdMeta));
    }
    if (data.containsKey('backend_user_name')) {
      context.handle(
          _backendUserNameMeta,
          backendUserName.isAcceptableOrUnknown(
              data['backend_user_name']!, _backendUserNameMeta));
    }
    if (data.containsKey('backend_user_email')) {
      context.handle(
          _backendUserEmailMeta,
          backendUserEmail.isAcceptableOrUnknown(
              data['backend_user_email']!, _backendUserEmailMeta));
    }
    if (data.containsKey('permissions_json')) {
      context.handle(
          _permissionsJsonMeta,
          permissionsJson.isAcceptableOrUnknown(
              data['permissions_json']!, _permissionsJsonMeta));
    }
    if (data.containsKey('connected_at')) {
      context.handle(
          _connectedAtMeta,
          connectedAt.isAcceptableOrUnknown(
              data['connected_at']!, _connectedAtMeta));
    } else if (isInserting) {
      context.missing(_connectedAtMeta);
    }
    if (data.containsKey('last_sync_at')) {
      context.handle(
          _lastSyncAtMeta,
          lastSyncAt.isAcceptableOrUnknown(
              data['last_sync_at']!, _lastSyncAtMeta));
    }
    return context;
  }

  @override
  Set<GeneratedColumn> get $primaryKey => {id};
  @override
  BackendConnectionData map(Map<String, dynamic> data, {String? tablePrefix}) {
    final effectivePrefix = tablePrefix != null ? '$tablePrefix.' : '';
    return BackendConnectionData(
      id: attachedDatabase.typeMapping
          .read(DriftSqlType.int, data['${effectivePrefix}id'])!,
      apiUrl: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}api_url'])!,
      syncToken: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}sync_token'])!,
      schoolName: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}school_name']),
      backendUserId: attachedDatabase.typeMapping
          .read(DriftSqlType.string, data['${effectivePrefix}backend_user_id']),
      backendUserName: attachedDatabase.typeMapping.read(
          DriftSqlType.string, data['${effectivePrefix}backend_user_name']),
      backendUserEmail: attachedDatabase.typeMapping.read(
          DriftSqlType.string, data['${effectivePrefix}backend_user_email']),
      permissionsJson: attachedDatabase.typeMapping.read(
          DriftSqlType.string, data['${effectivePrefix}permissions_json'])!,
      connectedAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}connected_at'])!,
      lastSyncAt: attachedDatabase.typeMapping
          .read(DriftSqlType.dateTime, data['${effectivePrefix}last_sync_at']),
    );
  }

  @override
  $BackendConnectionTable createAlias(String alias) {
    return $BackendConnectionTable(attachedDatabase, alias);
  }
}

class BackendConnectionData extends DataClass
    implements Insertable<BackendConnectionData> {
  final int id;
  final String apiUrl;
  final String syncToken;
  final String? schoolName;
  final String? backendUserId;
  final String? backendUserName;
  final String? backendUserEmail;
  final String permissionsJson;
  final DateTime connectedAt;
  final DateTime? lastSyncAt;
  const BackendConnectionData(
      {required this.id,
      required this.apiUrl,
      required this.syncToken,
      this.schoolName,
      this.backendUserId,
      this.backendUserName,
      this.backendUserEmail,
      required this.permissionsJson,
      required this.connectedAt,
      this.lastSyncAt});
  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    map['id'] = Variable<int>(id);
    map['api_url'] = Variable<String>(apiUrl);
    map['sync_token'] = Variable<String>(syncToken);
    if (!nullToAbsent || schoolName != null) {
      map['school_name'] = Variable<String>(schoolName);
    }
    if (!nullToAbsent || backendUserId != null) {
      map['backend_user_id'] = Variable<String>(backendUserId);
    }
    if (!nullToAbsent || backendUserName != null) {
      map['backend_user_name'] = Variable<String>(backendUserName);
    }
    if (!nullToAbsent || backendUserEmail != null) {
      map['backend_user_email'] = Variable<String>(backendUserEmail);
    }
    map['permissions_json'] = Variable<String>(permissionsJson);
    map['connected_at'] = Variable<DateTime>(connectedAt);
    if (!nullToAbsent || lastSyncAt != null) {
      map['last_sync_at'] = Variable<DateTime>(lastSyncAt);
    }
    return map;
  }

  BackendConnectionCompanion toCompanion(bool nullToAbsent) {
    return BackendConnectionCompanion(
      id: Value(id),
      apiUrl: Value(apiUrl),
      syncToken: Value(syncToken),
      schoolName: schoolName == null && nullToAbsent
          ? const Value.absent()
          : Value(schoolName),
      backendUserId: backendUserId == null && nullToAbsent
          ? const Value.absent()
          : Value(backendUserId),
      backendUserName: backendUserName == null && nullToAbsent
          ? const Value.absent()
          : Value(backendUserName),
      backendUserEmail: backendUserEmail == null && nullToAbsent
          ? const Value.absent()
          : Value(backendUserEmail),
      permissionsJson: Value(permissionsJson),
      connectedAt: Value(connectedAt),
      lastSyncAt: lastSyncAt == null && nullToAbsent
          ? const Value.absent()
          : Value(lastSyncAt),
    );
  }

  factory BackendConnectionData.fromJson(Map<String, dynamic> json,
      {ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return BackendConnectionData(
      id: serializer.fromJson<int>(json['id']),
      apiUrl: serializer.fromJson<String>(json['apiUrl']),
      syncToken: serializer.fromJson<String>(json['syncToken']),
      schoolName: serializer.fromJson<String?>(json['schoolName']),
      backendUserId: serializer.fromJson<String?>(json['backendUserId']),
      backendUserName: serializer.fromJson<String?>(json['backendUserName']),
      backendUserEmail: serializer.fromJson<String?>(json['backendUserEmail']),
      permissionsJson: serializer.fromJson<String>(json['permissionsJson']),
      connectedAt: serializer.fromJson<DateTime>(json['connectedAt']),
      lastSyncAt: serializer.fromJson<DateTime?>(json['lastSyncAt']),
    );
  }
  @override
  Map<String, dynamic> toJson({ValueSerializer? serializer}) {
    serializer ??= driftRuntimeOptions.defaultSerializer;
    return <String, dynamic>{
      'id': serializer.toJson<int>(id),
      'apiUrl': serializer.toJson<String>(apiUrl),
      'syncToken': serializer.toJson<String>(syncToken),
      'schoolName': serializer.toJson<String?>(schoolName),
      'backendUserId': serializer.toJson<String?>(backendUserId),
      'backendUserName': serializer.toJson<String?>(backendUserName),
      'backendUserEmail': serializer.toJson<String?>(backendUserEmail),
      'permissionsJson': serializer.toJson<String>(permissionsJson),
      'connectedAt': serializer.toJson<DateTime>(connectedAt),
      'lastSyncAt': serializer.toJson<DateTime?>(lastSyncAt),
    };
  }

  BackendConnectionData copyWith(
          {int? id,
          String? apiUrl,
          String? syncToken,
          Value<String?> schoolName = const Value.absent(),
          Value<String?> backendUserId = const Value.absent(),
          Value<String?> backendUserName = const Value.absent(),
          Value<String?> backendUserEmail = const Value.absent(),
          String? permissionsJson,
          DateTime? connectedAt,
          Value<DateTime?> lastSyncAt = const Value.absent()}) =>
      BackendConnectionData(
        id: id ?? this.id,
        apiUrl: apiUrl ?? this.apiUrl,
        syncToken: syncToken ?? this.syncToken,
        schoolName: schoolName.present ? schoolName.value : this.schoolName,
        backendUserId:
            backendUserId.present ? backendUserId.value : this.backendUserId,
        backendUserName: backendUserName.present
            ? backendUserName.value
            : this.backendUserName,
        backendUserEmail: backendUserEmail.present
            ? backendUserEmail.value
            : this.backendUserEmail,
        permissionsJson: permissionsJson ?? this.permissionsJson,
        connectedAt: connectedAt ?? this.connectedAt,
        lastSyncAt: lastSyncAt.present ? lastSyncAt.value : this.lastSyncAt,
      );
  BackendConnectionData copyWithCompanion(BackendConnectionCompanion data) {
    return BackendConnectionData(
      id: data.id.present ? data.id.value : this.id,
      apiUrl: data.apiUrl.present ? data.apiUrl.value : this.apiUrl,
      syncToken: data.syncToken.present ? data.syncToken.value : this.syncToken,
      schoolName:
          data.schoolName.present ? data.schoolName.value : this.schoolName,
      backendUserId: data.backendUserId.present
          ? data.backendUserId.value
          : this.backendUserId,
      backendUserName: data.backendUserName.present
          ? data.backendUserName.value
          : this.backendUserName,
      backendUserEmail: data.backendUserEmail.present
          ? data.backendUserEmail.value
          : this.backendUserEmail,
      permissionsJson: data.permissionsJson.present
          ? data.permissionsJson.value
          : this.permissionsJson,
      connectedAt:
          data.connectedAt.present ? data.connectedAt.value : this.connectedAt,
      lastSyncAt:
          data.lastSyncAt.present ? data.lastSyncAt.value : this.lastSyncAt,
    );
  }

  @override
  String toString() {
    return (StringBuffer('BackendConnectionData(')
          ..write('id: $id, ')
          ..write('apiUrl: $apiUrl, ')
          ..write('syncToken: $syncToken, ')
          ..write('schoolName: $schoolName, ')
          ..write('backendUserId: $backendUserId, ')
          ..write('backendUserName: $backendUserName, ')
          ..write('backendUserEmail: $backendUserEmail, ')
          ..write('permissionsJson: $permissionsJson, ')
          ..write('connectedAt: $connectedAt, ')
          ..write('lastSyncAt: $lastSyncAt')
          ..write(')'))
        .toString();
  }

  @override
  int get hashCode => Object.hash(
      id,
      apiUrl,
      syncToken,
      schoolName,
      backendUserId,
      backendUserName,
      backendUserEmail,
      permissionsJson,
      connectedAt,
      lastSyncAt);
  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is BackendConnectionData &&
          other.id == this.id &&
          other.apiUrl == this.apiUrl &&
          other.syncToken == this.syncToken &&
          other.schoolName == this.schoolName &&
          other.backendUserId == this.backendUserId &&
          other.backendUserName == this.backendUserName &&
          other.backendUserEmail == this.backendUserEmail &&
          other.permissionsJson == this.permissionsJson &&
          other.connectedAt == this.connectedAt &&
          other.lastSyncAt == this.lastSyncAt);
}

class BackendConnectionCompanion
    extends UpdateCompanion<BackendConnectionData> {
  final Value<int> id;
  final Value<String> apiUrl;
  final Value<String> syncToken;
  final Value<String?> schoolName;
  final Value<String?> backendUserId;
  final Value<String?> backendUserName;
  final Value<String?> backendUserEmail;
  final Value<String> permissionsJson;
  final Value<DateTime> connectedAt;
  final Value<DateTime?> lastSyncAt;
  const BackendConnectionCompanion({
    this.id = const Value.absent(),
    this.apiUrl = const Value.absent(),
    this.syncToken = const Value.absent(),
    this.schoolName = const Value.absent(),
    this.backendUserId = const Value.absent(),
    this.backendUserName = const Value.absent(),
    this.backendUserEmail = const Value.absent(),
    this.permissionsJson = const Value.absent(),
    this.connectedAt = const Value.absent(),
    this.lastSyncAt = const Value.absent(),
  });
  BackendConnectionCompanion.insert({
    this.id = const Value.absent(),
    required String apiUrl,
    required String syncToken,
    this.schoolName = const Value.absent(),
    this.backendUserId = const Value.absent(),
    this.backendUserName = const Value.absent(),
    this.backendUserEmail = const Value.absent(),
    this.permissionsJson = const Value.absent(),
    required DateTime connectedAt,
    this.lastSyncAt = const Value.absent(),
  })  : apiUrl = Value(apiUrl),
        syncToken = Value(syncToken),
        connectedAt = Value(connectedAt);
  static Insertable<BackendConnectionData> custom({
    Expression<int>? id,
    Expression<String>? apiUrl,
    Expression<String>? syncToken,
    Expression<String>? schoolName,
    Expression<String>? backendUserId,
    Expression<String>? backendUserName,
    Expression<String>? backendUserEmail,
    Expression<String>? permissionsJson,
    Expression<DateTime>? connectedAt,
    Expression<DateTime>? lastSyncAt,
  }) {
    return RawValuesInsertable({
      if (id != null) 'id': id,
      if (apiUrl != null) 'api_url': apiUrl,
      if (syncToken != null) 'sync_token': syncToken,
      if (schoolName != null) 'school_name': schoolName,
      if (backendUserId != null) 'backend_user_id': backendUserId,
      if (backendUserName != null) 'backend_user_name': backendUserName,
      if (backendUserEmail != null) 'backend_user_email': backendUserEmail,
      if (permissionsJson != null) 'permissions_json': permissionsJson,
      if (connectedAt != null) 'connected_at': connectedAt,
      if (lastSyncAt != null) 'last_sync_at': lastSyncAt,
    });
  }

  BackendConnectionCompanion copyWith(
      {Value<int>? id,
      Value<String>? apiUrl,
      Value<String>? syncToken,
      Value<String?>? schoolName,
      Value<String?>? backendUserId,
      Value<String?>? backendUserName,
      Value<String?>? backendUserEmail,
      Value<String>? permissionsJson,
      Value<DateTime>? connectedAt,
      Value<DateTime?>? lastSyncAt}) {
    return BackendConnectionCompanion(
      id: id ?? this.id,
      apiUrl: apiUrl ?? this.apiUrl,
      syncToken: syncToken ?? this.syncToken,
      schoolName: schoolName ?? this.schoolName,
      backendUserId: backendUserId ?? this.backendUserId,
      backendUserName: backendUserName ?? this.backendUserName,
      backendUserEmail: backendUserEmail ?? this.backendUserEmail,
      permissionsJson: permissionsJson ?? this.permissionsJson,
      connectedAt: connectedAt ?? this.connectedAt,
      lastSyncAt: lastSyncAt ?? this.lastSyncAt,
    );
  }

  @override
  Map<String, Expression> toColumns(bool nullToAbsent) {
    final map = <String, Expression>{};
    if (id.present) {
      map['id'] = Variable<int>(id.value);
    }
    if (apiUrl.present) {
      map['api_url'] = Variable<String>(apiUrl.value);
    }
    if (syncToken.present) {
      map['sync_token'] = Variable<String>(syncToken.value);
    }
    if (schoolName.present) {
      map['school_name'] = Variable<String>(schoolName.value);
    }
    if (backendUserId.present) {
      map['backend_user_id'] = Variable<String>(backendUserId.value);
    }
    if (backendUserName.present) {
      map['backend_user_name'] = Variable<String>(backendUserName.value);
    }
    if (backendUserEmail.present) {
      map['backend_user_email'] = Variable<String>(backendUserEmail.value);
    }
    if (permissionsJson.present) {
      map['permissions_json'] = Variable<String>(permissionsJson.value);
    }
    if (connectedAt.present) {
      map['connected_at'] = Variable<DateTime>(connectedAt.value);
    }
    if (lastSyncAt.present) {
      map['last_sync_at'] = Variable<DateTime>(lastSyncAt.value);
    }
    return map;
  }

  @override
  String toString() {
    return (StringBuffer('BackendConnectionCompanion(')
          ..write('id: $id, ')
          ..write('apiUrl: $apiUrl, ')
          ..write('syncToken: $syncToken, ')
          ..write('schoolName: $schoolName, ')
          ..write('backendUserId: $backendUserId, ')
          ..write('backendUserName: $backendUserName, ')
          ..write('backendUserEmail: $backendUserEmail, ')
          ..write('permissionsJson: $permissionsJson, ')
          ..write('connectedAt: $connectedAt, ')
          ..write('lastSyncAt: $lastSyncAt')
          ..write(')'))
        .toString();
  }
}

abstract class _$AppDatabase extends GeneratedDatabase {
  _$AppDatabase(QueryExecutor e) : super(e);
  $AppDatabaseManager get managers => $AppDatabaseManager(this);
  late final $CachedUsersTable cachedUsers = $CachedUsersTable(this);
  late final $ClassroomsTable classrooms = $ClassroomsTable(this);
  late final $StudentsTable students = $StudentsTable(this);
  late final $DashboardCacheTable dashboardCache = $DashboardCacheTable(this);
  late final $MyAttendanceCacheTable myAttendanceCache =
      $MyAttendanceCacheTable(this);
  late final $MyAttendanceHistoryTable myAttendanceHistory =
      $MyAttendanceHistoryTable(this);
  late final $CachedNotificationsTable cachedNotifications =
      $CachedNotificationsTable(this);
  late final $FeeSummaryCacheTable feeSummaryCache =
      $FeeSummaryCacheTable(this);
  late final $PaymentEntriesTable paymentEntries = $PaymentEntriesTable(this);
  late final $QueuedScansTable queuedScans = $QueuedScansTable(this);
  late final $QueuedClassAttendancesTable queuedClassAttendances =
      $QueuedClassAttendancesTable(this);
  late final $PendingActionsTable pendingActions = $PendingActionsTable(this);
  late final $SyncMetadataTable syncMetadata = $SyncMetadataTable(this);
  late final $LocalAccountsTable localAccounts = $LocalAccountsTable(this);
  late final $BackendConnectionTable backendConnection =
      $BackendConnectionTable(this);
  @override
  Iterable<TableInfo<Table, Object?>> get allTables =>
      allSchemaEntities.whereType<TableInfo<Table, Object?>>();
  @override
  List<DatabaseSchemaEntity> get allSchemaEntities => [
        cachedUsers,
        classrooms,
        students,
        dashboardCache,
        myAttendanceCache,
        myAttendanceHistory,
        cachedNotifications,
        feeSummaryCache,
        paymentEntries,
        queuedScans,
        queuedClassAttendances,
        pendingActions,
        syncMetadata,
        localAccounts,
        backendConnection
      ];
}

typedef $$CachedUsersTableCreateCompanionBuilder = CachedUsersCompanion
    Function({
  required String id,
  required String email,
  required String fullName,
  Value<String?> username,
  Value<String?> userType,
  Value<String?> avatarUrl,
  Value<String?> tenantId,
  Value<String> rolesJson,
  Value<String> permissionsJson,
  required DateTime syncedAt,
  Value<int> rowid,
});
typedef $$CachedUsersTableUpdateCompanionBuilder = CachedUsersCompanion
    Function({
  Value<String> id,
  Value<String> email,
  Value<String> fullName,
  Value<String?> username,
  Value<String?> userType,
  Value<String?> avatarUrl,
  Value<String?> tenantId,
  Value<String> rolesJson,
  Value<String> permissionsJson,
  Value<DateTime> syncedAt,
  Value<int> rowid,
});

class $$CachedUsersTableFilterComposer
    extends Composer<_$AppDatabase, $CachedUsersTable> {
  $$CachedUsersTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get email => $composableBuilder(
      column: $table.email, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get fullName => $composableBuilder(
      column: $table.fullName, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get username => $composableBuilder(
      column: $table.username, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get userType => $composableBuilder(
      column: $table.userType, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get avatarUrl => $composableBuilder(
      column: $table.avatarUrl, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get tenantId => $composableBuilder(
      column: $table.tenantId, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get rolesJson => $composableBuilder(
      column: $table.rolesJson, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get permissionsJson => $composableBuilder(
      column: $table.permissionsJson,
      builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnFilters(column));
}

class $$CachedUsersTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedUsersTable> {
  $$CachedUsersTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get email => $composableBuilder(
      column: $table.email, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get fullName => $composableBuilder(
      column: $table.fullName, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get username => $composableBuilder(
      column: $table.username, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get userType => $composableBuilder(
      column: $table.userType, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get avatarUrl => $composableBuilder(
      column: $table.avatarUrl, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get tenantId => $composableBuilder(
      column: $table.tenantId, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get rolesJson => $composableBuilder(
      column: $table.rolesJson, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get permissionsJson => $composableBuilder(
      column: $table.permissionsJson,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnOrderings(column));
}

class $$CachedUsersTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedUsersTable> {
  $$CachedUsersTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get email =>
      $composableBuilder(column: $table.email, builder: (column) => column);

  GeneratedColumn<String> get fullName =>
      $composableBuilder(column: $table.fullName, builder: (column) => column);

  GeneratedColumn<String> get username =>
      $composableBuilder(column: $table.username, builder: (column) => column);

  GeneratedColumn<String> get userType =>
      $composableBuilder(column: $table.userType, builder: (column) => column);

  GeneratedColumn<String> get avatarUrl =>
      $composableBuilder(column: $table.avatarUrl, builder: (column) => column);

  GeneratedColumn<String> get tenantId =>
      $composableBuilder(column: $table.tenantId, builder: (column) => column);

  GeneratedColumn<String> get rolesJson =>
      $composableBuilder(column: $table.rolesJson, builder: (column) => column);

  GeneratedColumn<String> get permissionsJson => $composableBuilder(
      column: $table.permissionsJson, builder: (column) => column);

  GeneratedColumn<DateTime> get syncedAt =>
      $composableBuilder(column: $table.syncedAt, builder: (column) => column);
}

class $$CachedUsersTableTableManager extends RootTableManager<
    _$AppDatabase,
    $CachedUsersTable,
    CachedUser,
    $$CachedUsersTableFilterComposer,
    $$CachedUsersTableOrderingComposer,
    $$CachedUsersTableAnnotationComposer,
    $$CachedUsersTableCreateCompanionBuilder,
    $$CachedUsersTableUpdateCompanionBuilder,
    (CachedUser, BaseReferences<_$AppDatabase, $CachedUsersTable, CachedUser>),
    CachedUser,
    PrefetchHooks Function()> {
  $$CachedUsersTableTableManager(_$AppDatabase db, $CachedUsersTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedUsersTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedUsersTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedUsersTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> id = const Value.absent(),
            Value<String> email = const Value.absent(),
            Value<String> fullName = const Value.absent(),
            Value<String?> username = const Value.absent(),
            Value<String?> userType = const Value.absent(),
            Value<String?> avatarUrl = const Value.absent(),
            Value<String?> tenantId = const Value.absent(),
            Value<String> rolesJson = const Value.absent(),
            Value<String> permissionsJson = const Value.absent(),
            Value<DateTime> syncedAt = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              CachedUsersCompanion(
            id: id,
            email: email,
            fullName: fullName,
            username: username,
            userType: userType,
            avatarUrl: avatarUrl,
            tenantId: tenantId,
            rolesJson: rolesJson,
            permissionsJson: permissionsJson,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String id,
            required String email,
            required String fullName,
            Value<String?> username = const Value.absent(),
            Value<String?> userType = const Value.absent(),
            Value<String?> avatarUrl = const Value.absent(),
            Value<String?> tenantId = const Value.absent(),
            Value<String> rolesJson = const Value.absent(),
            Value<String> permissionsJson = const Value.absent(),
            required DateTime syncedAt,
            Value<int> rowid = const Value.absent(),
          }) =>
              CachedUsersCompanion.insert(
            id: id,
            email: email,
            fullName: fullName,
            username: username,
            userType: userType,
            avatarUrl: avatarUrl,
            tenantId: tenantId,
            rolesJson: rolesJson,
            permissionsJson: permissionsJson,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$CachedUsersTable, CachedUser>(table),
                    BaseReferences<_$AppDatabase, $CachedUsersTable,
                        CachedUser>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$CachedUsersTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $CachedUsersTable,
    CachedUser,
    $$CachedUsersTableFilterComposer,
    $$CachedUsersTableOrderingComposer,
    $$CachedUsersTableAnnotationComposer,
    $$CachedUsersTableCreateCompanionBuilder,
    $$CachedUsersTableUpdateCompanionBuilder,
    (CachedUser, BaseReferences<_$AppDatabase, $CachedUsersTable, CachedUser>),
    CachedUser,
    PrefetchHooks Function()>;
typedef $$ClassroomsTableCreateCompanionBuilder = ClassroomsCompanion Function({
  required String id,
  required String name,
  Value<String?> academicYear,
  Value<bool> isActive,
  Value<int> studentsCount,
  required DateTime syncedAt,
  Value<int> rowid,
});
typedef $$ClassroomsTableUpdateCompanionBuilder = ClassroomsCompanion Function({
  Value<String> id,
  Value<String> name,
  Value<String?> academicYear,
  Value<bool> isActive,
  Value<int> studentsCount,
  Value<DateTime> syncedAt,
  Value<int> rowid,
});

final class $$ClassroomsTableReferences
    extends BaseReferences<_$AppDatabase, $ClassroomsTable, Classroom> {
  $$ClassroomsTableReferences(super.$_db, super.$_table, super.$_typedResult);

  static MultiTypedResultKey<$StudentsTable, List<Student>> _studentsRefsTable(
          _$AppDatabase db) =>
      MultiTypedResultKey.fromTable(db.students,
          aliasName: 'classrooms__id__students__classroom_id');

  $$StudentsTableProcessedTableManager get studentsRefs {
    final manager = $$StudentsTableTableManager($_db, $_db.students)
        .filter((f) => f.classroomId.id.sqlEquals($_itemColumn<String>('id')!));

    final cache = $_typedResult.readTableOrNull(_studentsRefsTable($_db));
    return ProcessedTableManager(
        manager.$state.copyWith(prefetchedData: cache));
  }
}

class $$ClassroomsTableFilterComposer
    extends Composer<_$AppDatabase, $ClassroomsTable> {
  $$ClassroomsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get name => $composableBuilder(
      column: $table.name, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get academicYear => $composableBuilder(
      column: $table.academicYear, builder: (column) => ColumnFilters(column));

  ColumnFilters<bool> get isActive => $composableBuilder(
      column: $table.isActive, builder: (column) => ColumnFilters(column));

  ColumnFilters<int> get studentsCount => $composableBuilder(
      column: $table.studentsCount, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnFilters(column));

  Expression<bool> studentsRefs(
      Expression<bool> Function($$StudentsTableFilterComposer f) f) {
    final $$StudentsTableFilterComposer composer = $composerBuilder(
        composer: this,
        getCurrentColumn: (t) => t.id,
        referencedTable: $db.students,
        getReferencedColumn: (t) => t.classroomId,
        builder: (joinBuilder,
                {$addJoinBuilderToRootComposer,
                $removeJoinBuilderFromRootComposer}) =>
            $$StudentsTableFilterComposer(
              $db: $db,
              $table: $db.students,
              $addJoinBuilderToRootComposer: $addJoinBuilderToRootComposer,
              joinBuilder: joinBuilder,
              $removeJoinBuilderFromRootComposer:
                  $removeJoinBuilderFromRootComposer,
            ));
    return f(composer);
  }
}

class $$ClassroomsTableOrderingComposer
    extends Composer<_$AppDatabase, $ClassroomsTable> {
  $$ClassroomsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get name => $composableBuilder(
      column: $table.name, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get academicYear => $composableBuilder(
      column: $table.academicYear,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<bool> get isActive => $composableBuilder(
      column: $table.isActive, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<int> get studentsCount => $composableBuilder(
      column: $table.studentsCount,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnOrderings(column));
}

class $$ClassroomsTableAnnotationComposer
    extends Composer<_$AppDatabase, $ClassroomsTable> {
  $$ClassroomsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get name =>
      $composableBuilder(column: $table.name, builder: (column) => column);

  GeneratedColumn<String> get academicYear => $composableBuilder(
      column: $table.academicYear, builder: (column) => column);

  GeneratedColumn<bool> get isActive =>
      $composableBuilder(column: $table.isActive, builder: (column) => column);

  GeneratedColumn<int> get studentsCount => $composableBuilder(
      column: $table.studentsCount, builder: (column) => column);

  GeneratedColumn<DateTime> get syncedAt =>
      $composableBuilder(column: $table.syncedAt, builder: (column) => column);

  Expression<T> studentsRefs<T extends Object>(
      Expression<T> Function($$StudentsTableAnnotationComposer a) f) {
    final $$StudentsTableAnnotationComposer composer = $composerBuilder(
        composer: this,
        getCurrentColumn: (t) => t.id,
        referencedTable: $db.students,
        getReferencedColumn: (t) => t.classroomId,
        builder: (joinBuilder,
                {$addJoinBuilderToRootComposer,
                $removeJoinBuilderFromRootComposer}) =>
            $$StudentsTableAnnotationComposer(
              $db: $db,
              $table: $db.students,
              $addJoinBuilderToRootComposer: $addJoinBuilderToRootComposer,
              joinBuilder: joinBuilder,
              $removeJoinBuilderFromRootComposer:
                  $removeJoinBuilderFromRootComposer,
            ));
    return f(composer);
  }
}

class $$ClassroomsTableTableManager extends RootTableManager<
    _$AppDatabase,
    $ClassroomsTable,
    Classroom,
    $$ClassroomsTableFilterComposer,
    $$ClassroomsTableOrderingComposer,
    $$ClassroomsTableAnnotationComposer,
    $$ClassroomsTableCreateCompanionBuilder,
    $$ClassroomsTableUpdateCompanionBuilder,
    (Classroom, $$ClassroomsTableReferences),
    Classroom,
    PrefetchHooks Function({bool studentsRefs})> {
  $$ClassroomsTableTableManager(_$AppDatabase db, $ClassroomsTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$ClassroomsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$ClassroomsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$ClassroomsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> id = const Value.absent(),
            Value<String> name = const Value.absent(),
            Value<String?> academicYear = const Value.absent(),
            Value<bool> isActive = const Value.absent(),
            Value<int> studentsCount = const Value.absent(),
            Value<DateTime> syncedAt = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              ClassroomsCompanion(
            id: id,
            name: name,
            academicYear: academicYear,
            isActive: isActive,
            studentsCount: studentsCount,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String id,
            required String name,
            Value<String?> academicYear = const Value.absent(),
            Value<bool> isActive = const Value.absent(),
            Value<int> studentsCount = const Value.absent(),
            required DateTime syncedAt,
            Value<int> rowid = const Value.absent(),
          }) =>
              ClassroomsCompanion.insert(
            id: id,
            name: name,
            academicYear: academicYear,
            isActive: isActive,
            studentsCount: studentsCount,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$ClassroomsTable, Classroom>(table),
                    $$ClassroomsTableReferences(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: ({studentsRefs = false}) {
            return PrefetchHooks(
              db: db,
              explicitlyWatchedTables: [if (studentsRefs) db.students],
              addJoins: null,
              getPrefetchedDataCallback: (items) async {
                return [
                  if (studentsRefs)
                    await $_getPrefetchedData<Classroom, $ClassroomsTable,
                            Student>(
                        currentTable: table,
                        referencedTable:
                            $$ClassroomsTableReferences._studentsRefsTable(db),
                        managerFromTypedResult: (p0) =>
                            $$ClassroomsTableReferences(db, table, p0)
                                .studentsRefs,
                        referencedItemsForCurrentItem:
                            (item, referencedItems) => referencedItems
                                .where((e) => e.classroomId == item.id),
                        typedResults: items)
                ];
              },
            );
          },
        ));
}

typedef $$ClassroomsTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $ClassroomsTable,
    Classroom,
    $$ClassroomsTableFilterComposer,
    $$ClassroomsTableOrderingComposer,
    $$ClassroomsTableAnnotationComposer,
    $$ClassroomsTableCreateCompanionBuilder,
    $$ClassroomsTableUpdateCompanionBuilder,
    (Classroom, $$ClassroomsTableReferences),
    Classroom,
    PrefetchHooks Function({bool studentsRefs})>;
typedef $$StudentsTableCreateCompanionBuilder = StudentsCompanion Function({
  required String id,
  required String classroomId,
  Value<String?> nis,
  required String name,
  required DateTime syncedAt,
  Value<int> rowid,
});
typedef $$StudentsTableUpdateCompanionBuilder = StudentsCompanion Function({
  Value<String> id,
  Value<String> classroomId,
  Value<String?> nis,
  Value<String> name,
  Value<DateTime> syncedAt,
  Value<int> rowid,
});

final class $$StudentsTableReferences
    extends BaseReferences<_$AppDatabase, $StudentsTable, Student> {
  $$StudentsTableReferences(super.$_db, super.$_table, super.$_typedResult);

  static $ClassroomsTable _classroomIdTable(_$AppDatabase db) =>
      db.classrooms.createAlias('students__classroom_id__classrooms__id');

  $$ClassroomsTableProcessedTableManager get classroomId {
    final $_column = $_itemColumn<String>('classroom_id')!;

    final manager = $$ClassroomsTableTableManager($_db, $_db.classrooms)
        .filter((f) => f.id.sqlEquals($_column));
    final item = $_typedResult.readTableOrNull(_classroomIdTable($_db));
    if (item == null) return manager;
    return ProcessedTableManager(
        manager.$state.copyWith(prefetchedData: [item]));
  }
}

class $$StudentsTableFilterComposer
    extends Composer<_$AppDatabase, $StudentsTable> {
  $$StudentsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get nis => $composableBuilder(
      column: $table.nis, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get name => $composableBuilder(
      column: $table.name, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnFilters(column));

  $$ClassroomsTableFilterComposer get classroomId {
    final $$ClassroomsTableFilterComposer composer = $composerBuilder(
        composer: this,
        getCurrentColumn: (t) => t.classroomId,
        referencedTable: $db.classrooms,
        getReferencedColumn: (t) => t.id,
        builder: (joinBuilder,
                {$addJoinBuilderToRootComposer,
                $removeJoinBuilderFromRootComposer}) =>
            $$ClassroomsTableFilterComposer(
              $db: $db,
              $table: $db.classrooms,
              $addJoinBuilderToRootComposer: $addJoinBuilderToRootComposer,
              joinBuilder: joinBuilder,
              $removeJoinBuilderFromRootComposer:
                  $removeJoinBuilderFromRootComposer,
            ));
    return composer;
  }
}

class $$StudentsTableOrderingComposer
    extends Composer<_$AppDatabase, $StudentsTable> {
  $$StudentsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get nis => $composableBuilder(
      column: $table.nis, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get name => $composableBuilder(
      column: $table.name, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnOrderings(column));

  $$ClassroomsTableOrderingComposer get classroomId {
    final $$ClassroomsTableOrderingComposer composer = $composerBuilder(
        composer: this,
        getCurrentColumn: (t) => t.classroomId,
        referencedTable: $db.classrooms,
        getReferencedColumn: (t) => t.id,
        builder: (joinBuilder,
                {$addJoinBuilderToRootComposer,
                $removeJoinBuilderFromRootComposer}) =>
            $$ClassroomsTableOrderingComposer(
              $db: $db,
              $table: $db.classrooms,
              $addJoinBuilderToRootComposer: $addJoinBuilderToRootComposer,
              joinBuilder: joinBuilder,
              $removeJoinBuilderFromRootComposer:
                  $removeJoinBuilderFromRootComposer,
            ));
    return composer;
  }
}

class $$StudentsTableAnnotationComposer
    extends Composer<_$AppDatabase, $StudentsTable> {
  $$StudentsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get nis =>
      $composableBuilder(column: $table.nis, builder: (column) => column);

  GeneratedColumn<String> get name =>
      $composableBuilder(column: $table.name, builder: (column) => column);

  GeneratedColumn<DateTime> get syncedAt =>
      $composableBuilder(column: $table.syncedAt, builder: (column) => column);

  $$ClassroomsTableAnnotationComposer get classroomId {
    final $$ClassroomsTableAnnotationComposer composer = $composerBuilder(
        composer: this,
        getCurrentColumn: (t) => t.classroomId,
        referencedTable: $db.classrooms,
        getReferencedColumn: (t) => t.id,
        builder: (joinBuilder,
                {$addJoinBuilderToRootComposer,
                $removeJoinBuilderFromRootComposer}) =>
            $$ClassroomsTableAnnotationComposer(
              $db: $db,
              $table: $db.classrooms,
              $addJoinBuilderToRootComposer: $addJoinBuilderToRootComposer,
              joinBuilder: joinBuilder,
              $removeJoinBuilderFromRootComposer:
                  $removeJoinBuilderFromRootComposer,
            ));
    return composer;
  }
}

class $$StudentsTableTableManager extends RootTableManager<
    _$AppDatabase,
    $StudentsTable,
    Student,
    $$StudentsTableFilterComposer,
    $$StudentsTableOrderingComposer,
    $$StudentsTableAnnotationComposer,
    $$StudentsTableCreateCompanionBuilder,
    $$StudentsTableUpdateCompanionBuilder,
    (Student, $$StudentsTableReferences),
    Student,
    PrefetchHooks Function({bool classroomId})> {
  $$StudentsTableTableManager(_$AppDatabase db, $StudentsTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$StudentsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$StudentsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$StudentsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> id = const Value.absent(),
            Value<String> classroomId = const Value.absent(),
            Value<String?> nis = const Value.absent(),
            Value<String> name = const Value.absent(),
            Value<DateTime> syncedAt = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              StudentsCompanion(
            id: id,
            classroomId: classroomId,
            nis: nis,
            name: name,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String id,
            required String classroomId,
            Value<String?> nis = const Value.absent(),
            required String name,
            required DateTime syncedAt,
            Value<int> rowid = const Value.absent(),
          }) =>
              StudentsCompanion.insert(
            id: id,
            classroomId: classroomId,
            nis: nis,
            name: name,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$StudentsTable, Student>(table),
                    $$StudentsTableReferences(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: ({classroomId = false}) {
            return PrefetchHooks(
              db: db,
              explicitlyWatchedTables: [],
              addJoins: <
                  T extends TableManagerState<
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic,
                      dynamic>>(state) {
                if (classroomId) {
                  state = state.withJoin(
                    currentTable: table,
                    currentColumn: table.classroomId,
                    referencedTable:
                        $$StudentsTableReferences._classroomIdTable(db),
                    referencedColumn:
                        $$StudentsTableReferences._classroomIdTable(db).id,
                  ) as T;
                }

                return state;
              },
              getPrefetchedDataCallback: (items) async {
                return [];
              },
            );
          },
        ));
}

typedef $$StudentsTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $StudentsTable,
    Student,
    $$StudentsTableFilterComposer,
    $$StudentsTableOrderingComposer,
    $$StudentsTableAnnotationComposer,
    $$StudentsTableCreateCompanionBuilder,
    $$StudentsTableUpdateCompanionBuilder,
    (Student, $$StudentsTableReferences),
    Student,
    PrefetchHooks Function({bool classroomId})>;
typedef $$DashboardCacheTableCreateCompanionBuilder = DashboardCacheCompanion
    Function({
  required String userId,
  required String package,
  required String jsonData,
  required DateTime cachedAt,
  Value<int> rowid,
});
typedef $$DashboardCacheTableUpdateCompanionBuilder = DashboardCacheCompanion
    Function({
  Value<String> userId,
  Value<String> package,
  Value<String> jsonData,
  Value<DateTime> cachedAt,
  Value<int> rowid,
});

class $$DashboardCacheTableFilterComposer
    extends Composer<_$AppDatabase, $DashboardCacheTable> {
  $$DashboardCacheTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get userId => $composableBuilder(
      column: $table.userId, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get package => $composableBuilder(
      column: $table.package, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get jsonData => $composableBuilder(
      column: $table.jsonData, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get cachedAt => $composableBuilder(
      column: $table.cachedAt, builder: (column) => ColumnFilters(column));
}

class $$DashboardCacheTableOrderingComposer
    extends Composer<_$AppDatabase, $DashboardCacheTable> {
  $$DashboardCacheTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get userId => $composableBuilder(
      column: $table.userId, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get package => $composableBuilder(
      column: $table.package, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get jsonData => $composableBuilder(
      column: $table.jsonData, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get cachedAt => $composableBuilder(
      column: $table.cachedAt, builder: (column) => ColumnOrderings(column));
}

class $$DashboardCacheTableAnnotationComposer
    extends Composer<_$AppDatabase, $DashboardCacheTable> {
  $$DashboardCacheTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get userId =>
      $composableBuilder(column: $table.userId, builder: (column) => column);

  GeneratedColumn<String> get package =>
      $composableBuilder(column: $table.package, builder: (column) => column);

  GeneratedColumn<String> get jsonData =>
      $composableBuilder(column: $table.jsonData, builder: (column) => column);

  GeneratedColumn<DateTime> get cachedAt =>
      $composableBuilder(column: $table.cachedAt, builder: (column) => column);
}

class $$DashboardCacheTableTableManager extends RootTableManager<
    _$AppDatabase,
    $DashboardCacheTable,
    DashboardCacheData,
    $$DashboardCacheTableFilterComposer,
    $$DashboardCacheTableOrderingComposer,
    $$DashboardCacheTableAnnotationComposer,
    $$DashboardCacheTableCreateCompanionBuilder,
    $$DashboardCacheTableUpdateCompanionBuilder,
    (
      DashboardCacheData,
      BaseReferences<_$AppDatabase, $DashboardCacheTable, DashboardCacheData>
    ),
    DashboardCacheData,
    PrefetchHooks Function()> {
  $$DashboardCacheTableTableManager(
      _$AppDatabase db, $DashboardCacheTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$DashboardCacheTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$DashboardCacheTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$DashboardCacheTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> userId = const Value.absent(),
            Value<String> package = const Value.absent(),
            Value<String> jsonData = const Value.absent(),
            Value<DateTime> cachedAt = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              DashboardCacheCompanion(
            userId: userId,
            package: package,
            jsonData: jsonData,
            cachedAt: cachedAt,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String userId,
            required String package,
            required String jsonData,
            required DateTime cachedAt,
            Value<int> rowid = const Value.absent(),
          }) =>
              DashboardCacheCompanion.insert(
            userId: userId,
            package: package,
            jsonData: jsonData,
            cachedAt: cachedAt,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$DashboardCacheTable, DashboardCacheData>(
                        table),
                    BaseReferences<_$AppDatabase, $DashboardCacheTable,
                        DashboardCacheData>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$DashboardCacheTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $DashboardCacheTable,
    DashboardCacheData,
    $$DashboardCacheTableFilterComposer,
    $$DashboardCacheTableOrderingComposer,
    $$DashboardCacheTableAnnotationComposer,
    $$DashboardCacheTableCreateCompanionBuilder,
    $$DashboardCacheTableUpdateCompanionBuilder,
    (
      DashboardCacheData,
      BaseReferences<_$AppDatabase, $DashboardCacheTable, DashboardCacheData>
    ),
    DashboardCacheData,
    PrefetchHooks Function()>;
typedef $$MyAttendanceCacheTableCreateCompanionBuilder
    = MyAttendanceCacheCompanion Function({
  required String userId,
  required String date,
  required String status,
  Value<String?> checkInTime,
  Value<String?> checkOutTime,
  Value<int> lateMinutes,
  Value<String?> identityNumber,
  Value<String?> uniqueCode,
  Value<String?> rfidCode,
  Value<String?> dateLabel,
  required DateTime syncedAt,
  Value<int> rowid,
});
typedef $$MyAttendanceCacheTableUpdateCompanionBuilder
    = MyAttendanceCacheCompanion Function({
  Value<String> userId,
  Value<String> date,
  Value<String> status,
  Value<String?> checkInTime,
  Value<String?> checkOutTime,
  Value<int> lateMinutes,
  Value<String?> identityNumber,
  Value<String?> uniqueCode,
  Value<String?> rfidCode,
  Value<String?> dateLabel,
  Value<DateTime> syncedAt,
  Value<int> rowid,
});

class $$MyAttendanceCacheTableFilterComposer
    extends Composer<_$AppDatabase, $MyAttendanceCacheTable> {
  $$MyAttendanceCacheTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get userId => $composableBuilder(
      column: $table.userId, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get date => $composableBuilder(
      column: $table.date, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get status => $composableBuilder(
      column: $table.status, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get checkInTime => $composableBuilder(
      column: $table.checkInTime, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get checkOutTime => $composableBuilder(
      column: $table.checkOutTime, builder: (column) => ColumnFilters(column));

  ColumnFilters<int> get lateMinutes => $composableBuilder(
      column: $table.lateMinutes, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get identityNumber => $composableBuilder(
      column: $table.identityNumber,
      builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get uniqueCode => $composableBuilder(
      column: $table.uniqueCode, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get rfidCode => $composableBuilder(
      column: $table.rfidCode, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get dateLabel => $composableBuilder(
      column: $table.dateLabel, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnFilters(column));
}

class $$MyAttendanceCacheTableOrderingComposer
    extends Composer<_$AppDatabase, $MyAttendanceCacheTable> {
  $$MyAttendanceCacheTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get userId => $composableBuilder(
      column: $table.userId, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get date => $composableBuilder(
      column: $table.date, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get status => $composableBuilder(
      column: $table.status, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get checkInTime => $composableBuilder(
      column: $table.checkInTime, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get checkOutTime => $composableBuilder(
      column: $table.checkOutTime,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<int> get lateMinutes => $composableBuilder(
      column: $table.lateMinutes, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get identityNumber => $composableBuilder(
      column: $table.identityNumber,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get uniqueCode => $composableBuilder(
      column: $table.uniqueCode, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get rfidCode => $composableBuilder(
      column: $table.rfidCode, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get dateLabel => $composableBuilder(
      column: $table.dateLabel, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnOrderings(column));
}

class $$MyAttendanceCacheTableAnnotationComposer
    extends Composer<_$AppDatabase, $MyAttendanceCacheTable> {
  $$MyAttendanceCacheTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get userId =>
      $composableBuilder(column: $table.userId, builder: (column) => column);

  GeneratedColumn<String> get date =>
      $composableBuilder(column: $table.date, builder: (column) => column);

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<String> get checkInTime => $composableBuilder(
      column: $table.checkInTime, builder: (column) => column);

  GeneratedColumn<String> get checkOutTime => $composableBuilder(
      column: $table.checkOutTime, builder: (column) => column);

  GeneratedColumn<int> get lateMinutes => $composableBuilder(
      column: $table.lateMinutes, builder: (column) => column);

  GeneratedColumn<String> get identityNumber => $composableBuilder(
      column: $table.identityNumber, builder: (column) => column);

  GeneratedColumn<String> get uniqueCode => $composableBuilder(
      column: $table.uniqueCode, builder: (column) => column);

  GeneratedColumn<String> get rfidCode =>
      $composableBuilder(column: $table.rfidCode, builder: (column) => column);

  GeneratedColumn<String> get dateLabel =>
      $composableBuilder(column: $table.dateLabel, builder: (column) => column);

  GeneratedColumn<DateTime> get syncedAt =>
      $composableBuilder(column: $table.syncedAt, builder: (column) => column);
}

class $$MyAttendanceCacheTableTableManager extends RootTableManager<
    _$AppDatabase,
    $MyAttendanceCacheTable,
    MyAttendanceCacheData,
    $$MyAttendanceCacheTableFilterComposer,
    $$MyAttendanceCacheTableOrderingComposer,
    $$MyAttendanceCacheTableAnnotationComposer,
    $$MyAttendanceCacheTableCreateCompanionBuilder,
    $$MyAttendanceCacheTableUpdateCompanionBuilder,
    (
      MyAttendanceCacheData,
      BaseReferences<_$AppDatabase, $MyAttendanceCacheTable,
          MyAttendanceCacheData>
    ),
    MyAttendanceCacheData,
    PrefetchHooks Function()> {
  $$MyAttendanceCacheTableTableManager(
      _$AppDatabase db, $MyAttendanceCacheTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$MyAttendanceCacheTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$MyAttendanceCacheTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$MyAttendanceCacheTableAnnotationComposer(
                  $db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> userId = const Value.absent(),
            Value<String> date = const Value.absent(),
            Value<String> status = const Value.absent(),
            Value<String?> checkInTime = const Value.absent(),
            Value<String?> checkOutTime = const Value.absent(),
            Value<int> lateMinutes = const Value.absent(),
            Value<String?> identityNumber = const Value.absent(),
            Value<String?> uniqueCode = const Value.absent(),
            Value<String?> rfidCode = const Value.absent(),
            Value<String?> dateLabel = const Value.absent(),
            Value<DateTime> syncedAt = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              MyAttendanceCacheCompanion(
            userId: userId,
            date: date,
            status: status,
            checkInTime: checkInTime,
            checkOutTime: checkOutTime,
            lateMinutes: lateMinutes,
            identityNumber: identityNumber,
            uniqueCode: uniqueCode,
            rfidCode: rfidCode,
            dateLabel: dateLabel,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String userId,
            required String date,
            required String status,
            Value<String?> checkInTime = const Value.absent(),
            Value<String?> checkOutTime = const Value.absent(),
            Value<int> lateMinutes = const Value.absent(),
            Value<String?> identityNumber = const Value.absent(),
            Value<String?> uniqueCode = const Value.absent(),
            Value<String?> rfidCode = const Value.absent(),
            Value<String?> dateLabel = const Value.absent(),
            required DateTime syncedAt,
            Value<int> rowid = const Value.absent(),
          }) =>
              MyAttendanceCacheCompanion.insert(
            userId: userId,
            date: date,
            status: status,
            checkInTime: checkInTime,
            checkOutTime: checkOutTime,
            lateMinutes: lateMinutes,
            identityNumber: identityNumber,
            uniqueCode: uniqueCode,
            rfidCode: rfidCode,
            dateLabel: dateLabel,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$MyAttendanceCacheTable, MyAttendanceCacheData>(
                        table),
                    BaseReferences<_$AppDatabase, $MyAttendanceCacheTable,
                        MyAttendanceCacheData>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$MyAttendanceCacheTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $MyAttendanceCacheTable,
    MyAttendanceCacheData,
    $$MyAttendanceCacheTableFilterComposer,
    $$MyAttendanceCacheTableOrderingComposer,
    $$MyAttendanceCacheTableAnnotationComposer,
    $$MyAttendanceCacheTableCreateCompanionBuilder,
    $$MyAttendanceCacheTableUpdateCompanionBuilder,
    (
      MyAttendanceCacheData,
      BaseReferences<_$AppDatabase, $MyAttendanceCacheTable,
          MyAttendanceCacheData>
    ),
    MyAttendanceCacheData,
    PrefetchHooks Function()>;
typedef $$MyAttendanceHistoryTableCreateCompanionBuilder
    = MyAttendanceHistoryCompanion Function({
  Value<int> rowId,
  required String userId,
  required String date,
  required String status,
  Value<String?> label,
  required DateTime syncedAt,
});
typedef $$MyAttendanceHistoryTableUpdateCompanionBuilder
    = MyAttendanceHistoryCompanion Function({
  Value<int> rowId,
  Value<String> userId,
  Value<String> date,
  Value<String> status,
  Value<String?> label,
  Value<DateTime> syncedAt,
});

class $$MyAttendanceHistoryTableFilterComposer
    extends Composer<_$AppDatabase, $MyAttendanceHistoryTable> {
  $$MyAttendanceHistoryTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get rowId => $composableBuilder(
      column: $table.rowId, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get userId => $composableBuilder(
      column: $table.userId, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get date => $composableBuilder(
      column: $table.date, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get status => $composableBuilder(
      column: $table.status, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get label => $composableBuilder(
      column: $table.label, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnFilters(column));
}

class $$MyAttendanceHistoryTableOrderingComposer
    extends Composer<_$AppDatabase, $MyAttendanceHistoryTable> {
  $$MyAttendanceHistoryTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get rowId => $composableBuilder(
      column: $table.rowId, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get userId => $composableBuilder(
      column: $table.userId, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get date => $composableBuilder(
      column: $table.date, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get status => $composableBuilder(
      column: $table.status, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get label => $composableBuilder(
      column: $table.label, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnOrderings(column));
}

class $$MyAttendanceHistoryTableAnnotationComposer
    extends Composer<_$AppDatabase, $MyAttendanceHistoryTable> {
  $$MyAttendanceHistoryTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get rowId =>
      $composableBuilder(column: $table.rowId, builder: (column) => column);

  GeneratedColumn<String> get userId =>
      $composableBuilder(column: $table.userId, builder: (column) => column);

  GeneratedColumn<String> get date =>
      $composableBuilder(column: $table.date, builder: (column) => column);

  GeneratedColumn<String> get status =>
      $composableBuilder(column: $table.status, builder: (column) => column);

  GeneratedColumn<String> get label =>
      $composableBuilder(column: $table.label, builder: (column) => column);

  GeneratedColumn<DateTime> get syncedAt =>
      $composableBuilder(column: $table.syncedAt, builder: (column) => column);
}

class $$MyAttendanceHistoryTableTableManager extends RootTableManager<
    _$AppDatabase,
    $MyAttendanceHistoryTable,
    MyAttendanceHistoryData,
    $$MyAttendanceHistoryTableFilterComposer,
    $$MyAttendanceHistoryTableOrderingComposer,
    $$MyAttendanceHistoryTableAnnotationComposer,
    $$MyAttendanceHistoryTableCreateCompanionBuilder,
    $$MyAttendanceHistoryTableUpdateCompanionBuilder,
    (
      MyAttendanceHistoryData,
      BaseReferences<_$AppDatabase, $MyAttendanceHistoryTable,
          MyAttendanceHistoryData>
    ),
    MyAttendanceHistoryData,
    PrefetchHooks Function()> {
  $$MyAttendanceHistoryTableTableManager(
      _$AppDatabase db, $MyAttendanceHistoryTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$MyAttendanceHistoryTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$MyAttendanceHistoryTableOrderingComposer(
                  $db: db, $table: table),
          createComputedFieldComposer: () =>
              $$MyAttendanceHistoryTableAnnotationComposer(
                  $db: db, $table: table),
          updateCompanionCallback: ({
            Value<int> rowId = const Value.absent(),
            Value<String> userId = const Value.absent(),
            Value<String> date = const Value.absent(),
            Value<String> status = const Value.absent(),
            Value<String?> label = const Value.absent(),
            Value<DateTime> syncedAt = const Value.absent(),
          }) =>
              MyAttendanceHistoryCompanion(
            rowId: rowId,
            userId: userId,
            date: date,
            status: status,
            label: label,
            syncedAt: syncedAt,
          ),
          createCompanionCallback: ({
            Value<int> rowId = const Value.absent(),
            required String userId,
            required String date,
            required String status,
            Value<String?> label = const Value.absent(),
            required DateTime syncedAt,
          }) =>
              MyAttendanceHistoryCompanion.insert(
            rowId: rowId,
            userId: userId,
            date: date,
            status: status,
            label: label,
            syncedAt: syncedAt,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$MyAttendanceHistoryTable,
                        MyAttendanceHistoryData>(table),
                    BaseReferences<_$AppDatabase, $MyAttendanceHistoryTable,
                        MyAttendanceHistoryData>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$MyAttendanceHistoryTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $MyAttendanceHistoryTable,
    MyAttendanceHistoryData,
    $$MyAttendanceHistoryTableFilterComposer,
    $$MyAttendanceHistoryTableOrderingComposer,
    $$MyAttendanceHistoryTableAnnotationComposer,
    $$MyAttendanceHistoryTableCreateCompanionBuilder,
    $$MyAttendanceHistoryTableUpdateCompanionBuilder,
    (
      MyAttendanceHistoryData,
      BaseReferences<_$AppDatabase, $MyAttendanceHistoryTable,
          MyAttendanceHistoryData>
    ),
    MyAttendanceHistoryData,
    PrefetchHooks Function()>;
typedef $$CachedNotificationsTableCreateCompanionBuilder
    = CachedNotificationsCompanion Function({
  required String id,
  required String userId,
  required String title,
  required String body,
  Value<bool> isRead,
  Value<DateTime?> createdAt,
  required DateTime syncedAt,
  Value<int> rowid,
});
typedef $$CachedNotificationsTableUpdateCompanionBuilder
    = CachedNotificationsCompanion Function({
  Value<String> id,
  Value<String> userId,
  Value<String> title,
  Value<String> body,
  Value<bool> isRead,
  Value<DateTime?> createdAt,
  Value<DateTime> syncedAt,
  Value<int> rowid,
});

class $$CachedNotificationsTableFilterComposer
    extends Composer<_$AppDatabase, $CachedNotificationsTable> {
  $$CachedNotificationsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get userId => $composableBuilder(
      column: $table.userId, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get title => $composableBuilder(
      column: $table.title, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get body => $composableBuilder(
      column: $table.body, builder: (column) => ColumnFilters(column));

  ColumnFilters<bool> get isRead => $composableBuilder(
      column: $table.isRead, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
      column: $table.createdAt, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnFilters(column));
}

class $$CachedNotificationsTableOrderingComposer
    extends Composer<_$AppDatabase, $CachedNotificationsTable> {
  $$CachedNotificationsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get userId => $composableBuilder(
      column: $table.userId, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get title => $composableBuilder(
      column: $table.title, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get body => $composableBuilder(
      column: $table.body, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<bool> get isRead => $composableBuilder(
      column: $table.isRead, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
      column: $table.createdAt, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnOrderings(column));
}

class $$CachedNotificationsTableAnnotationComposer
    extends Composer<_$AppDatabase, $CachedNotificationsTable> {
  $$CachedNotificationsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get userId =>
      $composableBuilder(column: $table.userId, builder: (column) => column);

  GeneratedColumn<String> get title =>
      $composableBuilder(column: $table.title, builder: (column) => column);

  GeneratedColumn<String> get body =>
      $composableBuilder(column: $table.body, builder: (column) => column);

  GeneratedColumn<bool> get isRead =>
      $composableBuilder(column: $table.isRead, builder: (column) => column);

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<DateTime> get syncedAt =>
      $composableBuilder(column: $table.syncedAt, builder: (column) => column);
}

class $$CachedNotificationsTableTableManager extends RootTableManager<
    _$AppDatabase,
    $CachedNotificationsTable,
    CachedNotification,
    $$CachedNotificationsTableFilterComposer,
    $$CachedNotificationsTableOrderingComposer,
    $$CachedNotificationsTableAnnotationComposer,
    $$CachedNotificationsTableCreateCompanionBuilder,
    $$CachedNotificationsTableUpdateCompanionBuilder,
    (
      CachedNotification,
      BaseReferences<_$AppDatabase, $CachedNotificationsTable,
          CachedNotification>
    ),
    CachedNotification,
    PrefetchHooks Function()> {
  $$CachedNotificationsTableTableManager(
      _$AppDatabase db, $CachedNotificationsTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$CachedNotificationsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$CachedNotificationsTableOrderingComposer(
                  $db: db, $table: table),
          createComputedFieldComposer: () =>
              $$CachedNotificationsTableAnnotationComposer(
                  $db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> id = const Value.absent(),
            Value<String> userId = const Value.absent(),
            Value<String> title = const Value.absent(),
            Value<String> body = const Value.absent(),
            Value<bool> isRead = const Value.absent(),
            Value<DateTime?> createdAt = const Value.absent(),
            Value<DateTime> syncedAt = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              CachedNotificationsCompanion(
            id: id,
            userId: userId,
            title: title,
            body: body,
            isRead: isRead,
            createdAt: createdAt,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String id,
            required String userId,
            required String title,
            required String body,
            Value<bool> isRead = const Value.absent(),
            Value<DateTime?> createdAt = const Value.absent(),
            required DateTime syncedAt,
            Value<int> rowid = const Value.absent(),
          }) =>
              CachedNotificationsCompanion.insert(
            id: id,
            userId: userId,
            title: title,
            body: body,
            isRead: isRead,
            createdAt: createdAt,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$CachedNotificationsTable, CachedNotification>(
                        table),
                    BaseReferences<_$AppDatabase, $CachedNotificationsTable,
                        CachedNotification>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$CachedNotificationsTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $CachedNotificationsTable,
    CachedNotification,
    $$CachedNotificationsTableFilterComposer,
    $$CachedNotificationsTableOrderingComposer,
    $$CachedNotificationsTableAnnotationComposer,
    $$CachedNotificationsTableCreateCompanionBuilder,
    $$CachedNotificationsTableUpdateCompanionBuilder,
    (
      CachedNotification,
      BaseReferences<_$AppDatabase, $CachedNotificationsTable,
          CachedNotification>
    ),
    CachedNotification,
    PrefetchHooks Function()>;
typedef $$FeeSummaryCacheTableCreateCompanionBuilder = FeeSummaryCacheCompanion
    Function({
  required String userId,
  required String billed,
  required String paid,
  required String remaining,
  Value<int> countOverdue,
  Value<int> countPartial,
  required DateTime cachedAt,
  Value<int> rowid,
});
typedef $$FeeSummaryCacheTableUpdateCompanionBuilder = FeeSummaryCacheCompanion
    Function({
  Value<String> userId,
  Value<String> billed,
  Value<String> paid,
  Value<String> remaining,
  Value<int> countOverdue,
  Value<int> countPartial,
  Value<DateTime> cachedAt,
  Value<int> rowid,
});

class $$FeeSummaryCacheTableFilterComposer
    extends Composer<_$AppDatabase, $FeeSummaryCacheTable> {
  $$FeeSummaryCacheTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get userId => $composableBuilder(
      column: $table.userId, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get billed => $composableBuilder(
      column: $table.billed, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get paid => $composableBuilder(
      column: $table.paid, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get remaining => $composableBuilder(
      column: $table.remaining, builder: (column) => ColumnFilters(column));

  ColumnFilters<int> get countOverdue => $composableBuilder(
      column: $table.countOverdue, builder: (column) => ColumnFilters(column));

  ColumnFilters<int> get countPartial => $composableBuilder(
      column: $table.countPartial, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get cachedAt => $composableBuilder(
      column: $table.cachedAt, builder: (column) => ColumnFilters(column));
}

class $$FeeSummaryCacheTableOrderingComposer
    extends Composer<_$AppDatabase, $FeeSummaryCacheTable> {
  $$FeeSummaryCacheTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get userId => $composableBuilder(
      column: $table.userId, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get billed => $composableBuilder(
      column: $table.billed, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get paid => $composableBuilder(
      column: $table.paid, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get remaining => $composableBuilder(
      column: $table.remaining, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<int> get countOverdue => $composableBuilder(
      column: $table.countOverdue,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<int> get countPartial => $composableBuilder(
      column: $table.countPartial,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get cachedAt => $composableBuilder(
      column: $table.cachedAt, builder: (column) => ColumnOrderings(column));
}

class $$FeeSummaryCacheTableAnnotationComposer
    extends Composer<_$AppDatabase, $FeeSummaryCacheTable> {
  $$FeeSummaryCacheTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get userId =>
      $composableBuilder(column: $table.userId, builder: (column) => column);

  GeneratedColumn<String> get billed =>
      $composableBuilder(column: $table.billed, builder: (column) => column);

  GeneratedColumn<String> get paid =>
      $composableBuilder(column: $table.paid, builder: (column) => column);

  GeneratedColumn<String> get remaining =>
      $composableBuilder(column: $table.remaining, builder: (column) => column);

  GeneratedColumn<int> get countOverdue => $composableBuilder(
      column: $table.countOverdue, builder: (column) => column);

  GeneratedColumn<int> get countPartial => $composableBuilder(
      column: $table.countPartial, builder: (column) => column);

  GeneratedColumn<DateTime> get cachedAt =>
      $composableBuilder(column: $table.cachedAt, builder: (column) => column);
}

class $$FeeSummaryCacheTableTableManager extends RootTableManager<
    _$AppDatabase,
    $FeeSummaryCacheTable,
    FeeSummaryCacheData,
    $$FeeSummaryCacheTableFilterComposer,
    $$FeeSummaryCacheTableOrderingComposer,
    $$FeeSummaryCacheTableAnnotationComposer,
    $$FeeSummaryCacheTableCreateCompanionBuilder,
    $$FeeSummaryCacheTableUpdateCompanionBuilder,
    (
      FeeSummaryCacheData,
      BaseReferences<_$AppDatabase, $FeeSummaryCacheTable, FeeSummaryCacheData>
    ),
    FeeSummaryCacheData,
    PrefetchHooks Function()> {
  $$FeeSummaryCacheTableTableManager(
      _$AppDatabase db, $FeeSummaryCacheTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$FeeSummaryCacheTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$FeeSummaryCacheTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$FeeSummaryCacheTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> userId = const Value.absent(),
            Value<String> billed = const Value.absent(),
            Value<String> paid = const Value.absent(),
            Value<String> remaining = const Value.absent(),
            Value<int> countOverdue = const Value.absent(),
            Value<int> countPartial = const Value.absent(),
            Value<DateTime> cachedAt = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              FeeSummaryCacheCompanion(
            userId: userId,
            billed: billed,
            paid: paid,
            remaining: remaining,
            countOverdue: countOverdue,
            countPartial: countPartial,
            cachedAt: cachedAt,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String userId,
            required String billed,
            required String paid,
            required String remaining,
            Value<int> countOverdue = const Value.absent(),
            Value<int> countPartial = const Value.absent(),
            required DateTime cachedAt,
            Value<int> rowid = const Value.absent(),
          }) =>
              FeeSummaryCacheCompanion.insert(
            userId: userId,
            billed: billed,
            paid: paid,
            remaining: remaining,
            countOverdue: countOverdue,
            countPartial: countPartial,
            cachedAt: cachedAt,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$FeeSummaryCacheTable, FeeSummaryCacheData>(
                        table),
                    BaseReferences<_$AppDatabase, $FeeSummaryCacheTable,
                        FeeSummaryCacheData>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$FeeSummaryCacheTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $FeeSummaryCacheTable,
    FeeSummaryCacheData,
    $$FeeSummaryCacheTableFilterComposer,
    $$FeeSummaryCacheTableOrderingComposer,
    $$FeeSummaryCacheTableAnnotationComposer,
    $$FeeSummaryCacheTableCreateCompanionBuilder,
    $$FeeSummaryCacheTableUpdateCompanionBuilder,
    (
      FeeSummaryCacheData,
      BaseReferences<_$AppDatabase, $FeeSummaryCacheTable, FeeSummaryCacheData>
    ),
    FeeSummaryCacheData,
    PrefetchHooks Function()>;
typedef $$PaymentEntriesTableCreateCompanionBuilder = PaymentEntriesCompanion
    Function({
  required String id,
  required String studentName,
  required String amount,
  Value<String?> classroom,
  Value<String?> method,
  Value<String?> invoiceNumber,
  Value<String?> statusLabel,
  Value<String?> paidAt,
  Value<bool> canVerify,
  required DateTime syncedAt,
  Value<int> rowid,
});
typedef $$PaymentEntriesTableUpdateCompanionBuilder = PaymentEntriesCompanion
    Function({
  Value<String> id,
  Value<String> studentName,
  Value<String> amount,
  Value<String?> classroom,
  Value<String?> method,
  Value<String?> invoiceNumber,
  Value<String?> statusLabel,
  Value<String?> paidAt,
  Value<bool> canVerify,
  Value<DateTime> syncedAt,
  Value<int> rowid,
});

class $$PaymentEntriesTableFilterComposer
    extends Composer<_$AppDatabase, $PaymentEntriesTable> {
  $$PaymentEntriesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get studentName => $composableBuilder(
      column: $table.studentName, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get amount => $composableBuilder(
      column: $table.amount, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get classroom => $composableBuilder(
      column: $table.classroom, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get method => $composableBuilder(
      column: $table.method, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get invoiceNumber => $composableBuilder(
      column: $table.invoiceNumber, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get statusLabel => $composableBuilder(
      column: $table.statusLabel, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get paidAt => $composableBuilder(
      column: $table.paidAt, builder: (column) => ColumnFilters(column));

  ColumnFilters<bool> get canVerify => $composableBuilder(
      column: $table.canVerify, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnFilters(column));
}

class $$PaymentEntriesTableOrderingComposer
    extends Composer<_$AppDatabase, $PaymentEntriesTable> {
  $$PaymentEntriesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get studentName => $composableBuilder(
      column: $table.studentName, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get amount => $composableBuilder(
      column: $table.amount, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get classroom => $composableBuilder(
      column: $table.classroom, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get method => $composableBuilder(
      column: $table.method, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get invoiceNumber => $composableBuilder(
      column: $table.invoiceNumber,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get statusLabel => $composableBuilder(
      column: $table.statusLabel, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get paidAt => $composableBuilder(
      column: $table.paidAt, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<bool> get canVerify => $composableBuilder(
      column: $table.canVerify, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get syncedAt => $composableBuilder(
      column: $table.syncedAt, builder: (column) => ColumnOrderings(column));
}

class $$PaymentEntriesTableAnnotationComposer
    extends Composer<_$AppDatabase, $PaymentEntriesTable> {
  $$PaymentEntriesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get studentName => $composableBuilder(
      column: $table.studentName, builder: (column) => column);

  GeneratedColumn<String> get amount =>
      $composableBuilder(column: $table.amount, builder: (column) => column);

  GeneratedColumn<String> get classroom =>
      $composableBuilder(column: $table.classroom, builder: (column) => column);

  GeneratedColumn<String> get method =>
      $composableBuilder(column: $table.method, builder: (column) => column);

  GeneratedColumn<String> get invoiceNumber => $composableBuilder(
      column: $table.invoiceNumber, builder: (column) => column);

  GeneratedColumn<String> get statusLabel => $composableBuilder(
      column: $table.statusLabel, builder: (column) => column);

  GeneratedColumn<String> get paidAt =>
      $composableBuilder(column: $table.paidAt, builder: (column) => column);

  GeneratedColumn<bool> get canVerify =>
      $composableBuilder(column: $table.canVerify, builder: (column) => column);

  GeneratedColumn<DateTime> get syncedAt =>
      $composableBuilder(column: $table.syncedAt, builder: (column) => column);
}

class $$PaymentEntriesTableTableManager extends RootTableManager<
    _$AppDatabase,
    $PaymentEntriesTable,
    PaymentEntryRow,
    $$PaymentEntriesTableFilterComposer,
    $$PaymentEntriesTableOrderingComposer,
    $$PaymentEntriesTableAnnotationComposer,
    $$PaymentEntriesTableCreateCompanionBuilder,
    $$PaymentEntriesTableUpdateCompanionBuilder,
    (
      PaymentEntryRow,
      BaseReferences<_$AppDatabase, $PaymentEntriesTable, PaymentEntryRow>
    ),
    PaymentEntryRow,
    PrefetchHooks Function()> {
  $$PaymentEntriesTableTableManager(
      _$AppDatabase db, $PaymentEntriesTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$PaymentEntriesTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$PaymentEntriesTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$PaymentEntriesTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> id = const Value.absent(),
            Value<String> studentName = const Value.absent(),
            Value<String> amount = const Value.absent(),
            Value<String?> classroom = const Value.absent(),
            Value<String?> method = const Value.absent(),
            Value<String?> invoiceNumber = const Value.absent(),
            Value<String?> statusLabel = const Value.absent(),
            Value<String?> paidAt = const Value.absent(),
            Value<bool> canVerify = const Value.absent(),
            Value<DateTime> syncedAt = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              PaymentEntriesCompanion(
            id: id,
            studentName: studentName,
            amount: amount,
            classroom: classroom,
            method: method,
            invoiceNumber: invoiceNumber,
            statusLabel: statusLabel,
            paidAt: paidAt,
            canVerify: canVerify,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String id,
            required String studentName,
            required String amount,
            Value<String?> classroom = const Value.absent(),
            Value<String?> method = const Value.absent(),
            Value<String?> invoiceNumber = const Value.absent(),
            Value<String?> statusLabel = const Value.absent(),
            Value<String?> paidAt = const Value.absent(),
            Value<bool> canVerify = const Value.absent(),
            required DateTime syncedAt,
            Value<int> rowid = const Value.absent(),
          }) =>
              PaymentEntriesCompanion.insert(
            id: id,
            studentName: studentName,
            amount: amount,
            classroom: classroom,
            method: method,
            invoiceNumber: invoiceNumber,
            statusLabel: statusLabel,
            paidAt: paidAt,
            canVerify: canVerify,
            syncedAt: syncedAt,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$PaymentEntriesTable, PaymentEntryRow>(table),
                    BaseReferences<_$AppDatabase, $PaymentEntriesTable,
                        PaymentEntryRow>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$PaymentEntriesTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $PaymentEntriesTable,
    PaymentEntryRow,
    $$PaymentEntriesTableFilterComposer,
    $$PaymentEntriesTableOrderingComposer,
    $$PaymentEntriesTableAnnotationComposer,
    $$PaymentEntriesTableCreateCompanionBuilder,
    $$PaymentEntriesTableUpdateCompanionBuilder,
    (
      PaymentEntryRow,
      BaseReferences<_$AppDatabase, $PaymentEntriesTable, PaymentEntryRow>
    ),
    PaymentEntryRow,
    PrefetchHooks Function()>;
typedef $$QueuedScansTableCreateCompanionBuilder = QueuedScansCompanion
    Function({
  required String id,
  required String uniqueCode,
  required String waktu,
  required DateTime scannedAt,
  Value<double?> latitude,
  Value<double?> longitude,
  Value<SyncStatus> syncStatus,
  Value<String?> syncError,
  Value<int> syncAttempts,
  Value<int> rowid,
});
typedef $$QueuedScansTableUpdateCompanionBuilder = QueuedScansCompanion
    Function({
  Value<String> id,
  Value<String> uniqueCode,
  Value<String> waktu,
  Value<DateTime> scannedAt,
  Value<double?> latitude,
  Value<double?> longitude,
  Value<SyncStatus> syncStatus,
  Value<String?> syncError,
  Value<int> syncAttempts,
  Value<int> rowid,
});

class $$QueuedScansTableFilterComposer
    extends Composer<_$AppDatabase, $QueuedScansTable> {
  $$QueuedScansTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get uniqueCode => $composableBuilder(
      column: $table.uniqueCode, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get waktu => $composableBuilder(
      column: $table.waktu, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get scannedAt => $composableBuilder(
      column: $table.scannedAt, builder: (column) => ColumnFilters(column));

  ColumnFilters<double> get latitude => $composableBuilder(
      column: $table.latitude, builder: (column) => ColumnFilters(column));

  ColumnFilters<double> get longitude => $composableBuilder(
      column: $table.longitude, builder: (column) => ColumnFilters(column));

  ColumnWithTypeConverterFilters<SyncStatus, SyncStatus, String>
      get syncStatus => $composableBuilder(
          column: $table.syncStatus,
          builder: (column) => ColumnWithTypeConverterFilters(column));

  ColumnFilters<String> get syncError => $composableBuilder(
      column: $table.syncError, builder: (column) => ColumnFilters(column));

  ColumnFilters<int> get syncAttempts => $composableBuilder(
      column: $table.syncAttempts, builder: (column) => ColumnFilters(column));
}

class $$QueuedScansTableOrderingComposer
    extends Composer<_$AppDatabase, $QueuedScansTable> {
  $$QueuedScansTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get uniqueCode => $composableBuilder(
      column: $table.uniqueCode, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get waktu => $composableBuilder(
      column: $table.waktu, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get scannedAt => $composableBuilder(
      column: $table.scannedAt, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<double> get latitude => $composableBuilder(
      column: $table.latitude, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<double> get longitude => $composableBuilder(
      column: $table.longitude, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get syncStatus => $composableBuilder(
      column: $table.syncStatus, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get syncError => $composableBuilder(
      column: $table.syncError, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<int> get syncAttempts => $composableBuilder(
      column: $table.syncAttempts,
      builder: (column) => ColumnOrderings(column));
}

class $$QueuedScansTableAnnotationComposer
    extends Composer<_$AppDatabase, $QueuedScansTable> {
  $$QueuedScansTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get uniqueCode => $composableBuilder(
      column: $table.uniqueCode, builder: (column) => column);

  GeneratedColumn<String> get waktu =>
      $composableBuilder(column: $table.waktu, builder: (column) => column);

  GeneratedColumn<DateTime> get scannedAt =>
      $composableBuilder(column: $table.scannedAt, builder: (column) => column);

  GeneratedColumn<double> get latitude =>
      $composableBuilder(column: $table.latitude, builder: (column) => column);

  GeneratedColumn<double> get longitude =>
      $composableBuilder(column: $table.longitude, builder: (column) => column);

  GeneratedColumnWithTypeConverter<SyncStatus, String> get syncStatus =>
      $composableBuilder(
          column: $table.syncStatus, builder: (column) => column);

  GeneratedColumn<String> get syncError =>
      $composableBuilder(column: $table.syncError, builder: (column) => column);

  GeneratedColumn<int> get syncAttempts => $composableBuilder(
      column: $table.syncAttempts, builder: (column) => column);
}

class $$QueuedScansTableTableManager extends RootTableManager<
    _$AppDatabase,
    $QueuedScansTable,
    QueuedScanRow,
    $$QueuedScansTableFilterComposer,
    $$QueuedScansTableOrderingComposer,
    $$QueuedScansTableAnnotationComposer,
    $$QueuedScansTableCreateCompanionBuilder,
    $$QueuedScansTableUpdateCompanionBuilder,
    (
      QueuedScanRow,
      BaseReferences<_$AppDatabase, $QueuedScansTable, QueuedScanRow>
    ),
    QueuedScanRow,
    PrefetchHooks Function()> {
  $$QueuedScansTableTableManager(_$AppDatabase db, $QueuedScansTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$QueuedScansTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$QueuedScansTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$QueuedScansTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> id = const Value.absent(),
            Value<String> uniqueCode = const Value.absent(),
            Value<String> waktu = const Value.absent(),
            Value<DateTime> scannedAt = const Value.absent(),
            Value<double?> latitude = const Value.absent(),
            Value<double?> longitude = const Value.absent(),
            Value<SyncStatus> syncStatus = const Value.absent(),
            Value<String?> syncError = const Value.absent(),
            Value<int> syncAttempts = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              QueuedScansCompanion(
            id: id,
            uniqueCode: uniqueCode,
            waktu: waktu,
            scannedAt: scannedAt,
            latitude: latitude,
            longitude: longitude,
            syncStatus: syncStatus,
            syncError: syncError,
            syncAttempts: syncAttempts,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String id,
            required String uniqueCode,
            required String waktu,
            required DateTime scannedAt,
            Value<double?> latitude = const Value.absent(),
            Value<double?> longitude = const Value.absent(),
            Value<SyncStatus> syncStatus = const Value.absent(),
            Value<String?> syncError = const Value.absent(),
            Value<int> syncAttempts = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              QueuedScansCompanion.insert(
            id: id,
            uniqueCode: uniqueCode,
            waktu: waktu,
            scannedAt: scannedAt,
            latitude: latitude,
            longitude: longitude,
            syncStatus: syncStatus,
            syncError: syncError,
            syncAttempts: syncAttempts,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$QueuedScansTable, QueuedScanRow>(table),
                    BaseReferences<_$AppDatabase, $QueuedScansTable,
                        QueuedScanRow>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$QueuedScansTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $QueuedScansTable,
    QueuedScanRow,
    $$QueuedScansTableFilterComposer,
    $$QueuedScansTableOrderingComposer,
    $$QueuedScansTableAnnotationComposer,
    $$QueuedScansTableCreateCompanionBuilder,
    $$QueuedScansTableUpdateCompanionBuilder,
    (
      QueuedScanRow,
      BaseReferences<_$AppDatabase, $QueuedScansTable, QueuedScanRow>
    ),
    QueuedScanRow,
    PrefetchHooks Function()>;
typedef $$QueuedClassAttendancesTableCreateCompanionBuilder
    = QueuedClassAttendancesCompanion Function({
  required String id,
  required String classroomId,
  required String classroomName,
  required DateTime date,
  required String marksJson,
  required DateTime savedAt,
  Value<SyncStatus> syncStatus,
  Value<String?> syncError,
  Value<int> rowid,
});
typedef $$QueuedClassAttendancesTableUpdateCompanionBuilder
    = QueuedClassAttendancesCompanion Function({
  Value<String> id,
  Value<String> classroomId,
  Value<String> classroomName,
  Value<DateTime> date,
  Value<String> marksJson,
  Value<DateTime> savedAt,
  Value<SyncStatus> syncStatus,
  Value<String?> syncError,
  Value<int> rowid,
});

class $$QueuedClassAttendancesTableFilterComposer
    extends Composer<_$AppDatabase, $QueuedClassAttendancesTable> {
  $$QueuedClassAttendancesTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get classroomId => $composableBuilder(
      column: $table.classroomId, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get classroomName => $composableBuilder(
      column: $table.classroomName, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get date => $composableBuilder(
      column: $table.date, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get marksJson => $composableBuilder(
      column: $table.marksJson, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get savedAt => $composableBuilder(
      column: $table.savedAt, builder: (column) => ColumnFilters(column));

  ColumnWithTypeConverterFilters<SyncStatus, SyncStatus, String>
      get syncStatus => $composableBuilder(
          column: $table.syncStatus,
          builder: (column) => ColumnWithTypeConverterFilters(column));

  ColumnFilters<String> get syncError => $composableBuilder(
      column: $table.syncError, builder: (column) => ColumnFilters(column));
}

class $$QueuedClassAttendancesTableOrderingComposer
    extends Composer<_$AppDatabase, $QueuedClassAttendancesTable> {
  $$QueuedClassAttendancesTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get classroomId => $composableBuilder(
      column: $table.classroomId, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get classroomName => $composableBuilder(
      column: $table.classroomName,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get date => $composableBuilder(
      column: $table.date, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get marksJson => $composableBuilder(
      column: $table.marksJson, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get savedAt => $composableBuilder(
      column: $table.savedAt, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get syncStatus => $composableBuilder(
      column: $table.syncStatus, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get syncError => $composableBuilder(
      column: $table.syncError, builder: (column) => ColumnOrderings(column));
}

class $$QueuedClassAttendancesTableAnnotationComposer
    extends Composer<_$AppDatabase, $QueuedClassAttendancesTable> {
  $$QueuedClassAttendancesTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get classroomId => $composableBuilder(
      column: $table.classroomId, builder: (column) => column);

  GeneratedColumn<String> get classroomName => $composableBuilder(
      column: $table.classroomName, builder: (column) => column);

  GeneratedColumn<DateTime> get date =>
      $composableBuilder(column: $table.date, builder: (column) => column);

  GeneratedColumn<String> get marksJson =>
      $composableBuilder(column: $table.marksJson, builder: (column) => column);

  GeneratedColumn<DateTime> get savedAt =>
      $composableBuilder(column: $table.savedAt, builder: (column) => column);

  GeneratedColumnWithTypeConverter<SyncStatus, String> get syncStatus =>
      $composableBuilder(
          column: $table.syncStatus, builder: (column) => column);

  GeneratedColumn<String> get syncError =>
      $composableBuilder(column: $table.syncError, builder: (column) => column);
}

class $$QueuedClassAttendancesTableTableManager extends RootTableManager<
    _$AppDatabase,
    $QueuedClassAttendancesTable,
    QueuedClassAttendanceRow,
    $$QueuedClassAttendancesTableFilterComposer,
    $$QueuedClassAttendancesTableOrderingComposer,
    $$QueuedClassAttendancesTableAnnotationComposer,
    $$QueuedClassAttendancesTableCreateCompanionBuilder,
    $$QueuedClassAttendancesTableUpdateCompanionBuilder,
    (
      QueuedClassAttendanceRow,
      BaseReferences<_$AppDatabase, $QueuedClassAttendancesTable,
          QueuedClassAttendanceRow>
    ),
    QueuedClassAttendanceRow,
    PrefetchHooks Function()> {
  $$QueuedClassAttendancesTableTableManager(
      _$AppDatabase db, $QueuedClassAttendancesTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$QueuedClassAttendancesTableFilterComposer(
                  $db: db, $table: table),
          createOrderingComposer: () =>
              $$QueuedClassAttendancesTableOrderingComposer(
                  $db: db, $table: table),
          createComputedFieldComposer: () =>
              $$QueuedClassAttendancesTableAnnotationComposer(
                  $db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> id = const Value.absent(),
            Value<String> classroomId = const Value.absent(),
            Value<String> classroomName = const Value.absent(),
            Value<DateTime> date = const Value.absent(),
            Value<String> marksJson = const Value.absent(),
            Value<DateTime> savedAt = const Value.absent(),
            Value<SyncStatus> syncStatus = const Value.absent(),
            Value<String?> syncError = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              QueuedClassAttendancesCompanion(
            id: id,
            classroomId: classroomId,
            classroomName: classroomName,
            date: date,
            marksJson: marksJson,
            savedAt: savedAt,
            syncStatus: syncStatus,
            syncError: syncError,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String id,
            required String classroomId,
            required String classroomName,
            required DateTime date,
            required String marksJson,
            required DateTime savedAt,
            Value<SyncStatus> syncStatus = const Value.absent(),
            Value<String?> syncError = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              QueuedClassAttendancesCompanion.insert(
            id: id,
            classroomId: classroomId,
            classroomName: classroomName,
            date: date,
            marksJson: marksJson,
            savedAt: savedAt,
            syncStatus: syncStatus,
            syncError: syncError,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$QueuedClassAttendancesTable,
                        QueuedClassAttendanceRow>(table),
                    BaseReferences<_$AppDatabase, $QueuedClassAttendancesTable,
                        QueuedClassAttendanceRow>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$QueuedClassAttendancesTableProcessedTableManager
    = ProcessedTableManager<
        _$AppDatabase,
        $QueuedClassAttendancesTable,
        QueuedClassAttendanceRow,
        $$QueuedClassAttendancesTableFilterComposer,
        $$QueuedClassAttendancesTableOrderingComposer,
        $$QueuedClassAttendancesTableAnnotationComposer,
        $$QueuedClassAttendancesTableCreateCompanionBuilder,
        $$QueuedClassAttendancesTableUpdateCompanionBuilder,
        (
          QueuedClassAttendanceRow,
          BaseReferences<_$AppDatabase, $QueuedClassAttendancesTable,
              QueuedClassAttendanceRow>
        ),
        QueuedClassAttendanceRow,
        PrefetchHooks Function()>;
typedef $$PendingActionsTableCreateCompanionBuilder = PendingActionsCompanion
    Function({
  Value<int> id,
  required String actionType,
  required String payloadJson,
  required DateTime createdAt,
  Value<SyncStatus> syncStatus,
  Value<String?> syncError,
  Value<int> syncAttempts,
});
typedef $$PendingActionsTableUpdateCompanionBuilder = PendingActionsCompanion
    Function({
  Value<int> id,
  Value<String> actionType,
  Value<String> payloadJson,
  Value<DateTime> createdAt,
  Value<SyncStatus> syncStatus,
  Value<String?> syncError,
  Value<int> syncAttempts,
});

class $$PendingActionsTableFilterComposer
    extends Composer<_$AppDatabase, $PendingActionsTable> {
  $$PendingActionsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get actionType => $composableBuilder(
      column: $table.actionType, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get payloadJson => $composableBuilder(
      column: $table.payloadJson, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
      column: $table.createdAt, builder: (column) => ColumnFilters(column));

  ColumnWithTypeConverterFilters<SyncStatus, SyncStatus, String>
      get syncStatus => $composableBuilder(
          column: $table.syncStatus,
          builder: (column) => ColumnWithTypeConverterFilters(column));

  ColumnFilters<String> get syncError => $composableBuilder(
      column: $table.syncError, builder: (column) => ColumnFilters(column));

  ColumnFilters<int> get syncAttempts => $composableBuilder(
      column: $table.syncAttempts, builder: (column) => ColumnFilters(column));
}

class $$PendingActionsTableOrderingComposer
    extends Composer<_$AppDatabase, $PendingActionsTable> {
  $$PendingActionsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get actionType => $composableBuilder(
      column: $table.actionType, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get payloadJson => $composableBuilder(
      column: $table.payloadJson, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
      column: $table.createdAt, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get syncStatus => $composableBuilder(
      column: $table.syncStatus, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get syncError => $composableBuilder(
      column: $table.syncError, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<int> get syncAttempts => $composableBuilder(
      column: $table.syncAttempts,
      builder: (column) => ColumnOrderings(column));
}

class $$PendingActionsTableAnnotationComposer
    extends Composer<_$AppDatabase, $PendingActionsTable> {
  $$PendingActionsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get actionType => $composableBuilder(
      column: $table.actionType, builder: (column) => column);

  GeneratedColumn<String> get payloadJson => $composableBuilder(
      column: $table.payloadJson, builder: (column) => column);

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumnWithTypeConverter<SyncStatus, String> get syncStatus =>
      $composableBuilder(
          column: $table.syncStatus, builder: (column) => column);

  GeneratedColumn<String> get syncError =>
      $composableBuilder(column: $table.syncError, builder: (column) => column);

  GeneratedColumn<int> get syncAttempts => $composableBuilder(
      column: $table.syncAttempts, builder: (column) => column);
}

class $$PendingActionsTableTableManager extends RootTableManager<
    _$AppDatabase,
    $PendingActionsTable,
    PendingAction,
    $$PendingActionsTableFilterComposer,
    $$PendingActionsTableOrderingComposer,
    $$PendingActionsTableAnnotationComposer,
    $$PendingActionsTableCreateCompanionBuilder,
    $$PendingActionsTableUpdateCompanionBuilder,
    (
      PendingAction,
      BaseReferences<_$AppDatabase, $PendingActionsTable, PendingAction>
    ),
    PendingAction,
    PrefetchHooks Function()> {
  $$PendingActionsTableTableManager(
      _$AppDatabase db, $PendingActionsTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$PendingActionsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$PendingActionsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$PendingActionsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<int> id = const Value.absent(),
            Value<String> actionType = const Value.absent(),
            Value<String> payloadJson = const Value.absent(),
            Value<DateTime> createdAt = const Value.absent(),
            Value<SyncStatus> syncStatus = const Value.absent(),
            Value<String?> syncError = const Value.absent(),
            Value<int> syncAttempts = const Value.absent(),
          }) =>
              PendingActionsCompanion(
            id: id,
            actionType: actionType,
            payloadJson: payloadJson,
            createdAt: createdAt,
            syncStatus: syncStatus,
            syncError: syncError,
            syncAttempts: syncAttempts,
          ),
          createCompanionCallback: ({
            Value<int> id = const Value.absent(),
            required String actionType,
            required String payloadJson,
            required DateTime createdAt,
            Value<SyncStatus> syncStatus = const Value.absent(),
            Value<String?> syncError = const Value.absent(),
            Value<int> syncAttempts = const Value.absent(),
          }) =>
              PendingActionsCompanion.insert(
            id: id,
            actionType: actionType,
            payloadJson: payloadJson,
            createdAt: createdAt,
            syncStatus: syncStatus,
            syncError: syncError,
            syncAttempts: syncAttempts,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$PendingActionsTable, PendingAction>(table),
                    BaseReferences<_$AppDatabase, $PendingActionsTable,
                        PendingAction>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$PendingActionsTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $PendingActionsTable,
    PendingAction,
    $$PendingActionsTableFilterComposer,
    $$PendingActionsTableOrderingComposer,
    $$PendingActionsTableAnnotationComposer,
    $$PendingActionsTableCreateCompanionBuilder,
    $$PendingActionsTableUpdateCompanionBuilder,
    (
      PendingAction,
      BaseReferences<_$AppDatabase, $PendingActionsTable, PendingAction>
    ),
    PendingAction,
    PrefetchHooks Function()>;
typedef $$SyncMetadataTableCreateCompanionBuilder = SyncMetadataCompanion
    Function({
  required String entityType,
  Value<DateTime?> lastSyncedAt,
  Value<DateTime?> lastFullSyncAt,
  Value<String?> syncCursor,
  Value<int> rowid,
});
typedef $$SyncMetadataTableUpdateCompanionBuilder = SyncMetadataCompanion
    Function({
  Value<String> entityType,
  Value<DateTime?> lastSyncedAt,
  Value<DateTime?> lastFullSyncAt,
  Value<String?> syncCursor,
  Value<int> rowid,
});

class $$SyncMetadataTableFilterComposer
    extends Composer<_$AppDatabase, $SyncMetadataTable> {
  $$SyncMetadataTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get entityType => $composableBuilder(
      column: $table.entityType, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get lastSyncedAt => $composableBuilder(
      column: $table.lastSyncedAt, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get lastFullSyncAt => $composableBuilder(
      column: $table.lastFullSyncAt,
      builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get syncCursor => $composableBuilder(
      column: $table.syncCursor, builder: (column) => ColumnFilters(column));
}

class $$SyncMetadataTableOrderingComposer
    extends Composer<_$AppDatabase, $SyncMetadataTable> {
  $$SyncMetadataTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get entityType => $composableBuilder(
      column: $table.entityType, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get lastSyncedAt => $composableBuilder(
      column: $table.lastSyncedAt,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get lastFullSyncAt => $composableBuilder(
      column: $table.lastFullSyncAt,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get syncCursor => $composableBuilder(
      column: $table.syncCursor, builder: (column) => ColumnOrderings(column));
}

class $$SyncMetadataTableAnnotationComposer
    extends Composer<_$AppDatabase, $SyncMetadataTable> {
  $$SyncMetadataTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get entityType => $composableBuilder(
      column: $table.entityType, builder: (column) => column);

  GeneratedColumn<DateTime> get lastSyncedAt => $composableBuilder(
      column: $table.lastSyncedAt, builder: (column) => column);

  GeneratedColumn<DateTime> get lastFullSyncAt => $composableBuilder(
      column: $table.lastFullSyncAt, builder: (column) => column);

  GeneratedColumn<String> get syncCursor => $composableBuilder(
      column: $table.syncCursor, builder: (column) => column);
}

class $$SyncMetadataTableTableManager extends RootTableManager<
    _$AppDatabase,
    $SyncMetadataTable,
    SyncMetadataData,
    $$SyncMetadataTableFilterComposer,
    $$SyncMetadataTableOrderingComposer,
    $$SyncMetadataTableAnnotationComposer,
    $$SyncMetadataTableCreateCompanionBuilder,
    $$SyncMetadataTableUpdateCompanionBuilder,
    (
      SyncMetadataData,
      BaseReferences<_$AppDatabase, $SyncMetadataTable, SyncMetadataData>
    ),
    SyncMetadataData,
    PrefetchHooks Function()> {
  $$SyncMetadataTableTableManager(_$AppDatabase db, $SyncMetadataTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$SyncMetadataTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$SyncMetadataTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$SyncMetadataTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> entityType = const Value.absent(),
            Value<DateTime?> lastSyncedAt = const Value.absent(),
            Value<DateTime?> lastFullSyncAt = const Value.absent(),
            Value<String?> syncCursor = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              SyncMetadataCompanion(
            entityType: entityType,
            lastSyncedAt: lastSyncedAt,
            lastFullSyncAt: lastFullSyncAt,
            syncCursor: syncCursor,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String entityType,
            Value<DateTime?> lastSyncedAt = const Value.absent(),
            Value<DateTime?> lastFullSyncAt = const Value.absent(),
            Value<String?> syncCursor = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              SyncMetadataCompanion.insert(
            entityType: entityType,
            lastSyncedAt: lastSyncedAt,
            lastFullSyncAt: lastFullSyncAt,
            syncCursor: syncCursor,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$SyncMetadataTable, SyncMetadataData>(table),
                    BaseReferences<_$AppDatabase, $SyncMetadataTable,
                        SyncMetadataData>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$SyncMetadataTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $SyncMetadataTable,
    SyncMetadataData,
    $$SyncMetadataTableFilterComposer,
    $$SyncMetadataTableOrderingComposer,
    $$SyncMetadataTableAnnotationComposer,
    $$SyncMetadataTableCreateCompanionBuilder,
    $$SyncMetadataTableUpdateCompanionBuilder,
    (
      SyncMetadataData,
      BaseReferences<_$AppDatabase, $SyncMetadataTable, SyncMetadataData>
    ),
    SyncMetadataData,
    PrefetchHooks Function()>;
typedef $$LocalAccountsTableCreateCompanionBuilder = LocalAccountsCompanion
    Function({
  required String id,
  required String email,
  required String fullName,
  required String passwordHash,
  required String passwordSalt,
  required DateTime createdAt,
  required DateTime updatedAt,
  Value<int> rowid,
});
typedef $$LocalAccountsTableUpdateCompanionBuilder = LocalAccountsCompanion
    Function({
  Value<String> id,
  Value<String> email,
  Value<String> fullName,
  Value<String> passwordHash,
  Value<String> passwordSalt,
  Value<DateTime> createdAt,
  Value<DateTime> updatedAt,
  Value<int> rowid,
});

class $$LocalAccountsTableFilterComposer
    extends Composer<_$AppDatabase, $LocalAccountsTable> {
  $$LocalAccountsTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get email => $composableBuilder(
      column: $table.email, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get fullName => $composableBuilder(
      column: $table.fullName, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get passwordHash => $composableBuilder(
      column: $table.passwordHash, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get passwordSalt => $composableBuilder(
      column: $table.passwordSalt, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get createdAt => $composableBuilder(
      column: $table.createdAt, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get updatedAt => $composableBuilder(
      column: $table.updatedAt, builder: (column) => ColumnFilters(column));
}

class $$LocalAccountsTableOrderingComposer
    extends Composer<_$AppDatabase, $LocalAccountsTable> {
  $$LocalAccountsTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<String> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get email => $composableBuilder(
      column: $table.email, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get fullName => $composableBuilder(
      column: $table.fullName, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get passwordHash => $composableBuilder(
      column: $table.passwordHash,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get passwordSalt => $composableBuilder(
      column: $table.passwordSalt,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get createdAt => $composableBuilder(
      column: $table.createdAt, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get updatedAt => $composableBuilder(
      column: $table.updatedAt, builder: (column) => ColumnOrderings(column));
}

class $$LocalAccountsTableAnnotationComposer
    extends Composer<_$AppDatabase, $LocalAccountsTable> {
  $$LocalAccountsTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<String> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get email =>
      $composableBuilder(column: $table.email, builder: (column) => column);

  GeneratedColumn<String> get fullName =>
      $composableBuilder(column: $table.fullName, builder: (column) => column);

  GeneratedColumn<String> get passwordHash => $composableBuilder(
      column: $table.passwordHash, builder: (column) => column);

  GeneratedColumn<String> get passwordSalt => $composableBuilder(
      column: $table.passwordSalt, builder: (column) => column);

  GeneratedColumn<DateTime> get createdAt =>
      $composableBuilder(column: $table.createdAt, builder: (column) => column);

  GeneratedColumn<DateTime> get updatedAt =>
      $composableBuilder(column: $table.updatedAt, builder: (column) => column);
}

class $$LocalAccountsTableTableManager extends RootTableManager<
    _$AppDatabase,
    $LocalAccountsTable,
    LocalAccount,
    $$LocalAccountsTableFilterComposer,
    $$LocalAccountsTableOrderingComposer,
    $$LocalAccountsTableAnnotationComposer,
    $$LocalAccountsTableCreateCompanionBuilder,
    $$LocalAccountsTableUpdateCompanionBuilder,
    (
      LocalAccount,
      BaseReferences<_$AppDatabase, $LocalAccountsTable, LocalAccount>
    ),
    LocalAccount,
    PrefetchHooks Function()> {
  $$LocalAccountsTableTableManager(_$AppDatabase db, $LocalAccountsTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$LocalAccountsTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$LocalAccountsTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$LocalAccountsTableAnnotationComposer($db: db, $table: table),
          updateCompanionCallback: ({
            Value<String> id = const Value.absent(),
            Value<String> email = const Value.absent(),
            Value<String> fullName = const Value.absent(),
            Value<String> passwordHash = const Value.absent(),
            Value<String> passwordSalt = const Value.absent(),
            Value<DateTime> createdAt = const Value.absent(),
            Value<DateTime> updatedAt = const Value.absent(),
            Value<int> rowid = const Value.absent(),
          }) =>
              LocalAccountsCompanion(
            id: id,
            email: email,
            fullName: fullName,
            passwordHash: passwordHash,
            passwordSalt: passwordSalt,
            createdAt: createdAt,
            updatedAt: updatedAt,
            rowid: rowid,
          ),
          createCompanionCallback: ({
            required String id,
            required String email,
            required String fullName,
            required String passwordHash,
            required String passwordSalt,
            required DateTime createdAt,
            required DateTime updatedAt,
            Value<int> rowid = const Value.absent(),
          }) =>
              LocalAccountsCompanion.insert(
            id: id,
            email: email,
            fullName: fullName,
            passwordHash: passwordHash,
            passwordSalt: passwordSalt,
            createdAt: createdAt,
            updatedAt: updatedAt,
            rowid: rowid,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$LocalAccountsTable, LocalAccount>(table),
                    BaseReferences<_$AppDatabase, $LocalAccountsTable,
                        LocalAccount>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$LocalAccountsTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $LocalAccountsTable,
    LocalAccount,
    $$LocalAccountsTableFilterComposer,
    $$LocalAccountsTableOrderingComposer,
    $$LocalAccountsTableAnnotationComposer,
    $$LocalAccountsTableCreateCompanionBuilder,
    $$LocalAccountsTableUpdateCompanionBuilder,
    (
      LocalAccount,
      BaseReferences<_$AppDatabase, $LocalAccountsTable, LocalAccount>
    ),
    LocalAccount,
    PrefetchHooks Function()>;
typedef $$BackendConnectionTableCreateCompanionBuilder
    = BackendConnectionCompanion Function({
  Value<int> id,
  required String apiUrl,
  required String syncToken,
  Value<String?> schoolName,
  Value<String?> backendUserId,
  Value<String?> backendUserName,
  Value<String?> backendUserEmail,
  Value<String> permissionsJson,
  required DateTime connectedAt,
  Value<DateTime?> lastSyncAt,
});
typedef $$BackendConnectionTableUpdateCompanionBuilder
    = BackendConnectionCompanion Function({
  Value<int> id,
  Value<String> apiUrl,
  Value<String> syncToken,
  Value<String?> schoolName,
  Value<String?> backendUserId,
  Value<String?> backendUserName,
  Value<String?> backendUserEmail,
  Value<String> permissionsJson,
  Value<DateTime> connectedAt,
  Value<DateTime?> lastSyncAt,
});

class $$BackendConnectionTableFilterComposer
    extends Composer<_$AppDatabase, $BackendConnectionTable> {
  $$BackendConnectionTableFilterComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnFilters<int> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get apiUrl => $composableBuilder(
      column: $table.apiUrl, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get syncToken => $composableBuilder(
      column: $table.syncToken, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get schoolName => $composableBuilder(
      column: $table.schoolName, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get backendUserId => $composableBuilder(
      column: $table.backendUserId, builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get backendUserName => $composableBuilder(
      column: $table.backendUserName,
      builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get backendUserEmail => $composableBuilder(
      column: $table.backendUserEmail,
      builder: (column) => ColumnFilters(column));

  ColumnFilters<String> get permissionsJson => $composableBuilder(
      column: $table.permissionsJson,
      builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get connectedAt => $composableBuilder(
      column: $table.connectedAt, builder: (column) => ColumnFilters(column));

  ColumnFilters<DateTime> get lastSyncAt => $composableBuilder(
      column: $table.lastSyncAt, builder: (column) => ColumnFilters(column));
}

class $$BackendConnectionTableOrderingComposer
    extends Composer<_$AppDatabase, $BackendConnectionTable> {
  $$BackendConnectionTableOrderingComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  ColumnOrderings<int> get id => $composableBuilder(
      column: $table.id, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get apiUrl => $composableBuilder(
      column: $table.apiUrl, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get syncToken => $composableBuilder(
      column: $table.syncToken, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get schoolName => $composableBuilder(
      column: $table.schoolName, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get backendUserId => $composableBuilder(
      column: $table.backendUserId,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get backendUserName => $composableBuilder(
      column: $table.backendUserName,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get backendUserEmail => $composableBuilder(
      column: $table.backendUserEmail,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<String> get permissionsJson => $composableBuilder(
      column: $table.permissionsJson,
      builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get connectedAt => $composableBuilder(
      column: $table.connectedAt, builder: (column) => ColumnOrderings(column));

  ColumnOrderings<DateTime> get lastSyncAt => $composableBuilder(
      column: $table.lastSyncAt, builder: (column) => ColumnOrderings(column));
}

class $$BackendConnectionTableAnnotationComposer
    extends Composer<_$AppDatabase, $BackendConnectionTable> {
  $$BackendConnectionTableAnnotationComposer({
    required super.$db,
    required super.$table,
    super.joinBuilder,
    super.$addJoinBuilderToRootComposer,
    super.$removeJoinBuilderFromRootComposer,
  });
  GeneratedColumn<int> get id =>
      $composableBuilder(column: $table.id, builder: (column) => column);

  GeneratedColumn<String> get apiUrl =>
      $composableBuilder(column: $table.apiUrl, builder: (column) => column);

  GeneratedColumn<String> get syncToken =>
      $composableBuilder(column: $table.syncToken, builder: (column) => column);

  GeneratedColumn<String> get schoolName => $composableBuilder(
      column: $table.schoolName, builder: (column) => column);

  GeneratedColumn<String> get backendUserId => $composableBuilder(
      column: $table.backendUserId, builder: (column) => column);

  GeneratedColumn<String> get backendUserName => $composableBuilder(
      column: $table.backendUserName, builder: (column) => column);

  GeneratedColumn<String> get backendUserEmail => $composableBuilder(
      column: $table.backendUserEmail, builder: (column) => column);

  GeneratedColumn<String> get permissionsJson => $composableBuilder(
      column: $table.permissionsJson, builder: (column) => column);

  GeneratedColumn<DateTime> get connectedAt => $composableBuilder(
      column: $table.connectedAt, builder: (column) => column);

  GeneratedColumn<DateTime> get lastSyncAt => $composableBuilder(
      column: $table.lastSyncAt, builder: (column) => column);
}

class $$BackendConnectionTableTableManager extends RootTableManager<
    _$AppDatabase,
    $BackendConnectionTable,
    BackendConnectionData,
    $$BackendConnectionTableFilterComposer,
    $$BackendConnectionTableOrderingComposer,
    $$BackendConnectionTableAnnotationComposer,
    $$BackendConnectionTableCreateCompanionBuilder,
    $$BackendConnectionTableUpdateCompanionBuilder,
    (
      BackendConnectionData,
      BaseReferences<_$AppDatabase, $BackendConnectionTable,
          BackendConnectionData>
    ),
    BackendConnectionData,
    PrefetchHooks Function()> {
  $$BackendConnectionTableTableManager(
      _$AppDatabase db, $BackendConnectionTable table)
      : super(TableManagerState(
          db: db,
          table: table,
          createFilteringComposer: () =>
              $$BackendConnectionTableFilterComposer($db: db, $table: table),
          createOrderingComposer: () =>
              $$BackendConnectionTableOrderingComposer($db: db, $table: table),
          createComputedFieldComposer: () =>
              $$BackendConnectionTableAnnotationComposer(
                  $db: db, $table: table),
          updateCompanionCallback: ({
            Value<int> id = const Value.absent(),
            Value<String> apiUrl = const Value.absent(),
            Value<String> syncToken = const Value.absent(),
            Value<String?> schoolName = const Value.absent(),
            Value<String?> backendUserId = const Value.absent(),
            Value<String?> backendUserName = const Value.absent(),
            Value<String?> backendUserEmail = const Value.absent(),
            Value<String> permissionsJson = const Value.absent(),
            Value<DateTime> connectedAt = const Value.absent(),
            Value<DateTime?> lastSyncAt = const Value.absent(),
          }) =>
              BackendConnectionCompanion(
            id: id,
            apiUrl: apiUrl,
            syncToken: syncToken,
            schoolName: schoolName,
            backendUserId: backendUserId,
            backendUserName: backendUserName,
            backendUserEmail: backendUserEmail,
            permissionsJson: permissionsJson,
            connectedAt: connectedAt,
            lastSyncAt: lastSyncAt,
          ),
          createCompanionCallback: ({
            Value<int> id = const Value.absent(),
            required String apiUrl,
            required String syncToken,
            Value<String?> schoolName = const Value.absent(),
            Value<String?> backendUserId = const Value.absent(),
            Value<String?> backendUserName = const Value.absent(),
            Value<String?> backendUserEmail = const Value.absent(),
            Value<String> permissionsJson = const Value.absent(),
            required DateTime connectedAt,
            Value<DateTime?> lastSyncAt = const Value.absent(),
          }) =>
              BackendConnectionCompanion.insert(
            id: id,
            apiUrl: apiUrl,
            syncToken: syncToken,
            schoolName: schoolName,
            backendUserId: backendUserId,
            backendUserName: backendUserName,
            backendUserEmail: backendUserEmail,
            permissionsJson: permissionsJson,
            connectedAt: connectedAt,
            lastSyncAt: lastSyncAt,
          ),
          withReferenceMapper: (p0) => p0
              .map((e) => (
                    e.readTable<$BackendConnectionTable, BackendConnectionData>(
                        table),
                    BaseReferences<_$AppDatabase, $BackendConnectionTable,
                        BackendConnectionData>(db, table, e)
                  ))
              .toList(),
          prefetchHooksCallback: null,
        ));
}

typedef $$BackendConnectionTableProcessedTableManager = ProcessedTableManager<
    _$AppDatabase,
    $BackendConnectionTable,
    BackendConnectionData,
    $$BackendConnectionTableFilterComposer,
    $$BackendConnectionTableOrderingComposer,
    $$BackendConnectionTableAnnotationComposer,
    $$BackendConnectionTableCreateCompanionBuilder,
    $$BackendConnectionTableUpdateCompanionBuilder,
    (
      BackendConnectionData,
      BaseReferences<_$AppDatabase, $BackendConnectionTable,
          BackendConnectionData>
    ),
    BackendConnectionData,
    PrefetchHooks Function()>;

class $AppDatabaseManager {
  final _$AppDatabase _db;
  $AppDatabaseManager(this._db);
  $$CachedUsersTableTableManager get cachedUsers =>
      $$CachedUsersTableTableManager(_db, _db.cachedUsers);
  $$ClassroomsTableTableManager get classrooms =>
      $$ClassroomsTableTableManager(_db, _db.classrooms);
  $$StudentsTableTableManager get students =>
      $$StudentsTableTableManager(_db, _db.students);
  $$DashboardCacheTableTableManager get dashboardCache =>
      $$DashboardCacheTableTableManager(_db, _db.dashboardCache);
  $$MyAttendanceCacheTableTableManager get myAttendanceCache =>
      $$MyAttendanceCacheTableTableManager(_db, _db.myAttendanceCache);
  $$MyAttendanceHistoryTableTableManager get myAttendanceHistory =>
      $$MyAttendanceHistoryTableTableManager(_db, _db.myAttendanceHistory);
  $$CachedNotificationsTableTableManager get cachedNotifications =>
      $$CachedNotificationsTableTableManager(_db, _db.cachedNotifications);
  $$FeeSummaryCacheTableTableManager get feeSummaryCache =>
      $$FeeSummaryCacheTableTableManager(_db, _db.feeSummaryCache);
  $$PaymentEntriesTableTableManager get paymentEntries =>
      $$PaymentEntriesTableTableManager(_db, _db.paymentEntries);
  $$QueuedScansTableTableManager get queuedScans =>
      $$QueuedScansTableTableManager(_db, _db.queuedScans);
  $$QueuedClassAttendancesTableTableManager get queuedClassAttendances =>
      $$QueuedClassAttendancesTableTableManager(
          _db, _db.queuedClassAttendances);
  $$PendingActionsTableTableManager get pendingActions =>
      $$PendingActionsTableTableManager(_db, _db.pendingActions);
  $$SyncMetadataTableTableManager get syncMetadata =>
      $$SyncMetadataTableTableManager(_db, _db.syncMetadata);
  $$LocalAccountsTableTableManager get localAccounts =>
      $$LocalAccountsTableTableManager(_db, _db.localAccounts);
  $$BackendConnectionTableTableManager get backendConnection =>
      $$BackendConnectionTableTableManager(_db, _db.backendConnection);
}
