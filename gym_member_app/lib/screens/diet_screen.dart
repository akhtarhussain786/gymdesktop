import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
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
    final provider = context.watch<MemberDataProvider>();
    final data = provider.diet;

    return Scaffold(
      backgroundColor: AppColors.darkBg,
      appBar: AppBar(
        backgroundColor: AppColors.darkBgDeep,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        title: Text(
          'NUTRITION & MACROS',
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
                valueColor: AlwaysStoppedAnimation<Color>(AppColors.cyan),
              ),
            );
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
              message: 'Your personal coach or nutritionist has not assigned a customized meal plan yet.',
              icon: Icons.restaurant_rounded,
            );
          }

          return RefreshIndicator(
            color: AppColors.cyan,
            backgroundColor: AppColors.darkCard,
            onRefresh: () => provider.fetchDiet(refresh: true),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ...data.dietPlans.map(
                    (diet) => Container(
                      margin: const EdgeInsets.only(bottom: 22),
                      padding: const EdgeInsets.all(20),
                      decoration: BoxDecoration(
                        color: AppColors.darkCard,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: AppColors.darkBorder),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.4),
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
                              Row(
                                children: [
                                  const Icon(Icons.restaurant_rounded, color: AppColors.cyan, size: 22),
                                  const SizedBox(width: 10),
                                  Text(
                                    diet.planName.toUpperCase(),
                                    style: GoogleFonts.outfit(
                                      color: AppColors.darkTextPrimary,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 17,
                                    ),
                                  ),
                                ],
                              ),
                              StatusBadge(
                                status: '${diet.calories} kcal',
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Target: ${diet.target} • Nutritionist: ${diet.nutritionistName}',
                            style: GoogleFonts.plusJakartaSans(
                              color: AppColors.darkTextSecondary,
                              fontSize: 12.5,
                            ),
                          ),
                          const SizedBox(height: 16),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: AppColors.darkBg,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: AppColors.darkBorder),
                            ),
                            child: Text(
                              diet.mealsJson,
                              style: GoogleFonts.plusJakartaSans(
                                color: AppColors.darkTextPrimary,
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
