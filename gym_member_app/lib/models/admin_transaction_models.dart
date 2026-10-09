class AdminTransactionSummary {
  final double totalCollection;
  final double todayCollection;
  final double thisMonthCollection;
  final double cashCollection;
  final double onlineUpiCollection;
  final double pendingDues;
  final String currency;

  AdminTransactionSummary({
    required this.totalCollection,
    required this.todayCollection,
    required this.thisMonthCollection,
    required this.cashCollection,
    required this.onlineUpiCollection,
    required this.pendingDues,
    required this.currency,
  });

  factory AdminTransactionSummary.fromJson(Map<String, dynamic> json) {
    return AdminTransactionSummary(
      totalCollection: (json['total_collection'] is num)
          ? (json['total_collection'] as num).toDouble()
          : double.tryParse('${json['total_collection']}') ?? 0.0,
      todayCollection: (json['today_collection'] is num)
          ? (json['today_collection'] as num).toDouble()
          : double.tryParse('${json['today_collection']}') ?? 0.0,
      thisMonthCollection: (json['this_month_collection'] is num)
          ? (json['this_month_collection'] as num).toDouble()
          : double.tryParse('${json['this_month_collection']}') ?? 0.0,
      cashCollection: (json['cash_collection'] is num)
          ? (json['cash_collection'] as num).toDouble()
          : double.tryParse('${json['cash_collection']}') ?? 0.0,
      onlineUpiCollection: (json['online_upi_collection'] is num)
          ? (json['online_upi_collection'] as num).toDouble()
          : double.tryParse('${json['online_upi_collection']}') ?? 0.0,
      pendingDues: (json['pending_dues'] is num)
          ? (json['pending_dues'] as num).toDouble()
          : double.tryParse('${json['pending_dues']}') ?? 0.0,
      currency: json['currency']?.toString() ?? '₹',
    );
  }
}

class AdminTransactionItem {
  final int id;
  final String invoiceNumber;
  final String transactionRef;
  final int memberId;
  final String memberName;
  final String memberPhone;
  final String? memberAvatar;
  final String serviceName;
  final int planMonths;
  final double amount;
  final double paidAmount;
  final double discount;
  final double dueAmount;
  final String paymentMethod;
  final String status;
  final String paymentDate;
  final String? dueDate;
  final String collectedBy;
  final String createdAt;
  final String notes;
  final String receiptUrl;

  AdminTransactionItem({
    required this.id,
    required this.invoiceNumber,
    required this.transactionRef,
    required this.memberId,
    required this.memberName,
    required this.memberPhone,
    this.memberAvatar,
    required this.serviceName,
    required this.planMonths,
    required this.amount,
    required this.paidAmount,
    required this.discount,
    required this.dueAmount,
    required this.paymentMethod,
    required this.status,
    required this.paymentDate,
    this.dueDate,
    required this.collectedBy,
    required this.createdAt,
    required this.notes,
    required this.receiptUrl,
  });

  factory AdminTransactionItem.fromJson(Map<String, dynamic> json) {
    return AdminTransactionItem(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      invoiceNumber: json['invoice_number']?.toString() ?? 'INV-0000',
      transactionRef: json['transaction_ref']?.toString() ?? 'TXN-0000',
      memberId: (json['member_id'] is int) ? json['member_id'] : int.tryParse('${json['member_id']}') ?? 0,
      memberName: json['member_name']?.toString() ?? 'Gym Member',
      memberPhone: json['member_phone']?.toString() ?? '',
      memberAvatar: json['member_avatar']?.toString(),
      serviceName: json['service_name']?.toString() ?? 'Membership',
      planMonths: (json['plan_months'] is int) ? json['plan_months'] : int.tryParse('${json['plan_months']}') ?? 1,
      amount: (json['amount'] is num) ? (json['amount'] as num).toDouble() : double.tryParse('${json['amount']}') ?? 0.0,
      paidAmount: (json['paid_amount'] is num) ? (json['paid_amount'] as num).toDouble() : double.tryParse('${json['paid_amount']}') ?? 0.0,
      discount: (json['discount'] is num) ? (json['discount'] as num).toDouble() : double.tryParse('${json['discount']}') ?? 0.0,
      dueAmount: (json['due_amount'] is num) ? (json['due_amount'] as num).toDouble() : double.tryParse('${json['due_amount']}') ?? 0.0,
      paymentMethod: json['payment_method']?.toString() ?? 'Cash',
      status: json['status']?.toString() ?? 'Paid',
      paymentDate: json['payment_date']?.toString() ?? '',
      dueDate: json['due_date']?.toString(),
      collectedBy: json['collected_by']?.toString() ?? 'Admin',
      createdAt: json['created_at']?.toString() ?? '',
      notes: json['notes']?.toString() ?? '',
      receiptUrl: json['receipt_url']?.toString() ?? '',
    );
  }
}

class StaffFilterOption {
  final int id;
  final String name;
  final String role;

  StaffFilterOption({required this.id, required this.name, required this.role});

  factory StaffFilterOption.fromJson(Map<String, dynamic> json) {
    return StaffFilterOption(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      name: json['name']?.toString() ?? '',
      role: json['role']?.toString() ?? '',
    );
  }
}

class MemberRegistrationDocumentData {
  final GymDocInfo gym;
  final MemberDocInfo member;
  final MembershipDocInfo membership;
  final PaymentDocInfo payment;
  final String generatedAt;

  MemberRegistrationDocumentData({
    required this.gym,
    required this.member,
    required this.membership,
    required this.payment,
    required this.generatedAt,
  });

  factory MemberRegistrationDocumentData.fromJson(Map<String, dynamic> json) {
    return MemberRegistrationDocumentData(
      gym: GymDocInfo.fromJson(json['gym'] as Map<String, dynamic>? ?? {}),
      member: MemberDocInfo.fromJson(json['member'] as Map<String, dynamic>? ?? {}),
      membership: MembershipDocInfo.fromJson(json['membership'] as Map<String, dynamic>? ?? {}),
      payment: PaymentDocInfo.fromJson(json['payment'] as Map<String, dynamic>? ?? {}),
      generatedAt: json['generated_at']?.toString() ?? '',
    );
  }
}

class GymDocInfo {
  final int id;
  final String name;
  final String phone;
  final String email;
  final String address;
  final String? logoUrl;
  final String currency;
  final String terms;

  GymDocInfo({
    required this.id,
    required this.name,
    required this.phone,
    required this.email,
    required this.address,
    this.logoUrl,
    required this.currency,
    required this.terms,
  });

  factory GymDocInfo.fromJson(Map<String, dynamic> json) {
    return GymDocInfo(
      id: (json['id'] is int) ? json['id'] : int.tryParse('${json['id']}') ?? 0,
      name: json['name']?.toString() ?? 'FITISIFY FITNESS',
      phone: json['phone']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      address: json['address']?.toString() ?? '',
      logoUrl: json['logo_url']?.toString(),
      currency: json['currency']?.toString() ?? '₹',
      terms: json['terms']?.toString() ?? '',
    );
  }
}

class MemberDocInfo {
  final int memberId;
  final String formattedMemberId;
  final String fullname;
  final String phone;
  final String email;
  final String gender;
  final String? dob;
  final String address;
  final String? avatarUrl;
  final String emergencyContact;
  final String bloodGroup;
  final String joiningDate;

  MemberDocInfo({
    required this.memberId,
    required this.formattedMemberId,
    required this.fullname,
    required this.phone,
    required this.email,
    required this.gender,
    this.dob,
    required this.address,
    this.avatarUrl,
    required this.emergencyContact,
    required this.bloodGroup,
    required this.joiningDate,
  });

  factory MemberDocInfo.fromJson(Map<String, dynamic> json) {
    return MemberDocInfo(
      memberId: (json['member_id'] is int) ? json['member_id'] : int.tryParse('${json['member_id']}') ?? 0,
      formattedMemberId: json['formatted_member_id']?.toString() ?? '#MEM-0001',
      fullname: json['fullname']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      gender: json['gender']?.toString() ?? 'Male',
      dob: json['dob']?.toString(),
      address: json['address']?.toString() ?? '',
      avatarUrl: json['avatar_url']?.toString(),
      emergencyContact: json['emergency_contact']?.toString() ?? '',
      bloodGroup: json['blood_group']?.toString() ?? '',
      joiningDate: json['joining_date']?.toString() ?? '',
    );
  }
}

class MembershipDocInfo {
  final String planName;
  final int planMonths;
  final String durationText;
  final String startDate;
  final String expiryDate;
  final String status;
  final String trainerName;

  MembershipDocInfo({
    required this.planName,
    required this.planMonths,
    required this.durationText,
    required this.startDate,
    required this.expiryDate,
    required this.status,
    required this.trainerName,
  });

  factory MembershipDocInfo.fromJson(Map<String, dynamic> json) {
    return MembershipDocInfo(
      planName: json['plan_name']?.toString() ?? 'General Fitness',
      planMonths: (json['plan_months'] is int) ? json['plan_months'] : int.tryParse('${json['plan_months']}') ?? 1,
      durationText: json['duration_text']?.toString() ?? '1 Month',
      startDate: json['start_date']?.toString() ?? '',
      expiryDate: json['expiry_date']?.toString() ?? '',
      status: json['status']?.toString() ?? 'Active',
      trainerName: json['trainer_name']?.toString() ?? 'General Floor Trainer',
    );
  }
}

class PaymentDocInfo {
  final int invoiceId;
  final String invoiceNumber;
  final double totalAmount;
  final double discount;
  final double finalPayable;
  final double paidAmount;
  final double dueAmount;
  final String paymentMethod;
  final String paymentStatus;
  final String paymentDate;
  final String transactionRef;

  PaymentDocInfo({
    required this.invoiceId,
    required this.invoiceNumber,
    required this.totalAmount,
    required this.discount,
    required this.finalPayable,
    required this.paidAmount,
    required this.dueAmount,
    required this.paymentMethod,
    required this.paymentStatus,
    required this.paymentDate,
    required this.transactionRef,
  });

  factory PaymentDocInfo.fromJson(Map<String, dynamic> json) {
    return PaymentDocInfo(
      invoiceId: (json['invoice_id'] is int) ? json['invoice_id'] : int.tryParse('${json['invoice_id']}') ?? 0,
      invoiceNumber: json['invoice_number']?.toString() ?? 'INV-0001',
      totalAmount: (json['total_amount'] is num) ? (json['total_amount'] as num).toDouble() : double.tryParse('${json['total_amount']}') ?? 0.0,
      discount: (json['discount'] is num) ? (json['discount'] as num).toDouble() : double.tryParse('${json['discount']}') ?? 0.0,
      finalPayable: (json['final_payable'] is num) ? (json['final_payable'] as num).toDouble() : double.tryParse('${json['final_payable']}') ?? 0.0,
      paidAmount: (json['paid_amount'] is num) ? (json['paid_amount'] as num).toDouble() : double.tryParse('${json['paid_amount']}') ?? 0.0,
      dueAmount: (json['due_amount'] is num) ? (json['due_amount'] as num).toDouble() : double.tryParse('${json['due_amount']}') ?? 0.0,
      paymentMethod: json['payment_method']?.toString() ?? 'Cash',
      paymentStatus: json['payment_status']?.toString() ?? 'Paid',
      paymentDate: json['payment_date']?.toString() ?? '',
      transactionRef: json['transaction_ref']?.toString() ?? '',
    );
  }
}
