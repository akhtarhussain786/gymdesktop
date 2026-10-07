import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/member_data_provider.dart';
import '../widgets/empty_state_view.dart';
import '../widgets/error_retry_view.dart';

class NoticesScreen extends StatefulWidget {
  const NoticesScreen({super.key});

  @override
  State<NoticesScreen> createState() => _NoticesScreenState();
}

class _NoticesScreenState extends State<NoticesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MemberDataProvider>().fetchNotices();
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final provider = context.watch<MemberDataProvider>();
    final data = provider.notices;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Gym Announcements'),
      ),
      body: Builder(
        builder: (context) {
          if (provider.loading && data == null) {
            return const Center(child: CircularProgressIndicator());
          }

          if (provider.error != null && data == null) {
            return ErrorRetryView(
              message: provider.error!,
              onRetry: () => provider.fetchNotices(refresh: true),
            );
          }

          if (data == null || data.notices.isEmpty) {
            return const EmptyStateView(
              title: 'No Announcements',
              message: 'There are no active gym announcements at this time.',
              icon: Icons.campaign_outlined,
            );
          }

          return RefreshIndicator(
            onRefresh: () => provider.fetchNotices(refresh: true),
            child: ListView.builder(
              padding: const EdgeInsets.all(20),
              itemCount: data.notices.length,
              itemBuilder: (context, index) {
                final notice = data.notices[index];
                return Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: isDark ? AppColors.darkCard : AppColors.lightCard,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: theme.primaryColor.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Icon(Icons.campaign_rounded, color: theme.primaryColor, size: 22),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  notice.title,
                                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                                ),
                                Text(
                                  notice.formattedDate,
                                  style: TextStyle(color: theme.primaryColor, fontWeight: FontWeight.w600, fontSize: 12),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(
                              notice.message,
                              style: theme.textTheme.bodyMedium?.copyWith(height: 1.45),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
