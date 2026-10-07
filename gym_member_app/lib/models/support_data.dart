class SupportModuleData {
  final SupportGymContact gymContact;
  final GymTimings timings;
  final List<String> policies;
  final List<SupportTicketItem> tickets;

  SupportModuleData({
    required this.gymContact,
    required this.timings,
    required this.policies,
    required this.tickets,
  });

  factory SupportModuleData.fromJson(Map<String, dynamic> json) {
    return SupportModuleData(
      gymContact: SupportGymContact.fromJson(json['gym_contact'] ?? {}),
      timings: GymTimings.fromJson(json['timings'] ?? {}),
      policies: (json['policies'] as List? ?? []).map((p) => p.toString()).toList(),
      tickets: (json['tickets'] as List? ?? [])
          .map((t) => SupportTicketItem.fromJson(t))
          .toList(),
    );
  }
}

class SupportGymContact {
  final String gymName;
  final String branchName;
  final String address;
  final String phone;
  final String email;
  final String mapQuery;

  SupportGymContact({
    required this.gymName,
    required this.branchName,
    required this.address,
    required this.phone,
    required this.email,
    required this.mapQuery,
  });

  factory SupportGymContact.fromJson(Map<String, dynamic> json) {
    return SupportGymContact(
      gymName: json['gym_name'] ?? 'Gym Facility',
      branchName: json['branch_name'] ?? 'Main Facility',
      address: json['address'] ?? '',
      phone: json['phone'] ?? '',
      email: json['email'] ?? '',
      mapQuery: json['map_query'] ?? '',
    );
  }
}

class GymTimings {
  final String weekdays;
  final String sunday;
  final String holidays;

  GymTimings({
    required this.weekdays,
    required this.sunday,
    required this.holidays,
  });

  factory GymTimings.fromJson(Map<String, dynamic> json) {
    return GymTimings(
      weekdays: json['weekdays'] ?? '06:00 AM - 10:00 PM',
      sunday: json['sunday'] ?? '07:00 AM - 01:00 PM',
      holidays: json['holidays'] ?? 'Special timings announced in advance',
    );
  }
}

class SupportTicketItem {
  final int id;
  final String category;
  final String subject;
  final String message;
  final String? reply;
  final String status;
  final String createdAt;

  SupportTicketItem({
    required this.id,
    required this.category,
    required this.subject,
    required this.message,
    this.reply,
    required this.status,
    required this.createdAt,
  });

  factory SupportTicketItem.fromJson(Map<String, dynamic> json) {
    return SupportTicketItem(
      id: json['id'] ?? 0,
      category: json['category'] ?? 'General',
      subject: json['subject'] ?? '',
      message: json['message'] ?? '',
      reply: json['reply'],
      status: json['status'] ?? 'open',
      createdAt: json['created_at'] ?? '',
    );
  }
}
