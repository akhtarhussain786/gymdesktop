import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/theme/app_colors.dart';
import '../../models/admin_models.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';
import 'admin_collect_payment_dialog.dart';
import 'admin_edit_member_screen.dart';

class AdminMemberDetailScreen extends StatefulWidget {
  final int memberId;
  final String? initialName;

  const AdminMemberDetailScreen({
    super.key,
    required this.memberId,
    this.initialName,
  });

  @override
  State<AdminMemberDetailScreen> createState() => _AdminMemberDetailScreenState();
}

class _AdminMemberDetailScreenState extends State<AdminMemberDetailScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadDetails();
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadDetails() async {
    await context.read<AdminProvider>().fetchMemberDetail(widget.memberId);
  }

  void _openCollectPayment(AdminMemberItem member) {
    showDialog(
      context: context,
      builder: (_) => AdminCollectPaymentDialog(
        memberId: member.memberId,
        memberName: member.fullname,
        memberPhone: member.phone,
        currentDue: member.dueAmount,
        currentDueDate: member.dueDate,
        currentService: member.services,
        currentPlanMonths: member.planMonths,
      ),
    ).then((_) => _loadDetails());
  }

  Future<void> _sendWhatsApp(String phone, String text) async {
    final cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');
    final url = 'https://wa.me/$cleanPhone?text=${Uri.encodeComponent(text)}';
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  Future<void> _makeCall(String phone) async {
    final cleanPhone = phone.replaceAll(RegExp(r'[^0-9+]'), '');
    final uri = Uri.parse('tel:$cleanPhone');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  @override
  Widget build(BuildContext context) {
    final admin = context.watch<AdminProvider>();
    final auth = context.watch<AuthProvider>();
    final detail = admin.memberDetail;
    final currency = detail?.currency ?? auth.currentTenant?.currency ?? '₹';

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: AppBar(
        title: Text(
          detail?.member.fullname ?? widget.initialName ?? 'Member Profile',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w800, fontSize: 18),
        ),
        actions: [
          if (detail != null)
            IconButton(
              icon: const Icon(Icons.edit_rounded, color: AppColors.lime),
              tooltip: 'Edit Profile',
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => AdminEditMemberScreen(member: detail.member)),
                ).then((_) => _loadDetails());
              },
            ),
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _loadDetails,
          ),
        ],
      ),
      body: admin.isDetailLoading && detail == null
          ? const Center(child: CircularProgressIndicator(color: AppColors.lime))
          : detail == null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.person_off_rounded, size: 48, color: AppColors.textMuted(context)),
                      const SizedBox(height: 12),
                      Text(
                        admin.errorMessage ?? 'Member not found.',
                        style: GoogleFonts.plusJakartaSans(color: AppColors.textMuted(context)),
                      ),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: _loadDetails,
                        child: const Text('Try Again'),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadDetails,
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
                            // 1. Hero Profile Card
                            _buildHeroProfile(detail.member),
                            const SizedBox(height: 16),

                            // 2. Action Bar (Collect Cash, WhatsApp, Call)
                            _buildActionBar(detail.member),
                            const SizedBox(height: 20),

                            // 3. Financial & Membership Breakdown Card
                            _buildFinancialCard(detail.member, currency),
                            const SizedBox(height: 20),

                            // 4. Tab Bar (Invoices / Receipts & Attendance)
                            Container(
                              decoration: BoxDecoration(
                                color: AppColors.card(context),
                                borderRadius: BorderRadius.circular(16),
                                border: Border.all(color: AppColors.border(context)),
                              ),
                              child: Column(
                                children: [
                                  TabBar(
                                    controller: _tabController,
                                    indicatorColor: AppColors.lime,
                                    indicatorWeight: 3,
                                    labelColor: AppColors.lime,
                                    unselectedLabelColor: AppColors.textMuted(context),
                                    labelStyle: GoogleFonts.plusJakartaSans(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 13,
                                    ),
                                    tabs: [
                                      Tab(
                                        text: 'Invoices & Receipts (${detail.invoices.length})',
                                        icon: const Icon(Icons.receipt_long_rounded, size: 18),
                                      ),
                                      Tab(
                                        text: 'Attendance Log (${detail.attendance.length})',
                                        icon: const Icon(Icons.how_to_reg_rounded, size: 18),
                                      ),
                                    ],
                                  ),
                                  SizedBox(
                                    height: 380,
                                    child: TabBarView(
                                      controller: _tabController,
                                      children: [
                                        _buildInvoicesList(detail.invoices, currency),
                                        _buildAttendanceList(detail.attendance),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
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

  Widget _buildHeroProfile(AdminMemberItem member) {
    final isExpired = member.membershipStatus.toLowerCase() == 'expired';

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.card(context),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: member.dueAmount > 0
              ? AppColors.warning.withValues(alpha: 0.5)
              : AppColors.border(context),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.3),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          // Avatar
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: AppColors.cardElevated(context),
              border: Border.all(
                color: isExpired ? AppColors.danger : AppColors.lime,
                width: 2,
              ),
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
          const SizedBox(width: 16),

          // Details
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        member.fullname,
                        style: GoogleFonts.outfit(
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                          color: AppColors.textPrimary(context),
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: isExpired
                            ? AppColors.danger.withValues(alpha: 0.15)
                            : AppColors.success.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(6),
                        border: Border.all(
                          color: isExpired ? AppColors.danger : AppColors.success,
                        ),
                      ),
                      child: Text(
                        member.membershipStatus.toUpperCase(),
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          color: isExpired ? AppColors.danger : AppColors.success,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  '@${member.username} • ID #${member.memberId}',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textMuted(context),
                  ),
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    const Icon(Icons.phone_rounded, size: 14, color: AppColors.cyan),
                    const SizedBox(width: 6),
                    Text(
                      member.phone.isNotEmpty ? member.phone : 'No phone listed',
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary(context),
                      ),
                    ),
                  ],
                ),
              ],
            ),
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
          fontSize: 24,
          fontWeight: FontWeight.w900,
          color: AppColors.lime,
        ),
      ),
    );
  }

  Widget _buildActionBar(AdminMemberItem member) {
    return Row(
      children: [
        // Collect Payment
        Expanded(
          flex: 3,
          child: ElevatedButton.icon(
            onPressed: () => _openCollectPayment(member),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.lime,
              foregroundColor: Colors.black,
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              elevation: 4,
            ),
            icon: const Icon(Icons.payments_rounded, size: 18),
            label: Text(
              'Collect Payment',
              style: GoogleFonts.plusJakartaSans(
                fontWeight: FontWeight.w900,
                fontSize: 13.5,
              ),
            ),
          ),
        ),
        const SizedBox(width: 10),

        // WhatsApp
        if (member.phone.isNotEmpty) ...[
          IconButton.filled(
            onPressed: () => _sendWhatsApp(member.phone, member.whatsappReminder),
            style: IconButton.styleFrom(
              backgroundColor: const Color(0xFF25D366),
              foregroundColor: Colors.white,
              padding: const EdgeInsets.all(12),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            icon: const Icon(Icons.chat_bubble_outline_rounded, size: 20),
            tooltip: 'WhatsApp Reminder',
          ),
          const SizedBox(width: 8),

          // Direct Call
          IconButton.filled(
            onPressed: () => _makeCall(member.phone),
            style: IconButton.styleFrom(
              backgroundColor: AppColors.cardElevated(context),
              foregroundColor: AppColors.cyan,
              padding: const EdgeInsets.all(12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
                side: BorderSide(color: AppColors.border(context)),
              ),
            ),
            icon: const Icon(Icons.call_outlined, size: 20),
            tooltip: 'Call Member',
          ),
        ],
      ],
    );
  }

  Widget _buildFinancialCard(AdminMemberItem member, String currency) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.card(context),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'FINANCIAL & MEMBERSHIP SUMMARY',
            style: GoogleFonts.plusJakartaSans(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: AppColors.textMuted(context),
              letterSpacing: 0.6,
            ),
          ),
          const SizedBox(height: 14),

          // 2x2 Grid Stats
          Row(
            children: [
              Expanded(
                child: _statItem(
                  'Outstanding Due',
                  '$currency${member.dueAmount.toStringAsFixed(2)}',
                  color: member.dueAmount > 0 ? AppColors.danger : AppColors.success,
                  subtitle: member.dueDate != null ? 'Due by ${member.dueDate}' : 'Zero Dues',
                ),
              ),
              Expanded(
                child: _statItem(
                  'Total Invoiced',
                  '$currency${member.totalFee.toStringAsFixed(2)}',
                  subtitle: 'Paid: $currency${member.paidAmount.toStringAsFixed(2)}',
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          const Divider(height: 1),
          const SizedBox(height: 14),

          Row(
            children: [
              Expanded(
                child: _statItem(
                  'Active Package',
                  member.services,
                  subtitle: '${member.planMonths} Month(s) duration',
                ),
              ),
              Expanded(
                child: _statItem(
                  'Membership Expiry',
                  member.expiryDate.isNotEmpty ? member.expiryDate : 'N/A',
                  color: member.daysRemaining <= 7 ? AppColors.warning : AppColors.textPrimary(context),
                  subtitle: '${member.daysRemaining} days remaining',
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _statItem(String label, String value, {Color? color, String? subtitle}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.plusJakartaSans(
            fontSize: 11,
            fontWeight: FontWeight.w600,
            color: AppColors.textMuted(context),
          ),
        ),
        const SizedBox(height: 4),
        Text(
          value,
          style: GoogleFonts.outfit(
            fontSize: 16,
            fontWeight: FontWeight.w900,
            color: color ?? AppColors.textPrimary(context),
          ),
          overflow: TextOverflow.ellipsis,
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 2),
          Text(
            subtitle,
            style: GoogleFonts.plusJakartaSans(
              fontSize: 10.5,
              fontWeight: FontWeight.w600,
              color: AppColors.textMuted(context),
            ),
          ),
        ],
      ],
    );
  }

  Widget _buildInvoicesList(List<AdminInvoiceItem> invoices, String currency) {
    if (invoices.isEmpty) {
      return Center(
        child: Text(
          'No invoice records found.',
          style: GoogleFonts.plusJakartaSans(color: AppColors.textMuted(context)),
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: invoices.length,
      separatorBuilder: (ctx, i) => const SizedBox(height: 8),
      itemBuilder: (ctx, i) {
        final inv = invoices[i];
        final isPaid = inv.status.toLowerCase() == 'paid';

        return Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: AppColors.cardElevated(ctx),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.border(ctx)),
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: (isPaid ? AppColors.success : AppColors.warning).withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  isPaid ? Icons.check_circle_rounded : Icons.pending_actions_rounded,
                  color: isPaid ? AppColors.success : AppColors.warning,
                  size: 20,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      inv.invoiceNumber,
                      style: GoogleFonts.plusJakartaSans(
                        fontWeight: FontWeight.w800,
                        fontSize: 13,
                        color: AppColors.textPrimary(ctx),
                      ),
                    ),
                    Text(
                      '${inv.paymentDate} • ${inv.paymentMethod}',
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 11,
                        color: AppColors.textMuted(ctx),
                      ),
                    ),
                  ],
                ),
              ),
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
                  if (inv.dueAmount > 0)
                    Text(
                      'Due: $currency${inv.dueAmount.toStringAsFixed(2)}',
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                        color: AppColors.danger,
                      ),
                    ),
                ],
              ),
              if (inv.receiptUrl != null && inv.receiptUrl!.isNotEmpty) ...[
                const SizedBox(width: 8),
                IconButton(
                  icon: const Icon(Icons.receipt_rounded, size: 18, color: AppColors.cyan),
                  onPressed: () async {
                    final uri = Uri.parse(inv.receiptUrl!);
                    if (await canLaunchUrl(uri)) {
                      await launchUrl(uri, mode: LaunchMode.externalApplication);
                    }
                  },
                ),
              ],
            ],
          ),
        );
      },
    );
  }

  Widget _buildAttendanceList(List<AdminAttendanceItem> attendance) {
    if (attendance.isEmpty) {
      return Center(
        child: Text(
          'No recent attendance check-in records.',
          style: GoogleFonts.plusJakartaSans(color: AppColors.textMuted(context)),
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: attendance.length,
      separatorBuilder: (ctx, i) => const SizedBox(height: 8),
      itemBuilder: (ctx, i) {
        final att = attendance[i];
        return Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          decoration: BoxDecoration(
            color: AppColors.cardElevated(ctx),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.border(ctx)),
          ),
          child: Row(
            children: [
              const Icon(Icons.access_time_rounded, size: 16, color: AppColors.lime),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  att.date,
                  style: GoogleFonts.plusJakartaSans(
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                    color: AppColors.textPrimary(ctx),
                  ),
                ),
              ),
              Text(
                att.time,
                style: GoogleFonts.firaCode(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textMuted(ctx),
                ),
              ),
              const SizedBox(width: 10),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  'Present',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    color: AppColors.success,
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
