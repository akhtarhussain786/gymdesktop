import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../models/payment_data.dart';
import '../providers/auth_provider.dart';
import '../providers/member_data_provider.dart';
import '../widgets/empty_state_view.dart';
import '../widgets/error_retry_view.dart';
import '../widgets/stat_card.dart';
import '../widgets/status_badge.dart';

class PaymentsScreen extends StatefulWidget {
  const PaymentsScreen({super.key});

  @override
  State<PaymentsScreen> createState() => _PaymentsScreenState();
}

class _PaymentsScreenState extends State<PaymentsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MemberDataProvider>().fetchPayments();
    });
  }

  void _showReceiptModal(InvoiceItem invoice) {
    _showFullTaxInvoiceDialog(invoice);
  }

  void _showFullTaxInvoiceDialog(InvoiceItem invoice) {
    final auth = context.read<AuthProvider>();
    final tenant = auth.currentTenant;
    final member = auth.currentMember;

    showDialog(
      context: context,
      builder: (dialogCtx) => Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
        child: Container(
          constraints: const BoxConstraints(maxWidth: 440),
          decoration: BoxDecoration(
            color: const Color(0xFF0F151E),
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: AppColors.lime.withValues(alpha: 0.35), width: 1.5),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.8),
                blurRadius: 30,
                offset: const Offset(0, 10),
              ),
            ],
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // 1. Certificate Top Header
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: const Color(0xFF151D28),
                    borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
                    border: Border(bottom: BorderSide(color: AppColors.lime.withValues(alpha: 0.2))),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: AppColors.lime.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.limeBorder),
                        ),
                        child: const Icon(Icons.receipt_long_rounded, color: AppColors.lime, size: 24),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              tenant?.gymName.toUpperCase() ?? 'FITISIFY GYM',
                              style: GoogleFonts.outfit(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                fontSize: 16,
                                letterSpacing: 0.5,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            Text(
                              'OFFICIAL TAX INVOICE RECEIPT',
                              style: GoogleFonts.plusJakartaSans(
                                color: AppColors.lime,
                                fontSize: 10.5,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.8,
                              ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close_rounded, color: AppColors.darkTextMuted, size: 20),
                        onPressed: () => Navigator.pop(dialogCtx),
                      ),
                    ],
                  ),
                ),

                // 2. Invoice Meta Details Box
                Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Invoice # and Date Row
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.04),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('INVOICE NO', style: GoogleFonts.plusJakartaSans(fontSize: 10, color: AppColors.darkTextMuted, fontWeight: FontWeight.w700)),
                                const SizedBox(height: 2),
                                Text('#${invoice.invoiceNumber}', style: GoogleFonts.outfit(fontSize: 13, color: Colors.white, fontWeight: FontWeight.w800)),
                              ],
                            ),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Text('PAYMENT DATE', style: GoogleFonts.plusJakartaSans(fontSize: 10, color: AppColors.darkTextMuted, fontWeight: FontWeight.w700)),
                                const SizedBox(height: 2),
                                Text(invoice.paymentDate, style: GoogleFonts.outfit(fontSize: 13, color: Colors.white, fontWeight: FontWeight.w800)),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Billed To Member
                      Text('BILLED TO ATHLETE', style: GoogleFonts.plusJakartaSans(fontSize: 10.5, color: AppColors.darkTextMuted, fontWeight: FontWeight.w800, letterSpacing: 0.6)),
                      const SizedBox(height: 6),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFF131A24),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(member?.fullname ?? 'Member Athlete', style: GoogleFonts.outfit(fontWeight: FontWeight.w800, fontSize: 15, color: Colors.white)),
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                Text('Member ID: ${member?.memberId ?? "N/A"}', style: GoogleFonts.plusJakartaSans(fontSize: 12, color: AppColors.darkTextSecondary)),
                                if (member?.phone != null && member!.phone.isNotEmpty) ...[
                                  const SizedBox(width: 8),
                                  Text('• ${member.phone}', style: GoogleFonts.plusJakartaSans(fontSize: 12, color: AppColors.darkTextSecondary)),
                                ],
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Breakdown Table
                      Text('SERVICE BREAKDOWN', style: GoogleFonts.plusJakartaSans(fontSize: 10.5, color: AppColors.darkTextMuted, fontWeight: FontWeight.w800, letterSpacing: 0.6)),
                      const SizedBox(height: 6),
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: const Color(0xFF131A24),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
                        ),
                        child: Column(
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Text(
                                    invoice.serviceName,
                                    style: GoogleFonts.outfit(fontWeight: FontWeight.w700, fontSize: 14, color: Colors.white),
                                  ),
                                ),
                                Text(
                                  '${tenant?.currency ?? "₹"}${invoice.totalAmount.toStringAsFixed(2)}',
                                  style: GoogleFonts.outfit(fontWeight: FontWeight.w800, fontSize: 14, color: Colors.white),
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text('Duration Validity', style: GoogleFonts.plusJakartaSans(fontSize: 12, color: AppColors.darkTextMuted)),
                                Text('${invoice.planMonths} Month(s)', style: GoogleFonts.plusJakartaSans(fontSize: 12, color: AppColors.darkTextSecondary, fontWeight: FontWeight.w600)),
                              ],
                            ),
                            if (invoice.discount > 0) ...[
                              const SizedBox(height: 6),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text('Special Discount', style: GoogleFonts.plusJakartaSans(fontSize: 12, color: AppColors.success)),
                                  Text('-${tenant?.currency ?? "₹"}${invoice.discount.toStringAsFixed(2)}', style: GoogleFonts.plusJakartaSans(fontSize: 12, color: AppColors.success, fontWeight: FontWeight.w700)),
                                ],
                              ),
                            ],
                            const SizedBox(height: 12),
                            Container(height: 1, color: Colors.white.withValues(alpha: 0.08)),
                            const SizedBox(height: 12),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text('TOTAL AMOUNT PAID', style: GoogleFonts.outfit(fontWeight: FontWeight.w900, fontSize: 13, color: AppColors.lime, letterSpacing: 0.4)),
                                Text(
                                  '${tenant?.currency ?? "₹"}${invoice.paidAmount.toStringAsFixed(2)}',
                                  style: GoogleFonts.outfit(fontWeight: FontWeight.w900, fontSize: 20, color: AppColors.lime),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Payment Mode & Verification Badge
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        decoration: BoxDecoration(
                          color: AppColors.success.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: AppColors.success.withValues(alpha: 0.3)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.verified_rounded, color: AppColors.success, size: 22),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('PAYMENT STATUS: VERIFIED PAID', style: GoogleFonts.plusJakartaSans(fontSize: 11.5, color: AppColors.success, fontWeight: FontWeight.w800)),
                                  Text('Method: ${invoice.paymentMethod} ${invoice.transactionRef != null && invoice.transactionRef!.isNotEmpty ? "• Ref: ${invoice.transactionRef}" : ""}', style: GoogleFonts.plusJakartaSans(fontSize: 11, color: AppColors.darkTextSecondary)),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 20),

                      // Bottom Buttons
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              icon: const Icon(Icons.copy_rounded, size: 16),
                              label: const Text('Copy Receipt'),
                              style: OutlinedButton.styleFrom(
                                side: const BorderSide(color: AppColors.darkBorder),
                                foregroundColor: AppColors.darkTextPrimary,
                                padding: const EdgeInsets.symmetric(vertical: 13),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
                              ),
                              onPressed: () {
                                final text = '==============================\n'
                                    '${tenant?.gymName.toUpperCase()}\n'
                                    'OFFICIAL INVOICE RECEIPT\n'
                                    '==============================\n'
                                    'Invoice #: ${invoice.invoiceNumber}\n'
                                    'Member: ${member?.fullname} (ID: ${member?.memberId})\n'
                                    'Plan: ${invoice.serviceName} (${invoice.planMonths} Mo)\n'
                                    'Date: ${invoice.paymentDate}\n'
                                    'Paid Amount: ${tenant?.currency ?? "₹"}${invoice.paidAmount.toStringAsFixed(2)}\n'
                                    'Status: PAID (Verified)\n'
                                    'Payment Method: ${invoice.paymentMethod}\n'
                                    '==============================';
                                Clipboard.setData(ClipboardData(text: text));
                                ScaffoldMessenger.of(context).showSnackBar(
                                  const SnackBar(
                                    content: Text('✓ Full Invoice Receipt copied to clipboard!'),
                                    backgroundColor: AppColors.success,
                                    behavior: SnackBarBehavior.floating,
                                  ),
                                );
                              },
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: ElevatedButton(
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.lime,
                                padding: const EdgeInsets.symmetric(vertical: 13),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
                              ),
                              onPressed: () => Navigator.pop(dialogCtx),
                              child: Text(
                                'Done',
                                style: GoogleFonts.plusJakartaSans(
                                  fontWeight: FontWeight.w800,
                                  color: const Color(0xFF05080D),
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<MemberDataProvider>();
    final data = provider.payments;
    final tenant = context.read<AuthProvider>().currentTenant;
    final currency = tenant?.currency ?? '₹';

    if (provider.loading && data == null) {
      return Scaffold(
        backgroundColor: AppColors.bg(context),
        body: const Center(
          child: CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(AppColors.lime),
          ),
        ),
      );
    }

    if (provider.error != null && data == null) {
      return Scaffold(
        backgroundColor: AppColors.bg(context),
        body: ErrorRetryView(
          message: provider.error!,
          onRetry: () => provider.fetchPayments(refresh: true),
        ),
      );
    }

    final invoices = data?.invoices ?? [];

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: Navigator.canPop(context)
          ? AppBar(
              backgroundColor: AppColors.bgDeep(context),
              elevation: 0,
              title: Text(
                'PAYMENTS & INVOICES',
                style: GoogleFonts.outfit(
                  color: AppColors.textPrimary(context),
                  fontWeight: FontWeight.w800,
                  fontSize: 18,
                  letterSpacing: 0.5,
                ),
              ),
              actions: [
                IconButton(
                  icon: Icon(Icons.refresh_rounded, color: AppColors.textSecondary(context)),
                  onPressed: () => provider.fetchPayments(refresh: true),
                ),
              ],
            )
          : null,
      body: RefreshIndicator(
        color: AppColors.lime,
        backgroundColor: AppColors.card(context),
        onRefresh: () => provider.fetchPayments(refresh: true),
        child: invoices.isEmpty
            ? const EmptyStateView(
                icon: Icons.receipt_long_rounded,
                title: 'No Payment Receipts',
                message: 'Your payment receipts and membership invoices will appear here.',
              )
            : ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  // Payment Summary Cards
                  Row(
                    children: [
                      Expanded(
                        child: StatCard(
                          title: 'Total Paid',
                          value: '$currency${data?.summary.totalPaid.toStringAsFixed(0) ?? '0'}',
                          icon: Icons.account_balance_wallet_rounded,
                          color: AppColors.lime,
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: StatCard(
                          title: 'Pending Dues',
                          value: '$currency${data?.summary.totalDue.toStringAsFixed(0) ?? '0'}',
                          icon: Icons.pending_actions_rounded,
                          color: (data != null && data.summary.hasPendingDues) ? AppColors.danger : AppColors.success,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 22),

                  // Pending Dues Alert Banner
                  if (data != null && data.summary.hasPendingDues) ...[
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.danger.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppColors.danger.withValues(alpha: 0.3)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.warning_amber_rounded, color: AppColors.danger, size: 28),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Outstanding Dues Pending',
                                  style: GoogleFonts.outfit(
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.danger,
                                    fontSize: 14.5,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  'You have $currency${data.summary.totalDue.toStringAsFixed(2)} remaining. Please clear at the gym reception desk.',
                                  style: GoogleFonts.plusJakartaSans(
                                    fontSize: 12,
                                    color: AppColors.textSecondary(context),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 22),
                  ],

                  // Invoices List Section Title
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'PAYMENT HISTORY (${invoices.length})',
                        style: GoogleFonts.outfit(
                          color: AppColors.textPrimary(context),
                          fontWeight: FontWeight.w800,
                          fontSize: 16,
                          letterSpacing: 0.6,
                        ),
                      ),
                      Text(
                        'Tap for receipt',
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 12,
                          color: AppColors.lime,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  // Invoices List
                  ...invoices.map((inv) {
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: InkWell(
                        onTap: () => _showReceiptModal(inv),
                        borderRadius: BorderRadius.circular(16),
                        child: Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: AppColors.card(context),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: AppColors.border(context)),
                          ),
                          child: Row(
                            children: [
                              Container(
                                width: 44,
                                height: 44,
                                decoration: BoxDecoration(
                                  color: inv.status == 'Paid'
                                      ? AppColors.success.withValues(alpha: 0.12)
                                      : AppColors.danger.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Icon(
                                  inv.status == 'Paid' ? Icons.check_circle_rounded : Icons.pending_rounded,
                                  color: inv.status == 'Paid' ? AppColors.success : AppColors.danger,
                                  size: 22,
                                ),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      inv.serviceName,
                                      style: GoogleFonts.outfit(
                                        color: AppColors.textPrimary(context),
                                        fontWeight: FontWeight.w800,
                                        fontSize: 15,
                                      ),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                    const SizedBox(height: 3),
                                    Text(
                                      '#${inv.invoiceNumber} • ${inv.paymentDate}',
                                      style: GoogleFonts.plusJakartaSans(
                                        fontSize: 11.5,
                                        color: AppColors.textMuted(context),
                                        fontWeight: FontWeight.w600,
                                      ),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 8),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    '$currency${inv.paidAmount.toStringAsFixed(2)}',
                                    style: GoogleFonts.outfit(
                                      fontWeight: FontWeight.w900,
                                      fontSize: 15,
                                      color: AppColors.lime,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  StatusBadge(status: inv.status),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  }),
                ],
              ),
      ),
    );
  }
}
