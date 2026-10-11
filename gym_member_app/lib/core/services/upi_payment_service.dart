import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfsession/cfsession.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfenums.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpaymentgateway/cfpaymentgatewayservice.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfupipayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfupi.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfwebcheckoutpayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfupi/cfupiutils.dart';
import 'package:flutter_cashfree_pg_sdk/api/cferrorresponse/cferrorresponse.dart';

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

  /// Keys under which the server's `upi_links` may hold this app's deep link
  final List<String> linkKeys;

  const InstalledUpiApp({
    required this.id,
    required this.name,
    required this.packageName,
    required this.schemePrefix,
    required this.brandColor,
    required this.badgeText,
    required this.iconData,
    this.isPopular = false,
    this.linkKeys = const [],
  });

  bool get isGeneric => id == 'generic';

  /// The app id Cashfree's UPI intent expects: the package name on Android, the app's URL scheme on iOS.
  /// Empty for "Any UPI app" so the phone offers every installed UPI app.
  String get cashfreeAppId {
    if (isGeneric) return '';
    if (defaultTargetPlatform == TargetPlatform.iOS) {
      final scheme = Uri.tryParse(schemePrefix)?.scheme ?? '';
      return scheme.isEmpty ? '' : '$scheme://';
    }
    return packageName;
  }
}

class UpiPaymentService {
  static final CFPaymentGatewayService _cfService = CFPaymentGatewayService();

  /// Only real app deep links are ever opened for a payment (never http(s)/data: URLs such as a QR image)
  static const Set<String> upiSchemes = {
    'upi', 'tez', 'phonepe', 'paytmmp', 'paytm', 'bhim', 'credpay', 'whatsapp', 'amazonpay', 'mobikwik', 'freecharge',
  };

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
      linkKeys: ['phonepe'],
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
      linkKeys: ['gpay', 'googlepay', 'google_pay', 'tez'],
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
      linkKeys: ['paytm', 'paytmmp'],
    ),
    InstalledUpiApp(
      id: 'bhim',
      name: 'BHIM UPI',
      packageName: 'in.org.npci.upiapp',
      schemePrefix: 'bhim://pay',
      brandColor: Color(0xFF007A3D),
      badgeText: 'NPCI Official',
      iconData: Icons.shield_rounded,
      isPopular: true,
      linkKeys: ['bhim'],
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
      linkKeys: ['cred', 'credpay'],
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
      linkKeys: ['amazon', 'amazonpay'],
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
      linkKeys: ['whatsapp'],
    ),
  ];

  /// Generic UPI Intent option (triggers Android system UPI app chooser)
  static const InstalledUpiApp genericUpiApp = InstalledUpiApp(
    id: 'generic',
    name: 'Any UPI App',
    packageName: '',
    schemePrefix: 'upi://pay',
    brandColor: Color(0xFF10B981),
    badgeText: 'All Apps',
    iconData: Icons.apps_rounded,
    isPopular: true,
  );

  /// UPI apps actually installed on this phone ("Any UPI app" first).
  /// Uses a 2-tier discovery mechanism:
  /// 1. Cashfree Native SDK discovery via CFUPIUtils.getUPIApps() on Android.
  /// 2. Scheme queries matching AndroidManifest `<queries>` as an instant reliable fallback.
  static Future<List<InstalledUpiApp>> getInstalledUpiApps() async {
    final installed = <InstalledUpiApp>[genericUpiApp];

    // 1. Try Cashfree native package discovery on Android
    try {
      if (sdkSupported && defaultTargetPlatform == TargetPlatform.android) {
        final cfApps = await CFUPIUtils().getUPIApps();
        if (cfApps != null && cfApps.isNotEmpty) {
          final detectedPackages = <String>{};
          for (final item in cfApps) {
            if (item is Map) {
              final pkg = item['appPackage'] ?? item['package'] ?? item['id'] ?? item['packageName'];
              if (pkg != null && pkg.toString().trim().isNotEmpty) {
                detectedPackages.add(pkg.toString().trim().toLowerCase());
              }
            }
          }
          if (detectedPackages.isNotEmpty) {
            for (final app in allKnownUpiApps) {
              if (detectedPackages.contains(app.packageName.toLowerCase())) {
                if (!installed.any((a) => a.id == app.id)) {
                  installed.add(app);
                }
              }
            }
            if (installed.length > 1) {
              return installed;
            }
          }
        }
      }
    } catch (e) {
      debugPrint('CFUPIUtils discovery: $e');
    }

    // 2. Reliable fallback scheme detection
    for (final app in allKnownUpiApps) {
      try {
        if (await canLaunchUrl(Uri.parse(app.schemePrefix))) {
          if (!installed.any((a) => a.id == app.id)) {
            installed.add(app);
          }
        }
      } catch (_) {}
    }
    return installed;
  }

  static bool get sdkSupported =>
      !kIsWeb && (defaultTargetPlatform == TargetPlatform.android || defaultTargetPlatform == TargetPlatform.iOS);

  static bool isUpiDeepLink(String? url) {
    if (url == null || url.trim().isEmpty) return false;
    final uri = Uri.tryParse(url.trim());
    return uri != null && upiSchemes.contains(uri.scheme.toLowerCase());
  }

  static CFEnvironment environmentFor(String cashfreeMode) {
    final mode = cashfreeMode.trim().toLowerCase();
    return (mode == 'production' || mode == 'prod' || mode == 'live') ? CFEnvironment.PRODUCTION : CFEnvironment.SANDBOX;
  }

  /// Starts Cashfree's native UPI intent for [app] on the given order.
  /// - For specific apps (PhonePe, GPay, etc.): CFUPIChannel.INTENT with package name.
  /// - For "Any UPI App": CFUPIChannel.INTENT_WITH_UI which opens the SDK's native UPI selector.
  /// Returns false when the SDK could not be started.
  static bool startSdkUpiPayment({
    required InstalledUpiApp app,
    required String orderId,
    required String paymentSessionId,
    required String cashfreeMode,
    required void Function(String orderId) onVerify,
    required void Function(CFErrorResponse error, String orderId) onError,
  }) {
    if (!sdkSupported || orderId.isEmpty || paymentSessionId.isEmpty) return false;
    try {
      final session = CFSessionBuilder()
          .setEnvironment(environmentFor(cashfreeMode))
          .setOrderId(orderId)
          .setPaymentSessionId(paymentSessionId)
          .build();

      final channel = app.isGeneric ? CFUPIChannel.INTENT_WITH_UI : CFUPIChannel.INTENT;
      final upi = CFUPIBuilder()
          .setChannel(channel)
          .setUPIID(app.cashfreeAppId)
          .build();

      final upiPayment = CFUPIPaymentBuilder()
          .setSession(session)
          .setUPI(upi)
          .build();

      _cfService.setCallback(onVerify, onError);
      debugPrint('Invoking Cashfree SDK UPI intent for ${app.name} (${app.cashfreeAppId.isEmpty ? 'chooser' : app.cashfreeAppId})');
      _cfService.doPayment(upiPayment);
      return true;
    } catch (e) {
      debugPrint('Cashfree SDK UPI intent could not start: $e');
      return false;
    }
  }

  /// Complete launcher for chosen UPI app with native Cashfree SDK intent and fallback.
  static Future<bool> launchSelectedUpiApp({
    required InstalledUpiApp app,
    required String orderId,
    required String paymentSessionId,
    required String cashfreeMode,
    required void Function(String orderId) onVerify,
    required void Function(CFErrorResponse error, String orderId) onError,
    Map<String, dynamic>? upiLinks,
    String? upiIntentUrl,
    String? checkoutUrl,
  }) async {
    // 1. Try Cashfree native UPI Intent
    final started = startSdkUpiPayment(
      app: app,
      orderId: orderId,
      paymentSessionId: paymentSessionId,
      cashfreeMode: cashfreeMode,
      onVerify: onVerify,
      onError: onError,
    );
    if (started) return true;

    // 2. Direct deep link fallback (if sideloaded APK integrity check blocks SDK)
    final link = deepLinkFor(app, upiLinks: upiLinks, upiIntentUrl: upiIntentUrl);
    if (link != null) {
      return await launchDeepLink(link);
    }
    if (checkoutUrl != null && checkoutUrl.isNotEmpty) {
      return await openCheckoutInBrowser(checkoutUrl);
    }
    return false;
  }

  /// Official Cashfree Drop-in / Web Checkout launcher for Card & Netbanking payments
  static bool launchNativeDropin({
    required String orderId,
    required String paymentSessionId,
    required String cashfreeMode,
    required void Function(String orderId) onVerify,
    required void Function(CFErrorResponse error, String orderId) onError,
  }) {
    if (!sdkSupported || orderId.isEmpty || paymentSessionId.isEmpty) return false;
    try {
      final session = CFSessionBuilder()
          .setEnvironment(environmentFor(cashfreeMode))
          .setOrderId(orderId)
          .setPaymentSessionId(paymentSessionId)
          .build();

      final webCheckout = CFWebCheckoutPaymentBuilder()
          .setSession(session)
          .build();

      _cfService.setCallback(onVerify, onError);
      debugPrint('Invoking Cashfree SDK Web Checkout for order $orderId');
      _cfService.doPayment(webCheckout);
      return true;
    } catch (e) {
      debugPrint('Cashfree SDK Web Checkout could not start: $e');
      return false;
    }
  }

  static String _errorText(CFErrorResponse error) =>
      '${error.getCode()} ${error.getType()} ${error.getStatus()} ${error.getMessage()}'.toLowerCase();

  /// Cashfree's production integrity check refuses UPI intent for apps not installed from the Play Store.
  static bool isInstallerNotApproved(CFErrorResponse error) {
    final text = _errorText(error);
    return text.contains('installer') || text.contains('not_approved') || text.contains('not approved') || text.contains('integrity');
  }

  /// True when Cashfree reports that the customer cancelled / the payment itself failed,
  /// as opposed to the SDK being unable to start (e.g. installer_package_not_approved).
  static bool isPaymentAttemptFailure(CFErrorResponse error) {
    if (isInstallerNotApproved(error)) return false;
    final text = _errorText(error);
    return text.contains('cancel') ||
        text.contains('dropped') ||
        text.contains('user_aborted') ||
        text.contains('payment_failed') ||
        text.contains('declined');
  }

  /// The server-provided deep link to use for [app] (app-specific first, then the generic upi:// link).
  static String? deepLinkFor(InstalledUpiApp app, {Map<String, dynamic>? upiLinks, String? upiIntentUrl}) {
    final links = <String, String>{};
    upiLinks?.forEach((key, value) {
      final link = value?.toString().trim() ?? '';
      if (isUpiDeepLink(link)) links[key.toLowerCase()] = link;
    });

    if (!app.isGeneric) {
      for (final key in app.linkKeys) {
        final link = links[key];
        if (link != null) return link;
      }
    }

    String? link = links['default'];
    if (link == null && isUpiDeepLink(upiIntentUrl)) link = upiIntentUrl!.trim();
    link ??= links.values.isNotEmpty ? links.values.first : null;
    if (link == null) return null;

    // "Any UPI app" must open the system chooser: use the standard upi:// form of the same payment request
    if (app.isGeneric && !link.toLowerCase().startsWith('upi://')) {
      final query = Uri.tryParse(link)?.query ?? '';
      if (query.contains('pa=')) return 'upi://pay?$query';
    }
    return link;
  }

  /// Opens a UPI deep link in the UPI app (or Android's chooser for upi://). No canLaunchUrl() gate:
  /// on Android 11+ it would hide apps that are not declared in `<queries>`.
  static Future<bool> launchDeepLink(String link) async {
    if (!isUpiDeepLink(link)) return false;
    try {
      return await launchUrl(Uri.parse(link.trim()), mode: LaunchMode.externalApplication);
    } catch (e) {
      debugPrint('Could not open UPI link: $e');
      return false;
    }
  }

  /// Opens the hosted checkout page in the phone's browser (it can open any UPI app itself).
  static Future<bool> openCheckoutInBrowser(String checkoutUrl) async {
    final uri = Uri.tryParse(checkoutUrl.trim());
    if (uri == null || !(uri.scheme == 'https' || uri.scheme == 'http')) return false;
    try {
      return await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (e) {
      debugPrint('Could not open checkout page: $e');
      return false;
    }
  }
}
