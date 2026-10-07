import 'package:flutter/foundation.dart';

class ApiConfig {
  // Toggle true for Live Server, false for Localhost Development
  static const bool isProduction = true;

  // Live Production Server Domain
  static const String _productionDomain = 'https://gymsaas.nexoralabtechnologies.com';

  // Local Development Server
  static const String _localServerHost = 'localhost:8000';

  static String get baseUrl {
    if (kIsWeb) {
      return '/api/member';
    }
    if (isProduction) {
      return '$_productionDomain/api/member';
    }
    return 'http://$_localServerHost/api/member';
  }

  static String get adminBaseUrl {
    if (kIsWeb) {
      return '/api/admin';
    }
    if (isProduction) {
      return '$_productionDomain/api/admin';
    }
    return 'http://$_localServerHost/api/admin';
  }

  // Member Endpoints
  static const String listGyms = '/list_gyms.php';
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
  static const String plans = '/plans.php';
  static const String generateRenewalQr = '/generate_renewal_qr.php';
  static const String submitRenewalPayment = '/submit_renewal_payment.php';
  static const String createPaymentOrder = '/create_payment_order.php';
  static const String checkPaymentStatus = '/check_payment_status.php';

  // Admin Endpoints
  static const String adminDashboard = '/dashboard.php';
  static const String adminMembers = '/members.php';
  static const String adminMemberDetail = '/member_detail.php';
  static const String adminAddMember = '/add_member.php';
  static const String adminCollectPayment = '/collect_payment.php';
  static const String adminGymQr = '/gym_qr.php';
  static const String adminRates = '/rates.php';
}
