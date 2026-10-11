import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:flutter_cashfree_pg_sdk/api/cferrorresponse/cferrorresponse.dart';
import '../../core/services/upi_payment_service.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/admin_provider.dart';
import '../../widgets/upi_app_brand_logo.dart';

/// SaaS subscription checkout for the gym admin: pay with any installed UPI app (Cashfree UPI intent,
/// amount locked to the order), or card / net banking. Success is decided only by the server's
/// verify_order answer; this screen pops with `true` once the subscription is active.
class InAppPaymentCheckoutScreen extends StatefulWidget {
  final String orderId;
  final String checkoutUrl;
  final String planName;
  final double? amount;
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
    this.amount,
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
  static const int _maxPollAttempts = 10;

  List<InstalledUpiApp> _installedUpiApps = [];
  bool _isLoadingApps = true;
  bool _isLaunchingApp = false;
  String _launchingAppId = '';

  // Last payment method, so "Retry" reuses the same order with the same choice
  InstalledUpiApp? _lastApp;
  bool _lastWasCards = false;

  // Set once Cashfree's SDK reports installer_package_not_approved (app not installed from the
  // Play Store); later taps go straight to the deep link / browser.
  bool _sdkUnavailable = false;

  // Payment Verification State
  bool _isVerifying = false;
  bool _isPaymentSuccess = false;
  bool _notCompleted = false;
  String? _statusDetail;
  int _pollAttempts = 0;
  int _pollGeneration = 0;
  bool _awaitingReturn = false; // the admin was sent to a UPI app / the browser
  bool _closed = false;

  // Visible WebView checkout for Cards / Net Banking
  bool _showWebCheckout = false;
  WebViewController? _webController;
  bool _isWebLoading = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _detectInstalledUpiApps();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _pollGeneration++;
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _awaitingReturn && !_isVerifying && !_isPaymentSuccess && mounted) {
      _awaitingReturn = false;
      _startVerification();
    }
  }

  Future<void> _detectInstalledUpiApps() async {
    final apps = await UpiPaymentService.getInstalledUpiApps();
    if (!mounted) return;
    setState(() {
      _installedUpiApps = apps;
      _isLoadingApps = false;
    });
  }

  // ---------------------------------------------------------------- UPI

  Future<void> _handleUpiAppSelection(InstalledUpiApp app) async {
    if (_isLaunchingApp || _isVerifying || _isPaymentSuccess) return;

    setState(() {
      _isLaunchingApp = true;
      _launchingAppId = app.id;
      _notCompleted = false;
      _statusDetail = null;
      _lastApp = app;
      _lastWasCards = false;
    });

    final sessionId = widget.paymentSessionId ?? '';
    var started = false;
    if (!_sdkUnavailable && sessionId.isNotEmpty) {
      started = UpiPaymentService.startSdkUpiPayment(
        app: app,
        orderId: widget.orderId,
        paymentSessionId: sessionId,
        cashfreeMode: widget.cashfreeMode,
        onVerify: _onSdkVerify,
        onError: _onSdkError,
      );
    }

    if (started) {
      _awaitingReturn = true;
      // The SDK answers through the callbacks; don't leave the spinner up if no UPI app opens
      Future.delayed(const Duration(seconds: 8), () {
        if (mounted && _isLaunchingApp && _launchingAppId == app.id) {
          setState(() => _isLaunchingApp = false);
        }
      });
      return;
    }

    await _launchFallback(app);
  }

  bool _isForThisOrder(String orderId) =>
      orderId.isEmpty || orderId == 'order_id_not_found' || orderId == widget.orderId;

  // The UPI app returned. Only the server can say whether the money arrived.
  void _onSdkVerify(String orderId) {
    if (!mounted || orderId != widget.orderId) return;
    _awaitingReturn = false;
    if (_isLaunchingApp) setState(() => _isLaunchingApp = false);
    _startVerification();
  }

  Future<void> _onSdkError(CFErrorResponse error, String orderId) async {
    if (!mounted || !_isForThisOrder(orderId)) return;
    debugPrint('Cashfree SDK error: ${error.getCode()} ${error.getMessage()}');
    setState(() => _isLaunchingApp = false);

    if (UpiPaymentService.isPaymentAttemptFailure(error)) {
      // Back from the UPI app without a successful payment. It may still have gone through, so ask once.
      _awaitingReturn = false;
      _stopPolling();
      final admin = context.read<AdminProvider>();
      setState(() => _isVerifying = true);
      final paid = await admin.verifySaasOrder(widget.orderId);
      if (!mounted) return;
      if (paid) {
        await _onPaid();
      } else {
        _showNotCompleted(error.getMessage());
      }
      return;
    }

    // The SDK could not start the payment: open the order's UPI link / checkout instead. When the cause is
    // the install source (not from the Play Store) every later attempt would fail the same way.
    if (UpiPaymentService.isInstallerNotApproved(error)) {
      _sdkUnavailable = true;
    }
    await _launchFallback(_lastApp ?? UpiPaymentService.genericUpiApp);
  }

  Future<void> _launchFallback(InstalledUpiApp app) async {
    if (!mounted) return;
    if (!_isLaunchingApp) {
      setState(() {
        _isLaunchingApp = true;
        _launchingAppId = app.id;
      });
    }

    var opened = false;
    var openedBrowser = false;
    final link = UpiPaymentService.deepLinkFor(app, upiLinks: widget.upiLinks, upiIntentUrl: widget.upiIntentUrl);
    if (link != null) {
      opened = await UpiPaymentService.launchDeepLink(link);
    }
    if (!opened && widget.checkoutUrl.isNotEmpty) {
      opened = await UpiPaymentService.openCheckoutInBrowser(widget.checkoutUrl);
      openedBrowser = opened;
    }
    if (!mounted) return;

    setState(() => _isLaunchingApp = false);
    if (opened) {
      _awaitingReturn = true;
      if (openedBrowser) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Complete the payment on the secure page in your browser, then come back here.')),
        );
      }
    } else {
      _showNotCompleted('No UPI app or browser could be opened on this phone. Please try Card / Net Banking.');
    }
  }

  // ---------------------------------------------------------------- Verification

  Future<void> _startVerification() async {
    if (_isVerifying || _isPaymentSuccess || !mounted) return;

    final generation = ++_pollGeneration;
    final admin = context.read<AdminProvider>();
    setState(() {
      _isVerifying = true;
      _isLaunchingApp = false;
      _pollAttempts = 0;
      _notCompleted = false;
      _statusDetail = null;
    });

    // One request at a time: the next check is scheduled only after the previous one answered
    while (mounted && generation == _pollGeneration) {
      setState(() => _pollAttempts++);
      final paid = await admin.verifySaasOrder(widget.orderId);
      if (!mounted || generation != _pollGeneration) return;
      if (paid) {
        await _onPaid();
        return;
      }
      if (_pollAttempts >= _maxPollAttempts) break;
      await Future.delayed(const Duration(seconds: 3));
    }

    if (!mounted || generation != _pollGeneration) return;
    _showNotCompleted(null);
  }

  void _stopPolling() {
    _pollGeneration++;
  }

  void _showNotCompleted(String? detail) {
    if (!mounted) return;
    setState(() {
      _isVerifying = false;
      _notCompleted = true;
      _statusDetail = detail;
    });
  }

  Future<void> _onPaid() async {
    _stopPolling();
    if (!mounted) return;
    setState(() {
      _isVerifying = false;
      _notCompleted = false;
      _isPaymentSuccess = true;
    });
    await Future.delayed(const Duration(milliseconds: 1200));
    if (!mounted) return;
    _closeCheckout(true);
  }

  // Pops only this checkout route (and anything shown on top of it, e.g. the exit dialog)
  void _closeCheckout(bool result) {
    if (_closed || !mounted) return;
    _closed = true;
    _stopPolling();
    final navigator = Navigator.of(context);
    final route = ModalRoute.of(context);
    if (route != null && !route.isCurrent) {
      navigator.popUntil((r) => r == route);
    }
    navigator.pop(result);
  }

  void _retry() {
    if (_lastWasCards) {
      _handleCardsNetBanking();
    } else {
      _handleUpiAppSelection(_lastApp ?? UpiPaymentService.genericUpiApp);
    }
  }

  // ---------------------------------------------------------------- Cards / Net Banking

  WebViewController _ensureWebController() {
    return _webController ??= WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFF0F1015))
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) {
            if (mounted) setState(() => _isWebLoading = true);
          },
          onPageFinished: (url) {
            if (mounted) setState(() => _isWebLoading = false);
            if (url.contains('saas-renew-callback.php')) {
              _onWebCheckoutReturned();
            }
          },
          onNavigationRequest: (request) async {
            final url = request.url;
            final uri = Uri.tryParse(url);
            final scheme = uri?.scheme.toLowerCase() ?? '';

            // A UPI app button on the checkout page: hand the deep link to the phone
            if (UpiPaymentService.upiSchemes.contains(scheme)) {
              if (await UpiPaymentService.launchDeepLink(url)) {
                _awaitingReturn = true;
              }
              return NavigationDecision.prevent;
            }

            // Android intent:// links -> the equivalent UPI deep link
            if (url.startsWith('intent://')) {
              final schemeMatch = RegExp(r'scheme=([^;]+)').firstMatch(url);
              final bodyMatch = RegExp(r'intent://([^#]+)').firstMatch(url);
              final target = (schemeMatch != null && bodyMatch != null) ? '${schemeMatch.group(1)}://${bodyMatch.group(1)}' : '';
              if (await UpiPaymentService.launchDeepLink(target)) {
                _awaitingReturn = true;
              }
              return NavigationDecision.prevent;
            }

            // Never navigate to inline data (e.g. QR images) or unknown app schemes
            if (scheme != 'http' && scheme != 'https' && scheme != 'about') {
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
        ),
      );
  }

  Future<void> _handleCardsNetBanking() async {
    if (_isVerifying || _isPaymentSuccess) return;
    if (widget.checkoutUrl.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Card / Net Banking checkout is not available for this order. Please use UPI.')),
      );
      return;
    }
    final controller = _ensureWebController();
    setState(() {
      _showWebCheckout = true;
      _isWebLoading = true;
      _notCompleted = false;
      _statusDetail = null;
      _lastWasCards = true;
    });
    final separator = widget.checkoutUrl.contains('?') ? '&' : '?';
    await controller.loadRequest(Uri.parse('${widget.checkoutUrl}${separator}mode=cards'));
  }

  Future<void> _openWebCheckoutInBrowser() async {
    final opened = await UpiPaymentService.openCheckoutInBrowser(widget.checkoutUrl);
    if (!mounted) return;
    if (opened) {
      _awaitingReturn = true;
      setState(() => _showWebCheckout = false);
    }
  }

  void _onWebCheckoutReturned() {
    if (!mounted) return;
    setState(() => _showWebCheckout = false);
    _startVerification();
  }

  // Closing the card page: one quiet check in case the payment was completed there
  Future<void> _closeWebCheckout() async {
    setState(() => _showWebCheckout = false);
    if (_isVerifying || _isPaymentSuccess) return;
    final admin = context.read<AdminProvider>();
    final paid = await admin.verifySaasOrder(widget.orderId);
    if (!mounted) return;
    if (paid) await _onPaid();
  }

  // ---------------------------------------------------------------- UI

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        _handleBackPress();
      },
      child: _showWebCheckout ? _buildWebCheckoutView() : _buildMainView(),
    );
  }

  Widget _buildMainView() {
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
          onPressed: _handleBackPress,
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
              if (_isVerifying || _isPaymentSuccess || _notCompleted) ...[
                _buildVerificationStatusCard(),
                const SizedBox(height: 18),
              ],

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

              // 4. Installed UPI Apps
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
                    Flexible(
                      child: Text(
                        '256-Bit SSL Encrypted • PCI-DSS Level 1 Gateway',
                        style: GoogleFonts.plusJakartaSans(color: Colors.white38, fontSize: 11),
                      ),
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
              Expanded(
                child: Column(
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
              ),
              const SizedBox(width: 10),
              Text(
                // Always the server's total_payable; the app never computes the amount
                widget.amount != null ? '${widget.currency}${widget.amount!.toStringAsFixed(2)}' : '—',
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
    final isGeneric = app.id == 'generic';
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: isGeneric ? const Color(0xFF1A1C28) : const Color(0xFF161822),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: isGeneric
              ? AppColors.lime.withValues(alpha: 0.6)
              : (app.isPopular
                  ? AppColors.lime.withValues(alpha: 0.25)
                  : Colors.white.withValues(alpha: 0.06)),
          width: isGeneric ? 1.5 : 1.0,
        ),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: (_isLaunchingApp || _isVerifying || _isPaymentSuccess) ? null : () => _handleUpiAppSelection(app),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            child: Row(
              children: [
                UpiAppBrandLogo(
                  appId: app.id,
                  size: 44,
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              isGeneric ? 'Choose Any UPI App' : app.name,
                              style: GoogleFonts.plusJakartaSans(
                                color: isGeneric ? AppColors.lime : Colors.white,
                                fontSize: 14.5,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                          if (isGeneric) ...[
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: AppColors.lime.withValues(alpha: 0.2),
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: Text(
                                'ALL APPS',
                                style: GoogleFonts.plusJakartaSans(
                                  color: AppColors.lime,
                                  fontSize: 9,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 2),
                      Text(
                        isGeneric
                            ? 'Choose from every UPI app on this phone'
                            : 'Instant payment with UPI PIN',
                        style: GoogleFonts.plusJakartaSans(
                          color: Colors.white54,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                if (_isLaunchingApp && _launchingAppId == app.id)
                  const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(color: AppColors.lime, strokeWidth: 2),
                  )
                else
                  Icon(
                    Icons.arrow_forward_ios_rounded,
                    color: isGeneric ? AppColors.lime : Colors.white30,
                    size: 14,
                  ),
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
          onTap: (_isLaunchingApp || _isVerifying || _isPaymentSuccess) ? null : onTap,
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
                    'SaaS subscription activated. Returning...',
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
                    'Please wait while we confirm your transaction (Check ${_pollAttempts.clamp(1, _maxPollAttempts)}/$_maxPollAttempts)',
                    style: GoogleFonts.plusJakartaSans(color: Colors.white60, fontSize: 11.5),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    }

    // Payment not completed (cancelled, failed, or not confirmed yet)
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
                  'Payment not completed',
                  style: GoogleFonts.outfit(color: const Color(0xFFFCA5A5), fontSize: 14.5, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            (_statusDetail != null && _statusDetail!.trim().isNotEmpty)
                ? _statusDetail!
                : 'We could not confirm this payment yet. If money was deducted, tap "Check Status" — '
                    'it can take a minute to reach us. Otherwise tap "Retry" to pay this same order again.',
            style: GoogleFonts.plusJakartaSans(color: const Color(0xFFFCA5A5), fontSize: 12),
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              TextButton.icon(
                onPressed: _startVerification,
                icon: const Icon(Icons.published_with_changes_rounded, size: 14, color: Colors.white70),
                label: Text(
                  'Check Status',
                  style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.bold),
                ),
              ),
              const SizedBox(width: 6),
              TextButton.icon(
                onPressed: _isLaunchingApp ? null : _retry,
                icon: const Icon(Icons.refresh_rounded, size: 14, color: AppColors.lime),
                label: Text(
                  'Retry',
                  style: GoogleFonts.plusJakartaSans(color: AppColors.lime, fontSize: 12, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
        ],
      ),
    );
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
          onPressed: _closeWebCheckout,
        ),
        actions: [
          IconButton(
            tooltip: 'Open in browser',
            icon: const Icon(Icons.open_in_browser_rounded, color: Colors.white70),
            onPressed: _openWebCheckoutInBrowser,
          ),
        ],
      ),
      body: Stack(
        children: [
          if (_webController != null) WebViewWidget(controller: _webController!),
          if (_isWebLoading)
            const Center(
              child: CircularProgressIndicator(color: AppColors.lime),
            ),
        ],
      ),
    );
  }

  void _handleBackPress() {
    if (_showWebCheckout) {
      _closeWebCheckout();
      return;
    }
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
              onPressed: () => _closeCheckout(false),
              child: const Text('Exit Anyway', style: TextStyle(color: Colors.redAccent)),
            ),
          ],
        ),
      );
    } else {
      _closeCheckout(false);
    }
  }
}
