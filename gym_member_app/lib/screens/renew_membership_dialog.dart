import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/network/api_service.dart';
import '../core/theme/app_colors.dart';
import '../providers/member_data_provider.dart';

class RenewMembershipDialog extends StatefulWidget {
  const RenewMembershipDialog({super.key});

  static Future<void> show(BuildContext context) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => const RenewMembershipDialog(),
    );
  }

  @override
  State<RenewMembershipDialog> createState() => _RenewMembershipDialogState();
}

class _RenewMembershipDialogState extends State<RenewMembershipDialog> with WidgetsBindingObserver {
  int _step = 1; // 1: Select Plan, 2: Pay, 3: Success (auto-verified), 4: UTR submitted for gym verification
  bool _loading = true;
  String? _error;

  List<dynamic> _plans = [];
  Map<String, dynamic> _gymInfo = {};

  int _selectedPlanId = 0;
  String _selectedPlanName = '';
  int _selectedMonths = 1;
  double _calculatedPrice = 0.0;

  Map<String, dynamic>? _orderData;
  bool _creatingOrder = false;
  bool _checkingStatus = false;
  bool _statusRequestInFlight = false;

  // How this order is paid (decided by the server):
  //  cashfree_upi   -> Cashfree UPI link / QR, credited automatically (we poll check_payment_status)
  //  gym_upi_direct -> paid straight to the gym's UPI ID; the member submits the UPI reference (UTR)
  //  checkout_only  -> neither a UPI link nor a QR: Cashfree's hosted checkout in the browser
  String _paymentMode = 'cashfree_upi';

  Timer? _countdownTimer;
  int _pollGeneration = 0;
  int _secondsRemaining = 600; // 10 minutes

  final _utrController = TextEditingController();
  bool _submittingUtr = false;
  String? _utrError;
  String _submittedInvoiceNumber = '';

  String _scheduledStartDate = '';
  String _scheduledExpiryDate = '';
  String _successNotice = '';

  static const _upiSchemes = {'upi', 'tez', 'phonepe', 'paytmmp', 'paytm', 'bhim', 'credpay', 'whatsapp', 'amazonpay', 'mobikwik', 'freecharge'};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadPlans();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _countdownTimer?.cancel();
    _pollGeneration++;
    _utrController.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Back from the UPI app / browser: ask the server straight away instead of waiting for the next poll
    if (state == AppLifecycleState.resumed && mounted && _step == 2 && _orderData != null && _paymentMode != 'gym_upi_direct') {
      _checkPaymentStatus(silent: true);
    }
  }

  Future<void> _loadPlans() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    final provider = context.read<MemberDataProvider>();
    final res = await provider.fetchPlans();
    if (!mounted) return;

    if (res != null && res['plans'] != null) {
      _gymInfo = Map<String, dynamic>.from(res['gym_info'] ?? {});
      _plans = List.from(res['plans'] ?? []);
      if (_plans.isNotEmpty) {
        _selectedPlanId = _plans[0]['id'] ?? 0;
        _selectedPlanName = _plans[0]['name'] ?? 'General Fitness';
        _updateCalculatedPrice();
      }
      setState(() {
        _loading = false;
      });
    } else {
      setState(() {
        _loading = false;
        _error = 'Failed to load membership plans. Please try again.';
      });
    }
  }

  void _updateCalculatedPrice() {
    if (_plans.isEmpty) return;
    final plan = _plans.firstWhere((p) => p['id'] == _selectedPlanId, orElse: () => _plans[0]);
    final packages = List<dynamic>.from(plan['packages'] ?? []);
    final pkg = packages.firstWhere((p) => p['months'] == _selectedMonths, orElse: () => packages.isNotEmpty ? packages[0] : null);
    if (pkg != null) {
      _calculatedPrice = (pkg['total_price'] as num).toDouble();
    }
  }

  static double? _asDouble(dynamic v) => v is num ? v.toDouble() : double.tryParse('${v ?? ''}');

  String _str(String key) => (_orderData?[key] ?? '').toString().trim();

  bool _isUpiDeepLink(String url) {
    final uri = Uri.tryParse(url);
    return uri != null && _upiSchemes.contains(uri.scheme.toLowerCase());
  }

  // The order's own UPI link ('default' opens Android's chooser of every installed UPI app)
  String _orderUpiLink() {
    final links = _orderData?['upi_links'];
    if (links is Map) {
      final def = (links['default'] ?? '').toString().trim();
      if (def.isNotEmpty && _isUpiDeepLink(def)) return def;
    }
    final upiUrl = _str('upi_url');
    return _isUpiDeepLink(upiUrl) ? upiUrl : '';
  }

  String _resolvePaymentMode(Map<String, dynamic> order) {
    _orderData = order;
    final link = _orderUpiLink();
    final qr = _str('qr_code_url');
    if (link.isEmpty && qr.isEmpty) return 'checkout_only';

    final mode = (order['payment_mode'] ?? '').toString();
    if (mode == 'gym_upi_direct' || order['requires_utr'] == true) return 'gym_upi_direct';
    // Servers that predate payment_mode only returned the gym's own UPI ID for direct payments
    if (mode.isEmpty && _str('upi_id').isNotEmpty) return 'gym_upi_direct';
    return 'cashfree_upi';
  }

  Future<void> _createPaymentOrder() async {
    setState(() {
      _creatingOrder = true;
      _error = null;
    });

    final provider = context.read<MemberDataProvider>();
    try {
      final res = await provider.createPaymentOrder(
        planId: _selectedPlanId,
        months: _selectedMonths,
      );
      if (!mounted) return;

      if (res['order_id'] == null) {
        setState(() {
          _creatingOrder = false;
          _error = 'Could not create payment order. Please retry.';
        });
        return;
      }

      final mode = _resolvePaymentMode(res);
      setState(() {
        _orderData = res;
        _paymentMode = mode;
        _creatingOrder = false;
        _step = 2;
        _utrController.clear();
        _utrError = null;
        _secondsRemaining = int.tryParse('${res['expires_in_seconds'] ?? ''}') ?? 600;
      });

      if (mode == 'gym_upi_direct') {
        // Cashfree never sees money paid straight to the gym: no polling, no expiry
        _countdownTimer?.cancel();
        _pollGeneration++;
      } else {
        _startTimerAndPolling();
        if (mode == 'checkout_only') {
          await _openCheckoutInBrowser();
        }
      }
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _creatingOrder = false;
        _error = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _creatingOrder = false;
        _error = 'Could not create payment order. Please retry.';
      });
    }
  }

  void _startTimerAndPolling() {
    _countdownTimer?.cancel();

    // 1. Countdown timer
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      if (_secondsRemaining <= 1) {
        timer.cancel();
        _pollGeneration++;
        setState(() => _secondsRemaining = 0);
        _finalCheckOnExpiry();
      } else {
        setState(() {
          _secondsRemaining--;
        });
      }
    });

    // 2. Status polling: one request at a time, the next one only after the previous answered
    final generation = ++_pollGeneration;
    _pollLoop(generation);
  }

  Future<void> _pollLoop(int generation) async {
    while (mounted && generation == _pollGeneration && _step == 2) {
      await Future.delayed(const Duration(seconds: 5));
      if (!mounted || generation != _pollGeneration || _step != 2) return;
      await _checkPaymentStatus(silent: true);
    }
  }

  // A payment made in the last seconds must not be reported as expired
  Future<void> _finalCheckOnExpiry() async {
    await _checkPaymentStatus(silent: true);
    if (!mounted || _step != 2) return;
    setState(() {
      _error = 'This payment request has expired. Tap "Try Again" to create a new one.';
    });
  }

  Future<void> _checkPaymentStatus({bool silent = false}) async {
    final orderId = _orderData?['order_id']?.toString();
    if (orderId == null || orderId.isEmpty) return;
    if (_statusRequestInFlight) return;
    _statusRequestInFlight = true;

    if (!silent) {
      setState(() {
        _checkingStatus = true;
        _error = null;
      });
    }

    final provider = context.read<MemberDataProvider>();
    final res = await provider.checkPaymentStatus(orderId);
    _statusRequestInFlight = false;
    if (!mounted) return;

    if (!silent) {
      setState(() {
        _checkingStatus = false;
      });
    }

    if (res != null) {
      final payStatus = res['payment_status'];
      if (payStatus == 'PAID') {
        _countdownTimer?.cancel();
        _pollGeneration++;
        setState(() {
          _error = null;
          _scheduledStartDate = (res['scheduled_start_date'] ?? '').toString();
          _scheduledExpiryDate = (res['scheduled_expiry_date'] ?? '').toString();
          _successNotice = (res['message'] ?? 'Payment verified successfully!').toString();
          _step = 3;
        });
      } else if (!silent) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text((res['message'] ?? 'Payment status: $payStatus').toString())),
        );
      }
    } else if (!silent) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not check the payment status. Please try again.')),
      );
    }
  }

  // Opens the payment link in the phone's UPI app. No canLaunchUrl() gate: with a plain upi:// link
  // Android shows its chooser of every installed UPI app.
  Future<bool> _launchUpiLink(String link) async {
    if (link.isEmpty || !_isUpiDeepLink(link)) return false;
    try {
      return await launchUrl(Uri.parse(link), mode: LaunchMode.externalApplication);
    } catch (_) {
      return false;
    }
  }

  Future<void> _openCheckoutInBrowser() async {
    final checkoutUrl = _str('cashfree_checkout_url');
    var opened = false;
    if (checkoutUrl.isNotEmpty) {
      try {
        opened = await launchUrl(Uri.parse(checkoutUrl), mode: LaunchMode.externalApplication);
      } catch (_) {
        opened = false;
      }
    }
    if (!mounted) return;
    if (!opened) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open the payment page. Please try again.')),
      );
    }
  }

  Future<void> _payWithUpiApp() async {
    final link = _paymentMode == 'gym_upi_direct' ? (_isUpiDeepLink(_str('upi_url')) ? _str('upi_url') : '') : _orderUpiLink();
    final launched = await _launchUpiLink(link);
    if (launched || !mounted) return;

    if (_paymentMode == 'gym_upi_direct') {
      final upiId = _str('upi_id');
      if (upiId.isNotEmpty) {
        await Clipboard.setData(ClipboardData(text: upiId));
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('No UPI app could be opened. Gym UPI ID copied: $upiId')),
        );
      }
      return;
    }
    // Cashfree order: its hosted checkout works in any browser
    await _openCheckoutInBrowser();
  }

  Future<void> _submitUtr() async {
    final utr = _utrController.text.replaceAll(RegExp(r'\s+'), '').toUpperCase();
    if (!RegExp(r'^[A-Z0-9\-]{6,40}$').hasMatch(utr)) {
      setState(() => _utrError = 'Enter the UPI reference / UTR number shown in your UPI app (usually 12 digits).');
      return;
    }

    setState(() {
      _submittingUtr = true;
      _utrError = null;
    });

    final provider = context.read<MemberDataProvider>();
    try {
      final res = await provider.submitRenewalPayment(
        planId: _selectedPlanId,
        planName: _str('plan_name').isNotEmpty ? _str('plan_name') : _selectedPlanName,
        months: int.tryParse(_str('months')) ?? _selectedMonths,
        transactionRef: utr,
      );
      if (!mounted) return;
      setState(() {
        _submittingUtr = false;
        _submittedInvoiceNumber = (res['invoice_number'] ?? '').toString();
        _step = 4;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _submittingUtr = false;
        _utrError = e.message;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _submittingUtr = false;
        _utrError = 'Could not submit your payment reference. Please try again.';
      });
    }
  }

  String _formatDuration(int seconds) {
    final minutes = seconds ~/ 60;
    final secs = seconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${secs.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    return Container(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom + MediaQuery.of(context).padding.bottom + 24,
        top: 20,
        left: 20,
        right: 20,
      ),
      decoration: const BoxDecoration(
        color: AppColors.darkCard,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Handle bar
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: isDark ? Colors.white24 : Colors.black12,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Header
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Advance Membership Renewal',
                        style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                        overflow: TextOverflow.ellipsis,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Queued Plan • Cashfree Payment',
                        style: theme.textTheme.bodySmall?.copyWith(color: theme.primaryColor, fontWeight: FontWeight.w600),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            const Divider(height: 24),

            if (_error != null) ...[
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.danger.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.danger.withValues(alpha: 0.3)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.error_outline_rounded, color: AppColors.danger, size: 20),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        _error!,
                        style: const TextStyle(color: AppColors.danger, fontSize: 13),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
            ],

            if (_loading)
              const Padding(
                padding: EdgeInsets.all(40.0),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_step == 1)
              _buildStep1SelectPlan(theme, isDark)
            else if (_step == 2)
              (_paymentMode == 'gym_upi_direct' ? _buildStep2GymUpiDirect(theme, isDark) : _buildStep2CashfreeQr(theme, isDark))
            else if (_step == 3)
              _buildStep3Success(theme, isDark)
            else if (_step == 4)
              _buildStep4UtrSubmitted(theme, isDark),
          ],
        ),
      ),
    );
  }

  // STEP 1: Select Plan & Duration
  Widget _buildStep1SelectPlan(ThemeData theme, bool isDark) {
    final currency = _gymInfo['currency'] ?? '₹';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          '1. Select Membership Service',
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
        ),
        const SizedBox(height: 12),

        ..._plans.map((plan) {
          final planId = plan['id'] as int;
          final isSelected = planId == _selectedPlanId;
          final monthlyCharge = (plan['monthly_charge'] as num).toDouble();

          return GestureDetector(
            onTap: () {
              setState(() {
                _selectedPlanId = planId;
                _selectedPlanName = plan['name'] ?? '';
                _updateCalculatedPrice();
              });
            },
            child: Container(
              margin: const EdgeInsets.only(bottom: 10),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: isSelected
                    ? theme.primaryColor.withValues(alpha: 0.1)
                    : (isDark ? AppColors.darkCard : AppColors.lightCard),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: isSelected ? theme.primaryColor : theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2),
                  width: isSelected ? 2 : 1,
                ),
              ),
              child: Row(
                children: [
                  Icon(
                    isSelected ? Icons.radio_button_checked_rounded : Icons.radio_button_unchecked_rounded,
                    color: isSelected ? theme.primaryColor : Colors.grey,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          plan['name'] ?? '',
                          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                        ),
                        Text(
                          '$currency${monthlyCharge.toStringAsFixed(0)} / month base rate',
                          style: theme.textTheme.bodySmall,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          );
        }),

        const SizedBox(height: 16),
        const Text(
          '2. Select Duration Package',
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
        ),
        const SizedBox(height: 10),

        Row(
          children: [1, 3, 6, 12].map((m) {
            final isSelected = m == _selectedMonths;
            String discountText = '';
            if (m == 3) discountText = '5% OFF';
            if (m == 6) discountText = '10% OFF';
            if (m == 12) discountText = '20% OFF';

            return Expanded(
              child: GestureDetector(
                onTap: () {
                  setState(() {
                    _selectedMonths = m;
                    _updateCalculatedPrice();
                  });
                },
                child: Container(
                  margin: const EdgeInsets.symmetric(horizontal: 4),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  decoration: BoxDecoration(
                    color: isSelected ? theme.primaryColor : (isDark ? AppColors.darkCard : AppColors.lightCard),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: isSelected ? theme.primaryColor : theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2),
                    ),
                  ),
                  child: Column(
                    children: [
                      Text(
                        '${m}Mo',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          color: isSelected ? Colors.white : theme.textTheme.bodyLarge?.color,
                          fontSize: 16,
                        ),
                      ),
                      if (discountText.isNotEmpty)
                        Container(
                          margin: const EdgeInsets.only(top: 4),
                          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                          decoration: BoxDecoration(
                            color: isSelected ? Colors.white24 : AppColors.success.withValues(alpha: 0.2),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            discountText,
                            style: TextStyle(
                              fontSize: 9,
                              fontWeight: FontWeight.w800,
                              color: isSelected ? Colors.white : AppColors.success,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
            );
          }).toList(),
        ),

        const SizedBox(height: 20),

        // Queue behavior note
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: theme.primaryColor.withValues(alpha: 0.05),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: theme.primaryColor.withValues(alpha: 0.2)),
          ),
          child: const Row(
            children: [
              Icon(Icons.event_available_rounded, color: AppColors.info, size: 20),
              SizedBox(width: 10),
              Expanded(
                child: Text(
                  'Your current active membership will NOT be shortened. The new plan will be queued as "Upcoming" and activates automatically after your current plan expires.',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w500),
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 20),

        // Price Summary Box
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: theme.primaryColor.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: theme.primaryColor.withValues(alpha: 0.2)),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Total Price (Backend Verified)', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text(
                      '$_selectedPlanName ($_selectedMonths Months)',
                      style: theme.textTheme.bodySmall,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Text(
                '$currency${_calculatedPrice.toStringAsFixed(2)}',
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                  color: theme.primaryColor,
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 20),

        SizedBox(
          width: double.infinity,
          height: 50,
          child: ElevatedButton(
            onPressed: _creatingOrder ? null : _createPaymentOrder,
            style: ElevatedButton.styleFrom(
              backgroundColor: theme.primaryColor,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: _creatingOrder
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.payment_rounded),
                      SizedBox(width: 8),
                      Text('Proceed to Payment', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                    ],
                  ),
          ),
        ),
      ],
    );
  }

  // STEP 2 (Cashfree): pay with any UPI app on this phone, or scan the QR from another phone
  Widget _buildStep2CashfreeQr(ThemeData theme, bool isDark) {
    final qrUrl = _str('qr_code_url');
    final payableAmount = _asDouble(_orderData?['payable_amount']);
    final currency = _orderData?['currency'] ?? '₹';
    final gymName = _orderData?['gym_name'] ?? _gymInfo['gym_name'] ?? 'Gym Owner';
    final orderId = _orderData?['order_id'] ?? '';
    final hasUpiLink = _orderUpiLink().isNotEmpty;
    final checkoutOnly = _paymentMode == 'checkout_only';

    return Column(
      children: [
        // Countdown timer bar
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            color: _secondsRemaining > 60 ? AppColors.warning.withValues(alpha: 0.15) : AppColors.danger.withValues(alpha: 0.15),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.timer_outlined, size: 18, color: _secondsRemaining > 60 ? AppColors.warning : AppColors.danger),
              const SizedBox(width: 6),
              Text(
                'Payment request expires in: ${_formatDuration(_secondsRemaining)}',
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 13,
                  color: _secondsRemaining > 60 ? AppColors.warning : AppColors.danger,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),

        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: isDark ? AppColors.darkCard : AppColors.lightCard,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: theme.primaryColor.withValues(alpha: 0.3)),
          ),
          child: Column(
            children: [
              Text(
                gymName.toString(),
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
              ),
              const SizedBox(height: 4),
              Text(
                'Order ID: $orderId',
                style: theme.textTheme.bodySmall?.copyWith(fontSize: 11, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 12),
              Text(
                'Payable Amount: $currency${(payableAmount ?? _calculatedPrice).toStringAsFixed(2)}',
                style: TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: theme.primaryColor),
              ),
              const SizedBox(height: 4),
              Text(
                'Amount is locked • Credited automatically once paid',
                textAlign: TextAlign.center,
                style: theme.textTheme.bodySmall?.copyWith(fontSize: 11),
              ),

              if (!checkoutOnly && hasUpiLink) ...[
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton.icon(
                    onPressed: _payWithUpiApp,
                    icon: const Icon(Icons.account_balance_wallet_rounded, size: 20),
                    label: const Text('Pay with any UPI app', style: TextStyle(fontWeight: FontWeight.w800)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: theme.primaryColor,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
              ],

              if (!checkoutOnly && qrUrl.isNotEmpty) ...[
                const SizedBox(height: 16),
                Text(
                  'Paying from another phone? Scan this QR',
                  style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                // QR Code Box
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 10),
                    ],
                  ),
                  child: Image.network(
                    qrUrl,
                    width: 190,
                    height: 190,
                    fit: BoxFit.contain,
                    errorBuilder: (ctx, err, stack) => const Icon(Icons.qr_code_2_rounded, size: 160, color: Colors.grey),
                  ),
                ),
              ],

              if (checkoutOnly) ...[
                const SizedBox(height: 14),
                Text(
                  'Complete the payment on the secure payment page that opened in your browser, then come back here.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodySmall,
                ),
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton.icon(
                    onPressed: _openCheckoutInBrowser,
                    icon: const Icon(Icons.open_in_new_rounded, size: 18),
                    label: const Text('Open Payment Page', style: TextStyle(fontWeight: FontWeight.w800)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: theme.primaryColor,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),

        const SizedBox(height: 16),

        Row(
          children: [
            if (!checkoutOnly)
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _openCheckoutInBrowser,
                  icon: const Icon(Icons.credit_card_rounded, size: 18),
                  label: const Text('Card / Other'),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ),
            if (!checkoutOnly) const SizedBox(width: 12),
            Expanded(
              child: ElevatedButton.icon(
                onPressed: _checkingStatus ? null : () => _checkPaymentStatus(silent: false),
                icon: _checkingStatus
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Icon(Icons.published_with_changes_rounded, size: 18),
                label: const Text('Check Status'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.success,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
          ],
        ),

        if (_secondsRemaining == 0) ...[
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: TextButton.icon(
              onPressed: () {
                setState(() {
                  _error = null;
                  _step = 1;
                });
              },
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Try Again'),
            ),
          ),
        ],
      ],
    );
  }

  // STEP 2 (gym's own UPI): pay the gym directly, then submit the UPI reference for the gym to verify
  Widget _buildStep2GymUpiDirect(ThemeData theme, bool isDark) {
    final qrUrl = _str('qr_code_url');
    final payableAmount = _asDouble(_orderData?['payable_amount']);
    final currency = _orderData?['currency'] ?? '₹';
    final gymName = _orderData?['gym_name'] ?? _gymInfo['gym_name'] ?? 'Gym Owner';
    final upiId = _str('upi_id');
    final hasUpiLink = _isUpiDeepLink(_str('upi_url'));
    final amountText = '$currency${(payableAmount ?? _calculatedPrice).toStringAsFixed(2)}';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: isDark ? AppColors.darkCard : AppColors.lightCard,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: theme.primaryColor.withValues(alpha: 0.3)),
          ),
          child: Column(
            children: [
              const Text('Step 1 • Pay the gym', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
              const SizedBox(height: 8),
              Text(
                gymName.toString(),
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
              ),
              const SizedBox(height: 8),
              Text(
                'Pay exactly: $amountText',
                style: TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: theme.primaryColor),
              ),
              if (upiId.isNotEmpty) ...[
                const SizedBox(height: 6),
                InkWell(
                  onTap: () async {
                    await Clipboard.setData(ClipboardData(text: upiId));
                    if (!mounted) return;
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text('UPI ID copied: $upiId')),
                    );
                  },
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text('UPI ID: $upiId', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                      const SizedBox(width: 6),
                      const Icon(Icons.copy_rounded, size: 14),
                    ],
                  ),
                ),
              ],
              if (hasUpiLink) ...[
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton.icon(
                    onPressed: _payWithUpiApp,
                    icon: const Icon(Icons.account_balance_wallet_rounded, size: 20),
                    label: const Text('Pay with any UPI app', style: TextStyle(fontWeight: FontWeight.w800)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: theme.primaryColor,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                ),
              ],
              if (qrUrl.isNotEmpty) ...[
                const SizedBox(height: 14),
                Text(
                  'Or scan the gym\'s UPI QR',
                  style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 10),
                    ],
                  ),
                  child: Image.network(
                    qrUrl,
                    width: 190,
                    height: 190,
                    fit: BoxFit.contain,
                    errorBuilder: (ctx, err, stack) => const Icon(Icons.qr_code_2_rounded, size: 160, color: Colors.grey),
                  ),
                ),
              ],
            ],
          ),
        ),

        const SizedBox(height: 16),

        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: theme.primaryColor.withValues(alpha: 0.06),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: theme.primaryColor.withValues(alpha: 0.25)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text("Step 2 • I've paid — enter UPI reference (UTR)", style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
              const SizedBox(height: 4),
              Text(
                'This payment goes straight to the gym, so it is not confirmed automatically. '
                'Enter the UPI reference / UTR number from your UPI app\'s payment receipt. '
                'Your membership is renewed once the gym verifies it.',
                style: theme.textTheme.bodySmall,
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _utrController,
                textCapitalization: TextCapitalization.characters,
                keyboardType: TextInputType.visiblePassword,
                enabled: !_submittingUtr,
                decoration: InputDecoration(
                  labelText: 'UPI reference / UTR number',
                  hintText: 'e.g. 412345678901',
                  errorText: _utrError,
                  errorMaxLines: 3,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                height: 48,
                child: ElevatedButton.icon(
                  onPressed: _submittingUtr ? null : _submitUtr,
                  icon: _submittingUtr
                      ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Icon(Icons.send_rounded, size: 18),
                  label: const Text('Submit for verification', style: TextStyle(fontWeight: FontWeight.w800)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.success,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  // STEP 4: UTR submitted, waiting for the gym to verify
  Widget _buildStep4UtrSubmitted(ThemeData theme, bool isDark) {
    return Column(
      children: [
        const SizedBox(height: 16),
        const Icon(Icons.hourglass_top_rounded, color: AppColors.warning, size: 64),
        const SizedBox(height: 14),
        const Text(
          'Submitted — the gym will verify your payment',
          textAlign: TextAlign.center,
          style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18),
        ),
        const SizedBox(height: 10),
        Text(
          'Your renewal for $_selectedPlanName ($_selectedMonths Months) is pending verification'
          '${_submittedInvoiceNumber.isNotEmpty ? ' (Invoice $_submittedInvoiceNumber)' : ''}. '
          'Your membership will be extended as soon as the gym confirms the payment.',
          textAlign: TextAlign.center,
          style: theme.textTheme.bodyMedium,
        ),
        const SizedBox(height: 24),
        SizedBox(
          width: double.infinity,
          height: 48,
          child: ElevatedButton(
            onPressed: () => Navigator.pop(context),
            style: ElevatedButton.styleFrom(
              backgroundColor: theme.primaryColor,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text('Done', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
        ),
      ],
    );
  }

  // STEP 3: Success View
  Widget _buildStep3Success(ThemeData theme, bool isDark) {
    return Column(
      children: [
        const SizedBox(height: 16),
        const Icon(Icons.check_circle_rounded, color: AppColors.success, size: 64),
        const SizedBox(height: 14),
        const Text(
          'Plan Scheduled Successfully!',
          style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18),
        ),
        const SizedBox(height: 8),

        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: AppColors.success.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.success.withValues(alpha: 0.3)),
          ),
          child: Column(
            children: [
              Text(
                'Plan: $_selectedPlanName ($_selectedMonths Months)',
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
              ),
              const SizedBox(height: 6),
              Text(
                'Scheduled Start: $_scheduledStartDate\nScheduled Expiry: $_scheduledExpiryDate',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),

        const SizedBox(height: 14),
        Text(
          _successNotice.isNotEmpty
              ? _successNotice
              : 'Your new plan has been successfully scheduled and will activate automatically after your current plan expires.',
          textAlign: TextAlign.center,
          style: theme.textTheme.bodyMedium,
        ),
        const SizedBox(height: 24),

        SizedBox(
          width: double.infinity,
          height: 48,
          child: ElevatedButton(
            onPressed: () => Navigator.pop(context),
            style: ElevatedButton.styleFrom(
              backgroundColor: theme.primaryColor,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text('Done', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
        ),
      ],
    );
  }
}
