import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/admin_provider.dart';

class InAppPaymentCheckoutScreen extends StatefulWidget {
  final String orderId;
  final String checkoutUrl;
  final String planName;
  final double amount;
  final String currency;

  const InAppPaymentCheckoutScreen({
    super.key,
    required this.orderId,
    required this.checkoutUrl,
    required this.planName,
    required this.amount,
    this.currency = '₹',
  });

  @override
  State<InAppPaymentCheckoutScreen> createState() => _InAppPaymentCheckoutScreenState();
}

class _InAppPaymentCheckoutScreenState extends State<InAppPaymentCheckoutScreen> with WidgetsBindingObserver {
  late final WebViewController _controller;
  int _loadingProgress = 0;
  bool _isLoading = true;
  bool _isVerifying = false;
  Timer? _autoPollTimer;
  int _pollAttempts = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _initWebViewController();
    _startBackgroundPolling();
  }

  void _startBackgroundPolling() {
    _autoPollTimer = Timer.periodic(const Duration(seconds: 4), (timer) async {
      _pollAttempts++;
      if (_pollAttempts > 45) {
        timer.cancel();
        return;
      }
      if (!_isVerifying && mounted) {
        await _checkPaymentStatus(silent: true);
      }
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _autoPollTimer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && !_isVerifying && mounted) {
      // User just returned from PhonePe / Google Pay / Paytm app!
      _checkPaymentStatus(silent: false);
    }
  }

  void _initWebViewController() {
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFF0F1015))
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (progress) {
            if (mounted) {
              setState(() {
                _loadingProgress = progress;
                _isLoading = progress < 100;
              });
            }
          },
          onPageStarted: (url) {
            if (mounted) {
              setState(() {
                _isLoading = true;
              });
            }
            _checkUrlForCompletion(url);
          },
          onPageFinished: (url) {
            if (mounted) {
              setState(() {
                _isLoading = false;
              });
            }
            _checkUrlForCompletion(url);
          },
          onWebResourceError: (error) {
            debugPrint('WebView Resource Error: ${error.description}');
          },
          onNavigationRequest: (request) async {
            final url = request.url;
            debugPrint('WebView Navigation Request: $url');

            // 1. Check if redirecting to payment completion callback
            if (url.contains('saas-renew-callback.php') ||
                url.contains('callback') ||
                url.contains('order_status=PAID') ||
                url.contains('status=approved')) {
              _checkPaymentStatus(silent: false);
              return NavigationDecision.navigate;
            }

            // 2. Intercept UPI Apps & Native Deep Links (PhonePe, GPay, Paytm, BHIM, etc.)
            if (_isDeepLinkUrl(url)) {
              await _launchDeepLink(url);
              return NavigationDecision.prevent;
            }

            return NavigationDecision.navigate;
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.checkoutUrl));
  }

  bool _isDeepLinkUrl(String url) {
    final lower = url.toLowerCase();
    return lower.startsWith('upi://') ||
        lower.startsWith('phonepe://') ||
        lower.startsWith('tez://') ||
        lower.startsWith('gpay://') ||
        lower.startsWith('paytmmp://') ||
        lower.startsWith('bhim://') ||
        lower.startsWith('credpay://') ||
        lower.startsWith('intent://') ||
        lower.startsWith('whatsapp://');
  }

  Future<void> _launchDeepLink(String url) async {
    try {
      Uri? targetUri;
      if (url.startsWith('intent://')) {
        // Extract scheme & fallback from Android intent:// URI
        final schemeMatch = RegExp(r'scheme=([^;]+)').firstMatch(url);
        final scheme = schemeMatch?.group(1) ?? 'upi';
        final rawPath = url.replaceFirst(RegExp(r'^intent:\/\/'), '').split('#Intent;')[0];
        targetUri = Uri.tryParse('$scheme://$rawPath') ?? Uri.tryParse(url);
      } else {
        targetUri = Uri.tryParse(url);
      }

      if (targetUri != null) {
        final launched = await launchUrl(
          targetUri,
          mode: LaunchMode.externalNonBrowserApplication,
        );
        if (!launched) {
          await launchUrl(targetUri, mode: LaunchMode.externalApplication);
        }
      }
    } catch (e) {
      debugPrint('Deep link launch error: $e');
    }
  }

  void _checkUrlForCompletion(String url) {
    if (url.contains('saas-renew-callback.php') || url.contains('status=approved') || url.contains('status=paid')) {
      _checkPaymentStatus(silent: false);
    }
  }

  Future<void> _checkPaymentStatus({bool silent = false}) async {
    if (_isVerifying) return;
    _isVerifying = true;

    try {
      final success = await context.read<AdminProvider>().verifySaasOrder(widget.orderId);
      if (success && mounted) {
        _autoPollTimer?.cancel();
        Navigator.pop(context, true); // Return success to calling screen!
      } else if (!silent && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Payment not completed yet. Please finish payment in your UPI app.'),
            backgroundColor: AppColors.warning,
            duration: Duration(seconds: 3),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isVerifying = false;
        });
      }
    }
  }

  Future<bool> _onWillPop() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1E1E2C),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: Text(
          'Cancel Payment?',
          style: GoogleFonts.outfit(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 17),
        ),
        content: Text(
          'Are you sure you want to exit? Your subscription for ${widget.planName} will not be activated until payment is completed.',
          style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 13),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Continue Payment', style: TextStyle(color: AppColors.lime, fontWeight: FontWeight.bold)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.danger.withValues(alpha: 0.2),
              foregroundColor: AppColors.danger,
              elevation: 0,
            ),
            child: const Text('Exit'),
          ),
        ],
      ),
    );
    return confirm ?? false;
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) return;
        final shouldPop = await _onWillPop();
        if (shouldPop && context.mounted) {
          _autoPollTimer?.cancel();
          Navigator.pop(context, false);
        }
      },
      child: Scaffold(
        backgroundColor: const Color(0xFF0F1015),
        appBar: AppBar(
          backgroundColor: const Color(0xFF171821),
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 18),
            onPressed: () async {
              final shouldPop = await _onWillPop();
              if (shouldPop && context.mounted) {
                _autoPollTimer?.cancel();
                Navigator.pop(context, false);
              }
            },
          ),
          titleSpacing: 0,
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(Icons.shield_outlined, color: AppColors.lime, size: 15),
                  const SizedBox(width: 5),
                  Text(
                    'Secure In-App Payment',
                    style: GoogleFonts.outfit(fontSize: 15, fontWeight: FontWeight.w700, color: Colors.white),
                  ),
                ],
              ),
              const SizedBox(height: 2),
              Text(
                '${widget.planName} • ${widget.currency}${widget.amount.toStringAsFixed(0)}',
                style: GoogleFonts.plusJakartaSans(fontSize: 11, color: Colors.white54, fontWeight: FontWeight.w500),
              ),
            ],
          ),
          actions: [
            IconButton(
              icon: const Icon(Icons.refresh_rounded, color: Colors.white70, size: 20),
              tooltip: 'Reload Checkout',
              onPressed: () => _controller.reload(),
            ),
            IconButton(
              icon: const Icon(Icons.open_in_browser_rounded, color: Color(0xFF00CEC9), size: 20),
              tooltip: 'Open in Browser',
              onPressed: () async {
                final uri = Uri.parse(widget.checkoutUrl);
                if (await canLaunchUrl(uri)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                }
              },
            ),
            const SizedBox(width: 4),
          ],
          bottom: _isLoading
              ? PreferredSize(
                  preferredSize: const Size.fromHeight(3),
                  child: LinearProgressIndicator(
                    value: _loadingProgress > 0 ? _loadingProgress / 100 : null,
                    backgroundColor: Colors.white10,
                    color: AppColors.lime,
                    minHeight: 3,
                  ),
                )
              : null,
        ),
        body: Stack(
          children: [
            WebViewWidget(controller: _controller),
            if (_isLoading && _loadingProgress < 60)
              Container(
                color: const Color(0xFF0F1015),
                alignment: Alignment.center,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const CircularProgressIndicator(color: AppColors.lime, strokeWidth: 3),
                    const SizedBox(height: 18),
                    Text(
                      'Connecting to Cashfree Gateway...',
                      style: GoogleFonts.outfit(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Loading UPI, Cards & Net Banking options',
                      style: GoogleFonts.plusJakartaSans(color: Colors.white38, fontSize: 12),
                    ),
                  ],
                ),
              ),
          ],
        ),
        bottomNavigationBar: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          decoration: BoxDecoration(
            color: const Color(0xFF171821),
            border: Border(top: BorderSide(color: Colors.white.withValues(alpha: 0.08))),
          ),
          child: SafeArea(
            top: false,
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Total Payable',
                        style: GoogleFonts.plusJakartaSans(fontSize: 11, color: Colors.white54),
                      ),
                      Text(
                        '${widget.currency}${widget.amount.toStringAsFixed(0)}',
                        style: GoogleFonts.outfit(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.lime),
                      ),
                    ],
                  ),
                ),
                ElevatedButton.icon(
                  onPressed: _isVerifying ? null : () => _checkPaymentStatus(silent: false),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.lime,
                    foregroundColor: Colors.black,
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    elevation: 0,
                  ),
                  icon: _isVerifying
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.black),
                        )
                      : const Icon(Icons.check_circle_rounded, size: 18),
                  label: Text(
                    _isVerifying ? 'Verifying...' : 'Verify Payment',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 13),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
