import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/member_data_provider.dart';
import '../widgets/branded_button.dart';
import '../widgets/empty_state_view.dart';
import '../widgets/error_retry_view.dart';
import '../widgets/status_badge.dart';

class SupportScreen extends StatefulWidget {
  const SupportScreen({super.key});

  @override
  State<SupportScreen> createState() => _SupportScreenState();
}

class _SupportScreenState extends State<SupportScreen> {
  final _subjectController = TextEditingController();
  final _messageController = TextEditingController();
  String _selectedCategory = 'General Inquiry';
  bool _isSubmitting = false;

  final List<String> _categories = [
    'General Inquiry',
    'Membership & Renewal',
    'Workout & Trainer',
    'Billing & Receipt',
    'Feedback / Suggestion'
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MemberDataProvider>().fetchSupport();
    });
  }

  @override
  void dispose() {
    _subjectController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _submitTicket() async {
    final subject = _subjectController.text.trim();
    final message = _messageController.text.trim();

    if (subject.isEmpty || message.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please provide both subject and message.'),
          backgroundColor: AppColors.warning,
        ),
      );
      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    final success = await context.read<MemberDataProvider>().submitInquiry(
          subject,
          message,
          _selectedCategory,
        );

    setState(() {
      _isSubmitting = false;
    });

    if (success && mounted) {
      _subjectController.clear();
      _messageController.clear();
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Help request submitted to gym administration!'),
          backgroundColor: AppColors.success,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<MemberDataProvider>();
    final data = provider.support;

    return Scaffold(
      backgroundColor: AppColors.darkBg,
      appBar: AppBar(
        backgroundColor: AppColors.darkBgDeep,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        title: Text(
          'SUPPORT & HELP DESK',
          style: GoogleFonts.outfit(
            color: AppColors.darkTextPrimary,
            fontWeight: FontWeight.w800,
            fontSize: 18,
            letterSpacing: 0.5,
          ),
        ),
      ),
      body: Builder(
        builder: (context) {
          if (provider.loading && data == null) {
            return const Center(
              child: CircularProgressIndicator(
                valueColor: AlwaysStoppedAnimation<Color>(AppColors.lime),
              ),
            );
          }

          if (provider.error != null && data == null) {
            return ErrorRetryView(
              message: provider.error!,
              onRetry: () => provider.fetchSupport(refresh: true),
            );
          }

          if (data == null) {
            return const EmptyStateView(
              title: 'Support Desk',
              message: 'Could not load gym support details.',
              icon: Icons.help_outline_rounded,
            );
          }

          final contact = data.gymContact;
          final timings = data.timings;

          return RefreshIndicator(
            color: AppColors.lime,
            backgroundColor: AppColors.darkCard,
            onRefresh: () => provider.fetchSupport(refresh: true),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Gym Contact Info
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: AppColors.darkCard,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: AppColors.limeBorder, width: 1.2),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.4),
                          blurRadius: 16,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          contact.gymName.toUpperCase(),
                          style: GoogleFonts.outfit(fontWeight: FontWeight.w900, fontSize: 17, color: AppColors.darkTextPrimary),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          contact.branchName,
                          style: GoogleFonts.plusJakartaSans(color: AppColors.lime, fontWeight: FontWeight.w700, fontSize: 12.5),
                        ),
                        const SizedBox(height: 14),
                        if (contact.phone.isNotEmpty) ...[
                          Row(
                            children: [
                              const Icon(Icons.phone_rounded, size: 18, color: AppColors.lime),
                              const SizedBox(width: 10),
                              Expanded(
                                child: SelectableText(
                                  contact.phone,
                                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, color: AppColors.darkTextPrimary),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                        ],
                        if (contact.email.isNotEmpty) ...[
                          Row(
                            children: [
                              const Icon(Icons.email_rounded, size: 18, color: AppColors.cyan),
                              const SizedBox(width: 10),
                              Expanded(
                                child: SelectableText(
                                  contact.email,
                                  style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700, color: AppColors.darkTextPrimary),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                        ],
                        if (contact.address.isNotEmpty) ...[
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(Icons.location_on_rounded, size: 18, color: AppColors.darkTextMuted),
                              const SizedBox(width: 10),
                              Expanded(
                                child: SelectableText(
                                  contact.address,
                                  style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextSecondary, fontSize: 12.5),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Working Timings
                  Text(
                    'FACILITY TIMINGS',
                    style: GoogleFonts.outfit(
                      color: AppColors.darkTextPrimary,
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      letterSpacing: 0.6,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: AppColors.darkCard,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: AppColors.darkBorder),
                    ),
                    child: Column(
                      children: [
                        _buildTimingRow('Weekdays (Mon - Sat)', timings.weekdays),
                        Container(height: 1, color: AppColors.darkBorder, margin: const EdgeInsets.symmetric(vertical: 10)),
                        _buildTimingRow('Sunday', timings.sunday),
                        Container(height: 1, color: AppColors.darkBorder, margin: const EdgeInsets.symmetric(vertical: 10)),
                        _buildTimingRow('Holidays', timings.holidays),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Gym Policies
                  Text(
                    'FACILITY GUIDELINES & SAFETY',
                    style: GoogleFonts.outfit(
                      color: AppColors.darkTextPrimary,
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      letterSpacing: 0.6,
                    ),
                  ),
                  const SizedBox(height: 12),
                  ...data.policies.map(
                    (p) => Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: AppColors.darkCard,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: AppColors.darkBorder),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(Icons.shield_outlined, size: 18, color: AppColors.lime),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              p,
                              style: GoogleFonts.plusJakartaSans(
                                color: AppColors.darkTextSecondary,
                                fontSize: 13,
                                height: 1.45,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Submit Help Inquiry Ticket
                  Text(
                    'SUBMIT HELP TICKET',
                    style: GoogleFonts.outfit(
                      color: AppColors.darkTextPrimary,
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      letterSpacing: 0.6,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: AppColors.darkCard,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: AppColors.darkBorder),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        DropdownButtonFormField<String>(
                          initialValue: _selectedCategory,
                          dropdownColor: AppColors.darkCardElevated,
                          style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextPrimary),
                          decoration: const InputDecoration(labelText: 'Inquiry Category'),
                          items: _categories
                              .map((c) => DropdownMenuItem(value: c, child: Text(c, style: const TextStyle(fontSize: 13.5))))
                              .toList(),
                          onChanged: (v) {
                            if (v != null) {
                              setState(() {
                                _selectedCategory = v;
                              });
                            }
                          },
                        ),
                        const SizedBox(height: 14),
                        TextField(
                          controller: _subjectController,
                          style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextPrimary),
                          decoration: const InputDecoration(labelText: 'Subject', hintText: 'Brief summary of request'),
                        ),
                        const SizedBox(height: 14),
                        TextField(
                          controller: _messageController,
                          maxLines: 3,
                          style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextPrimary),
                          decoration: const InputDecoration(labelText: 'Message', hintText: 'Detailed description...'),
                        ),
                        const SizedBox(height: 18),
                        BrandedButton(
                          label: 'Submit Ticket',
                          icon: Icons.send_rounded,
                          isLoading: _isSubmitting,
                          onPressed: _submitTicket,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Past Tickets List
                  if (data.tickets.isNotEmpty) ...[
                    Text(
                      'MY PAST REQUESTS',
                      style: GoogleFonts.outfit(
                        color: AppColors.darkTextPrimary,
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                        letterSpacing: 0.6,
                      ),
                    ),
                    const SizedBox(height: 12),
                    ...data.tickets.map(
                      (t) => Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: AppColors.darkCard,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: AppColors.darkBorder),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Text(
                                    t.subject,
                                    style: GoogleFonts.outfit(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 15,
                                      color: AppColors.darkTextPrimary,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                StatusBadge(status: t.status),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${t.category} • ${t.createdAt}',
                              style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextMuted, fontSize: 11.5),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              t.message,
                              style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextSecondary, fontSize: 13),
                            ),
                            if (t.reply != null && t.reply!.isNotEmpty) ...[
                              const SizedBox(height: 12),
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: AppColors.lime.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(10),
                                  border: Border.all(color: AppColors.limeBorder),
                                ),
                                child: Text(
                                  'Staff Reply: ${t.reply}',
                                  style: GoogleFonts.plusJakartaSans(
                                    color: AppColors.lime,
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                  ],

                  const SizedBox(height: 30),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildTimingRow(String day, String timing) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          flex: 2,
          child: Text(
            day,
            style: GoogleFonts.plusJakartaSans(fontSize: 12.5, color: AppColors.darkTextMuted),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          flex: 3,
          child: Text(
            timing,
            textAlign: TextAlign.right,
            style: GoogleFonts.plusJakartaSans(
              fontSize: 12.5,
              fontWeight: FontWeight.w700,
              color: AppColors.darkTextPrimary,
            ),
          ),
        ),
      ],
    );
  }
}
