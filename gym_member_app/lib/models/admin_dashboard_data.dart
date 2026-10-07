import 'admin_member_item.dart';

class AdminDashboardData {
  final int totalMembers;
  final int activeMembers;
  final int expiredMembers;
  final int expiring7Days;
  final int todayCheckins;
  final int duesPendingCount;
  final double totalDuesAmount;
  final List<AdminMemberItem> recentDues;
  final String gymName;
  final String gymCode;
  final String currency;
  final String? logo;
  final String upiId;

  AdminDashboardData({
    required this.totalMembers,
    required this.activeMembers,
    required this.expiredMembers,
    required this.expiring7Days,
    required this.todayCheckins,
    required this.duesPendingCount,
    required this.totalDuesAmount,
    required this.recentDues,
    required this.gymName,
    required this.gymCode,
    required this.currency,
    this.logo,
    required this.upiId,
  });

  factory AdminDashboardData.fromJson(Map<String, dynamic> json) {
    final kpis = json['kpis'] ?? {};
    final gym = json['gym'] ?? {};
    final recentDuesList = (json['recent_dues'] as List? ?? [])
        .map((e) => AdminMemberItem.fromJson(Map<String, dynamic>.from(e)))
        .toList();

    return AdminDashboardData(
      totalMembers: kpis['total_members'] ?? 0,
      activeMembers: kpis['active_members'] ?? 0,
      expiredMembers: kpis['expired_members'] ?? 0,
      expiring7Days: kpis['expiring_7days'] ?? 0,
      todayCheckins: kpis['today_checkins'] ?? 0,
      duesPendingCount: kpis['dues_pending_count'] ?? 0,
      totalDuesAmount: (kpis['total_dues_amount'] ?? 0).toDouble(),
      recentDues: recentDuesList,
      gymName: gym['name'] ?? 'Gym',
      gymCode: gym['code'] ?? '',
      currency: gym['currency'] ?? '₹',
      logo: gym['logo'],
      upiId: gym['upi_id'] ?? '',
    );
  }
}
