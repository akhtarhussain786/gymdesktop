import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
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
    final provider = context.watch<MemberDataProvider>();
    final data = provider.notices;

    return Scaffold(
      backgroundColor: AppColors.darkBg,
      appBar: AppBar(
        backgroundColor: AppColors.darkBgDeep,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        title: Text(
          'ANNOUNCEMENTS',
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
            color: AppColors.lime,
            backgroundColor: AppColors.darkCard,
            onRefresh: () => provider.fetchNotices(refresh: true),
            child: ListView.builder(
              padding: const EdgeInsets.all(20),
              itemCount: data.notices.length,
              itemBuilder: (context, index) {
                final notice = data.notices[index];
                return Container(
                  margin: const EdgeInsets.only(bottom: 14),
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: AppColors.darkCard,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: AppColors.darkBorder),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.3),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: AppColors.lime.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.limeBorder),
                        ),
                        child: const Icon(Icons.campaign_rounded, color: AppColors.lime, size: 22),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Text(
                                    notice.title,
                                    style: GoogleFonts.outfit(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 16,
                                      color: AppColors.darkTextPrimary,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Text(
                                  notice.formattedDate,
                                  style: GoogleFonts.plusJakartaSans(
                                    color: AppColors.lime,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 11.5,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            Text(
                              notice.message,
                              style: GoogleFonts.plusJakartaSans(
                                color: AppColors.darkTextSecondary,
                                fontSize: 13,
                                height: 1.5,
                              ),
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
