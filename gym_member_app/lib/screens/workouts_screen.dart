import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/member_data_provider.dart';
import '../widgets/empty_state_view.dart';
import '../widgets/error_retry_view.dart';
import '../widgets/status_badge.dart';

class WorkoutsScreen extends StatefulWidget {
  const WorkoutsScreen({super.key});

  @override
  State<WorkoutsScreen> createState() => _WorkoutsScreenState();
}

class _WorkoutsScreenState extends State<WorkoutsScreen> {
  final _todoController = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MemberDataProvider>().fetchWorkouts();
    });
  }

  @override
  void dispose() {
    _todoController.dispose();
    super.dispose();
  }

  void _showAddTodoDialog() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.darkCard,
        title: Text(
          'Add Workout Exercise',
          style: GoogleFonts.outfit(
            color: AppColors.darkTextPrimary,
            fontWeight: FontWeight.w800,
          ),
        ),
        content: TextField(
          controller: _todoController,
          autofocus: true,
          style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextPrimary),
          decoration: const InputDecoration(
            hintText: 'e.g. 4 sets Bench Press 80kg',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel', style: TextStyle(color: AppColors.darkTextSecondary)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.lime,
              foregroundColor: const Color(0xFF05080D),
            ),
            onPressed: () {
              final text = _todoController.text.trim();
              if (text.isNotEmpty) {
                context.read<MemberDataProvider>().addWorkoutTodo(text);
                _todoController.clear();
                Navigator.pop(ctx);
              }
            },
            child: Text(
              'Add to Split',
              style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<MemberDataProvider>();
    final data = provider.workouts;

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: Navigator.canPop(context)
          ? AppBar(
              backgroundColor: AppColors.bgDeep(context),
              elevation: 0,
              title: Text(
                'WORKOUT REGIMEN',
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
              onRetry: () => provider.fetchWorkouts(refresh: true),
            );
          }

          if (data == null || (!data.hasWorkoutPlan && data.plans.isEmpty)) {
            return EmptyStateView(
              icon: Icons.fitness_center_rounded,
              title: 'No Workouts Assigned',
              message: 'Your personal workout plan will be assigned by your coach.',
              actionLabel: 'Refresh Plans',
              onAction: () => provider.fetchWorkouts(refresh: true),
            );
          }

          return RefreshIndicator(
            color: AppColors.lime,
            backgroundColor: AppColors.card(context),
            onRefresh: () => provider.fetchWorkouts(refresh: true),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Assigned Plans
                  ...data.plans.map(
                    (plan) => Container(
                      margin: const EdgeInsets.only(bottom: 22),
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: AppColors.card(context),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: AppColors.border(context)),
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
                            children: [
                              Expanded(
                                child: Row(
                                  children: [
                                    const Icon(Icons.fitness_center_rounded, color: AppColors.lime, size: 22),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        plan.planName.toUpperCase(),
                                        style: GoogleFonts.outfit(
                                          color: AppColors.textPrimary(context),
                                          fontWeight: FontWeight.w800,
                                          fontSize: 17,
                                        ),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 8),
                              StatusBadge(status: plan.level),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Goal: ${plan.goal} • Coach: ${plan.trainerName}',
                            style: GoogleFonts.plusJakartaSans(
                              color: AppColors.textSecondary(context),
                              fontSize: 12.5,
                            ),
                          ),
                          const SizedBox(height: 16),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: AppColors.bgDeep(context),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: AppColors.border(context)),
                            ),
                            child: Text(
                              plan.scheduleJson,
                              style: GoogleFonts.plusJakartaSans(
                                color: AppColors.textPrimary(context),
                                fontSize: 13,
                                height: 1.7,
                              ),
                            ),
                          ),
                          if (plan.trainerNotes != null && plan.trainerNotes!.isNotEmpty) ...[
                            const SizedBox(height: 14),
                            Container(
                              padding: const EdgeInsets.all(14),
                              decoration: BoxDecoration(
                                color: AppColors.lime.withValues(alpha: 0.08),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: AppColors.limeBorder),
                              ),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Icon(Icons.tips_and_updates_rounded, color: AppColors.lime, size: 18),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      'Coach Advice: ${plan.trainerNotes}',
                                      style: GoogleFonts.plusJakartaSans(
                                        color: AppColors.lime,
                                        fontSize: 12.5,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),

                  // Today's Workout Checklist Section
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'SESSION CHECKLIST',
                        style: GoogleFonts.outfit(
                          color: AppColors.darkTextPrimary,
                          fontWeight: FontWeight.w800,
                          fontSize: 16,
                          letterSpacing: 0.6,
                        ),
                      ),
                      IconButton(
                        style: IconButton.styleFrom(
                          backgroundColor: AppColors.lime.withValues(alpha: 0.15),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        icon: const Icon(Icons.add_rounded, color: AppColors.lime, size: 20),
                        tooltip: 'Add Exercise Task',
                        onPressed: _showAddTodoDialog,
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  if (data.todos.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(20),
                      alignment: Alignment.center,
                      child: Text(
                        'No checklist items yet. Tap + to add exercises.',
                        style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextMuted),
                      ),
                    )
                  else
                    ...data.todos.map(
                      (todo) => Container(
                        margin: const EdgeInsets.only(bottom: 10),
                        decoration: BoxDecoration(
                          color: AppColors.darkCard,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: todo.isCompleted ? AppColors.limeBorder : AppColors.darkBorder,
                          ),
                        ),
                        child: CheckboxListTile(
                          activeColor: AppColors.lime,
                          checkColor: const Color(0xFF05080D),
                          value: todo.isCompleted,
                          title: Text(
                            todo.taskDesc,
                            style: GoogleFonts.plusJakartaSans(
                              decoration: todo.isCompleted ? TextDecoration.lineThrough : null,
                              color: todo.isCompleted ? AppColors.darkTextMuted : AppColors.darkTextPrimary,
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                            ),
                          ),
                          onChanged: (_) {
                            provider.toggleWorkoutTodo(todo.id);
                          },
                        ),
                      ),
                    ),
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
