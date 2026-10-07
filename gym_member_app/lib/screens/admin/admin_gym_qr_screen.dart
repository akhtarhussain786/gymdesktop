import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';

class AdminGymQrScreen extends StatefulWidget {
  const AdminGymQrScreen({super.key});

  @override
  State<AdminGymQrScreen> createState() => _AdminGymQrScreenState();
}

class _AdminGymQrScreenState extends State<AdminGymQrScreen> {
  final _amountController = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AdminProvider>().loadGymQr();
    });
  }

  @override
  void dispose() {
    _amountController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = context.watch<AuthProvider>();
    final admin = context.watch<AdminProvider>();
    final tenant = auth.currentTenant;
    final upiId = tenant?.upiId?.trim() ?? admin.dashboardData?.upiId ?? '';
    final gymName = tenant?.gymName ?? 'Our Gym';

    final customAmount = double.tryParse(_amountController.text.trim()) ?? 0.0;
    String qrData = "upi://pay?pa=$upiId&pn=${Uri.encodeComponent(gymName)}&cu=INR";
    if (customAmount > 0) {
      qrData += "&am=${customAmount.toStringAsFixed(2)}";
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Gym UPI QR Code', style: TextStyle(fontWeight: FontWeight.bold)),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          children: [
            // Container Box
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: theme.cardColor,
                borderRadius: BorderRadius.circular(24),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.08),
                    blurRadius: 16,
                    offset: const Offset(0, 4),
                  ),
                ],
                border: Border.all(color: Colors.grey.withOpacity(0.15)),
              ),
              child: Column(
                children: [
                  // Gym Logo or Bolt Icon
                  Container(
                    width: 54,
                    height: 54,
                    decoration: BoxDecoration(
                      color: const Color(0xFF10B981).withOpacity(0.12),
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(Icons.bolt, color: Color(0xFF10B981), size: 32),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    gymName,
                    style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 20),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Direct UPI Counter Payment',
                    style: TextStyle(color: Colors.grey[600], fontSize: 13),
                  ),

                  const SizedBox(height: 20),

                  // QR Code
                  if (upiId.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: Colors.amber.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Column(
                        children: [
                          Icon(Icons.warning, color: Colors.amber, size: 36),
                          SizedBox(height: 8),
                          Text(
                            'UPI ID not configured in Gym Settings.\nPlease set your UPI ID in website settings.',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold),
                          ),
                        ],
                      ),
                    )
                  else
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: Colors.grey.withOpacity(0.2)),
                      ),
                      child: QrImageView(
                        data: qrData,
                        version: QrVersions.auto,
                        size: 220.0,
                        backgroundColor: Colors.white,
                      ),
                    ),

                  const SizedBox(height: 16),

                  // UPI ID display pill
                  if (upiId.isNotEmpty)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      decoration: BoxDecoration(
                        color: Colors.grey.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.account_balance_wallet, size: 16, color: Color(0xFF10B981)),
                          const SizedBox(width: 6),
                          Text(
                            upiId,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                          ),
                        ],
                      ),
                    ),

                  if (customAmount > 0) ...[
                    const SizedBox(height: 10),
                    Text(
                      'Fixed Amount: ₹${customAmount.toStringAsFixed(2)}',
                      style: const TextStyle(
                        color: Color(0xFF10B981),
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                    ),
                  ],
                ],
              ),
            ),

            const SizedBox(height: 24),

            // Optional: Enter specific amount
            TextField(
              controller: _amountController,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(
                labelText: 'Optional: Enter Specific Amount (₹)',
                hintText: 'e.g. 1500',
                prefixIcon: const Icon(Icons.currency_rupee),
                filled: true,
                fillColor: theme.cardColor,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
              ),
              onChanged: (_) => setState(() {}),
            ),
          ],
        ),
      ),
    );
  }
}
