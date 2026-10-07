import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/dashboard_provider.dart';
import '../widgets/empty_state_view.dart';
import '../widgets/error_retry_view.dart';
import '../widgets/loading_skeleton.dart';
import '../widgets/stat_card.dart';
import '../widgets/status_badge.dart';
import 'diet_screen.dart';
import 'notices_screen.dart';
import 'qr_card_dialog.dart';
import 'trainer_screen.dart';
import 'workouts_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<DashboardProvider>().fetchDashboard();
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final dashboardProvider = context.watch<DashboardProvider>();

    if (dashboardProvider.isLoading && dashboardProvider.dashboardData == null) {
      return const DashboardSkeleton();
    }

    if (dashboardProvider.errorMessage != null && dashboardProvider.dashboardData == null) {
      return ErrorRetryView(
        message: dashboardProvider.errorMessage!,
        onRetry: () => dashboardProvider.fetchDashboard(refresh: true),
      );
    }

    final data = dashboardProvider.dashboardData;
    if (data == null) {
      return const EmptyStateView(
        title: 'No Data Available',
        message: 'Could not load your member dashboard. Pull down to retry.',
        icon: Icons.dashboard_outlined,
      );
    }

    final member = data.member;
    final membership = data.membership;
    final attendance = data.attendance;
    final payments = data.payments;
    final workout = data.workout;
    final diet = data.diet;
    final trainer = data.trainer;

    return RefreshIndicator(
      onRefresh: () => dashboardProvider.fetchDashboard(refresh: true),
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // 1. Member Profile & Digital ID Pass Header
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    theme.primaryColor,
                    theme.primaryColor.withValues(alpha: 0.85),
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(20),
                boxShadow: [
                  BoxShadow(
                    color: theme.primaryColor.withValues(alpha: 0.3),
                    blurRadius: 16,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Column(
                children: [
                  Row(
                    children: [
                      // Avatar
                      CircleAvatar(
                        radius: 28,
                        backgroundColor: Colors.white.withValues(alpha: 0.2),
                        child: Text(
                          member.fullname.isNotEmpty ? member.fullname[0].toUpperCase() : 'M',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 24,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              member.fullname,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Member ID: ${member.memberId} • ${data.gym.gymCode}',
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.85),
                                fontSize: 12.5,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),
                      // QR Card Button
                      IconButton.filled(
                        style: IconButton.styleFrom(
                          backgroundColor: Colors.white.withValues(alpha: 0.25),
                          foregroundColor: Colors.white,
                        ),
                        icon: const Icon(Icons.qr_code_2_rounded, size: 24),
                        tooltip: 'View QR Pass',
                        onPressed: () {
                          showDialog(
                            context: context,
                            builder: (_) => QrCardDialog(qrPass: data.qrPass, gym: data.gym, member: member),
                          );
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  const Divider(color: Colors.white24, height: 1),
                  const SizedBox(height: 14),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            membership.planName,
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                              fontSize: 13.5,
                            ),
                          ),
                          Text(
                            'Expires: ${membership.expiryDate}',
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.8),
                              fontSize: 11.5,
                            ),
                          ),
                        ],
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: membership.daysRemaining > 5
                              ? AppColors.success.withValues(alpha: 0.3)
                              : AppColors.warning.withValues(alpha: 0.3),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.white38),
                        ),
                        child: Text(
                          '${membership.daysRemaining} Days Left',
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w800,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // 2. Expiry Warning Alert (if expiring soon or expired)
            if (membership.isExpiringSoon) ...[
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.warning.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.warning),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.hourglass_bottom_rounded, color: AppColors.warning),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Your membership expires in ${membership.daysRemaining} days. Contact reception to renew.',
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
            ],

            // 3. Quick Stats Grid
            Row(
              children: [
                Expanded(
                  child: StatCard(
                    title: "Today's Status",
                    value: attendance.todayStatus,
                    subtitle: attendance.todayCheckIn != null ? 'In: ${attendance.todayCheckIn}' : 'Not checked in',
                    icon: Icons.check_circle_outline_rounded,
                    color: attendance.todayCheckIn != null ? AppColors.success : Colors.grey,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: StatCard(
                    title: 'Total Sessions',
                    value: '${attendance.totalLifetimeSessions}',
                    subtitle: 'Lifetime workouts',
                    icon: Icons.local_fire_department_rounded,
                    color: theme.colorScheme.secondary,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: StatCard(
                    title: 'Current Weight',
                    value: '${member.currentWeight} kg',
                    subtitle: 'Start: ${member.initialWeight} kg (${member.bodyType})',
                    icon: Icons.monitor_weight_outlined,
                    color: theme.primaryColor,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: StatCard(
                    title: 'Pending Dues',
                    value: '${data.gym.currency}${payments.outstandingDue.toStringAsFixed(0)}',
                    subtitle: payments.outstandingDue > 0 ? 'Payment pending' : 'All clear',
                    icon: Icons.account_balance_wallet_outlined,
                    color: payments.outstandingDue > 0 ? AppColors.danger : AppColors.success,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),

            // 4. Assigned Workout & Diet Section
            Text(
              'My Regimen',
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
                fontSize: 17,
              ),
            ),
            const SizedBox(height: 12),

            // Workout Routine Card
            if (workout != null) ...[
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: isDark ? AppColors.darkCard : AppColors.lightCard,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            Icon(Icons.sports_gymnastics_rounded, color: theme.primaryColor, size: 22),
                            const SizedBox(width: 8),
                            Text(
                              workout.name,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w700,
                                fontSize: 15,
                              ),
                            ),
                          ],
                        ),
                        StatusBadge(status: workout.level, small: true),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Goal: ${workout.goal} • Prescribed by ${workout.trainerName}',
                      style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12),
                    ),
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: isDark ? AppColors.darkBg : AppColors.lightCardElevated,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        workout.scheduleText,
                        style: const TextStyle(fontFamily: 'monospace', fontSize: 12, height: 1.5),
                        maxLines: 4,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        onPressed: () {
                          Navigator.push(context, MaterialPageRoute(builder: (_) => const WorkoutsScreen()));
                        },
                        child: const Text('View Full Workout & Checklist →'),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
            ],

            // Diet Card
            if (diet != null) ...[
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: isDark ? AppColors.darkCard : AppColors.lightCard,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            Icon(Icons.restaurant_rounded, color: theme.colorScheme.secondary, size: 22),
                            const SizedBox(width: 8),
                            Text(
                              diet.name,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w700,
                                fontSize: 15,
                              ),
                            ),
                          ],
                        ),
                        StatusBadge(status: '${diet.calories} kcal', customColor: theme.colorScheme.secondary, small: true),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Target: ${diet.target}',
                      style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12),
                    ),
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: isDark ? AppColors.darkBg : AppColors.lightCardElevated,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        diet.mealsText,
                        style: const TextStyle(fontFamily: 'monospace', fontSize: 12, height: 1.5),
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        onPressed: () {
                          Navigator.push(context, MaterialPageRoute(builder: (_) => const DietScreen()));
                        },
                        child: const Text('View Full Nutrition Schedule →'),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
            ],

            // 5. Assigned Trainer Quick Card
            if (trainer != null) ...[
              Text(
                'My Personal Trainer',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  fontSize: 17,
                ),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: isDark ? AppColors.darkCard : AppColors.lightCard,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                ),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 24,
                      backgroundColor: theme.primaryColor.withValues(alpha: 0.15),
                      child: Icon(Icons.sports_rounded, color: theme.primaryColor),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            trainer.name,
                            style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                          ),
                          Text(
                            trainer.designation,
                            style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.info_outline_rounded),
                      onPressed: () {
                        Navigator.push(context, MaterialPageRoute(builder: (_) => const TrainerScreen()));
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
            ],

            // 6. Gym Announcements
            if (data.announcements.isNotEmpty) ...[
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Gym Notices',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                      fontSize: 17,
                    ),
                  ),
                  TextButton(
                    onPressed: () {
                      Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen()));
                    },
                    child: const Text('See All'),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              ...data.announcements.take(2).map(
                    (a) => Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: isDark ? AppColors.darkCard : AppColors.lightCard,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.campaign_rounded, color: theme.primaryColor, size: 22),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  a.date,
                                  style: TextStyle(color: theme.primaryColor, fontSize: 11, fontWeight: FontWeight.w700),
                                ),
                                const SizedBox(height: 3),
                                Text(a.message, style: theme.textTheme.bodyMedium),
                              ],
                            ),
                          ),
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
  }
}
