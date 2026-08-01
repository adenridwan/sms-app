/// Data awal scanner dari `GET /scan/bootstrap`.
class BootstrapData {
  const BootstrapData({
    required this.today,
    required this.currentTime,
    required this.isHoliday,
    this.holidayNote,
    this.requireLocation = false,
    this.checkInDeadline,
    this.checkInStart,
    this.checkInEnd,
  });

  final String today;
  final String currentTime;
  final bool isHoliday;
  final String? holidayNote;
  final bool requireLocation;
  final String? checkInDeadline;
  final String? checkInStart;
  final String? checkInEnd;

  factory BootstrapData.fromJson(Map<String, dynamic> json) {
    final settings = (json['settings'] as Map?) ?? const {};
    final holiday = json['holiday_info'] as Map?;
    return BootstrapData(
      today: (json['today'] ?? '').toString(),
      currentTime: (json['current_time'] ?? '').toString(),
      isHoliday: json['is_holiday'] == true,
      holidayNote: holiday?['keterangan']?.toString(),
      requireLocation: settings['require_location'] == true,
      checkInDeadline: json['check_in_deadline']?.toString(),
      checkInStart: settings['check_in_start']?.toString(),
      checkInEnd: settings['check_in_end']?.toString(),
    );
  }
}
