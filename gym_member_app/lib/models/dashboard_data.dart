import 'gym_tenant.dart';
import 'member_user.dart';

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
      gym: GymTenant.fromJson(json['gym'] ?? {}),
      member: MemberUser.fromJson(json['member'] ?? {}),
      membership: DashboardMembership.fromJson(json['membership'] ?? {}),
      attendance: DashboardAttendance.fromJson(json['attendance'] ?? {}),
      payments: DashboardPayments.fromJson(json['payments'] ?? {}),
      workout: json['workout'] != null ? DashboardWorkout.fromJson(json['workout']) : null,
      diet: json['diet'] != null ? DashboardDiet.fromJson(json['diet']) : null,
      trainer: json['trainer'] != null ? DashboardTrainer.fromJson(json['trainer']) : null,
      announcements: (json['announcements'] as List? ?? [])
          .map((a) => DashboardAnnouncement.fromJson(a))
          .toList(),
      todos: (json['todos'] as List? ?? []).map((t) => DashboardTodo.fromJson(t)).toList(),
      qrPass: DashboardQrPass.fromJson(json['qr_pass'] ?? {}),
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
      planName: json['plan_name'] ?? 'General Fitness',
      planDurationMonths: json['plan_duration_months'] ?? 1,
      status: json['status'] ?? 'Active',
      startDate: json['start_date'] ?? '',
      expiryDate: json['expiry_date'] ?? '',
      daysRemaining: json['days_remaining'] ?? 0,
      isExpiringSoon: json['is_expiring_soon'] == true,
      totalFee: (json['total_fee'] ?? 0).toDouble(),
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
      todayStatus: json['today_status'] ?? 'Not Checked In',
      todayCheckIn: json['today_check_in'],
      todayCheckOut: json['today_check_out'],
      totalLifetimeSessions: json['total_lifetime_sessions'] ?? 0,
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
      totalPaid: (json['total_paid'] ?? 0).toDouble(),
      outstandingDue: (json['outstanding_due'] ?? 0).toDouble(),
      latestInvoice: json['latest_invoice'] != null
          ? DashboardLatestInvoice.fromJson(json['latest_invoice'])
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
      id: json['id'] ?? 0,
      number: json['number'] ?? '',
      amount: (json['amount'] ?? 0).toDouble(),
      date: json['date'] ?? '',
      status: json['status'] ?? 'Paid',
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
      planId: json['plan_id'] ?? 0,
      name: json['name'] ?? 'Workout Plan',
      goal: json['goal'] ?? '',
      level: json['level'] ?? 'Beginner',
      scheduleText: json['schedule_text'] ?? '',
      trainerName: json['trainer_name'] ?? 'Trainer',
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
      planId: json['plan_id'] ?? 0,
      name: json['name'] ?? 'Diet Plan',
      target: json['target'] ?? '',
      calories: json['calories'] ?? 2000,
      mealsText: json['meals_text'] ?? '',
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
      id: json['id'] ?? 0,
      name: json['name'] ?? 'Trainer',
      designation: json['designation'] ?? 'Personal Trainer',
      phone: json['phone'] ?? '',
      email: json['email'] ?? '',
      specialization: json['specialization'] ?? 'Fitness',
      timings: json['timings'] ?? 'Working Hours',
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
      id: json['id'] ?? 0,
      message: json['message'] ?? '',
      date: json['date'] ?? '',
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
      id: json['id'] ?? 0,
      taskDesc: json['task_desc'] ?? '',
      taskStatus: json['task_status'] ?? 'Pending',
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
      payload: json['payload'] ?? '',
      code: json['code'] ?? '',
      validUntil: json['valid_until'] ?? '',
    );
  }
}
