import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../../core/services/upi_payment_service.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';

class InAppPaymentCheckoutScreen extends StatefulWidget {
  final String orderId;
  final String checkoutUrl;
  final String planName;
  final double amount;
  final String currency;
  final String? paymentSessionId;
  final String? upiIntentUrl;
  final Map<String, dynamic>? upiLinks;
  final String cashfreeMode;
  final String? gymName;

  const InAppPaymentCheckoutScreen({
    super.key,
    required this.orderId,
    required this.checkoutUrl,
    required this.planName,
    required this.amount,
    this.currency = '₹',
    this.paymentSessionId,
    this.upiIntentUrl,
    this.upiLinks,
    this.cashfreeMode = 'production',
    this.gymName,
  });

  @override
  State<InAppPaymentCheckoutScreen> createState() => _InAppPaymentCheckoutScreenState();
}

class _InAppPaymentCheckoutScreenState extends State<InAppPaymentCheckoutScreen> with WidgetsBindingObserver {
  List<InstalledUpiApp> _installedUpiApps = [];
  bool _isLoadingApps = true;
  bool _isLaunchingApp = false;
  String _launchingAppName = '';

  // Payment Verification State
  bool _isVerifying = false;
  bool _isPaymentSuccess = false;
  String? _verificationError;
  int _pollAttempts = 0;
  Timer? _autoPollTimer;
  bool _hasAttemptedPayment = false;

  // Optional WebView for Cards / Netbanking fallback
  bool _showWebCheckout = false;
  late final WebViewController _webController;
  bool _isWebLoading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _detectInstalledUpiApps();
    _initWebFallback();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _autoPollTimer?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _hasAttemptedPayment && !_isVerifying && !_isPaymentSuccess && mounted) {
      debugPrint('App resumed after UPI payment attempt. Starting backend verification for order: ${widget.orderId}');
      _startVerificationPolling();
    }
  }

  Future<void> _detectInstalledUpiApps() async {
    setState(() => _isLoadingApps = true);
    final apps = await UpiPaymentService.getInstalledUpiApps();
    if (mounted) {
      setState(() {
        _installedUpiApps = apps;
        _isLoadingApps = false;
      });
    }
  }

  void _initWebFallback() {
    _webController = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFF0F1015))
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) {
            if (mounted) setState(() => _isWebLoading = true);
          },
          onPageFinished: (url) {
            if (mounted) setState(() => _isWebLoading = false);
            if (url.contains('callback') || url.contains('status=approved') || url.contains('order_status=PAID')) {
              _startVerificationPolling();
            }
          },
          onNavigationRequest: (request) async {
            final url = request.url;
            if (url.contains('saas-renew-callback.php') || url.contains('callback') || url.contains('status=approved')) {
              _startVerificationPolling();
              return NavigationDecision.navigate;
            }
            return NavigationDecision.navigate;
          },
        ),
      );
  }

  Future<void> _handleUpiAppSelection(InstalledUpiApp app) async {
    if (_isLaunchingApp || _isVerifying) return;

    final gym = widget.gymName ?? context.read<AuthProvider>().currentTenant?.gymName ?? 'Fitisify SaaS';

    setState(() {
      _isLaunchingApp = true;
      _launchingAppName = app.name;
      _verificationError = null;
    });

    final success = await UpiPaymentService.launchSelectedUpiApp(
      app: app,
      orderId: widget.orderId,
      amount: widget.amount,
      upiIntentUrl: widget.upiIntentUrl,
      upiLinks: widget.upiLinks,
      gymName: gym,
    );

    if (mounted) {
      setState(() {
        _isLaunchingApp = false;
        if (success) {
          _hasAttemptedPayment = true;
        } else {
          _verificationError = 'Could not open ${app.name}. Please select another UPI app or pay with Card/Netbanking.';
        }
      });
    }
  }

  Future<void> _handleCardsNetBanking() async {
    if (widget.paymentSessionId != null && widget.paymentSessionId!.isNotEmpty) {
      // Launch native Cashfree Drop-in SDK
      await UpiPaymentService.launchNativeDropin(
        orderId: widget.orderId,
        paymentSessionId: widget.paymentSessionId!,
        cashfreeMode: widget.cashfreeMode,
        onVerify: (orderId) {
          if (mounted) _startVerificationPolling();
        },
        onError: (error, orderId) {
          if (mounted) {
            setState(() {
              _verificationError = error;
            });
          }
        },
      );
    } else {
      // Fallback to secure hosted web checkout
      setState(() {
        _showWebCheckout = true;
      });
      _webController.loadRequest(Uri.parse(widget.checkoutUrl));
    }
  }

  void _startVerificationPolling() {
    if (_isVerifying || _isPaymentSuccess) return;

    setState(() {
      _isVerifying = true;
      _pollAttempts = 0;
      _verificationError = null;
    });

    _autoPollTimer?.cancel();
    _autoPollTimer = Timer.periodic(const Duration(seconds: 3), (timer) async {
      _pollAttempts++;

      final isPaid = await _verifyWithBackend();
      if (isPaid) {
        timer.cancel();
        if (mounted) {
          setState(() {
            _isVerifying = false;
            _isPaymentSuccess = true;
          });
          await Future.delayed(const Duration(milliseconds: 1200));
          if (mounted) {
            Navigator.of(context).pop(true); // Return success to caller
          }
        }
      } else if (_pollAttempts >= 10) {
        timer.cancel();
        if (mounted) {
          setState(() {
            _isVerifying = false;
            _verificationError = 'Payment verification is taking longer than usual. If amount was deducted, tap "Check Status" below.';
          });
        }
      }
    });
  }

  Future<bool> _verifyWithBackend() async {
    try {
      final admin = context.read<AdminProvider>();
      final isVerified = await admin.verifySaasOrder(widget.orderId);
      return isVerified;
    } catch (_) {
      return false;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_showWebCheckout) {
      return _buildWebCheckoutView();
    }

    return Scaffold(
      backgroundColor: const Color(0xFF0F1015),
      appBar: AppBar(
        backgroundColor: const Color(0xFF13141C),
        elevation: 0,
        title: Text(
          'Secure Checkout',
          style: GoogleFonts.outfit(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.white),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
          onPressed: () => _handleBackPress(),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // 1. Order Summary Card
              _buildOrderSummaryCard(),
              const SizedBox(height: 18),

              // 2. Active Verification State Banner
              if (_isVerifying || _isPaymentSuccess || _verificationError != null)
                _buildVerificationStatusCard(),

              if (_isVerifying || _isPaymentSuccess || _verificationError != null)
                const SizedBox(height: 18),

              // 3. UPI Apps Section Header
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: AppColors.lime.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: AppColors.lime.withValues(alpha: 0.3)),
                    ),
                    child: Text(
                      'FASTEST',
                      style: GoogleFonts.plusJakartaSans(
                        color: AppColors.lime,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    'PAY VIA INSTALLED UPI APPS',
                    style: GoogleFonts.plusJakartaSans(
                      color: Colors.white70,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.5,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),

              // 4. Installed UPI Apps Grid / List
              if (_isLoadingApps)
                const Padding(
                  padding: EdgeInsets.all(24.0),
                  child: Center(
                    child: CircularProgressIndicator(color: AppColors.lime, strokeWidth: 2.5),
                  ),
                )
              else
                ..._installedUpiApps.map((app) => _buildUpiAppTile(app)),

              const SizedBox(height: 20),

              // 5. Divider
              Row(
                children: [
                  const Expanded(child: Divider(color: Colors.white12)),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    child: Text(
                      'OR OTHER PAYMENT MODES',
                      style: GoogleFonts.plusJakartaSans(color: Colors.white38, fontSize: 11, fontWeight: FontWeight.w700),
                    ),
                  ),
                  const Expanded(child: Divider(color: Colors.white12)),
                ],
              ),
              const SizedBox(height: 16),

              // 6. Cards & Net Banking Tiles
              _buildOtherPaymentTile(
                icon: Icons.credit_card_rounded,
                title: 'Debit / Credit Card',
                subtitle: 'Visa, MasterCard, RuPay, Maestro',
                onTap: _handleCardsNetBanking,
              ),
              const SizedBox(height: 10),
              _buildOtherPaymentTile(
                icon: Icons.account_balance_rounded,
                title: 'Net Banking',
                subtitle: 'SBI, HDFC, ICICI, Axis, Kotak & 50+ Banks',
                onTap: _handleCardsNetBanking,
              ),

              const SizedBox(height: 28),

              // 7. Security Trust Badge
              Center(
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.lock_rounded, color: Color(0xFF10B981), size: 14),
                    const SizedBox(width: 6),
                    Text(
                      '256-Bit SSL Encrypted • PCI-DSS Level 1 Gateway',
                      style: GoogleFonts.plusJakartaSans(color: Colors.white38, fontSize: 11),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildOrderSummaryCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF161822),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withValues(alpha: 0.08)),
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    widget.planName,
                    style: GoogleFonts.outfit(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Order ID: #${widget.orderId}',
                    style: GoogleFonts.plusJakartaSans(color: Colors.white38, fontSize: 11),
                  ),
                ],
              ),
              Text(
                '${widget.currency}${widget.amount.toStringAsFixed(2)}',
                style: GoogleFonts.outfit(
                  color: AppColors.lime,
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildUpiAppTile(InstalledUpiApp app) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: const Color(0xFF161822),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: app.isPopular
              ? AppColors.lime.withValues(alpha: 0.25)
              : Colors.white.withValues(alpha: 0.06),
        ),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: (_isLaunchingApp || _isVerifying) ? null : () => _handleUpiAppSelection(app),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            child: Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: app.brandColor.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: app.brandColor.withValues(alpha: 0.3)),
                  ),
                  child: Icon(app.iconData, color: app.brandColor, size: 22),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        app.name,
                        style: GoogleFonts.plusJakartaSans(
                          color: Colors.white,
                          fontSize: 14.5,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Instant payment with UPI PIN',
                        style: GoogleFonts.plusJakartaSans(
                          color: Colors.white38,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                if (_isLaunchingApp && _launchingAppName == app.name)
                  const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(color: AppColors.lime, strokeWidth: 2),
                  )
                else
                  const Icon(Icons.arrow_forward_ios_rounded, color: Colors.white30, size: 14),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildOtherPaymentTile({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFF161822),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.white.withValues(alpha: 0.06)),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: (_isLaunchingApp || _isVerifying) ? null : onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            child: Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.06),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(icon, color: Colors.white70, size: 22),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: GoogleFonts.plusJakartaSans(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: GoogleFonts.plusJakartaSans(color: Colors.white38, fontSize: 11),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.arrow_forward_ios_rounded, color: Colors.white30, size: 14),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildVerificationStatusCard() {
    if (_isPaymentSuccess) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: const Color(0xFF064E3B),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFF10B981)),
        ),
        child: Row(
          children: [
            const Icon(Icons.check_circle_rounded, color: Color(0xFF34D399), size: 28),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Payment Verified Successfully!',
                    style: GoogleFonts.outfit(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold),
                  ),
                  Text(
                    'SaaS subscription activated. Returning to dashboard...',
                    style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 12),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    if (_isVerifying) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: const Color(0xFF1E1E2C),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.lime.withValues(alpha: 0.4)),
        ),
        child: Row(
          children: [
            const SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(color: AppColors.lime, strokeWidth: 2.5),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Verifying Payment with Gateway...',
                    style: GoogleFonts.outfit(color: Colors.white, fontSize: 14.5, fontWeight: FontWeight.bold),
                  ),
                  Text(
                    'Please wait while we confirm your transaction (Attempt $_pollAttempts/10)',
                    style: GoogleFonts.plusJakartaSans(color: Colors.white60, fontSize: 11.5),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    if (_verificationError != null) {
      return Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: const Color(0xFF7F1D1D).withValues(alpha: 0.3),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFEF4444).withValues(alpha: 0.5)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.info_outline_rounded, color: Color(0xFFF87171), size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    _verificationError!,
                    style: GoogleFonts.plusJakartaSans(color: const Color(0xFFFCA5A5), fontSize: 12),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton.icon(
                  onPressed: _startVerificationPolling,
                  icon: const Icon(Icons.refresh_rounded, size: 14, color: AppColors.lime),
                  label: Text(
                    'Check Status Again',
                    style: GoogleFonts.plusJakartaSans(color: AppColors.lime, fontSize: 12, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            ),
          ],
        ),
      );
    }

    return const SizedBox.shrink();
  }

  Widget _buildWebCheckoutView() {
    return Scaffold(
      backgroundColor: const Color(0xFF0F1015),
      appBar: AppBar(
        backgroundColor: const Color(0xFF13141C),
        title: Text(
          'Card & Net Banking Payment',
          style: GoogleFonts.outfit(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white),
        ),
        leading: IconButton(
          icon: const Icon(Icons.close_rounded, color: Colors.white),
          onPressed: () => setState(() => _showWebCheckout = false),
        ),
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _webController),
          if (_isWebLoading)
            const Center(
              child: CircularProgressIndicator(color: AppColors.lime),
            ),
        ],
      ),
    );
  }

  void _handleBackPress() {
    if (_isVerifying) {
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          backgroundColor: const Color(0xFF1E1E2C),
          title: Text('Verification in progress', style: GoogleFonts.outfit(color: Colors.white)),
          content: Text(
            'We are currently verifying your payment status. Exiting now will not cancel your transaction.',
            style: GoogleFonts.plusJakartaSans(color: Colors.white70),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Wait'),
            ),
            TextButton(
              onPressed: () {
                Navigator.pop(ctx);
                Navigator.pop(context, false);
              },
              child: const Text('Exit Anyway', style: TextStyle(color: Colors.redAccent)),
            ),
          ],
        ),
      );
    } else {
      Navigator.of(context).pop(false);
    }
  }
}
