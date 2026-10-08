import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../models/admin_models.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';
import '../common/in_app_payment_checkout_screen.dart';

class AdminSaasSubscriptionScreen extends StatefulWidget {
  const AdminSaasSubscriptionScreen({super.key});

  @override
  State<AdminSaasSubscriptionScreen> createState() => _AdminSaasSubscriptionScreenState();
}

class _AdminSaasSubscriptionScreenState extends State<AdminSaasSubscriptionScreen> with WidgetsBindingObserver {
  String _selectedCycle = 'monthly'; // monthly, quarterly, yearly
  final _couponController = TextEditingController();

  String? _activeOrderId;
  Timer? _pollingTimer;
  bool _isCreatingOrder = false;
  bool _isAutoVerifying = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AdminProvider>().fetchSaasSubscription();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _pollingTimer?.cancel();
    _couponController.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _activeOrderId != null && !_isAutoVerifying) {
      // User returned from PhonePe / GPay / Paytm / Browser: verify with backend immediately!
      _triggerVerification(_activeOrderId!, isBackgroundPoll: true);
    }
  }

  Future<void> _handlePlanSelection(AdminSaasPlanItem plan) async {
    if (_isCreatingOrder) return; // Prevent multiple taps

    final currency = context.read<AuthProvider>().currentTenant?.currency ?? '₹';
    double price = plan.priceMonthly;
    String durationLabel = '1 Month';
    if (_selectedCycle == 'quarterly') {
      price = plan.priceQuarterly;
      durationLabel = '3 Months';
    } else if (_selectedCycle == 'yearly') {
      price = plan.priceYearly;
      durationLabel = '1 Year';
    }

    final isRenew = plan.isCurrent;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF1E1E2C),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setSheetState) {
            return Padding(
              padding: EdgeInsets.only(
                top: 20,
                left: 20,
                right: 20,
                bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(
                          color: Colors.white24,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      isRenew ? 'Renew SaaS Subscription' : 'Upgrade SaaS Subscription',
                      style: GoogleFonts.outfit(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Instant automatic activation upon payment via UPI (GPay/PhonePe/Paytm), Cards or Net Banking.',
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 12,
                        color: Colors.white60,
                      ),
                    ),
                    const SizedBox(height: 16),

                    // Plan summary card
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFF13131A),
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: Colors.white.withOpacity(0.08)),
                      ),
                      child: Column(
                        children: [
                          _summaryRow('Selected Plan', plan.name, isBold: true),
                          _summaryRow('Duration', durationLabel),
                          _summaryRow('Billing Cycle', '${_selectedCycle.toUpperCase()} Billing'),
                          _summaryRow('Member Limit', 'Up to ${plan.maxMembers} Members'),
                          _summaryRow('Staff Limit', 'Up to ${plan.maxStaff} Staff'),
                          const Divider(height: 16, color: Colors.white12),
                          _summaryRow(
                            'Payable Amount',
                            '$currency${price.toStringAsFixed(0)}',
                            isBold: true,
                            valueColor: AppColors.lime,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),

                    // Coupon field
                    TextFormField(
                      controller: _couponController,
                      textCapitalization: TextCapitalization.characters,
                      style: const TextStyle(color: Colors.white, fontSize: 13),
                      decoration: InputDecoration(
                        hintText: 'Promo / Coupon Code (Optional)',
                        hintStyle: const TextStyle(color: Colors.white38),
                        prefixIcon: const Icon(Icons.local_offer_outlined, size: 18, color: Color(0xFF00CEC9)),
                        filled: true,
                        fillColor: const Color(0xFF13131A),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      ),
                    ),
                    const SizedBox(height: 18),

                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: ElevatedButton.icon(
                        onPressed: _isCreatingOrder
                            ? null
                            : () async {
                                Navigator.pop(ctx);
                                _initiatePaymentFlow(
                                  plan: plan,
                                  cycle: _selectedCycle,
                                  coupon: _couponController.text.trim(),
                                  price: price,
                                  currency: currency,
                                );
                              },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.lime,
                          foregroundColor: Colors.black,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        icon: const Icon(Icons.lock_outline_rounded, size: 18),
                        label: Text(
                          'Subscribe / Pay Now ($currency${price.toStringAsFixed(0)})',
                          style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800, fontSize: 14),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _initiatePaymentFlow({
    required AdminSaasPlanItem plan,
    required String cycle,
    required String coupon,
    required double price,
    required String currency,
  }) async {
    if (_isCreatingOrder) return;

    setState(() {
      _isCreatingOrder = true;
    });

    // Show loading indicator: Creating payment order...
    if (mounted) {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (ctx) => PopScope(
          canPop: false,
          child: AlertDialog(
            backgroundColor: const Color(0xFF1E1E2C),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
            content: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Row(
                children: [
                  const CircularProgressIndicator(color: AppColors.lime, strokeWidth: 3),
                  const SizedBox(width: 18),
                  Expanded(
                    child: Text(
                      'Creating payment order...',
                      style: GoogleFonts.outfit(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      );
    }

    final admin = context.read<AdminProvider>();
    try {
      final res = await admin.createSaasCashfreeOrder(
        planId: plan.id,
        billingCycle: cycle,
        couponCode: coupon.isNotEmpty ? coupon : null,
      );

      // Close "Creating payment order..." dialog
      if (mounted) {
        Navigator.of(context, rootNavigator: true).pop();
      }

      if (res != null && res['order_id'] != null && res['checkout_url'] != null) {
        final orderId = res['order_id'].toString();
        final checkoutUrl = res['checkout_url'].toString();
        final finalPayable = (res['total_payable'] != null) ? (double.tryParse('${res['total_payable']}') ?? price) : price;
        _activeOrderId = orderId;

        // Open Meesho / Flipkart style In-App Payment Checkout Screen
        if (mounted) {
          final isSuccess = await Navigator.push<bool>(
            context,
            MaterialPageRoute(
              builder: (ctx) => InAppPaymentCheckoutScreen(
                orderId: orderId,
                checkoutUrl: checkoutUrl,
                planName: plan.name,
                amount: finalPayable,
                currency: currency,
              ),
            ),
          );

          if (isSuccess == true && mounted) {
            // Refresh SaaS data and show celebration success modal!
            await admin.fetchSaasSubscription();
            _showRenewalSuccessDialog();
          } else if (mounted) {
            // Check status silently in case payment succeeded right before exiting
            await _triggerVerification(orderId, isBackgroundPoll: true);
          }
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Could not create payment order. Please try again.'),
              backgroundColor: AppColors.danger,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        Navigator.of(context, rootNavigator: true).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Payment was not completed. Please try again: $e'),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isCreatingOrder = false;
        });
      }
    }
  }

  Future<void> _triggerVerification(String orderId, {bool isBackgroundPoll = false}) async {
    if (_isAutoVerifying) return;
    _isAutoVerifying = true;

    try {
      final success = await context.read<AdminProvider>().verifySaasOrder(orderId);
      if (success) {
        _pollingTimer?.cancel();
        _activeOrderId = null;

        if (mounted) {
          // Close verification waiting dialog
          Navigator.of(context, rootNavigator: true).popUntil((route) => route.isFirst || route.settings.name == '/');

          // Refresh subscription info
          await context.read<AdminProvider>().fetchSaasSubscription();

          // Show Success popup
          _showRenewalSuccessDialog();
        }
      } else if (!isBackgroundPoll && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Payment was not completed. Please try again.'),
            backgroundColor: AppColors.warning,
          ),
        );
      }
    } finally {
      _isAutoVerifying = false;
    }
  }

  void _showRenewalSuccessDialog() {
    final sub = context.read<AdminProvider>().saasSubscription;
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        return AlertDialog(
          backgroundColor: const Color(0xFF1E1E2C),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
            side: const BorderSide(color: AppColors.lime),
          ),
          contentPadding: const EdgeInsets.all(22),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 60,
                height: 60,
                decoration: BoxDecoration(
                  color: AppColors.lime.withOpacity(0.15),
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.lime, width: 2),
                ),
                child: const Icon(Icons.verified_rounded, color: AppColors.lime, size: 36),
              ),
              const SizedBox(height: 14),
              Text(
                'Payment Successful',
                style: GoogleFonts.outfit(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                  color: Colors.white,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 6),
              Text(
                'Subscription activated successfully',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppColors.lime,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 6),
              Text(
                'Your payment was verified with the payment gateway and your SaaS subscription has been activated immediately.',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 11.5,
                  color: Colors.white70,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFF13131A),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Column(
                  children: [
                    _summaryRow('Current Plan', sub?.planName ?? 'SaaS Plan', isBold: true),
                    _summaryRow('Subscription Status', 'Active', isBold: true, valueColor: AppColors.success),
                    _summaryRow('Start Date', sub?.subscriptionStart ?? '-', isBold: false),
                    _summaryRow(
                      'Expiry Date',
                      sub?.subscriptionExpiry ?? 'Extended',
                      isBold: true,
                      valueColor: const Color(0xFF00CEC9),
                    ),
                    _summaryRow('Remaining Days', '${sub?.daysRemaining ?? 30} Days', isBold: true, valueColor: AppColors.lime),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => Navigator.pop(ctx),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.lime,
                    foregroundColor: Colors.black,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                  ),
                  child: const Text('Continue', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _summaryRow(String label, String value, {bool isBold = false, Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(color: Colors.white60, fontSize: 12.5)),
          Text(
            value,
            style: TextStyle(
              color: valueColor ?? Colors.white,
              fontWeight: isBold ? FontWeight.bold : FontWeight.w500,
              fontSize: 13,
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final admin = context.watch<AdminProvider>();
    final currency = context.watch<AuthProvider>().currentTenant?.currency ?? '₹';
    final sub = admin.saasSubscription;

    return Scaffold(
      backgroundColor: const Color(0xFF13131A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E1E2C),
        elevation: 0,
        title: Text(
          'SaaS Subscription & Billing',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w900, fontSize: 18, color: Colors.white),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded, color: Colors.white70),
            onPressed: () => admin.fetchSaasSubscription(),
          ),
        ],
      ),
      body: admin.isSaasLoading && sub == null
          ? const Center(child: CircularProgressIndicator(color: AppColors.lime))
          : RefreshIndicator(
              onRefresh: () => admin.fetchSaasSubscription(),
              color: AppColors.lime,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // 1. Current Subscription Status Hero Card
                    if (sub != null) _buildCurrentSubscriptionCard(sub),
                    const SizedBox(height: 24),

                    // 2. Billing Cycle Switcher
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Available SaaS Plans',
                          style: GoogleFonts.outfit(fontSize: 17, fontWeight: FontWeight.w800, color: Colors.white),
                        ),
                        const SizedBox(height: 10),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(4),
                          decoration: BoxDecoration(
                            color: const Color(0xFF1E1E2C),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.white12),
                          ),
                          child: Row(
                            children: [
                              Expanded(child: _cycleOption('monthly', '1 Month')),
                              Expanded(child: _cycleOption('quarterly', '3 Mo (10% Off)')),
                              Expanded(child: _cycleOption('yearly', '1 Yr (Best)')),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),

                    // 3. Plans List
                    if (admin.saasPlans.isEmpty)
                      const Center(
                        child: Padding(
                          padding: EdgeInsets.all(24),
                          child: Text('No active SaaS plans found.', style: TextStyle(color: Colors.white54)),
                        ),
                      )
                    else
                      ListView.separated(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        itemCount: admin.saasPlans.length,
                        separatorBuilder: (ctx, i) => const SizedBox(height: 14),
                        itemBuilder: (ctx, i) {
                          final plan = admin.saasPlans[i];
                          return _buildPlanCard(plan, currency);
                        },
                      ),

                    const SizedBox(height: 28),

                    // 4. Payment History Section
                    if (admin.saasHistory.isNotEmpty) ...[
                      Text(
                        'Recent Renewal Invoices',
                        style: GoogleFonts.outfit(fontSize: 17, fontWeight: FontWeight.w800, color: Colors.white),
                      ),
                      const SizedBox(height: 12),
                      ListView.separated(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        itemCount: admin.saasHistory.length,
                        separatorBuilder: (ctx, i) => const SizedBox(height: 10),
                        itemBuilder: (ctx, i) {
                          final hist = admin.saasHistory[i];
                          return _buildHistoryCard(hist, currency);
                        },
                      ),
                      const SizedBox(height: 20),
                    ],
                  ],
                ),
              ),
            ),
    );
  }

  Widget _cycleOption(String key, String label) {
    final isSelected = _selectedCycle == key;
    return InkWell(
      onTap: () => setState(() => _selectedCycle = key),
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.lime : Colors.transparent,
          borderRadius: BorderRadius.circular(8),
        ),
        alignment: Alignment.center,
        child: Text(
          label,
          textAlign: TextAlign.center,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.bold,
            color: isSelected ? Colors.black : Colors.white70,
          ),
        ),
      ),
    );
  }

  Widget _buildCurrentSubscriptionCard(AdminSaasSubscriptionInfo sub) {
    final isExpired = sub.state == 'expired' || sub.daysRemaining < 0;
    final isExpiringSoon = sub.daysRemaining <= 7 && !isExpired;
    final isCritical = sub.daysRemaining <= 2 && !isExpired;

    String expiryFormatted = sub.subscriptionExpiry;
    String startFormatted = sub.subscriptionStart;
    try {
      final dt = DateTime.parse(sub.subscriptionExpiry);
      expiryFormatted = DateFormat('dd MMMM yyyy').format(dt);
    } catch (_) {}
    try {
      final dt = DateTime.parse(sub.subscriptionStart);
      startFormatted = DateFormat('dd MMMM yyyy').format(dt);
    } catch (_) {}

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFF1E1E2C),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: isCritical
              ? const Color(0xFFFF9F43)
              : (isExpired
                  ? AppColors.danger.withOpacity(0.6)
                  : (isExpiringSoon ? AppColors.warning.withOpacity(0.5) : AppColors.lime.withOpacity(0.3))),
          width: 1.5,
        ),
        boxShadow: [
          BoxShadow(
            color: isCritical
                ? const Color(0xFFFF9F43).withOpacity(0.15)
                : Colors.black.withOpacity(0.3),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: AppColors.lime.withOpacity(0.15),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.bolt_rounded, color: AppColors.lime, size: 20),
                  ),
                  const SizedBox(width: 10),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'CURRENT SAAS SUBSCRIPTION',
                        style: TextStyle(color: Colors.white54, fontSize: 10, fontWeight: FontWeight.bold, letterSpacing: 0.5),
                      ),
                      Text(
                        sub.planName,
                        style: GoogleFonts.outfit(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                decoration: BoxDecoration(
                  color: (isExpired ? AppColors.danger : (isExpiringSoon ? AppColors.warning : AppColors.success)).withOpacity(0.15),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  isExpired ? 'Expired' : 'Active',
                  style: TextStyle(
                    color: isExpired ? AppColors.danger : (isExpiringSoon ? AppColors.warning : AppColors.success),
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Validity & Expiry display
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFF13131A),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Start Date:', style: TextStyle(color: Colors.white54, fontSize: 11.5)),
                    Text(
                      startFormatted,
                      style: const TextStyle(color: Colors.white70, fontWeight: FontWeight.w600, fontSize: 12),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Expiry Date:', style: TextStyle(color: Colors.white54, fontSize: 11.5)),
                    Text(
                      expiryFormatted,
                      style: TextStyle(
                        color: isExpired ? AppColors.danger : (isExpiringSoon ? const Color(0xFFFF9F43) : Colors.white),
                        fontWeight: FontWeight.bold,
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ),
                const Divider(height: 14, color: Colors.white10),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Remaining Days:', style: TextStyle(color: Colors.white54, fontSize: 11.5)),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.06),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        isExpired
                            ? 'Expired'
                            : (sub.daysRemaining == 0
                                ? 'Expires Today'
                                : '${sub.daysRemaining} Days Left'),
                        style: TextStyle(
                          color: isExpired ? AppColors.danger : (isExpiringSoon ? const Color(0xFFFF9F43) : const Color(0xFF00CEC9)),
                          fontWeight: FontWeight.bold,
                          fontSize: 11.5,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),

          // Quota bars
          _quotaBar('Members Registered', sub.currentMembers, sub.maxMembers),
          const SizedBox(height: 8),
          _quotaBar('Staff Accounts', sub.currentStaff, sub.maxStaff),

          if (sub.message != null && sub.message!.isNotEmpty) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: (isExpired ? AppColors.danger : AppColors.warning).withOpacity(0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  Icon(Icons.info_outline, size: 16, color: isExpired ? AppColors.danger : AppColors.warning),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      sub.message!,
                      style: TextStyle(color: isExpired ? AppColors.danger : AppColors.warning, fontSize: 11.5),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _quotaBar(String label, int current, int maxLimit) {
    final double percent = maxLimit > 0 ? (current / maxLimit).clamp(0.0, 1.0) : 0.0;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label, style: const TextStyle(color: Colors.white60, fontSize: 11.5)),
            Text(
              '$current / $maxLimit',
              style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.bold),
            ),
          ],
        ),
        const SizedBox(height: 4),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: percent,
            minHeight: 5,
            backgroundColor: Colors.white10,
            valueColor: AlwaysStoppedAnimation<Color>(percent > 0.9 ? AppColors.danger : AppColors.lime),
          ),
        ),
      ],
    );
  }

  Widget _buildPlanCard(AdminSaasPlanItem plan, String currency) {
    double price = plan.priceMonthly;
    String periodText = '/ month';
    if (_selectedCycle == 'quarterly') {
      price = plan.priceQuarterly;
      periodText = '/ 3 months';
    } else if (_selectedCycle == 'yearly') {
      price = plan.priceYearly;
      periodText = '/ year';
    }

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFF1E1E2C),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: plan.isCurrent ? AppColors.lime.withOpacity(0.5) : Colors.white.withOpacity(0.08),
          width: plan.isCurrent ? 1.5 : 1.0,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  plan.name,
                  style: GoogleFonts.outfit(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    color: Colors.white,
                  ),
                ),
              ),
              if (plan.isCurrent)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: AppColors.lime.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Text(
                    'CURRENT PLAN',
                    style: TextStyle(color: AppColors.lime, fontWeight: FontWeight.bold, fontSize: 10),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 10),

          // Price display
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(
                '$currency${price.toStringAsFixed(0)}',
                style: GoogleFonts.outfit(
                  fontSize: 26,
                  fontWeight: FontWeight.w900,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 4),
              Text(
                periodText,
                style: const TextStyle(color: Colors.white54, fontSize: 12),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Quotas
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: const Color(0xFF13131A),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Row(
              children: [
                const Icon(Icons.people_outline, size: 14, color: Color(0xFF00CEC9)),
                const SizedBox(width: 6),
                Text(
                  'Up to ${plan.maxMembers} Members • ${plan.maxStaff} Staff',
                  style: const TextStyle(color: Colors.white70, fontSize: 11.5, fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),

          // Features Checklist
          if (plan.features.isNotEmpty) ...[
            ...plan.features.take(4).map(
              (f) => Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Row(
                  children: [
                    const Icon(Icons.check_circle_rounded, size: 14, color: AppColors.lime),
                    const SizedBox(width: 6),
                    Text(
                      f.replaceAll('_', ' ').toUpperCase(),
                      style: const TextStyle(color: Colors.white70, fontSize: 11.5),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 14),
          ],

          // CTA Button
          SizedBox(
            width: double.infinity,
            height: 44,
            child: ElevatedButton.icon(
              onPressed: () => _handlePlanSelection(plan),
              style: ElevatedButton.styleFrom(
                backgroundColor: plan.isCurrent ? const Color(0xFF00CEC9) : AppColors.lime,
                foregroundColor: Colors.black,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              icon: Icon(plan.isCurrent ? Icons.autorenew_rounded : Icons.check_circle_outline_rounded, size: 16),
              label: Text(
                plan.isCurrent ? 'Renew Plan' : 'Choose Plan',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHistoryCard(AdminSaasPaymentHistoryItem hist, String currency) {
    final isApproved = hist.status.toLowerCase() == 'approved';
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFF1E1E2C),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.white.withOpacity(0.06)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${hist.planName} (${hist.billingCycle})',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  'Ref: #${hist.transactionRef} • ${hist.createdAt.split(' ').first}',
                  style: const TextStyle(color: Colors.white38, fontSize: 11),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                '$currency${hist.totalPayable.toStringAsFixed(0)}',
                style: const TextStyle(color: Color(0xFF00CEC9), fontWeight: FontWeight.bold, fontSize: 14),
              ),
              const SizedBox(height: 2),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: (isApproved ? AppColors.success : AppColors.warning).withOpacity(0.15),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  hist.status.toUpperCase(),
                  style: TextStyle(
                    color: isApproved ? AppColors.success : AppColors.warning,
                    fontSize: 9.5,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
