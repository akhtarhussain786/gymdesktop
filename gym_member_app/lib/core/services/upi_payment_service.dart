import 'package:flutter/material.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfsession/cfsession.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfenums.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpaymentgateway/cfpaymentgatewayservice.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfwebcheckoutpayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cferrorresponse/cferrorresponse.dart';
import 'package:url_launcher/url_launcher.dart';

/// Model representing a detected or supported UPI Application on the device
class InstalledUpiApp {
  final String id;
  final String name;
  final String packageName;
  final String schemePrefix;
  final Color brandColor;
  final String badgeText;
  final IconData iconData;
  final bool isPopular;

  const InstalledUpiApp({
    required this.id,
    required this.name,
    required this.packageName,
    required this.schemePrefix,
    required this.brandColor,
    required this.badgeText,
    required this.iconData,
    this.isPopular = false,
  });
}

class UpiPaymentService {
  static final CFPaymentGatewayService _cfService = CFPaymentGatewayService();
  static bool _callbacksInitialized = false;

  /// Complete list of major Indian UPI application definitions
  static const List<InstalledUpiApp> allKnownUpiApps = [
    InstalledUpiApp(
      id: 'phonepe',
      name: 'PhonePe',
      packageName: 'com.phonepe.app',
      schemePrefix: 'phonepe://pay',
      brandColor: Color(0xFF5F259F),
      badgeText: 'Instant UPI',
      iconData: Icons.account_balance_wallet_rounded,
      isPopular: true,
    ),
    InstalledUpiApp(
      id: 'gpay',
      name: 'Google Pay',
      packageName: 'com.google.android.apps.nbu.paisa.user',
      schemePrefix: 'tez://upi/pay',
      brandColor: Color(0xFF4285F4),
      badgeText: 'Instant UPI',
      iconData: Icons.g_mobiledata_rounded,
      isPopular: true,
    ),
    InstalledUpiApp(
      id: 'paytm',
      name: 'Paytm UPI',
      packageName: 'net.one97.paytm',
      schemePrefix: 'paytmmp://pay',
      brandColor: Color(0xFF00BAF2),
      badgeText: 'Instant UPI',
      iconData: Icons.payment_rounded,
      isPopular: true,
    ),
    InstalledUpiApp(
      id: 'bhim',
      name: 'BHIM UPI',
      packageName: 'in.org.npci.upiapp',
      schemePrefix: 'bhim://pay',
      brandColor: Color(0xFF007A3D),
      badgeText: 'NPCI Official',
      iconData: Icons.shield_rounded,
      isPopular: false,
    ),
    InstalledUpiApp(
      id: 'cred',
      name: 'CRED UPI',
      packageName: 'com.dreamplug.androidapp',
      schemePrefix: 'credpay://pay',
      brandColor: Color(0xFF1E1E1E),
      badgeText: 'Cashback',
      iconData: Icons.credit_card_rounded,
      isPopular: false,
    ),
    InstalledUpiApp(
      id: 'amazon',
      name: 'Amazon Pay',
      packageName: 'in.amazon.mShop.android.shopping',
      schemePrefix: 'amazonpay://pay',
      brandColor: Color(0xFFFF9900),
      badgeText: 'Amazon',
      iconData: Icons.shopping_bag_rounded,
      isPopular: false,
    ),
    InstalledUpiApp(
      id: 'whatsapp',
      name: 'WhatsApp Pay',
      packageName: 'com.whatsapp',
      schemePrefix: 'whatsapp://pay',
      brandColor: Color(0xFF25D366),
      badgeText: 'WhatsApp',
      iconData: Icons.chat_rounded,
      isPopular: false,
    ),
  ];

  /// Generic UPI Intent option (triggers Android system UPI app chooser)
  static const InstalledUpiApp genericUpiApp = InstalledUpiApp(
    id: 'generic',
    name: 'Any UPI App (Chooser)',
    packageName: '',
    schemePrefix: 'upi://pay',
    brandColor: Color(0xFF10B981),
    badgeText: 'All Apps',
    iconData: Icons.qr_code_2_rounded,
    isPopular: false,
  );

  /// Detects which UPI apps are installed and queryable on the user's Android phone
  static Future<List<InstalledUpiApp>> getInstalledUpiApps() async {
    final List<InstalledUpiApp> installed = [];
    final Set<String> foundIds = {};

    // Check scheme availability via url_launcher & AndroidManifest package queries
    for (final app in allKnownUpiApps) {
      if (!foundIds.contains(app.id)) {
        try {
          final uri = Uri.parse(app.schemePrefix);
          final canOpen = await canLaunchUrl(uri);
          if (canOpen) {
            foundIds.add(app.id);
            installed.add(app);
          }
        } catch (_) {}
      }
    }

    // Always include default apps (GPay, PhonePe, Paytm) so user can attempt direct launch
    if (installed.isEmpty) {
      installed.addAll(allKnownUpiApps.where((a) => a.isPopular));
    }

    // Always append generic UPI intent option at the end
    if (!foundIds.contains('generic')) {
      installed.add(genericUpiApp);
    }

    return installed;
  }

  /// Launch chosen UPI app directly with transaction payload
  static Future<bool> launchSelectedUpiApp({
    required InstalledUpiApp app,
    required String orderId,
    required double amount,
    String? upiIntentUrl,
    Map<String, dynamic>? upiLinks,
    String gymName = 'Fitisify SaaS',
  }) async {
    // 1. Check if backend provided specific app deep link (e.g. from Cashfree /orders/sessions)
    String? targetUrl;
    if (upiLinks != null && upiLinks.isNotEmpty) {
      if (upiLinks.containsKey(app.id) && upiLinks[app.id] != null) {
        targetUrl = upiLinks[app.id].toString();
      } else if (app.id == 'gpay' && upiLinks.containsKey('gpay')) {
        targetUrl = upiLinks['gpay'].toString();
      } else if (app.id == 'phonepe' && upiLinks.containsKey('phonepe')) {
        targetUrl = upiLinks['phonepe'].toString();
      } else if (app.id == 'paytm' && upiLinks.containsKey('paytm')) {
        targetUrl = upiLinks['paytm'].toString();
      } else if (upiLinks.containsKey('default') && upiLinks['default'] != null) {
        targetUrl = upiLinks['default'].toString();
      }
    }

    // 2. Fallback to upiIntentUrl if provided
    targetUrl ??= upiIntentUrl;

    // If no valid gateway intent URL provided, return false to trigger Cashfree Native SDK flow
    if (targetUrl == null || targetUrl.isEmpty) {
      debugPrint('No direct gateway UPI link provided for ${app.name}, delegating to Cashfree Native SDK');
      return false;
    }

    // Transform scheme for app-specific deep links if using generic upi://
    if (app.schemePrefix != 'upi://pay' && targetUrl.startsWith('upi://pay?')) {
      final query = targetUrl.substring('upi://pay?'.length);
      targetUrl = '${app.schemePrefix}?$query';
    }

    debugPrint('Launching UPI Intent URL: $targetUrl (App: ${app.name})');

    try {
      final uri = Uri.parse(targetUrl);
      final launched = await launchUrl(
        uri,
        mode: LaunchMode.externalApplication,
      );
      return launched;
    } catch (e) {
      debugPrint('Error launching UPI app ${app.name}: $e');
      // Retry with standard upi:// scheme as fallback
      try {
        if (!targetUrl.startsWith('upi://pay?')) {
          final queryIndex = targetUrl.indexOf('?');
          if (queryIndex != -1) {
            final query = targetUrl.substring(queryIndex + 1);
            final fallbackUri = Uri.parse('upi://pay?$query');
            return await launchUrl(fallbackUri, mode: LaunchMode.externalApplication);
          }
        }
      } catch (_) {}
      return false;
    }
  }

  /// Launch Native Cashfree Drop-in SDK for Cards (Debit/Credit) & Net Banking
  static Future<void> launchNativeDropin({
    required String orderId,
    required String paymentSessionId,
    required String cashfreeMode,
    required Function(String orderId) onVerify,
    required Function(String error, String orderId) onError,
  }) async {
    try {
      _initCallbacks(onVerify, onError);

      final env = (cashfreeMode.toLowerCase() == 'production' || cashfreeMode.toLowerCase() == 'prod')
          ? CFEnvironment.PRODUCTION
          : CFEnvironment.SANDBOX;

      final session = CFSessionBuilder()
          .setEnvironment(env)
          .setOrderId(orderId)
          .setPaymentSessionId(paymentSessionId)
          .build();

      final webPayment = CFWebCheckoutPaymentBuilder()
          .setSession(session)
          .build();

      _cfService.doPayment(webPayment);
    } catch (e) {
      debugPrint('Native Cashfree SDK Dropin Error: $e');
      onError(e.toString(), orderId);
    }
  }

  static void _initCallbacks(Function(String) onVerify, Function(String, String) onError) {
    if (!_callbacksInitialized) {
      _cfService.setCallback(
        (orderId) {
          debugPrint('Cashfree SDK Callback Verified: $orderId');
          onVerify(orderId);
        },
        (CFErrorResponse errorResponse, String orderId) {
          debugPrint('Cashfree SDK Callback Error: ${errorResponse.getMessage()} (Order: $orderId)');
          onError(errorResponse.getMessage() ?? 'Payment was cancelled or failed', orderId);
        },
      );
      _callbacksInitialized = true;
    }
  }
}
