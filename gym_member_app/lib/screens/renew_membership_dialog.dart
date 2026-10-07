import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
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

class _RenewMembershipDialogState extends State<RenewMembershipDialog> {
  int _step = 1; // 1: Select Plan, 2: Payment QR & Checkout, 3: Success
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

  Timer? _countdownTimer;
  Timer? _pollTimer;
  int _secondsRemaining = 600; // 10 minutes

  String _scheduledStartDate = '';
  String _scheduledExpiryDate = '';
  String _successNotice = '';

  @override
  void initState() {
    super.initState();
    _loadPlans();
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    _pollTimer?.cancel();
    super.dispose();
  }

  Future<void> _loadPlans() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    final provider = context.read<MemberDataProvider>();
    final res = await provider.fetchPlans();

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

  Future<void> _createPaymentOrder() async {
    setState(() {
      _creatingOrder = true;
      _error = null;
    });

    final provider = context.read<MemberDataProvider>();
    final res = await provider.createPaymentOrder(
      planId: _selectedPlanId,
      months: _selectedMonths,
    );

    if (res != null && res['order_id'] != null) {
      setState(() {
        _orderData = res;
        _creatingOrder = false;
        _step = 2;
        _secondsRemaining = res['expires_in_seconds'] ?? 600;
      });
      _startTimerAndPolling();
    } else {
      setState(() {
        _creatingOrder = false;
        _error = 'Could not create payment order. Please retry.';
      });
    }
  }

  void _startTimerAndPolling() {
    _countdownTimer?.cancel();
    _pollTimer?.cancel();

    // 1. Countdown timer
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_secondsRemaining <= 1) {
        timer.cancel();
        _pollTimer?.cancel();
        setState(() {
          _secondsRemaining = 0;
          _error = 'Payment QR Code expired. Please tap "Try Again" to generate a new order.';
        });
      } else {
        setState(() {
          _secondsRemaining--;
        });
      }
    });

    // 2. Auto-polling payment status every 5 seconds
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
      _checkPaymentStatus(silent: true);
    });
  }

  Future<void> _checkPaymentStatus({bool silent = false}) async {
    final orderId = _orderData?['order_id'];
    if (orderId == null) return;

    if (!silent) {
      setState(() {
        _checkingStatus = true;
        _error = null;
      });
    }

    final provider = context.read<MemberDataProvider>();
    final res = await provider.checkPaymentStatus(orderId);

    if (!silent) {
      setState(() {
        _checkingStatus = false;
      });
    }

    if (res != null) {
      final payStatus = res['payment_status'];
      if (payStatus == 'PAID') {
        _countdownTimer?.cancel();
        _pollTimer?.cancel();
        setState(() {
          _scheduledStartDate = res['scheduled_start_date'] ?? '';
          _scheduledExpiryDate = res['scheduled_expiry_date'] ?? '';
          _successNotice = res['message'] ?? 'Payment verified successfully!';
          _step = 3;
        });
      } else if (!silent && payStatus != 'PAID') {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(res['message'] ?? 'Payment status: $payStatus')),
        );
      }
    }
  }

  Future<void> _launchPaymentUrl(String url) async {
    final uri = Uri.parse(url);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } else {
      final upiId = _orderData?['upi_id'] ?? _gymInfo['upi_id'] ?? '';
      if (upiId.isNotEmpty) {
        await Clipboard.setData(ClipboardData(text: upiId));
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Gym Owner UPI ID copied: $upiId')),
          );
        }
      }
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
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Advance Membership Renewal',
                      style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Queued Plan • Cashfree Payment',
                      style: theme.textTheme.bodySmall?.copyWith(color: theme.primaryColor, fontWeight: FontWeight.w600),
                    ),
                  ],
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
              _buildStep2CashfreeQr(theme, isDark)
            else if (_step == 3)
              _buildStep3Success(theme, isDark),
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
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Total Price (Backend Verified)', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 2),
                  Text(
                    '$_selectedPlanName ($_selectedMonths Months)',
                    style: theme.textTheme.bodySmall,
                  ),
                ],
              ),
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
                      Text('Proceed to Payment QR', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                    ],
                  ),
          ),
        ),
      ],
    );
  }

  // STEP 2: Cashfree Dynamic Payment QR & Timer
  Widget _buildStep2CashfreeQr(ThemeData theme, bool isDark) {
    final qrUrl = _orderData?['qr_code_url'] ?? '';
    final payableAmount = _orderData?['payable_amount'] ?? _calculatedPrice;
    final currency = _orderData?['currency'] ?? '₹';
    final gymName = _orderData?['gym_name'] ?? _gymInfo['gym_name'] ?? 'Gym Owner';
    final upiId = _orderData?['upi_id'] ?? _gymInfo['upi_id'] ?? '';
    final orderId = _orderData?['order_id'] ?? '';
    final upiUrl = _orderData?['upi_url'] ?? '';
    final checkoutUrl = _orderData?['cashfree_checkout_url'] ?? '';

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
                'QR Expires in: ${_formatDuration(_secondsRemaining)}',
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
                gymName,
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
              ),
              const SizedBox(height: 4),
              Text(
                'Order ID: $orderId',
                style: theme.textTheme.bodySmall?.copyWith(fontSize: 11, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 12),

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
                  width: 210,
                  height: 210,
                  fit: BoxFit.contain,
                  errorBuilder: (ctx, err, stack) => const Icon(Icons.qr_code_2_rounded, size: 180, color: Colors.grey),
                ),
              ),

              const SizedBox(height: 12),
              Text(
                'Payable Amount: $currency$payableAmount',
                style: TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: theme.primaryColor),
              ),
              const SizedBox(height: 4),
              Text('UPI ID: $upiId', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
            ],
          ),
        ),

        const SizedBox(height: 16),

        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => _launchPaymentUrl(upiUrl.isNotEmpty ? upiUrl : checkoutUrl),
                icon: const Icon(Icons.open_in_new_rounded, size: 18),
                label: const Text('Pay via App'),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
            const SizedBox(width: 12),
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
