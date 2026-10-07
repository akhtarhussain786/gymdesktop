import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/member_data_provider.dart';
import '../widgets/empty_state_view.dart';
import '../widgets/error_retry_view.dart';
import '../widgets/stat_card.dart';
import '../widgets/status_badge.dart';

class AttendanceScreen extends StatefulWidget {
  const AttendanceScreen({super.key});

  @override
  State<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends State<AttendanceScreen> {
  DateTime _selectedMonth = DateTime.now();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadAttendance();
    });
  }

  void _loadAttendance() {
    final monthStr = DateFormat('yyyy-MM').format(_selectedMonth);
    context.read<MemberDataProvider>().fetchAttendance(month: monthStr);
  }

  void _changeMonth(int offset) {
    setState(() {
      _selectedMonth = DateTime(_selectedMonth.year, _selectedMonth.month + offset);
    });
    _loadAttendance();
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<MemberDataProvider>();
    final data = provider.attendance;

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: Navigator.canPop(context)
          ? AppBar(
              backgroundColor: AppColors.bgDeep(context),
              elevation: 0,
              title: Text(
                'ATTENDANCE & SESSIONS',
                style: GoogleFonts.outfit(
                  color: AppColors.textPrimary(context),
                  fontWeight: FontWeight.w800,
                  fontSize: 18,
                  letterSpacing: 0.5,
                ),
              ),
            )
          : null,
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
              onRetry: _loadAttendance,
            );
          }

          if (data == null) {
            return const EmptyStateView(
              title: 'No Records',
              message: 'No attendance logs found.',
              icon: Icons.calendar_month_outlined,
            );
          }

          final summary = data.summary;

          return RefreshIndicator(
            color: AppColors.lime,
            backgroundColor: AppColors.card(context),
            onRefresh: () async => _loadAttendance(),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Today's Live Check-in / Check-out Card
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: AppColors.card(context),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: AppColors.limeBorder, width: 1.2),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: Theme.of(context).brightness == Brightness.dark ? 0.4 : 0.06),
                          blurRadius: 16,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  "TODAY'S SESSION",
                                  style: GoogleFonts.outfit(
                                    color: AppColors.textPrimary(context),
                                    fontWeight: FontWeight.w800,
                                    fontSize: 16,
                                    letterSpacing: 0.5,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  DateFormat('EEEE, d MMMM yyyy').format(DateTime.now()),
                                  style: GoogleFonts.plusJakartaSans(
                                    fontSize: 12,
                                    color: AppColors.textMuted(context),
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                            StatusBadge(
                              status: summary.todayStatus,
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),

                        // Check-in / Check-out Timings
                        if (summary.todayCheckIn != null) ...[
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                            decoration: BoxDecoration(
                              color: AppColors.bgDeep(context),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: AppColors.border(context)),
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceAround,
                              children: [
                                Row(
                                  children: [
                                    const Icon(Icons.login_rounded, size: 16, color: AppColors.success),
                                    const SizedBox(width: 8),
                                    Text(
                                      'In: ${summary.todayCheckIn}',
                                      style: GoogleFonts.plusJakartaSans(
                                        color: AppColors.textPrimary(context),
                                        fontWeight: FontWeight.w800,
                                        fontSize: 13.5,
                                      ),
                                    ),
                                  ],
                                ),
                                Container(width: 1, height: 16, color: AppColors.border(context)),
                                Row(
                                  children: [
                                    const Icon(Icons.logout_rounded, size: 16, color: AppColors.warning),
                                    const SizedBox(width: 8),
                                    Text(
                                      summary.todayCheckOut != null ? 'Out: ${summary.todayCheckOut}' : 'Out: Active',
                                      style: GoogleFonts.plusJakartaSans(
                                        fontWeight: FontWeight.w800,
                                        fontSize: 13.5,
                                        color: summary.todayCheckOut != null ? AppColors.textPrimary(context) : AppColors.warning,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 16),
                        ],

                        // Interactive Action Buttons
                        if (summary.todayCheckIn == null) ...[
                          SizedBox(
                            width: double.infinity,
                            height: 46,
                            child: ElevatedButton.icon(
                              onPressed: provider.loading
                                  ? null
                                  : () async {
                                      final msg = await provider.checkIn();
                                      if (context.mounted && msg != null) {
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(
                                            content: Text(msg),
                                            backgroundColor: AppColors.success,
                                            behavior: SnackBarBehavior.floating,
                                          ),
                                        );
                                      }
                                    },
                              icon: const Icon(Icons.touch_app_rounded, color: Color(0xFF05080D), size: 18),
                              label: Text(
                                provider.loading ? 'Recording Check-In...' : 'Check In Now',
                                style: GoogleFonts.plusJakartaSans(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 14.5,
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
                        ] else if (summary.todayCheckOut == null) ...[
                          SizedBox(
                            width: double.infinity,
                            height: 46,
                            child: ElevatedButton.icon(
                              onPressed: provider.loading
                                  ? null
                                  : () async {
                                      final msg = await provider.checkOut();
                                      if (context.mounted && msg != null) {
                                        ScaffoldMessenger.of(context).showSnackBar(
                                          SnackBar(
                                            content: Text(msg),
                                            backgroundColor: AppColors.warning,
                                            behavior: SnackBarBehavior.floating,
                                          ),
                                        );
                                      }
                                    },
                              icon: const Icon(Icons.exit_to_app_rounded, color: Colors.white, size: 18),
                              label: Text(
                                provider.loading ? 'Recording Check-Out...' : 'Check Out Now',
                                style: GoogleFonts.plusJakartaSans(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 14.5,
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
                                const Icon(Icons.check_circle_rounded, color: AppColors.success, size: 18),
                                const SizedBox(width: 8),
                                Text(
                                  'Daily Workout Completed',
                                  style: GoogleFonts.plusJakartaSans(
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.success,
                                    fontSize: 13.5,
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

                  // Stats Summary Row
                  Row(
                    children: [
                      Expanded(
                        child: StatCard(
                          title: 'This Month',
                          value: '${summary.monthSessions}',
                          subtitle: '${DateFormat('MMMM yyyy').format(_selectedMonth)} visits',
                          icon: Icons.calendar_today_rounded,
                          color: AppColors.lime,
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: StatCard(
                          title: 'Total Lifetime',
                          value: '${summary.totalLifetimeSessions}',
                          subtitle: 'All-time gym visits',
                          icon: Icons.local_fire_department_rounded,
                          color: AppColors.cyan,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),

                  // Month Navigation Bar
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    decoration: BoxDecoration(
                      color: AppColors.card(context),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: AppColors.border(context)),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        IconButton(
                          icon: Icon(Icons.chevron_left_rounded, color: AppColors.textPrimary(context)),
                          onPressed: () => _changeMonth(-1),
                        ),
                        Text(
                          DateFormat('MMMM yyyy').format(_selectedMonth).toUpperCase(),
                          style: GoogleFonts.outfit(
                            color: AppColors.textPrimary(context),
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                            letterSpacing: 0.5,
                          ),
                        ),
                        IconButton(
                          icon: Icon(Icons.chevron_right_rounded, color: AppColors.textPrimary(context)),
                          onPressed: () => _changeMonth(1),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),

                  // Attendance History Log
                  Text(
                    'CHECK-IN LOGS',
                    style: GoogleFonts.outfit(
                      color: AppColors.textPrimary(context),
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      letterSpacing: 0.6,
                    ),
                  ),
                  const SizedBox(height: 12),

                  if (data.history.isEmpty) ...[
                    const SizedBox(height: 30),
                    Center(
                      child: Text(
                        'No check-in records logged for this month.',
                        style: GoogleFonts.plusJakartaSans(color: AppColors.textMuted(context)),
                      ),
                    ),
                  ] else ...[
                    ...data.history.map(
                      (record) => Container(
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: AppColors.card(context),
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: AppColors.border(context)),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Row(
                                children: [
                                  Container(
                                    padding: const EdgeInsets.all(10),
                                    decoration: BoxDecoration(
                                      color: AppColors.lime.withValues(alpha: 0.12),
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: const Icon(Icons.check_circle_rounded, color: AppColors.lime, size: 20),
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          record.date,
                                          style: GoogleFonts.outfit(
                                            color: AppColors.textPrimary(context),
                                            fontWeight: FontWeight.w800,
                                            fontSize: 14.5,
                                          ),
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                        const SizedBox(height: 2),
                                        Text(
                                          'In: ${record.checkInTime} ${record.checkOutTime != null ? "• Out: ${record.checkOutTime}" : ""}',
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
                            StatusBadge(status: record.status),
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
}
