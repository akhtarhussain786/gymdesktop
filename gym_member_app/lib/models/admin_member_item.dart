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

  bool get hasDue => dueAmount > 0;
  bool get isExpired => membershipStatus.toLowerCase() == 'expired' || daysRemaining < 0;
  bool get isExpiringSoon => !isExpired && daysRemaining <= 7;

  factory AdminMemberItem.fromJson(Map<String, dynamic> json) {
    return AdminMemberItem(
      memberId: json['member_id'] ?? json['id'] ?? 0,
      fullname: json['fullname'] ?? '',
      username: json['username'] ?? '',
      phone: json['phone'] ?? json['contact'] ?? '',
      email: json['email'] ?? '',
      gender: json['gender'] ?? 'Not Specified',
      address: json['address'] ?? '',
      avatar: json['avatar'],
      services: json['services'] ?? 'General Fitness',
      planMonths: json['plan_months'] ?? 1,
      membershipStatus: json['membership_status'] ?? 'Active',
      startDate: json['start_date'] ?? '',
      expiryDate: json['expiry_date'] ?? '',
      daysRemaining: json['days_remaining'] ?? 0,
      totalFee: (json['total_fee'] ?? json['amount'] ?? 0).toDouble(),
      paidAmount: (json['paid_amount'] ?? 0).toDouble(),
      dueAmount: (json['due_amount'] ?? 0).toDouble(),
      dueDate: json['due_date'],
      attendanceCount: json['attendance_count'] ?? 0,
      whatsappReminder: json['whatsapp_reminder'] ?? '',
    );
  }
}
