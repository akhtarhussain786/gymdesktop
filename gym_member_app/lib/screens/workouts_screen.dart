import 'package:flutter/material.dart';
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
        title: const Text('Add Workout Task'),
        content: TextField(
          controller: _todoController,
          autofocus: true,
          decoration: const InputDecoration(
            hintText: 'e.g. 4 sets Bench Press 80kg',
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              final text = _todoController.text.trim();
              if (text.isNotEmpty) {
                context.read<MemberDataProvider>().addWorkoutTodo(text);
                _todoController.clear();
                Navigator.pop(ctx);
              }
            },
            child: const Text('Add'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final provider = context.watch<MemberDataProvider>();
    final data = provider.workouts;

    return Scaffold(
      body: Builder(
        builder: (context) {
          if (provider.loading && data == null) {
            return const Center(child: CircularProgressIndicator());
          }

          if (provider.error != null && data == null) {
            return ErrorRetryView(
              message: provider.error!,
              onRetry: () => provider.fetchWorkouts(refresh: true),
            );
          }

          if (data == null || !data.hasWorkoutPlan) {
            return const EmptyStateView(
              title: 'No Workout Routine',
              message: 'Your personal trainer has not assigned a workout plan yet. Speak with your gym instructor to get customized routines.',
              icon: Icons.sports_gymnastics_rounded,
            );
          }

          return RefreshIndicator(
            onRefresh: () => provider.fetchWorkouts(refresh: true),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Workout Plans List
                  ...data.plans.map(
                    (plan) => Container(
                      margin: const EdgeInsets.only(bottom: 20),
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: isDark ? AppColors.darkCard : AppColors.lightCard,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Expanded(
                                child: Text(
                                  plan.planName,
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 17,
                                  ),
                                ),
                              ),
                              StatusBadge(status: plan.level, small: true),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Goal: ${plan.goal} • Prescribed by ${plan.trainerName}',
                            style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12.5),
                          ),
                          const SizedBox(height: 16),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: isDark ? AppColors.darkBg : AppColors.lightCardElevated,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                            ),
                            child: Text(
                              plan.scheduleJson,
                              style: const TextStyle(
                                fontFamily: 'monospace',
                                fontSize: 13,
                                height: 1.7,
                              ),
                            ),
                          ),
                          if (plan.trainerNotes != null && plan.trainerNotes!.isNotEmpty) ...[
                            const SizedBox(height: 12),
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: theme.primaryColor.withValues(alpha: 0.08),
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(color: theme.primaryColor.withValues(alpha: 0.3)),
                              ),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Icon(Icons.tips_and_updates_rounded, color: theme.primaryColor, size: 18),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      'Trainer Advice: ${plan.trainerNotes}',
                                      style: TextStyle(color: theme.primaryColor, fontSize: 12.5, fontWeight: FontWeight.w600),
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
                        'Workout Checklist',
                        style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      IconButton.filledTonal(
                        icon: const Icon(Icons.add_rounded, size: 20),
                        tooltip: 'Add Task',
                        onPressed: _showAddTodoDialog,
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),

                  if (data.todos.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(20),
                      alignment: Alignment.center,
                      child: const Text('No checklist items yet. Tap + to add exercises.'),
                    )
                  else
                    ...data.todos.map(
                      (todo) => Container(
                        margin: const EdgeInsets.only(bottom: 8),
                        decoration: BoxDecoration(
                          color: isDark ? AppColors.darkCard : AppColors.lightCard,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                        ),
                        child: CheckboxListTile(
                          value: todo.isCompleted,
                          title: Text(
                            todo.taskDesc,
                            style: TextStyle(
                              decoration: todo.isCompleted ? TextDecoration.lineThrough : null,
                              color: todo.isCompleted ? Colors.grey : null,
                              fontWeight: FontWeight.w600,
                              fontSize: 14,
                            ),
                          ),
                          onChanged: (_) {
                            provider.toggleWorkoutTodo(todo.id);
                          },
                        ),
                      ),
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
