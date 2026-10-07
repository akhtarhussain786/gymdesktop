class MembershipData {
  final CurrentPlan currentPlan;
  final List<RenewalHistoryItem> renewalHistory;
  final GymSupportInfo gymSupport;

  MembershipData({
    required this.currentPlan,
    required this.renewalHistory,
    required this.gymSupport,
  });

  factory MembershipData.fromJson(Map<String, dynamic> json) {
    return MembershipData(
      currentPlan: CurrentPlan.fromJson(json['current_plan'] ?? {}),
      renewalHistory: (json['renewal_history'] as List? ?? [])
          .map((r) => RenewalHistoryItem.fromJson(r))
          .toList(),
      gymSupport: GymSupportInfo.fromJson(json['gym_support'] ?? {}),
    );
  }
}

class CurrentPlan {
  final String planName;
  final int durationMonths;
  final double totalFee;
  final String startDate;
  final String expiryDate;
  final int daysRemaining;
  final String status;
  final bool isExpiringSoon;
  final bool renewalDue;
  final List<String> benefits;

  CurrentPlan({
    required this.planName,
    required this.durationMonths,
    required this.totalFee,
    required this.startDate,
    required this.expiryDate,
    required this.daysRemaining,
    required this.status,
    required this.isExpiringSoon,
    required this.renewalDue,
    required this.benefits,
  });

  factory CurrentPlan.fromJson(Map<String, dynamic> json) {
    return CurrentPlan(
      planName: json['plan_name'] ?? 'General Fitness',
      durationMonths: json['duration_months'] ?? 1,
      totalFee: (json['total_fee'] ?? 0).toDouble(),
      startDate: json['start_date'] ?? '',
      expiryDate: json['expiry_date'] ?? '',
      daysRemaining: json['days_remaining'] ?? 0,
      status: json['status'] ?? 'Active',
      isExpiringSoon: json['is_expiring_soon'] == true,
      renewalDue: json['renewal_due'] == true,
      benefits: (json['benefits'] as List? ?? []).map((b) => b.toString()).toList(),
    );
  }
}

class RenewalHistoryItem {
  final int invoiceId;
  final String invoiceNumber;
  final String serviceName;
  final double paidAmount;
  final int planMonths;
  final String paymentDate;
  final String paymentMethod;
  final String status;
  final String? transactionRef;

  RenewalHistoryItem({
    required this.invoiceId,
    required this.invoiceNumber,
    required this.serviceName,
    required this.paidAmount,
    required this.planMonths,
    required this.paymentDate,
    required this.paymentMethod,
    required this.status,
    this.transactionRef,
  });

  factory RenewalHistoryItem.fromJson(Map<String, dynamic> json) {
    return RenewalHistoryItem(
      invoiceId: json['invoice_id'] ?? 0,
      invoiceNumber: json['invoice_number'] ?? '',
      serviceName: json['service_name'] ?? '',
      paidAmount: (json['paid_amount'] ?? 0).toDouble(),
      planMonths: json['plan_months'] ?? 1,
      paymentDate: json['payment_date'] ?? '',
      paymentMethod: json['payment_method'] ?? 'Cash',
      status: json['status'] ?? 'Paid',
      transactionRef: json['transaction_ref'],
    );
  }
}

class GymSupportInfo {
  final String phone;
  final String email;
  final String contactMessage;

  GymSupportInfo({
    required this.phone,
    required this.email,
    required this.contactMessage,
  });

  factory GymSupportInfo.fromJson(Map<String, dynamic> json) {
    return GymSupportInfo(
      phone: json['phone'] ?? '',
      email: json['email'] ?? '',
      contactMessage: json['contact_message'] ?? '',
    );
  }
}
