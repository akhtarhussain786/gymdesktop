import 'package:flutter/material.dart';
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
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final provider = context.watch<MemberDataProvider>();
    final data = provider.attendance;

    return Scaffold(
      body: Builder(
        builder: (context) {
          if (provider.loading && data == null) {
            return const Center(child: CircularProgressIndicator());
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
            onRefresh: () async => _loadAttendance(),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Stats Summary Row
                  Row(
                    children: [
                      Expanded(
                        child: StatCard(
                          title: 'This Month',
                          value: '${summary.monthSessions}',
                          subtitle: '${DateFormat('MMMM yyyy').format(_selectedMonth)} visits',
                          icon: Icons.calendar_today_rounded,
                          color: theme.primaryColor,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: StatCard(
                          title: 'Total Lifetime',
                          value: '${summary.totalLifetimeSessions}',
                          subtitle: 'All-time gym visits',
                          icon: Icons.local_fire_department_rounded,
                          color: theme.colorScheme.secondary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Month Navigation Bar
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    decoration: BoxDecoration(
                      color: isDark ? AppColors.darkCard : AppColors.lightCard,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        IconButton(
                          icon: const Icon(Icons.chevron_left_rounded),
                          onPressed: () => _changeMonth(-1),
                        ),
                        Text(
                          DateFormat('MMMM yyyy').format(_selectedMonth),
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                            fontSize: 15,
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.chevron_right_rounded),
                          onPressed: () => _changeMonth(1),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Attendance History Log
                  Text(
                    'Check-In Logs',
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 12),

                  if (data.history.isEmpty) ...[
                    const SizedBox(height: 40),
                    const Center(
                      child: Text('No check-in records logged for this month.'),
                    ),
                  ] else ...[
                    ...data.history.map(
                      (record) => Container(
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: isDark ? AppColors.darkCard : AppColors.lightCard,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: AppColors.success.withValues(alpha: 0.15),
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: const Icon(Icons.check_circle_rounded, color: AppColors.success, size: 20),
                                ),
                                const SizedBox(width: 14),
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      record.date,
                                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      'In: ${record.checkInTime} ${record.checkOutTime != null ? "• Out: ${record.checkOutTime}" : ""}',
                                      style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                            StatusBadge(status: record.status, small: true),
                          ],
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
