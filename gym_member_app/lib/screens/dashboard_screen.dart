import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/dashboard_provider.dart';
import '../providers/member_data_provider.dart';
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
      color: AppColors.lime,
      backgroundColor: AppColors.darkCard,
      onRefresh: () => dashboardProvider.fetchDashboard(refresh: true),
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // 0. High-Impact Fitness Hero Banner
            Container(
              width: double.infinity,
              height: 175,
              margin: const EdgeInsets.only(bottom: 20),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(22),
                border: Border.all(
                  color: AppColors.lime.withValues(alpha: 0.4),
                  width: 1.5,
                ),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.lime.withValues(alpha: 0.12),
                    blurRadius: 20,
                    offset: const Offset(0, 8),
                  ),
                ],
                image: const DecorationImage(
                  image: AssetImage('assets/images/fitness_hero.png'),
                  fit: BoxFit.cover,
                  alignment: Alignment.centerRight,
                ),
              ),
              child: Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(21),
                  gradient: LinearGradient(
                    begin: Alignment.centerLeft,
                    end: Alignment.centerRight,
                    colors: [
                      const Color(0xFF090D14).withValues(alpha: 0.94),
                      const Color(0xFF090D14).withValues(alpha: 0.75),
                      Colors.transparent,
                    ],
                    stops: const [0.0, 0.55, 1.0],
                  ),
                ),
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Row(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.asset(
                            'assets/images/app_logo.png',
                            width: 26,
                            height: 26,
                            fit: BoxFit.contain,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          'FITISIFY GYM OS',
                          style: GoogleFonts.outfit(
                            color: AppColors.lime,
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 1.5,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'PUSH YOUR LIMITS',
                      style: GoogleFonts.outfit(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 0.5,
                      ),
                    ),
                    const SizedBox(height: 4),
                    ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 220),
                      child: Text(
                        'Unlock peak performance with your custom workouts & diet plan.',
                        style: GoogleFonts.plusJakartaSans(
                          color: const Color(0xFFCBD5E1),
                          fontSize: 11.5,
                          fontWeight: FontWeight.w500,
                          height: 1.3,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // 1. Digital Athlete ID Pass Card (Dark-Tech Glassmorphism)
            Container(
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                color: AppColors.card(context),
                borderRadius: BorderRadius.circular(22),
                border: Border.all(
                  color: AppColors.limeBorder,
                  width: 1.5,
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: Theme.of(context).brightness == Brightness.dark ? 0.4 : 0.08),
                    blurRadius: 20,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: Column(
                children: [
                  Row(
                    children: [
                      // Avatar with Neon Border
                      Container(
                        padding: const EdgeInsets.all(2),
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(color: AppColors.lime, width: 1.8),
                        ),
                        child: CircleAvatar(
                          radius: 26,
                          backgroundColor: AppColors.cardElevated(context),
                          child: Text(
                            member.fullname.isNotEmpty ? member.fullname[0].toUpperCase() : 'A',
                            style: GoogleFonts.outfit(
                              color: AppColors.primaryText(context),
                              fontSize: 22,
                              fontWeight: FontWeight.w900,
                            ),
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
                              style: GoogleFonts.outfit(
                                color: AppColors.textPrimary(context),
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.3,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 2),
                            Row(
                              children: [
                                Text(
                                  'ID: ${member.memberId}',
                                  style: GoogleFonts.plusJakartaSans(
                                    color: AppColors.textSecondary(context),
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                                const SizedBox(width: 6),
                                Container(
                                  width: 3,
                                  height: 3,
                                  decoration: BoxDecoration(
                                    color: AppColors.textMuted(context),
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                const SizedBox(width: 6),
                                Text(
                                  data.gym.gymCode,
                                  style: GoogleFonts.plusJakartaSans(
                                    color: AppColors.primaryText(context),
                                    fontSize: 12,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      // Digital QR Pass Quick Trigger Button
                      IconButton(
                        style: IconButton.styleFrom(
                          backgroundColor: AppColors.lime.withValues(alpha: 0.15),
                          side: const BorderSide(color: AppColors.limeBorder, width: 1),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          padding: const EdgeInsets.all(12),
                        ),
                        icon: const Icon(Icons.qr_code_rounded, color: AppColors.lime, size: 22),
                        tooltip: 'View Digital Pass',
                        onPressed: () {
                          showDialog(
                            context: context,
                            builder: (_) => QrCardDialog(qrPass: data.qrPass, gym: data.gym, member: member),
                          );
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  Container(
                    height: 1,
                    color: AppColors.border(context),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              membership.planName.toUpperCase(),
                              style: GoogleFonts.outfit(
                                color: AppColors.textPrimary(context),
                                fontWeight: FontWeight.w800,
                                fontSize: 14,
                                letterSpacing: 0.5,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Renews: ${membership.expiryDate}',
                              style: GoogleFonts.plusJakartaSans(
                                color: AppColors.textMuted(context),
                                fontSize: 11.5,
                                fontWeight: FontWeight.w600,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      StatusBadge(
                        status: membership.daysRemaining > 5
                            ? '${membership.daysRemaining} DAYS LEFT'
                            : '${membership.daysRemaining} DAYS (EXPIRING)',
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),

            // 2. Expiry Warning Alert Banner (if at risk)
            if (membership.isExpiringSoon) ...[
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.warning.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.warning.withValues(alpha: 0.4)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.hourglass_bottom_rounded, color: AppColors.warning, size: 20),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Your membership expires in ${membership.daysRemaining} days. Renew now to avoid interruption.',
                        style: GoogleFonts.plusJakartaSans(
                          fontWeight: FontWeight.w600,
                          fontSize: 12.5,
                          color: const Color(0xFFFDE68A),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
            ],

            // 3. Today's Attendance Check-In Card
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: AppColors.card(context),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: AppColors.border(context), width: 1),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: Theme.of(context).brightness == Brightness.dark ? 0.3 : 0.05),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Column(
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: (attendance.todayCheckIn != null ? AppColors.success : AppColors.lime).withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(
                                  color: (attendance.todayCheckIn != null ? AppColors.success : AppColors.lime).withValues(alpha: 0.3),
                                ),
                              ),
                              child: Icon(
                                Icons.fact_check_rounded,
                                color: attendance.todayCheckIn != null ? AppColors.success : AppColors.lime,
                                size: 20,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    "Today's Attendance",
                                    style: GoogleFonts.outfit(
                                      color: AppColors.textPrimary(context),
                                      fontWeight: FontWeight.w800,
                                      fontSize: 15,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  Text(
                                    attendance.todayCheckIn != null
                                        ? 'In: ${attendance.todayCheckIn} ${attendance.todayCheckOut != null ? "• Out: ${attendance.todayCheckOut}" : ""}'
                                        : 'Not checked in today',
                                    style: GoogleFonts.plusJakartaSans(
                                      color: AppColors.textSecondary(context),
                                      fontSize: 12,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      StatusBadge(
                        status: attendance.todayStatus,
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  if (attendance.todayCheckIn == null) ...[
                    SizedBox(
                      width: double.infinity,
                      height: 44,
                      child: ElevatedButton.icon(
                        onPressed: () async {
                          final msg = await context.read<MemberDataProvider>().checkIn();
                          if (context.mounted) {
                            await context.read<DashboardProvider>().fetchDashboard(refresh: true);
                            if (msg != null && context.mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(msg),
                                  backgroundColor: AppColors.success,
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                            }
                          }
                        },
                        icon: const Icon(Icons.touch_app_rounded, color: Color(0xFF05080D), size: 18),
                        label: Text(
                          'Self Check-In',
                          style: GoogleFonts.plusJakartaSans(
                            fontWeight: FontWeight.w800,
                            fontSize: 14,
                            color: const Color(0xFF05080D),
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.lime,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
                          elevation: 0,
                        ),
                      ),
                    ),
                  ] else if (attendance.todayCheckOut == null) ...[
                    SizedBox(
                      width: double.infinity,
                      height: 44,
                      child: ElevatedButton.icon(
                        onPressed: () async {
                          final msg = await context.read<MemberDataProvider>().checkOut();
                          if (context.mounted) {
                            await context.read<DashboardProvider>().fetchDashboard(refresh: true);
                            if (msg != null && context.mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(
                                  content: Text(msg),
                                  backgroundColor: AppColors.warning,
                                  behavior: SnackBarBehavior.floating,
                                ),
                              );
                            }
                          }
                        },
                        icon: const Icon(Icons.exit_to_app_rounded, color: Colors.white, size: 18),
                        label: Text(
                          'Check Out Now',
                          style: GoogleFonts.plusJakartaSans(
                            fontWeight: FontWeight.w800,
                            fontSize: 14,
                            color: Colors.white,
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.warning,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
                          elevation: 0,
                        ),
                      ),
                    ),
                  ] else ...[
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      decoration: BoxDecoration(
                        color: AppColors.success.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.success.withValues(alpha: 0.3)),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(Icons.check_circle_rounded, color: AppColors.success, size: 16),
                          const SizedBox(width: 8),
                          Text(
                            'Workout Session Recorded',
                            style: GoogleFonts.plusJakartaSans(
                              fontWeight: FontWeight.w800,
                              color: AppColors.success,
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 18),

            // 4. Quick Live Stats KPI Grid
            Row(
              children: [
                Expanded(
                  child: StatCard(
                    title: 'Gym Visits',
                    value: '${attendance.totalLifetimeSessions}',
                    subtitle: 'Lifetime check-ins',
                    icon: Icons.local_fire_department_rounded,
                    color: AppColors.lime,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: StatCard(
                    title: 'Body Weight',
                    value: '${member.currentWeight} kg',
                    subtitle: 'Start: ${member.initialWeight} kg',
                    icon: Icons.monitor_weight_outlined,
                    color: AppColors.cyan,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: StatCard(
                    title: 'Body Type',
                    value: member.bodyType.isNotEmpty ? member.bodyType : 'Athletic',
                    subtitle: 'Current physique',
                    icon: Icons.accessibility_new_rounded,
                    color: AppColors.defaultAccent,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: StatCard(
                    title: 'Dues Balance',
                    value: '${data.gym.currency}${payments.outstandingDue.toStringAsFixed(0)}',
                    subtitle: payments.outstandingDue > 0 ? 'Payment Due' : 'All Clear ✓',
                    icon: Icons.account_balance_wallet_outlined,
                    color: payments.outstandingDue > 0 ? AppColors.danger : AppColors.success,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 28),

            // 5. Workout Routine Card
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'TODAY\'S WORKOUT',
                  style: GoogleFonts.outfit(
                    color: AppColors.textPrimary(context),
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                    letterSpacing: 0.6,
                  ),
                ),
                TextButton(
                  onPressed: () {
                    Navigator.push(context, MaterialPageRoute(builder: (_) => const WorkoutsScreen()));
                  },
                  child: Text(
                    'Full Split →',
                    style: GoogleFonts.plusJakartaSans(
                      color: AppColors.primaryText(context),
                      fontWeight: FontWeight.w700,
                      fontSize: 13,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),

            if (workout != null) ...[
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: AppColors.card(context),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: AppColors.border(context), width: 1),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.fitness_center_rounded, color: AppColors.lime, size: 20),
                            const SizedBox(width: 10),
                            Text(
                              workout.name,
                              style: GoogleFonts.outfit(
                                color: AppColors.textPrimary(context),
                                fontWeight: FontWeight.w800,
                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),
                        StatusBadge(status: workout.level),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Goal: ${workout.goal} • Coach: ${workout.trainerName}',
                      style: GoogleFonts.plusJakartaSans(
                        color: AppColors.textSecondary(context),
                        fontSize: 12.5,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: AppColors.bgDeep(context),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.border(context)),
                      ),
                      child: Text(
                        workout.scheduleText,
                        style: GoogleFonts.plusJakartaSans(
                          color: AppColors.textSecondary(context),
                          fontSize: 12.5,
                          height: 1.5,
                        ),
                        maxLines: 4,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
            ],

            // 6. Nutrition & Diet Card
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'NUTRITION & MACROS',
                  style: GoogleFonts.outfit(
                    color: AppColors.textPrimary(context),
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                    letterSpacing: 0.6,
                  ),
                ),
                TextButton(
                  onPressed: () {
                    Navigator.push(context, MaterialPageRoute(builder: (_) => const DietScreen()));
                  },
                  child: Text(
                    'Meal Plan →',
                    style: GoogleFonts.plusJakartaSans(
                      color: AppColors.accentText(context),
                      fontWeight: FontWeight.w700,
                      fontSize: 13,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),

            if (diet != null) ...[
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: AppColors.card(context),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: AppColors.border(context), width: 1),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.restaurant_rounded, color: AppColors.cyan, size: 20),
                            const SizedBox(width: 10),
                            Text(
                              diet.name,
                              style: GoogleFonts.outfit(
                                color: AppColors.textPrimary(context),
                                fontWeight: FontWeight.w800,
                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),
                        StatusBadge(status: '${diet.calories} kcal'),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Target: ${diet.target}',
                      style: GoogleFonts.plusJakartaSans(
                        color: AppColors.textSecondary(context),
                        fontSize: 12.5,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: AppColors.bgDeep(context),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.border(context)),
                      ),
                      child: Text(
                        diet.mealsText,
                        style: GoogleFonts.plusJakartaSans(
                          color: AppColors.textSecondary(context),
                          fontSize: 12.5,
                          height: 1.5,
                        ),
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
            ],

            // 7. Assigned Trainer Card
            if (trainer != null) ...[
              Text(
                'PERSONAL COACH',
                style: GoogleFonts.outfit(
                  color: AppColors.darkTextPrimary,
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                  letterSpacing: 0.6,
                ),
              ),
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: AppColors.card(context),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: AppColors.border(context), width: 1),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(
                        color: AppColors.lime.withValues(alpha: 0.12),
                        shape: BoxShape.circle,
                        border: Border.all(color: AppColors.limeBorder),
                      ),
                      child: const Icon(Icons.sports_rounded, color: AppColors.lime, size: 22),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            trainer.name,
                            style: GoogleFonts.outfit(
                              color: AppColors.textPrimary(context),
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
                            ),
                          ),
                          Text(
                            trainer.designation,
                            style: GoogleFonts.plusJakartaSans(
                              color: AppColors.textSecondary(context),
                              fontSize: 12.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      icon: Icon(Icons.arrow_forward_ios_rounded, color: AppColors.textMuted(context), size: 16),
                      onPressed: () {
                        Navigator.push(context, MaterialPageRoute(builder: (_) => const TrainerScreen()));
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
            ],

            // 8. Gym Notices
            if (data.announcements.isNotEmpty) ...[
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'GYM ANNOUNCEMENTS',
                    style: GoogleFonts.outfit(
                      color: AppColors.textPrimary(context),
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      letterSpacing: 0.6,
                    ),
                  ),
                  TextButton(
                    onPressed: () {
                      Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen()));
                    },
                    child: Text(
                      'View All →',
                      style: GoogleFonts.plusJakartaSans(
                        color: AppColors.lime,
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              ...data.announcements.take(2).map(
                    (a) => Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.card(context),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppColors.border(context)),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: AppColors.lime.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: const Icon(Icons.campaign_rounded, color: AppColors.lime, size: 20),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  a.date,
                                  style: GoogleFonts.plusJakartaSans(
                                    color: AppColors.primaryText(context),
                                    fontSize: 11,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  a.message,
                                  style: GoogleFonts.plusJakartaSans(
                                    color: AppColors.textSecondary(context),
                                    fontSize: 13,
                                    height: 1.45,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
            ],

            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }
}
