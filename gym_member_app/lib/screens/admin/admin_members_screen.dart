import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/theme/app_colors.dart';
import '../../models/admin_models.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';
import 'admin_add_member_screen.dart';
import 'admin_collect_payment_dialog.dart';
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

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: AppBar(
        title: Text(
          'Gym Members Directory',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w900, fontSize: 18),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
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
          // 1. Search Bar & Sort Row
          Container(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            color: AppColors.bg(context),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _searchController,
                        style: GoogleFonts.plusJakartaSans(fontSize: 13.5),
                        decoration: InputDecoration(
                          hintText: 'Search by Name, Phone, ID...',
                          prefixIcon: const Icon(Icons.search_rounded, size: 20),
                          suffixIcon: _searchController.text.isNotEmpty
                              ? IconButton(
                                  icon: const Icon(Icons.clear_rounded, size: 18),
                                  onPressed: () {
                                    _searchController.clear();
                                    _loadMembers();
                                  },
                                )
                              : null,
                          contentPadding: const EdgeInsets.symmetric(vertical: 0, horizontal: 16),
                        ),
                        onChanged: (v) {
                          _loadMembers();
                        },
                      ),
                    ),
                    const SizedBox(width: 8),
                    // Sort Menu
                    PopupMenuButton<String>(
                      icon: Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: AppColors.card(context),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.border(context)),
                        ),
                        child: const Icon(Icons.sort_rounded, color: AppColors.lime, size: 20),
                      ),
                      color: AppColors.card(context),
                      initialValue: _activeSort,
                      onSelected: (val) {
                        setState(() => _activeSort = val);
                        _loadMembers();
                      },
                      itemBuilder: (ctx) => [
                        const PopupMenuItem(value: 'recent', child: Text('Recent Registrations')),
                        const PopupMenuItem(value: 'due_high', child: Text('Highest Dues First')),
                        const PopupMenuItem(value: 'expiry_soon', child: Text('Expiring Soonest')),
                        const PopupMenuItem(value: 'name_asc', child: Text('Name (A to Z)')),
                      ],
                    ),
                  ],
                ),
                const SizedBox(height: 10),

                // Filter Chips
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _filterChip('all', 'All Members'),
                      _filterChip('dues', 'Pending Dues'),
                      _filterChip('expiring', 'Expiring Soon (7d)'),
                      _filterChip('expired', 'Expired'),
                      _filterChip('active', 'Active Only'),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1),

          // 2. Members List
          Expanded(
            child: admin.isMembersLoading && admin.members.isEmpty
                ? const Center(child: CircularProgressIndicator(color: AppColors.lime))
                : admin.members.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.people_outline_rounded, size: 48, color: AppColors.textMuted(context)),
                            const SizedBox(height: 12),
                            Text(
                              'No members found for this filter.',
                              style: GoogleFonts.plusJakartaSans(
                                color: AppColors.textMuted(context),
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            const SizedBox(height: 8),
                            TextButton.icon(
                              onPressed: () {
                                _searchController.clear();
                                setState(() => _activeFilter = 'all');
                                _loadMembers();
                              },
                              icon: const Icon(Icons.clear_all_rounded, size: 16),
                              label: const Text('Reset Filters'),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () => _loadMembers(refresh: true),
                        color: AppColors.lime,
                        child: ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 80),
                          itemCount: admin.members.length,
                          separatorBuilder: (ctx, i) => const SizedBox(height: 10),
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
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: FilterChip(
        label: Text(label),
        selected: isSelected,
        onSelected: (s) {
          setState(() => _activeFilter = key);
          _loadMembers();
        },
        selectedColor: AppColors.lime,
        checkmarkColor: Colors.black,
        backgroundColor: AppColors.card(context),
        labelStyle: GoogleFonts.plusJakartaSans(
          fontSize: 11.5,
          fontWeight: FontWeight.w800,
          color: isSelected ? Colors.black : AppColors.textPrimary(context),
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
          side: BorderSide(
            color: isSelected ? AppColors.lime : AppColors.border(context),
          ),
        ),
      ),
    );
  }

  Widget _buildMemberCard(AdminMemberItem member, String currency) {
    final isExpired = member.membershipStatus.toLowerCase() == 'expired';
    final hasDue = member.dueAmount > 0;

    return InkWell(
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
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.card(context),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(
            color: hasDue
                ? AppColors.warning.withValues(alpha: 0.4)
                : AppColors.border(context),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.15),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Row(
          children: [
            // Avatar
            Container(
              width: 50,
              height: 50,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: AppColors.cardElevated(context),
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

            // Member Info
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
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                            color: AppColors.textPrimary(context),
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: (isExpired ? AppColors.danger : AppColors.success).withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          member.membershipStatus.toUpperCase(),
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 9.5,
                            fontWeight: FontWeight.w800,
                            color: isExpired ? AppColors.danger : AppColors.success,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${member.phone} • ${member.services}',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 11.5,
                      color: AppColors.textMuted(context),
                      fontWeight: FontWeight.w600,
                    ),
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 6),

                  // Dues & Expiry Row
                  Row(
                    children: [
                      if (hasDue)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: AppColors.warning.withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: AppColors.warning, width: 0.8),
                          ),
                          child: Text(
                            'Due: $currency${member.dueAmount.toStringAsFixed(0)}',
                            style: GoogleFonts.outfit(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w900,
                              color: AppColors.warning,
                            ),
                          ),
                        )
                      else
                        Text(
                          'Exp: ${member.expiryDate}',
                          style: GoogleFonts.plusJakartaSans(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: member.daysRemaining <= 7 ? AppColors.warning : AppColors.textMuted(context),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),

            // Quick Actions (Collect & WhatsApp)
            Column(
              children: [
                IconButton(
                  icon: const Icon(Icons.payments_rounded, color: AppColors.lime, size: 20),
                  onPressed: () => _openCollectDialog(member),
                  tooltip: 'Collect Payment',
                ),
                if (member.phone.isNotEmpty)
                  IconButton(
                    icon: const Icon(Icons.chat_rounded, color: Color(0xFF25D366), size: 18),
                    onPressed: () => _sendWhatsApp(member.phone, member.whatsappReminder),
                    tooltip: 'WhatsApp Reminder',
                  ),
              ],
            ),
          ],
        ),
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
