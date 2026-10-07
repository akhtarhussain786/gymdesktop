import 'package:flutter/foundation.dart';

class ApiConfig {
  // Your computer's Wi-Fi IP address or production domain
  static const String _localServerIp = '192.168.1.4:8000';

  static String get baseUrl {
    if (kIsWeb) {
      return '/api/member';
    }
    return 'http://$_localServerIp/api/member';
  }

  static String get adminBaseUrl {
    if (kIsWeb) {
      return '/api/admin';
    }
    return 'http://$_localServerIp/api/admin';
  }

  // Member Endpoints
  static const String gymLookup = '/gym_lookup.php';
  static const String login = '/login.php';
  static const String forgotPassword = '/forgot_password.php';
  static const String dashboard = '/dashboard.php';
  static const String profile = '/profile.php';
  static const String changePassword = '/change_password.php';
  static const String membership = '/membership.php';
  static const String attendance = '/attendance.php';
  static const String payments = '/payments.php';
  static const String receipt = '/receipt.php';
  static const String workouts = '/workouts.php';
  static const String diet = '/diet.php';
  static const String trainer = '/trainer.php';
  static const String notices = '/notices.php';
  static const String support = '/support.php';
  static const String deviceToken = '/device_token.php';
  static const String logout = '/logout.php';

  // Admin Endpoints
  static const String adminDashboard = '/dashboard.php';
  static const String adminMembers = '/members.php';
  static const String adminMemberDetail = '/member_detail.php';
  static const String adminAddMember = '/add_member.php';
  static const String adminCollectPayment = '/collect_payment.php';
  static const String adminRates = '/rates.php';
  static const String adminGymQr = '/gym_qr.php';
}
