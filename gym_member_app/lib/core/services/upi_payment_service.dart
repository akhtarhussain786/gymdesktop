import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfsession/cfsession.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfenums.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpaymentgateway/cfpaymentgatewayservice.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfdropcheckoutpayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfupipayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfupi.dart';
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
      isPopular: true,
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
    name: 'Any UPI App (System Chooser)',
    packageName: '',
    schemePrefix: 'upi://pay',
    brandColor: Color(0xFF10B981),
    badgeText: 'All Apps',
    iconData: Icons.apps_rounded,
    isPopular: true,
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

    // Always include standard major apps so user can attempt direct launch
    for (final app in allKnownUpiApps) {
      if (!foundIds.contains(app.id)) {
        installed.add(app);
        foundIds.add(app.id);
      }
    }

    // Always include system chooser option at the top
    if (!foundIds.contains('generic')) {
      installed.insert(0, genericUpiApp);
    }

    return installed;
  }

  /// Fetches live UPI deep links directly from Cashfree client endpoint if not pre-populated
  static Future<Map<String, dynamic>?> fetchLiveUpiLinks({
    required String paymentSessionId,
    required String cashfreeMode,
  }) async {
    try {
      final baseUrl = (cashfreeMode.toLowerCase() == 'production' || cashfreeMode.toLowerCase() == 'prod')
          ? 'https://api.cashfree.com/pg'
          : 'https://sandbox.cashfree.com/pg';

      final url = Uri.parse('$baseUrl/orders/sessions');
      final headers = {
        'x-api-version': '2023-08-01',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };

      // 1. Try channel: 'link'
      final bodyLink = jsonEncode({
        'payment_session_id': paymentSessionId,
        'payment_method': {
          'upi': {
            'channel': 'link'
          }
        }
      });

      final responseLink = await http.post(url, headers: headers, body: bodyLink).timeout(const Duration(seconds: 8));
      if (responseLink.statusCode == 200) {
        final decoded = jsonDecode(responseLink.body);
        if (decoded is Map && decoded['data'] != null) {
          final data = decoded['data'];
          final Map<String, dynamic> results = {};
          if (data['payload'] is Map) {
            results.addAll(Map<String, dynamic>.from(data['payload']));
          }
          if (data['url'] != null) {
            results['default'] = data['url'].toString();
          }
          if (results.isNotEmpty) return results;
        }
      }

      // 2. Try channel: 'qrcode' (dynamic merchant UPI QR string)
      final bodyQr = jsonEncode({
        'payment_session_id': paymentSessionId,
        'payment_method': {
          'upi': {
            'channel': 'qrcode'
          }
        }
      });

      final responseQr = await http.post(url, headers: headers, body: bodyQr).timeout(const Duration(seconds: 8));
      if (responseQr.statusCode == 200) {
        final decoded = jsonDecode(responseQr.body);
        if (decoded is Map && decoded['data'] != null) {
          final data = decoded['data'];
          final Map<String, dynamic> results = {};
          if (data['payload'] is Map) {
            results.addAll(Map<String, dynamic>.from(data['payload']));
          } else if (data['payload'] is String) {
            results['default'] = data['payload'].toString();
          }
          if (data['url'] != null) {
            results['default'] = data['url'].toString();
          }
          if (results.isNotEmpty) return results;
        }
      }
    } catch (e) {
      debugPrint('Error fetching live UPI links from Cashfree client session: $e');
    }
    return null;
  }

  /// Launch chosen UPI app directly via Cashfree SDK or direct Intent
  static Future<bool> launchSelectedUpiApp({
    required InstalledUpiApp app,
    required String orderId,
    required double amount,
    String? paymentSessionId,
    String cashfreeMode = 'production',
    String? upiIntentUrl,
    Map<String, dynamic>? upiLinks,
    String gymName = 'Fitisify SaaS',
    Function(String orderId)? onVerify,
    Function(String error, String orderId)? onError,
  }) async {
    // 1. Try Cashfree Native SDK UPI Intent first if paymentSessionId is available
    if (paymentSessionId != null && paymentSessionId.isNotEmpty) {
      try {
        final env = (cashfreeMode.toLowerCase() == 'production' || cashfreeMode.toLowerCase() == 'prod')
            ? CFEnvironment.PRODUCTION
            : CFEnvironment.SANDBOX;

        if (onVerify != null && onError != null) {
          _initCallbacks(onVerify, onError);
        }

        final session = CFSessionBuilder()
            .setEnvironment(env)
            .setOrderId(orderId)
            .setPaymentSessionId(paymentSessionId)
            .build();

        CFUPI upi;
        if (app.id == 'generic' || app.packageName.isEmpty) {
          upi = CFUPIBuilder()
              .setChannel(CFUPIChannel.INTENT)
              .build();
        } else {
          upi = CFUPIBuilder()
              .setChannel(CFUPIChannel.INTENT)
              .setUPIID(app.packageName)
              .build();
        }

        final upiPayment = CFUPIPaymentBuilder()
            .setSession(session)
            .setUPI(upi)
            .build();

        debugPrint('Invoking Cashfree SDK UPI Payment for ${app.name} (${app.packageName})');
        _cfService.doPayment(upiPayment);
        return true;
      } catch (e) {
        debugPrint('Cashfree Native SDK UPI failed, falling back to direct intent URI: $e');
      }
    }

    // 2. Gather all existing upiLinks & upiIntentUrl
    return launchDirectUpiLink(
      app: app,
      upiIntentUrl: upiIntentUrl,
      upiLinks: upiLinks,
      paymentSessionId: paymentSessionId,
      cashfreeMode: cashfreeMode,
    );
  }

  /// Launch pre-resolved or direct UPI link for the given application
  static Future<bool> launchDirectUpiLink({
    required InstalledUpiApp app,
    String? upiIntentUrl,
    Map<String, dynamic>? upiLinks,
    String? paymentSessionId,
    String cashfreeMode = 'production',
  }) async {
    final Map<String, dynamic> links = upiLinks != null ? Map<String, dynamic>.from(upiLinks) : {};
    String? targetUrl = upiIntentUrl;

    // 1. If no valid gateway links provided and session ID exists, attempt fetch
    if ((targetUrl == null || targetUrl.isEmpty) && (paymentSessionId != null && paymentSessionId.isNotEmpty)) {
      final fetched = await fetchLiveUpiLinks(
        paymentSessionId: paymentSessionId,
        cashfreeMode: cashfreeMode,
      );
      if (fetched != null && fetched.isNotEmpty) {
        links.addAll(fetched);
        targetUrl = links['default'] ?? links['qrcode'] ?? (links.isNotEmpty ? links.values.first.toString() : null);
      }
    }

    // 2. Match app-specific URL from links
    if (links.isNotEmpty) {
      if (app.id != 'generic' && links.containsKey(app.id) && links[app.id] != null) {
        targetUrl = links[app.id].toString();
      } else if (app.id == 'gpay' && (links['gpay'] != null || links['googlepay'] != null)) {
        targetUrl = (links['gpay'] ?? links['googlepay']).toString();
      } else if (app.id == 'phonepe' && links['phonepe'] != null) {
        targetUrl = links['phonepe'].toString();
      } else if (app.id == 'paytm' && links['paytm'] != null) {
        targetUrl = links['paytm'].toString();
      } else if (app.id == 'bhim' && links['bhim'] != null) {
        targetUrl = links['bhim'].toString();
      } else if (app.id == 'cred' && links['cred'] != null) {
        targetUrl = links['cred'].toString();
      } else if (links.containsKey('default') && links['default'] != null) {
        targetUrl = links['default'].toString();
      } else if (links.containsKey('qrcode') && links['qrcode'] != null) {
        targetUrl = links['qrcode'].toString();
      }
    }

    if (targetUrl == null || targetUrl.isEmpty) {
      debugPrint('No direct gateway UPI link available for ${app.name}');
      return false;
    }

    // 3. Adapt URL scheme for targeted app if starting with generic upi://pay
    String finalUrl = targetUrl;
    if (app.id != 'generic' && app.schemePrefix != 'upi://pay' && targetUrl.startsWith('upi://pay?')) {
      final query = targetUrl.substring('upi://pay?'.length);
      finalUrl = '${app.schemePrefix}?$query';
    } else if (app.id == 'generic' && !targetUrl.startsWith('upi://pay?')) {
      final queryIndex = targetUrl.indexOf('?');
      if (queryIndex != -1) {
        final query = targetUrl.substring(queryIndex + 1);
        finalUrl = 'upi://pay?$query';
      }
    }

    debugPrint('Launching UPI Intent URL: $finalUrl (App: ${app.name})');

    // 4. Try launching target app with external application mode
    try {
      final uri = Uri.parse(finalUrl);
      final launched = await launchUrl(
        uri,
        mode: LaunchMode.externalApplication,
      );
      if (launched) return true;
    } catch (e) {
      debugPrint('Error launching specific scheme: $e');
    }

    // 5. Fallback: launch with standard generic upi:// scheme (triggers Android System Chooser)
    try {
      if (!finalUrl.startsWith('upi://pay?')) {
        final queryIndex = finalUrl.indexOf('?');
        if (queryIndex != -1) {
          final query = finalUrl.substring(queryIndex + 1);
          final genericUri = Uri.parse('upi://pay?$query');
          final fallbackLaunched = await launchUrl(genericUri, mode: LaunchMode.externalApplication);
          if (fallbackLaunched) return true;
        }
      }
    } catch (e) {
      debugPrint('Error launching generic fallback: $e');
    }

    return false;
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

      final dropCheckout = CFDropCheckoutPaymentBuilder()
          .setSession(session)
          .build();

      _cfService.doPayment(dropCheckout);
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
