import 'gym_tenant.dart';
import 'member_user.dart';

double _toDouble(dynamic val) {
  if (val == null) return 0.0;
  if (val is double) return val;
  if (val is int) return val.toDouble();
  if (val is String) return double.tryParse(val) ?? 0.0;
  return 0.0;
}

int _toInt(dynamic val) {
  if (val == null) return 0;
  if (val is int) return val;
  if (val is double) return val.toInt();
  if (val is String) return int.tryParse(val) ?? 0;
  return 0;
}

class DashboardData {
  final GymTenant gym;
  final MemberUser member;
  final DashboardMembership membership;
  final DashboardAttendance attendance;
  final DashboardPayments payments;
  final DashboardWorkout? workout;
  final DashboardDiet? diet;
  final DashboardTrainer? trainer;
  final List<DashboardAnnouncement> announcements;
  final List<DashboardTodo> todos;
  final DashboardQrPass qrPass;

  DashboardData({
    required this.gym,
    required this.member,
    required this.membership,
    required this.attendance,
    required this.payments,
    this.workout,
    this.diet,
    this.trainer,
    required this.announcements,
    required this.todos,
    required this.qrPass,
  });

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    return DashboardData(
      gym: GymTenant.fromJson(json['gym'] is Map ? Map<String, dynamic>.from(json['gym']) : {}),
      member: MemberUser.fromJson(json['member'] is Map ? Map<String, dynamic>.from(json['member']) : {}),
      membership: DashboardMembership.fromJson(json['membership'] is Map ? Map<String, dynamic>.from(json['membership']) : {}),
      attendance: DashboardAttendance.fromJson(json['attendance'] is Map ? Map<String, dynamic>.from(json['attendance']) : {}),
      payments: DashboardPayments.fromJson(json['payments'] is Map ? Map<String, dynamic>.from(json['payments']) : {}),
      workout: (json['workout'] is Map) ? DashboardWorkout.fromJson(Map<String, dynamic>.from(json['workout'])) : null,
      diet: (json['diet'] is Map) ? DashboardDiet.fromJson(Map<String, dynamic>.from(json['diet'])) : null,
      trainer: (json['trainer'] is Map) ? DashboardTrainer.fromJson(Map<String, dynamic>.from(json['trainer'])) : null,
      announcements: (json['announcements'] as List? ?? [])
          .whereType<Map>()
          .map((a) => DashboardAnnouncement.fromJson(Map<String, dynamic>.from(a)))
          .toList(),
      todos: (json['todos'] as List? ?? [])
          .whereType<Map>()
          .map((t) => DashboardTodo.fromJson(Map<String, dynamic>.from(t)))
          .toList(),
      qrPass: DashboardQrPass.fromJson(json['qr_pass'] is Map ? Map<String, dynamic>.from(json['qr_pass']) : {}),
    );
  }
}

class DashboardMembership {
  final String planName;
  final int planDurationMonths;
  final String status;
  final String startDate;
  final String expiryDate;
  final int daysRemaining;
  final bool isExpiringSoon;
  final double totalFee;

  DashboardMembership({
    required this.planName,
    required this.planDurationMonths,
    required this.status,
    required this.startDate,
    required this.expiryDate,
    required this.daysRemaining,
    required this.isExpiringSoon,
    required this.totalFee,
  });

  factory DashboardMembership.fromJson(Map<String, dynamic> json) {
    return DashboardMembership(
      planName: json['plan_name']?.toString() ?? 'General Fitness',
      planDurationMonths: _toInt(json['plan_duration_months'] ?? 1),
      status: json['status']?.toString() ?? 'Active',
      startDate: json['start_date']?.toString() ?? '',
      expiryDate: json['expiry_date']?.toString() ?? '',
      daysRemaining: _toInt(json['days_remaining']),
      isExpiringSoon: json['is_expiring_soon'] == true,
      totalFee: _toDouble(json['total_fee']),
    );
  }
}

class DashboardAttendance {
  final String todayStatus;
  final String? todayCheckIn;
  final String? todayCheckOut;
  final int totalLifetimeSessions;

  DashboardAttendance({
    required this.todayStatus,
    this.todayCheckIn,
    this.todayCheckOut,
    required this.totalLifetimeSessions,
  });

  factory DashboardAttendance.fromJson(Map<String, dynamic> json) {
    return DashboardAttendance(
      todayStatus: json['today_status']?.toString() ?? 'Not Checked In',
      todayCheckIn: json['today_check_in']?.toString(),
      todayCheckOut: json['today_check_out']?.toString(),
      totalLifetimeSessions: _toInt(json['total_lifetime_sessions']),
    );
  }
}

class DashboardPayments {
  final double totalPaid;
  final double outstandingDue;
  final DashboardLatestInvoice? latestInvoice;

  DashboardPayments({
    required this.totalPaid,
    required this.outstandingDue,
    this.latestInvoice,
  });

  factory DashboardPayments.fromJson(Map<String, dynamic> json) {
    return DashboardPayments(
      totalPaid: _toDouble(json['total_paid']),
      outstandingDue: _toDouble(json['outstanding_due']),
      latestInvoice: (json['latest_invoice'] is Map)
          ? DashboardLatestInvoice.fromJson(Map<String, dynamic>.from(json['latest_invoice']))
          : null,
    );
  }
}

class DashboardLatestInvoice {
  final int id;
  final String number;
  final double amount;
  final String date;
  final String status;

  DashboardLatestInvoice({
    required this.id,
    required this.number,
    required this.amount,
    required this.date,
    required this.status,
  });

  factory DashboardLatestInvoice.fromJson(Map<String, dynamic> json) {
    return DashboardLatestInvoice(
      id: _toInt(json['id']),
      number: json['number']?.toString() ?? '',
      amount: _toDouble(json['amount']),
      date: json['date']?.toString() ?? '',
      status: json['status']?.toString() ?? 'Paid',
    );
  }
}

class DashboardWorkout {
  final int planId;
  final String name;
  final String goal;
  final String level;
  final String scheduleText;
  final String trainerName;

  DashboardWorkout({
    required this.planId,
    required this.name,
    required this.goal,
    required this.level,
    required this.scheduleText,
    required this.trainerName,
  });

  factory DashboardWorkout.fromJson(Map<String, dynamic> json) {
    return DashboardWorkout(
      planId: _toInt(json['plan_id']),
      name: json['name']?.toString() ?? 'Workout Plan',
      goal: json['goal']?.toString() ?? '',
      level: json['level']?.toString() ?? 'Beginner',
      scheduleText: json['schedule_text']?.toString() ?? json['schedule']?.toString() ?? '',
      trainerName: json['trainer_name']?.toString() ?? 'Trainer',
    );
  }
}

class DashboardDiet {
  final int planId;
  final String name;
  final String target;
  final int calories;
  final String mealsText;

  DashboardDiet({
    required this.planId,
    required this.name,
    required this.target,
    required this.calories,
    required this.mealsText,
  });

  factory DashboardDiet.fromJson(Map<String, dynamic> json) {
    return DashboardDiet(
      planId: _toInt(json['plan_id']),
      name: json['name']?.toString() ?? 'Diet Plan',
      target: json['target']?.toString() ?? '',
      calories: _toInt(json['calories'] ?? 2000),
      mealsText: json['meals_text']?.toString() ?? json['meals']?.toString() ?? '',
    );
  }
}

class DashboardTrainer {
  final int id;
  final String name;
  final String designation;
  final String phone;
  final String email;
  final String specialization;
  final String timings;

  DashboardTrainer({
    required this.id,
    required this.name,
    required this.designation,
    required this.phone,
    required this.email,
    required this.specialization,
    required this.timings,
  });

  factory DashboardTrainer.fromJson(Map<String, dynamic> json) {
    return DashboardTrainer(
      id: _toInt(json['id']),
      name: json['name']?.toString() ?? 'Trainer',
      designation: json['designation']?.toString() ?? 'Personal Trainer',
      phone: json['phone']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      specialization: json['specialization']?.toString() ?? 'Fitness',
      timings: json['timings']?.toString() ?? 'Working Hours',
    );
  }
}

class DashboardAnnouncement {
  final int id;
  final String message;
  final String date;

  DashboardAnnouncement({
    required this.id,
    required this.message,
    required this.date,
  });

  factory DashboardAnnouncement.fromJson(Map<String, dynamic> json) {
    return DashboardAnnouncement(
      id: _toInt(json['id']),
      message: json['message']?.toString() ?? '',
      date: json['date']?.toString() ?? '',
    );
  }
}

class DashboardTodo {
  final int id;
  final String taskDesc;
  final String taskStatus;

  DashboardTodo({
    required this.id,
    required this.taskDesc,
    required this.taskStatus,
  });

  factory DashboardTodo.fromJson(Map<String, dynamic> json) {
    return DashboardTodo(
      id: _toInt(json['id']),
      taskDesc: json['task_desc']?.toString() ?? '',
      taskStatus: json['task_status']?.toString() ?? json['is_completed']?.toString() ?? 'Pending',
    );
  }
}

class DashboardQrPass {
  final String payload;
  final String code;
  final String validUntil;

  DashboardQrPass({
    required this.payload,
    required this.code,
    required this.validUntil,
  });

  factory DashboardQrPass.fromJson(Map<String, dynamic> json) {
    return DashboardQrPass(
      payload: json['payload']?.toString() ?? '',
      code: json['code']?.toString() ?? '',
      validUntil: json['valid_until']?.toString() ?? '',
    );
  }
}
