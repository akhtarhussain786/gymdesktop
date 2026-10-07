class NoticeModuleData {
  final int total;
  final List<GymNoticeItem> notices;

  NoticeModuleData({
    required this.total,
    required this.notices,
  });

  factory NoticeModuleData.fromJson(Map<String, dynamic> json) {
    return NoticeModuleData(
      total: json['total'] ?? 0,
      notices: (json['notices'] as List? ?? [])
          .map((n) => GymNoticeItem.fromJson(n))
          .toList(),
    );
  }
}

class GymNoticeItem {
  final int id;
  final String title;
  final String message;
  final String date;
  final String formattedDate;
  final String type;

  GymNoticeItem({
    required this.id,
    required this.title,
    required this.message,
    required this.date,
    required this.formattedDate,
    required this.type,
  });

  factory GymNoticeItem.fromJson(Map<String, dynamic> json) {
    return GymNoticeItem(
      id: json['id'] ?? 0,
      title: json['title'] ?? 'Gym Notice',
      message: json['message'] ?? '',
      date: json['date'] ?? '',
      formattedDate: json['formatted_date'] ?? '',
      type: json['type'] ?? 'general',
    );
  }
}
