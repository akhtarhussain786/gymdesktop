class AdminUser {
  final int id;
  final String username;
  final String fullname;
  final String role;
  final String email;
  final String phone;
  final String? avatar;

  AdminUser({
    required this.id,
    required this.username,
    required this.fullname,
    required this.role,
    required this.email,
    required this.phone,
    this.avatar,
  });

  factory AdminUser.fromJson(Map<String, dynamic> json) {
    return AdminUser(
      id: json['id'] ?? 0,
      username: json['username'] ?? '',
      fullname: json['fullname'] ?? 'Gym Admin',
      role: json['role'] ?? 'gym_admin',
      email: json['email'] ?? '',
      phone: json['phone'] ?? '',
      avatar: json['avatar'],
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'username': username,
    'fullname': fullname,
    'role': role,
    'email': email,
    'phone': phone,
    'avatar': avatar,
  };
}
