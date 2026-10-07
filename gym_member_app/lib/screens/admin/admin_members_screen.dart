import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/theme/app_colors.dart';
import '../../models/admin_models.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';
import 'admin_add_member_screen.dart';
import 'admin_collect_payment_dialog.dart';
import 'admin_edit_member_screen.dart';
import 'admin_member_detail_screen.dart';

class AdminMembersScreen extends StatefulWidget {
  final String? initialFilter;

  const AdminMembersScreen({super.key, this.initialFilter});

  @override
  State<AdminMembersScreen> createState() => _AdminMembersScreenState();
}

class _AdminMembersScreenState extends State<AdminMembersScreen> {
  final _searchController = TextEditingController();
  String _activeFilter = 'all';
  String _activeSort = 'recent';

  @override
  void initState() {
    super.initState();
    if (widget.initialFilter != null) {
      _activeFilter = widget.initialFilter!;
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadMembers();
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadMembers({bool refresh = false}) async {
    await context.read<AdminProvider>().fetchMembers(
      search: _searchController.text.trim(),
      filter: _activeFilter,
      sort: _activeSort,
      refresh: refresh,
    );
  }

  void _openCollectDialog(AdminMemberItem member) {
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
    ).then((_) => _loadMembers(refresh: true));
  }

  Future<void> _sendWhatsApp(String phone, String text) async {
    final cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');
    final url = 'https://wa.me/$cleanPhone?text=${Uri.encodeComponent(text)}';
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final admin = context.watch<AdminProvider>();
    final currency = context.watch<AuthProvider>().currentTenant?.currency ?? '₹';

    final totalMembers = admin.members.length;
    final activeCount = admin.members.where((m) => m.membershipStatus.toLowerCase() == 'active' && m.daysRemaining >= 0).length;
    final expiringCount = admin.members.where((m) => m.daysRemaining >= 0 && m.daysRemaining <= 7 && m.membershipStatus.toLowerCase() != 'expired').length;
    final expiredCount = admin.members.where((m) => m.membershipStatus.toLowerCase() == 'expired' || m.daysRemaining < 0).length;
    final dueCount = admin.members.where((m) => m.dueAmount > 0).length;

    return Scaffold(
      backgroundColor: const Color(0xFF13131A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E1E2C),
        elevation: 0,
        title: Text(
          'Members Directory',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w900, fontSize: 18, color: Colors.white),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: Colors.white70),
            onPressed: () => _loadMembers(refresh: true),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const AdminAddMemberScreen()),
          ).then((_) => _loadMembers(refresh: true));
        },
        backgroundColor: AppColors.lime,
        foregroundColor: Colors.black,
        icon: const Icon(Icons.person_add_alt_1_rounded),
        label: Text(
          'Add Member',
          style: GoogleFonts.plusJakartaSans(
            fontWeight: FontWeight.w900,
            fontSize: 13,
          ),
        ),
      ),
      body: Column(
        children: [
          // 1. Search Bar & Sort Menu
          Container(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            color: const Color(0xFF1E1E2C),
            child: Row(
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _searchController,
                    style: const TextStyle(color: Colors.white, fontSize: 13.5),
                    decoration: InputDecoration(
                      hintText: 'Search by Name, Phone, ID...',
                      hintStyle: const TextStyle(color: Colors.white38),
                      prefixIcon: const Icon(Icons.search_rounded, size: 20, color: AppColors.lime),
                      suffixIcon: _searchController.text.isNotEmpty
                          ? IconButton(
                              icon: const Icon(Icons.clear_rounded, size: 18, color: Colors.white54),
                              onPressed: () {
                                _searchController.clear();
                                _loadMembers();
                              },
                            )
                          : null,
                      filled: true,
                      fillColor: const Color(0xFF13131A),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                      contentPadding: const EdgeInsets.symmetric(vertical: 0, horizontal: 16),
                    ),
                    onChanged: (v) {
                      _loadMembers();
                    },
                  ),
                ),
                const SizedBox(width: 8),
                PopupMenuButton<String>(
                  icon: Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFF13131A),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(Icons.sort_rounded, color: AppColors.lime, size: 20),
                  ),
                  color: const Color(0xFF2A2A3E),
                  onSelected: (v) {
                    setState(() => _activeSort = v);
                    _loadMembers();
                  },
                  itemBuilder: (ctx) => const [
                    PopupMenuItem(value: 'recent', child: Text('Most Recent Joined', style: TextStyle(color: Colors.white))),
                    PopupMenuItem(value: 'expiry_soon', child: Text('Expiring Soonest (Alert)', style: TextStyle(color: Colors.white))),
                    PopupMenuItem(value: 'due_high', child: Text('Highest Due Amount', style: TextStyle(color: Colors.white))),
                    PopupMenuItem(value: 'name_asc', child: Text('Name (A to Z)', style: TextStyle(color: Colors.white))),
                  ],
                ),
              ],
            ),
          ),

          // 2. Filter Chips Row
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: Row(
              children: [
                _filterChip('all', 'All ($totalMembers)'),
                _filterChip('expiring', 'Expiring Soon ($expiringCount)'),
                _filterChip('active', 'Active ($activeCount)'),
                _filterChip('dues', 'Pending Dues ($dueCount)'),
                _filterChip('expired', 'Expired ($expiredCount)'),
              ],
            ),
          ),

          // 3. Members List
          Expanded(
            child: admin.isMembersLoading && admin.members.isEmpty
                ? const Center(child: CircularProgressIndicator(color: AppColors.lime))
                : admin.members.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.people_outline_rounded, size: 56, color: Colors.white.withOpacity(0.3)),
                            const SizedBox(height: 12),
                            const Text(
                              'No members found for this filter.',
                              style: TextStyle(color: Colors.white70, fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(height: 8),
                            TextButton.icon(
                              onPressed: () {
                                _searchController.clear();
                                setState(() => _activeFilter = 'all');
                                _loadMembers();
                              },
                              icon: const Icon(Icons.clear_all_rounded, size: 16, color: AppColors.lime),
                              label: const Text('Reset Filters', style: TextStyle(color: AppColors.lime)),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () => _loadMembers(refresh: true),
                        color: AppColors.lime,
                        child: ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
                          itemCount: admin.members.length,
                          separatorBuilder: (ctx, i) => const SizedBox(height: 12),
                          itemBuilder: (ctx, i) {
                            final member = admin.members[i];
                            return _buildMemberCard(member, currency);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _filterChip(String key, String label) {
    final isSelected = _activeFilter == key;
    final isExpiringKey = key == 'expiring';
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: FilterChip(
        label: Text(label),
        selected: isSelected,
        selectedColor: isExpiringKey ? const Color(0xFFFF9F43) : AppColors.lime,
        checkmarkColor: Colors.black,
        backgroundColor: const Color(0xFF1E1E2C),
        labelStyle: TextStyle(
          fontSize: 11.5,
          fontWeight: FontWeight.bold,
          color: isSelected ? Colors.black : (isExpiringKey ? const Color(0xFFFF9F43) : Colors.white70),
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
          side: BorderSide(
            color: isSelected
                ? (isExpiringKey ? const Color(0xFFFF9F43) : AppColors.lime)
                : (isExpiringKey ? const Color(0xFFFF9F43).withOpacity(0.3) : Colors.white.withOpacity(0.06)),
          ),
        ),
        onSelected: (s) {
          setState(() => _activeFilter = key);
          _loadMembers();
        },
      ),
    );
  }

  Widget _buildExpiryAlertBanner(AdminMemberItem member) {
    final isExpired = member.membershipStatus.toLowerCase() == 'expired' || member.daysRemaining < 0;
    final days = member.daysRemaining;

    if (isExpired) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(
          color: AppColors.danger.withOpacity(0.18),
          borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
          border: Border(bottom: BorderSide(color: AppColors.danger.withOpacity(0.3))),
        ),
        child: Row(
          children: [
            const Icon(Icons.cancel_rounded, size: 14, color: AppColors.danger),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                '🔴 Membership Expired (${member.expiryDate})',
                style: const TextStyle(color: AppColors.danger, fontSize: 11.5, fontWeight: FontWeight.bold),
              ),
            ),
            const Text('Needs Renewal', style: TextStyle(color: AppColors.danger, fontSize: 10.5, fontWeight: FontWeight.w700)),
          ],
        ),
      );
    } else if (days == 0) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(
          color: const Color(0xFFFF5252).withOpacity(0.25),
          borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
          border: const Border(bottom: BorderSide(color: Color(0xFFFF5252))),
        ),
        child: Row(
          children: [
            const Icon(Icons.warning_amber_rounded, size: 15, color: Color(0xFFFF5252)),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                '🚨 Expiring Today (${member.expiryDate})!',
                style: const TextStyle(color: Color(0xFFFF5252), fontSize: 11.5, fontWeight: FontWeight.bold),
              ),
            ),
            const Text('Action Required', style: TextStyle(color: Color(0xFFFF5252), fontSize: 10.5, fontWeight: FontWeight.w700)),
          ],
        ),
      );
    } else if (days == 1) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(
          color: const Color(0xFFFF9F43).withOpacity(0.22),
          borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
          border: const Border(bottom: BorderSide(color: Color(0xFFFF9F43))),
        ),
        child: Row(
          children: [
            const Icon(Icons.alarm_on_rounded, size: 15, color: Color(0xFFFF9F43)),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                '⚠️ Expiring Tomorrow! (1 Day Left)',
                style: const TextStyle(color: Color(0xFFFF9F43), fontSize: 11.5, fontWeight: FontWeight.bold),
              ),
            ),
            Text(member.expiryDate, style: const TextStyle(color: Color(0xFFFF9F43), fontSize: 11, fontWeight: FontWeight.w700)),
          ],
        ),
      );
    } else if (days == 2) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
        decoration: BoxDecoration(
          color: const Color(0xFFFF9F43).withOpacity(0.22),
          borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
          border: const Border(bottom: BorderSide(color: Color(0xFFFF9F43))),
        ),
        child: Row(
          children: [
            const Icon(Icons.schedule_rounded, size: 15, color: Color(0xFFFF9F43)),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                '⚠️ Expiring in 2 Days! (${member.expiryDate})',
                style: const TextStyle(color: Color(0xFFFF9F43), fontSize: 11.5, fontWeight: FontWeight.bold),
              ),
            ),
            const Text('2 Days Left', style: TextStyle(color: Color(0xFFFF9F43), fontSize: 11, fontWeight: FontWeight.w700)),
          ],
        ),
      );
    } else if (days <= 5) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(
          color: const Color(0xFFFECA57).withOpacity(0.12),
          borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
          border: Border(bottom: BorderSide(color: const Color(0xFFFECA57).withOpacity(0.3))),
        ),
        child: Row(
          children: [
            const Icon(Icons.hourglass_bottom_rounded, size: 14, color: Color(0xFFFECA57)),
            const SizedBox(width: 6),
            Expanded(
              child: Text(
                '⏳ Expiring in $days Days (${member.expiryDate})',
                style: const TextStyle(color: Color(0xFFFECA57), fontSize: 11.5, fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ),
      );
    }
    return const SizedBox.shrink();
  }

  Widget _buildMemberCard(AdminMemberItem member, String currency) {
    final isExpired = member.membershipStatus.toLowerCase() == 'expired' || member.daysRemaining < 0;
    final isExpiringCritical = member.daysRemaining <= 2 && member.daysRemaining >= 0 && !isExpired;
    final isExpiringSoon = member.daysRemaining <= 7 && !isExpired;
    final hasDue = member.dueAmount > 0;

    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF1E1E2C),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: isExpiringCritical
              ? const Color(0xFFFF9F43)
              : (isExpired
                  ? AppColors.danger.withOpacity(0.5)
                  : (hasDue ? AppColors.warning.withOpacity(0.5) : Colors.white.withOpacity(0.06))),
          width: isExpiringCritical ? 1.5 : 1.0,
        ),
        boxShadow: [
          BoxShadow(
            color: isExpiringCritical
                ? const Color(0xFFFF9F43).withOpacity(0.15)
                : Colors.black.withOpacity(0.2),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        children: [
          _buildExpiryAlertBanner(member),

          // Main Info Row
          InkWell(
            onTap: () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => AdminMemberDetailScreen(
                    memberId: member.memberId,
                    initialName: member.fullname,
                  ),
                ),
              ).then((_) => _loadMembers(refresh: true));
            },
            borderRadius: const BorderRadius.vertical(top: Radius.circular(18)),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Avatar
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: const Color(0xFF2A2A3E),
                      border: Border.all(
                        color: isExpired ? AppColors.danger : (hasDue ? AppColors.warning : AppColors.lime),
                        width: 1.5,
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
                  const SizedBox(width: 12),

                  // Member Core Details
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Row(
                                children: [
                                  Flexible(
                                    child: Text(
                                      member.fullname,
                                      style: GoogleFonts.outfit(
                                        fontWeight: FontWeight.w800,
                                        fontSize: 15,
                                        color: Colors.white,
                                      ),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  Text(
                                    '#${member.memberId}',
                                    style: const TextStyle(color: Colors.white38, fontSize: 11, fontWeight: FontWeight.bold),
                                  ),
                                ],
                              ),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                              decoration: BoxDecoration(
                                color: (isExpired ? AppColors.danger : AppColors.success).withOpacity(0.15),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                member.membershipStatus.toUpperCase(),
                                style: TextStyle(
                                  fontSize: 10,
                                  fontWeight: FontWeight.bold,
                                  color: isExpired ? AppColors.danger : AppColors.success,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 3),
                        Text(
                          '${member.phone} • ${member.services} (${member.planMonths} Mo)',
                          style: const TextStyle(
                            fontSize: 12,
                            color: Colors.white70,
                            fontWeight: FontWeight.w500,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),

          // Key Metrics & Dates Badge Bar
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 14),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: const Color(0xFF13131A),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              children: [
                // Dates Row
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.calendar_today_rounded, size: 13, color: Color(0xFF00CEC9)),
                        const SizedBox(width: 5),
                        Text(
                          'Joined: ${member.startDate}',
                          style: const TextStyle(color: Colors.white70, fontSize: 11),
                        ),
                      ],
                    ),
                    Row(
                      children: [
                        Icon(Icons.alarm_rounded, size: 13, color: isExpired ? AppColors.danger : (isExpiringSoon ? AppColors.warning : AppColors.lime)),
                        const SizedBox(width: 5),
                        Text(
                          'Exp: ${member.expiryDate}',
                          style: TextStyle(
                            color: isExpired ? AppColors.danger : (isExpiringSoon ? AppColors.warning : Colors.white70),
                            fontSize: 11,
                            fontWeight: (isExpired || isExpiringSoon) ? FontWeight.bold : FontWeight.normal,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
                const Divider(height: 12, color: Colors.white10),

                // Financials Row
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.check_circle_outline, size: 13, color: Color(0xFF00CEC9)),
                        const SizedBox(width: 5),
                        Text(
                          'Paid: $currency${member.paidAmount.toStringAsFixed(0)}',
                          style: const TextStyle(color: Color(0xFF00CEC9), fontWeight: FontWeight.bold, fontSize: 12),
                        ),
                      ],
                    ),
                    if (hasDue)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.warning.withOpacity(0.15),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.warning_amber_rounded, size: 12, color: AppColors.warning),
                            const SizedBox(width: 4),
                            Text(
                              'Due: $currency${member.dueAmount.toStringAsFixed(0)}',
                              style: const TextStyle(color: AppColors.warning, fontWeight: FontWeight.bold, fontSize: 11),
                            ),
                          ],
                        ),
                      )
                    else
                      const Text(
                        '✓ Zero Due (Clear)',
                        style: TextStyle(color: AppColors.success, fontWeight: FontWeight.bold, fontSize: 11),
                      ),
                  ],
                ),
              ],
            ),
          ),

          // Action Buttons Bottom Row
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 10),
            child: Row(
              children: [
                if (hasDue)
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => _openCollectDialog(member),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.warning,
                        foregroundColor: Colors.black,
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      icon: const Icon(Icons.payments_rounded, size: 14),
                      label: const Text('Collect Due', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold)),
                    ),
                  )
                else
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _openCollectDialog(member),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.lime,
                        side: const BorderSide(color: AppColors.lime),
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      icon: const Icon(Icons.autorenew_rounded, size: 14),
                      label: const Text('Renew Plan', style: TextStyle(fontSize: 11.5, fontWeight: FontWeight.bold)),
                    ),
                  ),
                const SizedBox(width: 8),

                if (member.phone.isNotEmpty)
                  IconButton(
                    icon: const Icon(Icons.chat_rounded, color: Color(0xFF25D366), size: 18),
                    onPressed: () => _sendWhatsApp(member.phone, member.whatsappReminder),
                    tooltip: 'Send WhatsApp Reminder',
                  ),

                IconButton(
                  icon: const Icon(Icons.edit_rounded, color: Colors.white70, size: 18),
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => AdminEditMemberScreen(member: member)),
                    ).then((_) => _loadMembers(refresh: true));
                  },
                  tooltip: 'Edit Profile',
                ),

                IconButton(
                  icon: const Icon(Icons.arrow_forward_ios_rounded, color: Colors.white38, size: 14),
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => AdminMemberDetailScreen(
                          memberId: member.memberId,
                          initialName: member.fullname,
                        ),
                      ),
                    ).then((_) => _loadMembers(refresh: true));
                  },
                  tooltip: 'View Profile',
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
          fontSize: 16,
          fontWeight: FontWeight.w900,
          color: AppColors.lime,
        ),
      ),
    );
  }
}
