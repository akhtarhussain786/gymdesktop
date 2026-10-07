class GymTenant {
  final int id;
  final String gymCode;
  final String gymName;
  final String slug;
  final String? logo;
  final String address;
  final String phone;
  final String email;
  final String currency;
  final String timezone;
  final String primaryColor;
  final String secondaryColor;
  final String? planName;
  final String? upiId;
  final List<GymBranch> branches;
  final Map<String, bool> features;

  GymTenant({
    required this.id,
    required this.gymCode,
    required this.gymName,
    required this.slug,
    this.logo,
    this.upiId,
    required this.address,
    required this.phone,
    required this.email,
    required this.currency,
    required this.timezone,
    required this.primaryColor,
    required this.secondaryColor,
    this.planName,
    this.branches = const [],
    this.features = const {},
  });

  factory GymTenant.fromJson(Map<String, dynamic> json) {
    var rawBranches = json['branches'] as List? ?? [];
    var branchList = rawBranches.map((b) => GymBranch.fromJson(b)).toList();

    var rawFeatures = json['features_enabled'] as Map<String, dynamic>? ?? {};
    var featureMap = rawFeatures.map((k, v) => MapEntry(k, v == true));

    return GymTenant(
      id: json['gym_id'] ?? json['id'] ?? 0,
      gymCode: json['gym_code'] ?? '',
      gymName: json['gym_name'] ?? 'Gym Facility',
      slug: json['slug'] ?? '',
      logo: json['logo'],
      upiId: json['upi_id'],
      address: json['address'] ?? '',
      phone: json['phone'] ?? '',
      email: json['email'] ?? '',
      currency: json['currency'] ?? '\$',
      timezone: json['timezone'] ?? 'UTC',
      primaryColor: json['primary_color'] ?? '#2563eb',
      secondaryColor: json['secondary_color'] ?? '#10b981',
      planName: json['plan_name'],
      branches: branchList,
      features: featureMap,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'gym_code': gymCode,
        'gym_name': gymName,
        'slug': slug,
        'logo': logo,
        'address': address,
        'phone': phone,
        'email': email,
        'currency': currency,
        'timezone': timezone,
        'primary_color': primaryColor,
        'secondary_color': secondaryColor,
        'plan_name': planName,
        'upi_id': upiId,
      };
}

class GymBranch {
  final int id;
  final String branchName;
  final String? address;
  final String? phone;
  final String? email;
  final bool isMain;

  GymBranch({
    required this.id,
    required this.branchName,
    this.address,
    this.phone,
    this.email,
    this.isMain = false,
  });

  factory GymBranch.fromJson(Map<String, dynamic> json) {
    return GymBranch(
      id: json['id'] ?? 0,
      branchName: json['branch_name'] ?? 'Branch',
      address: json['address'],
      phone: json['phone'],
      email: json['email'],
      isMain: (json['is_main'] == 1 || json['is_main'] == true),
    );
  }
}
