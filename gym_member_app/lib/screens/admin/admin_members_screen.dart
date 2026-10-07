import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../models/admin_member_item.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';
import 'admin_add_member_screen.dart';
import 'admin_member_detail_screen.dart';

class AdminMembersScreen extends StatefulWidget {
  const AdminMembersScreen({super.key});

  @override
  State<AdminMembersScreen> createState() => _AdminMembersScreenState();
}

class _AdminMembersScreenState extends State<AdminMembersScreen> {
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final admin = context.read<AdminProvider>();
      _searchController.text = admin.searchQuery;
      admin.searchMembers();
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _makePhoneCall(String phoneNumber) async {
    final clean = phoneNumber.replaceAll(RegExp(r'[^0-9+]'), '');
    final uri = Uri.parse('tel:$clean');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  Future<void> _sendWhatsApp(String phone, String message) async {
    final clean = phone.replaceAll(RegExp(r'[^0-9]'), '');
    final fullPhone = clean.startsWith('91') || clean.length > 10 ? clean : '91$clean';
    final uri = Uri.parse('https://wa.me/$fullPhone?text=${Uri.encodeComponent(message)}');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final admin = context.watch<AdminProvider>();
    final auth = context.watch<AuthProvider>();
    final currency = auth.currentTenant?.currency ?? '₹';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Members Directory', style: TextStyle(fontWeight: FontWeight.bold)),
        actions: [
          IconButton(
            icon: const Icon(Icons.person_add_alt_1, color: Color(0xFF3B82F6)),
            tooltip: 'Add New Member',
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const AdminAddMemberScreen()),
              );
            },
          ),
        ],
      ),
      body: Column(
        children: [
          // --- 1. SEARCH BAR ---
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Search by name, phone, address...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          admin.searchMembers(query: '');
                        },
                      )
                    : null,
                filled: true,
                fillColor: theme.cardColor,
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: Colors.grey.withOpacity(0.2)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide(color: Colors.grey.withOpacity(0.2)),
                ),
              ),
              onChanged: (val) => admin.searchMembers(query: val),
            ),
          ),

          // --- 2. FILTER CHIPS ---
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Row(
              children: [
                _buildFilterChip(
                  label: 'All Members',
                  filterKey: 'all',
                  icon: Icons.people,
                  isSelected: admin.activeFilter == 'all',
                  onSelected: () => admin.searchMembers(filter: 'all'),
                ),
                const SizedBox(width: 8),
                _buildFilterChip(
                  label: '⚠️ With Dues',
                  filterKey: 'dues',
                  icon: Icons.warning_amber_rounded,
                  isSelected: admin.activeFilter == 'dues',
                  selectedColor: const Color(0xFFEF4444),
                  onSelected: () => admin.searchMembers(filter: 'dues'),
                ),
                const SizedBox(width: 8),
                _buildFilterChip(
                  label: '⏳ Expiring Soon',
                  filterKey: 'expiring',
                  icon: Icons.access_time,
                  isSelected: admin.activeFilter == 'expiring',
                  selectedColor: const Color(0xFFF59E0B),
                  onSelected: () => admin.searchMembers(filter: 'expiring'),
                ),
                const SizedBox(width: 8),
                _buildFilterChip(
                  label: '🔴 Expired',
                  filterKey: 'expired',
                  icon: Icons.cancel_outlined,
                  isSelected: admin.activeFilter == 'expired',
                  selectedColor: const Color(0xFFEF4444),
                  onSelected: () => admin.searchMembers(filter: 'expired'),
                ),
                const SizedBox(width: 8),
                _buildFilterChip(
                  label: '🟢 Active',
                  filterKey: 'active',
                  icon: Icons.check_circle_outline,
                  isSelected: admin.activeFilter == 'active',
                  selectedColor: const Color(0xFF10B981),
                  onSelected: () => admin.searchMembers(filter: 'active'),
                ),
              ],
            ),
          ),

          const SizedBox(height: 8),

          // --- 3. MEMBERS COUNT & LIST ---
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Found ${admin.members.length} members',
                  style: TextStyle(fontSize: 13, color: Colors.grey[600], fontWeight: FontWeight.w600),
                ),
                if (admin.activeFilter == 'dues')
                  Text(
                    'Sorted by highest due amount',
                    style: TextStyle(fontSize: 11, color: Colors.red[400], fontWeight: FontWeight.bold),
                  ),
              ],
            ),
          ),

          Expanded(
            child: admin.isLoadingMembers
                ? const Center(child: CircularProgressIndicator())
                : admin.members.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.search_off, size: 50, color: Colors.grey.withOpacity(0.5)),
                            const SizedBox(height: 12),
                            Text(
                              'No members found',
                              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Try changing search query or filter chip.',
                              style: TextStyle(color: Colors.grey[600], fontSize: 13),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () => admin.searchMembers(),
                        child: ListView.separated(
                          padding: const EdgeInsets.all(16),
                          itemCount: admin.members.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 14),
                          itemBuilder: (context, index) {
                            final m = admin.members[index];
                            return _buildMemberCard(context, m, currency);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChip({
    required String label,
    required String filterKey,
    required IconData icon,
    required bool isSelected,
    Color selectedColor = const Color(0xFF3B82F6),
    required VoidCallback onSelected,
  }) {
    return ChoiceChip(
      avatar: Icon(icon, size: 16, color: isSelected ? Colors.white : Colors.grey[600]),
      label: Text(
        label,
        style: TextStyle(
          color: isSelected ? Colors.white : Colors.grey[800],
          fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
          fontSize: 13,
        ),
      ),
      selected: isSelected,
      selectedColor: selectedColor,
      backgroundColor: Colors.grey.withOpacity(0.1),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      onSelected: (_) => onSelected(),
    );
  }

  Widget _buildMemberCard(BuildContext context, AdminMemberItem m, String currency) {
    final theme = Theme.of(context);

    return InkWell(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => AdminMemberDetailScreen(memberId: m.memberId),
          ),
        );
      },
      borderRadius: BorderRadius.circular(16),
      child: Container(
        decoration: BoxDecoration(
          color: theme.cardColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: m.hasDue ? const Color(0xFFEF4444).withOpacity(0.35) : Colors.grey.withOpacity(0.18),
            width: m.hasDue ? 1.5 : 1,
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Row 1: Photo, Name, Phone & Status
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Member Photo Thumbnail
                ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    width: 54,
                    height: 54,
                    color: const Color(0xFF3B82F6).withOpacity(0.12),
                    child: m.avatar != null
                        ? Image.network(
                            m.avatar!,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => _buildAvatarFallback(m),
                          )
                        : _buildAvatarFallback(m),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        m.fullname,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                      ),
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          Icon(Icons.phone, size: 13, color: Colors.grey[600]),
                          const SizedBox(width: 4),
                          Text(
                            m.phone.isNotEmpty ? m.phone : 'No Phone',
                            style: TextStyle(fontSize: 13, color: Colors.grey[700]),
                          ),
                        ],
                      ),
                      if (m.address.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Row(
                          children: [
                            Icon(Icons.location_on_outlined, size: 13, color: Colors.grey[600]),
                            const SizedBox(width: 4),
                            Expanded(
                              child: Text(
                                m.address,
                                style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
                _buildStatusPill(m),
              ],
            ),

            const SizedBox(height: 12),
            const Divider(height: 1),
            const SizedBox(height: 10),

            // Row 2: Service & Dates
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${m.services} (${m.planMonths} Mo)',
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Joined: ${m.startDate}',
                      style: TextStyle(fontSize: 11, color: Colors.grey[600]),
                    ),
                  ],
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      'Expires: ${m.expiryDate}',
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                        color: m.isExpired ? const Color(0xFFEF4444) : Colors.grey[800],
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      m.isExpired
                          ? 'Expired ${m.daysRemaining.abs()} days ago'
                          : '${m.daysRemaining} days left',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.bold,
                        color: m.isExpired
                            ? const Color(0xFFEF4444)
                            : (m.isExpiringSoon ? const Color(0xFFF59E0B) : const Color(0xFF10B981)),
                      ),
                    ),
                  ],
                ),
              ],
            ),

            const SizedBox(height: 10),

            // Row 3: Financials & Action Buttons
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: m.hasDue ? const Color(0xFFEF4444).withOpacity(0.08) : const Color(0xFF10B981).withOpacity(0.08),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Total: $currency${m.totalFee.toStringAsFixed(0)} • Paid: $currency${m.paidAmount.toStringAsFixed(0)}',
                        style: TextStyle(fontSize: 11, color: Colors.grey[700]),
                      ),
                      const SizedBox(height: 2),
                      if (m.hasDue)
                        Text(
                          '⚠️ DUE: $currency${m.dueAmount.toStringAsFixed(0)}${m.dueDate != null ? " (Due: ${m.dueDate})" : ""}',
                          style: const TextStyle(
                            color: Color(0xFFEF4444),
                            fontWeight: FontWeight.w900,
                            fontSize: 13,
                          ),
                        )
                      else
                        Text(
                          '✓ FULLY PAID',
                          style: TextStyle(
                            color: Colors.green[700],
                            fontWeight: FontWeight.w900,
                            fontSize: 12,
                          ),
                        ),
                    ],
                  ),
                  Row(
                    children: [
                      if (m.phone.isNotEmpty) ...[
                        IconButton(
                          icon: const Icon(Icons.phone, size: 20, color: Color(0xFF3B82F6)),
                          tooltip: 'Call Member',
                          constraints: const BoxConstraints(),
                          padding: const EdgeInsets.all(6),
                          onPressed: () => _makePhoneCall(m.phone),
                        ),
                        const SizedBox(width: 4),
                        IconButton(
                          icon: const Icon(Icons.chat, size: 20, color: Color(0xFF25D366)),
                          tooltip: 'WhatsApp Reminder',
                          constraints: const BoxConstraints(),
                          padding: const EdgeInsets.all(6),
                          onPressed: () => _sendWhatsApp(m.phone, m.whatsappReminder),
                        ),
                        const SizedBox(width: 4),
                      ],
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: m.hasDue ? const Color(0xFFEF4444) : const Color(0xFF3B82F6),
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          minimumSize: const Size(60, 32),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => AdminMemberDetailScreen(memberId: m.memberId),
                            ),
                          );
                        },
                        child: Text(m.hasDue ? 'Collect' : 'View', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAvatarFallback(AdminMemberItem m) {
    return Center(
      child: Text(
        m.fullname.isNotEmpty ? m.fullname[0].toUpperCase() : 'M',
        style: const TextStyle(
          fontWeight: FontWeight.bold,
          fontSize: 22,
          color: Color(0xFF3B82F6),
        ),
      ),
    );
  }

  Widget _buildStatusPill(AdminMemberItem m) {
    Color bg = const Color(0xFF10B981).withOpacity(0.12);
    Color fg = const Color(0xFF10B981);
    String label = 'Active';

    if (m.isExpired) {
      bg = const Color(0xFFEF4444).withOpacity(0.12);
      fg = const Color(0xFFEF4444);
      label = 'Expired';
    } else if (m.isExpiringSoon) {
      bg = const Color(0xFFF59E0B).withOpacity(0.12);
      fg = const Color(0xFFF59E0B);
      label = 'Expiring';
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(color: fg, fontWeight: FontWeight.bold, fontSize: 11),
      ),
    );
  }
}
