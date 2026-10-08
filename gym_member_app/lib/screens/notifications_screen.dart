import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/config/api_config.dart';
import '../core/network/api_service.dart';
import '../core/theme/app_colors.dart';
import '../models/notification_model.dart';
import 'admin/admin_notifications_screen.dart';

class NotificationsScreen extends StatefulWidget {
  final bool isAdmin;

  const NotificationsScreen({super.key, this.isAdmin = false});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<NotificationItem> _notifications = [];
  int _unreadCount = 0;
  bool _isLoading = true;
  String _selectedFilter = 'all';

  @override
  void initState() {
    super.initState();
    _fetchNotifications();
  }

  Future<void> _fetchNotifications() async {
    setState(() => _isLoading = true);
    try {
      final endpoint = widget.isAdmin ? ApiConfig.adminNotifications : ApiConfig.notifications;
      final data = await ApiService.get(endpoint, isAdmin: widget.isAdmin);

      if (mounted && data is Map<String, dynamic>) {
        final rawList = widget.isAdmin
            ? (data['incoming_notices'] ?? data['sent_history'] ?? [])
            : (data['notifications'] ?? []);

        final list = (rawList as List)
            .map((item) => NotificationItem.fromJson(Map<String, dynamic>.from(item)))
            .toList();

        setState(() {
          _notifications = list;
          _unreadCount = (data['unread_count'] is num)
              ? (data['unread_count'] as num).toInt()
              : list.where((n) => !n.isRead).length;
          _isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _markAllAsRead() async {
    try {
      if (!widget.isAdmin) {
        await ApiService.post(ApiConfig.notifications, body: {'action': 'mark_all_read'});
      }
      setState(() {
        _unreadCount = 0;
        _notifications = _notifications.map((n) => NotificationItem(
          id: n.id,
          title: n.title,
          message: n.message,
          type: n.type,
          data: n.data,
          isRead: true,
          sender: n.sender,
          createdAt: n.createdAt,
          timeAgo: n.timeAgo,
        )).toList();
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('All notifications marked as read.')),
        );
      }
    } catch (_) {}
  }

  Future<void> _markSingleRead(NotificationItem item) async {
    if (item.isRead) return;
    try {
      if (!widget.isAdmin) {
        await ApiService.post(ApiConfig.notifications, body: {
          'action': 'mark_read',
          'notification_id': item.id,
        });
      }
      setState(() {
        if (_unreadCount > 0) _unreadCount--;
        final idx = _notifications.indexWhere((n) => n.id == item.id);
        if (idx != -1) {
          _notifications[idx] = NotificationItem(
            id: item.id,
            title: item.title,
            message: item.message,
            type: item.type,
            data: item.data,
            isRead: true,
            sender: item.sender,
            createdAt: item.createdAt,
            timeAgo: item.timeAgo,
          );
        }
      });
    } catch (_) {}
  }

  List<NotificationItem> get _filteredList {
    if (_selectedFilter == 'fee') {
      return _notifications.where((n) => n.type == 'fee_reminder').toList();
    }
    if (_selectedFilter == 'notice') {
      return _notifications.where((n) => n.type == 'announcement' || n.type == 'system_update' || n.type == 'holiday_timing').toList();
    }
    if (_selectedFilter == 'offers') {
      return _notifications.where((n) => n.type == 'offer').toList();
    }
    return _notifications;
  }

  IconData _getTypeIcon(String type) {
    switch (type) {
      case 'fee_reminder':
        return Icons.account_balance_wallet_rounded;
      case 'holiday_timing':
        return Icons.access_time_filled_rounded;
      case 'offer':
        return Icons.card_giftcard_rounded;
      case 'system_update':
        return Icons.rocket_launch_rounded;
      default:
        return Icons.campaign_rounded;
    }
  }

  Color _getTypeColor(String type) {
    switch (type) {
      case 'fee_reminder':
        return const Color(0xFFEF4444);
      case 'holiday_timing':
        return const Color(0xFFF59E0B);
      case 'offer':
        return const Color(0xFF10B981);
      case 'system_update':
        return const Color(0xFFA855F7);
      default:
        return AppColors.lime;
    }
  }

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: AppBar(
        backgroundColor: AppColors.card(context),
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back_ios_new_rounded, size: 18, color: AppColors.primaryText(context)),
          onPressed: () => Navigator.pop(context),
        ),
        title: Row(
          children: [
            Text(
              'Notification Center',
              style: GoogleFonts.outfit(
                fontWeight: FontWeight.w900,
                fontSize: 18,
                color: AppColors.textPrimary(context),
              ),
            ),
            if (_unreadCount > 0) ...[
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: AppColors.lime,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  '$_unreadCount NEW',
                  style: GoogleFonts.plusJakartaSans(
                    color: Colors.black,
                    fontWeight: FontWeight.w900,
                    fontSize: 10,
                  ),
                ),
              ),
            ],
          ],
        ),
        actions: [
          if (widget.isAdmin)
            IconButton(
              icon: const Icon(Icons.campaign_rounded, color: AppColors.lime),
              tooltip: 'Broadcast & Send Alerts',
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const AdminNotificationsScreen()),
                );
              },
            ),
          if (_unreadCount > 0 && !widget.isAdmin)
            TextButton(
              onPressed: _markAllAsRead,
              child: Text(
                'Mark All Read',
                style: GoogleFonts.plusJakartaSans(
                  color: AppColors.lime,
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                ),
              ),
            ),
          const SizedBox(width: 8),
        ],
      ),
      body: Column(
        children: [
          // Filter Tabs
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            color: AppColors.card(context),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _filterChip('all', 'All Alerts (${_notifications.length})'),
                  const SizedBox(width: 8),
                  _filterChip('fee', '💳 Fee Reminders'),
                  const SizedBox(width: 8),
                  _filterChip('notice', '📢 Notices & Timing'),
                  const SizedBox(width: 8),
                  _filterChip('offers', '🎁 Offers'),
                ],
              ),
            ),
          ),

          // Notification List
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppColors.lime))
                : RefreshIndicator(
                    color: AppColors.lime,
                    backgroundColor: AppColors.card(context),
                    onRefresh: _fetchNotifications,
                    child: _filteredList.isEmpty
                        ? _buildEmptyState()
                        : ListView.separated(
                            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                            itemCount: _filteredList.length,
                            separatorBuilder: (_, _) => const SizedBox(height: 12),
                            itemBuilder: (ctx, i) {
                              final item = _filteredList[i];
                              return _buildNotificationCard(item, dark);
                            },
                          ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _filterChip(String key, String label) {
    final isSelected = _selectedFilter == key;
    return InkWell(
      onTap: () => setState(() => _selectedFilter = key),
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.lime : AppColors.cardElevated(context),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected ? AppColors.lime : AppColors.border(context),
            width: 1,
          ),
        ),
        child: Text(
          label,
          style: GoogleFonts.plusJakartaSans(
            fontSize: 12,
            fontWeight: FontWeight.w700,
            color: isSelected ? Colors.black : AppColors.textSecondary(context),
          ),
        ),
      ),
    );
  }

  Widget _buildNotificationCard(NotificationItem item, bool dark) {
    final typeColor = _getTypeColor(item.type);
    final icon = _getTypeIcon(item.type);

    return InkWell(
      onTap: () {
        _markSingleRead(item);
        _showDetailDialog(item);
      },
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: item.isRead
              ? AppColors.card(context)
              : (dark ? const Color(0xFF1E2638) : const Color(0xFFEFF6FF)),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: item.isRead
                ? AppColors.border(context)
                : typeColor.withValues(alpha: 0.4),
            width: item.isRead ? 1 : 1.5,
          ),
          boxShadow: item.isRead
              ? []
              : [
                  BoxShadow(
                    color: typeColor.withValues(alpha: 0.08),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Leading Type Icon Badge
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: typeColor.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: typeColor.withValues(alpha: 0.3)),
              ),
              child: Icon(icon, color: typeColor, size: 22),
            ),
            const SizedBox(width: 14),

            // Content Column
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          item.title,
                          style: GoogleFonts.outfit(
                            fontSize: 15,
                            fontWeight: item.isRead ? FontWeight.w700 : FontWeight.w900,
                            color: AppColors.textPrimary(context),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (!item.isRead) ...[
                        const SizedBox(width: 6),
                        Container(
                          width: 8,
                          height: 8,
                          decoration: const BoxDecoration(
                            color: AppColors.lime,
                            shape: BoxShape.circle,
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    item.message,
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 12.5,
                      color: AppColors.textSecondary(context),
                      height: 1.4,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'From: ${item.sender}',
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w600,
                          color: typeColor,
                        ),
                      ),
                      Text(
                        item.timeAgo,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textMuted(context),
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
    );
  }

  void _showDetailDialog(NotificationItem item) {
    final typeColor = _getTypeColor(item.type);
    final icon = _getTypeIcon(item.type);

    showDialog(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          backgroundColor: AppColors.card(context),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: typeColor.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(icon, color: typeColor, size: 20),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  item.title,
                  style: GoogleFonts.outfit(
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                    color: AppColors.textPrimary(context),
                  ),
                ),
              ),
            ],
          ),
          content: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  item.message,
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 13.5,
                    color: AppColors.textSecondary(context),
                    height: 1.5,
                  ),
                ),
                const SizedBox(height: 16),
                const Divider(),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Sender: ${item.sender}',
                      style: GoogleFonts.plusJakartaSans(fontSize: 11, color: typeColor, fontWeight: FontWeight.w700),
                    ),
                    Text(
                      item.timeAgo,
                      style: GoogleFonts.plusJakartaSans(fontSize: 11, color: AppColors.textMuted(context)),
                    ),
                  ],
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: Text(
                'Close',
                style: GoogleFonts.plusJakartaSans(color: AppColors.lime, fontWeight: FontWeight.w800),
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                color: AppColors.cardElevated(context),
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.border(context)),
              ),
              child: const Icon(Icons.notifications_off_rounded, size: 36, color: AppColors.lime),
            ),
            const SizedBox(height: 16),
            Text(
              'No Notifications Yet',
              style: GoogleFonts.outfit(
                fontSize: 18,
                fontWeight: FontWeight.w900,
                color: AppColors.textPrimary(context),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'You are completely caught up! New gym announcements, timing updates, and fee reminders will appear here.',
              textAlign: TextAlign.center,
              style: GoogleFonts.plusJakartaSans(
                fontSize: 12.5,
                color: AppColors.textMuted(context),
                height: 1.4,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
