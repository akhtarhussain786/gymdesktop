import 'package:flutter/material.dart';
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

  void _showReceiptDialog(InvoiceItem invoice) {
    final tenant = context.read<AuthProvider>().currentTenant;

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(
          children: [
            const Icon(Icons.receipt_rounded),
            const SizedBox(width: 8),
            Text(invoice.invoiceNumber, style: const TextStyle(fontSize: 16)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(tenant?.gymName ?? 'Gym Receipt', style: const TextStyle(fontWeight: FontWeight.w700)),
            Text('Date: ${invoice.paymentDate}', style: const TextStyle(color: Colors.grey, fontSize: 12)),
            const Divider(height: 24),
            _buildReceiptRow('Service / Plan', invoice.serviceName),
            _buildReceiptRow('Duration', '${invoice.planMonths} Month(s)'),
            _buildReceiptRow('Payment Method', invoice.paymentMethod),
            if (invoice.transactionRef != null && invoice.transactionRef!.isNotEmpty)
              _buildReceiptRow('Transaction Ref', invoice.transactionRef!),
            const Divider(height: 24),
            _buildReceiptRow('Total Amount', '${tenant?.currency}${invoice.totalAmount.toStringAsFixed(2)}'),
            if (invoice.discount > 0)
              _buildReceiptRow('Discount', '-${tenant?.currency}${invoice.discount.toStringAsFixed(2)}'),
            _buildReceiptRow('Paid Amount', '${tenant?.currency}${invoice.paidAmount.toStringAsFixed(2)}', isBold: true),
            if (invoice.pendingAmount > 0)
              _buildReceiptRow('Pending Due', '${tenant?.currency}${invoice.pendingAmount.toStringAsFixed(2)}', color: AppColors.danger),
            const SizedBox(height: 12),
            Center(child: StatusBadge(status: invoice.status)),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  Widget _buildReceiptRow(String label, String value, {bool isBold = false, Color? color}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontSize: 12.5, color: Colors.grey)),
          Text(
            value,
            style: TextStyle(
              fontSize: 13,
              fontWeight: isBold ? FontWeight.w800 : FontWeight.w600,
              color: color,
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final provider = context.watch<MemberDataProvider>();
    final data = provider.payments;

    return Scaffold(
      body: Builder(
        builder: (context) {
          if (provider.loading && data == null) {
            return const Center(child: CircularProgressIndicator());
          }

          if (provider.error != null && data == null) {
            return ErrorRetryView(
              message: provider.error!,
              onRetry: () => provider.fetchPayments(refresh: true),
            );
          }

          if (data == null) {
            return const EmptyStateView(
              title: 'No Payments',
              message: 'No invoices or billing history found.',
              icon: Icons.receipt_long_outlined,
            );
          }

          final summary = data.summary;

          return RefreshIndicator(
            onRefresh: () => provider.fetchPayments(refresh: true),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Dues & Total Paid Row
                  Row(
                    children: [
                      Expanded(
                        child: StatCard(
                          title: 'Total Paid',
                          value: '${summary.currency}${summary.totalPaid.toStringAsFixed(0)}',
                          subtitle: 'Lifetime membership fees',
                          icon: Icons.check_circle_outline_rounded,
                          color: AppColors.success,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: StatCard(
                          title: 'Pending Dues',
                          value: '${summary.currency}${summary.totalDue.toStringAsFixed(0)}',
                          subtitle: summary.hasPendingDues ? 'Payment required' : 'All fees settled',
                          icon: Icons.pending_actions_rounded,
                          color: summary.hasPendingDues ? AppColors.danger : AppColors.info,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),

                  // Invoices List
                  Text(
                    'Invoice Receipts (${data.invoices.length})',
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 12),

                  if (data.invoices.isEmpty) ...[
                    const SizedBox(height: 40),
                    const Center(child: Text('No invoice receipts generated yet.')),
                  ] else ...[
                    ...data.invoices.map(
                      (inv) => Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: isDark ? AppColors.darkCard : AppColors.lightCard,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  inv.invoiceNumber,
                                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14.5),
                                ),
                                StatusBadge(status: inv.status, small: true),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              '${inv.serviceName} (${inv.planMonths} mo) • ${inv.paymentDate}',
                              style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12),
                            ),
                            const SizedBox(height: 12),
                            const Divider(height: 1),
                            const SizedBox(height: 12),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Text('Amount Paid', style: TextStyle(color: Colors.grey, fontSize: 11)),
                                    Text(
                                      '${summary.currency}${inv.paidAmount.toStringAsFixed(2)}',
                                      style: TextStyle(
                                        color: theme.primaryColor,
                                        fontWeight: FontWeight.w800,
                                        fontSize: 15,
                                      ),
                                    ),
                                  ],
                                ),
                                OutlinedButton.icon(
                                  onPressed: () => _showReceiptDialog(inv),
                                  icon: const Icon(Icons.receipt_rounded, size: 16),
                                  label: const Text('View Receipt'),
                                  style: OutlinedButton.styleFrom(
                                    visualDensity: VisualDensity.compact,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
