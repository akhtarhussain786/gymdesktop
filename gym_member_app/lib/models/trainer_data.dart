class TrainerModuleData {
  final bool hasTrainer;
  final TrainerDetail? trainer;
  final GymContactInfo gymContact;

  TrainerModuleData({
    required this.hasTrainer,
    this.trainer,
    required this.gymContact,
  });

  factory TrainerModuleData.fromJson(Map<String, dynamic> json) {
    return TrainerModuleData(
      hasTrainer: json['has_trainer'] == true,
      trainer: json['trainer'] != null ? TrainerDetail.fromJson(json['trainer']) : null,
      gymContact: GymContactInfo.fromJson(json['gym_contact'] ?? {}),
    );
  }
}

class TrainerDetail {
  final int id;
  final String fullname;
  final String designation;
  final String phone;
  final String email;
  final String gender;
  final List<String> specializations;
  final String availableTimings;
  final String notes;

  TrainerDetail({
    required this.id,
    required this.fullname,
    required this.designation,
    required this.phone,
    required this.email,
    required this.gender,
    required this.specializations,
    required this.availableTimings,
    required this.notes,
  });

  factory TrainerDetail.fromJson(Map<String, dynamic> json) {
    return TrainerDetail(
      id: json['id'] ?? 0,
      fullname: json['fullname'] ?? 'Trainer',
      designation: json['designation'] ?? 'Personal Trainer',
      phone: json['phone'] ?? '',
      email: json['email'] ?? '',
      gender: json['gender'] ?? 'Staff',
      specializations: (json['specializations'] as List? ?? []).map((s) => s.toString()).toList(),
      availableTimings: json['available_timings'] ?? 'Gym Hours',
      notes: json['notes'] ?? '',
    );
  }
}

class GymContactInfo {
  final String phone;
  final String email;

  GymContactInfo({required this.phone, required this.email});

  factory GymContactInfo.fromJson(Map<String, dynamic> json) {
    return GymContactInfo(
      phone: json['phone'] ?? '',
      email: json['email'] ?? '',
    );
  }
}
