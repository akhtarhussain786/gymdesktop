import 'package:flutter/material.dart';
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
        const SnackBar(content: Text('Please provide both subject and message.')),
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
        const SnackBar(content: Text('Help request submitted to gym administration!')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final provider = context.watch<MemberDataProvider>();
    final data = provider.support;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Support & Help Desk'),
      ),
      body: Builder(
        builder: (context) {
          if (provider.loading && data == null) {
            return const Center(child: CircularProgressIndicator());
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
                      color: isDark ? AppColors.darkCard : AppColors.lightCard,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(contact.gymName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                        const SizedBox(height: 4),
                        Text(contact.branchName, style: TextStyle(color: theme.primaryColor, fontWeight: FontWeight.w600, fontSize: 12.5)),
                        const SizedBox(height: 14),
                        if (contact.phone.isNotEmpty) ...[
                          Row(
                            children: [
                              const Icon(Icons.phone_rounded, size: 18, color: AppColors.success),
                              const SizedBox(width: 10),
                              SelectableText(contact.phone, style: const TextStyle(fontWeight: FontWeight.w600)),
                            ],
                          ),
                          const SizedBox(height: 8),
                        ],
                        if (contact.email.isNotEmpty) ...[
                          Row(
                            children: [
                              const Icon(Icons.email_rounded, size: 18, color: AppColors.info),
                              const SizedBox(width: 10),
                              SelectableText(contact.email, style: const TextStyle(fontWeight: FontWeight.w600)),
                            ],
                          ),
                          const SizedBox(height: 8),
                        ],
                        if (contact.address.isNotEmpty) ...[
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Icon(Icons.location_on_rounded, size: 18, color: Colors.grey),
                              const SizedBox(width: 10),
                              Expanded(child: SelectableText(contact.address)),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Working Timings
                  Text('Facility Timings', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: isDark ? AppColors.darkCard : AppColors.lightCard,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                    ),
                    child: Column(
                      children: [
                        _buildTimingRow('Weekdays (Mon - Sat)', timings.weekdays),
                        const Divider(height: 16),
                        _buildTimingRow('Sunday', timings.sunday),
                        const Divider(height: 16),
                        _buildTimingRow('Holidays', timings.holidays),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Gym Policies
                  Text('Gym Policies & Safety Guidelines', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 10),
                  ...data.policies.map(
                    (p) => Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: isDark ? AppColors.darkCard : AppColors.lightCard,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.shield_outlined, size: 18, color: theme.primaryColor),
                          const SizedBox(width: 10),
                          Expanded(child: Text(p, style: theme.textTheme.bodyMedium?.copyWith(fontSize: 13))),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Submit Help Inquiry Ticket
                  Text('Submit Help Inquiry', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: isDark ? AppColors.darkCard : AppColors.lightCard,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        DropdownButtonFormField<String>(
                          initialValue: _selectedCategory,
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
                        const SizedBox(height: 12),
                        TextField(
                          controller: _subjectController,
                          decoration: const InputDecoration(labelText: 'Subject', hintText: 'Brief summary of your request'),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _messageController,
                          maxLines: 3,
                          decoration: const InputDecoration(labelText: 'Message', hintText: 'Detailed description...'),
                        ),
                        const SizedBox(height: 16),
                        BrandedButton(
                          text: 'Submit Ticket',
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
                    Text('My Past Requests', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 10),
                    ...data.tickets.map(
                      (t) => Container(
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: isDark ? AppColors.darkCard : AppColors.lightCard,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(t.subject, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                                StatusBadge(status: t.status, small: true),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text('${t.category} • ${t.createdAt}', style: const TextStyle(color: Colors.grey, fontSize: 11.5)),
                            const SizedBox(height: 8),
                            Text(t.message, style: theme.textTheme.bodyMedium),
                            if (t.reply != null && t.reply!.isNotEmpty) ...[
                              const SizedBox(height: 10),
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: theme.primaryColor.withValues(alpha: 0.1),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Text('Staff Reply: ${t.reply}', style: TextStyle(color: theme.primaryColor, fontSize: 12.5, fontWeight: FontWeight.w600)),
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
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(day, style: const TextStyle(fontSize: 13, color: Colors.grey)),
        Text(timing, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
      ],
    );
  }
}
