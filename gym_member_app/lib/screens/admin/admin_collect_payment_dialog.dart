import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';
import 'admin_gym_qr_screen.dart';

class AdminCollectPaymentDialog extends StatefulWidget {
  final int memberId;
  final String memberName;
  final String memberPhone;
  final double currentDue;
  final String? currentDueDate;
  final String currentService;
  final int currentPlanMonths;

  const AdminCollectPaymentDialog({
    super.key,
    required this.memberId,
    required this.memberName,
    required this.memberPhone,
    required this.currentDue,
    this.currentDueDate,
    this.currentService = 'General Fitness',
    this.currentPlanMonths = 1,
  });

  @override
  State<AdminCollectPaymentDialog> createState() => _AdminCollectPaymentDialogState();
}

class _AdminCollectPaymentDialogState extends State<AdminCollectPaymentDialog> {
  final _formKey = GlobalKey<FormState>();
  final _amountController = TextEditingController();
  final _totalPlanFeeController = TextEditingController();
  final _notesController = TextEditingController();

  String _paymentType = 'due_clearance'; // 'due_clearance' or 'renewal'
  String _paymentMethod = 'Cash';
  int _planMonths = 1;
  DateTime? _newDueDate;
  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _planMonths = widget.currentPlanMonths > 0 ? widget.currentPlanMonths : 1;

    if (widget.currentDue > 0) {
      _paymentType = 'due_clearance';
      _amountController.text = widget.currentDue.toStringAsFixed(0);
    } else {
      _paymentType = 'renewal';
      _totalPlanFeeController.text = '1000';
      _amountController.text = '1000';
    }

    if (widget.currentDueDate != null) {
      try {
        _newDueDate = DateTime.parse(widget.currentDueDate!);
      } catch (_) {}
    }
  }

  @override
  void dispose() {
    _amountController.dispose();
    _totalPlanFeeController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _selectDueDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _newDueDate ?? DateTime.now().add(const Duration(days: 7)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: ColorScheme.dark(
              primary: AppColors.lime,
              onPrimary: Colors.black,
              surface: AppColors.card(context),
              onSurface: AppColors.textPrimary(context),
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null) {
      setState(() => _newDueDate = picked);
    }
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;

    final amountCollected = double.tryParse(_amountController.text.trim()) ?? 0.0;
    if (amountCollected <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter a valid payment amount.'),
          backgroundColor: AppColors.danger,
        ),
      );
      return;
    }

    setState(() => _isSubmitting = true);
    final admin = context.read<AdminProvider>();

    try {
      final totalPlanFee = _paymentType == 'renewal'
          ? (double.tryParse(_totalPlanFeeController.text.trim()) ?? amountCollected)
          : null;

      final result = await admin.collectPayment(
        memberId: widget.memberId,
        amountCollected: amountCollected,
        paymentMethod: _paymentMethod,
        paymentType: _paymentType,
        newDueDate: _newDueDate != null ? DateFormat('yyyy-MM-dd').format(_newDueDate!) : null,
        notes: _notesController.text.trim().isNotEmpty ? _notesController.text.trim() : null,
        planMonths: _paymentType == 'renewal' ? _planMonths : null,
        services: _paymentType == 'renewal' ? widget.currentService : null,
        totalPlanFee: totalPlanFee,
      );

      if (mounted && result != null) {
        Navigator.of(context).pop(); // Close collect modal
        _showSuccessSheet(result);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString()),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  void _showSuccessSheet(Map<String, dynamic> result) {
    final currency = context.read<AuthProvider>().currentTenant?.currency ?? '₹';
    final amount = result['amount_collected'] ?? 0;
    final remainingDue = (result['remaining_due'] is num) ? (result['remaining_due'] as num).toDouble() : 0.0;
    final invoiceNumber = result['invoice_number'] ?? '';
    final receiptUrl = result['receipt_url'];
    final whatsappMessage = result['whatsapp_message'] ?? '';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: AppColors.card(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    color: AppColors.success.withValues(alpha: 0.15),
                    shape: BoxShape.circle,
                    border: Border.all(color: AppColors.success, width: 2),
                  ),
                  child: const Icon(Icons.check_rounded, color: AppColors.success, size: 32),
                ),
                const SizedBox(height: 16),
                Text(
                  'Payment Recorded Successfully!',
                  style: GoogleFonts.outfit(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: AppColors.textPrimary(ctx),
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 6),
                Text(
                  'Invoice: $invoiceNumber',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: AppColors.textMuted(ctx),
                  ),
                ),
                const SizedBox(height: 18),

                // Summary Box
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.cardElevated(ctx),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: AppColors.border(ctx)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceAround,
                    children: [
                      Column(
                        children: [
                          Text(
                            'AMOUNT PAID',
                            style: GoogleFonts.plusJakartaSans(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: AppColors.textMuted(ctx),
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '$currency$amount',
                            style: GoogleFonts.outfit(
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                              color: AppColors.lime,
                            ),
                          ),
                        ],
                      ),
                      Container(height: 36, width: 1, color: AppColors.border(ctx)),
                      Column(
                        children: [
                          Text(
                            'REMAINING DUE',
                            style: GoogleFonts.plusJakartaSans(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: AppColors.textMuted(ctx),
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            remainingDue > 0 ? '$currency$remainingDue' : 'Zero (Paid)',
                            style: GoogleFonts.outfit(
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                              color: remainingDue > 0 ? AppColors.warning : AppColors.success,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // WhatsApp Share Receipt Button
                if (whatsappMessage.isNotEmpty && widget.memberPhone.isNotEmpty)
                  ElevatedButton.icon(
                    onPressed: () async {
                      final cleanPhone = widget.memberPhone.replaceAll(RegExp(r'[^0-9]'), '');
                      final url = 'https://wa.me/$cleanPhone?text=${Uri.encodeComponent(whatsappMessage)}';
                      final uri = Uri.parse(url);
                      if (await canLaunchUrl(uri)) {
                        await launchUrl(uri, mode: LaunchMode.externalApplication);
                      }
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF25D366),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      minimumSize: const Size.fromHeight(48),
                    ),
                    icon: const Icon(Icons.send_rounded, size: 18),
                    label: Text(
                      'Send Receipt on WhatsApp',
                      style: GoogleFonts.plusJakartaSans(
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                      ),
                    ),
                  ),
                const SizedBox(height: 10),

                // View HTML Receipt button
                if (receiptUrl != null && receiptUrl.toString().isNotEmpty)
                  OutlinedButton.icon(
                    onPressed: () async {
                      final uri = Uri.parse(receiptUrl.toString());
                      if (await canLaunchUrl(uri)) {
                        await launchUrl(uri, mode: LaunchMode.externalApplication);
                      }
                    },
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.textPrimary(ctx),
                      side: BorderSide(color: AppColors.border(ctx)),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      minimumSize: const Size.fromHeight(44),
                    ),
                    icon: const Icon(Icons.receipt_long_rounded, size: 18),
                    label: Text(
                      'View & Print Receipt',
                      style: GoogleFonts.plusJakartaSans(
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                      ),
                    ),
                  ),
                const SizedBox(height: 12),

                TextButton(
                  onPressed: () => Navigator.of(ctx).pop(),
                  child: Text(
                    'Done',
                    style: GoogleFonts.plusJakartaSans(
                      color: AppColors.textMuted(ctx),
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final currency = context.watch<AuthProvider>().currentTenant?.currency ?? '₹';

    return Dialog(
      backgroundColor: AppColors.card(context),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(24),
        side: BorderSide(color: AppColors.border(context)),
      ),
      insetPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxWidth: 440,
          maxHeight: MediaQuery.of(context).size.height * 0.85,
        ),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Header
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 20, 16, 12),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: AppColors.lime.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.limeBorder),
                      ),
                      child: const Icon(Icons.payments_rounded, color: AppColors.lime, size: 22),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Collect Payment',
                            style: GoogleFonts.outfit(
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                              color: AppColors.textPrimary(context),
                            ),
                          ),
                          Text(
                            widget.memberName,
                            style: GoogleFonts.plusJakartaSans(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textMuted(context),
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close_rounded),
                      onPressed: () => Navigator.of(context).pop(),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1),

              // Body Content
              Flexible(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Mode Selector (Clear Dues vs Renewal)
                      Container(
                        padding: const EdgeInsets.all(4),
                        decoration: BoxDecoration(
                          color: AppColors.cardElevated(context),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.border(context)),
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: InkWell(
                                onTap: () {
                                  setState(() {
                                    _paymentType = 'due_clearance';
                                    _amountController.text = widget.currentDue > 0
                                        ? widget.currentDue.toStringAsFixed(0)
                                        : '500';
                                  });
                                },
                                borderRadius: BorderRadius.circular(10),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(vertical: 10),
                                  decoration: BoxDecoration(
                                    color: _paymentType == 'due_clearance'
                                        ? AppColors.lime
                                        : Colors.transparent,
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Text(
                                    'Clear Due ($currency${widget.currentDue.toStringAsFixed(0)})',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 12,
                                      color: _paymentType == 'due_clearance'
                                          ? Colors.black
                                          : AppColors.textMuted(context),
                                    ),
                                    textAlign: TextAlign.center,
                                  ),
                                ),
                              ),
                            ),
                            Expanded(
                              child: InkWell(
                                onTap: () {
                                  setState(() {
                                    _paymentType = 'renewal';
                                    if (_totalPlanFeeController.text.isEmpty) {
                                      _totalPlanFeeController.text = '1000';
                                    }
                                    _amountController.text = _totalPlanFeeController.text;
                                  });
                                },
                                borderRadius: BorderRadius.circular(10),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(vertical: 10),
                                  decoration: BoxDecoration(
                                    color: _paymentType == 'renewal'
                                        ? AppColors.lime
                                        : Colors.transparent,
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Text(
                                    'Renew Membership',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 12,
                                      color: _paymentType == 'renewal'
                                          ? Colors.black
                                          : AppColors.textMuted(context),
                                    ),
                                    textAlign: TextAlign.center,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 18),

                      if (_paymentType == 'renewal') ...[
                        // Renewal Months & Total Plan Fee
                        Row(
                          children: [
                            Expanded(
                              child: DropdownButtonFormField<int>(
                                initialValue: const [1, 3, 6, 12].contains(_planMonths) ? _planMonths : 1,
                                decoration: const InputDecoration(labelText: 'Plan Duration'),
                                dropdownColor: AppColors.card(context),
                                items: const [
                                  DropdownMenuItem(value: 1, child: Text('1 Month')),
                                  DropdownMenuItem(value: 3, child: Text('3 Months')),
                                  DropdownMenuItem(value: 6, child: Text('6 Months')),
                                  DropdownMenuItem(value: 12, child: Text('1 Year (12 Mo)')),
                                ],
                                onChanged: (v) {
                                  if (v != null) setState(() => _planMonths = v);
                                },
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                controller: _totalPlanFeeController,
                                keyboardType: TextInputType.number,
                                decoration: InputDecoration(
                                  labelText: 'Total Plan Fee',
                                  prefixText: '$currency ',
                                ),
                                onChanged: (v) {
                                  setState(() {
                                    _amountController.text = v;
                                  });
                                },
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                      ],

                      // Amount Being Collected
                      TextFormField(
                        controller: _amountController,
                        keyboardType: TextInputType.number,
                        style: GoogleFonts.outfit(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          color: AppColors.lime,
                        ),
                        decoration: InputDecoration(
                          labelText: 'Amount Being Collected Now',
                          prefixText: '$currency ',
                          prefixStyle: GoogleFonts.outfit(
                            fontSize: 22,
                            fontWeight: FontWeight.w900,
                            color: AppColors.lime,
                          ),
                        ),
                        validator: (value) {
                          if (value == null || value.trim().isEmpty) return 'Enter amount';
                          final num = double.tryParse(value.trim());
                          if (num == null || num <= 0) return 'Must be greater than 0';
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),

                      // Payment Method Selector Chips
                      Text(
                        'PAYMENT METHOD',
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textMuted(context),
                          letterSpacing: 0.5,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: ['Cash', 'UPI', 'Card', 'Online'].map((method) {
                          final isSelected = _paymentMethod == method;
                          return Expanded(
                            child: Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 3),
                              child: ChoiceChip(
                                label: Text(method),
                                selected: isSelected,
                                onSelected: (s) {
                                  if (s) {
                                    setState(() => _paymentMethod = method);
                                    if (method == 'UPI') {
                                      // Suggest open QR
                                      showDialog(
                                        context: context,
                                        builder: (_) => AdminGymQrScreen(
                                          isModal: true,
                                          initialAmount: double.tryParse(_amountController.text),
                                          initialNote: 'Payment from ${widget.memberName}',
                                        ),
                                      );
                                    }
                                  }
                                },
                                selectedColor: AppColors.lime,
                                labelStyle: GoogleFonts.plusJakartaSans(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 11.5,
                                  color: isSelected ? Colors.black : AppColors.textPrimary(context),
                                ),
                              ),
                            ),
                          );
                        }).toList(),
                      ),
                      const SizedBox(height: 16),

                      // Next Due Date (if partial payment)
                      InkWell(
                        onTap: _selectDueDate,
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                          decoration: BoxDecoration(
                            color: AppColors.cardElevated(context),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: AppColors.border(context)),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.event_note_rounded, size: 18, color: AppColors.cyan),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Promise Due Date (If Partial)',
                                      style: GoogleFonts.plusJakartaSans(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w700,
                                        color: AppColors.textMuted(context),
                                      ),
                                    ),
                                    Text(
                                      _newDueDate != null
                                          ? DateFormat('dd MMM yyyy').format(_newDueDate!)
                                          : 'Not set (Full Settlement)',
                                      style: GoogleFonts.plusJakartaSans(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w700,
                                        color: AppColors.textPrimary(context),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              Icon(Icons.edit_calendar_rounded, size: 16, color: AppColors.textMuted(context)),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),

                      // Optional Remarks
                      TextFormField(
                        controller: _notesController,
                        style: GoogleFonts.plusJakartaSans(fontSize: 13),
                        decoration: const InputDecoration(
                          labelText: 'Notes / Remarks (Optional)',
                          hintText: 'e.g., Cash collected at front desk',
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const Divider(height: 1),

              // Action Buttons
              Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.of(context).pop(),
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          side: BorderSide(color: AppColors.border(context)),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        child: Text(
                          'Cancel',
                          style: GoogleFonts.plusJakartaSans(
                            fontWeight: FontWeight.w700,
                            color: AppColors.textMuted(context),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      flex: 2,
                      child: ElevatedButton(
                        onPressed: _isSubmitting ? null : _handleSubmit,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.lime,
                          foregroundColor: Colors.black,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        child: _isSubmitting
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.black),
                              )
                            : Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  const Icon(Icons.check_circle_rounded, size: 18),
                                  const SizedBox(width: 8),
                                  Text(
                                    'Confirm Payment',
                                    style: GoogleFonts.plusJakartaSans(
                                      fontWeight: FontWeight.w900,
                                      fontSize: 14,
                                    ),
                                  ),
                                ],
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
