class NotificationItem {
  final int id;
  final String title;
  final String message;
  final String type;
  final Map<String, dynamic>? data;
  final bool isRead;
  final String sender;
  final String createdAt;
  final String timeAgo;

  NotificationItem({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    this.data,
    this.isRead = false,
    required this.sender,
    required this.createdAt,
    required this.timeAgo,
  });

  factory NotificationItem.fromJson(Map<String, dynamic> json) {
    return NotificationItem(
      id: json['id'] is int ? json['id'] : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      title: json['title']?.toString() ?? '',
      message: json['message']?.toString() ?? '',
      type: json['type']?.toString() ?? 'announcement',
      data: json['data'] is Map<String, dynamic> ? json['data'] : null,
      isRead: json['is_read'] == true || json['is_read'] == 1 || json['is_read'] == '1',
      sender: json['sender']?.toString() ?? 'Gym Management',
      createdAt: json['created_at']?.toString() ?? '',
      timeAgo: json['time_ago']?.toString() ?? 'Just now',
    );
  }
}
