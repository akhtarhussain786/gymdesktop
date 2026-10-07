import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/member_data_provider.dart';
import '../widgets/empty_state_view.dart';
import '../widgets/error_retry_view.dart';
import '../widgets/status_badge.dart';

class DietScreen extends StatefulWidget {
  const DietScreen({super.key});

  @override
  State<DietScreen> createState() => _DietScreenState();
}

class _DietScreenState extends State<DietScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MemberDataProvider>().fetchDiet();
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final provider = context.watch<MemberDataProvider>();
    final data = provider.diet;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Nutrition & Diet Plans'),
      ),
      body: Builder(
        builder: (context) {
          if (provider.loading && data == null) {
            return const Center(child: CircularProgressIndicator());
          }

          if (provider.error != null && data == null) {
            return ErrorRetryView(
              message: provider.error!,
              onRetry: () => provider.fetchDiet(refresh: true),
            );
          }

          if (data == null || !data.hasDietPlan) {
            return const EmptyStateView(
              title: 'No Diet Plan Assigned',
              message: 'Your personal trainer or nutritionist has not assigned a customized meal plan yet.',
              icon: Icons.restaurant_rounded,
            );
          }

          return RefreshIndicator(
            onRefresh: () => provider.fetchDiet(refresh: true),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ...data.dietPlans.map(
                    (diet) => Container(
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
                                  diet.planName,
                                  style: theme.textTheme.titleMedium?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 17,
                                  ),
                                ),
                              ),
                              StatusBadge(
                                status: '${diet.calories} kcal',
                                customColor: theme.colorScheme.secondary,
                                small: true,
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Target: ${diet.target} • Prescribed by ${diet.nutritionistName}',
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
                              diet.mealsJson,
                              style: const TextStyle(
                                fontFamily: 'monospace',
                                fontSize: 13,
                                height: 1.7,
                              ),
                            ),
                          ),
                        ],
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
