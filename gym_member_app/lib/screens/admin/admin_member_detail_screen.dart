import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';

class AdminMemberDetailScreen extends StatefulWidget {
  final int memberId;

  const AdminMemberDetailScreen({super.key, required this.memberId});

  @override
  State<AdminMemberDetailScreen> createState() => _AdminMemberDetailScreenState();
}

class _AdminMemberDetailScreenState extends State<AdminMemberDetailScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AdminProvider>().loadMemberDetail(widget.memberId);
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _makePhoneCall(String phoneNumber) async {
    final clean = phoneNumber.replaceAll(RegExp(r'[^0-9+]'), '');
    final uri = Uri.parse('tel:$clean');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  Future<void> _sendWhatsApp(String phone, String message) async {
    final clean = phone.replaceAll(RegExp(r'[^0-9]'), '');
    final fullPhone = clean.startsWith('91') || clean.length > 10 ? clean : '91$clean';
    final uri = Uri.parse('https://wa.me/$fullPhone?text=${Uri.encodeComponent(message)}');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  // --- COLLECT CASH / CLEAR DUE BOTTOM SHEET ---
  void _showCollectPaymentSheet(BuildContext context, Map<String, dynamic> member, String currency) {
    final dueAmount = (member['due_amount'] ?? 0.0).toDouble();
    final amountController = TextEditingController(
      text: dueAmount > 0 ? dueAmount.toStringAsFixed(0) : '1000',
    );
    final notesController = TextEditingController();
    String selectedMethod = 'Cash';
    String paymentType = dueAmount > 0 ? 'due_clearance' : 'renewal';
    DateTime? selectedDueDate;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (sheetContext, setSheetState) {
            final enteredAmount = double.tryParse(amountController.text.trim()) ?? 0.0;
            final remainingBalance = (dueAmount - enteredAmount).clamp(0.0, 999999.0);

            return Container(
              decoration: BoxDecoration(
                color: Theme.of(context).scaffoldBackgroundColor,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
              ),
              padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(sheetContext).viewInsets.bottom + 20),
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
                          color: Colors.grey.withOpacity(0.3),
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          dueAmount > 0 ? '💵 Collect Cash / Due' : '🔄 Record Membership Renewal',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                        ),
                        IconButton(
                          icon: const Icon(Icons.close),
                          onPressed: () => Navigator.pop(sheetContext),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),

                    // Member Due Summary Box
                    if (dueAmount > 0)
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEF4444).withOpacity(0.08),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFEF4444).withOpacity(0.25)),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Total Due Pending:', style: TextStyle(fontWeight: FontWeight.w600)),
                            Text(
                              '$currency${dueAmount.toStringAsFixed(2)}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                                fontSize: 16,
                                color: Color(0xFFEF4444),
                              ),
                            ),
                          ],
                        ),
                      ),

                    const SizedBox(height: 16),

                    // Amount Field
                    Text('Amount Paying ($currency) *', style: const TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 6),
                    TextField(
                      controller: amountController,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Color(0xFF10B981)),
                      decoration: InputDecoration(
                        prefixText: '$currency ',
                        hintText: 'Enter collected cash amount',
                        filled: true,
                        fillColor: Theme.of(context).cardColor,
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      onChanged: (_) => setSheetState(() {}),
                    ),

                    const SizedBox(height: 16),

                    // Payment Method Picker
                    const Text('Payment Method', style: TextStyle(fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        _buildMethodRadio(
                          label: 'Cash',
                          icon: Icons.payments,
                          value: 'Cash',
                          groupValue: selectedMethod,
                          onChanged: (val) => setSheetState(() => selectedMethod = val!),
                        ),
                        const SizedBox(width: 8),
                        _buildMethodRadio(
                          label: 'UPI / QR',
                          icon: Icons.qr_code,
                          value: 'UPI',
                          groupValue: selectedMethod,
                          onChanged: (val) => setSheetState(() => selectedMethod = val!),
                        ),
                        const SizedBox(width: 8),
                        _buildMethodRadio(
                          label: 'Card',
                          icon: Icons.credit_card,
                          value: 'Card',
                          groupValue: selectedMethod,
                          onChanged: (val) => setSheetState(() => selectedMethod = val!),
                        ),
                      ],
                    ),

                    const SizedBox(height: 14),

                    // Balance Remaining Calculator
                    if (dueAmount > 0)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        decoration: BoxDecoration(
                          color: Theme.of(context).cardColor,
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: Colors.grey.withOpacity(0.2)),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Remaining Due Balance:'),
                            Text(
                              remainingBalance <= 0
                                  ? '✓ ZERO (Fully Paid)'
                                  : '$currency${remainingBalance.toStringAsFixed(2)}',
                              style: TextStyle(
                                fontWeight: FontWeight.bold,
                                color: remainingBalance <= 0 ? const Color(0xFF10B981) : const Color(0xFFEF4444),
                              ),
                            ),
                          ],
                        ),
                      ),

                    if (remainingBalance > 0) ...[
                      const SizedBox(height: 14),
                      const Text('Next Promised Due Date (Optional)', style: TextStyle(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 6),
                      InkWell(
                        onTap: () async {
                          final picked = await showDatePicker(
                            context: sheetContext,
                            initialDate: DateTime.now().add(const Duration(days: 7)),
                            firstDate: DateTime.now(),
                            lastDate: DateTime.now().add(const Duration(days: 365)),
                          );
                          if (picked != null) {
                            setSheetState(() => selectedDueDate = picked);
                          }
                        },
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                          decoration: BoxDecoration(
                            color: Theme.of(context).cardColor,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.grey.withOpacity(0.2)),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                selectedDueDate != null
                                    ? DateFormat('yyyy-MM-dd').format(selectedDueDate!)
                                    : 'Select Next Due Promise Date',
                              ),
                              const Icon(Icons.calendar_today, size: 18),
                            ],
                          ),
                        ),
                      ),
                    ],

                    const SizedBox(height: 24),

                    // Confirm Button
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton.icon(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF10B981),
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        icon: const Icon(Icons.check_circle),
                        label: const Text(
                          'CONFIRM & RECORD PAYMENT',
                          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                        ),
                        onPressed: () async {
                          if (enteredAmount <= 0) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('Please enter a valid payment amount.')),
                            );
                            return;
                          }

                          Navigator.pop(sheetContext);

                          final adminProvider = context.read<AdminProvider>();
                          final res = await adminProvider.collectPayment(
                            memberId: widget.memberId,
                            amount: enteredAmount,
                            paymentMethod: selectedMethod,
                            paymentType: paymentType,
                            newDueDate: selectedDueDate != null
                                ? DateFormat('yyyy-MM-dd').format(selectedDueDate!)
                                : null,
                            notes: notesController.text.trim().isNotEmpty
                                ? notesController.text.trim()
                                : 'Collected cash at gym counter',
                          );

                          if (res != null && mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text('Payment of $currency$enteredAmount recorded successfully!'),
                                backgroundColor: const Color(0xFF10B981),
                              ),
                            );

                            // Show WhatsApp Share Dialog
                            final waMsg = res['whatsapp_message'];
                            if (waMsg != null && member['phone'] != null) {
                              _showWhatsAppReceiptDialog(context, member['phone'], waMsg.toString());
                            }
                          } else if (adminProvider.errorMessage != null && mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(adminProvider.errorMessage!),
                                backgroundColor: const Color(0xFFEF4444),
                              ),
                            );
                          }
                        },
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

  void _showWhatsAppReceiptDialog(BuildContext context, String phone, String message) {
    showDialog(
      context: context,
      builder: (dlgContext) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.check_circle, color: Color(0xFF10B981)),
            SizedBox(width: 8),
            Text('Receipt Generated!'),
          ],
        ),
        content: const Text(
          'Would you like to send the payment receipt directly to the customer on WhatsApp?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dlgContext),
            child: const Text('Later'),
          ),
          ElevatedButton.icon(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF25D366),
              foregroundColor: Colors.white,
            ),
            icon: const Icon(Icons.chat),
            label: const Text('Send on WhatsApp'),
            onPressed: () {
              Navigator.pop(dlgContext);
              _sendWhatsApp(phone, message);
            },
          ),
        ],
      ),
    );
  }

  Widget _buildMethodRadio({
    required String label,
    required IconData icon,
    required String value,
    required String groupValue,
    required ValueChanged<String?> onChanged,
  }) {
    final isSelected = value == groupValue;
    return Expanded(
      child: InkWell(
        onTap: () => onChanged(value),
        borderRadius: BorderRadius.circular(10),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            color: isSelected ? const Color(0xFF3B82F6).withOpacity(0.12) : Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(
              color: isSelected ? const Color(0xFF3B82F6) : Colors.grey.withOpacity(0.25),
              width: isSelected ? 1.5 : 1,
            ),
          ),
          child: Column(
            children: [
              Icon(icon, color: isSelected ? const Color(0xFF3B82F6) : Colors.grey[600], size: 20),
              const SizedBox(height: 4),
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                  color: isSelected ? const Color(0xFF3B82F6) : Colors.grey[800],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final admin = context.watch<AdminProvider>();
    final detail = admin.selectedMemberDetail;
    final member = detail?['member'] as Map<String, dynamic>?;
    final invoices = (detail?['invoices'] as List? ?? []);
    final attendance = (detail?['attendance'] as List? ?? []);
    final currency = detail?['gym']?['currency'] ?? '₹';

    if (admin.isLoadingDetail && member == null) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    if (member == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Member Profile')),
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.red),
              const SizedBox(height: 12),
              const Text('Member record not found.'),
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Go Back'),
              ),
            ],
          ),
        ),
      );
    }

    final dueAmount = (member['due_amount'] ?? 0.0).toDouble();
    final hasDue = dueAmount > 0;
    final phone = (member['phone'] ?? '').toString();
    final waMsg = member['whatsapp_reminder'] ?? '';

    return Scaffold(
      appBar: AppBar(
        title: Text(member['fullname'] ?? 'Customer Profile', style: const TextStyle(fontWeight: FontWeight.bold)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => admin.loadMemberDetail(widget.memberId),
          ),
        ],
      ),
      body: SingleChildScrollView(
        child: Column(
          children: [
            // --- HEADER: MEMBER PROFILE PHOTO & INFO ---
            Container(
              padding: const EdgeInsets.all(20),
              color: theme.cardColor,
              child: Column(
                children: [
                  Row(
                    children: [
                      // Large Member Photo
                      ClipRRect(
                        borderRadius: BorderRadius.circular(16),
                        child: Container(
                          width: 80,
                          height: 80,
                          color: const Color(0xFF3B82F6).withOpacity(0.12),
                          child: member['avatar'] != null
                              ? Image.network(
                                  member['avatar']!,
                                  fit: BoxFit.cover,
                                  errorBuilder: (_, __, ___) => _buildAvatarFallback(member['fullname']),
                                )
                              : _buildAvatarFallback(member['fullname']),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              member['fullname'] ?? '',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 20),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Username: @${member['username'] ?? ''}',
                              style: TextStyle(fontSize: 13, color: Colors.grey[600]),
                            ),
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                Icon(Icons.phone, size: 14, color: Colors.grey[600]),
                                const SizedBox(width: 4),
                                Text(
                                  phone.isNotEmpty ? phone : 'No Phone Number',
                                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                                ),
                              ],
                            ),
                            if (member['address'] != null && member['address'].toString().isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  Icon(Icons.location_on, size: 14, color: Colors.grey[600]),
                                  const SizedBox(width: 4),
                                  Expanded(
                                    child: Text(
                                      member['address'].toString(),
                                      style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 16),

                  // Quick Action Buttons (Call, WhatsApp, Settle Due)
                  Row(
                    children: [
                      if (phone.isNotEmpty) ...[
                        Expanded(
                          child: OutlinedButton.icon(
                            style: OutlinedButton.styleFrom(
                              foregroundColor: const Color(0xFF3B82F6),
                              side: const BorderSide(color: Color(0xFF3B82F6)),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            icon: const Icon(Icons.phone, size: 18),
                            label: const Text('Call'),
                            onPressed: () => _makePhoneCall(phone),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: OutlinedButton.icon(
                            style: OutlinedButton.styleFrom(
                              foregroundColor: const Color(0xFF25D366),
                              side: const BorderSide(color: Color(0xFF25D366)),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            icon: const Icon(Icons.chat, size: 18),
                            label: const Text('WhatsApp'),
                            onPressed: () => _sendWhatsApp(phone, waMsg.toString()),
                          ),
                        ),
                        const SizedBox(width: 8),
                      ],
                      Expanded(
                        flex: phone.isNotEmpty ? 1 : 2,
                        child: ElevatedButton.icon(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: hasDue ? const Color(0xFFEF4444) : const Color(0xFF10B981),
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                          icon: Icon(hasDue ? Icons.payments : Icons.autorenew, size: 18),
                          label: Text(hasDue ? 'Collect Cash' : 'Renew'),
                          onPressed: () => _showCollectPaymentSheet(context, member, currency),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: 12),

            // --- FINANCIALS & DUES BREAKDOWN CARD ---
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: hasDue ? const Color(0xFFEF4444).withOpacity(0.08) : const Color(0xFF10B981).withOpacity(0.08),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                    color: hasDue ? const Color(0xFFEF4444).withOpacity(0.3) : const Color(0xFF10B981).withOpacity(0.3),
                  ),
                ),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Total Plan Fees', style: TextStyle(fontSize: 12, color: Colors.grey)),
                            Text(
                              '$currency${((member['total_fee'] ?? 0.0) as num).toStringAsFixed(2)}',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                            ),
                          ],
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            const Text('Amount Paid', style: TextStyle(fontSize: 12, color: Colors.grey)),
                            Text(
                              '$currency${((member['paid_amount'] ?? 0.0) as num).toStringAsFixed(2)}',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Color(0xFF10B981)),
                            ),
                          ],
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            const Text('Pending Due', style: TextStyle(fontSize: 12, color: Colors.grey)),
                            Text(
                              '$currency${dueAmount.toStringAsFixed(2)}',
                              style: TextStyle(
                                fontWeight: FontWeight.w900,
                                fontSize: 18,
                                color: hasDue ? const Color(0xFFEF4444) : const Color(0xFF10B981),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                    if (hasDue && member['due_date'] != null) ...[
                      const SizedBox(height: 10),
                      const Divider(height: 1),
                      const SizedBox(height: 8),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Due Promise Date:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                          Text(
                            member['due_date'].toString(),
                            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFFEF4444)),
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // --- TABS: INVOICES & ATTENDANCE ---
            TabBar(
              controller: _tabController,
              tabs: [
                Tab(text: 'Invoices & Receipts (${invoices.length})'),
                Tab(text: 'Attendance (${attendance.length})'),
              ],
            ),

            SizedBox(
              height: 320,
              child: TabBarView(
                controller: _tabController,
                children: [
                  // Tab 1: Invoices
                  invoices.isEmpty
                      ? const Center(child: Text('No invoice receipts recorded yet.'))
                      : ListView.separated(
                          padding: const EdgeInsets.all(16),
                          itemCount: invoices.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 10),
                          itemBuilder: (context, index) {
                            final inv = invoices[index];
                            final isPaid = inv['status'] == 'Paid';
                            return Card(
                              elevation: 0,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                                side: BorderSide(color: Colors.grey.withOpacity(0.2)),
                              ),
                              child: ListTile(
                                leading: Icon(
                                  isPaid ? Icons.check_circle : Icons.pending_actions,
                                  color: isPaid ? const Color(0xFF10B981) : const Color(0xFFEF4444),
                                ),
                                title: Text(
                                  '#${inv['invoice_number']}',
                                  style: const TextStyle(fontWeight: FontWeight.bold),
                                ),
                                subtitle: Text(
                                  '${inv['payment_date']} • ${inv['payment_method']}',
                                  style: const TextStyle(fontSize: 12),
                                ),
                                trailing: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    Text(
                                      '$currency${inv['paid_amount']}',
                                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                                    ),
                                    Text(
                                      isPaid ? 'Paid' : 'Due: $currency${inv['due_amount']}',
                                      style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.bold,
                                        color: isPaid ? const Color(0xFF10B981) : const Color(0xFFEF4444),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),

                  // Tab 2: Attendance
                  attendance.isEmpty
                      ? const Center(child: Text('No attendance recorded yet.'))
                      : ListView.separated(
                          padding: const EdgeInsets.all(16),
                          itemCount: attendance.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 8),
                          itemBuilder: (context, index) {
                            final att = attendance[index];
                            return ListTile(
                              dense: true,
                              leading: const Icon(Icons.check_circle, color: Color(0xFF10B981), size: 20),
                              title: Text(att['curr_date'] ?? ''),
                              subtitle: Text(att['curr_time'] ?? 'Recorded'),
                              trailing: const Text('Present', style: TextStyle(color: Color(0xFF10B981), fontWeight: FontWeight.bold)),
                            );
                          },
                        ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAvatarFallback(String? fullname) {
    final name = (fullname != null && fullname.isNotEmpty) ? fullname : 'Member';
    return Center(
      child: Text(
        name[0].toUpperCase(),
        style: const TextStyle(
          fontWeight: FontWeight.bold,
          fontSize: 32,
          color: Color(0xFF3B82F6),
        ),
      ),
    );
  }
}
