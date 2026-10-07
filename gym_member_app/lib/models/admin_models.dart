class AdminGymInfo {
  final int id;
  final String name;
  final String code;
  final String? logo;
  final String currency;
  final String upiId;
  final String phone;
  final String address;

  AdminGymInfo({
    required this.id,
    required this.name,
    required this.code,
    this.logo,
    required this.currency,
    required this.upiId,
    required this.phone,
    required this.address,
  });

  factory AdminGymInfo.fromJson(Map<String, dynamic> json) {
    return AdminGymInfo(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      name: json['name']?.toString() ?? '',
      code: json['code']?.toString() ?? '',
      logo: json['logo']?.toString(),
      currency: json['currency']?.toString() ?? '₹',
      upiId: json['upi_id']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      address: json['address']?.toString() ?? '',
    );
  }
}

class AdminKpis {
  final int totalMembers;
  final int activeMembers;
  final int expiredMembers;
  final int expiring7Days;
  final int todayCheckins;
  final int duesPendingCount;
  final double totalDuesAmount;

  AdminKpis({
    required this.totalMembers,
    required this.activeMembers,
    required this.expiredMembers,
    required this.expiring7Days,
    required this.todayCheckins,
    required this.duesPendingCount,
    required this.totalDuesAmount,
  });

  factory AdminKpis.fromJson(Map<String, dynamic> json) {
    return AdminKpis(
      totalMembers: (json['total_members'] is int) ? json['total_members'] : int.tryParse('${json['total_members']}') ?? 0,
      activeMembers: (json['active_members'] is int) ? json['active_members'] : int.tryParse('${json['active_members']}') ?? 0,
      expiredMembers: (json['expired_members'] is int) ? json['expired_members'] : int.tryParse('${json['expired_members']}') ?? 0,
      expiring7Days: (json['expiring_7days'] is int) ? json['expiring_7days'] : int.tryParse('${json['expiring_7days']}') ?? 0,
      todayCheckins: (json['today_checkins'] is int) ? json['today_checkins'] : int.tryParse('${json['today_checkins']}') ?? 0,
      duesPendingCount: (json['dues_pending_count'] is int) ? json['dues_pending_count'] : int.tryParse('${json['dues_pending_count']}') ?? 0,
      totalDuesAmount: (json['total_dues_amount'] is num) ? (json['total_dues_amount'] as num).toDouble() : double.tryParse('${json['total_dues_amount']}') ?? 0.0,
    );
  }
}

class AdminDueMember {
  final int memberId;
  final String fullname;
  final String phone;
  final String? avatar;
  final String services;
  final double dueAmount;
  final String? dueDate;

  AdminDueMember({
    required this.memberId,
    required this.fullname,
    required this.phone,
    this.avatar,
    required this.services,
    required this.dueAmount,
    this.dueDate,
  });

  factory AdminDueMember.fromJson(Map<String, dynamic> json) {
    return AdminDueMember(
      memberId: (json['member_id'] is int) ? json['member_id'] : int.tryParse('${json['member_id']}') ?? 0,
      fullname: json['fullname']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      avatar: json['avatar']?.toString(),
      services: json['services']?.toString() ?? 'General Fitness',
      dueAmount: (json['due_amount'] is num) ? (json['due_amount'] as num).toDouble() : double.tryParse('${json['due_amount']}') ?? 0.0,
      dueDate: json['due_date']?.toString(),
    );
  }
}

class AdminDashboardData {
  final AdminGymInfo gym;
  final AdminKpis kpis;
  final List<AdminDueMember> recentDues;

  AdminDashboardData({
    required this.gym,
    required this.kpis,
    required this.recentDues,
  });

  factory AdminDashboardData.fromJson(Map<String, dynamic> json) {
    return AdminDashboardData(
      gym: AdminGymInfo.fromJson(json['gym'] ?? {}),
      kpis: AdminKpis.fromJson(json['kpis'] ?? {}),
      recentDues: (json['recent_dues'] as List<dynamic>? ?? [])
          .map((e) => AdminDueMember.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}

class AdminMemberItem {
  final int memberId;
  final String fullname;
  final String username;
  final String phone;
  final String email;
  final String gender;
  final String address;
  final String? avatar;
  final String services;
  final int planMonths;
  final String membershipStatus;
  final String startDate;
  final String expiryDate;
  final int daysRemaining;
  final double totalFee;
  final double paidAmount;
  final double dueAmount;
  final String? dueDate;
  final int attendanceCount;
  final String whatsappReminder;

  AdminMemberItem({
    required this.memberId,
    required this.fullname,
    required this.username,
    required this.phone,
    required this.email,
    required this.gender,
    required this.address,
    this.avatar,
    required this.services,
    required this.planMonths,
    required this.membershipStatus,
    required this.startDate,
    required this.expiryDate,
    required this.daysRemaining,
    required this.totalFee,
    required this.paidAmount,
    required this.dueAmount,
    this.dueDate,
    required this.attendanceCount,
    required this.whatsappReminder,
  });

  factory AdminMemberItem.fromJson(Map<String, dynamic> json) {
    return AdminMemberItem(
      memberId: (json['member_id'] is int) ? json['member_id'] : int.tryParse('${json['member_id']}') ?? 0,
      fullname: json['fullname']?.toString() ?? '',
      username: json['username']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      gender: json['gender']?.toString() ?? 'Male',
      address: json['address']?.toString() ?? '',
      avatar: json['avatar']?.toString(),
      services: json['services']?.toString() ?? 'General Fitness',
      planMonths: (json['plan_months'] is int) ? json['plan_months'] : int.tryParse('${json['plan_months']}') ?? 1,
      membershipStatus: json['membership_status']?.toString() ?? 'Active',
      startDate: json['start_date']?.toString() ?? '',
      expiryDate: json['expiry_date']?.toString() ?? '',
      daysRemaining: (json['days_remaining'] is int) ? json['days_remaining'] : int.tryParse('${json['days_remaining']}') ?? 0,
      totalFee: (json['total_fee'] is num) ? (json['total_fee'] as num).toDouble() : double.tryParse('${json['total_fee']}') ?? 0.0,
      paidAmount: (json['paid_amount'] is num) ? (json['paid_amount'] as num).toDouble() : double.tryParse('${json['paid_amount']}') ?? 0.0,
      dueAmount: (json['due_amount'] is num) ? (json['due_amount'] as num).toDouble() : double.tryParse('${json['due_amount']}') ?? 0.0,
      dueDate: json['due_date']?.toString(),
      attendanceCount: (json['attendance_count'] is int) ? json['attendance_count'] : int.tryParse('${json['attendance_count']}') ?? 0,
      whatsappReminder: json['whatsapp_reminder']?.toString() ?? '',
    );
  }
}

class AdminInvoiceItem {
  final int id;
  final String invoiceNumber;
  final String serviceName;
  final int planMonths;
  final double amount;
  final double paidAmount;
  final double dueAmount;
  final String paymentMethod;
  final String paymentDate;
  final String? dueDate;
  final String status;
  final String notes;
  final String? receiptUrl;

  AdminInvoiceItem({
    required this.id,
    required this.invoiceNumber,
    required this.serviceName,
    required this.planMonths,
    required this.amount,
    required this.paidAmount,
    required this.dueAmount,
    required this.paymentMethod,
    required this.paymentDate,
    this.dueDate,
    required this.status,
    required this.notes,
    this.receiptUrl,
  });

  factory AdminInvoiceItem.fromJson(Map<String, dynamic> json) {
    return AdminInvoiceItem(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      invoiceNumber: json['invoice_number']?.toString() ?? '',
      serviceName: json['service_name']?.toString() ?? 'Membership',
      planMonths: (json['plan_months'] is int) ? json['plan_months'] : int.tryParse('${json['plan_months']}') ?? 1,
      amount: (json['amount'] is num) ? (json['amount'] as num).toDouble() : double.tryParse('${json['amount']}') ?? 0.0,
      paidAmount: (json['paid_amount'] is num) ? (json['paid_amount'] as num).toDouble() : double.tryParse('${json['paid_amount']}') ?? 0.0,
      dueAmount: (json['due_amount'] is num) ? (json['due_amount'] as num).toDouble() : double.tryParse('${json['due_amount']}') ?? 0.0,
      paymentMethod: json['payment_method']?.toString() ?? 'Cash',
      paymentDate: json['payment_date']?.toString() ?? '',
      dueDate: json['due_date']?.toString(),
      status: json['status']?.toString() ?? 'Paid',
      notes: json['notes']?.toString() ?? '',
      receiptUrl: json['receipt_url']?.toString(),
    );
  }
}

class AdminAttendanceItem {
  final String date;
  final String time;
  final String present;

  AdminAttendanceItem({
    required this.date,
    required this.time,
    required this.present,
  });

  factory AdminAttendanceItem.fromJson(Map<String, dynamic> json) {
    return AdminAttendanceItem(
      date: json['curr_date']?.toString() ?? '',
      time: json['curr_time']?.toString() ?? '',
      present: json['present']?.toString() ?? '1',
    );
  }
}

class AdminMemberDetail {
  final AdminMemberItem member;
  final List<AdminInvoiceItem> invoices;
  final List<AdminAttendanceItem> attendance;
  final String currency;
  final String upiId;
  final String gymName;

  AdminMemberDetail({
    required this.member,
    required this.invoices,
    required this.attendance,
    required this.currency,
    required this.upiId,
    required this.gymName,
  });

  factory AdminMemberDetail.fromJson(Map<String, dynamic> json) {
    return AdminMemberDetail(
      member: AdminMemberItem.fromJson(json['member'] ?? {}),
      invoices: (json['invoices'] as List<dynamic>? ?? [])
          .map((e) => AdminInvoiceItem.fromJson(e as Map<String, dynamic>))
          .toList(),
      attendance: (json['attendance'] as List<dynamic>? ?? [])
          .map((e) => AdminAttendanceItem.fromJson(e as Map<String, dynamic>))
          .toList(),
      currency: json['gym']?['currency']?.toString() ?? '₹',
      upiId: json['gym']?['upi_id']?.toString() ?? '',
      gymName: json['gym']?['name']?.toString() ?? 'Our Gym',
    );
  }
}

class AdminGymRate {
  final int id;
  final String name;
  final double charge;
  final String type;

  AdminGymRate({
    required this.id,
    required this.name,
    required this.charge,
    required this.type,
  });

  factory AdminGymRate.fromJson(Map<String, dynamic> json) {
    return AdminGymRate(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      name: json['name']?.toString() ?? '',
      charge: (json['charge'] is num) ? (json['charge'] as num).toDouble() : double.tryParse('${json['charge']}') ?? 0.0,
      type: json['type']?.toString() ?? 'Monthly',
    );
  }
}

class AdminGymQr {
  final String gymName;
  final String upiId;
  final bool hasUpi;
  final String upiPayload;
  final String currency;

  AdminGymQr({
    required this.gymName,
    required this.upiId,
    required this.hasUpi,
    required this.upiPayload,
    required this.currency,
  });

  factory AdminGymQr.fromJson(Map<String, dynamic> json) {
    return AdminGymQr(
      gymName: json['gym_name']?.toString() ?? 'Gym Owner',
      upiId: json['upi_id']?.toString() ?? '',
      hasUpi: json['has_upi'] == true,
      upiPayload: json['upi_payload']?.toString() ?? '',
      currency: json['currency']?.toString() ?? '₹',
    );
  }
}
