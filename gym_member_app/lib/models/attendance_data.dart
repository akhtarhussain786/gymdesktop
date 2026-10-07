class AttendanceData {
  final AttendanceSummary summary;
  final List<String> checkinDates;
  final List<AttendanceRecord> history;

  AttendanceData({
    required this.summary,
    required this.checkinDates,
    required this.history,
  });

  factory AttendanceData.fromJson(Map<String, dynamic> json) {
    return AttendanceData(
      summary: AttendanceSummary.fromJson(json['summary'] ?? {}),
      checkinDates: (json['checkin_dates'] as List? ?? []).map((d) => d.toString()).toList(),
      history: (json['history'] as List? ?? []).map((h) => AttendanceRecord.fromJson(h)).toList(),
    );
  }
}

class AttendanceSummary {
  final int totalLifetimeSessions;
  final int monthSessions;
  final String currentMonth;
  final String todayStatus;
  final String? todayCheckIn;
  final String? todayCheckOut;

  AttendanceSummary({
    required this.totalLifetimeSessions,
    required this.monthSessions,
    required this.currentMonth,
    required this.todayStatus,
    this.todayCheckIn,
    this.todayCheckOut,
  });

  factory AttendanceSummary.fromJson(Map<String, dynamic> json) {
    return AttendanceSummary(
      totalLifetimeSessions: json['total_lifetime_sessions'] ?? 0,
      monthSessions: json['month_sessions'] ?? 0,
      currentMonth: json['current_month'] ?? '',
      todayStatus: json['today_status'] ?? 'Not Checked In',
      todayCheckIn: json['today_check_in'],
      todayCheckOut: json['today_check_out'],
    );
  }
}

class AttendanceRecord {
  final int id;
  final String date;
  final String checkInTime;
  final String? checkOutTime;
  final String status;

  AttendanceRecord({
    required this.id,
    required this.date,
    required this.checkInTime,
    this.checkOutTime,
    required this.status,
  });

  factory AttendanceRecord.fromJson(Map<String, dynamic> json) {
    return AttendanceRecord(
      id: json['id'] ?? 0,
      date: json['date'] ?? '',
      checkInTime: json['check_in_time'] ?? '',
      checkOutTime: json['check_out_time'],
      status: json['status'] ?? 'Present',
    );
  }
}
