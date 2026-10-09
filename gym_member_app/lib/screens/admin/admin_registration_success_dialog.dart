import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/services/pdf_service.dart';
import '../../models/admin_transaction_models.dart';
import '../../providers/admin_provider.dart';

class AdminRegistrationSuccessDialog extends StatefulWidget {
  final int memberId;
  final String memberName;
  final String memberCode;
  final String mobileNumber;
  final String planName;
  final String startDate;
  final String expiryDate;
  final double totalAmount;
  final double amountPaid;
  final double dueAmount;
  final String paymentMode;
  final String paymentStatus;

  const AdminRegistrationSuccessDialog({
    super.key,
    required this.memberId,
    required this.memberName,
    required this.memberCode,
    required this.mobileNumber,
    required this.planName,
    required this.startDate,
    required this.expiryDate,
    required this.totalAmount,
    required this.amountPaid,
    required this.dueAmount,
    required this.paymentMode,
    required this.paymentStatus,
  });

  static Future<void> show(
    BuildContext context, {
    required int memberId,
    required String memberName,
    required String memberCode,
    required String mobileNumber,
    required String planName,
    required String startDate,
    required String expiryDate,
    required double totalAmount,
    required double amountPaid,
    required double dueAmount,
    required String paymentMode,
    required String paymentStatus,
  }) {
    return showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AdminRegistrationSuccessDialog(
        memberId: memberId,
        memberName: memberName,
        memberCode: memberCode,
        mobileNumber: mobileNumber,
        planName: planName,
        startDate: startDate,
        expiryDate: expiryDate,
        totalAmount: totalAmount,
        amountPaid: amountPaid,
        dueAmount: dueAmount,
        paymentMode: paymentMode,
        paymentStatus: paymentStatus,
      ),
    );
  }

  @override
  State<AdminRegistrationSuccessDialog> createState() => _AdminRegistrationSuccessDialogState();
}

class _AdminRegistrationSuccessDialogState extends State<AdminRegistrationSuccessDialog> {
  bool _isLoadingPdfData = false;
  MemberRegistrationDocumentData? _cachedData;

  final currencyFormatter = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 2);

  Future<MemberRegistrationDocumentData?> _loadDocumentData() async {
    if (_cachedData != null) return _cachedData;

    setState(() => _isLoadingPdfData = true);
    final provider = Provider.of<AdminProvider>(context, listen: false);
    final data = await provider.fetchMemberRegistrationData(widget.memberId);
    setState(() {
      _isLoadingPdfData = false;
      _cachedData = data;
    });

    if (data == null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Failed to load official registration details from server.'),
          backgroundColor: AppColors.danger,
        ),
      );
    }
    return data;
  }

  @override
  Widget build(BuildContext context) {
    Color statusColor;
    switch (widget.paymentStatus.toLowerCase()) {
      case 'paid':
        statusColor = const Color(0xFF10B981);
        break;
      case 'partial':
        statusColor = const Color(0xFFF59E0B);
        break;
      default:
        statusColor = const Color(0xFFFF5A36);
    }

    return Dialog(
      backgroundColor: AppColors.card(context),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      insetPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
      child: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 64,
                height: 64,
                decoration: BoxDecoration(
                  color: AppColors.lime.withValues(alpha: 0.15),
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.lime, width: 2),
                ),
                child: const Icon(
                  Icons.check_circle_rounded,
                  color: AppColors.lime,
                  size: 38,
                ),
              ),
              const SizedBox(height: 14),

              Text(
                'Member Registered Successfully!',
                textAlign: TextAlign.center,
                style: GoogleFonts.outfit(
                  color: AppColors.textPrimary(context),
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Official member record has been created and securely saved.',
                textAlign: TextAlign.center,
                style: GoogleFonts.plusJakartaSans(
                  color: AppColors.textMuted(context),
                  fontSize: 12,
                ),
              ),
              const SizedBox(height: 16),

              // Member Card Details
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.cardElevated(context),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.border(context)),
                ),
                child: Column(
                  children: [
                    _buildRow(context, 'Member Name', widget.memberName, isBold: true),
                    _buildRow(context, 'Member ID', widget.memberCode, isHighlight: true),
                    _buildRow(context, 'Mobile Number', widget.mobileNumber),
                    _buildRow(context, 'Selected Plan', widget.planName),
                    _buildRow(context, 'Start Date', widget.startDate),
                    _buildRow(context, 'Expiry Date', widget.expiryDate),
                    Divider(color: AppColors.border(context), height: 16),
                    _buildRow(context, 'Registration / Plan Fee', currencyFormatter.format(widget.totalAmount)),
                    _buildRow(context, 'Amount Paid', currencyFormatter.format(widget.amountPaid), isSuccess: true),
                    if (widget.dueAmount > 0)
                      _buildRow(context, 'Remaining Balance', currencyFormatter.format(widget.dueAmount), isDanger: true),
                    _buildRow(context, 'Payment Mode', widget.paymentMode),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Payment Status',
                          style: GoogleFonts.plusJakartaSans(color: AppColors.textSecondary(context), fontSize: 12),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: statusColor.withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: statusColor.withValues(alpha: 0.3)),
                          ),
                          child: Text(
                            widget.paymentStatus.toUpperCase(),
                            style: GoogleFonts.plusJakartaSans(
                              color: statusColor,
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              if (_isLoadingPdfData)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Center(
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.lime),
                        ),
                        SizedBox(width: 10),
                        Text(
                          'Preparing official registration document...',
                          style: TextStyle(color: Colors.white70, fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                )
              else ...[
                // Primary Action: Share Registration PDF
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    icon: const Icon(Icons.share_rounded, size: 18),
                    label: const Text('Share Registration PDF', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13.5)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.lime,
                      foregroundColor: Colors.black,
                      padding: const EdgeInsets.symmetric(vertical: 13),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    onPressed: () async {
                      final docData = await _loadDocumentData();
                      if (docData != null && mounted) {
                        await PdfService.shareRegistrationPdf(context, docData);
                      }
                    },
                  ),
                ),
                const SizedBox(height: 8),

                // 2 Secondary Buttons: View PDF & Download PDF
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        icon: const Icon(Icons.remove_red_eye_outlined, size: 16),
                        label: const Text('View PDF', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.textPrimary(context),
                          side: BorderSide(color: AppColors.border(context)),
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        onPressed: () async {
                          final docData = await _loadDocumentData();
                          if (docData != null && mounted) {
                            PdfService.previewRegistrationPdf(context, docData);
                          }
                        },
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: OutlinedButton.icon(
                        icon: const Icon(Icons.download_rounded, size: 16),
                        label: const Text('Download PDF', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.textPrimary(context),
                          side: BorderSide(color: AppColors.border(context)),
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        onPressed: () async {
                          final docData = await _loadDocumentData();
                          if (docData != null && mounted) {
                            await PdfService.downloadRegistrationPdf(context, docData);
                          }
                        },
                      ),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 12),

              // Done / Back to Members
              SizedBox(
                width: double.infinity,
                child: TextButton(
                  onPressed: () => Navigator.pop(context),
                  style: TextButton.styleFrom(
                    foregroundColor: AppColors.textMuted(context),
                    padding: const EdgeInsets.symmetric(vertical: 10),
                  ),
                  child: const Text(
                    'Done / Back to Members',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildRow(
    BuildContext context,
    String label,
    String value, {
    bool isBold = false,
    bool isHighlight = false,
    bool isSuccess = false,
    bool isDanger = false,
  }) {
    Color valColor = AppColors.textPrimary(context);
    if (isHighlight) valColor = AppColors.lime;
    if (isSuccess) valColor = const Color(0xFF10B981);
    if (isDanger) valColor = const Color(0xFFFF5A36);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3.5),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: GoogleFonts.plusJakartaSans(color: AppColors.textSecondary(context), fontSize: 12),
          ),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: GoogleFonts.plusJakartaSans(
                color: valColor,
                fontSize: 12,
                fontWeight: (isBold || isHighlight || isSuccess || isDanger) ? FontWeight.bold : FontWeight.w500,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}
