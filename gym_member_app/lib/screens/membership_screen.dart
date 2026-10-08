import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/member_data_provider.dart';
import '../widgets/error_retry_view.dart';
import '../widgets/status_badge.dart';
import 'renew_membership_dialog.dart';

class MembershipScreen extends StatefulWidget {
  const MembershipScreen({super.key});

  @override
  State<MembershipScreen> createState() => _MembershipScreenState();
}

class _MembershipScreenState extends State<MembershipScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MemberDataProvider>().fetchMembership();
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<MemberDataProvider>();

    return Scaffold(
      backgroundColor: AppColors.darkBg,
      appBar: AppBar(
        backgroundColor: AppColors.darkBgDeep,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        title: Text(
          'MEMBERSHIP & PLAN',
          style: GoogleFonts.outfit(
            color: AppColors.darkTextPrimary,
            fontWeight: FontWeight.w800,
            fontSize: 18,
            letterSpacing: 0.5,
          ),
        ),
      ),
      body: Builder(
        builder: (context) {
          if (provider.loading && provider.membership == null) {
            return const Center(
              child: CircularProgressIndicator(
                valueColor: AlwaysStoppedAnimation<Color>(AppColors.lime),
              ),
            );
          }

          if (provider.error != null && provider.membership == null) {
            return ErrorRetryView(
              message: provider.error!,
              onRetry: () => provider.fetchMembership(refresh: true),
            );
          }

          final data = provider.membership;
          if (data == null) {
            return Center(
              child: Text(
                'No membership data found.',
                style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextSecondary),
              ),
            );
          }

          final plan = data.currentPlan;

          return RefreshIndicator(
            color: AppColors.lime,
            backgroundColor: AppColors.darkCard,
            onRefresh: () => provider.fetchMembership(refresh: true),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Active Plan Card
                  Container(
                    padding: const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [
                          Color(0xFF151B23),
                          Color(0xFF0D1219),
                        ],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(22),
                      border: Border.all(
                        color: AppColors.limeBorder,
                        width: 1.5,
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.6),
                          blurRadius: 20,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                plan.planName.toUpperCase(),
                                style: GoogleFonts.outfit(
                                  color: AppColors.darkTextPrimary,
                                  fontSize: 20,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: 0.5,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            const SizedBox(width: 8),
                            StatusBadge(status: plan.status),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Expanded(child: _buildPlanMeta('Start Date', plan.startDate)),
                            const SizedBox(width: 12),
                            Expanded(child: _buildPlanMeta('Expiry Date', plan.expiryDate)),
                            const SizedBox(width: 12),
                            Expanded(child: _buildPlanMeta('Remaining', '${plan.daysRemaining} Days')),
                          ],
                        ),
                        const SizedBox(height: 20),
                        SizedBox(
                          width: double.infinity,
                          height: 46,
                          child: ElevatedButton.icon(
                            onPressed: () => RenewMembershipDialog.show(context),
                            icon: const Icon(Icons.bolt_rounded, size: 18, color: Color(0xFF05080D)),
                            label: Text(
                              'Renew / Upgrade Membership',
                              style: GoogleFonts.plusJakartaSans(
                                fontWeight: FontWeight.w800,
                                fontSize: 14,
                                color: const Color(0xFF05080D),
                              ),
                            ),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.lime,
                              elevation: 0,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 28),

                  // Upcoming Plans Queue Section
                  if (data.upcomingPlans.isNotEmpty) ...[
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'UPCOMING QUEUE',
                          style: GoogleFonts.outfit(
                            color: AppColors.darkTextPrimary,
                            fontWeight: FontWeight.w800,
                            fontSize: 16,
                            letterSpacing: 0.6,
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: AppColors.cyan.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(color: AppColors.cyanGlow),
                          ),
                          child: Text(
                            '${data.upcomingPlans.length} SCHEDULED',
                            style: GoogleFonts.plusJakartaSans(
                              color: AppColors.cyan,
                              fontWeight: FontWeight.w800,
                              fontSize: 11,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    ...data.upcomingPlans.map(
                      (up) => Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(18),
                        decoration: BoxDecoration(
                          color: AppColors.darkCard,
                          borderRadius: BorderRadius.circular(18),
                          border: Border.all(color: AppColors.cyan.withValues(alpha: 0.4), width: 1.2),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Text(
                                    up.planName,
                                    style: GoogleFonts.outfit(
                                      color: AppColors.darkTextPrimary,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 16,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                StatusBadge(status: 'Upcoming'),
                              ],
                            ),
                            const SizedBox(height: 12),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text('Start Date', style: GoogleFonts.plusJakartaSans(fontSize: 11, color: AppColors.darkTextMuted), maxLines: 1, overflow: TextOverflow.ellipsis),
                                      Text(up.startDate, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.darkTextPrimary), maxLines: 1, overflow: TextOverflow.ellipsis),
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text('Expiry Date', style: GoogleFonts.plusJakartaSans(fontSize: 11, color: AppColors.darkTextMuted), maxLines: 1, overflow: TextOverflow.ellipsis),
                                      Text(up.expiryDate, style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.darkTextPrimary), maxLines: 1, overflow: TextOverflow.ellipsis),
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    Text('Paid Fee', style: GoogleFonts.plusJakartaSans(fontSize: 11, color: AppColors.darkTextMuted)),
                                    Text(
                                      '${up.currency}${up.amount.toStringAsFixed(2)}',
                                      style: GoogleFonts.outfit(fontWeight: FontWeight.w900, color: AppColors.lime, fontSize: 15),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                  ],

                  // Plan Benefits
                  Text(
                    'INCLUDED BENEFITS',
                    style: GoogleFonts.outfit(
                      color: AppColors.darkTextPrimary,
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      letterSpacing: 0.6,
                    ),
                  ),
                  const SizedBox(height: 12),
                  ...plan.benefits.map(
                    (b) => Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.darkCard,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppColors.darkBorder),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.check_circle_rounded, color: AppColors.lime, size: 20),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              b,
                              style: GoogleFonts.plusJakartaSans(
                                color: AppColors.darkTextPrimary,
                                fontWeight: FontWeight.w600,
                                fontSize: 13.5,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 28),

                  // Renewal History
                  Text(
                    'RENEWAL HISTORY',
                    style: GoogleFonts.outfit(
                      color: AppColors.darkTextPrimary,
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      letterSpacing: 0.6,
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (data.renewalHistory.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(20),
                      alignment: Alignment.center,
                      child: Text(
                        'No renewal history logged yet.',
                        style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextMuted),
                      ),
                    )
                  else
                    ...data.renewalHistory.map(
                      (r) => Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: AppColors.darkCard,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: AppColors.darkBorder),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    r.invoiceNumber,
                                    style: GoogleFonts.outfit(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.darkTextPrimary),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    '${r.serviceName} • ${r.paymentDate}',
                                    style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextSecondary, fontSize: 12),
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
                                  '₹${r.paidAmount.toStringAsFixed(2)}',
                                  style: GoogleFonts.outfit(
                                    fontWeight: FontWeight.w900,
                                    color: AppColors.lime,
                                    fontSize: 15,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                StatusBadge(status: r.status),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildPlanMeta(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: GoogleFonts.plusJakartaSans(
            color: AppColors.darkTextMuted,
            fontSize: 10,
            fontWeight: FontWeight.w800,
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: GoogleFonts.outfit(
            color: AppColors.darkTextPrimary,
            fontWeight: FontWeight.w800,
            fontSize: 13.5,
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ],
    );
  }
}
