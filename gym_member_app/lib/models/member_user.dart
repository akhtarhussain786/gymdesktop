class MemberUser {
  final int memberId;
  final int userId;
  final String fullname;
  final String username;
  final String email;
  final String phone;
  final String gender;
  final String address;
  final String? avatar;
  final String services;
  final int planMonths;
  final String membershipStatus;
  final String startDate;
  final String expiryDate;
  final int daysRemaining;
  final int attendanceCount;
  final double currentWeight;
  final double initialWeight;
  final String bodyType;
  final int branchId;

  MemberUser({
    required this.memberId,
    required this.userId,
    required this.fullname,
    required this.username,
    required this.email,
    required this.phone,
    required this.gender,
    required this.address,
    this.avatar,
    required this.services,
    required this.planMonths,
    required this.membershipStatus,
    required this.startDate,
    required this.expiryDate,
    required this.daysRemaining,
    required this.attendanceCount,
    required this.currentWeight,
    required this.initialWeight,
    required this.bodyType,
    required this.branchId,
  });

  factory MemberUser.fromJson(Map<String, dynamic> json) {
    return MemberUser(
      memberId: json['member_id'] ?? json['id'] ?? 0,
      userId: json['user_id'] ?? json['member_id'] ?? 0,
      fullname: json['fullname'] ?? 'Member',
      username: json['username'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'] ?? json['contact'] ?? '',
      gender: json['gender'] ?? 'Not Specified',
      address: json['address'] ?? '',
      avatar: json['avatar'],
      services: json['services'] ?? 'General Fitness',
      planMonths: json['plan_months'] ?? 1,
      membershipStatus: json['membership_status'] ?? 'Active',
      startDate: json['start_date'] ?? '',
      expiryDate: json['expiry_date'] ?? '',
      daysRemaining: json['days_remaining'] ?? 0,
      attendanceCount: json['attendance_count'] ?? 0,
      currentWeight: (json['current_weight'] ?? json['weight_current'] ?? 0).toDouble(),
      initialWeight: (json['initial_weight'] ?? json['weight_initial'] ?? 0).toDouble(),
      bodyType: json['body_type'] ?? 'Normal',
      branchId: json['branch_id'] ?? 1,
    );
  }
}
