import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/theme/app_colors.dart';
import '../../models/admin_models.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';
import 'admin_add_member_screen.dart';
import 'admin_attendance_screen.dart';
import 'admin_collect_payment_dialog.dart';
import 'admin_expenses_screen.dart';
import 'admin_fitness_plans_screen.dart';
import 'admin_gym_qr_screen.dart';
import 'admin_member_detail_screen.dart';
import 'admin_members_screen.dart';
import 'admin_notifications_screen.dart';
import 'admin_reports_screen.dart';
import 'admin_saas_subscription_screen.dart';
import 'admin_staffs_screen.dart';
import 'admin_transaction_history_screen.dart';

class AdminDashboardScreen extends StatefulWidget {
  final Function(int)? onNavigateTab;

  const AdminDashboardScreen({super.key, this.onNavigateTab});

  @override
  State<AdminDashboardScreen> createState() => _AdminDashboardScreenState();
}

class _AdminDashboardScreenState extends State<AdminDashboardScreen> {

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadDashboard();
    });
  }

  Future<void> _loadDashboard({bool refresh = false}) async {
    await Future.wait([
      context.read<AdminProvider>().fetchDashboard(refresh: refresh),
      context.read<AdminProvider>().fetchSaasSubscription(),
    ]);
  }

  void _openCollectDialog(AdminDueMember member) {
    showDialog(
      context: context,
      builder: (_) => AdminCollectPaymentDialog(
        memberId: member.memberId,
        memberName: member.fullname,
        memberPhone: member.phone,
        currentDue: member.dueAmount,
        currentDueDate: member.dueDate,
        currentService: member.services,
      ),
    ).then((_) => _loadDashboard(refresh: true));
  }

  Future<void> _sendWhatsAppReminder(AdminDueMember member) async {
    final currency = context.read<AuthProvider>().currentTenant?.currency ?? '₹';
    final gymName = context.read<AuthProvider>().currentTenant?.gymName ?? 'Our Gym';
    final dueBy = member.dueDate != null ? " due by *${member.dueDate}*" : "";
    final msg = "Hello ${member.fullname}, this is a reminder from *$gymName*. You have an outstanding gym fee of *$currency${member.dueAmount.toStringAsFixed(2)}*$dueBy. Please clear your dues at the gym counter or via UPI. Thank you!";

    final cleanPhone = member.phone.replaceAll(RegExp(r'[^0-9]'), '');
    final url = 'https://wa.me/$cleanPhone?text=${Uri.encodeComponent(msg)}';
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final admin = context.watch<AdminProvider>();
    final auth = context.watch<AuthProvider>();
    final dash = admin.dashboardData;
    final saas = admin.saasSubscription;
    final currency = dash?.gym.currency ?? auth.currentTenant?.currency ?? '₹';
    final gymName = dash?.gym.name ?? auth.currentTenant?.gymName ?? 'FITISIFY OS';
    final adminUser = auth.adminUser;

    final isSaasExpired = saas != null && (saas.state == 'expired' || saas.daysRemaining < 0);
    final isSaasExpiringSoon = saas != null && saas.daysRemaining <= 7 && !isSaasExpired;

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              gymName.toUpperCase(),
              style: GoogleFonts.outfit(
                fontWeight: FontWeight.w900,
                fontSize: 16,
                letterSpacing: 0.5,
              ),
              overflow: TextOverflow.ellipsis,
            ),
            Text(
              'Admin Console • ${adminUser?['fullname'] ?? "Manager"}',
              style: GoogleFonts.plusJakartaSans(
                fontSize: 11.5,
                fontWeight: FontWeight.w600,
                color: AppColors.lime,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.campaign_rounded, color: AppColors.lime),
            tooltip: 'Push Alerts & Broadcast',
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const AdminNotificationsScreen()),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.qr_code_2_rounded, color: Colors.white70),
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const AdminGymQrScreen(isModal: true)),
              );
            },
            tooltip: 'Gym UPI QR',
          ),
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => _loadDashboard(refresh: true),
          ),
        ],
      ),
      body: admin.isDashboardLoading && dash == null
          ? const Center(child: CircularProgressIndicator(color: AppColors.lime))
          : RefreshIndicator(
              onRefresh: () => _loadDashboard(refresh: true),
              color: AppColors.lime,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 600),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // SaaS Expiry Alert Banner
                        if (isSaasExpired || isSaasExpiringSoon) ...[
                          InkWell(
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(builder: (_) => const AdminSaasSubscriptionScreen()),
                              );
                            },
                            borderRadius: BorderRadius.circular(14),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                              decoration: BoxDecoration(
                                color: (isSaasExpired ? AppColors.danger : const Color(0xFFFF9F43)).withOpacity(0.18),
                                borderRadius: BorderRadius.circular(14),
                                border: Border.all(
                                  color: isSaasExpired ? AppColors.danger : const Color(0xFFFF9F43),
                                  width: 1.2,
                                ),
                              ),
                              child: Row(
                                children: [
                                  Icon(
                                    isSaasExpired ? Icons.cancel_rounded : Icons.alarm_rounded,
                                    color: isSaasExpired ? AppColors.danger : const Color(0xFFFF9F43),
                                    size: 20,
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          isSaasExpired
                                              ? '🚨 SaaS Software License Expired!'
                                              : '⚠️ SaaS License Expiring in ${saas.daysRemaining} Days (${saas.subscriptionExpiry})',
                                          style: TextStyle(
                                            color: isSaasExpired ? AppColors.danger : const Color(0xFFFF9F43),
                                            fontWeight: FontWeight.bold,
                                            fontSize: 12,
                                          ),
                                        ),
                                        const Text(
                                          'Tap here to renew via Cashfree payment gateway.',
                                          style: TextStyle(color: Colors.white70, fontSize: 11),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: Colors.white54),
                                ],
                              ),
                            ),
                          ),
                          const SizedBox(height: 14),
                        ],

                        // 1. KPI Cards Grid
                        if (dash != null) ...[
                          Row(
                            children: [
                              Expanded(
                                child: _kpiCard(
                                  title: 'ACTIVE MEMBERS',
                                  value: '${dash.kpis.activeMembers}',
                                  subtitle: '${dash.kpis.totalMembers} Registered Total',
                                  icon: Icons.people_alt_rounded,
                                  accentColor: AppColors.lime,
                                  onTap: () {
                                    if (widget.onNavigateTab != null) {
                                      widget.onNavigateTab!(1); // Go to Members
                                    }
                                  },
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: _kpiCard(
                                  title: "TODAY'S CHECK-INS",
                                  value: '${dash.kpis.todayCheckins}',
                                  subtitle: 'Live Attendance Today',
                                  icon: Icons.how_to_reg_rounded,
                                  accentColor: AppColors.cyan,
                                  onTap: () {
                                    Navigator.of(context).push(
                                      MaterialPageRoute(builder: (_) => const AdminAttendanceScreen()),
                                    );
                                  },
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: _kpiCard(
                                  title: 'TOTAL DUES PENDING',
                                  value: '$currency${dash.kpis.totalDuesAmount.toStringAsFixed(0)}',
                                  subtitle: '${dash.kpis.duesPendingCount} Members with Balance',
                                  icon: Icons.pending_actions_rounded,
                                  accentColor: dash.kpis.totalDuesAmount > 0 ? AppColors.warning : AppColors.success,
                                  onTap: () {
                                    Navigator.of(context).push(
                                      MaterialPageRoute(
                                        builder: (_) => const AdminMembersScreen(initialFilter: 'dues'),
                                      ),
                                    );
                                  },
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: _kpiCard(
                                  title: 'EXPIRING IN 7 DAYS',
                                  value: '${dash.kpis.expiring7Days}',
                                  subtitle: '${dash.kpis.expiredMembers} Expired Members',
                                  icon: Icons.timer_outlined,
                                  accentColor: AppColors.danger,
                                  onTap: () {
                                    Navigator.of(context).push(
                                      MaterialPageRoute(
                                        builder: (_) => const AdminMembersScreen(initialFilter: 'expiring'),
                                      ),
                                    );
                                  },
                                ),
                              ),
                            ],
                          ),
                        ],
                        const SizedBox(height: 20),

                        // 2. Quick Actions
                        Text(
                          'QUICK OPERATIONS',
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: AppColors.textMuted(context),
                            letterSpacing: 0.6,
                          ),
                        ),
                        const SizedBox(height: 10),

                        Row(
                          children: [
                            Expanded(
                              child: _quickActionButton(
                                icon: Icons.person_add_alt_1_rounded,
                                label: 'Add Member\n+ Photo',
                                color: AppColors.lime,
                                onTap: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute(builder: (_) => const AdminAddMemberScreen()),
                                  ).then((_) => _loadDashboard(refresh: true));
                                },
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: _quickActionButton(
                                icon: Icons.payments_rounded,
                                label: 'Collect Cash\n& Dues',
                                color: AppColors.cyan,
                                onTap: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute(
                                      builder: (_) => const AdminMembersScreen(initialFilter: 'dues'),
                                    ),
                                  );
                                },
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: _quickActionButton(
                                icon: Icons.receipt_long_rounded,
                                label: 'Transaction\nHistory',
                                color: const Color(0xFF10B981),
                                onTap: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute(builder: (_) => const AdminTransactionHistoryScreen()),
                                  ).then((_) => _loadDashboard(refresh: true));
                                },
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),

                        Row(
                          children: [
                            Expanded(
                              child: _quickActionButton(
                                icon: Icons.how_to_reg_rounded,
                                label: 'Live\nAttendance',
                                color: const Color(0xFF00CEC9),
                                onTap: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute(builder: (_) => const AdminAttendanceScreen()),
                                  );
                                },
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: _quickActionButton(
                                icon: Icons.payments_rounded,
                                label: 'Expenses\nTracker',
                                color: const Color(0xFFFF7675),
                                onTap: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute(builder: (_) => const AdminExpensesScreen()),
                                  );
                                },
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: _quickActionButton(
                                icon: Icons.campaign_rounded,
                                label: 'Broadcast\nPush Alert',
                                color: AppColors.lime,
                                onTap: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute(builder: (_) => const AdminNotificationsScreen()),
                                  );
                                },
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: _quickActionButton(
                                icon: Icons.bar_chart_rounded,
                                label: 'Financial\nReports',
                                color: const Color(0xFF0984E3),
                                onTap: () {
                                  Navigator.of(context).push(
                                    MaterialPageRoute(builder: (_) => const AdminReportsScreen()),
                                  );
                                },
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 24),

                        // 3. High Priority Dues Section
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'PENDING DUES (URGENT)',
                              style: GoogleFonts.plusJakartaSans(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                color: AppColors.textMuted(context),
                                letterSpacing: 0.6,
                              ),
                            ),
                            TextButton(
                              onPressed: () {
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => const AdminMembersScreen(initialFilter: 'dues'),
                                  ),
                                );
                              },
                              child: Text(
                                'View All Dues',
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.lime,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),

                        if (dash == null || dash.recentDues.isEmpty)
                          Container(
                            padding: const EdgeInsets.all(20),
                            decoration: BoxDecoration(
                              color: AppColors.card(context),
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(color: AppColors.border(context)),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.verified_rounded, color: AppColors.success, size: 24),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Text(
                                    'Great job! No pending dues recorded at this moment.',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontWeight: FontWeight.w600,
                                      fontSize: 13,
                                      color: AppColors.textPrimary(context),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          )
                        else
                          ListView.separated(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: dash.recentDues.length,
                            separatorBuilder: (ctx, i) => const SizedBox(height: 8),
                            itemBuilder: (ctx, i) {
                              final dueMember = dash.recentDues[i];
                              return _buildDueItemCard(dueMember, currency);
                            },
                          ),
                        const SizedBox(height: 30),
                      ],
                    ),
                  ),
                ),
              ),
            ),
    );
  }

  Widget _kpiCard({
    required String title,
    required String value,
    required String subtitle,
    required IconData icon,
    required Color accentColor,
    VoidCallback? onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: AppColors.card(context),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: AppColors.border(context)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.2),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Flexible(
                  child: Text(
                    title,
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textMuted(context),
                      letterSpacing: 0.5,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: accentColor.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(icon, size: 16, color: accentColor),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              value,
              style: GoogleFonts.outfit(
                fontSize: 22,
                fontWeight: FontWeight.w900,
                color: AppColors.textPrimary(context),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              subtitle,
              style: GoogleFonts.plusJakartaSans(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: AppColors.textMuted(context),
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }

  Widget _quickActionButton({
    required IconData icon,
    required String label,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 10),
        decoration: BoxDecoration(
          color: AppColors.card(context),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: AppColors.border(context)),
        ),
        child: Column(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: color, size: 22),
            ),
            const SizedBox(height: 8),
            Text(
              label,
              style: GoogleFonts.plusJakartaSans(
                fontSize: 11.5,
                fontWeight: FontWeight.w800,
                color: AppColors.textPrimary(context),
                height: 1.2,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDueItemCard(AdminDueMember member, String currency) {
    final dueByText = member.dueDate != null ? ' (by ${member.dueDate})' : '';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.warning.withValues(alpha: 0.3)),
      ),
      child: Row(
        children: [
          // Avatar
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.cardElevated(context),
              border: Border.all(color: AppColors.warning, width: 1.5),
            ),
            child: ClipOval(
              child: member.avatar != null && member.avatar!.isNotEmpty
                  ? Image.network(
                      member.avatar!,
                      fit: BoxFit.cover,
                      errorBuilder: (ctx, err, stack) => _avatarFallback(member.fullname),
                    )
                  : _avatarFallback(member.fullname),
            ),
          ),
          const SizedBox(width: 12),

          // Name & Due details
          Expanded(
            child: InkWell(
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => AdminMemberDetailScreen(memberId: member.memberId),
                  ),
                );
              },
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    member.fullname,
                    style: GoogleFonts.outfit(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textPrimary(context),
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                  Text(
                    '${member.services} • ${member.phone}',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 11,
                      color: AppColors.textMuted(context),
                      fontWeight: FontWeight.w600,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Due: $currency${member.dueAmount.toStringAsFixed(0)}$dueByText',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w800,
                      color: AppColors.warning,
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Collect Cash & WhatsApp
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              IconButton(
                icon: const Icon(Icons.payments_rounded, color: AppColors.lime, size: 20),
                onPressed: () => _openCollectDialog(member),
                tooltip: 'Collect Payment',
              ),
              if (member.phone.isNotEmpty)
                IconButton(
                  icon: const Icon(Icons.chat_rounded, color: Color(0xFF25D366), size: 18),
                  onPressed: () => _sendWhatsAppReminder(member),
                  tooltip: 'WhatsApp Reminder',
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _avatarFallback(String name) {
    final initials = name.trim().isNotEmpty
        ? name.trim().split(' ').map((e) => e.isNotEmpty ? e[0] : '').take(2).join()
        : 'M';
    return Center(
      child: Text(
        initials.toUpperCase(),
        style: GoogleFonts.outfit(
          fontSize: 14,
          fontWeight: FontWeight.w900,
          color: AppColors.lime,
        ),
      ),
    );
  }
}
