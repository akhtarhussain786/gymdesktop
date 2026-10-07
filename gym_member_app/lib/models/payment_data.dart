class PaymentData {
  final PaymentSummary summary;
  final List<InvoiceItem> invoices;
  final PaymentInstructions instructions;

  PaymentData({
    required this.summary,
    required this.invoices,
    required this.instructions,
  });

  factory PaymentData.fromJson(Map<String, dynamic> json) {
    return PaymentData(
      summary: PaymentSummary.fromJson(json['summary'] ?? {}),
      invoices: (json['invoices'] as List? ?? []).map((i) => InvoiceItem.fromJson(i)).toList(),
      instructions: PaymentInstructions.fromJson(json['payment_instructions'] ?? {}),
    );
  }
}

class PaymentSummary {
  final double totalPaid;
  final double totalDue;
  final String currency;
  final bool hasPendingDues;
  final double membershipFee;

  PaymentSummary({
    required this.totalPaid,
    required this.totalDue,
    required this.currency,
    required this.hasPendingDues,
    required this.membershipFee,
  });

  factory PaymentSummary.fromJson(Map<String, dynamic> json) {
    return PaymentSummary(
      totalPaid: (json['total_paid'] ?? 0).toDouble(),
      totalDue: (json['total_due'] ?? 0).toDouble(),
      currency: json['currency'] ?? '\$',
      hasPendingDues: json['has_pending_dues'] == true,
      membershipFee: (json['membership_fee'] ?? 0).toDouble(),
    );
  }
}

class InvoiceItem {
  final int id;
  final String invoiceNumber;
  final String serviceName;
  final int planMonths;
  final double totalAmount;
  final double paidAmount;
  final double discount;
  final double pendingAmount;
  final String paymentMethod;
  final String paymentDate;
  final String status;
  final String? transactionRef;
  final String? notes;
  final String? receiptUrl;

  InvoiceItem({
    required this.id,
    required this.invoiceNumber,
    required this.serviceName,
    required this.planMonths,
    required this.totalAmount,
    required this.paidAmount,
    required this.discount,
    required this.pendingAmount,
    required this.paymentMethod,
    required this.paymentDate,
    required this.status,
    this.transactionRef,
    this.notes,
    this.receiptUrl,
  });

  factory InvoiceItem.fromJson(Map<String, dynamic> json) {
    return InvoiceItem(
      id: json['id'] ?? 0,
      invoiceNumber: json['invoice_number'] ?? '',
      serviceName: json['service_name'] ?? 'Gym Fee',
      planMonths: json['plan_months'] ?? 1,
      totalAmount: (json['total_amount'] ?? 0).toDouble(),
      paidAmount: (json['paid_amount'] ?? 0).toDouble(),
      discount: (json['discount'] ?? 0).toDouble(),
      pendingAmount: (json['pending_amount'] ?? 0).toDouble(),
      paymentMethod: json['payment_method'] ?? 'Cash',
      paymentDate: json['payment_date'] ?? '',
      status: json['status'] ?? 'Paid',
      transactionRef: json['transaction_ref'],
      notes: json['notes'],
      receiptUrl: json['receipt_url'],
    );
  }
}

class PaymentInstructions {
  final String mode;
  final String contact;
  final String notice;

  PaymentInstructions({
    required this.mode,
    required this.contact,
    required this.notice,
  });

  factory PaymentInstructions.fromJson(Map<String, dynamic> json) {
    return PaymentInstructions(
      mode: json['mode'] ?? 'Reception',
      contact: json['contact'] ?? '',
      notice: json['notice'] ?? 'Visit gym reception to settle pending dues.',
    );
  }
}
