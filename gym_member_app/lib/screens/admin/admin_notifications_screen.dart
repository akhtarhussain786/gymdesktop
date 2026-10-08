import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/config/api_config.dart';
import '../../core/network/api_service.dart';
import '../../core/services/push_notification_service.dart';
import '../../core/theme/app_colors.dart';

class AdminNotificationsScreen extends StatefulWidget {
  const AdminNotificationsScreen({super.key});

  @override
  State<AdminNotificationsScreen> createState() => _AdminNotificationsScreenState();
}

class _AdminNotificationsScreenState extends State<AdminNotificationsScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _isLoading = true;
  bool _isSending = false;

  List<Map<String, dynamic>> _sentHistory = [];
  List<Map<String, dynamic>> _incomingNotices = [];
  List<Map<String, dynamic>> _membersList = [];
  int _pendingDueCount = 0;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    setState(() => _isLoading = true);
    try {
      final res = await ApiService.get(ApiConfig.adminNotifications, isAdmin: true);
      if (mounted && res is Map<String, dynamic>) {
        setState(() {
          _sentHistory = List<Map<String, dynamic>>.from(res['sent_history'] ?? []);
          _incomingNotices = List<Map<String, dynamic>>.from(res['incoming_notices'] ?? []);
          _membersList = List<Map<String, dynamic>>.from(res['members_list'] ?? []);
          _pendingDueCount = (res['pending_due_count'] is num) ? (res['pending_due_count'] as num).toInt() : 0;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to load notifications: $e'), backgroundColor: Colors.redAccent),
        );
      }
    }
  }

  Future<void> _sendQuickDueReminder() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1E293B),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: [
            const Icon(Icons.bolt_rounded, color: Colors.amber, size: 28),
            const SizedBox(width: 8),
            Text('1-Click Due Reminder', style: GoogleFonts.outfit(color: Colors.white, fontWeight: FontWeight.bold)),
          ],
        ),
        content: Text(
          'Send instant fee due reminder push notification to all $_pendingDueCount member(s) with pending or expiring membership?',
          style: GoogleFonts.plusJakartaSans(color: Colors.white70),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text('Cancel', style: GoogleFonts.plusJakartaSans(color: Colors.white60)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.amber,
              foregroundColor: Colors.black,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: Text('Send Now', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isSending = true);
    try {
      final res = await ApiService.post(
        ApiConfig.adminNotifications,
        body: {'action': 'quick_due_reminder'},
        isAdmin: true,
      );
      if (mounted) {
        setState(() => _isSending = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res['message'] ?? 'Fee Due Reminders dispatched successfully!'),
            backgroundColor: const Color(0xFF10B981),
          ),
        );
        _loadData();
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isSending = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: Colors.redAccent),
        );
      }
    }
  }

  void _showComposeModal() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _ComposeNotificationModal(
        membersList: _membersList,
        onNotificationSent: () {
          _loadData();
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Push notification dispatched to members!'),
              backgroundColor: Color(0xFF10B981),
            ),
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF0F172A),
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Broadcast & Push Hub',
              style: GoogleFonts.outfit(fontWeight: FontWeight.w800, fontSize: 18, color: Colors.white),
            ),
            Text(
              'Send WhatsApp-style alerts to members',
              style: GoogleFonts.plusJakartaSans(fontSize: 11, color: Colors.white54),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_active_rounded, color: Colors.amber),
            tooltip: 'Test Floating Popup & Sound',
            onPressed: () {
              PushNotificationService.showTestNotification();
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Triggered live test popup banner!'),
                  backgroundColor: Color(0xFF10B981),
                  duration: Duration(seconds: 2),
                ),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: Colors.white70),
            onPressed: _loadData,
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: AppColors.lime,
          indicatorWeight: 3,
          labelColor: AppColors.lime,
          unselectedLabelColor: Colors.white60,
          labelStyle: GoogleFonts.outfit(fontWeight: FontWeight.bold, fontSize: 14),
          tabs: [
            Tab(
              icon: const Icon(Icons.send_rounded, size: 18),
              text: 'Sent to Members (${_sentHistory.length})',
            ),
            Tab(
              icon: const Icon(Icons.cloud_download_rounded, size: 18),
              text: 'SuperAdmin Alerts (${_incomingNotices.length})',
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: AppColors.lime,
        foregroundColor: Colors.black,
        elevation: 6,
        icon: const Icon(Icons.campaign_rounded, size: 22),
        label: Text(
          'Compose Alert',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w800, fontSize: 14),
        ),
        onPressed: _showComposeModal,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.lime))
          : RefreshIndicator(
              onRefresh: _loadData,
              color: AppColors.lime,
              child: Column(
                children: [
                  // 1-Click Action & Quick Due Card
                  Padding(
                    padding: const EdgeInsets.all(16.0),
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: [
                            const Color(0xFF1E293B),
                            const Color(0xFF334155).withValues(alpha: 0.6),
                          ],
                        ),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: Colors.amber.withValues(alpha: 0.3)),
                      ),
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: Colors.amber.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: const Icon(Icons.notifications_active_rounded, color: Colors.amber, size: 28),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '$_pendingDueCount Pending Fee Members',
                                  style: GoogleFonts.outfit(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  'Send 1-click WhatsApp-style push alert',
                                  style: GoogleFonts.plusJakartaSans(color: Colors.white60, fontSize: 11),
                                ),
                              ],
                            ),
                          ),
                          ElevatedButton(
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.amber,
                              foregroundColor: Colors.black,
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            onPressed: _isSending ? null : _sendQuickDueReminder,
                            child: _isSending
                                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black))
                                : Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const Icon(Icons.bolt_rounded, size: 18),
                                      const SizedBox(width: 4),
                                      Text('Send Due', style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 12)),
                                    ],
                                  ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  // Tab Views
                  Expanded(
                    child: TabBarView(
                      controller: _tabController,
                      children: [
                        _buildSentHistoryTab(),
                        _buildIncomingNoticesTab(),
                      ],
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildSentHistoryTab() {
    if (_sentHistory.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.mark_email_unread_outlined, size: 60, color: Colors.white.withValues(alpha: 0.2)),
            const SizedBox(height: 12),
            Text('No sent broadcasts yet', style: GoogleFonts.outfit(color: Colors.white70, fontSize: 16)),
            const SizedBox(height: 4),
            Text('Tap "Compose Alert" below to send push notices to members.',
                style: GoogleFonts.plusJakartaSans(color: Colors.white38, fontSize: 12)),
          ],
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      itemCount: _sentHistory.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (ctx, idx) {
        final item = _sentHistory[idx];
        final type = item['type'] ?? 'announcement';
        final target = item['target_type'] ?? 'all_members';

        Color badgeColor = const Color(0xFF6C5CE7);
        IconData badgeIcon = Icons.campaign_rounded;

        if (type == 'fee_reminder') {
          badgeColor = Colors.amber;
          badgeIcon = Icons.payments_rounded;
        } else if (type == 'offer') {
          badgeColor = const Color(0xFF10B981);
          badgeIcon = Icons.local_offer_rounded;
        } else if (type == 'workout_diet') {
          badgeColor = const Color(0xFF00CEC9);
          badgeIcon = Icons.fitness_center_rounded;
        }

        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFF1E293B),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white.withValues(alpha: 0.05)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: badgeColor.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(badgeIcon, size: 12, color: badgeColor),
                        const SizedBox(width: 4),
                        Text(
                          type.toString().toUpperCase().replaceAll('_', ' '),
                          style: GoogleFonts.plusJakartaSans(color: badgeColor, fontSize: 10, fontWeight: FontWeight.bold),
                        ),
                      ],
                    ),
                  ),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.05),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      target == 'all_members' ? '👥 All Members' : (target == 'due_members' ? '⚡ Due Members' : '👤 Specific'),
                      style: GoogleFonts.plusJakartaSans(color: Colors.white60, fontSize: 10),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                item['title'] ?? 'Notice',
                style: GoogleFonts.outfit(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
              ),
              const SizedBox(height: 4),
              Text(
                item['message'] ?? '',
                style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 13, height: 1.4),
              ),
              const SizedBox(height: 10),
              Text(
                item['created_at'] ?? '',
                style: GoogleFonts.plusJakartaSans(color: Colors.white38, fontSize: 11),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildIncomingNoticesTab() {
    if (_incomingNotices.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.cloud_done_rounded, size: 60, color: Colors.white.withValues(alpha: 0.2)),
            const SizedBox(height: 12),
            Text('No SuperAdmin announcements', style: GoogleFonts.outfit(color: Colors.white70, fontSize: 16)),
            const SizedBox(height: 4),
            Text('Platform-wide notices will appear here automatically.',
                style: GoogleFonts.plusJakartaSans(color: Colors.white38, fontSize: 12)),
          ],
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      itemCount: _incomingNotices.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (ctx, idx) {
        final item = _incomingNotices[idx];
        return Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFF1E293B),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFF6C5CE7).withValues(alpha: 0.3)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: const Color(0xFF6C5CE7).withValues(alpha: 0.2),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      '👑 SuperAdmin SaaS Notice',
                      style: GoogleFonts.plusJakartaSans(color: const Color(0xFF6C5CE7), fontSize: 10, fontWeight: FontWeight.bold),
                    ),
                  ),
                  const Spacer(),
                  Text(
                    item['time_ago'] ?? '',
                    style: GoogleFonts.plusJakartaSans(color: Colors.white38, fontSize: 11),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                item['title'] ?? '',
                style: GoogleFonts.outfit(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
              ),
              const SizedBox(height: 4),
              Text(
                item['message'] ?? '',
                style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 13, height: 1.4),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _ComposeNotificationModal extends StatefulWidget {
  final List<Map<String, dynamic>> membersList;
  final VoidCallback onNotificationSent;

  const _ComposeNotificationModal({required this.membersList, required this.onNotificationSent});

  @override
  State<_ComposeNotificationModal> createState() => _ComposeNotificationModalState();
}

class _ComposeNotificationModalState extends State<_ComposeNotificationModal> {
  final _titleController = TextEditingController();
  final _messageController = TextEditingController();
  String _target = 'all_members';
  String _type = 'announcement';
  int? _selectedMemberId;
  bool _isSubmitting = false;

  final List<Map<String, String>> _templates = [
    {'title': '📢 Gym Closed Tomorrow', 'msg': 'Dear athletes, gym will remain closed tomorrow for regular maintenance.'},
    {'title': '💰 Fee Due Reminder', 'msg': 'Your gym membership fee is due. Kindly renew at the counter or via app.'},
    {'title': '🔥 Special Discount Offer', 'msg': 'Upgrade to a 6-Month or Annual membership this week and get flat 20% OFF!'},
    {'title': '⚡ New Batch & Timing', 'msg': 'New morning CrossFit & Zumba batches are starting from next Monday!'},
  ];

  void _applyTemplate(Map<String, String> template) {
    setState(() {
      _titleController.text = template['title']!;
      _messageController.text = template['msg']!;
    });
  }

  Future<void> _submit() async {
    final title = _titleController.text.trim();
    final message = _messageController.text.trim();

    if (title.isEmpty || message.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter title and message.'), backgroundColor: Colors.redAccent),
      );
      return;
    }

    if (_target == 'specific_member' && _selectedMemberId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a member.'), backgroundColor: Colors.redAccent),
      );
      return;
    }

    setState(() => _isSubmitting = true);
    try {
      final payload = {
        'action': 'send_notification',
        'title': title,
        'message': message,
        'type': _type,
        'target': _target,
        'member_id': _selectedMemberId,
      };

      await ApiService.post(
        ApiConfig.adminNotifications,
        body: payload,
        isAdmin: true,
      );

      if (mounted) {
        Navigator.pop(context);
        widget.onNotificationSent();
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isSubmitting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.redAccent),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.only(
        top: 20,
        left: 20,
        right: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      decoration: const BoxDecoration(
        color: Color(0xFF1E293B),
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                const Icon(Icons.campaign_rounded, color: AppColors.lime, size: 24),
                const SizedBox(width: 8),
                Text('Compose Push Alert', style: GoogleFonts.outfit(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 18)),
                const Spacer(),
                IconButton(
                  icon: const Icon(Icons.close_rounded, color: Colors.white54),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Quick Templates Chips
            Text('Quick Templates:', style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: _templates.map((tpl) {
                  return Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ActionChip(
                      backgroundColor: const Color(0xFF0F172A),
                      side: BorderSide(color: Colors.white.withValues(alpha: 0.1)),
                      label: Text(tpl['title']!, style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 11)),
                      onPressed: () => _applyTemplate(tpl),
                    ),
                  );
                }).toList(),
              ),
            ),
            const SizedBox(height: 16),

            // Target Audience Selector
            Text('Target Audience', style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: const Color(0xFF0F172A),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Colors.white.withValues(alpha: 0.1)),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: _target,
                  isExpanded: true,
                  dropdownColor: const Color(0xFF0F172A),
                  items: const [
                    DropdownMenuItem(value: 'all_members', child: Text('👥 All Active Members (Broadcast)', style: TextStyle(color: Colors.white))),
                    DropdownMenuItem(value: 'due_members', child: Text('⚡ Pending Fee Due Members Only', style: TextStyle(color: Colors.white))),
                    DropdownMenuItem(value: 'specific_member', child: Text('👤 Specific Member', style: TextStyle(color: Colors.white))),
                  ],
                  onChanged: (val) {
                    if (val != null) setState(() => _target = val);
                  },
                ),
              ),
            ),

            if (_target == 'specific_member') ...[
              const SizedBox(height: 12),
              Text('Select Member', style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w600)),
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                decoration: BoxDecoration(
                  color: const Color(0xFF0F172A),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.white.withValues(alpha: 0.1)),
                ),
                child: DropdownButtonHideUnderline(
                  child: DropdownButton<int>(
                    value: _selectedMemberId,
                    hint: const Text('Choose a member', style: TextStyle(color: Colors.white54)),
                    isExpanded: true,
                    dropdownColor: const Color(0xFF0F172A),
                    items: widget.membersList.map((m) {
                      return DropdownMenuItem<int>(
                        value: m['id'] as int,
                        child: Text(
                          '${m['fullname']} (${m['member_code'] ?? 'ID: ${m['id']}'})',
                          style: const TextStyle(color: Colors.white),
                        ),
                      );
                    }).toList(),
                    onChanged: (val) {
                      setState(() => _selectedMemberId = val);
                    },
                  ),
                ),
              ),
            ],

            const SizedBox(height: 16),

            // Notification Title
            Text('Title *', style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            TextField(
              controller: _titleController,
              style: const TextStyle(color: Colors.white),
              decoration: InputDecoration(
                hintText: 'e.g. Special Announcement',
                hintStyle: const TextStyle(color: Colors.white38),
                filled: true,
                fillColor: const Color(0xFF0F172A),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
              ),
            ),
            const SizedBox(height: 16),

            // Notification Message
            Text('Message *', style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            TextField(
              controller: _messageController,
              maxLines: 4,
              style: const TextStyle(color: Colors.white),
              decoration: InputDecoration(
                hintText: 'Type your message here...',
                hintStyle: const TextStyle(color: Colors.white38),
                filled: true,
                fillColor: const Color(0xFF0F172A),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
              ),
            ),
            const SizedBox(height: 20),

            // Send Button
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.lime,
                  foregroundColor: Colors.black,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: _isSubmitting ? null : _submit,
                child: _isSubmitting
                    ? const CircularProgressIndicator(color: Colors.black)
                    : Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.send_rounded, size: 20),
                          const SizedBox(width: 8),
                          Text('Send Push Notification', style: GoogleFonts.outfit(fontWeight: FontWeight.w800, fontSize: 16)),
                        ],
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
