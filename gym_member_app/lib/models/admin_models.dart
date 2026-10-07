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
  final double monthlyRevenue;
  final double monthlyExpenses;
  final double netProfit;
  final int totalStaff;
  final int totalEquipment;

  AdminKpis({
    required this.totalMembers,
    required this.activeMembers,
    required this.expiredMembers,
    required this.expiring7Days,
    required this.todayCheckins,
    required this.duesPendingCount,
    required this.totalDuesAmount,
    this.monthlyRevenue = 0.0,
    this.monthlyExpenses = 0.0,
    this.netProfit = 0.0,
    this.totalStaff = 0,
    this.totalEquipment = 0,
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
      monthlyRevenue: (json['monthly_revenue'] is num) ? (json['monthly_revenue'] as num).toDouble() : double.tryParse('${json['monthly_revenue']}') ?? 0.0,
      monthlyExpenses: (json['monthly_expenses'] is num) ? (json['monthly_expenses'] as num).toDouble() : double.tryParse('${json['monthly_expenses']}') ?? 0.0,
      netProfit: (json['net_profit'] is num) ? (json['net_profit'] as num).toDouble() : double.tryParse('${json['net_profit']}') ?? 0.0,
      totalStaff: (json['total_staff'] is int) ? json['total_staff'] : int.tryParse('${json['total_staff']}') ?? 0,
      totalEquipment: (json['total_equipment'] is int) ? json['total_equipment'] : int.tryParse('${json['total_equipment']}') ?? 0,
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

// 1. Staff & Trainers Model
class AdminStaffItem {
  final int id;
  final String fullname;
  final String username;
  final String email;
  final String phone;
  final String designation;
  final String gender;
  final String address;
  final double salary;
  final String status;

  AdminStaffItem({
    required this.id,
    required this.fullname,
    required this.username,
    required this.email,
    required this.phone,
    required this.designation,
    required this.gender,
    required this.address,
    required this.salary,
    required this.status,
  });

  factory AdminStaffItem.fromJson(Map<String, dynamic> json) {
    return AdminStaffItem(
      id: (json['id'] is int) ? json['id'] : (json['user_id'] is int ? json['user_id'] : int.tryParse('${json['id'] ?? json['user_id']}') ?? 0),
      fullname: json['fullname']?.toString() ?? '',
      username: json['username']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      phone: json['phone']?.toString() ?? (json['contact']?.toString() ?? ''),
      designation: json['designation']?.toString() ?? 'Trainer',
      gender: json['gender']?.toString() ?? 'Male',
      address: json['address']?.toString() ?? '',
      salary: (json['salary'] is num) ? (json['salary'] as num).toDouble() : double.tryParse('${json['salary']}') ?? 0.0,
      status: json['status']?.toString() ?? 'Active',
    );
  }
}

// 2. Daily Attendance Model
class AdminDailyAttendanceItem {
  final int id;
  final int memberId;
  final String fullname;
  final String contact;
  final String? avatar;
  final String currDate;
  final String currTime;
  final String? checkOutTime;
  final String present;
  final String status;

  AdminDailyAttendanceItem({
    required this.id,
    required this.memberId,
    required this.fullname,
    required this.contact,
    this.avatar,
    required this.currDate,
    required this.currTime,
    this.checkOutTime,
    required this.present,
    required this.status,
  });

  factory AdminDailyAttendanceItem.fromJson(Map<String, dynamic> json) {
    return AdminDailyAttendanceItem(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      memberId: (json['member_id'] is int) ? json['member_id'] : (json['user_id'] is int ? json['user_id'] : int.tryParse('${json['member_id'] ?? json['user_id']}') ?? 0),
      fullname: json['fullname']?.toString() ?? 'Member',
      contact: json['contact']?.toString() ?? '',
      avatar: json['avatar']?.toString() ?? json['photo']?.toString(),
      currDate: json['curr_date']?.toString() ?? '',
      currTime: json['curr_time']?.toString() ?? '',
      checkOutTime: json['checkout_time']?.toString(),
      present: json['present']?.toString() ?? '1',
      status: json['status']?.toString() ?? 'Checked In',
    );
  }
}

// 3. Expenses Model
class AdminExpenseItem {
  final int id;
  final String title;
  final double amount;
  final String category;
  final String expenseDate;
  final String notes;
  final String paymentMode;

  AdminExpenseItem({
    required this.id,
    required this.title,
    required this.amount,
    required this.category,
    required this.expenseDate,
    required this.notes,
    required this.paymentMode,
  });

  factory AdminExpenseItem.fromJson(Map<String, dynamic> json) {
    return AdminExpenseItem(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      title: json['title']?.toString() ?? (json['name']?.toString() ?? 'Expense'),
      amount: (json['amount'] is num) ? (json['amount'] as num).toDouble() : double.tryParse('${json['amount']}') ?? 0.0,
      category: json['category']?.toString() ?? 'General',
      expenseDate: json['expense_date']?.toString() ?? (json['date']?.toString() ?? ''),
      notes: json['notes']?.toString() ?? (json['description']?.toString() ?? ''),
      paymentMode: json['payment_mode']?.toString() ?? 'Cash',
    );
  }
}

// 4. Equipment Model
class AdminEquipmentItem {
  final int id;
  final String name;
  final String description;
  final int quantity;
  final double amount;
  final String vendor;
  final String contact;
  final String datePurchased;
  final String address;

  AdminEquipmentItem({
    required this.id,
    required this.name,
    required this.description,
    required this.quantity,
    required this.amount,
    required this.vendor,
    required this.contact,
    required this.datePurchased,
    required this.address,
  });

  factory AdminEquipmentItem.fromJson(Map<String, dynamic> json) {
    return AdminEquipmentItem(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      name: json['name']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      quantity: (json['quantity'] is int) ? json['quantity'] : int.tryParse('${json['quantity']}') ?? 1,
      amount: (json['amount'] is num) ? (json['amount'] as num).toDouble() : double.tryParse('${json['amount']}') ?? 0.0,
      vendor: json['vendor']?.toString() ?? '',
      contact: json['contact']?.toString() ?? '',
      datePurchased: json['date']?.toString() ?? (json['date_purchased']?.toString() ?? ''),
      address: json['address']?.toString() ?? '',
    );
  }
}

// 5. Workout Plans Model
class AdminWorkoutItem {
  final int id;
  final String name;
  final String goal;
  final String daysJson;
  final String exercisesJson;
  final String description;

  AdminWorkoutItem({
    required this.id,
    required this.name,
    required this.goal,
    required this.daysJson,
    required this.exercisesJson,
    required this.description,
  });

  factory AdminWorkoutItem.fromJson(Map<String, dynamic> json) {
    return AdminWorkoutItem(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      name: json['name']?.toString() ?? '',
      goal: json['goal']?.toString() ?? 'Muscle Building',
      daysJson: json['days_json']?.toString() ?? '',
      exercisesJson: json['exercises_json']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
    );
  }
}

// 6. Diet Plans Model
class AdminDietItem {
  final int id;
  final String name;
  final String target;
  final int calories;
  final String description;
  final String mealsJson;

  AdminDietItem({
    required this.id,
    required this.name,
    required this.target,
    required this.calories,
    required this.description,
    required this.mealsJson,
  });

  factory AdminDietItem.fromJson(Map<String, dynamic> json) {
    return AdminDietItem(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      name: json['name']?.toString() ?? '',
      target: json['target']?.toString() ?? 'High Protein',
      calories: (json['calories'] is int) ? json['calories'] : int.tryParse('${json['calories']}') ?? 2200,
      description: json['description']?.toString() ?? '',
      mealsJson: json['meals_json']?.toString() ?? '',
    );
  }
}

// 7. Classes & Schedule Model
class AdminClassItem {
  final int id;
  final String title;
  final int? trainerId;
  final String instructorName;
  final String dayOfWeek;
  final String startTime;
  final String endTime;
  final int capacity;
  final String room;
  final String status;

  AdminClassItem({
    required this.id,
    required this.title,
    this.trainerId,
    required this.instructorName,
    required this.dayOfWeek,
    required this.startTime,
    required this.endTime,
    required this.capacity,
    required this.room,
    required this.status,
  });

  factory AdminClassItem.fromJson(Map<String, dynamic> json) {
    return AdminClassItem(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      title: json['title']?.toString() ?? '',
      trainerId: (json['trainer_id'] is int) ? json['trainer_id'] : int.tryParse('${json['trainer_id']}'),
      instructorName: json['instructor_name']?.toString() ?? 'Instructor',
      dayOfWeek: json['day_of_week']?.toString() ?? 'Monday',
      startTime: json['start_time']?.toString() ?? '06:00',
      endTime: json['end_time']?.toString() ?? '07:00',
      capacity: (json['capacity'] is int) ? json['capacity'] : int.tryParse('${json['capacity']}') ?? 20,
      room: json['room']?.toString() ?? 'Main Studio',
      status: json['status']?.toString() ?? 'active',
    );
  }
}

// 8. Announcements Model
class AdminAnnouncementItem {
  final int id;
  final String title;
  final String message;
  final String date;

  AdminAnnouncementItem({
    required this.id,
    required this.title,
    required this.message,
    required this.date,
  });

  factory AdminAnnouncementItem.fromJson(Map<String, dynamic> json) {
    return AdminAnnouncementItem(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      title: json['title']?.toString() ?? '',
      message: json['message']?.toString() ?? '',
      date: json['date']?.toString() ?? '',
    );
  }
}

// 9. Inquiries Model
class AdminInquiryItem {
  final int id;
  final int memberId;
  final String fullname;
  final String username;
  final String contact;
  final String subject;
  final String message;
  final String reply;
  final String status;
  final String createdAt;

  AdminInquiryItem({
    required this.id,
    required this.memberId,
    required this.fullname,
    required this.username,
    required this.contact,
    required this.subject,
    required this.message,
    required this.reply,
    required this.status,
    required this.createdAt,
  });

  factory AdminInquiryItem.fromJson(Map<String, dynamic> json) {
    return AdminInquiryItem(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      memberId: (json['member_id'] is int) ? json['member_id'] : int.tryParse('${json['member_id']}') ?? 0,
      fullname: json['fullname']?.toString() ?? 'Member',
      username: json['username']?.toString() ?? '',
      contact: json['contact']?.toString() ?? '',
      subject: json['subject']?.toString() ?? (json['type']?.toString() ?? 'Inquiry'),
      message: json['message']?.toString() ?? '',
      reply: json['reply']?.toString() ?? '',
      status: json['status']?.toString() ?? 'open',
      createdAt: json['created_at']?.toString() ?? '',
    );
  }
}

// 10. Reports Data Model
class AdminMonthlyTrendItem {
  final String month;
  final double revenue;
  final double expenses;
  final double net;

  AdminMonthlyTrendItem({
    required this.month,
    required this.revenue,
    required this.expenses,
    required this.net,
  });

  factory AdminMonthlyTrendItem.fromJson(Map<String, dynamic> json) {
    return AdminMonthlyTrendItem(
      month: json['month']?.toString() ?? '',
      revenue: (json['revenue'] is num) ? (json['revenue'] as num).toDouble() : double.tryParse('${json['revenue']}') ?? 0.0,
      expenses: (json['expenses'] is num) ? (json['expenses'] as num).toDouble() : double.tryParse('${json['expenses']}') ?? 0.0,
      net: (json['net'] is num) ? (json['net'] as num).toDouble() : double.tryParse('${json['net']}') ?? 0.0,
    );
  }
}

class AdminReportsData {
  final double revenue;
  final double expenses;
  final double netProfit;
  final int attendanceCount;
  final int newMembersCount;
  final List<AdminMonthlyTrendItem> monthlyTrend;
  final Map<String, dynamic> membersSummary;

  AdminReportsData({
    required this.revenue,
    required this.expenses,
    required this.netProfit,
    required this.attendanceCount,
    required this.newMembersCount,
    required this.monthlyTrend,
    required this.membersSummary,
  });

  factory AdminReportsData.fromJson(Map<String, dynamic> json) {
    return AdminReportsData(
      revenue: (json['revenue'] is num) ? (json['revenue'] as num).toDouble() : double.tryParse('${json['revenue']}') ?? 0.0,
      expenses: (json['expenses'] is num) ? (json['expenses'] as num).toDouble() : double.tryParse('${json['expenses']}') ?? 0.0,
      netProfit: (json['net_profit'] is num) ? (json['net_profit'] as num).toDouble() : double.tryParse('${json['net_profit']}') ?? 0.0,
      attendanceCount: (json['attendance_count'] is int) ? json['attendance_count'] : int.tryParse('${json['attendance_count']}') ?? 0,
      newMembersCount: (json['new_members_count'] is int) ? json['new_members_count'] : int.tryParse('${json['new_members_count']}') ?? 0,
      monthlyTrend: (json['monthly_trend'] as List<dynamic>? ?? [])
          .map((e) => AdminMonthlyTrendItem.fromJson(e as Map<String, dynamic>))
          .toList(),
      membersSummary: (json['members_summary'] is Map<String, dynamic>) ? json['members_summary'] : {},
    );
  }
}

// 11. Settings Data Model
class AdminSettingsData {
  final int id;
  final String gymName;
  final String email;
  final String phone;
  final String address;
  final String currency;
  final String timezone;
  final String primaryColor;
  final String secondaryColor;
  final String upiId;
  final String? logo;
  final String? logoUrl;

  AdminSettingsData({
    required this.id,
    required this.gymName,
    required this.email,
    required this.phone,
    required this.address,
    required this.currency,
    required this.timezone,
    required this.primaryColor,
    required this.secondaryColor,
    required this.upiId,
    this.logo,
    this.logoUrl,
  });

  factory AdminSettingsData.fromJson(Map<String, dynamic> json) {
    return AdminSettingsData(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      gymName: json['gym_name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      address: json['address']?.toString() ?? '',
      currency: json['currency']?.toString() ?? '₹',
      timezone: json['timezone']?.toString() ?? 'Asia/Kolkata',
      primaryColor: json['primary_color']?.toString() ?? '#3b82f6',
      secondaryColor: json['secondary_color']?.toString() ?? '#10b981',
      upiId: json['upi_id']?.toString() ?? '',
      logo: json['logo']?.toString(),
      logoUrl: json['logo_url']?.toString(),
    );
  }
}
